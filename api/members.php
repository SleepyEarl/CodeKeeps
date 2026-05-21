<?php
require_once __DIR__ . '/../config/config.php';
$pdo = db_connect();
header('Content-Type: application/json');
$action = $_GET['action'] ?? '';
$userId = get_current_user_id();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($action === 'add') {
    $input = json_decode(file_get_contents('php://input'), true);
    $folderId = isset($input['folder_id']) ? intval($input['folder_id']) : 0;
    $email = trim($input['email'] ?? '');
    if (!$folderId || !$email) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }
    // Ensure current user owns the folder
    $stmt = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$folderId, $userId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Folder not found or permission denied']);
        exit;
    }
    // find user by email
    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    // check already member
    $stmt = $pdo->prepare('SELECT id FROM folder_members WHERE folder_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$folderId, $user['id']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'User is already a member']);
        exit;
    }
    $stmt = $pdo->prepare('INSERT INTO folder_members (folder_id, user_id, added_at) VALUES (?, ?, NOW())');
    $stmt->execute([$folderId, $user['id']]);
    log_activity($pdo, $userId, 'Added member', 'folder', $email);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'list') {
    $folderId = isset($_GET['folder_id']) ? intval($_GET['folder_id']) : 0;
    if (!$folderId) {
        echo json_encode(['success' => false, 'message' => 'Missing folder id']);
        exit;
    }
    // Ensure user has access (owner or member)
    $stmt = $pdo->prepare('SELECT id, user_id FROM folders WHERE id = ? LIMIT 1');
    $stmt->execute([$folderId]);
    $folder = $stmt->fetch();
    if (!$folder) {
        echo json_encode(['success' => false, 'message' => 'Folder not found']);
        exit;
    }
    if ($folder['user_id'] !== $userId) {
        $stmt = $pdo->prepare('SELECT id FROM folder_members WHERE folder_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$folderId, $userId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
    }
    $stmt = $pdo->prepare('SELECT u.id, u.name, u.email FROM folder_members fm JOIN users u ON fm.user_id = u.id WHERE fm.folder_id = ?');
    $stmt->execute([$folderId]);
    $members = $stmt->fetchAll();
    echo json_encode(['success' => true, 'members' => $members]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action not supported']);
?>
