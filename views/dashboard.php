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
$profilePic = $user['profile_pic'] ? '../uploads/' . $user['profile_pic'] : 'https://via.placeholder.com/64?text=CP';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CodeKeep | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head> 
<body data-dashboard="true">
<nav class="navbar navbar-expand-lg navbar-light border-bottom">
    <div class="container-fluid px-4">
        <a class="navbar-brand me-3" href="#">CodeKeep</a>
        <!-- navbar search removed per user request -->
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary" onclick="toggleDarkMode()" title="Toggle dark mode" id="dark-mode-btn">
                <span id="theme-icon">🌙 Dark</span>
            </button>
            <div class="dropdown">
                <button class="btn btn-sm btn-primary" id="upload-btn" data-bs-toggle="dropdown" aria-expanded="false">＋</button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="upload-btn">
                    <li><a class="dropdown-item" href="upload.php?type=file">Upload file</a></li>
                    <li><a class="dropdown-item" href="upload.php?type=folder">Create folder</a></li>
                </ul>
            </div>
            <div class="nav-activity dropdown">
                <a href="#" class="nav-link dropdown-toggle" id="activityDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Activity</a>
                <ul class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="activityDropdown" id="nav-activity-list" style="min-width:320px; max-height:320px; overflow:auto;">
                    <li class="small text-muted">Loading...</li>
                </ul>
            </div>
            <button id="profile-btn" class="btn btn-link p-0" title="Profile">
                <img src="<?= htmlspecialchars($profilePic, ENT_QUOTES) ?>" alt="Profile" class="profile-picture">
            </button>
            <div>
                <div><?= htmlspecialchars($user['name'], ENT_QUOTES) ?></div>
                <small class="text-muted">Student Storage</small>
            </div>
        </div>
    </div>
</nav>
<div class="container-fluid px-4 py-4">
    <div class="row">
        <div class="col-xl-3 mb-4">
            <div class="sidebar p-3 d-flex flex-column">
                <h5>Navigation</h5>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="#">Dashboard</a>
                    <a class="nav-link" href="#private-files">Private Files</a>
                    <a class="nav-link" href="#deleted-files">Deleted Files</a>
                    <a class="nav-link" href="#notifications">Notifications</a>
                    <a class="nav-link" href="#folders-section">Folders</a>
                </nav>
                <div class="mt-4">
                    <h6>Quick stats</h6>
                    <p class="mb-1"><strong id="total-files">0</strong> files</p>
                    <p class="mb-1"><strong id="total-folders">0</strong> folders</p>
                    <p class="mb-1"><strong id="used-storage">0</strong> used</p>
                </div>
                <hr>
                <h6>Projects</h6>
                <div id="projects-list" class="mt-2 small text-muted">
                    <p class="text-muted small">No projects yet</p>
                </div>
                <div class="mt-2">
                    <input id="project-search" class="form-control form-control-sm" placeholder="Search projects..." />
                </div>
                <hr>
                <h6>About</h6>
                <div class="about small text-muted" id="about-section">
                    <p>Made by Earl Lumosad</p>
                </div>
                <div class="mt-auto pt-3">
                    <a class="btn btn-outline-danger btn-sm w-100" href="../logout.php">Logout</a>
                </div>
            </div>
        </div>
        <div class="col-xl-9">
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card p-3 h-100">
                        <h5>Welcome back, <?= htmlspecialchars($user['name'], ENT_QUOTES) ?>!</h5>
                        <p class="text-muted">Manage your projects, notes, images, and documents in one place.</p>
                    </div>
                </div>
            </div>
            <div class="card mb-4 p-4" id="blog-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Community</h5>
                    <div class="text-muted small">Share updates, ask questions, and discuss</div>
                </div>
                <div id="blog-alert"></div>
                <div class="mb-3">
                    <form id="post-form">
                        <div class="mb-2">
                            <input id="post-title" class="form-control" placeholder="Title (optional)">
                        </div>
                        <div class="mb-2">
                            <textarea id="post-content" class="form-control" rows="3" placeholder="Write a post..."></textarea>
                        </div>
                        <div class="mb-2">
                            <input type="file" id="post-attachment" name="attachment" class="form-control" accept="image/*">
                            <small class="text-muted">Attach an image (optional)</small>
                        </div>
                        <div class="text-end">
                            <button class="btn btn-primary" type="submit">Post</button>
                        </div>
                    </form>
                </div>
                <hr>
                <div class="mb-2 text-muted small">Global feed: all registered users can see and interact with these posts.</div>
                <div id="posts-list"></div>
            </div>
            <!-- Folder list moved to sidebar Projects -->
            <!-- Activity moved to navbar dropdown -->

<!-- Profile modal -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="profile-form" enctype="multipart/form-data">
                    <div class="text-center mb-3">
                        <img src="<?= htmlspecialchars($profilePic, ENT_QUOTES) ?>" alt="Profile" class="profile-picture-large mb-3">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Profile picture</label>
                        <input type="file" name="profile_picture" class="form-control">
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>var currentUserId = <?php echo $userId; ?>;</script>
<script src="../assets/js/main.js"></script>
<script src="../assets/js/posts.js"></script>
</body>
</html>
