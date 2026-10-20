<?php
// ==========================================================
// Session-based authentication and role authorization
// ==========================================================

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

function requireLogin() {
    $user = getCurrentUser();
    if (!$user) {
        sendResponse(false, 'Please login first', null, 401);
    }
    return $user;
}

function requireAdmin() {
    $user = requireLogin();
    if (($user['role'] ?? '') !== 'admin') {
        sendResponse(false, 'Admin access required', null, 403);
    }
    return $user;
}
