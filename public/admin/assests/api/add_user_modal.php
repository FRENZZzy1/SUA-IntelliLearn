<?php
if (!isset($aum_endpoint)) {
    $aum_endpoint = '/public/admin/assests/api/add_user_handler.php';
}
$aum_csrf = function_exists('generateCSRFToken') ? generateCSRFToken() : '';
?>
<div class="aum-overlay" id="aumOverlay" style="display:none;" data-endpoint="<?= htmlspecialchars($aum_endpoint) ?>">
    <div class="aum-modal">
        <div class="aum-accent-bar"></div>
        
        <div class="aum-header">
            <div class="aum-header-icon"><i class="fas fa-user-plus"></i></div>
            <div class="aum-header-text">
                <h2>Add New User</h2>
                <p>Fill in the details below to create an account</p>
            </div>
            <div class="aum-close" onclick="closeAddUserModal()"><i class="fas fa-times"></i></div>
        </div>

        <div class="aum-alert" id="aumAlert"></div>

        <!-- Scrollable inner wrapper keeps scrollbar away from the border -->
        <div class="aum-scroll-area">
            <form id="aumForm" novalidate>
                <input type="hidden" name="csrf_token" id="aumCsrf" value="<?= htmlspecialchars($aum_csrf) ?>">
                <input type="hidden" name="action" value="create">

                <div class="aum-section aum-section-pills">
                    <div class="aum-row">
                        <div class="aum-group" id="aumRoleGroup">
                            <label class="aum-section-label"><i class="fas fa-id-badge"></i> Select Role <span class="aum-req">*</span></label>
                            <div class="aum-pills">
                                <div class="aum-pill active" data-role="student" onclick="aumSetRole(this)">
                                    <div class="aum-pill-icon"><i class="fas fa-user-graduate"></i></div>
                                    <div class="aum-pill-text"><strong>Student</strong><small>Learner account</small></div>
                                </div>
                                <div class="aum-pill" data-role="teacher" onclick="aumSetRole(this)">
                                    <div class="aum-pill-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                                    <div class="aum-pill-text"><strong>Teacher</strong><small>Faculty account</small></div>
                                </div>
                                <div class="aum-pill" data-role="admin" onclick="aumSetRole(this)">
                                    <div class="aum-pill-icon"><i class="fas fa-user-shield"></i></div>
                                    <div class="aum-pill-text"><strong>Admin</strong><small>Staff account</small></div>
                                </div>
                            </div>
                            <input type="hidden" name="role" id="aumRoleInput" value="student">
                        </div>
                    </div>
                    <div class="aum-row">
                        <div class="aum-group">
                            <label class="aum-section-label"><i class="fas fa-toggle-on"></i> Account Status</label>
                            <div class="aum-pills aum-pills-compact">
                                <div class="aum-pill active" data-status="active" onclick="aumSetStatus(this)"><span class="aum-dot aum-dot-green"></span> Active</div>
                                <div class="aum-pill" data-status="inactive" onclick="aumSetStatus(this)"><span class="aum-dot aum-dot-gray"></span> Inactive</div>
                                <div class="aum-pill" data-status="suspended" onclick="aumSetStatus(this)"><span class="aum-dot aum-dot-red"></span> Suspended</div>
                            </div>
                            <input type="hidden" name="status" id="aumStatusInput" value="active">
                        </div>
                    </div>
                </div>

                <div id="aumStudentFields" class="aum-section aum-fields-section">
                    <div class="aum-section-title">
                        <span class="aum-section-line"></span>
                        <span><i class="fas fa-user-graduate"></i> Student Information</span>
                        <span class="aum-section-line"></span>
                    </div>
                    <div class="aum-row">
                        <div class="aum-group">
                            <label>First Name <span class="aum-req">*</span></label>
                            <div class="aum-input-wrap"><i class="fas fa-user aum-input-icon"></i><input type="text" name="firstname" class="aum-control" placeholder="e.g. Maria" data-student-required></div>
                        </div>
                        <div class="aum-group">
                            <label>Last Name <span class="aum-req">*</span></label>
                            <div class="aum-input-wrap"><i class="fas fa-user aum-input-icon"></i><input type="text" name="lastname" class="aum-control" placeholder="e.g. Santos" data-student-required></div>
                        </div>
                    </div>
                    <div class="aum-row">
                        <div class="aum-group">
                            <label>Middle Name</label>
                            <div class="aum-input-wrap"><i class="fas fa-user aum-input-icon"></i><input type="text" name="middlename" class="aum-control" placeholder="e.g. Clara"></div>
                        </div>
                        <div class="aum-group">
                            <label>LRN <span class="aum-req">*</span> <small>(12 digits)</small></label>
                            <div class="aum-input-wrap"><i class="fas fa-id-card aum-input-icon"></i><input type="text" name="lrn" class="aum-control" placeholder="e.g. 136090100234" pattern="\d{12}" maxlength="12" data-student-required></div>
                        </div>
                    </div>
                    <div class="aum-row">
                        <div class="aum-group">
                            <label>Email</label>
                            <div class="aum-input-wrap"><i class="fas fa-envelope aum-input-icon"></i><input type="email" name="email" id="aumStudentEmail" class="aum-control" placeholder="e.g. maria@sturiel.edu.ph" data-student-required></div>
                        </div>
                        <div class="aum-group">
                            <label>Birthdate <span class="aum-req">*</span></label>
                            <div class="aum-input-wrap"><i class="fas fa-calendar-alt aum-input-icon"></i><input type="date" name="birthdate" class="aum-control" data-student-required></div>
                        </div>
                    </div>
                    <div class="aum-group">
                        <label>Address</label>
                        <div class="aum-input-wrap"><i class="fas fa-map-marker-alt aum-input-icon"></i><input type="text" name="address" class="aum-control" placeholder="e.g. 123 Rizal St., Talisay City"></div>
                    </div>
                    <div class="aum-row">
                        <div class="aum-group">
                            <label>Guardian Name</label>
                            <div class="aum-input-wrap"><i class="fas fa-user-friends aum-input-icon"></i><input type="text" name="guardian_name" class="aum-control" placeholder="e.g. Juana Santos"></div>
                        </div>
                        <div class="aum-group">
                            <label>Guardian Contact</label>
                            <div class="aum-input-wrap"><i class="fas fa-phone aum-input-icon"></i><input type="tel" name="guardian_contact" class="aum-control" placeholder="e.g. +63 912 345 6789"></div>
                        </div>
                    </div>
                    <div class="aum-hint">
                        <div class="aum-hint-icon"><i class="fas fa-lightbulb"></i></div>
                        <div class="aum-hint-text">
                            <strong>Auto-generated credentials:</strong><br>
                            <span class="aum-hint-detail"><strong>Username:</strong> STU-(last 4 digits of LRN)-(birthdate as MMDDYY)<br><strong>Password:</strong> Last name + birthdate as MMDDYY</span>
                        </div>
                    </div>
                </div>

                <div id="aumStaffFields" style="display:none;" class="aum-section aum-fields-section">
                    <div class="aum-section-title">
                        <span class="aum-section-line"></span>
                        <span><i class="fas fa-briefcase"></i> Staff Information</span>
                        <span class="aum-section-line"></span>
                    </div>
                    <div class="aum-row" id="aumTeacherNameRow" style="display:none;">
                        <div class="aum-group">
                            <label>First Name <span class="aum-req">*</span></label>
                            <div class="aum-input-wrap"><i class="fas fa-user aum-input-icon"></i><input type="text" name="teacher_firstname" class="aum-control" placeholder="e.g. Maria" data-teacher-required></div>
                        </div>
                        <div class="aum-group">
                            <label>Last Name <span class="aum-req">*</span></label>
                            <div class="aum-input-wrap"><i class="fas fa-user aum-input-icon"></i><input type="text" name="teacher_lastname" class="aum-control" placeholder="e.g. Santos" data-teacher-required></div>
                        </div>
                    </div>
                    <div class="aum-row" id="aumTeacherMiddleNameRow" style="display:none;">
                        <div class="aum-group">
                            <label>Middle Name</label>
                            <div class="aum-input-wrap"><i class="fas fa-user aum-input-icon"></i><input type="text" name="teacher_middlename" class="aum-control" placeholder="e.g. Clara"></div>
                        </div>
                    </div>
                    <div class="aum-row">
                        <div class="aum-group">
                            <label>Email Address <span class="aum-req">*</span></label>
                            <div class="aum-input-wrap"><i class="fas fa-envelope aum-input-icon"></i><input type="email" name="email" id="aumStaffEmail" class="aum-control" placeholder="e.g. maria@sturiel.edu.ph" data-staff-required></div>
                            <small id="aumTeacherEmailHint" style="display:none;" class="aum-help-text"><i class="fas fa-info-circle"></i> This will also be the teacher's login username.</small>
                        </div>
                        <div class="aum-group" id="aumContactFieldGroup" style="display:none;">
                            <label>Contact Number</label>
                            <div class="aum-input-wrap"><i class="fas fa-phone aum-input-icon"></i><input type="tel" name="contact" class="aum-control" placeholder="e.g. +63 912 345 6789"></div>
                        </div>
                    </div>
                    <div class="aum-row" id="aumDepartmentRow" style="display:none;">
                        <div class="aum-group">
                            <label>Department / Grade Level</label>
                            <div class="aum-input-wrap"><i class="fas fa-building aum-input-icon"></i>
                                <select name="department" class="aum-control">
                                    <option value="">-- Select --</option>
                                    <option value="Senior High">Senior High</option>
                                    <option value="High School">High School</option>
                                    <option value="Highschool/Senior High">Highschool/Senior High</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="aum-row" id="aumTeacherOnlyFields" style="display:none;">
                        <div class="aum-group">
                            <label>Employment Status</label>
                            <div class="aum-input-wrap"><i class="fas fa-briefcase aum-input-icon"></i><select name="employment_status" class="aum-control"><option value="">-- Select --</option><option value="full-time">Full-time</option><option value="part-time">Part-time</option></select></div>
                        </div>
                        <div class="aum-group">
                            <label>Specialization <small>(optional)</small></label>
                            <div class="aum-input-wrap"><i class="fas fa-star aum-input-icon"></i><input type="text" name="specialization" class="aum-control" placeholder="e.g. Algebra, Biology"></div>
                        </div>
                    </div>
                    <div class="aum-row" id="aumAdminOnlyFields" style="display:none;">
                        <div class="aum-group">
                            <label>Position <span class="aum-req">*</span></label>
                            <div class="aum-input-wrap"><i class="fas fa-crown aum-input-icon"></i><select name="position" class="aum-control" data-admin-required><option value="principal">Principal</option><option value="registrar">Registrar</option><option value="staff" selected>Staff</option></select></div>
                        </div>
                        <div class="aum-group">
                            <label>Access Level <span class="aum-req">*</span></label>
                            <div class="aum-input-wrap"><i class="fas fa-lock aum-input-icon"></i><select name="access_level" class="aum-control" data-admin-required><option value="full">Full</option><option value="limited" selected>Limited</option><option value="read_only">Read Only</option></select></div>
                        </div>
                    </div>
                    <div class="aum-row">
                        <div class="aum-group">
                            <label>Password <span class="aum-req">*</span> <small>(min 6 characters)</small></label>
                            <div class="aum-input-wrap"><i class="fas fa-key aum-input-icon"></i><input type="password" name="password" class="aum-control" placeholder="Enter secure password" minlength="6" data-staff-required></div>
                        </div>
                    </div>
                </div>

                <div class="aum-actions">
                    <label class="aum-checkbox">
                        <div class="aum-checkbox-box"><input type="checkbox" name="send_email" value="1"><span class="aum-checkmark"><i class="fas fa-check"></i></span></div>
                        <span class="aum-checkbox-label"><i class="fas fa-envelope"></i> Send welcome email</span>
                    </label>
                    <div class="aum-actions-right">
                        <button type="button" class="aum-btn-secondary" onclick="closeAddUserModal()"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" class="aum-btn-primary" id="aumSubmitBtn"><i class="fas fa-save"></i> Save User</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* ── Overlay ───────────────────────────────────────────── */
.aum-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(18, 64, 41, 0.45);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999; padding: 24px;
    backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
    animation: aumFadeIn 0.25s ease;
}
@keyframes aumFadeIn { from { opacity: 0; } to { opacity: 1; } }

/* ── Modal Shell (no scroll here) ──────────────────────── */
.aum-modal {
    background: #ffffff; border-radius: 20px;
    width: 100%; max-width: 680px; max-height: 92vh;
    box-shadow: 0 25px 80px rgba(18, 64, 41, 0.25), 0 0 0 1px rgba(255,255,255,0.1);
    animation: aumSlideUp 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative; overflow: hidden;
    display: flex; flex-direction: column;
    font-family: 'DM Sans', 'Segoe UI', system-ui, sans-serif;
}
@keyframes aumSlideUp {
    from { opacity: 0; transform: translateY(30px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

/* ── Scrollable Inner Area ─────────────────────────────── */
.aum-scroll-area {
    overflow-y: auto;
    flex: 1;
    min-height: 0;
    /* Thin custom scrollbar so it never looks bulky at the edge */
    scrollbar-width: thin;
    scrollbar-color: #c5d5cb transparent;
}
.aum-scroll-area::-webkit-scrollbar { width: 6px; }
.aum-scroll-area::-webkit-scrollbar-track { background: transparent; margin: 8px 0; }
.aum-scroll-area::-webkit-scrollbar-thumb {
    background: #c5d5cb; border-radius: 10px;
}

/* ── Accent Bar ────────────────────────────────────────── */
.aum-accent-bar {
    position: absolute; top: 0; left: 0; right: 0; height: 5px;
    background: linear-gradient(90deg, #124029 0%, #1a5c3a 40%, #f4a261 100%);
    border-radius: 20px 20px 0 0; z-index: 2;
}

/* ── Header ────────────────────────────────────────────── */
.aum-header {
    display: flex; align-items: center; gap: 16px;
    padding: 28px 28px 0 28px; flex-shrink: 0; position: relative; z-index: 1;
}
.aum-header-icon {
    width: 48px; height: 48px; border-radius: 14px;
    background: linear-gradient(135deg, #124029 0%, #1a5c3a 100%);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.2rem;
    box-shadow: 0 4px 12px rgba(18, 64, 41, 0.25);
    flex-shrink: 0;
}
.aum-header-text { flex: 1; }
.aum-header-text h2 { font-size: 1.3rem; font-weight: 700; color: #124029; margin: 0 0 4px 0; letter-spacing: -0.3px; }
.aum-header-text p { font-size: 0.82rem; color: #6b7280; margin: 0; }
.aum-close {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: #9ca3af; font-size: 1.1rem;
    transition: all 0.2s ease; flex-shrink: 0;
    background: #f8faf9; border: 1px solid transparent;
}
.aum-close:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; transform: rotate(90deg); }

/* ── Alert ─────────────────────────────────────────────── */
.aum-alert {
    display: none; margin: 16px 28px 0 28px;
    padding: 14px 16px; border-radius: 12px;
    font-size: 0.88rem; line-height: 1.5;
    align-items: center; gap: 10px; flex-shrink: 0;
    animation: aumAlertPop 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes aumAlertPop {
    from { opacity: 0; transform: scale(0.95) translateY(-5px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.aum-alert.show { display: flex; }
.aum-alert.success {
    background: linear-gradient(135deg, #ecfdf3 0%, #d1fae5 100%);
    color: #166534; border: 1px solid #86efac;
    box-shadow: 0 2px 8px rgba(22, 101, 52, 0.08);
}
.aum-alert.error {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    color: #991b1b; border: 1px solid #fca5a5;
    box-shadow: 0 2px 8px rgba(153, 27, 27, 0.08);
}
.aum-alert i { font-size: 1.1rem; flex-shrink: 0; }

/* ── Sections ──────────────────────────────────────────── */
.aum-section { padding: 0 28px; margin-top: 20px; }
.aum-section-pills {
    background: linear-gradient(180deg, #f8faf9 0%, #ffffff 100%);
    margin: 16px 28px 0 28px; padding: 20px 24px;
    border-radius: 16px; border: 1px solid #e8f0eb;
}
.aum-fields-section { margin-top: 8px; }
.aum-section-title {
    display: flex; align-items: center; gap: 12px;
    margin: 24px 0 16px 0; color: #124029;
    font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;
}
.aum-section-line { flex: 1; height: 1px; background: linear-gradient(90deg, transparent, #d1e0d6, transparent); }
.aum-section-label { display: block; font-size: 0.8rem; font-weight: 700; color: #374151; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
.aum-section-label i { color: #1a5c3a; margin-right: 4px; }

/* ── Form Layout ───────────────────────────────────────── */
.aum-row { display: flex; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.aum-group { flex: 1; min-width: 200px; }
.aum-group label { display: block; font-size: 0.82rem; font-weight: 600; color: #374151; margin-bottom: 7px; }
.aum-req { color: #dc2626; font-weight: 700; }
.aum-help-text { display: block; margin-top: 6px; font-size: 0.78rem; color: #6b7280; }
.aum-help-text i { color: #f4a261; }

/* ── Inputs ────────────────────────────────────────────── */
.aum-input-wrap { position: relative; }
.aum-input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.85rem; pointer-events: none; transition: color 0.2s ease; }
.aum-control {
    width: 100%; padding: 10px 12px 10px 38px;
    border: 1.5px solid #e5e7eb; border-radius: 10px;
    font-size: 0.9rem; font-family: inherit; color: #1f2937;
    background: #fafbfc; transition: all 0.2s ease;
}
.aum-control::placeholder { color: #b0b8c4; }
.aum-control:hover { border-color: #c5d5cb; background: #f8faf9; }
.aum-control:focus { outline: none; border-color: #1a5c3a; background: #ffffff; box-shadow: 0 0 0 4px rgba(26,92,58,0.1); }
.aum-control:focus + .aum-input-icon, .aum-input-wrap:focus-within .aum-input-icon { color: #1a5c3a; }
select.aum-control { padding-right: 36px; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; }

/* ── Pills ─────────────────────────────────────────────── */
.aum-pills { display: flex; gap: 10px; flex-wrap: wrap; }
.aum-pill {
    flex: 1; min-width: 120px; padding: 10px 14px;
    border: 2px solid #e5e7eb; border-radius: 12px; cursor: pointer;
    background: #fff; color: #4b5563;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex; align-items: center; gap: 10px;
}
.aum-pill:hover { border-color: #1a5c3a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(18, 64, 41, 0.1); }
.aum-pill.active {
    background: linear-gradient(135deg, #124029 0%, #1a5c3a 100%);
    border-color: #124029; color: #fff;
    box-shadow: 0 4px 16px rgba(18, 64, 41, 0.25);
    transform: translateY(-1px);
}
.aum-pill-icon { width: 34px; height: 34px; border-radius: 10px; background: rgba(18, 64, 41, 0.08); display: flex; align-items: center; justify-content: center; font-size: 0.9rem; color: #1a5c3a; transition: all 0.2s ease; }
.aum-pill.active .aum-pill-icon { background: rgba(255,255,255,0.2); color: #fff; }
.aum-pill-text { display: flex; flex-direction: column; }
.aum-pill-text strong { font-size: 0.85rem; font-weight: 600; }
.aum-pill-text small { font-size: 0.72rem; opacity: 0.8; font-weight: 400; }
.aum-pills-compact .aum-pill { min-width: auto; padding: 8px 14px; font-size: 0.82rem; font-weight: 500; }
.aum-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.aum-dot-green { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,0.2); }
.aum-dot-gray { background: #9ca3af; box-shadow: 0 0 0 3px rgba(156,163,175,0.2); }
.aum-dot-red { background: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,0.2); }

/* ── Hint Box ──────────────────────────────────────────── */
.aum-hint {
    background: linear-gradient(135deg, #fef9f3 0%, #fdf4e8 100%);
    border: 1px solid #fde8cd; border-radius: 12px;
    padding: 16px; display: flex; gap: 14px;
    margin-top: 8px; margin-bottom: 4px;
}
.aum-hint-icon { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #f4a261 0%, #e8935a 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.9rem; flex-shrink: 0; box-shadow: 0 2px 8px rgba(244, 162, 97, 0.3); }
.aum-hint-text { font-size: 0.82rem; color: #7c5c3a; line-height: 1.6; }
.aum-hint-text strong { color: #92400e; }
.aum-hint-detail { color: #a16207; font-size: 0.8rem; }

/* ── Actions ───────────────────────────────────────────── */
.aum-actions {
    display: flex; align-items: center; justify-content: space-between;
    margin: 24px 28px 28px 28px; padding-top: 20px;
    border-top: 1px solid #e8f0eb; flex-wrap: wrap; gap: 14px;
}

/* Custom Checkbox */
.aum-checkbox { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
.aum-checkbox-box { position: relative; width: 22px; height: 22px; }
.aum-checkbox-box input { position: absolute; opacity: 0; width: 0; height: 0; }
.aum-checkmark {
    position: absolute; top: 0; left: 0; width: 22px; height: 22px; border-radius: 6px;
    border: 2px solid #d1d5db; background: #fff;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s ease; color: transparent; font-size: 0.65rem;
}
.aum-checkbox-box input:checked + .aum-checkmark {
    background: linear-gradient(135deg, #124029 0%, #1a5c3a 100%);
    border-color: #124029; color: #fff;
    box-shadow: 0 2px 6px rgba(18, 64, 41, 0.25);
}
.aum-checkbox-label { font-size: 0.85rem; color: #4b5563; font-weight: 500; }
.aum-checkbox-label i { color: #f4a261; margin-right: 2px; }
.aum-actions-right { display: flex; gap: 10px; margin-left: auto; }

/* Buttons */
.aum-btn-primary, .aum-btn-secondary {
    padding: 11px 20px; border-radius: 10px; font-size: 0.88rem; font-weight: 600;
    cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 8px;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.aum-btn-primary {
    background: linear-gradient(135deg, #f4a261 0%, #e8935a 100%);
    color: #1f2937; box-shadow: 0 4px 14px rgba(244, 162, 97, 0.35);
}
.aum-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(244, 162, 97, 0.45); }
.aum-btn-primary:active { transform: translateY(0); }
.aum-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }
.aum-btn-secondary { background: #f0f4f1; color: #4b5563; border: 1px solid #e5e7eb; }
.aum-btn-secondary:hover { background: #e4ebe6; border-color: #d1d5db; transform: translateY(-1px); }

/* ── Responsive ────────────────────────────────────────── */
@media (max-width: 640px) {
    .aum-overlay { padding: 12px; }
    .aum-modal { border-radius: 16px; max-height: 95vh; }
    .aum-accent-bar { border-radius: 16px 16px 0 0; }
    .aum-header { padding: 20px 20px 0 20px; }
    .aum-alert { margin: 12px 20px 0 20px; }
    .aum-section, .aum-actions { padding-left: 20px; padding-right: 20px; margin-left: 0; margin-right: 0; }
    .aum-section-pills { margin: 12px 20px 0 20px; padding: 16px; }
    .aum-row { flex-direction: column; gap: 12px; }
    .aum-group { min-width: 100%; }
    .aum-pill { min-width: 100%; }
    .aum-actions { flex-direction: column; align-items: stretch; }
    .aum-actions-right { margin-left: 0; justify-content: flex-end; }
}
</style>

<script>
(function () {
    var aumLockedRole = null;

    window.openAddUserModal = function (lockRole) {
        var overlay = document.getElementById('aumOverlay');
        aumResetForm();
        aumLockedRole = lockRole || null;

        var roleGroup = document.getElementById('aumRoleGroup');
        if (aumLockedRole) {
            roleGroup.style.display = 'none';
            var pill = document.querySelector('#aumOverlay .aum-pill[data-role="' + aumLockedRole + '"]');
            if (pill) aumSetRole(pill);
        } else {
            roleGroup.style.display = '';
        }

        overlay.style.display = 'flex';
    };

    window.closeAddUserModal = function () {
        document.getElementById('aumOverlay').style.display = 'none';
    };

    window.aumSetRole = function (el) {
        document.querySelectorAll('#aumOverlay .aum-pill[data-role]').forEach(function (p) { p.classList.remove('active'); });
        el.classList.add('active');
        var role = el.getAttribute('data-role');
        document.getElementById('aumRoleInput').value = role;

        var isStudent = role === 'student';
        document.getElementById('aumStudentFields').style.display = isStudent ? '' : 'none';
        document.getElementById('aumStaffFields').style.display = isStudent ? 'none' : '';
        document.querySelectorAll('#aumOverlay [data-student-required]').forEach(function (i) { i.required = isStudent; });
        document.querySelectorAll('#aumOverlay [data-staff-required]').forEach(function (i) { i.required = !isStudent; });

        if (!isStudent) aumToggleStaffSubFields(role);
    };

    window.aumSetStatus = function (el) {
        document.querySelectorAll('#aumOverlay .aum-pill[data-status]').forEach(function (p) { p.classList.remove('active'); });
        el.classList.add('active');
        document.getElementById('aumStatusInput').value = el.getAttribute('data-status');
    };

    function aumToggleStaffSubFields(role) {
        var isTeacher = role === 'teacher';
        document.getElementById('aumTeacherNameRow').style.display = isTeacher ? '' : 'none';
        document.getElementById('aumTeacherMiddleNameRow').style.display = isTeacher ? '' : 'none';
        document.getElementById('aumContactFieldGroup').style.display = isTeacher ? '' : 'none';
        document.getElementById('aumDepartmentRow').style.display = isTeacher ? '' : 'none';
        document.getElementById('aumTeacherOnlyFields').style.display = isTeacher ? '' : 'none';
        document.getElementById('aumAdminOnlyFields').style.display = isTeacher ? 'none' : '';
        document.getElementById('aumTeacherEmailHint').style.display = isTeacher ? '' : 'none';
        document.querySelectorAll('#aumOverlay [data-teacher-required]').forEach(function (i) { i.required = isTeacher; });
        document.querySelectorAll('#aumOverlay [data-admin-required]').forEach(function (i) { i.required = !isTeacher; });
    }

    function aumResetForm() {
        var form = document.getElementById('aumForm');
        form.reset();
        var alertBox = document.getElementById('aumAlert');
        alertBox.className = 'aum-alert';
        alertBox.innerHTML = '';
        aumSetRole(document.querySelector('#aumOverlay .aum-pill[data-role="student"]'));
        aumSetStatus(document.querySelector('#aumOverlay .aum-pill[data-status="active"]'));
    }

    function aumShowAlert(type, message) {
        var alertBox = document.getElementById('aumAlert');
        alertBox.className = 'aum-alert show ' + type;
        alertBox.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> <span>' + message + '</span>';
    }

    document.getElementById('aumForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var form = e.target;

           if (!form.reportValidity()) {
        return;
    }

        var btn = document.getElementById('aumSubmitBtn');
        var endpoint = document.getElementById('aumOverlay').getAttribute('data-endpoint');

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        fetch(endpoint, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                aumShowAlert('success', data.message || 'User created successfully.');
                if (typeof window.onUserAdded === 'function') {
                    window.onUserAdded(data.user || null);
                }
                setTimeout(function () { window.closeAddUserModal(); }, 1400);
            } else {
                var msg = Array.isArray(data.errors) ? data.errors.join(' ') : (data.message || 'Something went wrong.');
                aumShowAlert('error', msg);
            }
        })
        .catch(function () {
            aumShowAlert('error', 'Network error — please try again.');
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save User';
        });
    });

    document.getElementById('aumOverlay').addEventListener('click', function (e) {
        if (e.target === this) window.closeAddUserModal();
    });
})();
</script>