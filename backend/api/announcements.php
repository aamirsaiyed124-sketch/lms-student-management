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
    case 'DELETE':
        handleDelete($conn);
        break;
    default:
        sendResponse(false, 'Method Not Allowed', null, 405);
}

function handleGet($conn) {
    $dept = isset($_GET['department']) && $_GET['department'] !== 'All' ? trim($_GET['department']) : null;
    $priority = isset($_GET['priority']) && $_GET['priority'] !== 'All' ? trim($_GET['priority']) : null;

    $query = "SELECT * FROM announcements WHERE 1=1";
    $params = [];
    $types = "";

    if ($dept) {
        $query .= " AND (target_department = ? OR target_department = 'All')";
        $params[] = $dept;
        $types .= "s";
    }

    if ($priority) {
        $query .= " AND priority = ?";
        $params[] = $priority;
        $types .= "s";
    }

    $query .= " ORDER BY id DESC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    sendResponse(true, 'Announcements fetched successfully', $items);
}

function handlePost($conn) {
    $data = getJsonInput();

    if (empty($data['title']) || empty($data['content'])) {
        sendResponse(false, 'Title and Content are required', null, 400);
    }

    $title = sanitizeString($data['title']);
    $content = sanitizeString($data['content']);
    $priority = in_array($data['priority'] ?? 'Medium', ['Low', 'Medium', 'High', 'Urgent']) ? $data['priority'] : 'Medium';
    $target_department = !empty($data['target_department']) ? sanitizeString($data['target_department']) : 'All';
    $posted_by = !empty($data['posted_by']) ? sanitizeString($data['posted_by']) : 'Academic Office';

    try {
        $stmt = $conn->prepare("
            INSERT INTO announcements (title, content, priority, target_department, posted_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssss", $title, $content, $priority, $target_department, $posted_by);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        sendResponse(true, 'Announcement posted successfully', ['id' => $newId], 201);
    } catch (mysqli_sql_exception $e) {
        sendResponse(false, 'Failed to publish announcement: ' . $e->getMessage(), null, 500);
    }
}

function handleDelete($conn) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        $data = getJsonInput();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
    }

    if ($id <= 0) {
        sendResponse(false, 'Valid notice ID is required', null, 400);
    }

    try {
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            sendResponse(true, 'Announcement deleted successfully');
        } else {
            sendResponse(false, 'Announcement not found', null, 404);
        }
    } catch (mysqli_sql_exception $e) {
        sendResponse(false, 'Failed to delete announcement: ' . $e->getMessage(), null, 500);
    }
}
