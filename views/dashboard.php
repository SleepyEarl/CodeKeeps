<?php
require_once __DIR__ . '/../config/config.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}
$userId = get_current_user_id();
$pdo = db_connect();
$stmt = $pdo->prepare('SELECT name, profile_pic FROM users WHERE id = ? LIMIT 1');
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
        <a class="navbar-brand" href="#">CodeKeep</a>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary" onclick="toggleDarkMode()" title="Toggle dark mode" id="dark-mode-btn">
                <span id="theme-icon">🌙 Dark</span>
            </button>
            <div class="nav-activity dropdown">
                <a href="#" class="nav-link dropdown-toggle" id="activityDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Activity</a>
                <ul class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="activityDropdown" id="nav-activity-list" style="min-width:320px; max-height:320px; overflow:auto;">
                    <li class="small text-muted">Loading...</li>
                </ul>
            </div>
            <a href="profile.php" title="Edit profile">
                <img src="<?= htmlspecialchars($profilePic, ENT_QUOTES) ?>" alt="Profile" class="profile-picture">
            </a>
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
                    <a class="nav-link" href="#upload-section">Upload Files</a>
                    <a class="nav-link" href="#folders-section">Folders</a>
                </nav>
                <div class="mt-4">
                    <h6>Quick stats</h6>
                    <p class="mb-1"><strong id="total-files">0</strong> files</p>
                    <p class="mb-1"><strong id="total-folders">0</strong> folders</p>
                    <p class="mb-1"><strong id="used-storage">0</strong> used</p>
                </div>
                <hr>
                <h6>Recently Added</h6>
                <div id="recent-files-section" class="mt-3">
                    <p class="text-muted small">No files yet</p>
                </div>
                <hr>
                <h6>About</h6>
                <div class="about small text-muted">
                    <p>CodeKeep is a beginner-friendly full-stack web application designed as a simple cloud file storage and repository system for students. It allows users to securely upload, organize, and manage their files and folders online anytime and anywhere using a web browser. Built using PHP, MySQL, JavaScript, and Bootstrap, CodeKeep demonstrates core concepts of web development such as authentication, database management, file handling, and API-based backend structure. The system is designed to be lightweight, easy to use, and educational, making it ideal for students who are learning full-stack development.</p>

                    <p>This project was created by me 2nd year Computer Studies student, Earl Lumosad, from St. Peter’s College, Iligan City, as part of my academic practice in building real-world web applications.</p>
                </div>
                <div class="mt-auto pt-3">
                    <a class="btn btn-outline-danger btn-sm w-100" href="../logout.php">Logout</a>
                </div>
            </div>
        </div>
        <div class="col-xl-9">
            <div class="row g-3 mb-4">
                <div class="col-md-8">
                    <div class="card p-3 h-100">
                        <h5>Welcome back, <?= htmlspecialchars($user['name'], ENT_QUOTES) ?>!</h5>
                        <p class="text-muted">Manage your projects, notes, images, and documents in one place.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3 h-100">
                        <h6>Search files</h6>
                        <div class="input-group mb-3">
                            <input id="search-query" type="text" class="form-control" placeholder="Search files...">
                            <button class="btn btn-primary" type="button" onclick="searchFiles()">Search</button>
                        </div>
                        <div id="search-results"></div>
                    </div>
                </div>
            </div>
            <div class="card mb-4 p-4" id="upload-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Upload</h5>
                </div>
                <div id="alert-area"></div>
                <div class="mb-3">
                    <ul class="nav nav-tabs" id="uploadTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="files-tab" data-bs-toggle="tab" data-bs-target="#files-pane" type="button" role="tab" aria-controls="files-pane" aria-selected="true">Files</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="folder-tab" data-bs-toggle="tab" data-bs-target="#folder-pane" type="button" role="tab" aria-controls="folder-pane" aria-selected="false">Folder</button>
                        </li>
                    </ul>
                </div>
                <div class="tab-content" id="uploadTabContent">
                    <div class="tab-pane fade show active" id="files-pane" role="tabpanel" aria-labelledby="files-tab">
                        <form id="upload-form">
                            <div class="mb-3">
                                <label class="form-label">Choose files</label>
                                <input id="upload-files" type="file" name="files[]" class="form-control" multiple>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Folder destination</label>
                                <select id="folder-select" class="form-select" name="folder_id">
                                    <option value="">Root folder</option>
                                </select>
                            </div>
                            <div id="upload-dropzone" class="drop-zone mb-3">
                                Drag and drop files here or click to choose files
                            </div>
                            <button type="submit" class="btn btn-primary">Upload now</button>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="folder-pane" role="tabpanel" aria-labelledby="folder-tab">
                        <form id="folder-upload-form">
                            <div class="mb-3">
                                <label class="form-label">Folder name</label>
                                <input id="folder-upload-name" type="text" class="form-control" placeholder="My Folder" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Parent folder (optional)</label>
                                <select id="parent-folder-upload-select" class="form-select">
                                    <option value="">Root folder</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Create folder</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="row g-3 mb-4" id="folders-section">
                <div class="col-12">
                    <h5>Folder list</h5>
                    <div class="row" id="folder-list"></div>
                </div>
            </div>
            <!-- Activity moved to navbar dropdown -->
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>
