<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$conn = getDbConnection();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Public user registration. Admin accounts can only be provisioned by an administrator/database owner.
if ($action === 'register' && $method === 'POST') {
    $data = getJsonInput();
    $fullName = trim((string)($data['full_name'] ?? ''));
    $username = trim((string)($data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');

    if ($fullName === '' || mb_strlen($fullName) > 100) {
        sendResponse(false, 'Full name is required and must be 100 characters or fewer.', null, 400);
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        sendResponse(false, 'Username must be 3-50 characters and use only letters, numbers, dot, underscore or hyphen.', null, 400);
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        sendResponse(false, 'Password must be between 8 and 72 characters.', null, 400);
    }

    $check = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $check->bind_param('s', $username);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();
    if ($exists) {
        sendResponse(false, 'This username is already taken. Please choose another.', null, 409);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'user';
    $status = 'Active';
    $stmt = $conn->prepare('INSERT INTO users (username, full_name, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $username, $fullName, $passwordHash, $role, $status);
    $stmt->execute();
    $newId = (int)$stmt->insert_id;
    $stmt->close();

    sendResponse(true, 'Registration successful. You can now log in.', ['id' => $newId, 'username' => $username, 'full_name' => $fullName, 'role' => 'user']);
}

if ($action === 'login' && $method === 'POST') {
    $data = getJsonInput();
    $username = trim((string)($data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $role = strtolower(trim((string)($data['role'] ?? '')));

    if ($username === '' || $password === '' || !in_array($role, ['admin', 'user'], true)) {
        sendResponse(false, 'Username, password and valid role are required', null, 400);
    }

    $stmt = $conn->prepare('SELECT id, username, full_name, password_hash, role, status FROM users WHERE username = ? AND role = ? LIMIT 1');
    $stmt->bind_param('ss', $username, $role);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || $user['status'] !== 'Active' || !password_verify($password, $user['password_hash'])) {
        sendResponse(false, 'Invalid username, password or account role', null, 401);
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'role' => $user['role']
    ];

    sendResponse(true, 'Login successful', $_SESSION['user']);
}

if ($action === 'me' && $method === 'GET') {
    if (!empty($_SESSION['user'])) {
        sendResponse(true, 'Authenticated', $_SESSION['user']);
    }
    sendResponse(false, 'Not authenticated', null, 401);
}

if ($action === 'logout' && $method === 'POST') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    sendResponse(true, 'Logged out successfully');
}

sendResponse(false, 'Invalid authentication request', null, 400);
