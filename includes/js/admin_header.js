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

(function () {
    var ENDPOINT = '/SUA-INTELLILEARN/public/admin/assests/api/notifications.php';
    // "Read" state lives in this browser only (no database table).
    var USER_ID = parseInt((document.getElementById('shnRoot') || document.body).getAttribute('data-user-id'), 10) || 0;
    var STORE_KEY = 'sua_notif_read_' + USER_ID;
    // Deleted (dismissed) notifications are also remembered per browser.
    var DEL_KEY = 'sua_notif_deleted_' + USER_ID;
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
