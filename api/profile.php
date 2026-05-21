<?php
require_once __DIR__ . '/../config/config.php';
$pdo = db_connect();

if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$userId = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');

if (!$name || !$email) {
    echo json_encode(['success' => false, 'message' => 'Name and email are required']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
$stmt->execute([$email, $userId]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Email already taken']);
    exit;
}

$profilePic = $_FILES['profile_picture'] ?? null;
$profilePath = null;
if ($profilePic && $profilePic['error'] === UPLOAD_ERR_OK) {
    if ($profilePic['size'] > MAX_FILE_SIZE) {
        echo json_encode(['success' => false, 'message' => 'Profile image is too large']);
        exit;
    }
    $ext = strtolower(pathinfo($profilePic['name'], PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg', 'gif'];
    if (!in_array($ext, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Profile image must be PNG, JPG, or GIF']);
        exit;
    }
    $filename = uniqid('profile_', true) . '.' . $ext;
    $destination = UPLOAD_DIR . $filename;
    if (!move_uploaded_file($profilePic['tmp_name'], $destination)) {
        echo json_encode(['success' => false, 'message' => 'Unable to save profile picture']);
        exit;
    }
    $profilePath = $filename;
}

if ($profilePath) {
    $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, profile_pic = ? WHERE id = ?');
    $stmt->execute([$name, $email, $profilePath, $userId]);
} else {
    $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
    $stmt->execute([$name, $email, $userId]);
}
$_SESSION['user_name'] = $name;
log_activity($pdo, $userId, 'Updated profile');

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Profile saved']);
