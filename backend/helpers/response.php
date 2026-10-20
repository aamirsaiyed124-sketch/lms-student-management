<?php
// ==========================================================
// Response & Input Utility Helpers
// ==========================================================

function sendResponse($success, $message, $data = null, $statusCode = 200) {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

function getJsonInput() {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return $_POST;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function sanitizeString($val) {
    return trim(htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8'));
}
