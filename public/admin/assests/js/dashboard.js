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

        // ================= GLOBAL SEARCH (users / courses / subjects) =================
        (function () {
            const input = document.getElementById('globalSearchInput');
            const dropdown = document.getElementById('searchResultsDropdown');
            if (!input || !dropdown) return;

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

                if (users.length) {
                    html += '<div class="search-group-label">Users</div>';
                    users.forEach(u => {
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

            // Click a result -> hook this up to your actual detail/edit pages.
            dropdown.addEventListener('click', (e) => {
                const item = e.target.closest('.search-result-item');
                if (!item) return;

                const { type, id } = item.dataset;
                const label = item.querySelector('.search-result-title')?.textContent || '';

                // TODO: replace with real navigation once routes exist, e.g.:
                //   if (type === 'user') window.location.href = `users.php?id=${id}`;
                //   if (type === 'course') window.location.href = `courses.php?offering_id=${id}`;
                //   if (type === 'subject') window.location.href = `subjects.php?id=${id}`;
                showToast(`Selected ${type}: ${label}`);
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