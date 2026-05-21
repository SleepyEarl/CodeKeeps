<?php
require_once __DIR__ . '/../config/config.php';
$pdo = db_connect();
header('Content-Type: application/json');
$action = $_GET['action'] ?? '';
$userId = get_current_user_id();

function ensurePostTables($pdo) {
    try {
        $pdo->query('SELECT 1 FROM posts LIMIT 1');
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') {
            $pdo->beginTransaction();
            $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                title VARCHAR(190) DEFAULT NULL,
                content TEXT NOT NULL,
                attachment VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS post_comments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                post_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                comment TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS post_reactions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                post_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                type VARCHAR(32) NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY unique_reaction (post_id, user_id, type),
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->commit();
            return;
        } else {
            throw $e;
        }
    }
    
    // Add attachment column if it doesn't exist (for existing databases)
    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN attachment VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
}

try {
    ensurePostTables($pdo);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

if ($action === 'list') {
    // return latest posts with comments and reaction counts
    $stmt = $pdo->prepare('SELECT p.id, p.user_id, p.title, p.content, p.attachment, p.created_at, u.name FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 50');
    $stmt->execute();
    $posts = $stmt->fetchAll();
    foreach ($posts as &$p) {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM post_reactions WHERE post_id = ?');
        $stmt->execute([$p['id']]);
        $r = $stmt->fetch();
        $p['reactions'] = intval($r['cnt'] ?? 0);
        $stmt = $pdo->prepare('SELECT pc.id, pc.user_id, pc.comment, pc.created_at, u.name FROM post_comments pc JOIN users u ON pc.user_id = u.id WHERE pc.post_id = ? ORDER BY pc.created_at ASC');
        $stmt->execute([$p['id']]);
        $p['comments'] = $stmt->fetchAll();
    }
    echo json_encode(['success' => true, 'posts' => $posts]);
    exit;
}

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($action === 'create') {
    $content = trim($_POST['content'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $attachment = null;
    if (!$content) {
        echo json_encode(['success' => false, 'message' => 'Content is required']);
        exit;
    }
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedMimes)) {
            echo json_encode(['success' => false, 'message' => 'Only image files are allowed']);
            exit;
        }
        $filename = uniqid('post_') . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $uploadPath = __DIR__ . '/../uploads/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $attachment = $filename;
        }
    }
    $stmt = $pdo->prepare('INSERT INTO posts (user_id, title, content, attachment, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$userId, $title ?: null, $content, $attachment]);
    $postId = $pdo->lastInsertId();
    log_activity($pdo, $userId, 'Created post', 'post', $title ?: '');
    echo json_encode(['success' => true, 'post_id' => $postId]);
    exit;
}

if ($action === 'react') {
    $input = json_decode(file_get_contents('php://input'), true);
    $postId = isset($input['post_id']) ? intval($input['post_id']) : 0;
    $type = trim($input['type'] ?? 'like');
    if (!$postId) {
        echo json_encode(['success' => false, 'message' => 'Post id required']);
        exit;
    }
    // toggle reaction
    $stmt = $pdo->prepare('SELECT id FROM post_reactions WHERE post_id = ? AND user_id = ? AND type = ? LIMIT 1');
    $stmt->execute([$postId, $userId, $type]);
    $exists = $stmt->fetch();
    if ($exists) {
        $stmt = $pdo->prepare('DELETE FROM post_reactions WHERE id = ?');
        $stmt->execute([$exists['id']]);
        log_activity($pdo, $userId, 'Removed reaction', 'post', 'ID: ' . $postId);
        echo json_encode(['success' => true, 'removed' => true]);
        exit;
    }
    $stmt = $pdo->prepare('INSERT INTO post_reactions (post_id, user_id, type, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$postId, $userId, $type]);
    log_activity($pdo, $userId, 'Reacted to post', 'post', 'ID: ' . $postId);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'comment') {
    $content = trim($_POST['comment'] ?? '');
    $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$postId || !$content) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }
    $stmt = $pdo->prepare('INSERT INTO post_comments (post_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$postId, $userId, $content]);
    log_activity($pdo, $userId, 'Commented on post', 'post', 'ID: ' . $postId);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete') {
    $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$postId) {
        echo json_encode(['success' => false, 'message' => 'Post id required']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT user_id, attachment FROM posts WHERE id = ? LIMIT 1');
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }
    if ($post['user_id'] != $userId) {
        echo json_encode(['success' => false, 'message' => 'You can only delete your own posts']);
        exit;
    }
    if ($post['attachment']) {
        @unlink(__DIR__ . '/../uploads/' . $post['attachment']);
    }
    $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
    $stmt->execute([$postId]);
    log_activity($pdo, $userId, 'Deleted post', 'post', 'ID: ' . $postId);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'update') {
    $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $content = trim($_POST['content'] ?? '');
    $title = trim($_POST['title'] ?? '');
    if (!$postId || !$content) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT user_id FROM posts WHERE id = ? LIMIT 1');
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }
    if ($post['user_id'] != $userId) {
        echo json_encode(['success' => false, 'message' => 'You can only edit your own posts']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$title ?: null, $content, $postId]);
    log_activity($pdo, $userId, 'Updated post', 'post', 'ID: ' . $postId);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action not supported']);
?>