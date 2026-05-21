<?php
require_once __DIR__ . '/../config/config.php';
$pdo = db_connect();

header('Content-Type: application/json');
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$userId = get_current_user_id();
$query = trim($_GET['q'] ?? '');
if (!$query) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$filter = trim($_GET['filter'] ?? 'all');

$stmt = $pdo->prepare('SELECT id, original_name, file_size, mime_type, uploaded_at FROM files WHERE user_id = ? AND original_name LIKE ? ORDER BY uploaded_at DESC LIMIT 200');
$stmt->execute([$userId, "%$query%"]);
$filesAll = $stmt->fetchAll();

$files = array_values(array_filter($filesAll, function($file) use ($filter) {
    $mime = $file['mime_type'] ?? '';
    $name = strtolower($file['original_name']);
    if ($filter === 'all') return true;
    if ($filter === 'images') return strpos($mime, 'image/') === 0;
    if ($filter === 'code') {
        if (strpos($mime, 'text/') === 0) return true;
        return preg_match('/\.(php|js|py|html|css|java|c|cpp|rb|go|ts)$/', $name);
    }
    if ($filter === 'documents') {
        return preg_match('/\.(pdf|doc|docx|xls|xlsx|txt|md)$/', $name) || strpos($mime, 'application/') === 0;
    }
    return true;
}));

foreach ($files as &$file) {
    $file['download_url'] = '../api/files.php?action=download&file_id=' . $file['id'];
}

echo json_encode(['success' => true, 'results' => $files]);
