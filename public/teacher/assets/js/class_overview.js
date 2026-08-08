// Toggle the "Add Material" form open/closed
const btnShowUpload = document.getElementById('btnShowUpload');
const btnCancelUpload = document.getElementById('btnCancelUpload');
const uploadForm = document.getElementById('materialUploadForm');

if (btnShowUpload && uploadForm) {
    btnShowUpload.addEventListener('click', () => {
        uploadForm.hidden = !uploadForm.hidden;
    });
}
if (btnCancelUpload && uploadForm) {
    btnCancelUpload.addEventListener('click', () => {
        uploadForm.hidden = true;
        uploadForm.reset();
    });
}

// Toggle file-vs-link fields in the upload form
document.querySelectorAll('input[name="source"]').forEach((radio) => {
    radio.addEventListener('change', () => {
        const isFile = document.querySelector('input[name="source"]:checked').value === 'file';
        document.querySelector('.material-source-file').hidden = !isFile;
        document.querySelector('.material-source-link').hidden = isFile;
    });
});

// Toggle the "New Assignment" form open/closed
const btnShowAssignmentForm = document.getElementById('btnShowAssignmentForm');
const btnCancelAssignmentForm = document.getElementById('btnCancelAssignmentForm');
const assignmentForm = document.getElementById('assignmentCreateForm');

if (btnShowAssignmentForm && assignmentForm) {
    btnShowAssignmentForm.addEventListener('click', () => {
        assignmentForm.hidden = !assignmentForm.hidden;
    });
}
if (btnCancelAssignmentForm && assignmentForm) {
    btnCancelAssignmentForm.addEventListener('click', () => {
        assignmentForm.hidden = true;
        assignmentForm.reset();
    });
}

// ===================== Attendance =====================
const attendanceForm = document.getElementById('attendanceForm');
if (attendanceForm) {
    // Keep the status-pill's highlighted state in sync with its radio input.
    attendanceForm.querySelectorAll('.attendance-status-group').forEach((group) => {
        group.querySelectorAll('input[type="radio"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                group.querySelectorAll('.status-pill').forEach((pill) => pill.classList.remove('checked'));
                radio.closest('.status-pill').classList.add('checked');
            });
        });
    });

    // "Mark all" bulk-action buttons
    attendanceForm.querySelectorAll('[data-mark-all]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const status = btn.getAttribute('data-mark-all');
            attendanceForm.querySelectorAll('.attendance-status-group').forEach((group) => {
                const radio = group.querySelector(`input[value="${status}"]`);
                if (radio) {
                    radio.checked = true;
                    group.querySelectorAll('.status-pill').forEach((pill) => pill.classList.remove('checked'));
                    radio.closest('.status-pill').classList.add('checked');
                }
            });
        });
    });
}