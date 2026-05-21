<?php
require_once __DIR__ . '/../config/config.php';

$provider = $_GET['provider'] ?? '';

if (!in_array($provider, ['google', 'facebook'])) {
    die('Invalid provider');
}

if ($provider === 'google') {
    $url = 'https://accounts.google.com/o/oauth2/v2/auth';
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'openid profile email',
        'access_type' => 'offline',
    ];
} else {
    $url = 'https://www.facebook.com/v18.0/dialog/oauth';
    $params = [
        'client_id' => FACEBOOK_APP_ID,
        'redirect_uri' => FACEBOOK_REDIRECT_URI,
        'scope' => 'public_profile,email',
        'display' => 'popup',
    ];
}

header('Location: ' . $url . '?' . http_build_query($params));
exit;
?>
