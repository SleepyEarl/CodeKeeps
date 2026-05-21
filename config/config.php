<?php
// Configuration file for CodeKeep
// Change database settings for your server
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'codekeep');
define('DB_USER', 'root');
define('DB_PASS', '');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

define('MAX_FILE_SIZE', 25 * 1024 * 1024); // 25 MB max upload size

// OAuth Configuration (Get these from Google Cloud Console and Facebook Developers)
define('OAUTH_ENABLED', true);
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID_HERE');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET_HERE');
define('GOOGLE_REDIRECT_URI', 'http://localhost/CodeKeep/api/oauth_callback.php?provider=google');
define('FACEBOOK_APP_ID', 'YOUR_FACEBOOK_APP_ID_HERE');
define('FACEBOOK_APP_SECRET', 'YOUR_FACEBOOK_APP_SECRET_HERE');
define('FACEBOOK_REDIRECT_URI', 'http://localhost/CodeKeep/api/oauth_callback.php?provider=facebook');

function db_connect() {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die('Database connection failed: ' . $e->getMessage());
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

function get_current_user_id() {
    return isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
}

function log_activity($pdo, $user_id, $action, $target_type = null, $target_name = null) {
    $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, target_type, target_name, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$user_id, $action, $target_type, $target_name]);
}
