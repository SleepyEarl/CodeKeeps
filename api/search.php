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

$stmt = $pdo->prepare('SELECT id, original_name, file_size, mime_type, uploaded_at FROM files WHERE user_id = ? AND original_name LIKE ? ORDER BY uploaded_at DESC LIMIT 20');
$stmt->execute([$userId, "%$query%"]);
$files = $stmt->fetchAll();
foreach ($files as &$file) {
    $file['download_url'] = '../api/files.php?action=download&file_id=' . $file['id'];
}

echo json_encode(['success' => true, 'results' => $files]);
