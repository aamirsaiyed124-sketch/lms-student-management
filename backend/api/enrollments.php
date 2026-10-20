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
    $course_id = isset($_GET['course_id']) && is_numeric($_GET['course_id']) ? (int)$_GET['course_id'] : null;
    $student_id = isset($_GET['student_id']) && is_numeric($_GET['student_id']) ? (int)$_GET['student_id'] : null;
    $status = isset($_GET['status']) && $_GET['status'] !== 'All' ? trim($_GET['status']) : null;

    $query = "
        SELECT e.id, e.enrollment_date, e.status, e.grade, e.created_at,
               s.id as student_id, s.student_code, CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.email as student_email, s.department as student_department, s.semester as student_semester,
               c.id as course_id, c.course_code, c.title as course_title, c.credits, c.instructor_name, c.department as course_department
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN courses c ON e.course_id = c.id
        WHERE 1=1
    ";

    $params = [];
    $types = "";

    if ($course_id) {
        $query .= " AND e.course_id = ?";
        $params[] = $course_id;
        $types .= "i";
    }

    if ($student_id) {
        $query .= " AND e.student_id = ?";
        $params[] = $student_id;
        $types .= "i";
    }

    if ($status) {
        $query .= " AND e.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    $query .= " ORDER BY e.id DESC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $enrollments = [];
    while ($row = $result->fetch_assoc()) {
        $enrollments[] = $row;
    }
    $stmt->close();

    sendResponse(true, 'Enrollments fetched successfully', $enrollments);
}

function handlePost($conn) {
    $data = getJsonInput();

    if (empty($data['student_id']) || empty($data['course_id'])) {
        sendResponse(false, 'student_id and course_id are required', null, 400);
    }

    $student_id = (int)$data['student_id'];
    $course_id = (int)$data['course_id'];
    $enrollment_date = !empty($data['enrollment_date']) ? sanitizeString($data['enrollment_date']) : date('Y-m-d');
    $status = in_array($data['status'] ?? 'Enrolled', ['Enrolled', 'Completed', 'Dropped']) ? $data['status'] : 'Enrolled';
    $grade = in_array($data['grade'] ?? 'Pending', ['A+', 'A', 'B+', 'B', 'C', 'D', 'F', 'Pending']) ? $data['grade'] : 'Pending';

    // Verify student and course existence
    $checkStu = $conn->prepare("SELECT id FROM students WHERE id = ?");
    $checkStu->bind_param("i", $student_id);
    $checkStu->execute();
    if ($checkStu->get_result()->num_rows === 0) {
        $checkStu->close();
        sendResponse(false, 'Selected student does not exist', null, 404);
    }
    $checkStu->close();

    $checkCourse = $conn->prepare("SELECT id FROM courses WHERE id = ?");
    $checkCourse->bind_param("i", $course_id);
    $checkCourse->execute();
    if ($checkCourse->get_result()->num_rows === 0) {
        $checkCourse->close();
        sendResponse(false, 'Selected course does not exist', null, 404);
    }
    $checkCourse->close();

    // Check duplicate enrollment
    $checkDup = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
    $checkDup->bind_param("ii", $student_id, $course_id);
    $checkDup->execute();
    if ($checkDup->get_result()->num_rows > 0) {
        $checkDup->close();
        sendResponse(false, 'This student is already enrolled in this course', null, 409);
    }
    $checkDup->close();

    try {
        $stmt = $conn->prepare("
            INSERT INTO enrollments (student_id, course_id, enrollment_date, status, grade)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisss", $student_id, $course_id, $enrollment_date, $status, $grade);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        sendResponse(true, 'Student enrolled successfully', ['id' => $newId], 201);
    } catch (mysqli_sql_exception $e) {
        sendResponse(false, 'Error enrolling student: ' . $e->getMessage(), null, 500);
    }
}

function handlePut($conn) {
    $data = getJsonInput();
    $id = isset($data['id']) ? (int)$data['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

    if ($id <= 0) {
        sendResponse(false, 'Valid enrollment ID is required', null, 400);
    }

    $status = in_array($data['status'] ?? 'Enrolled', ['Enrolled', 'Completed', 'Dropped']) ? $data['status'] : 'Enrolled';
    $grade = in_array($data['grade'] ?? 'Pending', ['A+', 'A', 'B+', 'B', 'C', 'D', 'F', 'Pending']) ? $data['grade'] : 'Pending';

    try {
        $stmt = $conn->prepare("UPDATE enrollments SET status = ?, grade = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $grade, $id);
        $stmt->execute();
        $stmt->close();

        sendResponse(true, 'Enrollment updated successfully', ['id' => $id]);
    } catch (mysqli_sql_exception $e) {
        sendResponse(false, 'Error updating enrollment: ' . $e->getMessage(), null, 500);
    }
}

function handleDelete($conn) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        $data = getJsonInput();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
    }

    if ($id <= 0) {
        sendResponse(false, 'Valid enrollment ID is required', null, 400);
    }

    try {
        $stmt = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            sendResponse(true, 'Enrollment removed successfully');
        } else {
            sendResponse(false, 'Enrollment not found or already deleted', null, 404);
        }
    } catch (mysqli_sql_exception $e) {
        sendResponse(false, 'Error deleting enrollment: ' . $e->getMessage(), null, 500);
    }
}
