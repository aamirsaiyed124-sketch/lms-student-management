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
    default:
        sendResponse(false, 'Method Not Allowed', null, 405);
}

function handleGet($conn) {
    // 1. Get attendance report for a specific course & date, showing all enrolled students
    if (isset($_GET['course_id']) && is_numeric($_GET['course_id'])) {
        $course_id = (int)$_GET['course_id'];
        $date = !empty($_GET['date']) ? sanitizeString($_GET['date']) : date('Y-m-d');

        // Fetch all enrolled students in this course
        $stmt = $conn->prepare("
            SELECT s.id as student_id, s.student_code, CONCAT(s.first_name, ' ', s.last_name) as student_name,
                   s.department, s.email,
                   COALESCE(a.status, 'Present') as attendance_status,
                   COALESCE(a.remarks, '') as remarks,
                   CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END as is_marked
            FROM enrollments e
            JOIN students s ON e.student_id = s.id
            LEFT JOIN attendance a ON a.student_id = s.id AND a.course_id = ? AND a.attendance_date = ?
            WHERE e.course_id = ? AND e.status = 'Enrolled'
            ORDER BY s.first_name ASC
        ");
        $stmt->bind_param("isi", $course_id, $date, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $roster = [];
        while ($row = $result->fetch_assoc()) {
            $roster[] = $row;
        }
        $stmt->close();

        sendResponse(true, 'Attendance sheet fetched', [
            'course_id' => $course_id,
            'attendance_date' => $date,
            'students' => $roster
        ]);
    }

    // 2. Fetch history for a student
    if (isset($_GET['student_id']) && is_numeric($_GET['student_id'])) {
        $student_id = (int)$_GET['student_id'];
        $stmt = $conn->prepare("
            SELECT a.id, a.attendance_date, a.status, a.remarks,
                   c.course_code, c.title as course_title
            FROM attendance a
            JOIN courses c ON a.course_id = c.id
            WHERE a.student_id = ?
            ORDER BY a.attendance_date DESC
            LIMIT 50
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        sendResponse(true, 'Student attendance history fetched', $records);
    }

    // 3. List recent attendance logs
    $recentStmt = $conn->query("
        SELECT a.id, a.attendance_date, a.status, a.remarks,
               s.student_code, CONCAT(s.first_name, ' ', s.last_name) as student_name,
               c.course_code, c.title as course_title
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN courses c ON a.course_id = c.id
        ORDER BY a.attendance_date DESC, a.id DESC
        LIMIT 30
    ");
    $recentLogs = $recentStmt->fetch_all(MYSQLI_ASSOC);

    sendResponse(true, 'Recent attendance logs fetched', $recentLogs);
}

function handlePost($conn) {
    $data = getJsonInput();

    if (empty($data['course_id']) || empty($data['attendance_date']) || !isset($data['records']) || !is_array($data['records'])) {
        sendResponse(false, 'course_id, attendance_date, and records array are required', null, 400);
    }

    $course_id = (int)$data['course_id'];
    $date = sanitizeString($data['attendance_date']);
    $records = $data['records'];

    if (empty($records)) {
        sendResponse(false, 'Records list cannot be empty', null, 400);
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("
            INSERT INTO attendance (student_id, course_id, attendance_date, status, remarks)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                remarks = VALUES(remarks)
        ");

        $savedCount = 0;
        foreach ($records as $item) {
            $student_id = (int)($item['student_id'] ?? 0);
            $status = in_array($item['status'] ?? 'Present', ['Present', 'Absent', 'Late', 'Excused']) ? $item['status'] : 'Present';
            $remarks = sanitizeString($item['remarks'] ?? '');

            if ($student_id > 0) {
                $stmt->bind_param("iisss", $student_id, $course_id, $date, $status, $remarks);
                $stmt->execute();
                $savedCount++;
            }
        }
        $stmt->close();

        $conn->commit();
        sendResponse(true, "Successfully recorded attendance for {$savedCount} students", ['count' => $savedCount]);
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        sendResponse(false, 'Transaction failed: ' . $e->getMessage(), null, 500);
    }
}
