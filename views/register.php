<?php
require_once __DIR__ . '/../config/config.php';
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CodeKeep | Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="d-flex align-items-center min-vh-100">
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1000;">
    <button class="btn btn-sm btn-outline-secondary" onclick="toggleDarkMode()" title="Toggle dark mode" id="dark-mode-btn">
        <span id="theme-icon">🌙 Dark</span>
    </button>
</div>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card p-4 shadow-sm">
                <div class="card-body">
                    <h3 class="card-title mb-3">Create your CodeKeep account</h3>
                    <div id="alert-area"></div>
                    <form id="auth-form" data-endpoint="register" action="../api/register.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Register</button>
                    </form>
                    <div class="mt-4 mb-3">
                        <p class="text-center text-muted small">Or sign up with</p>
                        <div class="d-flex gap-2">
                            <a href="../api/oauth_login.php?provider=google" class="btn btn-outline-secondary flex-grow-1">
                                <i class="fab fa-google"></i> Google
                            </a>
                            <a href="../api/oauth_login.php?provider=facebook" class="btn btn-outline-secondary flex-grow-1">
                                <i class="fab fa-facebook"></i> Facebook
                            </a>
                        </div>
                    </div>
                    <p class="text-center text-muted mt-3">Already have an account? <a href="login.php">Login</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>
