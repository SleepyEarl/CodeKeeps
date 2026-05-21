<?php
require_once __DIR__ . '/../config/config.php';
$pdo = db_connect();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_GET['action'] !== 'list') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$userId = get_current_user_id();
$action = $_GET['action'] ?? '';

function cleanup_folder($pdo, $userId, $folderId) {
    $stmt = $pdo->prepare('SELECT id FROM folders WHERE parent_id = ? AND user_id = ?');
    $stmt->execute([$folderId, $userId]);
    $children = $stmt->fetchAll();
    foreach ($children as $child) {
        cleanup_folder($pdo, $userId, $child['id']);
    }
    $stmt = $pdo->prepare('SELECT storage_name FROM files WHERE folder_id = ? AND user_id = ?');
    $stmt->execute([$folderId, $userId]);
    while ($file = $stmt->fetch()) {
        $path = UPLOAD_DIR . $file['storage_name'];
        if (file_exists($path)) unlink($path);
    }
    $stmt = $pdo->prepare('DELETE FROM files WHERE folder_id = ? AND user_id = ?');
    $stmt->execute([$folderId, $userId]);
    $stmt = $pdo->prepare('DELETE FROM folders WHERE id = ? AND user_id = ?');
    $stmt->execute([$folderId, $userId]);
}

$input = json_decode(file_get_contents('php://input'), true);

if ($action === 'create') {
    $name = trim($input['folder_name'] ?? '');
    $parentId = isset($input['parent_id']) && is_numeric($input['parent_id']) ? intval($input['parent_id']) : null;
    if (!$name) {
        echo json_encode(['success' => false, 'message' => 'Folder name is required']);
        exit;
    }
    if ($parentId) {
        $stmt = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?');
        $stmt->execute([$parentId, $userId]);
        if (!$stmt->fetch()) {
            $parentId = null;
        }
    }
    $stmt = $pdo->prepare('INSERT INTO folders (user_id, name, parent_id, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$userId, $name, $parentId]);
    log_activity($pdo, $userId, 'Created folder', 'folder', $name);
    echo json_encode(['success' => true, 'message' => 'Folder created']);
    exit;
}

if ($action === 'delete') {
    $folderId = isset($input['folder_id']) ? intval($input['folder_id']) : 0;
    if (!$folderId) {
        echo json_encode(['success' => false, 'message' => 'Folder not found']);
        exit;
    }
    cleanup_folder($pdo, $userId, $folderId);
    log_activity($pdo, $userId, 'Deleted folder', 'folder', 'ID: ' . $folderId);
    echo json_encode(['success' => true, 'message' => 'Folder deleted']);
    exit;
}

if ($action === 'move') {
    $fileId = isset($input['file_id']) ? intval($input['file_id']) : 0;
    $folderId = isset($input['folder_id']) ? intval($input['folder_id']) : null;
    if (!$fileId || !$folderId) {
        echo json_encode(['success' => false, 'message' => 'Missing file or folder id']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT id FROM files WHERE id = ? AND user_id = ?');
    $stmt->execute([$fileId, $userId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?');
    $stmt->execute([$folderId, $userId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Folder not found']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE files SET folder_id = ? WHERE id = ?');
    $stmt->execute([$folderId, $fileId]);
    log_activity($pdo, $userId, 'Moved file', 'file', 'File ID: ' . $fileId . ' to Folder ID: ' . $folderId);
    echo json_encode(['success' => true, 'message' => 'File moved']);
    exit;
}

if ($action === 'list') {
    $stmt = $pdo->prepare('SELECT id, name, parent_id FROM folders WHERE user_id = ? ORDER BY name ASC');
    $stmt->execute([$userId]);
    $folders = $stmt->fetchAll();
    echo json_encode(['success' => true, 'folders' => $folders]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action not supported']);
