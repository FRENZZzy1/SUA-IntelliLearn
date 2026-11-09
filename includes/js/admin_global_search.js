/* Shared admin global search. Loaded from includes/admin_header.php. */
(function () {
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
})();

(function () {
 const input=document.getElementById('globalSearchInput');
 const dropdown=document.getElementById('searchResultsDropdown');
 if(!input||!dropdown)return;
 const endpoint=dropdown.getAttribute('data-search-endpoint');
 if(!endpoint)return;
 const cache=new Map(); let timer=null, controller=null, lastQuery='';
 const minChars=2;
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const initials=s=>String(s||'').trim().split(/\s+/).slice(0,2).map(x=>x[0].toUpperCase()).join('')||'?';
 const colors={admin:'#8b5cf6',teacher:'var(--info)',student:'var(--success)'};
 const open=()=>dropdown.classList.add('open'), close=()=>dropdown.classList.remove('open');
 function message(html,cls){dropdown.innerHTML='<div class="'+cls+'">'+html+'</div>';open();}
 function render(data){
  const users=data.users||[],courses=data.courses||[],subjects=data.subjects||[];
  if(!users.length&&!courses.length&&!subjects.length){message('No matching users, courses, or subjects found.','search-empty-state');return;}
  let html='';cache.clear();
  if(users.length){html+='<div class="search-group-label">Users</div>';users.forEach(u=>{cache.set('user-'+u.id,u);html+='<div class="search-result-item" data-type="user" data-id="'+esc(u.id)+'"><div class="search-result-icon" style="background:'+(colors[u.role]||'#8b5cf6')+';">'+esc(initials(u.full_name))+'</div><div class="search-result-main"><span class="search-result-title">'+esc(u.full_name)+'</span><span class="search-result-sub">'+esc(u.role)+' · '+esc(u.email||u.username)+'</span></div></div>';});}
  if(courses.length){html+='<div class="search-group-label">Courses</div>';courses.forEach(c=>{cache.set('course-'+c.offering_id,c);html+='<div class="search-result-item" data-type="course" data-id="'+esc(c.offering_id)+'"><div class="search-result-icon" style="background:var(--warning);"><i class="fas fa-book-open"></i></div><div class="search-result-main"><span class="search-result-title">'+esc(c.subject_name)+' — '+esc(c.section_name)+'</span><span class="search-result-sub">Grade '+esc(c.grade_level)+' · '+esc(c.teacher_name)+'</span></div></div>';});}
  if(subjects.length){html+='<div class="search-group-label">Subjects</div>';subjects.forEach(s=>{cache.set('subject-'+s.subject_id,s);html+='<div class="search-result-item" data-type="subject" data-id="'+esc(s.subject_id)+'"><div class="search-result-icon" style="background:var(--success);"><i class="fas fa-layer-group"></i></div><div class="search-result-main"><span class="search-result-title">'+esc(s.subject_name)+'</span><span class="search-result-sub">'+esc(s.description||'')+'</span></div></div>';});}
  dropdown.innerHTML=html;open();
 }
 async function search(term){
  if(controller)controller.abort();controller=new AbortController();message('<i class="fas fa-spinner fa-spin"></i> Searching...','search-loading-state');
  try{const r=await fetch(endpoint+'?q='+encodeURIComponent(term),{signal:controller.signal,credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});if(!r.ok)throw new Error('HTTP '+r.status);const data=await r.json();if(data.error)throw new Error(data.error);render(data);}
  catch(e){if(e.name==='AbortError')return;console.error('Admin global search:',e);message('Search is unavailable right now. Please try again.','search-empty-state');}
 }
 input.addEventListener('input',()=>{const term=input.value.trim();lastQuery=term;clearTimeout(timer);if(term.length<minChars){close();return;}timer=setTimeout(()=>{if(input.value.trim()===term)search(term);},300);});
 input.addEventListener('focus',()=>{if(lastQuery.length>=minChars&&dropdown.innerHTML.trim())open();});
 dropdown.addEventListener('click',e=>{const item=e.target.closest('.search-result-item');if(!item)return;const data=cache.get(item.dataset.type+'-'+item.dataset.id);if(data&&typeof window.showSearchDetailModal==='function')window.showSearchDetailModal(item.dataset.type,data);close();});
 document.addEventListener('click',e=>{if(!e.target.closest('.header-search'))close();});
 input.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
})();
