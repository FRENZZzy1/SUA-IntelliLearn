<?php

$csrfToken = generateCSRFToken();
$modalSubjects = $pdo->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name")->fetchAll();
$modalSections = $pdo->query("SELECT section_id, section_name, grade_level, strand FROM sections ORDER BY grade_level, section_name")->fetchAll();
$modalTeachers = $pdo->query("SELECT teacher_id, firstname, lastname FROM teachers ORDER BY lastname, firstname")->fetchAll();
$modalSchoolYears = $pdo->query("SELECT school_year_id, label, is_current FROM schoolyears ORDER BY start_date DESC")->fetchAll();

?>

<link rel='stylesheet' href='assests/css/add_course.css'>

<div class="modal-overlay" id="addCourseOverlay" onclick="if (event.target === this) closeAddCourseModal()">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="addCourseTitle">
        <div class="modal-header">
            <h2 id="addCourseTitle">Add New Course</h2>
            <button type="button" class="modal-close" onclick="closeAddCourseModal()" aria-label="Close">&times;</button>
        </div>

        <div class="modal-errors" id="addCourseErrors" hidden></div>

        <form id="addCourseForm">
            <input type="hidden" name="csrf" value="<?= clean($csrfToken) ?>">

            <div class="modal-body">
                <div class="form-row">
                    <label for="m_subject_id"><i class="fas fa-book" aria-hidden="true"></i> Subject</label>
                    <select id="m_subject_id" name="subject_id" required>
                        <option value="">Select a subject</option>
                        <?php foreach ($modalSubjects as $s): ?>
                            <option value="<?= (int) $s['subject_id'] ?>"><?= clean($s['subject_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="m_section_id"><i class="fas fa-users" aria-hidden="true"></i> Section</label>
                    <select id="m_section_id" name="section_id" required>
                        <option value="">Select a section</option>
                        <?php foreach ($modalSections as $sec): ?>
                            <option value="<?= (int) $sec['section_id'] ?>">
                                Grade <?= clean($sec['grade_level']) ?> — <?= clean($sec['section_name']) ?><?= $sec['strand'] ? ' (' . clean($sec['strand']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="m_teacher_id"><i class="fas fa-user-tie" aria-hidden="true"></i> Teacher</label>
                    <select id="m_teacher_id" name="teacher_id" required>
                        <option value="">Select a teacher</option>
                        <?php foreach ($modalTeachers as $t): ?>
                            <option value="<?= (int) $t['teacher_id'] ?>"><?= clean($t['firstname'] . ' ' . $t['lastname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row-split">
                    <div class="form-row">
                        <label for="m_quarter"><i class="fas fa-clock" aria-hidden="true"></i> Term</label>
                        <select id="m_quarter" name="quarter" required>
                            <option value="">Select</option>
                            <?php foreach (['TRM 1', 'TRM 2', 'TRM 3'] as $q): ?>
                                <option value="<?= $q ?>"><?= $q ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="m_school_year_id"><i class="fas fa-calendar-alt" aria-hidden="true"></i> School Year</label>
                        <select id="m_school_year_id" name="school_year_id" required>
                            <option value="">Select</option>
                            <?php foreach ($modalSchoolYears as $sy): ?>
                                <option value="<?= (int) $sy['school_year_id'] ?>" <?= $sy['is_current'] ? 'selected' : '' ?>><?= clean($sy['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="m_capacity"><i class="fas fa-hashtag" aria-hidden="true"></i> Capacity</label>
                        <input type="number" id="m_capacity" name="capacity" min="1" value="50" required>
                    </div>

                    <div class="form-row">
                        <label for="m_status"><i class="fas fa-toggle-on" aria-hidden="true"></i> Status</label>
                        <select id="m_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-row-split">
                    <div class="form-row">
                        <label for="m_schedule_days"><i class="fas fa-calendar-week" aria-hidden="true"></i> Schedule Days</label>
                        <input type="text" id="m_schedule_days" name="schedule_days" maxlength="20" placeholder="e.g. M - W">
                        <span class="field-note">Optional</span>
                    </div>

                    <div class="form-row">
                        <label for="m_start_time"><i class="fas fa-clock" aria-hidden="true"></i> Start Time</label>
                        <input type="text" id="m_start_time" name="start_time" maxlength="20" placeholder="e.g. 7:00 AM">
                    </div>

                    <div class="form-row">
                        <label for="m_end_time"><i class="fas fa-hourglass-end" aria-hidden="true"></i> End Time</label>
                        <input type="text" id="m_end_time" name="end_time" maxlength="20" placeholder="e.g. 10:00 AM">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAddCourseModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="addCourseSubmitBtn"><i class="fas fa-plus"></i> Add Course</button>
            </div>
        </form>

        <script>
            function submitModalForm(form, url, submitBtn, errorBox, idleLabel, openPanel) {
                errorBox.hidden = true;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

                fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new FormData(form)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (openPanel) {
                            const params = new URLSearchParams(window.location.search);
                            params.set('open', openPanel);
                            window.location.href = 'courses.php?' + params.toString();
                        } else {
                            location.reload();
                        }
                    } else {
                        errorBox.innerHTML = data.errors.map(err => '<div>' + err + '</div>').join('');
                        errorBox.hidden = false;
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = idleLabel;
                    }
                })
                .catch(() => {
                    errorBox.innerHTML = '<div>Something went wrong. Please try again.</div>';
                    errorBox.hidden = false;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = idleLabel;
                });
            }

            function openAddCourseModal() {
                document.getElementById('addCourseForm').reset();
                document.getElementById('addCourseOverlay').classList.add('open');
                document.getElementById('addCourseErrors').hidden = true;
            }

            function closeAddCourseModal() {
                document.getElementById('addCourseOverlay').classList.remove('open');
            }

            document.getElementById('addCourseForm').addEventListener('submit', function (e) {
                e.preventDefault();
                submitModalForm(
                    this,
                    'add_course.php',
                    document.getElementById('addCourseSubmitBtn'),
                    document.getElementById('addCourseErrors'),
                    '<i class="fas fa-plus"></i> Add Course'
                );
            });
        </script>
    </div>
</div>