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
        $courseId = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->bind_param("i", $courseId);
        $stmt->execute();
        $course = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$course) {
            sendResponse(false, 'Course not found', null, 404);
        }

        // Fetch enrolled students
        $stuStmt = $conn->prepare("
            SELECT e.id as enrollment_id, e.enrollment_date, e.status, e.grade,
                   s.id as student_id, s.student_code, CONCAT(s.first_name, ' ', s.last_name) as student_name,
                   s.email, s.department, s.semester
            FROM enrollments e
            JOIN students s ON e.student_id = s.id
            WHERE e.course_id = ?
            ORDER BY s.first_name ASC
        ");
        $stuStmt->bind_param("i", $courseId);
        $stuStmt->execute();
        $studentsResult = $stuStmt->get_result();
        $enrolledStudents = [];
        while ($row = $studentsResult->fetch_assoc()) {
            $enrolledStudents[] = $row;
        }
        $stuStmt->close();

        $course['enrolled_students'] = $enrolledStudents;
        sendResponse(true, 'Course details fetched', $course);
    }

    $department = isset($_GET['department']) && $_GET['department'] !== 'All' ? trim($_GET['department']) : null;
    $search = isset($_GET['search']) ? '%' . trim($_GET['search']) . '%' : null;

    $query = "SELECT c.*, 
                     COUNT(e.id) as enrolled_count
              FROM courses c
              LEFT JOIN enrollments e ON c.id = e.course_id
              WHERE 1=1";
    $params = [];
    $types = "";

    if ($department) {
        $query .= " AND c.department = ?";
        $params[] = $department;
        $types .= "s";
    }

    if ($search) {
        $query .= " AND (c.course_code LIKE ? OR c.title LIKE ? OR c.instructor_name LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= "sss";
    }

    $query .= " GROUP BY c.id ORDER BY c.id DESC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();

    sendResponse(true, 'Courses fetched successfully', $courses);
}

function handlePost($conn) {
    $data = getJsonInput();

    $required = ['course_code', 'title', 'credits', 'instructor_name', 'department', 'semester'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendResponse(false, "Field '{$field}' is required", null, 400);
        }
    }

    $course_code = strtoupper(sanitizeString($data['course_code']));
    $title = sanitizeString($data['title']);
    $description = sanitizeString($data['description'] ?? '');
    $credits = (int)$data['credits'];
    $instructor_name = sanitizeString($data['instructor_name']);
    $department = sanitizeString($data['department']);
    $semester = (int)$data['semester'];

    $checkStmt = $conn->prepare("SELECT id FROM courses WHERE course_code = ?");
    $checkStmt->bind_param("s", $course_code);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        sendResponse(false, 'Course Code already exists', null, 409);
    }
    $checkStmt->close();

    try {
        $stmt = $conn->prepare("
            INSERT INTO courses (course_code, title, description, credits, instructor_name, department, semester)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssisss", $course_code, $title, $description, $credits, $instructor_name, $department, $semester);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        sendResponse(true, 'Course created successfully', ['id' => $newId], 201);
    } catch (mysqli_sql_exception $e) {
        sendResponse(false, 'Error creating course: ' . $e->getMessage(), null, 500);
    }
}

function handlePut($conn) {
    $data = getJsonInput();
    $id = isset($data['id']) ? (int)$data['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

    if ($id <= 0) {
        sendResponse(false, 'Valid course ID is required', null, 400);
    }

    $required = ['course_code', 'title', 'credits', 'instructor_name', 'department', 'semester'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendResponse(false, "Field '{$field}' is required", null, 400);
        }
    }

    $course_code = strtoupper(sanitizeString($data['course_code']));
    $title = sanitizeString($data['title']);
    $description = sanitizeString($data['description'] ?? '');
    $credits = (int)$data['credits'];
    $instructor_name = sanitizeString($data['instructor_name']);
    $department = sanitizeString($data['department']);
    $semester = (int)$data['semester'];

    $checkStmt = $conn->prepare("SELECT id FROM courses WHERE course_code = ? AND id != ?");
    $checkStmt->bind_param("si", $course_code, $id);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        sendResponse(false, 'Course code is already used by another course', null, 409);
    }
    $checkStmt->close();

    try {
        $stmt = $conn->prepare("
            UPDATE courses
            SET course_code = ?, title = ?, description = ?, credits = ?, instructor_name = ?, department = ?, semester = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssisssi", $course_code, $title, $description, $credits, $instructor_name, $department, $semester, $id);
        $stmt->execute();
        $stmt->close();

        sendResponse(true, 'Course updated successfully', ['id' => $id]);
    } catch (mysqli_sql_exception $e) {
        sendResponse(false, 'Error updating course: ' . $e->getMessage(), null, 500);
    }
}

function handleDelete($conn) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        $data = getJsonInput();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
    }

    if ($id <= 0) {
        sendResponse(false, 'Valid course ID is required', null, 400);
    }

    try {
        $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            sendResponse(true, 'Course deleted successfully');
        } else {
            sendResponse(false, 'Course not found or already deleted', null, 404);
        }
    } catch (mysqli_sql_exception $e) {
        sendResponse(false, 'Error deleting course: ' . $e->getMessage(), null, 500);
    }
}
