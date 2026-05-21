<?php
require_once __DIR__ . '/../config/config.php';
$pdo = db_connect();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, password, oauth_provider FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

// User exists
if ($user) {
    // If user registered via OAuth, they won't have a password
    if ($user['oauth_provider'] && !$user['password']) {
        echo json_encode(['success' => false, 'message' => 'This account was created via ' . ucfirst($user['oauth_provider']) . '. Please use that to login.']);
        exit;
    }
    // Check password
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }
} else {
    // User doesn't exist
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    exit;
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];

log_activity($pdo, $user['id'], 'Logged in');

echo json_encode(['success' => true, 'message' => 'Login successful']);
