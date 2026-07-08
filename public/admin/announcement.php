<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    // TODO: adjust this path to match where your login.php actually lives
    header("Location: ../../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements | SUA IntelliLearn Admin</title>

<!-- Fonts & Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Announcements Stylesheet -->
<link rel="stylesheet" href="assests/css/announcement.css">
</head>
<body>

<?php include '../../includes/admin_sidebar.php';  ?>

<div class="main-content" id="mainContent">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1>Announcements</h1>
            <p>Post updates and notices visible to students, teachers, parents, and staff.</p>
        </div>
        <button class="btn-primary" id="newAnnouncementBtn" onclick="toggleCompose()">
            <i class="fas fa-plus"></i> New Announcement
        </button>
    </div>

    <!-- Quick Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-bullhorn"></i></div>
            <div>
                <div class="stat-value">42</div>
                <div class="stat-label">Total Announcements</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="stat-value">36</div>
                <div class="stat-label">Published</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gold"><i class="fas fa-file-alt"></i></div>
            <div>
                <div class="stat-value">6</div>
                <div class="stat-label">Drafts</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div>
            <div>
                <div class="stat-value">3</div>
                <div class="stat-label">Urgent Active</div>
            </div>
        </div>
    </div>

    <!-- Compose Panel (hidden by default) -->
    <div class="compose-panel" id="composePanel" style="display:none;">
        <div class="compose-panel-header">
            <h2><i class="fas fa-pen-to-square"></i>&nbsp; Create Announcement</h2>
            <div class="close-icon" onclick="toggleCompose()"><i class="fas fa-times"></i></div>
        </div>

        <div class="form-group">
            <label>Title</label>
            <input type="text" class="form-control" placeholder="e.g. Class Suspension Notice for June 12">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Audience</label>
                <div class="audience-options">
                    <label class="audience-chip checked">
                        <input type="checkbox" checked> All School
                    </label>
                    <label class="audience-chip">
                        <input type="checkbox"> Students
                    </label>
                    <label class="audience-chip">
                        <input type="checkbox"> Teachers
                    </label>
                    <label class="audience-chip">
                        <input type="checkbox"> Parents / Guardians
                    </label>
                    <label class="audience-chip">
                        <input type="checkbox"> Staff
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label>Priority</label>
                <div class="priority-options">
                    <div class="priority-pill active" data-level="normal" onclick="setPriority(this)">Normal</div>
                    <div class="priority-pill" data-level="important" onclick="setPriority(this)">Important</div>
                    <div class="priority-pill" data-level="urgent" onclick="setPriority(this)">Urgent</div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Message</label>
            <div class="editor-toolbar">
                <button type="button"><i class="fas fa-bold"></i></button>
                <button type="button"><i class="fas fa-italic"></i></button>
                <button type="button"><i class="fas fa-underline"></i></button>
                <div class="divider"></div>
                <button type="button"><i class="fas fa-list-ul"></i></button>
                <button type="button"><i class="fas fa-list-ol"></i></button>
                <div class="divider"></div>
                <button type="button"><i class="fas fa-link"></i></button>
                <button type="button"><i class="fas fa-image"></i></button>
            </div>
            <textarea class="form-control" placeholder="Write the announcement details here..."></textarea>
            <div class="attach-row">
                <div class="attach-btn"><i class="fas fa-paperclip"></i> Attach File</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Schedule (optional)</label>
                <input type="datetime-local" class="form-control">
            </div>
            <div class="form-group">
                <label>Expires On (optional)</label>
                <input type="date" class="form-control">
            </div>
        </div>

        <div class="compose-actions">
            <div class="left-actions">
                <i class="fas fa-thumbtack"></i> Pin to top of announcements
            </div>
            <div class="right-actions">
                <button class="btn-secondary" onclick="toggleCompose()">Cancel</button>
                <button class="btn-secondary">Save as Draft</button>
                <button class="btn-gold"><i class="fas fa-paper-plane"></i> Publish</button>
            </div>
        </div>
    </div>

    <!-- Filter / Search Toolbar -->
    <div class="toolbar-row">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search announcements...">
        </div>
        <select class="select-filter">
            <option>All Audiences</option>
            <option>Students</option>
            <option>Teachers</option>
            <option>Parents / Guardians</option>
            <option>Staff</option>
        </select>
        <select class="select-filter">
            <option>All Status</option>
            <option>Published</option>
            <option>Draft</option>
            <option>Scheduled</option>
        </select>
        <select class="select-filter">
            <option>Newest First</option>
            <option>Oldest First</option>
            <option>Most Viewed</option>
        </select>
    </div>

    <!-- Announcements List -->
    <div class="announcements-list">

        <div class="announcement-card pinned">
            <div class="priority-strip urgent"></div>
            <div class="announcement-body">
                <div class="announcement-top-row">
                    <div class="announcement-title-line">
                        <i class="fas fa-thumbtack pin-icon"></i>
                        <h3>Class Suspension — Typhoon Signal No. 2</h3>
                        <span class="badge urgent">Urgent</span>
                    </div>
                </div>
                <p class="announcement-excerpt">
                    In observance of the weather advisory issued by PAGASA, all classes from Kinder to Grade 12 are suspended for tomorrow, July 8. Please stay safe and monitor official channels for updates.
                </p>
                <div class="announcement-meta">
                    <span><i class="fas fa-user"></i> Maria Santos</span>
                    <span><i class="far fa-clock"></i> Posted 2 hours ago</span>
                    <span><i class="far fa-eye"></i> 812 views</span>
                    <div class="audience-tags">
                        <span class="tag">All School</span>
                    </div>
                </div>
            </div>
            <div class="card-actions">
                <div class="icon-btn" title="Edit"><i class="fas fa-pen"></i></div>
                <div class="icon-btn" title="Unpin"><i class="fas fa-thumbtack"></i></div>
                <div class="icon-btn delete" title="Delete"><i class="fas fa-trash"></i></div>
            </div>
        </div>

        <div class="announcement-card">
            <div class="priority-strip important"></div>
            <div class="announcement-body">
                <div class="announcement-top-row">
                    <div class="announcement-title-line">
                        <h3>Enrollment Period for SY 2026–2027 Now Open</h3>
                        <span class="badge important">Important</span>
                    </div>
                </div>
                <p class="announcement-excerpt">
                    The registrar's office is now accepting enrollment applications for the upcoming school year. Parents and guardians are encouraged to complete the online form before August 15.
                </p>
                <div class="announcement-meta">
                    <span><i class="fas fa-user"></i> Registrar's Office</span>
                    <span><i class="far fa-clock"></i> Posted yesterday</span>
                    <span><i class="far fa-eye"></i> 540 views</span>
                    <div class="audience-tags">
                        <span class="tag">Parents / Guardians</span>
                        <span class="tag">Students</span>
                    </div>
                </div>
            </div>
            <div class="card-actions">
                <div class="icon-btn" title="Edit"><i class="fas fa-pen"></i></div>
                <div class="icon-btn" title="Pin"><i class="far fa-thumbtack"></i></div>
                <div class="icon-btn delete" title="Delete"><i class="fas fa-trash"></i></div>
            </div>
        </div>

        <div class="announcement-card">
            <div class="priority-strip normal"></div>
            <div class="announcement-body">
                <div class="announcement-top-row">
                    <div class="announcement-title-line">
                        <h3>Faculty Meeting — General Assembly</h3>
                        <span class="badge normal">Normal</span>
                    </div>
                </div>
                <p class="announcement-excerpt">
                    All faculty members are requested to attend the general assembly this Friday at 3:00 PM in the Multipurpose Hall to discuss the second quarter academic calendar.
                </p>
                <div class="announcement-meta">
                    <span><i class="fas fa-user"></i> Academic Affairs</span>
                    <span><i class="far fa-clock"></i> Posted 3 days ago</span>
                    <span><i class="far fa-eye"></i> 128 views</span>
                    <div class="audience-tags">
                        <span class="tag">Teachers</span>
                        <span class="tag">Staff</span>
                    </div>
                </div>
            </div>
            <div class="card-actions">
                <div class="icon-btn" title="Edit"><i class="fas fa-pen"></i></div>
                <div class="icon-btn" title="Pin"><i class="far fa-thumbtack"></i></div>
                <div class="icon-btn delete" title="Delete"><i class="fas fa-trash"></i></div>
            </div>
        </div>

        <div class="announcement-card">
            <div class="priority-strip normal"></div>
            <div class="announcement-body">
                <div class="announcement-top-row">
                    <div class="announcement-title-line">
                        <h3>Intramurals 2026 Schedule Released</h3>
                        <span class="badge draft">Draft</span>
                    </div>
                </div>
                <p class="announcement-excerpt">
                    The official schedule for this year's intramural sports events has been finalized. Game brackets and venue assignments will be shared through homeroom advisers.
                </p>
                <div class="announcement-meta">
                    <span><i class="fas fa-user"></i> Sports Coordinator</span>
                    <span><i class="far fa-clock"></i> Last edited 5 days ago</span>
                    <span><i class="far fa-eye"></i> — views</span>
                    <div class="audience-tags">
                        <span class="tag">All School</span>
                    </div>
                </div>
            </div>
            <div class="card-actions">
                <div class="icon-btn" title="Edit"><i class="fas fa-pen"></i></div>
                <div class="icon-btn" title="Pin"><i class="far fa-thumbtack"></i></div>
                <div class="icon-btn delete" title="Delete"><i class="fas fa-trash"></i></div>
            </div>
        </div>

    </div>

    <!-- Pagination -->
    <div class="pagination">
        <div class="page-btn"><i class="fas fa-chevron-left"></i></div>
        <div class="page-btn active">1</div>
        <div class="page-btn">2</div>
        <div class="page-btn">3</div>
        <div class="page-btn"><i class="fas fa-chevron-right"></i></div>
    </div>

</div>

<script>
    // ---- Sidebar collapse/expand (shared with sidebar module) ----
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    }

    // ---- Sidebar nav active state (shared with sidebar module) ----
    function setActive(el) {
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        el.classList.add('active');
    }

    // ---- Compose panel show/hide (UI only, no data handling) ----
    function toggleCompose() {
        const panel = document.getElementById('composePanel');
        panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
        if (panel.style.display === 'block') {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // ---- Priority pill selector (visual state only) ----
    function setPriority(el) {
        document.querySelectorAll('.priority-pill').forEach(p => p.classList.remove('active'));
        el.classList.add('active');
    }

    // ---- Audience chip toggle (visual state only) ----
    document.querySelectorAll('.audience-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            chip.classList.toggle('checked');
        });
    });
</script>

</body>
</html>
