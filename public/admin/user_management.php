<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

/**
 * User Management — Admin Portal
 * Restructured to match the announcement module's architecture.
 */

$people = [
    ['name' => 'Juan Dela Cruz',  'email' => 'juan@gmail.com',   'role' => 'student', 'status' => 'active',  'last' => 'Today',      'color' => '#2F9C74'],
    ['name' => 'Ana Reyes',       'email' => 'reyes@gmail.com',  'role' => 'teacher', 'status' => 'active',  'last' => 'Yesterday',  'color' => '#1F6F54'],
    ['name' => 'Mark Santos',     'email' => 'msantos@gmail.com', 'role' => 'teacher', 'status' => 'active',  'last' => '2 days ago', 'color' => '#1F6F54'],
    ['name' => 'Liza Fernandez',  'email' => 'lizaf@gmail.com',  'role' => 'student', 'status' => 'pending', 'last' => '—',          'color' => '#C9A227'],
    ['name' => 'Carlo Aquino',    'email' => 'carlo.a@gmail.com', 'role' => 'student', 'status' => 'active',  'last' => '5 days ago', 'color' => '#2F9C74'],
];

function um_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= strtoupper(substr($p, 0, 1));
    }
    return $out ?: '?';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management | SUA IntelliLearn Admin</title>

<!-- Fonts & Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- User Management Stylesheet -->
<link rel="stylesheet" href="assests/css/user_management.css">
</head>
<body>

<?php include '../../includes/admin_sidebar.php';  ?>

<div class="main-content" id="mainContent">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1>User Management</h1>
            <p>Manage teacher and student accounts across St. Uriel Academy.</p>
        </div>
        <button class="btn-primary" id="newUserBtn" onclick="toggleAddUser()">
            <i class="fas fa-user-plus"></i> Add User
        </button>
    </div>

    <!-- Quick Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            <div>
                <div class="stat-value">248</div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-user-graduate"></i></div>
            <div>
                <div class="stat-value">201</div>
                <div class="stat-label">Students</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gold"><i class="fas fa-chalkboard-teacher"></i></div>
            <div>
                <div class="stat-value">39</div>
                <div class="stat-label">Teachers</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-hourglass-half"></i></div>
            <div>
                <div class="stat-value">8</div>
                <div class="stat-label">Pending Invites</div>
            </div>
        </div>
    </div>

    <!-- Add User Panel (hidden by default) -->
    <div class="compose-panel" id="addUserPanel" style="display:none;">
        <div class="compose-panel-header">
            <h2><i class="fas fa-user-plus"></i>&nbsp; Add New User</h2>
            <div class="close-icon" onclick="toggleAddUser()"><i class="fas fa-times"></i></div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" class="form-control" placeholder="e.g. Maria Clara Santos">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" class="form-control" placeholder="e.g. maria@sturiel.edu.ph">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Role</label>
                <div class="role-options">
                    <div class="role-pill active" data-role="student" onclick="setRole(this)">
                        <i class="fas fa-user-graduate"></i> Student
                    </div>
                    <div class="role-pill" data-role="teacher" onclick="setRole(this)">
                        <i class="fas fa-chalkboard-teacher"></i> Teacher
                    </div>
                    <div class="role-pill" data-role="staff" onclick="setRole(this)">
                        <i class="fas fa-briefcase"></i> Staff
                    </div>
                    <div class="role-pill" data-role="parent" onclick="setRole(this)">
                        <i class="fas fa-user-friends"></i> Parent
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Status</label>
                <div class="status-options">
                    <div class="status-pill active" data-status="active" onclick="setStatus(this)">Active</div>
                    <div class="status-pill" data-status="pending" onclick="setStatus(this)">Pending</div>
                    <div class="status-pill" data-status="inactive" onclick="setStatus(this)">Inactive</div>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Department / Grade Level</label>
                <input type="text" class="form-control" placeholder="e.g. Grade 7 or Mathematics Dept">
            </div>
            <div class="form-group">
                <label>Contact Number</label>
                <input type="tel" class="form-control" placeholder="e.g. +63 912 345 6789">
            </div>
        </div>

        <div class="form-group">
            <label>Notes (optional)</label>
            <textarea class="form-control" rows="3" placeholder="Additional information about this user..."></textarea>
        </div>

        <div class="compose-actions">
            <div class="left-actions">
                <i class="fas fa-envelope"></i> Send welcome email
            </div>
            <div class="right-actions">
                <button class="btn-secondary" onclick="toggleAddUser()">Cancel</button>
                <button class="btn-gold"><i class="fas fa-save"></i> Save User</button>
            </div>
        </div>
    </div>

    <!-- Filter / Search Toolbar -->
    <div class="toolbar-row">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search users by name or email...">
        </div>
        <select class="select-filter">
            <option>All Roles</option>
            <option>Students</option>
            <option>Teachers</option>
            <option>Staff</option>
            <option>Parents</option>
        </select>
        <select class="select-filter">
            <option>All Status</option>
            <option>Active</option>
            <option>Pending</option>
            <option>Inactive</option>
        </select>
        <select class="select-filter">
            <option>Newest First</option>
            <option>Oldest First</option>
            <option>Name A–Z</option>
            <option>Last Active</option>
        </select>
    </div>

    <!-- Users List (card-based, matching announcement cards) -->
    <div class="users-list">

        <?php foreach ($people as $p): ?>
        <div class="user-card <?= $p['status'] === 'pending' ? 'pending' : '' ?>">
            <div class="role-strip <?= $p['role'] === 'teacher' ? 'teacher' : 'student' ?>"></div>
            <div class="user-body">
                <div class="user-top-row">
                    <div class="user-title-line">
                        <div class="user-avatar" style="background: <?= htmlspecialchars($p['color']) ?>;">
                            <?= htmlspecialchars(um_initials($p['name'])) ?>
                        </div>
                        <div class="user-title-block">
                            <h3><?= htmlspecialchars($p['name']) ?></h3>
                            <span class="badge <?= $p['role'] === 'teacher' ? 'teacher' : 'student' ?>">
                                <?= $p['role'] === 'teacher' ? 'Teacher' : 'Student' ?>
                            </span>
                            <span class="badge <?= $p['status'] === 'active' ? 'active' : 'pending' ?>">
                                <?= $p['status'] === 'active' ? 'Active' : 'Pending' ?>
                            </span>
                        </div>
                    </div>
                </div>
                <p class="user-excerpt">
                    <i class="fas fa-envelope"></i> <?= htmlspecialchars($p['email']) ?>
                </p>
                <div class="user-meta">
                    <span><i class="fas fa-clock"></i> Last active <?= htmlspecialchars($p['last']) ?></span>
                    <span><i class="fas fa-id-badge"></i> ID: SUA-<?= rand(10000, 99999) ?></span>
                </div>
            </div>
            <div class="card-actions">
                <div class="icon-btn" title="View Profile"><i class="fas fa-eye"></i></div>
                <div class="icon-btn" title="Edit"><i class="fas fa-pen"></i></div>
                <div class="icon-btn delete" title="Deactivate"><i class="fas fa-ban"></i></div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Extra sample cards to fill the list -->
        <div class="user-card">
            <div class="role-strip staff"></div>
            <div class="user-body">
                <div class="user-top-row">
                    <div class="user-title-line">
                        <div class="user-avatar" style="background: #8B5CF6;">RP</div>
                        <div class="user-title-block">
                            <h3>Roberto Perez</h3>
                            <span class="badge staff">Staff</span>
                            <span class="badge active">Active</span>
                        </div>
                    </div>
                </div>
                <p class="user-excerpt">
                    <i class="fas fa-envelope"></i> roberto.p@sturiel.edu.ph
                </p>
                <div class="user-meta">
                    <span><i class="fas fa-clock"></i> Last active 1 hour ago</span>
                    <span><i class="fas fa-id-badge"></i> ID: SUA-48291</span>
                </div>
            </div>
            <div class="card-actions">
                <div class="icon-btn" title="View Profile"><i class="fas fa-eye"></i></div>
                <div class="icon-btn" title="Edit"><i class="fas fa-pen"></i></div>
                <div class="icon-btn delete" title="Deactivate"><i class="fas fa-ban"></i></div>
            </div>
        </div>

        <div class="user-card pending">
            <div class="role-strip parent"></div>
            <div class="user-body">
                <div class="user-top-row">
                    <div class="user-title-line">
                        <div class="user-avatar" style="background: #F59E0B;">EL</div>
                        <div class="user-title-block">
                            <h3>Elena Lopez</h3>
                            <span class="badge parent">Parent</span>
                            <span class="badge pending">Pending</span>
                        </div>
                    </div>
                </div>
                <p class="user-excerpt">
                    <i class="fas fa-envelope"></i> elena.lopez@email.com
                </p>
                <div class="user-meta">
                    <span><i class="fas fa-clock"></i> Invite sent 3 days ago</span>
                    <span><i class="fas fa-id-badge"></i> ID: SUA- pending</span>
                </div>
            </div>
            <div class="card-actions">
                <div class="icon-btn" title="View Profile"><i class="fas fa-eye"></i></div>
                <div class="icon-btn" title="Edit"><i class="fas fa-pen"></i></div>
                <div class="icon-btn delete" title="Deactivate"><i class="fas fa-ban"></i></div>
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

    // ---- Add User panel show/hide (UI only, no data handling) ----
    function toggleAddUser() {
        const panel = document.getElementById('addUserPanel');
        panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
        if (panel.style.display === 'block') {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // ---- Role pill selector (visual state only) ----
    function setRole(el) {
        document.querySelectorAll('.role-pill').forEach(p => p.classList.remove('active'));
        el.classList.add('active');
    }

    // ---- Status pill selector (visual state only) ----
    function setStatus(el) {
        document.querySelectorAll('.status-pill').forEach(p => p.classList.remove('active'));
        el.classList.add('active');
    }
</script>

</body>
</html>