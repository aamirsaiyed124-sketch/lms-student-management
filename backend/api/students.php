<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

$conn = getDbConnection();
requireLogin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    requireAdmin();
}

switch ($method) {
    case 'GET':
        handleGet($conn);
        break;
    case 'POST':
        handlePost($conn);
        break;
    case 'PUT':
        handlePut($conn);
        break;
    case 'DELETE':
        handleDelete($conn);
        break;
    default:
        sendResponse(false, 'Method Not Allowed', null, 405);
}

function handleGet($conn) {
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $studentId = (int)$_GET['id'];
        
        $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$student) {
            sendResponse(false, 'Student not found', null, 404);
        }

        // Fetch enrolled courses
        $courseStmt = $conn->prepare("
            SELECT e.id as enrollment_id, e.enrollment_date, e.status as enrollment_status, e.grade,
                   c.id as course_id, c.course_code, c.title, c.credits, c.instructor_name, c.department
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = ?
            ORDER BY c.course_code ASC
        ");
        $courseStmt->bind_param("i", $studentId);
        $courseStmt->execute();
        $coursesResult = $courseStmt->get_result();
        $enrolledCourses = [];
        while ($row = $coursesResult->fetch_assoc()) {
            $enrolledCourses[] = $row;
        }
        $courseStmt->close();

        // Attendance stats
        $attStmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_classes,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
            FROM attendance
            WHERE student_id = ?
        ");
        $attStmt->bind_param("i", $studentId);
        $attStmt->execute();
        $attendanceStats = $attStmt->get_result()->fetch_assoc();
        $attStmt->close();

        $totalClasses = (int)($attendanceStats['total_classes'] ?? 0);
        $present = (int)($attendanceStats['present_count'] ?? 0);
        $attendanceRate = $totalClasses > 0 ? round(($present / $totalClasses) * 100, 1) : 100.0;

        $student['enrolled_courses'] = $enrolledCourses;
        $student['attendance_stats'] = [
            'total_classes' => $totalClasses,
            'present' => $present,
            'late' => (int)($attendanceStats['late_count'] ?? 0),
            'absent' => (int)($attendanceStats['absent_count'] ?? 0),
            'percentage' => $attendanceRate
        ];

        sendResponse(true, 'Student profile fetched', $student);
    }

    // List all students with search & filter
    $search = isset($_GET['search']) ? '%' . trim($_GET['search']) . '%' : null;
    $department = isset($_GET['department']) && $_GET['department'] !== 'All' ? trim($_GET['department']) : null;
    $status = isset($_GET['status']) && $_GET['status'] !== 'All' ? trim($_GET['status']) : null;

    $query = "SELECT s.*, 
                     COUNT(DISTINCT e.id) as enrolled_count
              FROM students s
              LEFT JOIN enrollments e ON s.id = e.student_id
              WHERE 1=1";
    $params = [];
    $types = "";

    if ($search) {
        $query .= " AND (s.student_code LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= "ssss";
    }

    if ($department) {
        $query .= " AND s.department = ?";
        $params[] = $department;
        $types .= "s";
    }

    if ($status) {
        $query .= " AND s.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    $query .= " GROUP BY s.id ORDER BY s.id DESC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();

    sendResponse(true, 'Students fetched successfully', $students);
}

function handlePost($conn) {
    $data = getJsonInput();

    // Validation
    $required = ['student_code', 'first_name', 'last_name', 'email', 'phone', 'gender', 'dob', 'department', 'semester'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendResponse(false, "Field '{$field}' is required", null, 400);
        }
    }

    $student_code = sanitizeString($data['student_code']);
    $first_name = sanitizeString($data['first_name']);
    $last_name = sanitizeString($data['last_name']);
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    $phone = sanitizeString($data['phone']);
    $gender = in_array($data['gender'], ['Male', 'Female', 'Other']) ? $data['gender'] : 'Male';
    $dob = sanitizeString($data['dob']);
    $department = sanitizeString($data['department']);
    $semester = (int)$data['semester'];
    $status = in_array($data['status'] ?? 'Active', ['Active', 'Inactive', 'Suspended']) ? $data['status'] : 'Active';
    $address = sanitizeString($data['address'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email address', null, 400);
    }

    // Check unique email or code
    $checkStmt = $conn->prepare("SELECT id FROM students WHERE student_code = ? OR email = ?");
    $checkStmt->bind_param("ss", $student_code, $email);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        sendResponse(false, 'Student Code or Email already exists', null, 409);
    }
    $checkStmt->close();

    try {
        $stmt = $conn->prepare("
            INSERT INTO students (student_code, first_name, last_name, email, phone, gender, dob, department, semester, status, address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssssiss", $student_code, $first_name, $last_name, $email, $phone, $gender, $dob, $department, $semester, $status, $address);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        sendResponse(true, 'Student registered successfully', ['id' => $newId], 201);
    } catch (mysqli_sql_exception $e) {
        sendResponse(false, 'Error creating student: ' . $e->getMessage(), null, 500);
    }
}

function handlePut($conn) {
    $data = getJsonInput();
    $id = isset($data['id']) ? (int)$data['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

    if ($id <= 0) {
        sendResponse(false, 'Valid student ID is required', null, 400);
    }

    $required = ['student_code', 'first_name', 'last_name', 'email', 'phone', 'gender', 'dob', 'department', 'semester'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendResponse(false, "Field '{$field}' is required", null, 400);
        }
    }

    $student_code = sanitizeString($data['student_code']);
    $first_name = sanitizeString($data['first_name']);
    $last_name = sanitizeString($data['last_name']);
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    $phone = sanitizeString($data['phone']);
    $gender = in_array($data['gender'], ['Male', 'Female', 'Other']) ? $data['gender'] : 'Male';
    $dob = sanitizeString($data['dob']);
    $department = sanitizeString($data['department']);
    $semester = (int)$data['semester'];
    $status = in_array($data['status'] ?? 'Active', ['Active', 'Inactive', 'Suspended']) ? $data['status'] : 'Active';
    $address = sanitizeString($data['address'] ?? '');

    // Check duplicate code or email for other students
    $checkStmt = $conn->prepare("SELECT id FROM students WHERE (student_code = ? OR email = ?) AND id != ?");
    $checkStmt->bind_param("ssi", $student_code, $email, $id);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        sendResponse(false, 'Student Code or Email already used by another student', null, 409);
    }
    $checkStmt->close();

    try {
        $stmt = $conn->prepare("
            UPDATE students
            SET student_code = ?, first_name = ?, last_name = ?, email = ?, phone = ?, gender = ?, dob = ?, department = ?, semester = ?, status = ?, address = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssssssssissi", $student_code, $first_name, $last_name, $email, $phone, $gender, $dob, $department, $semester, $status, $address, $id);
        $stmt->execute();
        $stmt->close();

        sendResponse(true, 'Student updated successfully', ['id' => $id]);
    } catch (mysqli_sql_exception $e) {
        sendResponse(false, 'Error updating student: ' . $e->getMessage(), null, 500);
    }
}

function handleDelete($conn) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        $data = getJsonInput();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
    }

    if ($id <= 0) {
        sendResponse(false, 'Valid student ID is required', null, 400);
    }

    try {
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            sendResponse(true, 'Student and related records deleted successfully');
        } else {
            sendResponse(false, 'Student not found or already deleted', null, 404);
        }
    } catch (mysqli_sql_exception $e) {
        sendResponse(false, 'Error deleting student: ' . $e->getMessage(), null, 500);
    }
}
