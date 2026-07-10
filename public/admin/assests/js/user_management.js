 // ---- Sidebar collapse/expand ----
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    }

    function setActive(el) {
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        el.classList.add('active');
    }

    // ---- Add User panel ----
    function toggleAddUser() {
        const panel = document.getElementById('addUserPanel');
        panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
        if (panel.style.display === 'block') {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // ---- Role pill selector ----
    function setRole(el) {
    document.querySelectorAll('.role-pill').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    const role = el.dataset.role;
    document.getElementById('roleInput').value = role;

    const studentFields = document.getElementById('studentFields');
    const staffFields = document.getElementById('staffFields');
    const isStudent = role === 'student';

    // Show/hide the field containers
    studentFields.style.display = isStudent ? 'block' : 'none';
    staffFields.style.display = isStudent ? 'none' : 'block';

    // Handle student fields: enable when student, disable when staff
    studentFields.querySelectorAll('input, select, textarea').forEach(field => {
        field.disabled = !isStudent;
    });
    studentFields.querySelectorAll('[data-student-required]').forEach(el => {
        el.required = isStudent;
    });

    // Handle staff fields: enable when staff, disable when student
    staffFields.querySelectorAll('input, select, textarea').forEach(field => {
        field.disabled = isStudent;
    });
    staffFields.querySelectorAll('[data-staff-required]').forEach(el => {
        el.required = !isStudent;
    });
}

    // ---- Status pill selector ----
    function setStatus(el) {
        document.querySelectorAll('.status-pill').forEach(p => p.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('statusInput').value = el.dataset.status;
    }

    // Initialize required attributes on load (default role = student)
    document.addEventListener('DOMContentLoaded', () => {
    // Default role is student — student fields enabled, staff fields disabled
    document.querySelectorAll('#studentFields input, #studentFields select, #studentFields textarea').forEach(f => f.disabled = false);
    document.querySelectorAll('#studentFields [data-student-required]').forEach(el => el.required = true);
    
    document.querySelectorAll('#staffFields input, #staffFields select, #staffFields textarea').forEach(f => f.disabled = true);
    document.querySelectorAll('#staffFields [data-staff-required]').forEach(el => el.required = false);
});

    // ---- Edit User ----
    function editUser(userId) {
        const card = document.querySelector(`.user-card[data-user-id="${userId}"]`);
        if (!card) return;

        document.getElementById('editUserId').value = userId;
        document.getElementById('editFullname').value = card.dataset.fullname;
        document.getElementById('editEmail').value = card.dataset.email;
        document.getElementById('editRoleInput').value = card.dataset.role;
        document.getElementById('editDepartment').value = card.dataset.department;
        document.getElementById('editContact').value = '';
        document.getElementById('editStatus').value = card.dataset.status;
        document.getElementById('editNotes').value = card.dataset.notes;

        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // ---- View User ----
    function viewUser(userId) {
        const card = document.querySelector(`.user-card[data-user-id="${userId}"]`);
        if (!card) return;

        const roleColors = { admin: '#8B5CF6', teacher: '#1F6F54', student: '#2F9C74' };
        const roleLabels = { admin: 'Admin', teacher: 'Teacher', student: 'Student' };
        const statusLabels = { active: 'Active', inactive: 'Pending', suspended: 'Suspended' };

        const initials = card.querySelector('.user-avatar').textContent.trim();
        const color = roleColors[card.dataset.role] || '#6B7280';

        let extraRows = '';
        if (card.dataset.role === 'student') {
            extraRows = `
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-id-card"></i> LRN</span>
                    <span class="detail-value">${card.dataset.lrn || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-cake-candles"></i> Birthdate</span>
                    <span class="detail-value">${card.dataset.birthdate || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-location-dot"></i> Address</span>
                    <span class="detail-value">${card.dataset.address || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-people-roof"></i> Guardian</span>
                    <span class="detail-value">${card.dataset.guardian || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-phone"></i> Guardian Contact</span>
                    <span class="detail-value">${card.dataset.guardianContact || 'Not set'}</span>
                </div>
            `;
        }

        document.getElementById('viewProfileContent').innerHTML = `
            <div class="view-header">
                <div class="view-avatar" style="background:${color}">${initials}</div>
                <div class="view-info">
                    <h3>${card.dataset.fullname}</h3>
                    <span class="badge ${card.dataset.role}">${roleLabels[card.dataset.role]}</span>
                    <span class="badge ${card.dataset.status === 'active' ? 'active' : (card.dataset.status === 'suspended' ? 'inactive' : 'pending')}">${statusLabels[card.dataset.status]}</span>
                </div>
            </div>
            <div class="view-details">
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-envelope"></i> Email</span>
                    <span class="detail-value">${card.dataset.email || 'Not set'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-user"></i> Username</span>
                    <span class="detail-value">@${card.dataset.username}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-building"></i> Department</span>
                    <span class="detail-value">${card.dataset.department || 'Not assigned'}</span>
                </div>
                ${extraRows}
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-id-badge"></i> User ID</span>
                    <span class="detail-value">SUA-${String(userId).padStart(5, '0')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-calendar"></i> Created</span>
                    <span class="detail-value">${new Date(card.dataset.created).toLocaleDateString()}</span>
                </div>
            </div>
        `;
        document.getElementById('viewModal').style.display = 'flex';
    }

    function closeViewModal() {
        document.getElementById('viewModal').style.display = 'none';
    }

    // Close modals on outside click
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
        }
    }

    // Auto-hide flash messages
    setTimeout(() => {
        const flash = document.getElementById('flashMessage');
        if (flash) {
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 300);
        }
    }, 5000);
