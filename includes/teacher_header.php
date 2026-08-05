<?php
/**
 * teacher_header.php
 *
 * Reusable top header for teacher pages. Same contract as
 * admin_header.php — session already started, user already validated
 * by the including page, this file just displays $_SESSION and wires
 * up the student-search box.
 *
 * Include it the same way you include teachers_sidebar.php:
 *   include '../../includes/teacher_header.php';
 *
 * Search is intentionally scoped to "students this teacher teaches" —
 * see assests/api/search_students.php for the enforcement, this file
 * just calls that endpoint and renders whatever comes back.
 */

$displayName = $_SESSION['username'] ?? 'Guest';
$userRole    = $_SESSION['role'] ?? '';

if (function_exists('get_initials')) {
    $initials = get_initials($displayName);
} else {
    $parts = preg_split('/\s+/', trim($displayName));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    $initials = $initials ?: '?';
}
?>
<link rel="stylesheet" href="/SUA-INTELLILEARN/includes/css/header.css">
<style>
    /* Student search dropdown — scoped here since header.css is shared/unknown at edit time */
    .header-search { position: relative; }
    .search-results-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        width: 380px;
        max-width: 90vw;
        max-height: 420px;
        overflow-y: auto;
        background: #fff;
        border-radius: 12px;
        box-shadow: var(--shadow-lg, 0 10px 30px rgba(0,0,0,0.15));
        border: 1px solid rgba(0,0,0,0.06);
        z-index: 1000;
    }
    .search-results-dropdown.open { display: block; }
    .search-group-label {
        padding: 10px 16px 4px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #9ca3af;
    }
    .search-result-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 16px;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        background: none;
        border: none;
        width: 100%;
        text-align: left;
        font-family: inherit;
        font-size: inherit;
    }
    .search-result-item:hover, .search-result-item:focus { background: #f5f5f7; outline: none; }
    .search-result-icon {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.68rem;
        font-weight: 700;
        color: #fff;
        background: #1b4332;
        flex-shrink: 0;
    }
    .search-result-main { display: flex; flex-direction: column; min-width: 0; flex: 1; }
    .search-result-title {
        font-size: 0.85rem;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .search-result-sub { font-size: 0.72rem; color: #9ca3af; }
    .search-empty-state, .search-loading-state {
        padding: 20px 16px;
        text-align: center;
        font-size: 0.8rem;
        color: #9ca3af;
    }

    /* Profile dropdown */
    .thp-profile { position: relative; }
    .thp-profile-trigger {
        display: flex; align-items: center; gap: 8px;
        cursor: pointer; user-select: none;
    }
    .thp-profile-trigger .fa-chevron-down {
        font-size: 0.65rem; color: #9ca3af; margin-left: 2px;
        transition: transform 0.15s ease;
    }
    .thp-profile.open .fa-chevron-down { transform: rotate(180deg); }
    .thp-profile-menu {
        display: none;
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        width: 220px;
        background: #fff;
        border-radius: 12px;
        box-shadow: var(--shadow-lg, 0 10px 30px rgba(0,0,0,0.15));
        border: 1px solid rgba(0,0,0,0.06);
        z-index: 1000;
        overflow: hidden;
        padding: 6px;
    }
    .thp-profile.open .thp-profile-menu { display: block; }
    .thp-profile-header {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 10px 12px;
        border-bottom: 1px solid #f0f0f2;
        margin-bottom: 6px;
    }
    .thp-profile-header .header-avatar { flex-shrink: 0; }
    .thp-profile-header-text { min-width: 0; }
    .thp-profile-header-name {
        font-size: 0.85rem; font-weight: 600; color: #1f2937;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .thp-profile-header-role {
        font-size: 0.72rem; color: #9ca3af; text-transform: capitalize;
    }
    .thp-profile-item {
        display: flex; align-items: center; gap: 10px;
        width: 100%; padding: 9px 10px; border-radius: 8px;
        font-size: 0.83rem; color: #374151; text-decoration: none;
        background: none; border: none; cursor: pointer; text-align: left;
        font-family: inherit;
    }
    .thp-profile-item i { width: 16px; text-align: center; color: #9ca3af; }
    .thp-profile-item:hover { background: #f5f5f7; }
    .thp-profile-item.thp-logout { color: #b91c1c; margin-top: 4px; }
    .thp-profile-item.thp-logout i { color: #b91c1c; }
    .thp-profile-item.thp-logout:hover { background: #fef2f2; }

    /* Student quick-view modal */
    .svm-overlay {
        display: none;
        position: fixed; inset: 0;
        background: rgba(16, 40, 30, 0.45);
        z-index: 1100;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .svm-overlay.open { display: flex; }
    .svm-card {
        background: #fff;
        border-radius: 16px;
        width: 460px;
        max-width: 100%;
        max-height: 88vh;
        overflow-y: auto;
        box-shadow: 0 20px 50px rgba(0,0,0,0.25);
    }
    .svm-head {
        background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%);
        color: #fff;
        padding: 22px 22px 18px;
        border-radius: 16px 16px 0 0;
        display: flex;
        gap: 14px;
        align-items: center;
        position: relative;
    }
    .svm-close {
        position: absolute; top: 14px; right: 14px;
        background: rgba(255,255,255,0.18);
        border: none; color: #fff;
        width: 28px; height: 28px; border-radius: 50%;
        cursor: pointer; font-size: 0.8rem;
        display: grid; place-items: center;
    }
    .svm-avatar {
        width: 52px; height: 52px; border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: grid; place-items: center;
        font-weight: 700; font-size: 1.1rem;
        flex-shrink: 0;
    }
    .svm-head-text { min-width: 0; }
    .svm-name { font-size: 1.1rem; font-weight: 700; margin: 0 0 4px; }
    .svm-meta { font-size: 0.8rem; opacity: 0.85; }
    .svm-body { padding: 18px 22px 22px; }
    .svm-section { margin-bottom: 18px; }
    .svm-section:last-child { margin-bottom: 0; }
    .svm-section-title {
        font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.04em; color: #9ca3af; margin: 0 0 10px;
    }
    .svm-subject-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 10px 12px; border-radius: 10px; background: #f4f7f5;
        margin-bottom: 8px; font-size: 0.85rem;
    }
    .svm-subject-row:last-child { margin-bottom: 0; }
    .svm-subject-name { font-weight: 600; color: #1b4332; }
    .svm-subject-schedule { color: #6b7d74; font-size: 0.75rem; }
    .svm-info-grid {
        display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
        font-size: 0.83rem;
    }
    .svm-info-grid div span { display: block; }
    .svm-info-label { color: #9ca3af; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.03em; }
    .svm-empty-note {
        font-size: 0.8rem; color: #9ca3af; font-style: italic;
    }
    .svm-loading, .svm-error {
        padding: 40px 20px; text-align: center; color: #9ca3af; font-size: 0.85rem;
    }

    @media (max-width: 480px) {
        .svm-info-grid { grid-template-columns: 1fr; }
        .header-search input { width: 100%; }
    }

    /* ==========================================================
       Defensive mobile refinements layered on top of header.css's
       existing 768px breakpoint (.top-header left:0 !important,
       .header-search width:200px). These target phones where even
       a 200px search bar + icons + named profile pill get tight.
       ========================================================== */
    @media (max-width: 768px) {
        .top-header {
            padding-left: 14px;
            padding-right: 14px;
            /* Reserve space on the left for the fixed hamburger button
               from teacher_sidebar.css (top:14px; left:14px; width:42px).
               header.css does not exist in this project, so nothing else
               pushes the header content clear of that fixed button —
               without this, the search box renders underneath it. */
            padding-left: 64px;
        }

        .header-search {
            width: auto;
            flex: 1 1 auto;
            min-width: 0;
            margin-right: 8px;
        }

        .search-results-dropdown {
            left: 0;
            width: min(380px, calc(100vw - 28px));
        }

        .header-actions {
            flex: 0 0 auto;
            gap: 4px;
        }

        /* Collapse the profile trigger to avatar + chevron only —
           the name is still shown inside the open dropdown header. */
        .thp-profile-trigger span {
            display: none;
        }
        .thp-profile-trigger {
            padding: 0 6px !important;
        }

        .thp-profile-menu {
            width: 200px;
        }
    }

    @media (max-width: 400px) {
        /* Header is position:fixed with a fixed height — wrapping to
           a second row would overlap page content, so we keep it to
           one row and just shrink further instead. */
        .header-btn {
            width: 34px;
            height: 34px;
        }
        .header-search input {
            padding: 8px 12px 8px 34px;
            font-size: 0.8rem;
        }
    }
</style>
<header class="top-header">
    <div class="header-search">
        <i class="fas fa-search"></i>
        <input type="text" id="studentSearchInput" placeholder="Search your students…" autocomplete="off">
        <div id="searchResultsDropdown" class="search-results-dropdown"></div>
    </div>
    <div class="header-actions">
        <button class="header-btn">
            <i class="fas fa-bell"></i>
            <span class="notif-dot"></span>
        </button>
        <button class="header-btn">
            <i class="fas fa-question-circle"></i>
        </button>
        <div class="thp-profile" id="thpProfile">
            <button type="button"
                    class="header-btn thp-profile-trigger"
                    style="width: auto; gap: 8px; padding: 0 12px; border-radius: 20px;"
                    title="<?php echo htmlspecialchars($userRole); ?>"
                    onclick="thpToggleMenu()"
                    aria-haspopup="true"
                    aria-expanded="false">
                <div class="header-avatar" style="width: 28px; height: 28px; font-size: 0.7rem;"><?php echo htmlspecialchars($initials); ?></div>
                <span style="font-size: 0.8rem; font-weight: 500;"><?php echo htmlspecialchars($displayName); ?></span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="thp-profile-menu" id="thpProfileMenu">
                <div class="thp-profile-header">
                    <div class="header-avatar" style="width: 34px; height: 34px; font-size: 0.75rem;"><?php echo htmlspecialchars($initials); ?></div>
                    <div class="thp-profile-header-text">
                        <div class="thp-profile-header-name"><?php echo htmlspecialchars($displayName); ?></div>
                        <div class="thp-profile-header-role"><?php echo htmlspecialchars($userRole); ?></div>
                    </div>
                </div>
                <a href="/SUA-INTELLILEARN/public/teacher/settings.php" class="thp-profile-item">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </a>
                <a href="/SUA-INTELLILEARN/public/logout.php" class="thp-profile-item thp-logout">
                    <i class="fas fa-arrow-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Student quick-view modal -->
<div class="svm-overlay" id="svmOverlay">
    <div class="svm-card" id="svmCard" role="dialog" aria-modal="true" aria-labelledby="svmName">
        <!-- Populated by JS on open -->
    </div>
</div>

<script>
(function () {
    // ---------- Profile dropdown (same pattern as admin) ----------
    function thpEl() { return document.getElementById('thpProfile'); }

    window.thpToggleMenu = function () {
        var el = thpEl();
        var trigger = el.querySelector('.thp-profile-trigger');
        var isOpen = el.classList.toggle('open');
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    function thpClose() {
        var el = thpEl();
        if (!el) return;
        el.classList.remove('open');
        el.querySelector('.thp-profile-trigger').setAttribute('aria-expanded', 'false');
    }

    // ---------- Student search ----------
    var API_BASE = '/SUA-INTELLILEARN/public/teacher/assets/api/';
    var input    = document.getElementById('studentSearchInput');
    var dropdown = document.getElementById('searchResultsDropdown');
    var overlay  = document.getElementById('svmOverlay');
    var card     = document.getElementById('svmCard');

    var debounceTimer = null;
    var activeController = null;

    function initials(name) {
        return name.split(/\s+/).slice(0, 2).map(function (p) { return p.charAt(0).toUpperCase(); }).join('') || '?';
    }

    function openDropdown() { dropdown.classList.add('open'); }
    function closeDropdown() { dropdown.classList.remove('open'); }

    function renderLoading() {
        dropdown.innerHTML = '<div class="search-loading-state"><i class="fas fa-spinner fa-spin"></i> Searching…</div>';
        openDropdown();
    }

    function renderEmpty(term) {
        dropdown.innerHTML = '<div class="search-empty-state">No students matching "' + escapeHtml(term) + '" in your classes.</div>';
        openDropdown();
    }

    function renderResults(results) {
        var html = '<div class="search-group-label">Students</div>';
        results.forEach(function (r) {
            html += '' +
                '<button type="button" class="search-result-item" data-student-id="' + r.student_id + '">' +
                    '<div class="search-result-icon">' + escapeHtml(initials(r.name)) + '</div>' +
                    '<div class="search-result-main">' +
                        '<span class="search-result-title">' + escapeHtml(r.name) + '</span>' +
                        '<span class="search-result-sub">Grade ' + r.grade + ' – ' + escapeHtml(r.section) + ' · LRN ' + escapeHtml(r.lrn) + '</span>' +
                    '</div>' +
                '</button>';
        });
        dropdown.innerHTML = html;
        openDropdown();

        dropdown.querySelectorAll('.search-result-item').forEach(function (btn) {
            btn.addEventListener('click', function () {
                closeDropdown();
                input.value = '';
                openStudent(btn.getAttribute('data-student-id'));
            });
        });
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    input.addEventListener('input', function () {
        var term = input.value.trim();
        clearTimeout(debounceTimer);

        if (term.length < 2) {
            closeDropdown();
            return;
        }

        debounceTimer = setTimeout(function () {
            renderLoading();

            if (activeController) activeController.abort();
            activeController = new AbortController();

            fetch(API_BASE + 'search_students.php?q=' + encodeURIComponent(term), { signal: activeController.signal })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success) { renderEmpty(term); return; }
                    if (!data.results.length) { renderEmpty(term); return; }
                    renderResults(data.results);
                })
                .catch(function (err) {
                    if (err.name !== 'AbortError') {
                        dropdown.innerHTML = '<div class="search-empty-state">Something went wrong. Try again.</div>';
                        openDropdown();
                    }
                });
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.header-search')) closeDropdown();
        if (thpEl() && !thpEl().contains(e.target)) thpClose();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeDropdown();
            thpClose();
            closeModal();
        }
    });

    // ---------- Student quick-view modal ----------
    function openStudent(studentId) {
        card.innerHTML = '<div class="svm-loading"><i class="fas fa-spinner fa-spin"></i> Loading student…</div>';
        overlay.classList.add('open');

        fetch(API_BASE + 'student_lookup.php?student_id=' + encodeURIComponent(studentId))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) {
                    card.innerHTML = '<div class="svm-error">' + escapeHtml((data.errors && data.errors[0]) || 'Could not load this student.') + '</div>';
                    return;
                }
                renderStudentCard(data);
            })
            .catch(function () {
                card.innerHTML = '<div class="svm-error">Something went wrong loading this student.</div>';
            });
    }

    function renderStudentCard(data) {
        var s = data.student;
        var subjectsHtml = data.subjects.map(function (c) {
            return '' +
                '<div class="svm-subject-row">' +
                    '<span class="svm-subject-name">' + escapeHtml(c.subject) + '</span>' +
                    '<span class="svm-subject-schedule">' + escapeHtml(c.schedule) + '</span>' +
                '</div>';
        }).join('');

        var perf = data.performance;
        var perfHtml = '<div class="svm-info-grid">' +
            '<div><span class="svm-info-label">Avg. Grade (with you)</span><span>' + (perf.avg_grade !== null ? perf.avg_grade + '%' : '— (coming soon)') + '</span></div>' +
            '<div><span class="svm-info-label">Attendance Rate</span><span>' + (perf.attendance_rate !== null ? perf.attendance_rate + '%' : '— (coming soon)') + '</span></div>' +
        '</div>';

        card.innerHTML = '' +
            '<div class="svm-head">' +
                '<button type="button" class="svm-close" onclick="document.getElementById(\'svmOverlay\').classList.remove(\'open\')" aria-label="Close">' +
                    '<i class="fas fa-times"></i>' +
                '</button>' +
                '<div class="svm-avatar">' + escapeHtml(initials(s.name)) + '</div>' +
                '<div class="svm-head-text">' +
                    '<h3 class="svm-name" id="svmName">' + escapeHtml(s.name) + '</h3>' +
                    '<div class="svm-meta">Grade ' + s.grade + ' – ' + escapeHtml(s.section) + ' · LRN ' + escapeHtml(s.lrn) + '</div>' +
                '</div>' +
            '</div>' +
            '<div class="svm-body">' +
                '<div class="svm-section">' +
                    '<p class="svm-section-title">Enrolled With You</p>' +
                    (subjectsHtml || '<p class="svm-empty-note">No active classes with you.</p>') +
                '</div>' +
                '<div class="svm-section">' +
                    '<p class="svm-section-title">Student Info</p>' +
                    '<div class="svm-info-grid">' +
                        '<div><span class="svm-info-label">Gender</span><span>' + escapeHtml(s.gender || '—') + '</span></div>' +
                        '<div><span class="svm-info-label">Birthdate</span><span>' + escapeHtml(s.birthdate || '—') + '</span></div>' +
                    '</div>' +
                '</div>' +
                '<div class="svm-section">' +
                    '<p class="svm-section-title">Guardian Contact</p>' +
                    '<div class="svm-info-grid">' +
                        '<div><span class="svm-info-label">Name</span><span>' + escapeHtml(s.guardian_name || '—') + '</span></div>' +
                        '<div><span class="svm-info-label">Contact</span><span>' + escapeHtml(s.guardian_contact || '—') + '</span></div>' +
                    '</div>' +
                '</div>' +
                '<div class="svm-section">' +
                    '<p class="svm-section-title">Performance Snapshot</p>' +
                    perfHtml +
                '</div>' +
            '</div>';
    }

    function closeModal() { overlay.classList.remove('open'); }

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });

    // ---------- Shorter placeholder on narrow screens ----------
    // (CSS can't rewrite placeholder text, so this does it in JS.)
    var mobileQuery = window.matchMedia('(max-width: 640px)');
    function applyPlaceholder(mq) {
        input.placeholder = mq.matches ? 'Search students…' : 'Search your students…';
    }
    applyPlaceholder(mobileQuery);
    if (mobileQuery.addEventListener) {
        mobileQuery.addEventListener('change', applyPlaceholder);
    } else {
        mobileQuery.addListener(applyPlaceholder); // Safari <14 fallback
    }
})();
</script>