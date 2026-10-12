<?php
/**
 * SUA IntelliLearn - Teacher Announcements
 * Recycled from public/admin/announcement.php.
 *
 * Differences from the admin page:
 *  - Teachers can only post to students (audience is fixed to 'students').
 *  - Teachers only manage their own announcements (edit/pin/delete/publish).
 *  - A second tab shows published admin announcements aimed at teachers
 *    (read-only).
 *  - State-changing actions use POST + CSRF instead of GET links.
 */
require_once '../../config/config.php';

requireTeacher();

$currentUserId = (int) $_SESSION['user_id'];
$csrfToken     = generateCSRFToken();

// Resolve this teacher's id (used to scope admin announcements tied to a class).
$stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$currentUserId]);
$teacherId = (int) $stmt->fetchColumn();

// Classes this teacher handles (an announcement must be aimed at one of these).
$stmt = $pdo->prepare("
    SELECT co.offering_id, co.class_code, co.quarter,
           sub.subject_name, sec.grade_level, sec.section_name
    FROM classofferings co
    JOIN subjects sub ON sub.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    JOIN schoolyears sy ON sy.school_year_id = co.school_year_id
    WHERE co.teacher_id = :tid AND co.status = 'active' AND sy.is_current = 1
    ORDER BY sub.subject_name, sec.grade_level, sec.section_name, co.quarter
");
$stmt->execute([':tid' => $teacherId]);
$myOfferings   = $stmt->fetchAll(PDO::FETCH_ASSOC);
$myOfferingIds = array_map('intval', array_column($myOfferings, 'offering_id'));

/* ---------------------------------------------------------
   Handle POST actions: save (publish / draft), pin, delete, publish draft
--------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid request. Please refresh the page and try again.');
    }

    // ---- Create / edit ----
    if (isset($_POST['form_action'])) {
        $announcementId = (int) ($_POST['announcement_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $body     = trim($_POST['body'] ?? '');
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        $status   = ($_POST['form_action'] === 'publish') ? 'published' : 'draft';
        $audience = 'students'; // teachers always post to their students

        // Target class: a single offering the teacher owns, or 'all' = every class of theirs.
        $offeringRaw = $_POST['offering_id'] ?? '';
        if ($offeringRaw === 'all' && $announcementId === 0) {
            $targetOfferings = $myOfferingIds;
        } elseif (ctype_digit((string) $offeringRaw) && in_array((int) $offeringRaw, $myOfferingIds, true)) {
            $targetOfferings = [(int) $offeringRaw];
        } else {
            $targetOfferings = []; // missing / not this teacher's class -> reject
        }

        $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;

        if ($title !== '' && $body !== '' && $targetOfferings) {
            if ($announcementId > 0) {
                // Only the original poster may edit their own announcement.
                if ($status === 'published') {
                    $stmt = $pdo->prepare(
                        "UPDATE announcements
                         SET title = :title, body = :body, audience = :audience, offering_id = :offering_id,
                             status = :status, is_pinned = :is_pinned, published_at = :published_at
                         WHERE announcement_id = :id AND posted_by = :posted_by"
                    );
                    $stmt->execute([
                        ':offering_id'  => $targetOfferings[0],
                        ':title'        => $title,
                        ':body'         => $body,
                        ':audience'     => $audience,
                        ':status'       => $status,
                        ':is_pinned'    => $isPinned,
                        ':published_at' => $publishedAt,
                        ':id'           => $announcementId,
                        ':posted_by'    => $currentUserId,
                    ]);
                } else {
                    // Saving as a draft: leave any existing published_at untouched.
                    $stmt = $pdo->prepare(
                        "UPDATE announcements
                         SET title = :title, body = :body, audience = :audience, offering_id = :offering_id,
                             status = :status, is_pinned = :is_pinned
                         WHERE announcement_id = :id AND posted_by = :posted_by"
                    );
                    $stmt->execute([
                        ':offering_id' => $targetOfferings[0],
                        ':title'     => $title,
                        ':body'      => $body,
                        ':audience'  => $audience,
                        ':status'    => $status,
                        ':is_pinned' => $isPinned,
                        ':id'        => $announcementId,
                        ':posted_by' => $currentUserId,
                    ]);
                }
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO announcements (posted_by, title, body, audience, offering_id, status, is_pinned, published_at)
                     VALUES (:posted_by, :title, :body, :audience, :offering_id, :status, :is_pinned, :published_at)"
                );
                foreach ($targetOfferings as $oid) {
                    $stmt->execute([
                        ':posted_by'    => $currentUserId,
                        ':title'        => $title,
                        ':body'         => $body,
                        ':audience'     => $audience,
                        ':offering_id'  => $oid,
                        ':status'       => $status,
                        ':is_pinned'    => $isPinned,
                        ':published_at' => $publishedAt,
                    ]);
                }
            }
        }
    }

    // ---- Quick actions (own announcements only) ----
    if (isset($_POST['quick_action'], $_POST['id'])) {
        $id = (int) $_POST['id'];

        if ($_POST['quick_action'] === 'toggle_pin') {
            $stmt = $pdo->prepare("UPDATE announcements SET is_pinned = 1 - is_pinned WHERE announcement_id = :id AND posted_by = :posted_by");
            $stmt->execute([':id' => $id, ':posted_by' => $currentUserId]);
        } elseif ($_POST['quick_action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id = :id AND posted_by = :posted_by");
            $stmt->execute([':id' => $id, ':posted_by' => $currentUserId]);
        } elseif ($_POST['quick_action'] === 'publish') {
            $stmt = $pdo->prepare("UPDATE announcements SET status = 'published', published_at = :published_at WHERE announcement_id = :id AND posted_by = :posted_by");
            $stmt->execute([':published_at' => date('Y-m-d H:i:s'), ':id' => $id, ':posted_by' => $currentUserId]);
        }
    }

    // Redirect so refreshing the page doesn't resubmit the form.
    header("Location: announcements.php");
    exit();
}

/* ---------------------------------------------------------
   Stats (this teacher's own announcements)
--------------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total,
           COALESCE(SUM(status = 'published'), 0) AS published,
           COALESCE(SUM(status = 'draft'), 0) AS drafts,
           COALESCE(SUM(status = 'published' AND is_pinned = 1), 0) AS pinned
    FROM announcements
    WHERE posted_by = ?
");
$stmt->execute([$currentUserId]);
$stats     = $stmt->fetch(PDO::FETCH_ASSOC);
$total     = (int) $stats['total'];
$published = (int) $stats['published'];
$drafts    = (int) $stats['drafts'];
$pinned    = (int) $stats['pinned'];

/* ---------------------------------------------------------
   My announcements
--------------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT a.*, sub.subject_name, sec.grade_level, sec.section_name
    FROM announcements a
    LEFT JOIN classofferings co ON co.offering_id = a.offering_id
    LEFT JOIN subjects sub ON sub.subject_id = co.subject_id
    LEFT JOIN sections sec ON sec.section_id = co.section_id
    WHERE a.posted_by = :uid
    ORDER BY a.is_pinned DESC, COALESCE(a.published_at, a.created_at) DESC
");
$stmt->execute([':uid' => $currentUserId]);
$myAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------------------------------------
   Announcements from administration (published, for teachers).
   Posts tied to a class offering only show if it's one of this
   teacher's classes.
--------------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT a.*
    FROM announcements a
    JOIN users u ON u.id = a.posted_by
    WHERE a.status = 'published'
      AND u.role = 'admin'
      AND a.audience IN ('all', 'teachers')
      AND (a.offering_id IS NULL
           OR a.offering_id IN (SELECT offering_id FROM classofferings WHERE teacher_id = :tid))
    ORDER BY a.is_pinned DESC, COALESCE(a.published_at, a.created_at) DESC
");
$stmt->execute([':tid' => $teacherId]);
$adminAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);

function timeAgo(string $timestamp): string {
    $diff = time() - strtotime($timestamp);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr' . (floor($diff / 3600) > 1 ? 's' : '') . ' ago';
    return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
}

$audienceLabel = ['all' => 'All School', 'teachers' => 'Teachers', 'students' => 'Students'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements - SUA IntelliLearn</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="assets/css/announcements.css">
</head>
<body>

<?php include '../../includes/teachers_sidebar.php'; ?>

<div class="main-content">
    <?php include '../../includes/teacher_header.php'; ?>

    <div class="ann-page">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>Announcements</h1>
                <p>Post updates to your students and read notices from administration.</p>
            </div>
            <button class="btn-primary" type="button" onclick="openNewAnnouncement()">
                <i class="fas fa-plus"></i> New Announcement
            </button>
        </div>

        <!-- Quick Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-bullhorn"></i></div>
                <div>
                    <div class="stat-value"><?= $total ?></div>
                    <div class="stat-label">My Announcements</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-value"><?= $published ?></div>
                    <div class="stat-label">Published</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon gold"><i class="fas fa-file-alt"></i></div>
                <div>
                    <div class="stat-value"><?= $drafts ?></div>
                    <div class="stat-label">Drafts</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-thumbtack"></i></div>
                <div>
                    <div class="stat-value"><?= $pinned ?></div>
                    <div class="stat-label">Pinned</div>
                </div>
            </div>
        </div>

        <!-- Compose Panel (hidden by default) -->
        <form class="compose-panel" id="composePanel" style="display:none;" method="POST" action="announcements.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="announcement_id" id="announcementIdInput" value="">

            <div class="compose-panel-header">
                <h2 id="composeTitle"><i class="fas fa-pen-to-square"></i>&nbsp; Create Announcement</h2>
                <div class="close-icon" onclick="closeCompose()"><i class="fas fa-times"></i></div>
            </div>

            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" id="titleInput" class="form-control" placeholder="e.g. Quiz moved to Friday" required>
            </div>

            <div class="form-group">
                <label>Class</label>
                <?php if (empty($myOfferings)): ?>
                    <div class="audience-fixed"><i class="fas fa-triangle-exclamation"></i> You have no active classes this school year, so there is nobody to post to yet.</div>
                <?php else: ?>
                    <select name="offering_id" id="offeringInput" class="form-control" required>
                        <option value="">Select the class that should see this...</option>
                        <option value="all" id="offeringAllOption">All my classes</option>
                        <?php foreach ($myOfferings as $o): ?>
                            <option value="<?= (int) $o['offering_id'] ?>">
                                <?= htmlspecialchars($o['subject_name'] . ' — Grade ' . $o['grade_level'] . ' ' . $o['section_name'] . ' (' . $o['quarter'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:#6b7280;">Only students enrolled in the selected class will see it.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Message</label>
                <textarea class="form-control" name="body" id="bodyInput" placeholder="Write the announcement details here..." required></textarea>
            </div>

            <div class="compose-actions">
                <div class="left-actions">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" name="is_pinned" id="pinInput" value="1">
                        <i class="fas fa-thumbtack"></i> Pin to top of announcements
                    </label>
                </div>
                <div class="right-actions">
                    <button type="button" class="btn-secondary" onclick="closeCompose()">Cancel</button>
                    <button type="submit" name="form_action" value="draft" class="btn-secondary">Save as Draft</button>
                    <button type="submit" name="form_action" value="publish" class="btn-gold" id="publishBtn"><i class="fas fa-paper-plane"></i> Publish</button>
                </div>
            </div>
        </form>

        <!-- Tabs -->
        <div class="ann-tabs" id="annTabs">
            <button type="button" class="ann-tab active" data-target="mineList">
                My Announcements <span class="count"><?= count($myAnnouncements) ?></span>
            </button>
            <button type="button" class="ann-tab" data-target="adminList">
                From Administration <span class="count"><?= count($adminAnnouncements) ?></span>
            </button>
        </div>

        <!-- My Announcements -->
        <div class="announcements-list" id="mineList">
            <?php if (empty($myAnnouncements)): ?>
                <p class="empty-note">You haven't posted any announcements yet.</p>
            <?php endif; ?>

            <?php foreach ($myAnnouncements as $a): ?>
                <div class="announcement-card <?= $a['is_pinned'] ? 'pinned' : '' ?>">
                    <div class="announcement-body">
                        <div class="announcement-top-row">
                            <div class="announcement-title-line">
                                <?php if ($a['is_pinned']): ?><i class="fas fa-thumbtack pin-icon"></i><?php endif; ?>
                                <h3><?= htmlspecialchars($a['title']) ?></h3>
                                <?php if ($a['status'] === 'draft'): ?>
                                    <span class="badge draft">Draft</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="announcement-excerpt"><?= nl2br(htmlspecialchars($a['body'])) ?></p>
                        <div class="announcement-meta">
                            <span><i class="far fa-clock"></i> Posted <?= timeAgo($a['published_at'] ?? $a['created_at']) ?></span>
                            <div class="audience-tags">
                                <?php if ($a['subject_name']): ?>
                                    <span class="tag"><i class="fas fa-chalkboard"></i> <?= htmlspecialchars($a['subject_name'] . ' · Gr ' . $a['grade_level'] . ' ' . $a['section_name']) ?></span>
                                <?php else: ?>
                                    <span class="tag">No class (visible to all students)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-actions">
                        <a class="icon-btn" href="#" title="Edit"
                           onclick="openEditAnnouncement(<?= htmlspecialchars(json_encode([
                                'id'     => (int) $a['announcement_id'],
                                'title'  => $a['title'],
                                'body'   => $a['body'],
                                'pinned' => (bool) $a['is_pinned'],
                                'offering' => (int) $a['offering_id'],
                           ]), ENT_QUOTES, 'UTF-8') ?>); return false;">
                            <i class="fas fa-pen"></i>
                        </a>

                        <?php if ($a['status'] === 'draft'): ?>
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="quick_action" value="publish">
                            <input type="hidden" name="id" value="<?= (int) $a['announcement_id'] ?>">
                            <button type="submit" class="icon-btn" title="Publish draft"><i class="fas fa-paper-plane"></i></button>
                        </form>
                        <?php endif; ?>

                        <form method="POST" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="quick_action" value="toggle_pin">
                            <input type="hidden" name="id" value="<?= (int) $a['announcement_id'] ?>">
                            <button type="submit" class="icon-btn" title="<?= $a['is_pinned'] ? 'Unpin' : 'Pin' ?>">
                                <i class="<?= $a['is_pinned'] ? 'fas' : 'far' ?> fa-thumbtack"></i>
                            </button>
                        </form>

                        <form method="POST" class="inline-form" onsubmit="return confirm('Delete this announcement?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="quick_action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $a['announcement_id'] ?>">
                            <button type="submit" class="icon-btn delete" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- From Administration (read-only) -->
        <div class="announcements-list" id="adminList" style="display:none;">
            <?php if (empty($adminAnnouncements)): ?>
                <p class="empty-note">No announcements from administration right now.</p>
            <?php endif; ?>

            <?php foreach ($adminAnnouncements as $a): ?>
                <div class="announcement-card <?= $a['is_pinned'] ? 'pinned' : '' ?>">
                    <div class="announcement-body">
                        <div class="announcement-top-row">
                            <div class="announcement-title-line">
                                <?php if ($a['is_pinned']): ?><i class="fas fa-thumbtack pin-icon"></i><?php endif; ?>
                                <h3><?= htmlspecialchars($a['title']) ?></h3>
                                <?php if (in_array($a['priority'], ['urgent', 'important'], true)): ?>
                                    <span class="badge <?= $a['priority'] ?>"><?= ucfirst($a['priority']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="announcement-excerpt"><?= nl2br(htmlspecialchars($a['body'])) ?></p>
                        <div class="announcement-meta">
                            <span><i class="fas fa-user-shield"></i> School Administration</span>
                            <span><i class="far fa-clock"></i> Posted <?= timeAgo($a['published_at'] ?? $a['created_at']) ?></span>
                            <div class="audience-tags">
                                <span class="tag"><?= $audienceLabel[$a['audience']] ?? 'All School' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<script>
    // ---- Sidebar helpers (same as the other teacher pages) ----
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    }

    function setActive(el) {
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        el.classList.add('active');
    }

    // ---- Tabs ----
    let activeListId = 'mineList';

    document.querySelectorAll('.ann-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.ann-tab').forEach(t => t.classList.toggle('active', t === tab));
            activeListId = tab.dataset.target;
            ['mineList', 'adminList'].forEach(id => {
                document.getElementById(id).style.display = (id === activeListId) ? 'flex' : 'none';
            });
            closeCompose();
        });
    });

    // ---- Compose panel ----
    function showPanel() {
        const panel = document.getElementById('composePanel');
        panel.style.display = 'block';
        document.getElementById('annTabs').style.display = 'none';
        document.getElementById('mineList').style.display = 'none';
        document.getElementById('adminList').style.display = 'none';
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function closeCompose() {
        document.getElementById('composePanel').style.display = 'none';
        document.getElementById('annTabs').style.display = 'flex';
        document.getElementById('mineList').style.display  = (activeListId === 'mineList')  ? 'flex' : 'none';
        document.getElementById('adminList').style.display = (activeListId === 'adminList') ? 'flex' : 'none';
    }

    function resetComposeForm() {
        document.getElementById('announcementIdInput').value = '';
        document.getElementById('titleInput').value = '';
        document.getElementById('bodyInput').value = '';
        document.getElementById('pinInput').checked = false;
        const offSel = document.getElementById('offeringInput');
        if (offSel) {
            offSel.value = '';
            const allOpt = document.getElementById('offeringAllOption'); allOpt.hidden = false; allOpt.disabled = false;
        }
        document.getElementById('composeTitle').innerHTML = '<i class="fas fa-pen-to-square"></i>&nbsp; Create Announcement';
        document.getElementById('publishBtn').innerHTML = '<i class="fas fa-paper-plane"></i> Publish';
    }

    function openNewAnnouncement() {
        resetComposeForm();
        activeListId = 'mineList';
        showPanel();
    }

    function openEditAnnouncement(data) {
        document.getElementById('announcementIdInput').value = data.id;
        document.getElementById('titleInput').value = data.title;
        document.getElementById('bodyInput').value = data.body;
        document.getElementById('pinInput').checked = !!data.pinned;
        const offSel = document.getElementById('offeringInput');
        if (offSel) {
            offSel.value = data.offering ? String(data.offering) : '';
            // 'All my classes' only makes sense when creating, not editing one post.
            const allOpt = document.getElementById('offeringAllOption'); allOpt.hidden = true; allOpt.disabled = true;
        }
        document.getElementById('composeTitle').innerHTML = '<i class="fas fa-pen-to-square"></i>&nbsp; Edit Announcement';
        document.getElementById('publishBtn').innerHTML = '<i class="fas fa-paper-plane"></i> Publish Update';
        activeListId = 'mineList';
        showPanel();
    }
</script>

</body>
</html>
