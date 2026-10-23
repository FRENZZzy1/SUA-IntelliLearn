<?php
/**
 * admin_header.php
 *
 * Reusable top header for admin pages.
 * Expects a session to already be started (session_start()) and the
 * user's info already validated by the including page — this file just
 * displays whatever is in $_SESSION.
 *
 * Include it the same way you include admin_sidebar.php:
 *   include '../../includes/admin_header.php';
 */

$displayName = $_SESSION['username'] ?? 'Guest';
$userRole    = $_SESSION['role'] ?? '';

// Reuse get_initials() from dashboard_functions.php if it's already loaded,
// otherwise fall back to a simple inline version so this file also works
// on pages that don't include the dashboard data layer.
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
    /* Global search dropdown — scoped here since header.css is shared/unknown at edit time */
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
    }
    .search-result-item:hover { background: #f5f5f7; }
    .search-result-icon {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 600;
        color: #fff;
        flex-shrink: 0;
    }
    .search-result-main { display: flex; flex-direction: column; min-width: 0; }
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
    .ahp-profile { position: relative; }
    .ahp-profile-trigger {
        display: flex; align-items: center; gap: 8px;
        cursor: pointer; user-select: none;
    }
    .ahp-profile-trigger .fa-chevron-down {
        font-size: 0.65rem; color: #9ca3af; margin-left: 2px;
        transition: transform 0.15s ease;
    }
    .ahp-profile.open .fa-chevron-down { transform: rotate(180deg); }
    .ahp-profile-menu {
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
    .ahp-profile.open .ahp-profile-menu { display: block; }
    .ahp-profile-header {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 10px 12px;
        border-bottom: 1px solid #f0f0f2;
        margin-bottom: 6px;
    }
    .ahp-profile-header .header-avatar { flex-shrink: 0; }
    .ahp-profile-header-text { min-width: 0; }
    .ahp-profile-header-name {
        font-size: 0.85rem; font-weight: 600; color: #1f2937;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ahp-profile-header-role {
        font-size: 0.72rem; color: #9ca3af; text-transform: capitalize;
    }
    .ahp-profile-item {
        display: flex; align-items: center; gap: 10px;
        width: 100%; padding: 9px 10px; border-radius: 8px;
        font-size: 0.83rem; color: #374151; text-decoration: none;
        background: none; border: none; cursor: pointer; text-align: left;
        font-family: inherit;
    }
    .ahp-profile-item i { width: 16px; text-align: center; color: #9ca3af; }
    .ahp-profile-item:hover { background: #f5f5f7; }
    .ahp-profile-item.ahp-logout { color: #b91c1c; margin-top: 4px; }
    .ahp-profile-item.ahp-logout i { color: #b91c1c; }
    .ahp-profile-item.ahp-logout:hover { background: #fef2f2; }
    /* Notifications dropdown — built from existing tables, see
       public/admin/assests/api/notifications.php */
    .shn { position: relative; }
    .shn-badge {
        /* Sits on the bell's top-right corner so the icon stays visible. */
        position: absolute; top: -6px; left: 24px;
        min-width: 18px; height: 18px; padding: 0 4px;
        border-radius: 8px; border: 2px solid #fff;
        background: var(--danger, #ef4444); color: #fff;
        font-size: 0.6rem; font-weight: 700; line-height: 1;
        display: none; align-items: center; justify-content: center;
        box-sizing: border-box; pointer-events: none;
    }
    .shn-badge.show { display: flex; }
    .shn-panel {
        display: none;
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        width: 392px;
        max-width: calc(100vw - 24px);
        background: #fff;
        border-radius: 12px;
        box-shadow: var(--shadow-lg, 0 10px 30px rgba(0,0,0,0.15));
        border: 1px solid rgba(0,0,0,0.06);
        z-index: 1000;
        overflow: hidden;
    }
    .shn.open .shn-panel { display: block; }
    .shn-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 10px; padding: 14px 16px 10px;
    }
    .shn-title { font-size: 0.95rem; font-weight: 700; color: #1f2937; margin: 0; }
    .shn-markall {
        background: none; border: none; padding: 4px 6px; border-radius: 6px;
        font: inherit; font-size: 0.78rem; font-weight: 600;
        color: var(--primary, #1a5c3a); cursor: pointer;
    }
    .shn-markall:hover:not(:disabled) { background: rgba(26,92,58,0.08); }
    .shn-markall:disabled { color: #9ca3af; cursor: default; }
    .shn-tabs { display: flex; gap: 6px; padding: 0 16px 10px; border-bottom: 1px solid #f0f0f2; }
    .shn-tab {
        border: 1px solid var(--border, #e5e7eb); background: #fff; color: #6b7280;
        border-radius: 999px; padding: 4px 12px; font: inherit;
        font-size: 0.76rem; font-weight: 600; cursor: pointer;
    }
    .shn-tab:hover { background: #f5f5f7; }
    .shn-tab.active {
        background: var(--primary, #1a5c3a); border-color: var(--primary, #1a5c3a); color: #fff;
    }
    .shn-list { max-height: 420px; overflow-y: auto; overscroll-behavior: contain; }
    .shn-item {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 12px 16px; border-bottom: 1px solid #f5f5f7;
        text-decoration: none; color: inherit; cursor: pointer;
    }
    .shn-item:last-child { border-bottom: none; }
    .shn-item:hover { background: #f5f5f7; }
    .shn-item.unread { background: rgba(26,92,58,0.05); }
    .shn-item.unread:hover { background: rgba(26,92,58,0.09); }
    .shn-icon {
        width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem;
    }
    .shn-icon.ok     { background: #e3f1e9; color: #1a5c3a; }
    .shn-icon.warn   { background: #fef3c7; color: #b45309; }
    .shn-icon.info   { background: #eef2f6; color: #475569; }
    .shn-icon.notice { background: #fdeadb; color: #c2570c; }
    .shn-icon.bad    { background: #fee2e2; color: #b91c1c; }
    .shn-main { min-width: 0; flex: 1; }
    .shn-actor {
        font-size: 0.84rem; font-weight: 600; color: #1f2937;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .shn-msg {
        font-size: 0.8rem; color: #374151; line-height: 1.35; margin-top: 1px;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .shn-detail { font-size: 0.74rem; color: #6b7280; margin-top: 2px; }
    .shn-time { font-size: 0.72rem; color: #9ca3af; margin-top: 3px; }
    .shn-time.due { color: #b45309; font-weight: 600; }
    .shn-read {
        flex-shrink: 0; width: 24px; height: 24px; margin-top: 6px;
        border: none; background: none; border-radius: 50%; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        color: var(--primary, #1a5c3a);
    }
    .shn-read::before {
        content: ""; width: 8px; height: 8px; border-radius: 50%;
        background: var(--primary, #1a5c3a);
    }
    .shn-read i { display: none; font-size: 0.75rem; }
    .shn-read:hover { background: rgba(26,92,58,0.12); }
    .shn-read:hover::before { display: none; }
    .shn-read:hover i { display: block; }
    .shn-head-actions { display: flex; align-items: center; gap: 2px; }
    .shn-markall.shn-clear { color: #b91c1c; }
    .shn-markall.shn-clear:hover:not(:disabled) { background: #fef2f2; }
    .shn-markall.shn-clear:disabled { color: #9ca3af; }
    .shn-actions {
        flex-shrink: 0; display: flex; flex-direction: column;
        align-items: center; gap: 2px; margin-top: 2px;
    }
    .shn-actions .shn-read { margin-top: 0; }
    .shn-del {
        width: 24px; height: 24px; border: none; background: none; border-radius: 50%;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        color: #9ca3af; font-size: 0.75rem; opacity: 0; transition: opacity 0.12s ease;
    }
    .shn-item:hover .shn-del, .shn-del:focus-visible { opacity: 1; }
    .shn-del:hover { background: #fee2e2; color: #b91c1c; }
    @media (hover: none) { .shn-del { opacity: 1; } }
    .shn-state { padding: 34px 20px; text-align: center; color: #9ca3af; font-size: 0.8rem; }
    .shn-state i { display: block; font-size: 1.5rem; margin-bottom: 10px; color: #cbd5d1; }
    .shn-state strong { display: block; color: #374151; font-size: 0.88rem; margin-bottom: 4px; }
    .shn-retry {
        margin-top: 10px; border: 1px solid var(--border, #e5e7eb); background: #fff;
        border-radius: 8px; padding: 6px 12px; font: inherit; font-size: 0.78rem;
        font-weight: 600; color: #374151; cursor: pointer;
    }
    .shn-retry:hover { background: #f5f5f7; }
    @media (max-width: 600px) {
        /* Bell is not at the viewport edge on phones, so pin the panel to
           the screen instead of to the bell. */
        .shn { position: static; }
        .shn-panel {
            position: fixed;
            top: calc(var(--header-height, 64px) + 6px);
            left: 12px; right: 12px; width: auto; max-width: none;
        }
        .shn-list { max-height: calc(100vh - var(--header-height, 64px) - 150px); }
    }
    @media (prefers-reduced-motion: no-preference) {
        .shn.open .shn-panel { animation: shnIn 0.14s ease-out; }
        @keyframes shnIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }
    }

    /* =====================================================================
       MODERN ADMIN HEADER
       Scoped to .admin-top-header so the shared header.css (also used by the
       student and teacher headers) is left untouched.
    ===================================================================== */
    .top-header.admin-top-header {
        isolation: isolate;
        gap: 16px;
        padding: 0 28px;
        background: transparent;
        border-bottom: 1px solid rgba(20, 35, 28, 0.07);
        box-shadow: 0 8px 24px -18px rgba(16, 24, 20, 0.35);
    }
    /* The frosted-glass layer lives on a pseudo-element: a backdrop-filter on the
       header itself would trap the position:fixed mobile notification panel. */
    .top-header.admin-top-header::before {
        content: "";
        position: absolute;
        inset: 0;
        z-index: -1;
        background: rgba(255, 255, 255, 0.82);
        -webkit-backdrop-filter: saturate(1.6) blur(14px);
        backdrop-filter: saturate(1.6) blur(14px);
    }

    /* ---- Search ---- */
    .top-header.admin-top-header .header-search { flex: 0 1 440px; width: 100%; max-width: 440px; }
    .top-header.admin-top-header .header-search > i {
        left: 15px; font-size: 0.85rem; color: #8a9791; transition: color 0.15s ease; pointer-events: none;
    }
    .top-header.admin-top-header .header-search:focus-within > i { color: #1a5c3a; }
    .top-header.admin-top-header .header-search input {
        width: 100%; height: 42px; padding: 0 56px 0 42px;
        border: 1px solid transparent; border-radius: 12px;
        background: #f1f5f3; color: #14231c; box-shadow: none;
        font-family: inherit; font-size: 0.875rem; font-weight: 500;
        transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .top-header.admin-top-header .header-search input::placeholder { color: #8a9791; font-weight: 400; }
    .top-header.admin-top-header .header-search input:hover { background: #eaf0ed; }
    .top-header.admin-top-header .header-search input:focus {
        background: #fff; border-color: #2d7a52; box-shadow: 0 0 0 4px rgba(45, 122, 82, 0.14);
    }
    .hs-kbd {
        position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
        min-width: 22px; height: 22px; padding: 0 6px; border-radius: 6px;
        border: 1px solid #d9e1dd; border-bottom-width: 2px; background: #fff; color: #7b8982;
        font: 600 0.7rem/19px ui-monospace, SFMono-Regular, Consolas, Menlo, monospace;
        text-align: center; pointer-events: none; transition: opacity 0.15s ease;
    }
    .header-search:focus-within .hs-kbd { opacity: 0; }

    /* ---- Right-hand actions ---- */
    .top-header.admin-top-header .header-actions { display: flex; align-items: center; gap: 6px; margin: 0; }
    .top-header.admin-top-header .header-btn {
        width: 40px; height: 40px; border-radius: 12px; font-size: 1rem; color: #5b6a63;
        transition: background 0.15s ease, color 0.15s ease, transform 0.1s ease;
    }
    .top-header.admin-top-header .header-btn:hover { background: #eef3f0; color: #14231c; }
    .top-header.admin-top-header .header-btn:active { transform: scale(0.95); }
    .top-header.admin-top-header .header-btn:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(45, 122, 82, 0.28); }
    .top-header.admin-top-header .shn.open > .header-btn { background: #e3efe8; color: #1a5c3a; }
    .top-header.admin-top-header .shn-badge {
        top: 3px; left: auto; right: 1px; min-width: 18px; height: 18px; padding: 0 5px;
        border-radius: 999px; border: 2px solid #fff; font-size: 0.6rem; font-weight: 700;
        background: #ef4444; box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35);
    }
    .header-divider { width: 1px; height: 26px; margin: 0 8px; background: #e3e9e6; flex-shrink: 0; }

    /* ---- Profile chip ---- */
    .top-header.admin-top-header .ahp-profile-trigger {
        width: auto; height: 46px; gap: 10px; padding: 4px 10px 4px 5px;
        border-radius: 14px; border: 1px solid transparent; font-family: inherit; text-align: left;
    }
    .top-header.admin-top-header .ahp-profile-trigger:hover,
    .top-header.admin-top-header .ahp-profile.open .ahp-profile-trigger { background: #eef3f0; }
    .ahp-avatar {
        width: 36px; height: 36px; border-radius: 12px; font-size: 0.78rem; letter-spacing: 0.02em;
        background: linear-gradient(135deg, #2d7a52 0%, #124029 100%);
        box-shadow: 0 2px 6px rgba(26, 92, 58, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    }
    .ahp-avatar.ahp-avatar-lg { width: 40px; height: 40px; font-size: 0.85rem; }
    .ahp-user { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; min-width: 0; line-height: 1.15; }
    .ahp-user-name {
        max-width: 160px; font-size: 0.82rem; font-weight: 600; color: #14231c;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ahp-user-role { font-size: 0.7rem; font-weight: 500; color: #7b8982; text-transform: capitalize; }

    /* ---- Dropdown surfaces (search, notifications, profile) ---- */
    .admin-top-header .search-results-dropdown,
    .admin-top-header .shn-panel,
    .admin-top-header .ahp-profile-menu {
        border: 1px solid rgba(20, 35, 28, 0.08);
        border-radius: 16px;
        box-shadow: 0 24px 48px -16px rgba(16, 24, 20, 0.28), 0 4px 14px rgba(16, 24, 20, 0.06);
        top: calc(100% + 10px);
    }
    .admin-top-header .search-results-dropdown { width: 100%; min-width: 380px; padding: 6px; }
    .admin-top-header .search-group-label { padding: 10px 12px 4px; color: #8a9791; }
    .admin-top-header .search-result-item { padding: 8px 10px; border-radius: 10px; transition: background 0.12s ease; }
    .admin-top-header .search-result-item:hover { background: #eef6f1; }
    .admin-top-header .search-result-icon { width: 32px; height: 32px; border-radius: 10px; }
    .admin-top-header .search-result-sub { color: #7b8982; }

    .admin-top-header .ahp-profile-menu { width: 252px; padding: 8px; }
    .admin-top-header .ahp-profile-header {
        gap: 12px; padding: 12px; margin-bottom: 6px; border-bottom: 0; border-radius: 12px; background: #f4f8f6;
    }
    .admin-top-header .ahp-profile-header-name { font-size: 0.88rem; color: #14231c; }
    .admin-top-header .ahp-profile-header-role { color: #7b8982; }
    .admin-top-header .ahp-profile-item { padding: 10px 12px; border-radius: 10px; font-weight: 500; color: #2b3a33; transition: background 0.12s ease, color 0.12s ease; }
    .admin-top-header .ahp-profile-item i { color: #7b8982; }
    .admin-top-header .ahp-profile-item:hover { background: #eef6f1; color: #1a5c3a; }
    .admin-top-header .ahp-profile-item:hover i { color: #1a5c3a; }
    .admin-top-header .ahp-profile-item.ahp-logout { margin-top: 6px; border-top: 1px solid #f0f2f1; border-radius: 0 0 10px 10px; padding-top: 12px; }
    .admin-top-header .ahp-profile-item.ahp-logout:hover { background: #fef2f2; color: #b91c1c; }
    .admin-top-header .ahp-profile-item.ahp-logout:hover i { color: #b91c1c; }

    .admin-top-header .shn-head { padding: 16px 18px 10px; }
    .admin-top-header .shn-title { font-size: 1rem; letter-spacing: -0.01em; }
    .admin-top-header .shn-tabs { padding: 0 18px 12px; }
    .admin-top-header .shn-tab { padding: 5px 14px; border-color: transparent; background: #f1f5f3; }
    .admin-top-header .shn-tab:hover { background: #e6eeea; }
    .admin-top-header .shn-tab.active { background: #1a5c3a; color: #fff; }
    .admin-top-header .shn-icon { border-radius: 12px; }
    .admin-top-header .shn-item { padding: 12px 18px; }
    .admin-top-header .shn-item:hover { background: #f4f8f6; }

    @media (prefers-reduced-motion: no-preference) {
        .admin-top-header .ahp-profile.open .ahp-profile-menu,
        .admin-top-header .search-results-dropdown.open { animation: ahpIn 0.14s ease-out; }
        @keyframes ahpIn { from { opacity: 0; transform: translateY(-4px) scale(0.98); } to { opacity: 1; transform: none; } }
    }

    @media (max-width: 1024px) {
        .top-header.admin-top-header .ahp-user,
        .top-header.admin-top-header .ahp-profile-trigger .fa-chevron-down { display: none; }
        .top-header.admin-top-header .ahp-profile-trigger { padding: 4px; }
    }
    @media (max-width: 768px) {
        .top-header.admin-top-header { padding: 0 14px; gap: 8px; }
        .top-header.admin-top-header .header-search { flex: 1 1 auto; width: auto; max-width: none; }
        .hs-kbd, .header-divider { display: none; }
        .admin-top-header .search-results-dropdown { min-width: 0; }
    }
    @media (max-width: 480px) {
        .top-header.admin-top-header .sua-help-trigger { display: none; }
    }
</style>
<header class="top-header admin-top-header">
    <div class="header-search">
        <i class="fas fa-search"></i>
        <input type="text" id="globalSearchInput" placeholder="Search users, courses, subjects..." autocomplete="off" aria-label="Search users, courses, subjects" aria-keyshortcuts="/ Control+K Meta+K">
        <kbd class="hs-kbd" aria-hidden="true">/</kbd>
        <div id="searchResultsDropdown" class="search-results-dropdown"></div>
    </div>
    <div class="header-actions">
        <div class="shn" id="shnRoot">
            <button type="button" class="header-btn" id="shnBtn"
                    aria-haspopup="true" aria-expanded="false" aria-controls="shnPanel"
                    aria-label="Notifications" title="Notifications">
                <i class="fas fa-bell"></i>
                <span class="shn-badge" id="shnBadge" aria-hidden="true"></span>
            </button>
            <div class="shn-panel" id="shnPanel" role="dialog" aria-label="Notifications">
                <div class="shn-head">
                    <h3 class="shn-title">Notifications</h3>
                    <div class="shn-head-actions">
                        <button type="button" class="shn-markall" id="shnMarkAll" disabled>Mark all as read</button>
                        <button type="button" class="shn-markall shn-clear" id="shnClearAll" disabled>Clear all</button>
                    </div>
                </div>
                <div class="shn-tabs" role="tablist">
                    <button type="button" class="shn-tab active" data-tab="all" role="tab">All</button>
                    <button type="button" class="shn-tab" data-tab="unread" role="tab">Unread</button>
                </div>
                <div class="shn-list" id="shnList"></div>
            </div>
        </div>
        <button type="button" class="header-btn sua-help-trigger" id="suaHelpTrigger" aria-label="Help and about SUA IntelliLearn" title="Help and about SUA IntelliLearn">
            <i class="fas fa-question-circle"></i>
        </button>
        <?php include __DIR__ . '/help_center.php'; ?>
        <span class="header-divider" aria-hidden="true"></span>
        <div class="ahp-profile" id="ahpProfile">
            <button type="button"
                    class="header-btn ahp-profile-trigger"
                    title="<?php echo htmlspecialchars($userRole); ?>"
                    onclick="ahpToggleMenu()"
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-label="Account menu for <?php echo htmlspecialchars($displayName); ?>">
                <div class="header-avatar ahp-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <span class="ahp-user">
                    <span class="ahp-user-name"><?php echo htmlspecialchars($displayName); ?></span>
                    <?php if ($userRole !== ''): ?>
                    <span class="ahp-user-role"><?php echo htmlspecialchars($userRole); ?></span>
                    <?php endif; ?>
                </span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="ahp-profile-menu" id="ahpProfileMenu">
                <div class="ahp-profile-header">
                    <div class="header-avatar ahp-avatar ahp-avatar-lg"><?php echo htmlspecialchars($initials); ?></div>
                    <div class="ahp-profile-header-text">
                        <div class="ahp-profile-header-name"><?php echo htmlspecialchars($displayName); ?></div>
                        <div class="ahp-profile-header-role"><?php echo htmlspecialchars($userRole); ?></div>
                    </div>
                </div>
                <a href="/SUA-INTELLILEARN/public/admin/profile.php" class="ahp-profile-item">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </a>
                <a href="/SUA-INTELLILEARN/public/logout.php" class="ahp-profile-item ahp-logout">
                    <i class="fas fa-arrow-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>
<script>
(function () {
    function ahpEl() { return document.getElementById('ahpProfile'); }

    window.ahpToggleMenu = function () {
        var el = ahpEl();
        var trigger = el.querySelector('.ahp-profile-trigger');
        var isOpen = el.classList.toggle('open');
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    function ahpClose() {
        var el = ahpEl();
        if (!el) return;
        el.classList.remove('open');
        el.querySelector('.ahp-profile-trigger').setAttribute('aria-expanded', 'false');
    }

    // Close when clicking outside the dropdown.
    document.addEventListener('click', function (e) {
        var el = ahpEl();
        if (el && !el.contains(e.target)) ahpClose();
    });

    // Close on Escape.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') ahpClose();
    });

    // "/" or Ctrl/Cmd + K focuses the global search box.
    document.addEventListener('keydown', function (e) {
        var input = document.getElementById('globalSearchInput');
        if (!input) return;
        var tag = (e.target && e.target.tagName || '').toLowerCase();
        var typing = tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable);
        var combo = (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k';
        if (combo || (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey && !e.altKey)) {
            e.preventDefault();
            input.focus();
            input.select();
        }
    });
})();
</script>

<script>
(function () {
    var ENDPOINT = '/SUA-INTELLILEARN/public/admin/assests/api/notifications.php';
    // "Read" state lives in this browser only (no database table).
    var STORE_KEY = 'sua_notif_read_<?php echo (int) ($_SESSION['user_id'] ?? 0); ?>';
    // Deleted (dismissed) notifications are also remembered per browser.
    var DEL_KEY = 'sua_notif_deleted_<?php echo (int) ($_SESSION['user_id'] ?? 0); ?>';
    var POLL_MS = 60000;

    var root = document.getElementById('shnRoot');
    if (!root) return;
    var btn = document.getElementById('shnBtn');
    var badge = document.getElementById('shnBadge');
    var list = document.getElementById('shnList');
    var markAll = document.getElementById('shnMarkAll');
    var clearAll = document.getElementById('shnClearAll');
    var tabs = root.querySelectorAll('.shn-tab');

    var TYPES = {
        new_users:    { icon: 'fa-user-plus',         tone: 'ok' },
        announcement: { icon: 'fa-bullhorn',          tone: 'notice' },
        eval_open:    { icon: 'fa-clipboard-list',    tone: 'info' },
        eval_soon:    { icon: 'fa-hourglass-half',    tone: 'warn' },
        eval_closed:  { icon: 'fa-clipboard-check',   tone: 'ok' },
        quiz_failed:  { icon: 'fa-triangle-exclamation', tone: 'bad' }
    };

    var state = { items: [], read: loadRead(), deleted: loadDeleted(), tab: 'all', loaded: false, error: false };

    function loadRead() {
        try { return JSON.parse(localStorage.getItem(STORE_KEY) || '{}') || {}; }
        catch (e) { return {}; }
    }
    function loadDeleted() {
        try { return JSON.parse(localStorage.getItem(DEL_KEY) || '{}') || {}; }
        catch (e) { return {}; }
    }
    function saveDeleted() {
        try { localStorage.setItem(DEL_KEY, JSON.stringify(state.deleted)); } catch (e) {}
    }
    function saveRead() {
        try { localStorage.setItem(STORE_KEY, JSON.stringify(state.read)); } catch (e) {}
    }
    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function unreadCount() {
        return state.items.filter(function (n) { return !state.read[n.id]; }).length;
    }

    function renderBadge() {
        var n = unreadCount();
        badge.textContent = n > 9 ? '9+' : String(n);
        badge.classList.toggle('show', n > 0);
        btn.setAttribute('aria-label', n > 0 ? 'Notifications, ' + n + ' unread' : 'Notifications');
        markAll.disabled = n === 0;
        clearAll.disabled = state.items.length === 0;
    }

    function render() {
        renderBadge();
        if (!state.loaded) {
            list.innerHTML = state.error
                ? '<div class="shn-state"><i class="fas fa-triangle-exclamation"></i><strong>Couldn\'t load notifications</strong>Check your connection and try again.<br><button type="button" class="shn-retry" id="shnRetry">Try again</button></div>'
                : '<div class="shn-state">Loading…</div>';
            return;
        }
        var shown = state.tab === 'unread'
            ? state.items.filter(function (n) { return !state.read[n.id]; })
            : state.items;

        if (!shown.length) {
            list.innerHTML = state.tab === 'unread' && state.items.length
                ? '<div class="shn-state"><i class="fas fa-check-double"></i><strong>You\'re all caught up</strong>No unread notifications.</div>'
                : '<div class="shn-state"><i class="fas fa-bell-slash"></i><strong>No notifications yet</strong>New accounts, announcements, evaluation rounds and failed AI quiz runs will show up here.</div>';
            return;
        }

        list.innerHTML = shown.map(function (n) {
            var t = TYPES[n.type] || TYPES.announcement;
            var unread = !state.read[n.id];
            return '<a class="shn-item' + (unread ? ' unread' : '') + '" href="' + esc(n.url) + '" data-id="' + esc(n.id) + '">'
                + '<span class="shn-icon ' + t.tone + '"><i class="fas ' + t.icon + '"></i></span>'
                + '<span class="shn-main">'
                +   '<div class="shn-actor">' + esc(n.actor) + '</div>'
                +   '<div class="shn-msg">' + esc(n.message) + '</div>'
                +   (n.detail ? '<div class="shn-detail">' + esc(n.detail) + '</div>' : '')
                +   '<div class="shn-time' + (n.type === 'eval_soon' ? ' due' : '') + '">' + esc(n.time_label) + '</div>'
                + '</span>'
                + '<span class="shn-actions">'
                +   (unread ? '<button type="button" class="shn-read" data-read="' + esc(n.id) + '" title="Mark as read" aria-label="Mark as read"><i class="fas fa-check"></i></button>' : '')
                +   '<button type="button" class="shn-del" data-del="' + esc(n.id) + '" title="Delete" aria-label="Delete notification"><i class="fas fa-xmark"></i></button>'
                + '</span>'
                + '</a>';
        }).join('');
    }

    function load() {
        return fetch(ENDPOINT, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (!r.ok) throw new Error('http ' + r.status);
                return r.json();
            })
            .then(function (d) {
                if (!d || !d.success) throw new Error('bad payload');
                var all = d.notifications || [];
                state.loaded = true;
                state.error = false;
                // Forget read/deleted marks for notifications that aged out of the feed.
                var live = {};
                all.forEach(function (n) { live[n.id] = true; });
                Object.keys(state.read).forEach(function (id) { if (!live[id]) delete state.read[id]; });
                Object.keys(state.deleted).forEach(function (id) { if (!live[id]) delete state.deleted[id]; });
                saveRead();
                saveDeleted();
                state.items = all.filter(function (n) { return !state.deleted[n.id]; });
                render();
            })
            .catch(function () {
                if (!state.loaded) state.error = true;
                render();
            });
    }

    function setOpen(open) {
        root.classList.toggle('open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            var profile = document.getElementById('ahpProfile');
            if (profile) profile.classList.remove('open');
            render();
            load();
        }
    }

    btn.addEventListener('click', function () { setOpen(!root.classList.contains('open')); });

    markAll.addEventListener('click', function () {
        state.items.forEach(function (n) { state.read[n.id] = 1; });
        saveRead();
        render();
    });

    clearAll.addEventListener('click', function () {
        if (!state.items.length) return;
        if (!window.confirm('Delete all notifications? This cannot be undone.')) return;
        state.items.forEach(function (n) { state.deleted[n.id] = 1; });
        state.items = [];
        saveDeleted();
        render();
    });

    Array.prototype.forEach.call(tabs, function (tab) {
        tab.addEventListener('click', function () {
            state.tab = tab.getAttribute('data-tab');
            Array.prototype.forEach.call(tabs, function (t) { t.classList.toggle('active', t === tab); });
            render();
        });
    });

    list.addEventListener('click', function (e) {
        if (e.target.closest('#shnRetry')) { load(); return; }

        var delBtn = e.target.closest('.shn-del');
        if (delBtn) {
            e.preventDefault();
            e.stopPropagation();
            var delId = delBtn.getAttribute('data-del');
            state.deleted[delId] = 1;
            state.items = state.items.filter(function (n) { return n.id !== delId; });
            saveDeleted();
            render();
            return;
        }

        var readBtn = e.target.closest('.shn-read');
        if (readBtn) {
            e.preventDefault();
            e.stopPropagation();
            state.read[readBtn.getAttribute('data-read')] = 1;
            saveRead();
            render();
            return;
        }
        // Opening a notification marks it read, then follows the link.
        var item = e.target.closest('.shn-item');
        if (item) {
            state.read[item.getAttribute('data-id')] = 1;
            saveRead();
        }
    });

    document.addEventListener('click', function (e) {
        if (!root.contains(e.target)) setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && root.classList.contains('open')) { setOpen(false); btn.focus(); }
    });

    // Keep the unread badge fresh while the tab is visible.
    setInterval(function () { if (!document.hidden) load(); }, POLL_MS);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) load(); });

    render();
    load();
})();
</script>
