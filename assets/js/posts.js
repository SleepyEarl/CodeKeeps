const postsApiBase = typeof apiBase !== 'undefined' ? apiBase : '../api';

async function loadPosts() {
    try {
        const resp = await fetch(postsApiBase + '/posts.php?action=list');
        const data = await handleFormResponse(resp);
        if (!data.success) {
            showAlert(data.message || 'Unable to load posts', 'danger');
            return;
        }
        const container = document.getElementById('posts-list');
        if (!container) return;
        container.innerHTML = '';
        if (!data.posts.length) {
            container.innerHTML = '<div class="alert alert-secondary">No posts yet. Share your first update.</div>';
            return;
        }
        data.posts.forEach(p => {
            const el = document.createElement('div');
            el.className = 'post mb-3';
            const isOwner = p.user_id === currentUserId;
            el.innerHTML = `
                <div class="card p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <div><strong>${escapeHtml(p.name || 'User')}</strong></div>
                            <div class="text-muted small">${escapeHtml(p.created_at)}</div>
                        </div>
                        ${isOwner ? `<div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-secondary" onclick="editPost(${p.id}, '${escapeHtml(p.title || '')}', '${escapeHtml(p.content)}')">Edit</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deletePost(${p.id})">Delete</button>
                        </div>` : ''}
                    </div>
                    ${p.title ? `<h6>${escapeHtml(p.title)}</h6>` : ''}
                    <div class="mb-2">${escapeHtml(p.content)}</div>
                    ${p.attachment ? `<div class="mb-2"><img src="../uploads/${p.attachment}" alt="attachment" style="max-width: 100%; max-height: 300px; border-radius: 8px;"></div>` : ''}
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="toggleReact(${p.id}, this)">Like (${p.reactions})</button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="focusComment(${p.id})">Comment (${p.comments.length})</button>
                    </div>
                    <div class="mt-2 comments-list">
                        ${p.comments.map(c => `<div class="small text-muted"><strong>${escapeHtml(c.name)}</strong>: ${escapeHtml(c.comment)}</div>`).join('')}
                    </div>
                    <div class="mt-2">
                        <input placeholder="Write a comment..." class="form-control form-control-sm" id="comment-input-${p.id}" onkeydown="if(event.key==='Enter') sendComment(${p.id})">
                    </div>
                </div>
            `;
            container.appendChild(el);
        });
    } catch (err) {
        showAlert(err.message || 'Unable to load posts', 'danger');
    }
}

async function toggleReact(postId, btn) {
    try {
        const resp = await fetch(postsApiBase + '/posts.php?action=react', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: postId, type: 'like' }),
        });
        const data = await handleFormResponse(resp);
        if (data.success) {
            loadPosts();
        } else {
            showAlert(data.message || 'Error', 'danger');
        }
    } catch (err) {
        showAlert(err.message || 'Unable to react to post', 'danger');
    }
}

async function sendComment(postId) {
    try {
        const input = document.getElementById('comment-input-' + postId);
        if (!input) return;
        const comment = input.value.trim();
        if (!comment) return;
        const fd = new FormData();
        fd.append('post_id', postId);
        fd.append('comment', comment);
        const resp = await fetch(postsApiBase + '/posts.php?action=comment', { method: 'POST', body: fd });
        const data = await handleFormResponse(resp);
        if (data.success) {
            input.value = '';
            loadPosts();
        } else {
            showAlert(data.message || 'Could not comment', 'danger');
        }
    } catch (err) {
        showAlert(err.message || 'Unable to submit comment', 'danger');
    }
}

function focusComment(postId) {
    const el = document.getElementById('comment-input-' + postId);
    if (el) el.focus();
}

async function deletePost(postId) {
    if (!confirm('Delete this post?')) return;
    try {
        const fd = new FormData();
        fd.append('post_id', postId);
        const resp = await fetch(postsApiBase + '/posts.php?action=delete', { method: 'POST', body: fd });
        const data = await handleFormResponse(resp);
        if (data.success) {
            showAlert('Post deleted');
            loadPosts();
        } else {
            showAlert(data.message || 'Could not delete post', 'danger');
        }
    } catch (err) {
        showAlert(err.message || 'Unable to delete post', 'danger');
    }
}

async function editPost(postId, title, content) {
    const newTitle = prompt('Edit title:', title);
    if (newTitle === null) return;
    const newContent = prompt('Edit content:', content);
    if (newContent === null) return;
    try {
        const fd = new FormData();
        fd.append('post_id', postId);
        fd.append('title', newTitle);
        fd.append('content', newContent);
        const resp = await fetch(postsApiBase + '/posts.php?action=update', { method: 'POST', body: fd });
        const data = await handleFormResponse(resp);
        if (data.success) {
            showAlert('Post updated');
            loadPosts();
        } else {
            showAlert(data.message || 'Could not update post', 'danger');
        }
    } catch (err) {
        showAlert(err.message || 'Unable to update post', 'danger');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const postForm = document.getElementById('post-form');
    if (document.getElementById('posts-list')) {
        loadPosts();
    }
    if (postForm) {
        postForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const title = document.getElementById('post-title').value.trim();
            const content = document.getElementById('post-content').value.trim();
            const fileInput = document.getElementById('post-attachment');
            if (!content) return showAlert('Post cannot be empty', 'danger');
            try {
                const fd = new FormData();
                fd.append('title', title);
                fd.append('content', content);
                if (fileInput && fileInput.files.length > 0) {
                    fd.append('attachment', fileInput.files[0]);
                }
                const resp = await fetch(postsApiBase + '/posts.php?action=create', { method: 'POST', body: fd });
                const data = await handleFormResponse(resp);
                if (data.success) {
                    document.getElementById('post-title').value = '';
                    document.getElementById('post-content').value = '';
                    if (fileInput) fileInput.value = '';
                    showAlert('Post created');
                    await loadPosts();
                } else {
                    showAlert(data.message || 'Could not create post', 'danger');
                }
            } catch (err) {
                showAlert(err.message || 'Unable to create post', 'danger');
            }
        });
    }
});
