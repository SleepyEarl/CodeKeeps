<?php
require_once __DIR__ . '/../config/config.php';
$pdo = db_connect();

$action = $_GET['action'] ?? '';
$userId = get_current_user_id();

if ($action !== 'download' && !is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function sanitize_text($value) {
    return trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
}

if ($action === 'list') {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare('SELECT id, name, parent_id, (SELECT COUNT(*) FROM files WHERE folder_id = folders.id) AS file_count FROM folders WHERE user_id = ? AND parent_id IS NULL ORDER BY name ASC');
    $stmt->execute([$userId]);
    $folders = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT id, folder_id, original_name, file_size, mime_type, uploaded_at FROM files WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 50');
    $stmt->execute([$userId]);
    $files = $stmt->fetchAll();
    foreach ($files as &$file) {
        $file['download_url'] = '../api/files.php?action=download&file_id=' . $file['id'];
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) AS total_files, SUM(file_size) AS used_storage FROM files WHERE user_id = ?');
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    $totalFiles = $stats['total_files'] ?? 0;
    $usedBytes = $stats['used_storage'] ?? 0;
    $usedStorageText = $usedBytes ? number_format($usedBytes / 1024 / 1024, 2) . ' MB' : '0 MB';
    $summary = [
        'total-files' => $totalFiles,
        'total-folders' => count($folders),
        'used-storage' => $usedStorageText,
    ];

    $stmt = $pdo->prepare('SELECT action, target_type, target_name, created_at FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 8');
    $stmt->execute([$userId]);
    $activity = $stmt->fetchAll();

    echo json_encode(['success' => true, 'folders' => $folders, 'files' => $files, 'stats' => $summary, 'activity' => $activity]);
    exit;
}

if ($action === 'upload') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid upload request']);
        exit;
    }
    if (empty($_FILES['files'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No files uploaded']);
        exit;
    }
    $folderId = isset($_POST['folder_id']) && is_numeric($_POST['folder_id']) ? intval($_POST['folder_id']) : null;
    $allowedFolders = $folderId ? $pdo->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?') : null;
    if ($folderId) {
        $allowedFolders->execute([$folderId, $userId]);
        if (!$allowedFolders->fetch()) {
            $folderId = null;
        }
    }
    $files = $_FILES['files'];
    $saved = 0;
    $errors = [];
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload error for ' . sanitize_text($files['name'][$i]);
            continue;
        }
        if ($files['size'][$i] > MAX_FILE_SIZE) {
            $errors[] = sanitize_text($files['name'][$i]) . ' is too large.';
            continue;
        }
        $originalName = basename($files['name'][$i]);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $deny = ['php', 'php3', 'php4', 'phtml', 'exe', 'sh', 'bat'];
        if (in_array($extension, $deny, true)) {
            $errors[] = sanitize_text($originalName) . ' type not allowed.';
            continue;
        }
        $storageName = uniqid('file_', true) . '.' . $extension;
        $targetPath = UPLOAD_DIR . $storageName;
        if (!move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
            $errors[] = 'Failed to save ' . sanitize_text($originalName);
            continue;
        }
        $stmt = $pdo->prepare('INSERT INTO files (user_id, folder_id, original_name, storage_name, file_size, mime_type, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$userId, $folderId, $originalName, $storageName, $files['size'][$i], $files['type'][$i]]);
        log_activity($pdo, $userId, 'Uploaded file', 'file', $originalName);
        $saved++;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => $saved > 0, 'message' => $saved ? "Uploaded $saved file(s)." : implode(' ', $errors), 'errors' => $errors]);
    exit;
}

if ($action === 'delete') {
    $input = json_decode(file_get_contents('php://input'), true);
    $fileId = isset($input['file_id']) ? intval($input['file_id']) : 0;
    $stmt = $pdo->prepare('SELECT storage_name, original_name FROM files WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$fileId, $userId]);
    $file = $stmt->fetch();
    header('Content-Type: application/json');
    if (!$file) {
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit;
    }
    $path = UPLOAD_DIR . $file['storage_name'];
    if (file_exists($path)) {
        unlink($path);
    }
    $stmt = $pdo->prepare('DELETE FROM files WHERE id = ?');
    $stmt->execute([$fileId]);
    log_activity($pdo, $userId, 'Deleted file', 'file', $file['original_name']);
    echo json_encode(['success' => true, 'message' => 'File deleted']);
    exit;
}

if ($action === 'rename') {
    $input = json_decode(file_get_contents('php://input'), true);
    $fileId = isset($input['file_id']) ? intval($input['file_id']) : 0;
    $newName = trim($input['new_name'] ?? '');
    header('Content-Type: application/json');
    if (!$newName) {
        echo json_encode(['success' => false, 'message' => 'New name is required']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT original_name FROM files WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$fileId, $userId]);
    $file = $stmt->fetch();
    if (!$file) {
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE files SET original_name = ? WHERE id = ?');
    $stmt->execute([$newName, $fileId]);
    log_activity($pdo, $userId, 'Renamed file', 'file', $file['original_name'] . ' -> ' . $newName);
    echo json_encode(['success' => true, 'message' => 'File renamed']);
    exit;
}

if ($action === 'list_folder') {
    $folderId = isset($_GET['folder_id']) ? intval($_GET['folder_id']) : 0;
    header('Content-Type: application/json');
    if ($folderId) {
        $stmt = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?');
        $stmt->execute([$folderId, $userId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Folder not found']);
            exit;
        }
    }
    $stmt = $pdo->prepare('SELECT id, name, parent_id, (SELECT COUNT(*) FROM files WHERE folder_id = folders.id) AS file_count FROM folders WHERE user_id = ? AND parent_id = ? ORDER BY name ASC');
    $stmt->execute([$userId, $folderId ?: null]);
    $folders = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT id, folder_id, original_name, file_size, mime_type, uploaded_at FROM files WHERE user_id = ? AND folder_id = ? ORDER BY uploaded_at DESC');
    $stmt->execute([$userId, $folderId]);
    $files = $stmt->fetchAll();
    foreach ($files as &$file) {
        $file['download_url'] = '../api/files.php?action=download&file_id=' . $file['id'];
    }

    echo json_encode(['success' => true, 'folders' => $folders, 'files' => $files, 'folder_id' => $folderId]);
    exit;
}

if ($action === 'download') {
    $fileId = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;
    $stmt = $pdo->prepare('SELECT original_name, storage_name, mime_type FROM files WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$fileId, $userId]);
    $file = $stmt->fetch();
    if (!$file) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    $path = UPLOAD_DIR . $file['storage_name'];
    if (!file_exists($path)) {
        http_response_code(404);
        echo 'File missing';
        exit;
    }
    header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

if ($action === 'get') {
    $fileId = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;
    header('Content-Type: application/json');
    $stmt = $pdo->prepare('SELECT id, folder_id, original_name, storage_name, mime_type FROM files WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$fileId, $userId]);
    $file = $stmt->fetch();
    if (!$file) {
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit;
    }
    $path = UPLOAD_DIR . $file['storage_name'];
    $content = null;
    if (file_exists($path)) {
        $isText = strpos($file['mime_type'], 'text/') === 0 || preg_match('/\.(php|js|py|html|css|java|c|cpp)$/', $file['original_name']);
        if ($isText) {
            $content = file_get_contents($path);
        }
    }
    $file['download_url'] = '../api/files.php?action=download&file_id=' . $file['id'];
    $file['content'] = $content;
    echo json_encode(['success' => true, 'file' => $file]);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Action not supported']);
