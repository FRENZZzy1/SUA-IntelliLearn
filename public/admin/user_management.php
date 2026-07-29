<?php
require_once 'assests/api/user_management_logic.php';


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

<?php include '../../includes/admin_sidebar.php';
    $aum_endpoint = '/public/admin/assests/api/add_user_handler.php';
$aum_endpoint = '/SUA-IntelliLearn/public/admin/assests/api/add_user_handler.php';
include 'assests/api/add_user_modal.php';

?>


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
        <button class="btn-primary" id="newUserBtn" onclick="openAddUserModal()">
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

                <!-- Student & Teacher: separate name fields (matches Students / Teachers columns).
                     Admin has NO name field at all — admin table has no name columns. -->
                <div class="form-row" id="editNameRow" style="display:none;">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="firstname" id="editFirstname" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="lastname" id="editLastname" class="form-control">
                    </div>
                </div>
                <div class="form-row" id="editMiddleNameRow" style="display:none;">
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middlename" id="editMiddlename" class="form-control">
                    </div>
                </div>

                <!-- Email: one real field for every role (no more hidden duplicate) -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                        <small id="editTeacherEmailHint" style="display:none; color:var(--text-muted);">This is also the teacher's login username.</small>
                    </div>
                </div>

                <!-- Teacher only: department + contact (not columns on the admin table) -->
                <div class="form-row" id="editStaffMetaRow">
                    <div class="form-group">
                        <label>Department / Grade Level</label>
                        <input type="text" name="department" id="editDepartment" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="tel" name="contact" id="editContact" class="form-control">
                    </div>
                </div>

                <div class="form-row" id="editTeacherOnlyFields" style="display:none;">
                    <div class="form-group">
                        <label>Employment Status</label>
                        <select name="employment_status" id="editEmploymentStatus" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="full-time">Full-time</option>
                            <option value="part-time">Part-time</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Specialization</label>
                        <input type="text" name="specialization" id="editNotes" class="form-control" placeholder="e.g. Algebra, Biology">
                    </div>
                </div>

                <!-- Admin only: access_level + position (real columns on the admin table) -->
                <div class="form-row" id="editAdminOnlyFields" style="display:none;">
                    <div class="form-group">
                        <label>Position</label>
                        <select name="position" id="editPosition" class="form-control">
                            <option value="principal">Principal</option>
                            <option value="registrar">Registrar</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Access Level</label>
                        <select name="access_level" id="editAccessLevel" class="form-control">
                            <option value="full">Full</option>
                            <option value="limited">Limited</option>
                            <option value="read_only">Read Only</option>
                        </select>
                    </div>
                </div>

                <!-- Student only fields -->
                <div id="editStudentFields" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>LRN <small>(12 digits)</small></label>
                            <input type="text" name="lrn" id="editLrn" class="form-control" pattern="\d{12}" maxlength="12">
                        </div>
                        <div class="form-group">
                            <label>Birthdate</label>
                            <input type="date" name="birthdate" id="editBirthdate" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender" id="editGender" class="form-control">
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" id="editAddress" class="form-control">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Guardian Name</label>
                            <input type="text" name="guardian_name" id="editGuardianName" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Guardian Contact</label>
                            <input type="tel" name="guardian_contact" id="editGuardianContact" class="form-control">
                        </div>
                    </div>
                </div>

                <script>
               // Shows/hides the right block of fields for the role being edited, and
                // keeps `required` in sync with visibility so hidden fields never block
                //  native form validation.
                
                </script>

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
                        <input type="password" name="new_password" id="editNewPassword" class="form-control" placeholder="Min 6 characters" minlength="6">
                    </div>
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
        <?php if ($role_filter === 'all' || $role_filter === 'teacher'): ?>
        <select name="department" class="select-filter" onchange="this.form.submit()">
            <option value="all" <?= $department_filter === 'all' ? 'selected' : '' ?>>All Departments</option>
            <?php foreach ($departments as $dept): ?>
            <option value="<?= clean($dept) ?>" <?= $department_filter === $dept ? 'selected' : '' ?>><?= clean($dept) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if ($role_filter === 'all' || $role_filter === 'student'): ?>
        <select name="grade_level" class="select-filter" onchange="this.form.submit()">
            <option value="all" <?= $grade_level_filter === 'all' ? 'selected' : '' ?>>All Year Levels</option>
            <?php foreach ($grade_levels as $gl): ?>
            <option value="<?= clean($gl) ?>" <?= (string)$grade_level_filter === (string)$gl ? 'selected' : '' ?>>Grade <?= clean($gl) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if ($role_filter === 'all' || $role_filter === 'student'): ?>
        <select name="gender" class="select-filter" onchange="this.form.submit()">
            <option value="all" <?= $gender_filter === 'all' ? 'selected' : '' ?>>All Genders</option>
            <option value="male" <?= $gender_filter === 'male' ? 'selected' : '' ?>>Male</option>
            <option value="female" <?= $gender_filter === 'female' ? 'selected' : '' ?>>Female</option>
        </select>
        <?php endif; ?>
        <select name="sort" class="select-filter" onchange="this.form.submit()">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A–Z</option>
            <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z–A</option>
            <option value="last_active" <?= $sort === 'last_active' ? 'selected' : '' ?>>Last Active</option>
        </select>
        <?php if (!empty($search) || $role_filter !== 'all' || $status_filter !== 'all' || $department_filter !== 'all' || $grade_level_filter !== 'all' || $gender_filter !== 'all'): ?>
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
    <?php if (empty($users)): ?>
    <div class="empty-state">
        <i class="fas fa-users-slash"></i>
        <h3>No users found</h3>
        <p>Try adjusting your search or filter criteria.</p>
    </div>

    <?php else: ?>
    <!-- ============== USERS TABLE VIEW (all roles) ============== -->
    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th class="th-user">User</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th><?php
                        $effective_role = $role_filter;
                        if ($effective_role === 'all') {
                            if ($department_filter !== 'all' && $department_filter !== '') {
                                $effective_role = 'teacher';
                            } elseif ($grade_level_filter !== 'all' && $grade_level_filter !== '') {
                                $effective_role = 'student';
                            }
                        }
                        echo match($effective_role) {
                            'student' => 'LRN',
                            'teacher' => 'Department',
                            'admin' => 'Position',
                            default => 'Details',
                        };
                    ?></th>
                    <th>Status</th>
                    <th>Last Active</th>
                    <th class="th-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user):
                    $display_name = getDisplayName($user);
                    $role_color = getRoleColor($user['role']);
                    $status_class = getStatusClass($user['status']);
                    $status_label = getStatusLabel($user['status']);
                    $role_label = getRoleLabel($user['role']);
                    $is_pending = $user['status'] === 'inactive';

                    // Build a role-appropriate "Details" cell: LRN for students,
                    // department for teachers, position for admins.
                    if ($user['role'] === 'student') {
                        $gender_labels = ['male' => 'Male', 'female' => 'Female'];
                        $details_html = !empty($user['student_lrn'])
                            ? '<span class="chip"><i class="fas fa-id-card"></i> ' . clean($user['student_lrn']) . '</span>'
                            : '<span class="t-muted">No LRN</span>';
                        $details_html .= !empty($user['grade_level'])
                            ? ' <span class="chip"><i class="fas fa-layer-group"></i> Grade ' . clean($user['grade_level']) . '</span>'
                            : '';
                        $details_html .= !empty($user['gender'])
                            ? ' <span class="chip"><i class="fas fa-venus-mars"></i> ' . clean($gender_labels[$user['gender']] ?? ucfirst($user['gender'])) . '</span>'
                            : '';
                    } elseif ($user['role'] === 'teacher') {
                        $details_html = $user['department']
                            ? '<span class="chip"><i class="fas fa-building"></i> ' . clean($user['department']) . '</span>'
                            : '<span class="t-muted">Not assigned</span>';
                    } elseif ($user['role'] === 'admin') {
                        $position_labels = ['principal' => 'Principal', 'registrar' => 'Registrar', 'staff' => 'Staff'];
                        $position_text = $position_labels[$user['admin_position'] ?? ''] ?? 'Staff';
                        $details_html = '<span class="chip"><i class="fas fa-user-tie"></i> ' . clean($position_text) . '</span>';
                    } else {
                        $details_html = '<span class="t-muted">—</span>';
                    }
                ?>
                <tr class="<?= $is_pending ? 'pending' : '' ?>" data-user-id="<?= $user['id'] ?>"
                    data-fullname="<?= clean($display_name) ?>" data-email="<?= clean($user['email']) ?>"
                    data-firstname="<?= clean($user['firstname']) ?>" data-lastname="<?= clean($user['lastname']) ?>"
                    data-role="<?= $user['role'] ?>" data-status="<?= $user['status'] ?>"
                    data-department="<?= clean($user['department']) ?>" data-notes="<?= clean($user['notes']) ?>"
                    data-employment-status="<?= clean($user['employment_status'] ?? '') ?>"
                    data-position="<?= clean($user['admin_position'] ?? '') ?>" data-access-level="<?= clean($user['admin_access_level'] ?? '') ?>"
                    data-username="<?= clean($user['username']) ?>" data-created="<?= $user['created_at'] ?>"
                    data-lrn="<?= clean($user['student_lrn'] ?? '') ?>" data-middlename="<?= clean($user['middlename'] ?? '') ?>"
                    data-gender="<?= clean($user['gender'] ?? '') ?>"
                    data-birthdate="<?= clean($user['birthdate'] ?? '') ?>" data-address="<?= clean($user['address'] ?? '') ?>"
                    data-guardian="<?= clean($user['guardian_name'] ?? '') ?>" data-guardian-contact="<?= clean($user['guardian_contact'] ?? '') ?>">
                    <td data-label="User">
                        <div class="t-name-cell">
                            <div class="user-avatar-sm" style="background: <?= $role_color ?>;"><?= um_initials($display_name) ?></div>
                            <div class="t-name-block">
                                <span class="t-name"><?= clean($display_name) ?></span>
                                <span class="t-id">SUA-<?= str_pad($user['id'], 5, '0', STR_PAD_LEFT) ?></span>
                            </div>
                        </div>
                    </td>
                    <td data-label="Role">
                        <span class="badge <?= $user['role'] ?>"><?= $role_label ?></span>
                    </td>
                    <td data-label="Email">
                        <span class="t-email"><?= clean($user['email']) ?: '—' ?></span>
                    </td>
                    <td data-label="Details">
                        <?= $details_html ?>
                    </td>
                    <td data-label="Status">
                        <span class="badge <?= $status_class ?>"><?= $status_label ?></span>
                    </td>
                    <td data-label="Last Active">
                        <span class="t-muted"><i class="fas fa-clock"></i> <?= timeAgo($user['updated_at']) ?></span>
                    </td>
                    <td class="td-actions" data-label="Actions">
                        <div class="t-actions">
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
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

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

<script src="assests/js/user_management.js"></script>
   
</body>
</html>`