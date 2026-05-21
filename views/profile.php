<?php
require_once __DIR__ . '/../config/config.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}
$userId = get_current_user_id();
$pdo = db_connect();
$stmt = $pdo->prepare('SELECT name, email, profile_pic FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
$profilePic = $user['profile_pic'] ? '../uploads/' . $user['profile_pic'] : 'https://via.placeholder.com/128?text=Profile';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CodeKeep | Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="dashboard.php">CodeKeep</a>
        <div>
            <a class="btn btn-outline-secondary btn-sm me-2" href="dashboard.php">Back</a>
            <a class="btn btn-outline-danger btn-sm" href="../logout.php">Logout</a>
        </div>
    </div>
</nav>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card p-4 shadow-sm">
                <h4 class="mb-4">Profile settings</h4>
                <div id="alert-area"></div>
                <form id="profile-form" enctype="multipart/form-data">
                    <div class="text-center mb-4">
                        <img src="<?= htmlspecialchars($profilePic, ENT_QUOTES) ?>" alt="Profile" class="profile-picture-large mb-3">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Profile picture</label>
                        <input type="file" name="profile_picture" class="form-control">
                        <div class="form-text">Optional: PNG, JPG, GIF up to 25 MB.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>
