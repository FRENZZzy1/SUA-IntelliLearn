(function () {
    function yearLevelOptions(selected) {
        var html = '<option value="">-- Select Grade --</option>';
        for (var i = 7; i <= 12; i++) {
            var value = String(i);
            html += '<option value="' + value + '"' + (selected === value ? ' selected' : '') + '>Grade ' + value + '</option>';
        }
        return html;
    }

    function ensureYearLevelField() {
        var studentFields = document.getElementById('aumStudentFields');
        if (studentFields && !document.getElementById('aumYearLevel')) {
            var row = document.createElement('div');
            row.className = 'aum-row';
            row.id = 'aumYearLevelRow';
            row.innerHTML = '<div class="aum-group"><label>Year Level <span class="aum-req">*</span></label><div class="aum-input-wrap"><i class="fas fa-layer-group aum-input-icon"></i><select name="year_level" id="aumYearLevel" class="aum-control" data-student-required>' + yearLevelOptions('') + '</select></div></div>';
            studentFields.insertBefore(row, studentFields.querySelector('.aum-hint'));
        }

        var editStudent = document.getElementById('editStudentFields');
        if (editStudent && !document.getElementById('editYearLevel')) {
            var row2 = document.createElement('div');
            row2.className = 'form-row';
            row2.innerHTML = '<div class="form-group"><label>Year Level</label><select name="year_level" id="editYearLevel" class="form-control">' + yearLevelOptions('') + '</select></div>';
            editStudent.insertBefore(row2, editStudent.querySelector('.form-group'));
        }
    }

    function makeBatchModal() {
        if (document.getElementById('batchStudentModal')) return;
        var csrf = document.getElementById('aumCsrf');
        var modal = document.createElement('div');
        modal.id = 'batchStudentModal';
        modal.className = 'modal-overlay';
        modal.innerHTML =
            '<div class="modal-content batch-import-modal">' +
            '<div class="modal-header"><h2><i class="fas fa-file-excel"></i> Batch Add Students</h2><div class="close-icon" onclick="closeBatchStudentImportModal()"><i class="fas fa-times"></i></div></div>' +
            '<p class="batch-help">Upload an Excel <strong>.xlsx</strong> file using the required columns. Usernames and passwords will be generated automatically.</p>' +
            '<div class="batch-actions"><a class="btn-secondary" href="assests/api/student_import_template.php"><i class="fas fa-download"></i> Download Template</a></div>' +
            '<form id="batchStudentForm" enctype="multipart/form-data">' +
            '<input type="hidden" name="csrf_token" value="' + (csrf ? csrf.value : '') + '">' +
            '<div class="batch-drop"><i class="fas fa-file-arrow-up"></i><input type="file" name="excel_file" accept=".xlsx" required><small>Required: firstname, lastname, lrn, gender, birthdate, year_level</small></div>' +
            '<div id="batchImportResult" class="batch-result"></div>' +
            '<div class="compose-actions"><div></div><div class="right-actions"><button type="button" class="btn-secondary" onclick="closeBatchStudentImportModal()">Cancel</button><button class="btn-gold" type="submit"><i class="fas fa-upload"></i> Import Students</button></div></div>' +
            '</form></div>';
        document.body.appendChild(modal);
        document.getElementById('batchStudentForm').addEventListener('submit', function (event) {
            event.preventDefault();
            var form = event.target;
            var result = document.getElementById('batchImportResult');
            result.textContent = 'Importing...';
            fetch('assests/api/import_student_users.php', { method: 'POST', body: new FormData(form) })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    result.className = 'batch-result ' + (data.success ? 'success' : 'error');
                    result.innerHTML = '<strong>' + (data.message || 'Import completed.') + '</strong>';
                    if (data.errors && data.errors.length) {
                        result.innerHTML += '<br><small>' + data.errors.join('<br>') + '</small>';
                    }
                    if (data.success) setTimeout(function () { window.location.reload(); }, 1800);
                })
                .catch(function () {
                    result.className = 'batch-result error';
                    result.textContent = 'Import failed. Check the server error/log and make sure Composer dependencies are installed.';
                });
        });
    }

    window.openBatchStudentImportModal = function () {
        makeBatchModal();
        document.getElementById('batchStudentModal').style.display = 'flex';
    };
    window.closeBatchStudentImportModal = function () {
        var modal = document.getElementById('batchStudentModal');
        if (modal) modal.style.display = 'none';
    };
    window.exportStudentAccounts = function () {
        var level = new URLSearchParams(window.location.search).get('grade_level') || 'all';
        window.location.href = 'assests/api/export_student_accounts.php?year_level=' + encodeURIComponent(level);
    };

    document.addEventListener('DOMContentLoaded', function () {
        ensureYearLevelField();

        var editOriginal = window.editUser;
        if (typeof editOriginal === 'function') {
            window.editUser = function (userId) {
                ensureYearLevelField();
                editOriginal(userId);
                var card = document.querySelector('[data-user-id="' + userId + '"]');
                var level = card ? (card.dataset.yearLevel || '') : '';
                var field = document.getElementById('editYearLevel');
                if (field) field.value = level;
            };
        }
    });
})();