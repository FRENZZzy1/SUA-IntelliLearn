function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }

        // Active Nav State
        function setActive(el) {
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            el.classList.add('active');
        }

        // Toast Notification
        function showToast(message) {
            const toast = document.getElementById('toast');
            const msg = document.getElementById('toast-msg');
            msg.textContent = message;
            toast.style.transform = 'translateY(0)';
            toast.style.opacity = '1';
            setTimeout(() => {
                toast.style.transform = 'translateY(100px)';
                toast.style.opacity = '0';
            }, 2500);
        }

        // Animate progress bars on load
        window.addEventListener('load', () => {
            document.querySelectorAll('.progress-bar-fill').forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 300);
            });
        });

        // ================= SEARCH RESULT DETAIL MODAL =================
        // Builds/injects the modal markup + styles once, then exposes
        // window.showSearchDetailModal(type, data) to populate + open it.
        (function () {
            let modalEl = null;

            function injectStyles() {
                if (document.getElementById('searchDetailModalStyles')) return;
                const style = document.createElement('style');
                style.id = 'searchDetailModalStyles';
                style.textContent = `
                    .search-detail-overlay {
                        display: none;
                        position: fixed;
                        inset: 0;
                        background: rgba(15, 23, 42, 0.55);
                        z-index: 1000;
                        align-items: center;
                        justify-content: center;
                    }
                    .search-detail-overlay.open { display: flex; }
                    .search-detail-card {
                        background: var(--card-bg, #fff);
                        border-radius: 14px;
                        width: min(420px, 92vw);
                        max-height: 85vh;
                        overflow-y: auto;
                        box-shadow: 0 20px 50px rgba(0,0,0,0.25);
                        animation: searchDetailPop .15s ease-out;
                    }
                    @keyframes searchDetailPop {
                        from { transform: scale(.96); opacity: 0; }
                        to { transform: scale(1); opacity: 1; }
                    }
                    .search-detail-header {
                        display: flex;
                        align-items: center;
                        gap: 14px;
                        padding: 20px 22px 14px;
                        border-bottom: 1px solid rgba(0,0,0,0.08);
                    }
                    .search-detail-icon {
                        width: 46px; height: 46px;
                        border-radius: 50%;
                        display: flex; align-items: center; justify-content: center;
                        color: #fff; font-weight: 600; font-size: 15px;
                        flex-shrink: 0;
                    }
                    .search-detail-title { font-size: 16px; font-weight: 600; margin: 0; }
                    .search-detail-subtitle { font-size: 13px; color: var(--text-muted, #6b7280); margin: 2px 0 0; }
                    .search-detail-close {
                        margin-left: auto;
                        background: none; border: none; cursor: pointer;
                        font-size: 16px; color: var(--text-muted, #6b7280);
                        padding: 6px;
                    }
                    .search-detail-body { padding: 18px 22px 22px; }
                    .search-detail-row {
                        display: flex;
                        justify-content: space-between;
                        gap: 12px;
                        padding: 9px 0;
                        border-bottom: 1px dashed rgba(0,0,0,0.08);
                        font-size: 13.5px;
                    }
                    .search-detail-row:last-child { border-bottom: none; }
                    .search-detail-label { color: var(--text-muted, #6b7280); }
                    .search-detail-value { font-weight: 500; text-align: right; }
                `;
                document.head.appendChild(style);
            }

            function injectMarkup() {
                if (document.getElementById('searchDetailOverlay')) {
                    modalEl = document.getElementById('searchDetailOverlay');
                    return;
                }
                const overlay = document.createElement('div');
                overlay.id = 'searchDetailOverlay';
                overlay.className = 'search-detail-overlay';
                overlay.innerHTML = `
                    <div class="search-detail-card" role="dialog" aria-modal="true">
                        <div class="search-detail-header">
                            <div class="search-detail-icon" id="searchDetailIcon"></div>
                            <div>
                                <p class="search-detail-title" id="searchDetailTitle"></p>
                                <p class="search-detail-subtitle" id="searchDetailSubtitle"></p>
                            </div>
                            <button class="search-detail-close" id="searchDetailClose"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="search-detail-body" id="searchDetailBody"></div>
                    </div>`;
                document.body.appendChild(overlay);
                modalEl = overlay;

                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) closeDetailModal();
                });
                overlay.querySelector('#searchDetailClose').addEventListener('click', closeDetailModal);
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') closeDetailModal();
                });
            }

            function closeDetailModal() {
                if (modalEl) modalEl.classList.remove('open');
            }

            function escapeHtml(str) {
                return String(str ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                }[c]));
            }

            function initialsOf(name) {
                const parts = String(name || '').trim().split(/\s+/).slice(0, 2);
                return parts.map(p => p.charAt(0).toUpperCase()).join('') || '?';
            }

            const ROLE_COLORS = { admin: '#8b5cf6', teacher: 'var(--info)', student: 'var(--success)' };

            function buildRows(rows) {
                return rows
                    .filter(r => r.value !== null && r.value !== undefined && r.value !== '')
                    .map(r => `
                        <div class="search-detail-row">
                            <span class="search-detail-label">${escapeHtml(r.label)}</span>
                            <span class="search-detail-value">${escapeHtml(r.value)}</span>
                        </div>`).join('');
            }

            function showDetailModal(type, data) {
                injectStyles();
                injectMarkup();

                const icon = document.getElementById('searchDetailIcon');
                const title = document.getElementById('searchDetailTitle');
                const subtitle = document.getElementById('searchDetailSubtitle');
                const body = document.getElementById('searchDetailBody');

                if (type === 'user') {
                    const color = ROLE_COLORS[data.role] || '#8b5cf6';
                    icon.style.background = color;
                    icon.innerHTML = escapeHtml(initialsOf(data.full_name));
                    title.textContent = data.full_name || data.username || 'User';
                    subtitle.textContent = `${data.role || ''}${data.status ? ' · ' + data.status : ''}`;
                    body.innerHTML = buildRows([
                        { label: 'Username', value: data.username },
                        { label: 'Email', value: data.email },
                        { label: 'Role', value: data.role },
                        { label: 'Status', value: data.status },
                        { label: 'User ID', value: data.id },
                    ]);
                } else if (type === 'course') {
                    icon.style.background = 'var(--warning)';
                    icon.innerHTML = '<i class="fas fa-book-open"></i>';
                    title.textContent = `${data.subject_name || ''} — ${data.section_name || ''}`;
                    subtitle.textContent = `Grade ${data.grade_level || ''}`;
                    body.innerHTML = buildRows([
                        { label: 'Teacher', value: data.teacher_name },
                        { label: 'Section', value: data.section_name },
                        { label: 'Grade level', value: data.grade_level },
                        { label: 'Quarter', value: data.quarter },
                        { label: 'Capacity', value: data.capacity },
                        { label: 'Status', value: data.status },
                        { label: 'Offering ID', value: data.offering_id },
                    ]);
                } else if (type === 'subject') {
                    icon.style.background = 'var(--success)';
                    icon.innerHTML = '<i class="fas fa-layer-group"></i>';
                    title.textContent = data.subject_name || 'Subject';
                    subtitle.textContent = '';
                    body.innerHTML = buildRows([
                        { label: 'Description', value: data.description },
                        { label: 'Subject ID', value: data.subject_id },
                    ]);
                }

                modalEl.classList.add('open');
            }

            window.showSearchDetailModal = showDetailModal;
            window.closeSearchDetailModal = closeDetailModal;
        })();

        // ================= GLOBAL SEARCH (users / courses / subjects) =================
        (function () {
            const input = document.getElementById('globalSearchInput');
            const dropdown = document.getElementById('searchResultsDropdown');
            if (!input || !dropdown) return;

            // Cache of the last rendered results, keyed by "type-id",
            // so a click can show full details without another request.
            const resultsCache = new Map();

            // Path is relative to the *page* (dashboard.php), which already
            // requires 'assests/api/dashboard_functions.php', so the search
            // endpoint lives right next to it.
            const SEARCH_ENDPOINT = 'assests/api/search.php';
            const MIN_CHARS = 2;
            const DEBOUNCE_MS = 300;

            let debounceTimer = null;
            let activeController = null; // to cancel stale in-flight requests
            let lastQuery = '';

            function escapeHtml(str) {
                return String(str ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                }[c]));
            }

            function initialsOf(name) {
                const parts = String(name || '').trim().split(/\s+/).slice(0, 2);
                return parts.map(p => p.charAt(0).toUpperCase()).join('') || '?';
            }

            const ROLE_COLORS = { admin: '#8b5cf6', teacher: 'var(--info)', student: 'var(--success)' };

            function openDropdown() { dropdown.classList.add('open'); }
            function closeDropdown() { dropdown.classList.remove('open'); }

            function renderLoading() {
                dropdown.innerHTML = '<div class="search-loading-state"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
                openDropdown();
            }

            function renderEmpty() {
                dropdown.innerHTML = '<div class="search-empty-state">No matching users, courses, or subjects found.</div>';
                openDropdown();
            }

            function renderResults(data) {
                const users = data.users || [];
                const courses = data.courses || [];
                const subjects = data.subjects || [];

                if (!users.length && !courses.length && !subjects.length) {
                    renderEmpty();
                    return;
                }

                let html = '';
                resultsCache.clear();

                if (users.length) {
                    html += '<div class="search-group-label">Users</div>';
                    users.forEach(u => {
                        resultsCache.set(`user-${u.id}`, u);
                        const color = ROLE_COLORS[u.role] || '#8b5cf6';
                        html += `
                            <div class="search-result-item" data-type="user" data-id="${escapeHtml(u.id)}">
                                <div class="search-result-icon" style="background:${color};">${escapeHtml(initialsOf(u.full_name))}</div>
                                <div class="search-result-main">
                                    <span class="search-result-title">${escapeHtml(u.full_name)}</span>
                                    <span class="search-result-sub">${escapeHtml(u.role)} · ${escapeHtml(u.email || u.username)}</span>
                                </div>
                            </div>`;
                    });
                }

                if (courses.length) {
                    html += '<div class="search-group-label">Courses</div>';
                    courses.forEach(c => {
                        resultsCache.set(`course-${c.offering_id}`, c);
                        html += `
                            <div class="search-result-item" data-type="course" data-id="${escapeHtml(c.offering_id)}">
                                <div class="search-result-icon" style="background:var(--warning);"><i class="fas fa-book-open"></i></div>
                                <div class="search-result-main">
                                    <span class="search-result-title">${escapeHtml(c.subject_name)} — ${escapeHtml(c.section_name)}</span>
                                    <span class="search-result-sub">Grade ${escapeHtml(c.grade_level)} · ${escapeHtml(c.teacher_name)}</span>
                                </div>
                            </div>`;
                    });
                }

                if (subjects.length) {
                    html += '<div class="search-group-label">Subjects</div>';
                    subjects.forEach(s => {
                        resultsCache.set(`subject-${s.subject_id}`, s);
                        html += `
                            <div class="search-result-item" data-type="subject" data-id="${escapeHtml(s.subject_id)}">
                                <div class="search-result-icon" style="background:var(--success);"><i class="fas fa-layer-group"></i></div>
                                <div class="search-result-main">
                                    <span class="search-result-title">${escapeHtml(s.subject_name)}</span>
                                    <span class="search-result-sub">${escapeHtml(s.description || '')}</span>
                                </div>
                            </div>`;
                    });
                }

                dropdown.innerHTML = html;
                openDropdown();
            }

            async function runSearch(term) {
                if (activeController) activeController.abort();
                activeController = new AbortController();

                renderLoading();

                try {
                    const res = await fetch(`${SEARCH_ENDPOINT}?q=${encodeURIComponent(term)}`, {
                        signal: activeController.signal,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    if (!res.ok) {
                        dropdown.innerHTML = '<div class="search-empty-state">Search is unavailable right now.</div>';
                        openDropdown();
                        return;
                    }

                    const data = await res.json();
                    renderResults(data);
                } catch (err) {
                    if (err.name === 'AbortError') return; // superseded by a newer keystroke
                    dropdown.innerHTML = '<div class="search-empty-state">Search is unavailable right now.</div>';
                    openDropdown();
                }
            }

            input.addEventListener('input', () => {
                const term = input.value.trim();
                lastQuery = term;
                clearTimeout(debounceTimer);

                if (term.length < MIN_CHARS) {
                    closeDropdown();
                    return;
                }

                debounceTimer = setTimeout(() => {
                    // Guard against races if the user kept typing
                    if (input.value.trim() === term) runSearch(term);
                }, DEBOUNCE_MS);
            });

            input.addEventListener('focus', () => {
                if (lastQuery.length >= MIN_CHARS && dropdown.innerHTML) openDropdown();
            });

            // Click a result -> open the detail popup for that item.
            dropdown.addEventListener('click', (e) => {
                const item = e.target.closest('.search-result-item');
                if (!item) return;

                const { type, id } = item.dataset;
                const data = resultsCache.get(`${type}-${id}`);

                if (data && typeof window.showSearchDetailModal === 'function') {
                    window.showSearchDetailModal(type, data);
                } else {
                    // Fallback if cache missed for some reason.
                    const label = item.querySelector('.search-result-title')?.textContent || '';
                    showToast(`Selected ${type}: ${label}`);
                }
                closeDropdown();
            });

            // Close dropdown when clicking anywhere outside the search bar.
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.header-search')) closeDropdown();
            });

            // Close on Escape.
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeDropdown();
            });
        })();