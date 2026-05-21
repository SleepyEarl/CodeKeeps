const apiBase = '../api';

// Dark mode initialization
function initDarkMode() {
    const darkMode = localStorage.getItem('darkMode') === 'true';
    if (darkMode) {
        document.body.classList.add('dark-mode');
    }
    updateThemeIcon();
}

function toggleDarkMode() {
    const isDarkMode = document.body.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', isDarkMode);
    updateThemeIcon();
}

function updateThemeIcon() {
    const themeIcon = document.getElementById('theme-icon');
    if (!themeIcon) return;
    if (document.body.classList.contains('dark-mode')) {
        themeIcon.textContent = '🌙Dark';
    } else {
        themeIcon.textContent = '☀️Light';
    }
}

// Initialize dark mode on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDarkMode);
} else {
    initDarkMode();
}

function handleFormResponse(response) {
    if (!response.ok) throw new Error('Network error');
    return response.json();
}

async function sendAuthForm(form, endpoint) {
    const formData = new FormData(form);
    const response = await fetch(`${apiBase}/${endpoint}.php`, {
        method: 'POST',
        body: formData,
    });
    const data = await handleFormResponse(response);
    if (!data.success) {
        showAlert(data.message || 'Something went wrong', 'danger');
    } else {
        window.location.href = '../views/dashboard.php';
    }
}

function showAlert(message, type = 'success') {
    // toast-style notification: pop in/out
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.position = 'fixed';
        container.style.top = '20px';
        container.style.right = '20px';
        container.style.zIndex = 99999;
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = 'ck-toast ck-toast-' + type;
    toast.style.marginTop = '8px';
    toast.style.padding = '10px 14px';
    toast.style.borderRadius = '8px';
    toast.style.background = type === 'danger' ? 'rgba(220,53,69,0.95)' : 'rgba(13,110,253,0.95)';
    toast.style.color = '#fff';
    toast.style.boxShadow = '0 6px 18px rgba(0,0,0,0.12)';
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
    toast.innerText = message;
    container.appendChild(toast);
    // animate in
    requestAnimationFrame(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
    });
    // auto remove
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-6px)';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

async function loadDashboard() {
    const response = await fetch(`${apiBase}/files.php?action=list`);
    const data = await handleFormResponse(response);
    if (!data.success) {
        showAlert('Unable to load dashboard', 'danger');
        return;
    }
    renderFiles(data.files);
    renderFolders(data.folders);
    populateFolderSelect(data.folders);
    setDashboardStats(data.stats);
    renderActivityLog(data.activity || []);
    renderRecentFiles(data.files);
}

function populateFolderSelect(folders) {
    const folderSelect = document.getElementById('folder-select');
    const parentFolderSelect = document.getElementById('parent-folder-select');
    const parentFolderUploadSelect = document.getElementById('parent-folder-upload-select');
    const options = '<option value="">Root folder</option>' + folders.map(folder => `
        <option value="${folder.id}">${escapeHtml(folder.name)}</option>
    `).join('');
    if (folderSelect) {
        folderSelect.innerHTML = options;
    }
    if (parentFolderSelect) {
        parentFolderSelect.innerHTML = options;
    }
    if (parentFolderUploadSelect) {
        parentFolderUploadSelect.innerHTML = options;
    }
}

function formatSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) {
        bytes /= 1024;
        i += 1;
    }
    return `${bytes.toFixed(1)} ${units[i]}`;
}

function renderFiles(files) {
    const fileList = document.getElementById('file-list');
    if (!fileList) return;
    fileList.innerHTML = files.length ? files.map(file => `
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card h-100 file-card">
                <div class="card-body">
                    <h6 class="card-title">${file.original_name}</h6>
                    <p class="text-muted small mb-1">${file.mime_type} • ${formatSize(file.file_size)}</p>
                    <p class="text-muted small">Uploaded ${file.uploaded_at}</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="${file.download_url}" class="btn btn-sm btn-outline-primary" download>Download</a>
                        <button class="btn btn-sm btn-outline-secondary" onclick="openRenameModal(${file.id}, '${escapeHtml(file.original_name)}')">Rename</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteFile(${file.id})">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    `).join('') : '<div class="col-12"><p class="text-muted">No files yet. Upload your first document or image.</p></div>';
}

function renderFolders(folders) {
    // Populate sidebar Projects area instead
    renderProjects(folders);
}

function renderProjects(folders) {
    const projectsList = document.getElementById('projects-list');
    if (!projectsList) return;
    if (!folders.length) {
        projectsList.innerHTML = '<p class="text-muted small">No projects yet</p>';
        return;
    }
    projectsList.innerHTML = folders.map(folder => `
        <div class="project-item mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <a href="../views/folder.php?folder_id=${folder.id}">📁 <strong>${escapeHtml(folder.name)}</strong></a>
                    <div class="text-muted small">${folder.file_count} files</div>
                </div>
                <div>
                    <a href="#" class="btn btn-sm btn-outline-secondary btn-add-member" data-folder-id="${folder.id}" title="Add member">＋</a>
                </div>
            </div>
            <div class="project-members mt-1 small text-muted" id="members-for-${folder.id}">Members: <span class="text-muted">None</span></div>
        </div>
    `).join('');

    // load members for each project
    folders.forEach(f => loadProjectMembers(f.id));

    document.querySelectorAll('.btn-add-member').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const folderId = btn.dataset.folderId;
            const email = prompt('Enter member email (Gmail recommended):');
            if (!email) return;
            const resp = await fetch(`${apiBase}/members.php?action=add`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ folder_id: folderId, email }),
            });
            const data = await handleFormResponse(resp);
            if (data.success) {
                showAlert('Member added');
                loadProjectMembers(folderId);
            } else {
                showAlert(data.message || 'Could not add member', 'danger');
            }
        });
    });
}

async function loadProjectMembers(folderId) {
    const el = document.getElementById('members-for-' + folderId);
    if (!el) return;
    const resp = await fetch(`${apiBase}/members.php?action=list&folder_id=${folderId}`);
    const data = await handleFormResponse(resp);
    if (!data.success) {
        el.innerHTML = 'Members: <span class="text-muted">None</span>';
        return;
    }
    if (!data.members.length) {
        el.innerHTML = 'Members: <span class="text-muted">None</span>';
        return;
    }
    el.innerHTML = 'Members: ' + data.members.map(m => `<span title="${escapeHtml(m.email)}">${escapeHtml(m.name || m.email)}</span>`).join(', ');
}

async function openFileViewer(fileId) {
    const resp = await fetch(`${apiBase}/files.php?action=get&file_id=${fileId}`);
    const data = await handleFormResponse(resp);
    if (!data.success) {
        showAlert('Could not load file', 'danger');
        return;
    }
    const file = data.file;
    // Build modal
    const isText = file.mime_type.startsWith('text/') || /\.(php|js|py|html|css|java|c|cpp)$/.test(file.original_name.toLowerCase());
    let body = '';
    if (isText) {
        body = `
            <textarea id="file-editor" class="form-control" style="min-height:300px; font-family: monospace;">${escapeHtml(file.content || '')}</textarea>
            <div class="mt-2 text-end">
                <button class="btn btn-primary" id="save-file-btn">Save</button>
            </div>
        `;
    } else if (file.mime_type.startsWith('image/')) {
        body = `<div class="text-center"><img src="${file.download_url}" alt="${escapeHtml(file.original_name)}" style="max-width:100%; height:auto;"></div>`;
    } else {
        body = `<div class="text-muted">Preview not available. <a href="${file.download_url}" download>Download</a></div>`;
    }

    const modalHtml = `
        <div class="modal fade" id="fileViewerModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${escapeHtml(file.original_name)}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">${body}</div>
                </div>
            </div>
        </div>
    `;
    const existing = document.getElementById('fileViewerModal');
    if (existing) existing.remove();
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('fileViewerModal'));
    modal.show();
    document.getElementById('fileViewerModal').addEventListener('hidden.bs.modal', function () { this.remove(); });

    if (isText) {
        document.getElementById('save-file-btn').addEventListener('click', async () => {
            const content = document.getElementById('file-editor').value;
            const res = await fetch(`${apiBase}/edit_file.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ file_id: fileId, content }),
            });
            const r = await handleFormResponse(res);
            if (r.success) {
                showAlert('File saved');
                modal.hide();
                await loadDashboard();
            } else {
                showAlert(r.message || 'Could not save file', 'danger');
            }
        });
    }
}

function setDashboardStats(stats) {
    ['total-files', 'total-folders', 'used-storage'].forEach(key => {
        const el = document.getElementById(key);
        if (!el) return;
        el.textContent = stats[key] || '0';
    });
}

function renderActivityLog(logs) {
    const activityArea = document.getElementById('activity-log');
    const navActivity = document.getElementById('nav-activity-list');
    const emptyHtml = '<li class="list-group-item text-muted">Activity log is empty.</li>';
    if (!logs.length) {
        if (activityArea) activityArea.innerHTML = emptyHtml;
        if (navActivity) navActivity.innerHTML = emptyHtml;
        return;
    }
    const itemsHtml = logs.map(entry => `
        <li class="list-group-item">
            <strong>${escapeHtml(entry.action)}</strong>
            <div class="small text-muted">${entry.target_type || ''} ${entry.target_name || ''}</div>
            <div class="small text-muted">${entry.created_at}</div>
        </li>
    `).join('');
    if (activityArea) activityArea.innerHTML = itemsHtml;
    if (navActivity) navActivity.innerHTML = itemsHtml;
}

function renderRecentFiles(files) {
    const recentSection = document.getElementById('recent-files-section');
    if (!recentSection) return;
    if (!files.length) {
        recentSection.innerHTML = '<p class="text-muted small">No files yet</p>';
        return;
    }
    const recent = files.slice(0, 8);
    recentSection.innerHTML = recent.map(file => `
        <div class="d-flex align-items-center justify-content-between mb-2 p-2 border rounded" style="font-size: 0.85rem;">
            <div style="flex: 1; min-width: 0;">
                <div class="text-truncate"><strong>${escapeHtml(file.original_name)}</strong></div>
                <div class="text-muted small text-truncate">${formatSize(file.file_size)}</div>
            </div>
            <div class="d-flex gap-1" style="flex-shrink: 0;">
                <a href="${file.download_url}" class="btn btn-sm btn-outline-primary" download title="Download">↓</a>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteFile(${file.id})" title="Delete">✕</button>
            </div>
        </div>
    `).join('');
}

function escapeHtml(text) {
    return text.replace(/["&'<>]/g, function(a) {
        return { '"':'&quot;', '&':'&amp;', "'":'&#39;', '<':'&lt;', '>':'&gt;' }[a];
    });
}

async function deleteFile(fileId) {
    if (!confirm('Delete this file?')) return;
    const response = await fetch(`${apiBase}/files.php?action=delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ file_id: fileId }),
    });
    const data = await handleFormResponse(response);
    if (data.success) {
        showAlert('File deleted successfully');
        await loadDashboard();
    } else {
        showAlert(data.message || 'Could not delete file', 'danger');
    }
}

async function deleteFolder(folderId) {
    if (!confirm('Delete this folder and its contents?')) return;
    const response = await fetch(`${apiBase}/folders.php?action=delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ folder_id: folderId }),
    });
    const data = await handleFormResponse(response);
    if (data.success) {
        showAlert('Folder deleted successfully');
        await loadDashboard();
    } else {
        showAlert(data.message || 'Could not delete folder', 'danger');
    }
}

async function openRenameModal(fileId, fileName) {
    const newName = prompt('Enter new file name:', fileName);
    if (!newName || newName === fileName) return;
    const response = await fetch(`${apiBase}/files.php?action=rename`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ file_id: fileId, new_name: newName }),
    });
    const data = await handleFormResponse(response);
    if (data.success) {
        showAlert('File renamed successfully');
        await loadDashboard();
    } else {
        showAlert(data.message || 'Rename failed', 'danger');
    }
}

async function moveToFolder(folderId) {
    const fileId = prompt('Enter file ID to move into this folder:');
    if (!fileId) return;
    const response = await fetch(`${apiBase}/folders.php?action=move`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ file_id: parseInt(fileId, 10), folder_id: folderId }),
    });
    const data = await handleFormResponse(response);
    if (data.success) {
        showAlert('File moved successfully');
        await loadDashboard();
    } else {
        showAlert(data.message || 'Move failed', 'danger');
    }
}

async function openFolderView(folderId, folderName) {
    const response = await fetch(`${apiBase}/files.php?action=list_folder&folder_id=${folderId}`);
    const data = await handleFormResponse(response);
    if (!data.success) {
        showAlert('Could not load folder', 'danger');
        return;
    }
    showFolderModal(folderId, folderName, data.folders, data.files);
}

function showFolderModal(folderId, folderName, folders, files) {
    const modalHtml = `
        <div class="modal fade" id="folderModal" tabindex="-1" aria-labelledby="folderModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="folderModalLabel">📁 ${escapeHtml(folderName)}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <h6 class="mb-3">Subfolders (${folders.length})</h6>
                        <div class="mb-4" id="subfolders-section">
                            ${folders.length ? folders.map(folder => `
                                <div class="d-flex align-items-center justify-content-between p-2 border rounded mb-2 hover-bg-light" style="cursor: pointer;" onclick="openFolderView(${folder.id}, '${escapeHtml(folder.name)}')">
                                    <div>
                                        <strong>📁 ${escapeHtml(folder.name)}</strong>
                                        <div class="small text-muted">${folder.file_count} files</div>
                                    </div>
                                    <span class="text-primary">→</span>
                                </div>
                            `).join('') : '<p class="text-muted">No subfolders</p>'}
                        </div>
                        <h6 class="mb-3">Files in this folder (${files.length})</h6>
                        <div id="folder-files-section">
                            ${files.length ? files.map(file => `
                                <div class="d-flex align-items-center justify-content-between p-2 border rounded mb-2">
                                    <div>
                                        <strong>${escapeHtml(file.original_name)}</strong>
                                        <div class="small text-muted">${file.mime_type} • ${formatSize(file.file_size)}</div>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <a href="${file.download_url}" class="btn btn-sm btn-outline-primary" download>↓</a>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="openRenameFolderModal(${file.id}, '${escapeHtml(file.original_name)}')">✎</button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteFileFromFolder(${file.id})">✕</button>
                                    </div>
                                </div>
                            `).join('') : '<p class="text-muted">No files in this folder</p>'}
                        </div>
                        <hr class="my-4">
                        <h6 class="mb-3">Upload files to this folder</h6>
                        <form id="folder-upload-form" onsubmit="uploadToFolder(event, ${folderId})">
                            <div class="mb-3">
                                <label class="form-label">Choose files</label>
                                <input type="file" name="files[]" class="form-control" multiple required>
                            </div>
                            <button type="submit" class="btn btn-primary">Upload</button>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-danger" onclick="deleteFolderFromModal(${folderId}, '${escapeHtml(folderName)}')">Delete Folder</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const existingModal = document.getElementById('folderModal');
    if (existingModal) {
        existingModal.remove();
    }
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('folderModal'));
    modal.show();
    document.getElementById('folderModal').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}

async function uploadToFolder(event, folderId) {
    event.preventDefault();
    const uploadInput = event.target.querySelector('input[type="file"]');
    if (!uploadInput.files.length) {
        showAlert('Choose at least one file to upload', 'danger');
        return;
    }
    const formData = new FormData();
    Array.from(uploadInput.files).forEach(file => formData.append('files[]', file));
    formData.append('folder_id', folderId);
    const response = await fetch(`${apiBase}/files.php?action=upload`, {
        method: 'POST',
        body: formData,
    });
    const data = await handleFormResponse(response);
    if (data.success) {
        showAlert(data.message || 'Upload complete');
        uploadInput.value = '';
        await openFolderView(folderId, document.querySelector('.modal-title')?.textContent?.replace('📁 ', '') || 'Folder');
    } else {
        showAlert(data.message || 'Upload failed', 'danger');
    }
}

async function deleteFileFromFolder(fileId) {
    if (!confirm('Delete this file?')) return;
    const response = await fetch(`${apiBase}/files.php?action=delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ file_id: fileId }),
    });
    const data = await handleFormResponse(response);
    if (data.success) {
        showAlert('File deleted successfully');
        const folderId = document.querySelector('.modal-body')?.dataset?.folderId || 0;
        await loadDashboard();
    } else {
        showAlert(data.message || 'Could not delete file', 'danger');
    }
}

async function deleteFolderFromModal(folderId, folderName) {
    if (!confirm(`Delete "${folderName}" and all its contents?`)) return;
    const response = await fetch(`${apiBase}/folders.php?action=delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ folder_id: folderId }),
    });
    const data = await handleFormResponse(response);
    if (data.success) {
        showAlert('Folder deleted successfully');
        const modal = bootstrap.Modal.getInstance(document.getElementById('folderModal'));
        modal.hide();
        await loadDashboard();
    } else {
        showAlert(data.message || 'Could not delete folder', 'danger');
    }
}

async function openRenameFolderModal(fileId, fileName) {
    const newName = prompt('Enter new file name:', fileName);
    if (!newName || newName === fileName) return;
    const response = await fetch(`${apiBase}/files.php?action=rename`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ file_id: fileId, new_name: newName }),
    });
    const data = await handleFormResponse(response);
    if (data.success) {
        showAlert('File renamed successfully');
        const folderId = document.querySelector('[data-folder-id]')?.dataset?.folderId || 0;
    } else {
        showAlert(data.message || 'Rename failed', 'danger');
    }
}

async function searchFiles() {
    const queryEl = document.getElementById('navbar-search') || document.getElementById('search-query');
    const filterEl = document.getElementById('search-filter');
    const query = (queryEl ? queryEl.value : '').trim();
    const filter = filterEl ? filterEl.value : 'all';
    if (!query) {
        showAlert('Enter a search term', 'danger');
        return;
    }
    const response = await fetch(`${apiBase}/search.php?q=${encodeURIComponent(query)}&filter=${encodeURIComponent(filter)}`);
    const data = await handleFormResponse(response);
    if (!data.success) {
        showAlert('Search failed', 'danger');
        return;
    }
    const resultArea = document.getElementById('search-results');
    if (!resultArea) return;
    if (!data.results.length) {
        resultArea.innerHTML = '<div class="alert alert-secondary">No files matched your search.</div>';
        return;
    }
    resultArea.innerHTML = data.results.map(item => `
        <div class="card mb-2 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>${escapeHtml(item.original_name)}</strong>
                    <div class="small text-muted">${item.mime_type} • ${formatSize(item.file_size)}</div>
                </div>
                <a href="${item.download_url}" class="btn btn-sm btn-outline-primary">Download</a>
            </div>
        </div>
    `).join('');
}

async function createFolder(event) {
    event.preventDefault();
    const name = document.getElementById('folder-upload-name').value.trim();
    const parentId = document.getElementById('parent-folder-upload-select')?.value || '';
    if (!name) return showAlert('Folder name is required', 'danger');
    const response = await fetch(`${apiBase}/folders.php?action=create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ folder_name: name, parent_id: parentId || null }),
    });
    const data = await handleFormResponse(response);
    if (data.success) {
        showAlert('Folder created');
        document.getElementById('folder-upload-name').value = '';
        await loadDashboard();
    } else {
        showAlert(data.message || 'Could not create folder', 'danger');
    }
}

async function uploadFiles(event) {
    event.preventDefault();
    const uploadInput = document.getElementById('upload-files');
    const folderId = document.getElementById('folder-select').value || '';
    if (!uploadInput.files.length) return showAlert('Choose at least one file to upload', 'danger');
    const formData = new FormData();
    Array.from(uploadInput.files).forEach(file => formData.append('files[]', file));
    if (folderId) {
        formData.append('folder_id', folderId);
    }
    const response = await fetch(`${apiBase}/files.php?action=upload`, {
        method: 'POST',
        body: formData,
    });
    const data = await handleFormResponse(response);
    if (data.success) {
        showAlert(data.message || 'Upload complete');
        uploadInput.value = '';
        await loadDashboard();
    } else {
        showAlert(data.message || 'Upload failed', 'danger');
    }
}

function setupDragDrop() {
    const dropZone = document.getElementById('upload-dropzone');
    if (!dropZone) return;
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('dragover');
        });
    });
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('dragover');
        });
    });
    dropZone.addEventListener('drop', async (e) => {
        const files = e.dataTransfer.files;
        if (!files.length) return;
        const uploadInput = document.getElementById('upload-files');
        uploadInput.files = files;
        await uploadFiles(new Event('submit'));
    });
}

window.addEventListener('DOMContentLoaded', () => {
    const authForm = document.getElementById('auth-form');
    const dashboardForm = document.getElementById('upload-form');
    const folderForm = document.getElementById('folder-upload-form');
    if (authForm) {
        authForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const endpoint = authForm.dataset.endpoint;
            await sendAuthForm(authForm, endpoint);
        });
    }
    if (dashboardForm) {
        dashboardForm.addEventListener('submit', uploadFiles);
    }
    if (folderForm) {
        folderForm.addEventListener('submit', createFolder);
    }
    setupDragDrop();
    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const response = await fetch(`${apiBase}/profile.php`, {
                method: 'POST',
                body: new FormData(profileForm),
            });
            const data = await handleFormResponse(response);
            if (data.success) {
                showAlert(data.message || 'Profile saved');
            } else {
                showAlert(data.message || 'Unable to save profile', 'danger');
            }
        });
    }
    if (document.body.dataset.dashboard === 'true') {
        loadDashboard();
    }
    // About toggle
    const aboutToggle = document.getElementById('about-toggle');
    const aboutSection = document.getElementById('about-section');
    if (aboutToggle && aboutSection) {
        aboutToggle.addEventListener('click', () => {
            aboutSection.classList.toggle('collapsed');
        });
    }
    // Profile button opens floating modal
    const profileBtn = document.getElementById('profile-btn');
    if (profileBtn) {
        profileBtn.addEventListener('click', () => {
            const modalEl = document.getElementById('profileModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        });
    }

    // Project search (filters projects list client-side)
    const projectSearch = document.getElementById('project-search');
    if (projectSearch) {
        projectSearch.addEventListener('input', () => {
            const q = projectSearch.value.trim().toLowerCase();
            document.querySelectorAll('#projects-list .project-item').forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = q ? (text.includes(q) ? '' : 'none') : '';
            });
        });
    }
});
