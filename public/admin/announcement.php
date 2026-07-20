<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    // TODO: adjust this path to match where your login.php actually lives
    header("Location: ../login.php");
    exit();
}

// Only admins can post from this page.
// TODO: confirm $_SESSION['role'] is the key your login script actually sets.
if (($_SESSION['role'] ?? null) !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// TODO: adjust this path to match your actual DB connection include.
// This file is expected to expose a PDO instance in $pdo.
require_once '../../config/config.php';

$currentUserId = $_SESSION['user_id'];

/* ---------------------------------------------------------
   Handle new announcement submission (Publish / Save Draft)
--------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $title    = trim($_POST['title'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $audience = $_POST['audience'] ?? 'all';
    $priority = $_POST['priority'] ?? 'normal';
    $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
    $status   = ($_POST['form_action'] === 'publish') ? 'published' : 'draft';

    $allowedAudience = ['all', 'teachers', 'students'];
    $allowedPriority = ['normal', 'important', 'urgent'];
    if (!in_array($audience, $allowedAudience, true)) $audience = 'all';
    if (!in_array($priority, $allowedPriority, true)) $priority = 'normal';

    if ($title !== '' && $body !== '') {
        $stmt = $pdo->prepare(
            "INSERT INTO announcements (posted_by, title, body, audience, priority, status, is_pinned)
             VALUES (:posted_by, :title, :body, :audience, :priority, :status, :is_pinned)"
        );
        $stmt->execute([
            ':posted_by' => $currentUserId,
            ':title'     => $title,
            ':body'      => $body,
            ':audience'  => $audience,
            ':priority'  => $priority,
            ':status'    => $status,
            ':is_pinned' => $isPinned,
        ]);
    }

    // Redirect so refreshing the page doesn't resubmit the form.
    header("Location: announcement.php");
    exit();
}

/* ---------------------------------------------------------
   Handle quick actions: pin/unpin, delete
--------------------------------------------------------- */
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int) $_GET['id'];

    if ($_GET['action'] === 'toggle_pin') {
        $stmt = $pdo->prepare("UPDATE announcements SET is_pinned = 1 - is_pinned WHERE announcement_id = :id");
        $stmt->execute([':id' => $id]);
    } elseif ($_GET['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id = :id");
        $stmt->execute([':id' => $id]);
    }

    header("Location: announcement.php");
    exit();
}

/* ---------------------------------------------------------
   Stats
--------------------------------------------------------- */
$total     = (int) $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
$published = (int) $pdo->query("SELECT COUNT(*) FROM announcements WHERE status = 'published'")->fetchColumn();
$drafts    = (int) $pdo->query("SELECT COUNT(*) FROM announcements WHERE status = 'draft'")->fetchColumn();
$urgent    = (int) $pdo->query("SELECT COUNT(*) FROM announcements WHERE status = 'published' AND priority = 'urgent'")->fetchColumn();

/* ---------------------------------------------------------
   List announcements (poster name: teacher name if available,
   otherwise falls back to the account username)
--------------------------------------------------------- */
$sql = "
    SELECT a.*, u.username AS poster_username,
           t.firstname AS t_first, t.lastname AS t_last
    FROM announcements a
    JOIN users u ON u.id = a.posted_by
    LEFT JOIN teachers t ON t.user_id = u.id
    ORDER BY a.is_pinned DESC, a.created_at DESC
";
$announcements = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

function posterName(array $row): string {
    if (!empty($row['t_first'])) {
        return htmlspecialchars($row['t_first'] . ' ' . $row['t_last']);
    }
    return htmlspecialchars($row['poster_username']);
}

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
            <p>Post updates and notices visible to students and teachers.</p>
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
                <div class="stat-value"><?= $total ?></div>
                <div class="stat-label">Total Announcements</div>
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
            <div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div>
            <div>
                <div class="stat-value"><?= $urgent ?></div>
                <div class="stat-label">Urgent Active</div>
            </div>
        </div>
    </div>

    <!-- Compose Panel (hidden by default) -->
    <form class="compose-panel" id="composePanel" style="display:none;" method="POST" action="announcement.php">
        <div class="compose-panel-header">
            <h2><i class="fas fa-pen-to-square"></i>&nbsp; Create Announcement</h2>
            <div class="close-icon" onclick="toggleCompose()"><i class="fas fa-times"></i></div>
        </div>

        <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Class Suspension Notice for June 12" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Audience</label>
                <div class="audience-options">
                    <label class="audience-chip checked">
                        <input type="radio" name="audience" value="all" checked> All School
                    </label>
                    <label class="audience-chip">
                        <input type="radio" name="audience" value="students"> Students
                    </label>
                    <label class="audience-chip">
                        <input type="radio" name="audience" value="teachers"> Teachers
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label>Priority</label>
                <input type="hidden" name="priority" id="priorityInput" value="normal">
                <div class="priority-options">
                    <div class="priority-pill active" data-level="normal" onclick="setPriority(this)">Normal</div>
                    <div class="priority-pill" data-level="important" onclick="setPriority(this)">Important</div>
                    <div class="priority-pill" data-level="urgent" onclick="setPriority(this)">Urgent</div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Message</label>
            <textarea class="form-control" name="body" placeholder="Write the announcement details here..." required></textarea>
        </div>

        <div class="compose-actions">
            <div class="left-actions">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" name="is_pinned" value="1">
                    <i class="fas fa-thumbtack"></i> Pin to top of announcements
                </label>
            </div>
            <div class="right-actions">
                <button type="button" class="btn-secondary" onclick="toggleCompose()">Cancel</button>
                <button type="submit" name="form_action" value="draft" class="btn-secondary">Save as Draft</button>
                <button type="submit" name="form_action" value="publish" class="btn-gold"><i class="fas fa-paper-plane"></i> Publish</button>
            </div>
        </div>
    </form>

    <!-- Announcements List -->
    <div class="announcements-list">
        <?php if (empty($announcements)): ?>
            <p style="color:var(--text-muted);">No announcements yet. Create one above.</p>
        <?php endif; ?>

        <?php foreach ($announcements as $a): ?>
            <div class="announcement-card <?= $a['is_pinned'] ? 'pinned' : '' ?>">
                <div class="priority-strip <?= htmlspecialchars($a['priority']) ?>"></div>
                <div class="announcement-body">
                    <div class="announcement-top-row">
                        <div class="announcement-title-line">
                            <?php if ($a['is_pinned']): ?><i class="fas fa-thumbtack pin-icon"></i><?php endif; ?>
                            <h3><?= htmlspecialchars($a['title']) ?></h3>
                            <span class="badge <?= $a['status'] === 'draft' ? 'draft' : htmlspecialchars($a['priority']) ?>">
                                <?= $a['status'] === 'draft' ? 'Draft' : ucfirst($a['priority']) ?>
                            </span>
                        </div>
                    </div>
                    <p class="announcement-excerpt"><?= nl2br(htmlspecialchars($a['body'])) ?></p>
                    <div class="announcement-meta">
                        <span><i class="fas fa-user"></i> <?= posterName($a) ?></span>
                        <span><i class="far fa-clock"></i> Posted <?= timeAgo($a['created_at']) ?></span>
                        <div class="audience-tags">
                            <span class="tag"><?= $audienceLabel[$a['audience']] ?></span>
                        </div>
                    </div>
                </div>
                <div class="card-actions">
                    <a class="icon-btn" href="?action=toggle_pin&id=<?= $a['announcement_id'] ?>" title="<?= $a['is_pinned'] ? 'Unpin' : 'Pin' ?>">
                        <i class="<?= $a['is_pinned'] ? 'fas' : 'far' ?> fa-thumbtack"></i>
                    </a>
                    <a class="icon-btn delete" href="?action=delete&id=<?= $a['announcement_id'] ?>" title="Delete"
                       onclick="return confirm('Delete this announcement?');">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<script>
    function toggleCompose() {
        const panel = document.getElementById('composePanel');
        panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
        if (panel.style.display === 'block') {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function setPriority(el) {
        document.querySelectorAll('.priority-pill').forEach(p => p.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('priorityInput').value = el.dataset.level;
    }

    document.querySelectorAll('.audience-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.audience-chip').forEach(c => c.classList.remove('checked'));
            chip.classList.add('checked');
        });
    });
</script>

</body>
</html>