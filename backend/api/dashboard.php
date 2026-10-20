<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    sendResponse(false, 'Method Not Allowed', null, 405);
}

$conn = getDbConnection();
requireLogin();

try {
    // 1. Core Summary Metrics
    $totalStudents = $conn->query("SELECT COUNT(*) AS total FROM students")->fetch_assoc()['total'] ?? 0;
    $activeStudents = $conn->query("SELECT COUNT(*) AS total FROM students WHERE status = 'Active'")->fetch_assoc()['total'] ?? 0;
    $totalCourses = $conn->query("SELECT COUNT(*) AS total FROM courses")->fetch_assoc()['total'] ?? 0;
    $totalEnrollments = $conn->query("SELECT COUNT(*) AS total FROM enrollments")->fetch_assoc()['total'] ?? 0;
    
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM attendance WHERE attendance_date = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $todayAttendance = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    // 2. Department Breakdown
    $deptResult = $conn->query("SELECT department, COUNT(*) AS student_count FROM students GROUP BY department ORDER BY student_count DESC");
    $departments = [];
    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = $row;
    }

    // 3. Recent Enrollments
    $enrollSql = "SELECT e.id, e.enrollment_date, e.status, e.grade,
                         s.student_code, CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.department,
                         c.course_code, c.title AS course_title
                  FROM enrollments e
                  JOIN students s ON e.student_id = s.id
                  JOIN courses c ON e.course_id = c.id
                  ORDER BY e.id DESC LIMIT 5";
    $enrollResult = $conn->query($enrollSql);
    $recentEnrollments = [];
    while ($row = $enrollResult->fetch_assoc()) {
        $recentEnrollments[] = $row;
    }

    // 4. Latest Announcements
    $announcementsResult = $conn->query("SELECT * FROM announcements ORDER BY id DESC LIMIT 4");
    $announcements = [];
    while ($row = $announcementsResult->fetch_assoc()) {
        $announcements[] = $row;
    }

    sendResponse(true, 'Dashboard metrics fetched successfully', [
        'stats' => [
            'total_students' => (int)$totalStudents,
            'active_students' => (int)$activeStudents,
            'total_courses' => (int)$totalCourses,
            'total_enrollments' => (int)$totalEnrollments,
            'today_attendance' => (int)$todayAttendance,
        ],
        'departments' => $departments,
        'recent_enrollments' => $recentEnrollments,
        'announcements' => $announcements
    ]);

} catch (mysqli_sql_exception $e) {
    sendResponse(false, 'Failed to fetch dashboard data: ' . $e->getMessage(), null, 500);
}
