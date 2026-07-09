<?php
require_once '../../config/config.php';
requireAdmin();

$flash = getFlashMessage();

// ============================================================
// HANDLE ALL FORM SUBMISSIONS (POST requests)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $action = $_POST['action'] ?? '';

    // -------------------- CREATE USER --------------------
    if ($action === 'create') {
        $role = $_POST['role'] ?? 'student';
        $status = $_POST['status'] ?? 'active';

        // ============================================================
        // STUDENT CREATION — separate flow (auto username/password)
        // ============================================================
        if ($role === 'student') {
            $firstname        = trim($_POST['firstname'] ?? '');
            $lastname         = trim($_POST['lastname'] ?? '');
            $middlename       = trim($_POST['middlename'] ?? '');
            $lrn              = trim($_POST['lrn'] ?? '');
            $email            = trim($_POST['email'] ?? '');
            $birthdate        = trim($_POST['birthdate'] ?? '');
            $address          = trim($_POST['address'] ?? '');
            $guardian_name    = trim($_POST['guardian_name'] ?? '');
            $guardian_contact = trim($_POST['guardian_contact'] ?? '');

            $errors = [];
            if (empty($firstname)) $errors[] = "First name is required.";
            if (empty($lastname)) $errors[] = "Last name is required.";
            if (empty($lrn) || !preg_match('/^\d{12}$/', $lrn)) $errors[] = "A valid 12-digit LRN is required.";
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email address is invalid.";
            $bday_obj = DateTime::createFromFormat('Y-m-d', $birthdate);
            if (empty($birthdate) || !$bday_obj) $errors[] = "A valid birthdate is required.";
            if (!in_array($status, ['active', 'inactive', 'suspended'])) $errors[] = "Invalid status selected.";

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();

                    // LRN must be unique
                    $check = $pdo->prepare("SELECT student_id FROM Students WHERE student_lrn = ?");
                    $check->execute([$lrn]);
                    if ($check->fetch()) {
                        throw new Exception("A student with this LRN already exists.");
                    }

                    // Build username: STU-(last 4 digits of LRN)-(birthdate as MMDDYY)
                    $last4 = substr($lrn, -4);
                    $bday_code = $bday_obj->format('mdy'); // e.g. 091105

                    $username_base = "STU-{$last4}-{$bday_code}";
                    $username = $username_base;
                    $counter = 1;
                    while (true) {
                        $check = $pdo->prepare("SELECT id FROM Users WHERE username = ?");
                        $check->execute([$username]);
                        if (!$check->fetch()) break;
                        $username = $username_base . '-' . $counter;
                        $counter++;
                    }

                    // Build password: Lastname + birthdate as MMDDYY (e.g. Paller091105)
                    $password_plain = $lastname . $bday_code;
                    $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

                    // Insert into Users
                    $stmt = $pdo->prepare("
                        INSERT INTO Users (username, password, role, status, created_at, updated_at)
                        VALUES (?, ?, 'student', ?, NOW(), NOW())
                    ");
                    $stmt->execute([$username, $password_hash, $status]);
                    $user_id = $pdo->lastInsertId();

                    // Insert into Students
                    $stmt = $pdo->prepare("
                        INSERT INTO Students
                            (user_id, student_lrn, firstname, lastname, middlename, email, birthdate, address, guardian_name, guardian_contact, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $user_id,
                        $lrn,
                        $firstname,
                        $lastname,
                        $middlename !== '' ? $middlename : null,
                        $email !== '' ? $email : null,
                        $birthdate,
                        $address,
                        $guardian_name,
                        $guardian_contact,
                    ]);

                    $pdo->commit();
                    setFlashMessage('success', "Student '$firstname $lastname' created. Username: $username | Password: $password_plain (please record and share this securely)");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    setFlashMessage('error', "Database error: " . $e->getMessage());
                }
            } else {
                setFlashMessage('error', implode(" ", $errors));
            }
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        // ============================================================
        // TEACHER / ADMIN CREATION — original flow
        // ============================================================
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $password = $_POST['password'] ?? '';
        $send_email = isset($_POST['send_email']) ? 1 : 0;

        // Validation
        $errors = [];
        if (empty($fullname)) $errors[] = "Full name is required.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
        if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
        if (!in_array($role, ['admin', 'teacher'])) $errors[] = "Invalid role selected.";
        if (!in_array($status, ['active', 'inactive', 'suspended'])) $errors[] = "Invalid status selected.";

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Generate username from fullname
                $username_base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $fullname)[0]));
                $username = $username_base;
                $counter = 1;
                while (true) {
                    $check = $pdo->prepare("SELECT id FROM Users WHERE username = ?");
                    $check->execute([$username]);
                    if (!$check->fetch()) break;
                    $username = $username_base . $counter;
                    $counter++;
                }

                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert into Users table
                $stmt = $pdo->prepare("
                    INSERT INTO Users (username, password, role, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$username, $password_hash, $role, $status]);
                $user_id = $pdo->lastInsertId();

                // Insert role-specific data
                if ($role === 'teacher') {
                    $parts = explode(' ', $fullname, 2);
                    $firstname = $parts[0] ?? '';
                    $lastname = $parts[1] ?? '';

                    $stmt = $pdo->prepare("
                        INSERT INTO Teachers (user_id, firstname, lastname, email, department, specialization, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$user_id, $firstname, $lastname, $email, $department, $notes]);
                }
                elseif ($role === 'admin') {
                    $stmt = $pdo->prepare("
                        INSERT INTO Admin (user_id, email, access_level, position, created_at)
                        VALUES (?, ?, 'limited', 'staff', NOW())
                    ");
                    $stmt->execute([$user_id, $email]);
                }

                $pdo->commit();
                setFlashMessage('success', "User '$fullname' created successfully with username: $username");
            } catch (PDOException $e) {
                $pdo->rollBack();
                setFlashMessage('error', "Database error: " . $e->getMessage());
            }
        } else {
            setFlashMessage('error', implode(" ", $errors));
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // -------------------- UPDATE USER --------------------
    elseif ($action === 'update') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $status = $_POST['status'] ?? '';
        $department = trim($_POST['department'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $new_password = $_POST['new_password'] ?? '';

        if ($user_id <= 0) {
            setFlashMessage('error', "Invalid user ID.");
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Update Users table
            if (!empty($new_password) && strlen($new_password) >= 6) {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE Users SET password = ?, status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$password_hash, $status, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE Users SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $user_id]);
            }

            // Update role-specific table
            if ($role === 'teacher') {
                $parts = explode(' ', $fullname, 2);
                $firstname = $parts[0] ?? '';
                $lastname = $parts[1] ?? '';

                $stmt = $pdo->prepare("
                    UPDATE Teachers 
                    SET firstname = ?, lastname = ?, email = ?, department = ?, specialization = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([$firstname, $lastname, $email, $department, $notes, $user_id]);
            } 
            elseif ($role === 'student') {
                $parts = explode(' ', $fullname, 2);
                $firstname = $parts[0] ?? '';
                $lastname = $parts[1] ?? '';

                $stmt = $pdo->prepare("
                    UPDATE Students 
                    SET firstname = ?, lastname = ?, email = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([$firstname, $lastname, $email, $user_id]);
            }
            elseif ($role === 'admin') {
                $stmt = $pdo->prepare("UPDATE Admin SET email = ? WHERE user_id = ?");
                $stmt->execute([$email, $user_id]);
            }

            $pdo->commit();
            setFlashMessage('success', "User updated successfully.");
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage('error', "Database error: " . $e->getMessage());
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // -------------------- DELETE / DEACTIVATE USER --------------------
    elseif ($action === 'delete') {
        $user_id = intval($_POST['user_id'] ?? 0);

        if ($user_id <= 0) {
            setFlashMessage('error', "Invalid user ID.");
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        // Prevent deleting self
        if ($user_id == $_SESSION['user_id']) {
            setFlashMessage('error', "You cannot delete your own account.");
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        try {
            // Soft delete by setting status to suspended
            $stmt = $pdo->prepare("UPDATE Users SET status = 'suspended', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id]);
            setFlashMessage('success', "User deactivated successfully.");
        } catch (PDOException $e) {
            setFlashMessage('error', "Database error: " . $e->getMessage());
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ============================================================
// FETCH USERS FROM DATABASE
// ============================================================
$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query dynamically
$params = [];
$where_clauses = ["u.role IN ('admin', 'teacher', 'student')"];

if (!empty($search)) {
    $where_clauses[] = "(COALESCE(t.firstname, s.firstname, a.email, '') LIKE ? OR COALESCE(t.lastname, s.lastname, '') LIKE ? OR u.username LIKE ? OR COALESCE(t.email, s.email, a.email, '') LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($role_filter !== 'all' && in_array($role_filter, ['admin', 'teacher', 'student'])) {
    $where_clauses[] = "u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== 'all' && in_array($status_filter, ['active', 'inactive', 'suspended'])) {
    $where_clauses[] = "u.status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// Count total for pagination
$count_sql = "SELECT COUNT(*) FROM Users u 
    LEFT JOIN Teachers t ON u.id = t.user_id AND u.role = 'teacher'
    LEFT JOIN Students s ON u.id = s.user_id AND u.role = 'student'
    LEFT JOIN Admin a ON u.id = a.user_id AND u.role = 'admin'
    WHERE $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// Sorting
$order_sql = match($sort) {
    'oldest' => 'u.created_at ASC',
    'name_asc' => 'COALESCE(t.firstname, s.firstname, a.email) ASC',
    'name_desc' => 'COALESCE(t.firstname, s.firstname, a.email) DESC',
    'last_active' => 'u.updated_at DESC',
    default => 'u.created_at DESC',
};

// Fetch users with pagination
$sql = "SELECT 
    u.id,
    u.username,
    u.role,
    u.status,
    u.created_at,
    u.updated_at,
    COALESCE(t.firstname, s.firstname, '') as firstname,
    COALESCE(t.lastname, s.lastname, '') as lastname,
    COALESCE(t.email, s.email, a.email, '') as email,
    COALESCE(t.department, '') as department,
    COALESCE(t.teacher_id, s.student_id, a.admin_id, 0) as role_id,
    COALESCE(t.specialization, '') as notes,
    s.student_lrn,
    s.middlename,
    s.birthdate,
    s.address,
    s.guardian_name,
    s.guardian_contact
FROM Users u
LEFT JOIN Teachers t ON u.id = t.user_id AND u.role = 'teacher'
LEFT JOIN Students s ON u.id = s.user_id AND u.role = 'student'
LEFT JOIN Admin a ON u.id = a.user_id AND u.role = 'admin'
WHERE $where_sql
ORDER BY $order_sql
LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Fetch stats
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) FROM Users WHERE role IN ('admin', 'teacher', 'student')")->fetchColumn();
$stats['students'] = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 'student'")->fetchColumn();
$stats['teachers'] = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 'teacher'")->fetchColumn();
$stats['admins'] = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 'admin'")->fetchColumn();
$stats['pending'] = $pdo->query("SELECT COUNT(*) FROM Users WHERE status = 'inactive'")->fetchColumn();

// Helper function for initials
function um_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= strtoupper(substr($p, 0, 1));
    }
    return $out ?: '?';
}

// Helper for avatar color based on role
function getRoleColor(string $role): string {
    return match($role) {
        'admin' => '#8B5CF6',
        'teacher' => '#1F6F54',
        'student' => '#2F9C74',
        default => '#6B7280',
    };
}

// Helper for display name
function getDisplayName($user): string {
    $name = trim($user['firstname'] . ' ' . $user['lastname']);
    return $name ?: $user['username'];
}

// Helper for status badge class
function getStatusClass(string $status): string {
    return match($status) {
        'active' => 'active',
        'inactive' => 'pending',
        'suspended' => 'inactive',
        default => 'pending',
    };
}

// Helper for status label
function getStatusLabel(string $status): string {
    return match($status) {
        'active' => 'Active',
        'inactive' => 'Pending',
        'suspended' => 'Suspended',
        default => 'Unknown',
    };
}

// Helper for role label
function getRoleLabel(string $role): string {
    return match($role) {
        'admin' => 'Admin',
        'teacher' => 'Teacher',
        'student' => 'Student',
        default => ucfirst($role),
    };
}

// Helper for relative time
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', $time);
}

$csrf_token = generateCSRFToken();
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
<link rel="stylesheet" href="../../public/admin/assests/css/user_management.css">
</head>
<body>

<?php include '../../includes/admin_sidebar.php'; ?>

<!-- Flash Messages -->
<?php if ($flash): ?>
<div class="flash-message flash-<?= $flash['type'] ?>" id="flashMessage">
    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= clean($flash['message']) ?>
    <span class="flash-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></span>
</div>
<?php endif; ?>

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
                <div class="stat-value"><?= number_format($stats['total']) ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-user-graduate"></i></div>
            <div>
                <div class="stat-value"><?= number_format($stats['students']) ?></div>
                <div class="stat-label">Students</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gold"><i class="fas fa-chalkboard-teacher"></i></div>
            <div>
                <div class="stat-value"><?= number_format($stats['teachers']) ?></div>
                <div class="stat-label">Teachers</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-user-shield"></i></div>
            <div>
                <div class="stat-value"><?= number_format($stats['admins']) ?></div>
                <div class="stat-label">Admins</div>
            </div>
        </div>
    </div>

    <!-- Add User Panel (hidden by default) -->
    <div class="compose-panel" id="addUserPanel" style="display:none;">
        <div class="compose-panel-header">
            <h2><i class="fas fa-user-plus"></i>&nbsp; Add New User</h2>
            <div class="close-icon" onclick="toggleAddUser()"><i class="fas fa-times"></i></div>
        </div>

        <form method="POST" action="" id="addUserForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-row">
                <div class="form-group">
                    <label>Role *</label>
                    <div class="role-options">
                        <div class="role-pill active" data-role="student" onclick="setRole(this)">
                            <i class="fas fa-user-graduate"></i> Student
                        </div>
                        <div class="role-pill" data-role="teacher" onclick="setRole(this)">
                            <i class="fas fa-chalkboard-teacher"></i> Teacher
                        </div>
                        <div class="role-pill" data-role="admin" onclick="setRole(this)">
                            <i class="fas fa-user-shield"></i> Admin
                        </div>
                    </div>
                    <input type="hidden" name="role" id="roleInput" value="student">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <div class="status-options">
                        <div class="status-pill active" data-status="active" onclick="setStatus(this)">Active</div>
                        <div class="status-pill" data-status="inactive" onclick="setStatus(this)">Inactive</div>
                        <div class="status-pill" data-status="suspended" onclick="setStatus(this)">Suspended</div>
                    </div>
                    <input type="hidden" name="status" id="statusInput" value="active">
                </div>
            </div>

            <!-- ============================================================
                 STUDENT FIELDS (shown when Role = Student)
            ============================================================= -->
            <div id="studentFields">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="firstname" class="form-control" placeholder="e.g. Maria" data-student-required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="lastname" class="form-control" placeholder="e.g. Santos" data-student-required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middlename" class="form-control" placeholder="e.g. Clara">
                    </div>
                    <div class="form-group">
                        <label>LRN * <small>(12 digits)</small></label>
                        <input type="text" name="lrn" class="form-control" placeholder="e.g. 136090100234" pattern="\d{12}" maxlength="12" data-student-required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email <small>(optional)</small></label>
                        <input type="email" name="email" id="studentEmail" class="form-control" placeholder="e.g. maria@sturiel.edu.ph">
                    </div>
                    <div class="form-group">
                        <label>Birthdate *</label>
                        <input type="date" name="birthdate" class="form-control" data-student-required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" class="form-control" placeholder="e.g. 123 Rizal St., Talisay City">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Guardian Name</label>
                        <input type="text" name="guardian_name" class="form-control" placeholder="e.g. Juana Santos">
                    </div>
                    <div class="form-group">
                        <label>Guardian Contact</label>
                        <input type="tel" name="guardian_contact" class="form-control" placeholder="e.g. +63 912 345 6789">
                    </div>
                </div>

                <div class="form-group" style="background:var(--bg-page); padding:12px 14px; border-radius:var(--radius-sm); font-size:0.85rem; color:var(--text-muted);">
                    <i class="fas fa-circle-info"></i>
                    Username &amp; password are generated automatically:
                    <strong>Username</strong> = STU-(last 4 digits of LRN)-(birthdate as MMDDYY),
                    <strong>Password</strong> = Last name + birthdate as MMDDYY.
                </div>
            </div>

            <!-- ============================================================
                 TEACHER / ADMIN FIELDS (shown when Role = Teacher/Admin)
            ============================================================= -->
            <div id="staffFields" style="display:none;">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="fullname" class="form-control" placeholder="e.g. Maria Clara Santos" data-staff-required>
                    </div>
                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" name="email" id="staffEmail" class="form-control" placeholder="e.g. maria@sturiel.edu.ph" data-staff-required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Department / Grade Level</label>
                        <input type="text" name="department" class="form-control" placeholder="e.g. Grade 7 or Mathematics Dept">
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="tel" name="contact" class="form-control" placeholder="e.g. +63 912 345 6789">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password * <small>(min 6 characters)</small></label>
                        <input type="password" name="password" class="form-control" placeholder="Enter secure password" minlength="6" data-staff-required>
                    </div>
                    <div class="form-group">
                        <label>Notes (optional)</label>
                        <input type="text" name="notes" class="form-control" placeholder="Additional information...">
                    </div>
                </div>
            </div>

            <div class="compose-actions">
                <div class="left-actions">
                    <label class="checkbox-label">
                        <input type="checkbox" name="send_email" value="1"> 
                        <i class="fas fa-envelope"></i> Send welcome email
                    </label>
                </div>
                <div class="right-actions">
                    <button type="button" class="btn-secondary" onclick="toggleAddUser()">Cancel</button>
                    <button type="submit" class="btn-gold"><i class="fas fa-save"></i> Save User</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Edit User Modal -->
    <div class="modal-overlay" id="editModal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-pen"></i> Edit User</h2>
                <div class="close-icon" onclick="closeEditModal()"><i class="fas fa-times"></i></div>
            </div>
            <form method="POST" action="" id="editUserForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="editUserId">
                <input type="hidden" name="role" id="editRoleInput">

                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="fullname" id="editFullname" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Department / Grade Level</label>
                        <input type="text" name="department" id="editDepartment" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="tel" name="contact" id="editContact" class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="editStatus" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>New Password <small>(leave blank to keep current)</small></label>
                        <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters" minlength="6">
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea>
                </div>

                <div class="compose-actions">
                    <div class="right-actions" style="margin-left:auto;">
                        <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                        <button type="submit" class="btn-gold"><i class="fas fa-save"></i> Update User</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal-overlay" id="viewModal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-eye"></i> User Profile</h2>
                <div class="close-icon" onclick="closeViewModal()"><i class="fas fa-times"></i></div>
            </div>
            <div class="view-profile" id="viewProfileContent">
                <!-- Populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Filter / Search Toolbar -->
    <form method="GET" action="" class="toolbar-row" id="searchForm">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="Search users by name or email..." 
                   value="<?= clean($search) ?>" onkeydown="if(event.key==='Enter') this.form.submit()">
        </div>
        <select name="role" class="select-filter" onchange="this.form.submit()">
            <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
            <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Students</option>
            <option value="teacher" <?= $role_filter === 'teacher' ? 'selected' : '' ?>>Teachers</option>
            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admins</option>
        </select>
        <select name="status" class="select-filter" onchange="this.form.submit()">
            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
        <select name="sort" class="select-filter" onchange="this.form.submit()">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A–Z</option>
            <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z–A</option>
            <option value="last_active" <?= $sort === 'last_active' ? 'selected' : '' ?>>Last Active</option>
        </select>
        <?php if (!empty($search) || $role_filter !== 'all' || $status_filter !== 'all'): ?>
        <a href="?" class="btn-secondary" style="text-decoration:none; display:flex; align-items:center; gap:6px;">
            <i class="fas fa-times"></i> Clear Filters
        </a>
        <?php endif; ?>
    </form>

    <!-- Results Count -->
    <div class="results-info">
        Showing <?= count($users) ?> of <?= $total_users ?> user<?= $total_users !== 1 ? 's' : '' ?>
        <?= !empty($search) ? 'matching "' . clean($search) . '"' : '' ?>
    </div>

    <!-- Users List -->
    <div class="users-list">
        <?php if (empty($users)): ?>
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <h3>No users found</h3>
            <p>Try adjusting your search or filter criteria.</p>
        </div>
        <?php else: ?>
        <?php foreach ($users as $user): 
            $display_name = getDisplayName($user);
            $role_color = getRoleColor($user['role']);
            $status_class = getStatusClass($user['status']);
            $status_label = getStatusLabel($user['status']);
            $role_label = getRoleLabel($user['role']);
            $is_pending = $user['status'] === 'inactive';
        ?>
        <div class="user-card <?= $is_pending ? 'pending' : '' ?>" data-user-id="<?= $user['id'] ?>" 
             data-fullname="<?= clean($display_name) ?>" data-email="<?= clean($user['email']) ?>"
             data-role="<?= $user['role'] ?>" data-status="<?= $user['status'] ?>"
             data-department="<?= clean($user['department']) ?>" data-notes="<?= clean($user['notes']) ?>"
             data-username="<?= clean($user['username']) ?>" data-created="<?= $user['created_at'] ?>"
             data-lrn="<?= clean($user['student_lrn'] ?? '') ?>" data-middlename="<?= clean($user['middlename'] ?? '') ?>"
             data-birthdate="<?= clean($user['birthdate'] ?? '') ?>" data-address="<?= clean($user['address'] ?? '') ?>"
             data-guardian="<?= clean($user['guardian_name'] ?? '') ?>" data-guardian-contact="<?= clean($user['guardian_contact'] ?? '') ?>">
            <div class="role-strip <?= $user['role'] ?>"></div>
            <div class="user-body">
                <div class="user-top-row">
                    <div class="user-title-line">
                        <div class="user-avatar" style="background: <?= $role_color ?>;">
                            <?= um_initials($display_name) ?>
                        </div>
                        <div class="user-title-block">
                            <h3><?= clean($display_name) ?></h3>
                            <span class="badge <?= $user['role'] ?>"><?= $role_label ?></span>
                            <span class="badge <?= $status_class ?>"><?= $status_label ?></span>
                        </div>
                    </div>
                </div>
                <p class="user-excerpt">
                    <i class="fas fa-envelope"></i> <?= clean($user['email']) ?: 'No email' ?>
                    <?php if ($user['department']): ?>
                    &nbsp;&bull;&nbsp; <i class="fas fa-building"></i> <?= clean($user['department']) ?>
                    <?php endif; ?>
                    <?php if (!empty($user['student_lrn'])): ?>
                    &nbsp;&bull;&nbsp; <i class="fas fa-id-card"></i> LRN: <?= clean($user['student_lrn']) ?>
                    <?php endif; ?>
                </p>
                <div class="user-meta">
                    <span><i class="fas fa-clock"></i> <?= timeAgo($user['updated_at']) ?></span>
                    <span><i class="fas fa-user"></i> @<?= clean($user['username']) ?></span>
                    <span><i class="fas fa-id-badge"></i> ID: SUA-<?= str_pad($user['id'], 5, '0', STR_PAD_LEFT) ?></span>
                </div>
            </div>
            <div class="card-actions">
                <div class="icon-btn" title="View Profile" onclick="viewUser(<?= $user['id'] ?>)"><i class="fas fa-eye"></i></div>
                <div class="icon-btn" title="Edit" onclick="editUser(<?= $user['id'] ?>)"><i class="fas fa-pen"></i></div>
                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to deactivate this user?');">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <button type="submit" class="icon-btn delete" title="Deactivate" style="border:none; background:none; cursor:pointer;">
                        <i class="fas fa-ban"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>

        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<script>
    // ---- Sidebar collapse/expand ----
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    }

    function setActive(el) {
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        el.classList.add('active');
    }

    // ---- Add User panel ----
    function toggleAddUser() {
        const panel = document.getElementById('addUserPanel');
        panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
        if (panel.style.display === 'block') {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // ---- Role pill selector ----
    function setRole(el) {
    document.querySelectorAll('.role-pill').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    const role = el.dataset.role;
    document.getElementById('roleInput').value = role;

    const studentFields = document.getElementById('studentFields');
    const staffFields = document.getElementById('staffFields');
    const isStudent = role === 'student';

    // Show/hide the field containers
    studentFields.style.display = isStudent ? 'block' : 'none';
    staffFields.style.display = isStudent ? 'none' : 'block';

    // Handle student fields: enable when student, disable when staff
    studentFields.querySelectorAll('input, select, textarea').forEach(field => {
        field.disabled = !isStudent;
    });
    studentFields.querySelectorAll('[data-student-required]').forEach(el => {
        el.required = isStudent;
    });

    // Handle staff fields: enable when staff, disable when student
    staffFields.querySelectorAll('input, select, textarea').forEach(field => {
        field.disabled = isStudent;
    });
    staffFields.querySelectorAll('[data-staff-required]').forEach(el => {
        el.required = !isStudent;
    });
}

    // ---- Status pill selector ----
    function setStatus(el) {
        document.querySelectorAll('.status-pill').forEach(p => p.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('statusInput').value = el.dataset.status;
    }

    // Initialize required attributes on load (default role = student)
    document.addEventListener('DOMContentLoaded', () => {
    // Default role is student — student fields enabled, staff fields disabled
    document.querySelectorAll('#studentFields input, #studentFields select, #studentFields textarea').forEach(f => f.disabled = false);
    document.querySelectorAll('#studentFields [data-student-required]').forEach(el => el.required = true);
    
    document.querySelectorAll('#staffFields input, #staffFields select, #staffFields textarea').forEach(f => f.disabled = true);
    document.querySelectorAll('#staffFields [data-staff-required]').forEach(el => el.required = false);
});

    // ---- Edit User ----
    function editUser(userId) {
        const card = document.querySelector(`.user-card[data-user-id="${userId}"]`);
        if (!card) return;

        document.getElementById('editUserId').value = userId;
        document.getElementById('editFullname').value = card.dataset.fullname;
        document.getElementById('editEmail').value = card.dataset.email;
        document.getElementById('editRoleInput').value = card.dataset.role;
        document.getElementById('editDepartment').value = card.dataset.department;
        document.getElementById('editContact').value = '';
        document.getElementById('editStatus').value = card.dataset.status;
        document.getElementById('editNotes').value = card.dataset.notes;

        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // ---- View User ----
    function viewUser(userId) {
        const card = document.querySelector(`.user-card[data-user-id="${userId}"]`);
        if (!card) return;

        const roleColors = { admin: '#8B5CF6', teacher: '#1F6F54', student: '#2F9C74' };
        const roleLabels = { admin: 'Admin', teacher: 'Teacher', student: 'Student' };
        const statusLabels = { active: 'Active', inactive: 'Pending', suspended: 'Suspended' };

        const initials = card.querySelector('.user-avatar').textContent.trim();
        const color = roleColors[card.dataset.role] || '#6B7280';

        let extraRows = '';
        if (card.dataset.role === 'student') {
            extraRows = `
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-id-card"></i> LRN</span>
                    <span class="detail-value">${card.dataset.lrn || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-cake-candles"></i> Birthdate</span>
                    <span class="detail-value">${card.dataset.birthdate || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-location-dot"></i> Address</span>
                    <span class="detail-value">${card.dataset.address || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-people-roof"></i> Guardian</span>
                    <span class="detail-value">${card.dataset.guardian || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-phone"></i> Guardian Contact</span>
                    <span class="detail-value">${card.dataset.guardianContact || 'Not set'}</span>
                </div>
            `;
        }

        document.getElementById('viewProfileContent').innerHTML = `
            <div class="view-header">
                <div class="view-avatar" style="background:${color}">${initials}</div>
                <div class="view-info">
                    <h3>${card.dataset.fullname}</h3>
                    <span class="badge ${card.dataset.role}">${roleLabels[card.dataset.role]}</span>
                    <span class="badge ${card.dataset.status === 'active' ? 'active' : (card.dataset.status === 'suspended' ? 'inactive' : 'pending')}">${statusLabels[card.dataset.status]}</span>
                </div>
            </div>
            <div class="view-details">
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-envelope"></i> Email</span>
                    <span class="detail-value">${card.dataset.email || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-user"></i> Username</span>
                    <span class="detail-value">@${card.dataset.username}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-building"></i> Department</span>
                    <span class="detail-value">${card.dataset.department || 'Not assigned'}</span>
                </div>
                ${extraRows}
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-id-badge"></i> User ID</span>
                    <span class="detail-value">SUA-${String(userId).padStart(5, '0')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-calendar"></i> Created</span>
                    <span class="detail-value">${new Date(card.dataset.created).toLocaleDateString()}</span>
                </div>
            </div>
        `;
        document.getElementById('viewModal').style.display = 'flex';
    }

    function closeViewModal() {
        document.getElementById('viewModal').style.display = 'none';
    }

    // Close modals on outside click
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
        }
    }

    // Auto-hide flash messages
    setTimeout(() => {
        const flash = document.getElementById('flashMessage');
        if (flash) {
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 300);
        }
    }, 5000);
</script>

</body>
</html>