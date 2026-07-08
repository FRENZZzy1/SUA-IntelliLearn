<?php

/**
 * User Management — Admin Portal
 * -------------------------------------------------------------
 * This file assumes the surrounding page (or this file itself)
 * already: started the session, and will include admin_sidebar.php
 * as a SIBLING of the .um-page wrapper below (not inside it), e.g.:
 *
 *   <?php include '../../includes/admin_sidebar.php'; ?>
 *   <div class="um-page"> ... this markup ... </div>
 *
 * That sibling structure is what lets the existing
 * ".sidebar.collapsed ~ .main-content" style (and the matching
 * rule in user_management.css) push this content over correctly.
 *
 * Static sample data only — no DB/session logic, per request.
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

    <!-- Announcements Stylesheet -->
    <link rel="stylesheet" href="assests/css/user_management.css">
</head>

<body>

    <?php include '../../includes/admin_sidebar.php';  ?>

    <div class="um-page">

        <!-- Topbar -->
        <div class="um-topbar">
            <div class="um-breadcrumb">Admin &nbsp;/&nbsp; <span>User Management</span></div>
            <div class="um-topbar-actions">
                <div class="um-search">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search users…">
                </div>
                <div class="um-bell"><i class="fas fa-bell"></i></div>
            </div>
        </div>

        <!-- Page header -->
        <div class="um-header">
            <div>
                <h1>User Management</h1>
                <p>Manage teacher and student accounts across St. Uriel Academy.</p>
            </div>
            <button class="um-add-btn"><i class="fas fa-user-plus"></i> Add User</button>
        </div>

        <!-- Stat cards -->
        <div class="um-stats">
            <div class="um-stat">
                <div class="um-stat-icon"><i class="fas fa-users"></i></div>
                <div>
                    <div class="um-stat-value">248</div>
                    <div class="um-stat-label">Total Users</div>
                </div>
            </div>
            <div class="um-stat">
                <div class="um-stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div>
                    <div class="um-stat-value">201</div>
                    <div class="um-stat-label">Students</div>
                </div>
            </div>
            <div class="um-stat">
                <div class="um-stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <div>
                    <div class="um-stat-value">39</div>
                    <div class="um-stat-label">Teachers</div>
                </div>
            </div>
            <div class="um-stat">
                <div class="um-stat-icon"><i class="fas fa-hourglass-half"></i></div>
                <div>
                    <div class="um-stat-value">8</div>
                    <div class="um-stat-label">Pending Invites</div>
                </div>
            </div>
        </div>

        <!-- Panel -->
        <div class="um-panel">
            <div class="um-panel-toolbar">
                <div class="um-tabs">
                    <button class="um-tab active">All</button>
                    <button class="um-tab">Teachers</button>
                    <button class="um-tab">Students</button>
                </div>
                <div class="um-panel-toolbar-right">
                    <button class="um-filter-btn"><i class="fas fa-sliders-h"></i> Filter</button>
                    <button class="um-filter-btn"><i class="fas fa-arrow-down-wide-short"></i> Sort</button>
                </div>
            </div>

            <table class="um-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($people as $p): ?>
                        <tr>
                            <td>
                                <div class="um-person">
                                    <div class="um-avatar-wrap <?= $p['status'] === 'active' ? 'is-active' : '' ?>">
                                        <div class="um-avatar" style="background: <?= htmlspecialchars($p['color']) ?>;">
                                            <?= htmlspecialchars(um_initials($p['name'])) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="um-person-name"><?= htmlspecialchars($p['name']) ?></div>
                                        <div class="um-person-email"><?= htmlspecialchars($p['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="um-chip role-<?= $p['role'] ?>">
                                    <?= $p['role'] === 'teacher' ? 'Teacher' : 'Student' ?>
                                </span>
                            </td>
                            <td>
                                <span class="um-chip status-<?= $p['status'] ?>">
                                    <?= $p['status'] === 'active' ? 'Active' : 'Pending' ?>
                                </span>
                            </td>
                            <td class="um-last-active"><?= htmlspecialchars($p['last']) ?></td>
                            <td>
                                <div class="um-actions">
                                    <button class="um-icon-btn" title="View"><i class="fas fa-eye"></i></button>
                                    <button class="um-icon-btn" title="Edit"><i class="fas fa-pen"></i></button>
                                    <button class="um-icon-btn danger" title="Deactivate"><i class="fas fa-ban"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="um-panel-footer">
                <span>Showing 1–5 of 248</span>
                <div class="um-pagination">
                    <button class="active">1</button>
                    <button>2</button>
                    <button>3</button>
                    <button><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
        </div>

    </div>

</body>

</html>