/* Sidebar behaviour: desktop collapse (remembered across pages) + mobile drawer. */
(function () {
    'use strict';
    var KEY = 'sua_sidebar_collapsed';
    var BREAKPOINT = 768;

    var sidebar   = document.getElementById('sidebar');
    if (!sidebar) return;
    var overlay   = document.getElementById('sidebarOverlay');
    var mobileBtn = document.getElementById('mobileMenuBtn');
    var toggleBtn = document.getElementById('sidebarToggle');
    var root      = document.documentElement;

    function isMobile() { return window.innerWidth <= BREAKPOINT; }
    function readSaved() { try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; } }
    function save(v) { try { localStorage.setItem(KEY, v ? '1' : '0'); } catch (e) {} }

    /* Native tooltips on the icons while the rail is collapsed */
    function syncTooltips() {
        var collapsed = sidebar.classList.contains('collapsed') && !isMobile();
        sidebar.querySelectorAll('.nav-item').forEach(function (a) {
            var label = a.querySelector('.nav-label');
            if (!label) return;
            if (collapsed) a.setAttribute('title', label.textContent.trim());
            else a.removeAttribute('title');
        });
    }

    function applyCollapsed(collapsed) {
        sidebar.classList.toggle('collapsed', collapsed);
        root.classList.toggle('sidebar-collapsed', collapsed);   /* hook for page CSS */
        if (toggleBtn) {
            var text = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
            toggleBtn.setAttribute('aria-expanded', String(!collapsed));
            toggleBtn.setAttribute('aria-label', text);
            toggleBtn.setAttribute('title', text);
        }
        syncTooltips();
    }

    /* Restore the saved state without animating on page load */
    var saved = readSaved();
    if (saved) {
        var guard = document.createElement('style');
        guard.id = 'suaSidebarNoAnim';
        guard.textContent = '.sidebar, .sidebar *, .main-content, .top-header { transition: none !important; }';
        document.head.appendChild(guard);
        window.addEventListener('load', function () {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    var g = document.getElementById('suaSidebarNoAnim');
                    if (g) g.remove();
                });
            });
        });
    }
    applyCollapsed(saved);

    /* ---------- Mobile drawer ---------- */
    function setMobileOpen(open) {
        sidebar.classList.toggle('mobile-open', open);
        if (overlay) overlay.classList.toggle('active', open);
        document.body.classList.toggle('sidebar-locked', open);
        if (mobileBtn) {
            mobileBtn.innerHTML = open ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
            mobileBtn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
            mobileBtn.setAttribute('aria-expanded', String(open));
        }
    }
    window.closeMobileSidebar  = function () { setMobileOpen(false); };
    window.toggleMobileSidebar = function () { setMobileOpen(!sidebar.classList.contains('mobile-open')); };

    /* Desktop collapse / mobile drawer dispatcher */
    window.toggleSidebar = window.suaToggleSidebar = function () {
        if (isMobile()) { window.toggleMobileSidebar(); return; }
        var collapsed = !sidebar.classList.contains('collapsed');
        applyCollapsed(collapsed);
        save(collapsed);
    };

    if (toggleBtn) toggleBtn.addEventListener('click', window.toggleSidebar);

    /* Close drawer when a link is tapped */
    sidebar.querySelectorAll('.nav-item').forEach(function (link) {
        link.addEventListener('click', function () { if (isMobile()) setMobileOpen(false); });
    });

    /* ESC closes the drawer */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setMobileOpen(false);
    });

    /* Swipe left to close */
    var touchStartX = 0;
    sidebar.addEventListener('touchstart', function (e) {
        touchStartX = e.touches[0].clientX;
    }, { passive: true });
    sidebar.addEventListener('touchend', function (e) {
        if (!sidebar.classList.contains('mobile-open')) return;
        if (touchStartX - e.changedTouches[0].clientX > 60) setMobileOpen(false);
    }, { passive: true });

    /* Leaving mobile width: close the drawer and refresh tooltips */
    window.addEventListener('resize', function () {
        if (!isMobile()) setMobileOpen(false);
        syncTooltips();
    });
})();
