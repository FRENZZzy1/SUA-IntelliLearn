<?php

$csrfToken = generateCSRFToken();
// Archived subjects/sections can't be picked for a NEW class. The *All lists
// (with the is_archived flag) are used by the Update modal so an existing
// class that still points at an archived one keeps its selection.
$modalSubjectsAll = $pdo->query("SELECT subject_id, subject_name, is_archived FROM subjects ORDER BY subject_name")->fetchAll();
$modalSectionsAll = $pdo->query("SELECT section_id, section_name, grade_level, is_archived FROM sections ORDER BY grade_level, section_name")->fetchAll();
$modalSubjects = array_values(array_filter($modalSubjectsAll, static fn($r) => (int) $r['is_archived'] === 0));
$modalSections = array_values(array_filter($modalSectionsAll, static fn($r) => (int) $r['is_archived'] === 0));
$modalTeachers = $pdo->query("SELECT teacher_id, firstname, lastname FROM teachers ORDER BY lastname, firstname")->fetchAll();
$modalSchoolYears = $pdo->query("SELECT school_year_id, label, is_current FROM schoolyears ORDER BY start_date DESC")->fetchAll();
$modalCurrentTerm = resolveCurrentTerm(getTermIntervals($pdo));
$modalDefaultCapacity = 50;
try {
    $capacityStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'default_class_capacity' LIMIT 1");
    $capacityStmt->execute();
    $savedCapacity = $capacityStmt->fetchColumn();
    if (ctype_digit((string) $savedCapacity) && (int) $savedCapacity >= 1 && (int) $savedCapacity <= 500) {
        $modalDefaultCapacity = (int) $savedCapacity;
    }
} catch (PDOException $e) {
    // Keep the safe fallback if system_settings is unavailable.
}

?>

<link rel='stylesheet' href='assests/css/add_course.css'>

<div class="modal-overlay" id="addCourseOverlay" onclick="if (event.target === this) closeAddCourseModal()">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="addCourseTitle">
        <div class="modal-header">
            <h2 id="addCourseTitle">Add New Class</h2>
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
                                Grade <?= clean($sec['grade_level']) ?> — <?= clean($sec['section_name']) ?>
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
                        <input type="number" id="m_capacity" name="capacity" min="1" max="500" value="<?= $modalDefaultCapacity ?>" required>
                        <span class="field-note">Default from Settings &rarr; Enrollment Rules. You can still adjust this for this class.</span>
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
                            <input type="hidden" id="m_schedule_days" name="schedule_days" value="">
                            <fieldset class="schedule-day-picker" aria-label="Choose schedule days">
                                <legend>Select one or more days</legend>
                                <label class="schedule-day-option"><input type="checkbox" name="schedule_day_options[]" value="Monday"><span>Mon</span></label>
                                <label class="schedule-day-option"><input type="checkbox" name="schedule_day_options[]" value="Tuesday"><span>Tue</span></label>
                                <label class="schedule-day-option"><input type="checkbox" name="schedule_day_options[]" value="Wednesday"><span>Wed</span></label>
                                <label class="schedule-day-option"><input type="checkbox" name="schedule_day_options[]" value="Thursday"><span>Thu</span></label>
                                <label class="schedule-day-option"><input type="checkbox" name="schedule_day_options[]" value="Friday"><span>Fri</span></label>
                                <label class="schedule-day-option"><input type="checkbox" name="schedule_day_options[]" value="Saturday"><span>Sat</span></label>
                                <label class="schedule-day-option"><input type="checkbox" name="schedule_day_options[]" value="Sunday"><span>Sun</span></label>
                            </fieldset>
                            <span class="field-note">Click every day this class meets.</span>
                        </div>

                    <div class="form-row">
                        <label for="m_start_time"><i class="fas fa-clock" aria-hidden="true"></i> Start Time</label>
                        <input type="time" id="m_start_time" name="start_time" step="60">
                    </div>

                    <div class="form-row">
                        <label for="m_end_time"><i class="fas fa-hourglass-end" aria-hidden="true"></i> End Time</label>
                        <input type="time" id="m_end_time" name="end_time" step="60">
                    </div>
                </div>

                <div class="form-row">
                    <?php if ($modalCurrentTerm): ?>
                        <span class="field-note">This class will be added to the currently active term: <strong><?= clean($modalCurrentTerm) ?></strong>. Change this from "Set Term Interval" if needed.</span>
                    <?php else: ?>
                        <span class="field-note" style="color:#b91c1c;">No term is currently active for today's date — set your term intervals first, or this class can't be saved.</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAddCourseModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="addCourseSubmitBtn"><i class="fas fa-plus"></i> Add Course</button>
            </div>
        </form>

        <script>
            function syncScheduleDays(form) {
                const selected = Array.from(form.querySelectorAll('input[name="schedule_day_options[]"]:checked')).map(input => input.value);
                const hidden = form.querySelector('input[name="schedule_days"]');
                if (hidden) hidden.value = selected.join(', ');
            }

            function submitModalForm(form, url, submitBtn, errorBox, idleLabel, openPanel) {
                syncScheduleDays(form);
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