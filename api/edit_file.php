<?php
require_once __DIR__ . '/../config/config.php';
$pdo = db_connect();
require_login();
$userId = get_current_user_id();

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$fileId = isset($input['file_id']) ? intval($input['file_id']) : 0;
$content = $input['content'] ?? null;
if (!$fileId || $content === null) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}
$stmt = $pdo->prepare('SELECT storage_name, original_name FROM files WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$fileId, $userId]);
$file = $stmt->fetch();
if (!$file) {
    echo json_encode(['success' => false, 'message' => 'File not found or permission denied']);
    exit;
}
$path = UPLOAD_DIR . $file['storage_name'];
if (!file_exists($path)) {
    echo json_encode(['success' => false, 'message' => 'File missing']);
    exit;
}
// Write content
if (file_put_contents($path, $content) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit;
}
log_activity($pdo, $userId, 'Edited file', 'file', $file['original_name']);
echo json_encode(['success' => true, 'message' => 'File saved']);
exit;
?>