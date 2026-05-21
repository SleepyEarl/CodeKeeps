<?php
require_once __DIR__ . '/../config/config.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}
$userId = get_current_user_id();
$pdo = db_connect();
$folderId = isset($_GET['folder_id']) ? intval($_GET['folder_id']) : 0;
$stmt = $pdo->prepare('SELECT id, name, user_id FROM folders WHERE id = ? LIMIT 1');
$stmt->execute([$folderId]);
$folder = $stmt->fetch();
if (!$folder) {
    header('Location: dashboard.php');
    exit;
}
$profilePic = '';
$stmt = $pdo->prepare('SELECT name, email, profile_pic FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$u = $stmt->fetch();
$profilePic = $u['profile_pic'] ? '../uploads/' . $u['profile_pic'] : 'https://via.placeholder.com/64?text=CP';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CodeKeep | Folder: <?= htmlspecialchars($folder['name'], ENT_QUOTES) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light border-bottom">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="dashboard.php">CodeKeep</a>
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Back</a>
            <button id="profile-btn" class="btn btn-link p-0" title="Profile">
                <img src="<?= htmlspecialchars($profilePic, ENT_QUOTES) ?>" alt="Profile" class="profile-picture">
            </button>
        </div>
    </div>
</nav>
<div class="container py-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card p-4 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">Folder: <?= htmlspecialchars($folder['name'], ENT_QUOTES) ?></h4>
                    <div>
                        <button id="delete-folder-btn" class="btn btn-outline-danger btn-sm">Delete Folder</button>
                    </div>
                </div>
                <div id="folder-alert"></div>
                <div class="mb-3">
                    <h6>Files</h6>
                    <div id="folder-files" class="row g-3"></div>
                </div>
                <hr>
                <h6>Upload files to this folder</h6>
                <form id="folder-upload-form-local">
                    <div class="mb-3">
                        <input type="file" id="folder-upload-files" name="files[]" class="form-control" multiple>
                    </div>
                    <button class="btn btn-primary" type="submit">Upload</button>
                </form>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>Members</h6>
                <div id="folder-members" class="mt-2 small text-muted">Loading...</div>
                <div class="mt-3">
                    <button id="add-member-btn" class="btn btn-sm btn-outline-secondary">Add member</button>
                </div>
            </div>
        </div>
    </div>
</div>
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
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($u['name'] ?? '', ENT_QUOTES) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES) ?>" required>
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

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../assets/js/main.js"></script>
<script>
const folderId = <?= json_encode($folderId) ?>;
async function loadFolder() {
    const resp = await fetch(`../api/files.php?action=list_folder&folder_id=${folderId}`);
    const data = await handleFormResponse(resp);
    if (!data.success) {
        showAlert('Could not load folder', 'danger');
        return;
    }
    const filesEl = document.getElementById('folder-files');
    filesEl.innerHTML = data.files.length ? data.files.map(f => `
        <div class="col-md-6">
            <div class="card p-2">
                <div>
                    <strong>${escapeHtml(f.original_name)}</strong>
                    <div class="small text-muted">${f.mime_type} • ${formatSize(f.file_size)}</div>
                </div>
                <div class="mt-2 d-flex gap-2">
                    <a href="${f.download_url}" class="btn btn-sm btn-outline-primary">Download</a>
                    <button class="btn btn-sm btn-outline-secondary" onclick="openRenameModal(${f.id}, '${escapeHtml(f.original_name)}')">Rename</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteFile(${f.id})">Delete</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="openFileViewer(${f.id})">Open</button>
                </div>
            </div>
        </div>
    `).join('') : '<div class="col-12"><p class="text-muted">No files in this folder</p></div>';
}

async function loadMembers() {
    const resp = await fetch(`../api/members.php?action=list&folder_id=${folderId}`);
    const data = await handleFormResponse(resp);
    const el = document.getElementById('folder-members');
    if (!data.success) {
        el.innerHTML = 'Members: <span class="text-muted">None</span>';
        return;
    }
    if (!data.members.length) {
        el.innerHTML = 'Members: <span class="text-muted">None</span>';
        return;
    }
    el.innerHTML = data.members.map(m => `<div>${escapeHtml(m.name || m.email)} &lt;${escapeHtml(m.email)}&gt;</div>`).join('');
}

document.getElementById('add-member-btn').addEventListener('click', async () => {
    const email = prompt('Enter member email:');
    if (!email) return;
    const resp = await fetch('../api/members.php?action=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ folder_id: folderId, email }),
    });
    const data = await handleFormResponse(resp);
    if (data.success) {
        showAlert('Member added');
        loadMembers();
    } else {
        showAlert(data.message || 'Could not add member', 'danger');
    }
});

document.getElementById('folder-upload-form-local').addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = document.getElementById('folder-upload-files');
    if (!input.files.length) return showAlert('Choose at least one file', 'danger');
    const formData = new FormData();
    Array.from(input.files).forEach(f => formData.append('files[]', f));
    formData.append('folder_id', folderId);
    const resp = await fetch('../api/files.php?action=upload', { method: 'POST', body: formData });
    const data = await handleFormResponse(resp);
    if (data.success) {
        showAlert(data.message || 'Upload complete');
        input.value = '';
        loadFolder();
    } else {
        showAlert(data.message || 'Upload failed', 'danger');
    }
});

document.getElementById('delete-folder-btn').addEventListener('click', async () => {
    if (!confirm('Delete this folder and all its contents?')) return;
    const resp = await fetch('../api/folders.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ folder_id: folderId }),
    });
    const data = await handleFormResponse(resp);
    if (data.success) {
        showAlert('Folder deleted');
        window.location.href = 'dashboard.php';
    } else {
        showAlert(data.message || 'Could not delete folder', 'danger');
    }
});

loadFolder();
loadMembers();
</script>
</body>
</html>
