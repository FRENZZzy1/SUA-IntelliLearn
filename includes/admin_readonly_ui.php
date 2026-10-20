<?php
/**
 * Hides write controls (add / edit / archive / delete / post / backup buttons)
 * from admins who only have READ permission on a module.
 *
 * This is cosmetic only. The real enforcement is server-side in
 * includes/access_control.php (enforceCurrentAdminModuleAccess), which refuses
 * every write request from an admin without write permission.
 *
 * Usage (inside <head>):  <?php include '../../includes/admin_readonly_ui.php'; ?>
 */
if (!function_exists('adminCanWrite')) {
    require_once __DIR__ . '/access_control.php';
}

$__hide = [];

if (!adminCanWrite('courses')) {
    $__hide[] = '[onclick*="openAddCourseModal"]';
    $__hide[] = '[onclick*="openAddSubjectModal"]';
    $__hide[] = '[onclick*="openAddSectionModal"]';
    $__hide[] = '[onclick*="openTermIntervalModal"]';
    $__hide[] = '[onclick*="openEditCourseModal"]';
    $__hide[] = '[onclick*="openEditSectionModal"]';
    $__hide[] = '[onclick*="openEditSubjectModal"]';
    $__hide[] = '[onclick*="editCourseOffering"]';
    $__hide[] = '[onclick*="deleteCourseOffering"]';
    $__hide[] = '[onclick*="unenrollSelectedStudents"]';
    $__hide[] = 'a.archive, span.archive, a.restore';
    $__hide[] = '.vs-unenroll-btn';
}
if (!adminCanWrite('users')) {
    $__hide[] = '[onclick*="openAddUserModal"]';
}
if (!adminCanWrite('announcements')) {
    $__hide[] = '[onclick*="announcement.php"]';
    $__hide[] = '[onclick*="deleteAnnouncement"]';
    $__hide[] = '[onclick*="editAnnouncement"]';
    $__hide[] = '[onclick*="openEditAnnouncement"]';
    $__hide[] = '#newAnnouncementBtn';
    $__hide[] = '#composePanel';
    $__hide[] = 'a.icon-btn[href*="action="]';
}
if (!adminCanWrite('dashboard')) {
    $__hide[] = '[onclick*="backupData"]';
}

if ($__hide): ?>
<style id="readonly-ui">
<?= implode(",\n", $__hide) ?> { display: none !important; }
</style>
<?php endif; unset($__hide); ?>
