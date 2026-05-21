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
    <title>CodeKeep | Upload</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light border-bottom">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="dashboard.php">CodeKeep</a>
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Back</a>
            <a href="profile.php" title="Edit profile">
                <img src="<?= htmlspecialchars($profilePic, ENT_QUOTES) ?>" alt="Profile" class="profile-picture">
            </a>
        </div>
    </div>
</nav>
<div class="container py-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card p-4 mb-3">
                <h5>Upload</h5>
                <div id="upload-alert"></div>
                <hr>
                <h6>Upload files</h6>
                <form id="upload-form-local">
                    <div class="mb-3">
                        <input type="file" id="upload-files-local" name="files[]" class="form-control" multiple>
                    </div>
                    <div class="mb-3">
                        <label>Destination folder (optional)</label>
                        <select id="upload-folder-select" class="form-select">
                            <option value="">Root folder</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">Upload</button>
                </form>
                <hr>
                <h6>Create folder</h6>
                <form id="create-folder-local">
                    <div class="mb-3">
                        <input id="create-folder-name" class="form-control" placeholder="Folder name">
                    </div>
                    <div class="mb-3">
                        <label>Parent folder (optional)</label>
                        <select id="create-folder-parent" class="form-select">
                            <option value="">Root folder</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">Create folder</button>
                </form>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>Tips</h6>
                <p class="small text-muted">Use this page to upload files or create folders. You can also upload entire folders using your OS file picker if supported.</p>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
<script>
async function loadUploadFolders() {
    const resp = await fetch('../api/folders.php?action=list');
    const data = await handleFormResponse(resp);
    if (!data.success) return;
    const options = '<option value="">Root folder</option>' + data.folders.map(f => `<option value="${f.id}">${escapeHtml(f.name)}</option>`).join('');
    document.getElementById('upload-folder-select').innerHTML = options;
    document.getElementById('create-folder-parent').innerHTML = options;
}

document.getElementById('upload-form-local').addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = document.getElementById('upload-files-local');
    if (!input.files.length) return showAlert('Choose files to upload', 'danger');
    const fd = new FormData();
    Array.from(input.files).forEach(f => fd.append('files[]', f));
    const folderId = document.getElementById('upload-folder-select').value;
    if (folderId) fd.append('folder_id', folderId);
    const resp = await fetch('../api/files.php?action=upload', { method: 'POST', body: fd });
    const data = await handleFormResponse(resp);
    if (data.success) {
        showAlert(data.message || 'Upload complete');
        input.value = '';
        loadUploadFolders();
    } else {
        showAlert(data.message || 'Upload failed', 'danger');
    }
});

document.getElementById('create-folder-local').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('create-folder-name').value.trim();
    const parent = document.getElementById('create-folder-parent').value || null;
    if (!name) return showAlert('Folder name required', 'danger');
    const resp = await fetch('../api/folders.php?action=create', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ folder_name: name, parent_id: parent }) });
    const data = await handleFormResponse(resp);
    if (data.success) {
        showAlert('Folder created');
        document.getElementById('create-folder-name').value = '';
        loadUploadFolders();
    } else {
        showAlert(data.message || 'Could not create folder', 'danger');
    }
});

loadUploadFolders();
</script>
</body>
</html>
