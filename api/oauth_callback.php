<?php
require_once __DIR__ . '/../config/config.php';
$pdo = db_connect();
header('Content-Type: application/json');

$provider = $_GET['provider'] ?? '';
$code = $_GET['code'] ?? '';
$error = $_GET['error'] ?? '';

if (!$provider || !in_array($provider, ['google', 'facebook'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid provider']);
    exit;
}

if ($error) {
    echo json_encode(['success' => false, 'message' => 'OAuth error: ' . $error]);
    exit;
}

if (!$code) {
    echo json_encode(['success' => false, 'message' => 'No authorization code']);
    exit;
}

try {
    if ($provider === 'google') {
        $token = getGoogleToken($code);
        $userInfo = getGoogleUserInfo($token);
    } else {
        $token = getFacebookToken($code);
        $userInfo = getFacebookUserInfo($token);
    }

    if (!$userInfo) {
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve user info']);
        exit;
    }

    // Check if user exists by OAuth ID
    $stmt = $pdo->prepare('SELECT id FROM users WHERE oauth_provider = ? AND oauth_id = ? LIMIT 1');
    $stmt->execute([$provider, $userInfo['id']]);
    $user = $stmt->fetch();

    if ($user) {
        // User exists, log them in
        $_SESSION['user_id'] = $user['id'];
        $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();
        $_SESSION['user_name'] = $userData['name'];
        log_activity($pdo, $user['id'], 'Logged in via ' . ucfirst($provider));
    } else {
        // Check if email exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$userInfo['email']]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            // Link OAuth to existing account
            $stmt = $pdo->prepare('UPDATE users SET oauth_provider = ?, oauth_id = ? WHERE id = ?');
            $stmt->execute([$provider, $userInfo['id'], $existingUser['id']]);
            $_SESSION['user_id'] = $existingUser['id'];
            $_SESSION['user_name'] = $userInfo['name'];
            log_activity($pdo, $existingUser['id'], 'Linked ' . ucfirst($provider) . ' account');
        } else {
            // Create new user
            $stmt = $pdo->prepare('INSERT INTO users (name, email, oauth_provider, oauth_id, created_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$userInfo['name'], $userInfo['email'], $provider, $userInfo['id']]);
            $userId = $pdo->lastInsertId();
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $userInfo['name'];
            log_activity($pdo, $userId, 'Registered via ' . ucfirst($provider));
        }
    }

    // Redirect to dashboard
    header('Location: ../views/dashboard.php');
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}

function getGoogleToken($code) {
    $url = 'https://oauth2.googleapis.com/token';
    $data = [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => GOOGLE_REDIRECT_URI,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($response, true);
    if (!isset($tokenData['access_token'])) {
        throw new Exception('Failed to get Google access token');
    }
    return $tokenData['access_token'];
}

function getGoogleUserInfo($token) {
    $url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    $userInfo = json_decode($response, true);
    if (!isset($userInfo['id'])) {
        throw new Exception('Failed to get Google user info');
    }
    return [
        'id' => $userInfo['id'],
        'name' => $userInfo['name'] ?? 'User',
        'email' => $userInfo['email'] ?? '',
    ];
}

function getFacebookToken($code) {
    $url = 'https://graph.facebook.com/v18.0/oauth/access_token';
    $data = [
        'client_id' => FACEBOOK_APP_ID,
        'client_secret' => FACEBOOK_APP_SECRET,
        'code' => $code,
        'redirect_uri' => FACEBOOK_REDIRECT_URI,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($response, true);
    if (!isset($tokenData['access_token'])) {
        throw new Exception('Failed to get Facebook access token');
    }
    return $tokenData['access_token'];
}

function getFacebookUserInfo($token) {
    $url = 'https://graph.facebook.com/me?fields=id,name,email&access_token=' . $token;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    $userInfo = json_decode($response, true);
    if (!isset($userInfo['id'])) {
        throw new Exception('Failed to get Facebook user info');
    }
    return [
        'id' => $userInfo['id'],
        'name' => $userInfo['name'] ?? 'User',
        'email' => $userInfo['email'] ?? '',
    ];
}
?>
