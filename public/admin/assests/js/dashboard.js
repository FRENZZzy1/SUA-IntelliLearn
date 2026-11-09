function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}

// Active Nav State
function setActive(el) {
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    el.classList.add('active');
}

// Toast Notification
function showToast(message) {
    const toast = document.getElementById('toast');
    const msg = document.getElementById('toast-msg');
    msg.textContent = message;
    toast.style.transform = 'translateY(0)';
    toast.style.opacity = '1';
    setTimeout(() => {
        toast.style.transform = 'translateY(100px)';
        toast.style.opacity = '0';
    }, 2500);
}

// Animate progress bars on load
window.addEventListener('load', () => {
    document.querySelectorAll('.progress-bar-fill').forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => {
            bar.style.width = width;
        }, 300);
    });
});


function loadAnnouncements() {
    fetch('assests/api/get_announcements.php?limit=5', {
        headers: { 'Accept': 'application/json' }
    })
        .then(res => res.json())
        .then(data => {
            const list = document.querySelector('.announcement-list');
            if (!data.success || data.announcements.length === 0) {
                list.innerHTML = '<p class="field-note">No announcements yet.</p>';
                return;
            }

            list.innerHTML = data.announcements.map(a => {
                const sortSource = a.published_at || a.created_at;
                const dateStr = new Date(sortSource.replace(' ', 'T'))
                    .toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                const isOwner = typeof CURRENT_USER_ID !== 'undefined' && Number(a.posted_by) === Number(CURRENT_USER_ID);
                const actionsHtml = isOwner
                    ? `<a class="action-link" onclick="editAnnouncement(${a.announcement_id})">Edit</a>
                       <a class="action-link danger" onclick="deleteAnnouncement(${a.announcement_id})">Delete</a>`
                    : '';

                return `
                <div class="announcement-item">
                    <div class="announcement-meta">
                        <span class="announcement-date">${dateStr}</span>
                    </div>
                    <div class="announcement-title">${escapeHtml(a.title)}</div>
                    <div class="announcement-desc">${escapeHtml(a.body)}</div>
                    <div class="announcement-actions">
                        ${actionsHtml}
                    </div>
                </div>
            `;
            }).join('');
        })
        .catch(() => {
            document.querySelector('.announcement-list').innerHTML =
                '<p class="field-note">Failed to load announcements.</p>';
        });
}

function editAnnouncement(id) {
    // Reuses the existing edit panel on announcement.php via its ?edit= deep link.
    window.location.href = 'announcement.php?edit=' + encodeURIComponent(id);
}

function deleteAnnouncement(id) {
    if (!confirm('Delete this announcement?')) return;

    fetch('assests/api/delete_announcement.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json'
        },
        body: 'announcement_id=' + encodeURIComponent(id) + '&csrf=' + encodeURIComponent(PENDING_ENROLL_CSRF)
    })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                showToast((data.errors && data.errors[0]) || 'Failed to delete announcement.');
                return;
            }
            loadAnnouncements();
        })
        .catch(() => showToast('Failed to delete announcement.'));
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

// editCourseOffering() is defined inline in dashboard.php (next to the Update
// Course modal it opens), so the widget's edit stays on the dashboard instead
// of navigating away.

// Quick Actions -> "Backup Data"
// Streams a full .sql dump from assests/api/backup_data.php and saves it
// as a file download. Errors come back as JSON; success comes back as the
// raw .sql file, so we branch on the response Content-Type.
let backupInProgress = false;

function backupData(btnEl) {
    if (backupInProgress) return;
    if (!confirm('Generate a full database backup now? This may take a moment for large datasets.')) return;

    backupInProgress = true;
    if (btnEl) {
        btnEl.disabled = true;
        btnEl.dataset.originalHtml = btnEl.innerHTML;
        btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Backing up...</span>';
    }

    fetch('assests/api/backup_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/sql, application/json'
        },
        body: 'csrf=' + encodeURIComponent(PENDING_ENROLL_CSRF)
    })
        .then(async res => {
            const contentType = res.headers.get('Content-Type') || '';

            if (contentType.includes('application/json')) {
                const data = await res.json();
                throw new Error((data.errors && data.errors[0]) || 'Backup failed.');
            }
            if (!res.ok) {
                throw new Error('Backup failed.');
            }

            const disposition = res.headers.get('Content-Disposition') || '';
            const match = disposition.match(/filename="?([^"]+)"?/);
            const filename = match ? match[1] : `backup_${Date.now()}.sql`;

            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);

            showToast('Backup downloaded successfully.');
        })
        .catch(err => {
            showToast(err.message || 'Failed to back up data.');
        })
        .finally(() => {
            backupInProgress = false;
            if (btnEl) {
                btnEl.disabled = false;
                btnEl.innerHTML = btnEl.dataset.originalHtml;
            }
        });
}







document.addEventListener('DOMContentLoaded', loadAnnouncements);