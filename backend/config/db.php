<?php
// ==========================================================
// Database Configuration using MySQLi
// Ready for Production Solutions
// ==========================================================

// Enable mysqli exceptions for modern error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lms_student_db');
define('DB_PORT', 3306);

function getDbConnection() {
    static $conn = null;

    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            $conn->set_charset("utf8mb4");
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Database connection error. Please verify MySQL service is running.',
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    return $conn;
}
