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