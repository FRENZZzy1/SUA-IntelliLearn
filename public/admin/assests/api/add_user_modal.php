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
                <input type="hidden" name="permissions_json" value="">

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
                                    <div class="aum-pill-text"><strong>Admin</strong><small>IT Administrator account</small></div>
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
                            <label>Email <span class="aum-req">*</span></label>
                            <div class="aum-input-wrap"><i class="fas fa-envelope aum-input-icon"></i><input type="email" name="student_email" id="aumStudentEmail" class="aum-control" placeholder="e.g. maria@sturiel.edu.ph" data-student-required></div>
                        </div>
                        <div class="aum-group">
                            <label>Birthdate <span class="aum-req">*</span></label>
                            <div class="aum-input-wrap"><i class="fas fa-calendar-alt aum-input-icon"></i><input type="date" name="birthdate" class="aum-control" max="<?= date('Y-m-d') ?>" data-student-required></div>
                        </div>
                    </div>
                    <div class="aum-row">
                        <div class="aum-group">
                            <label>Year Level <span class="aum-req">*</span></label>
                            <div class="aum-input-wrap"><i class="fas fa-layer-group aum-input-icon"></i>
                                <select name="year_level" id="aumYearLevel" class="aum-control" data-student-required required>
                                    <option value="">-- Select Grade --</option>
                                    <option value="7">Grade 7</option><option value="8">Grade 8</option><option value="9">Grade 9</option>
                                    <option value="10">Grade 10</option><option value="11">Grade 11</option><option value="12">Grade 12</option>
                                </select>
                            </div>
                        </div>
                        <div class="aum-group">
                            <label>Strand</label>
                            <div class="aum-input-wrap"><i class="fas fa-sitemap aum-input-icon"></i>
                                <select name="strand" id="aumStrand" class="aum-control">
                                    <option value="">None</option>
                                    <option value="GAS">GAS</option><option value="ABM">ABM</option><option value="HUMSS">HUMSS</option><option value="TVL">TVL</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="aum-row">
                        <div class="aum-group">
                            <label>Gender <span class="aum-req">*</span></label>
                            <div class="aum-input-wrap"><i class="fas fa-venus-mars aum-input-icon"></i>
                                <select name="gender" class="aum-control" data-student-required>
                                    <option value="">-- Select --</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
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
                            <div class="aum-input-wrap"><i class="fas fa-phone aum-input-icon"></i><input type="tel" name="guardian_contact" class="aum-control" placeholder="+63 912 345 6789" data-ph-mobile="true" maxlength="16" inputmode="tel" title="Use 09XXXXXXXXX or +63 9XX XXX XXXX format"></div>
                        </div>
                    </div>
                    <div class="aum-hint">
                        <div class="aum-hint-icon"><i class="fas fa-lightbulb"></i></div>
                        <div class="aum-hint-text">
                            <strong>Auto-generated credentials:</strong><br>
                            <span class="aum-hint-detail"><strong>Username:</strong> STU-(7 random digits)<br><strong>Password:</strong> Last name (capitalized) + birthdate as MMDDYY + !
                            <br><strong>Example:</strong> Cruz091306! for a student named Cruz born on September 13, 2006</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div id="aumStaffFields" style="display:none;" class="aum-section aum-fields-section">
                    <div class="aum-section-title">
                        <span class="aum-section-line"></span>
                        <span><i class="fas fa-briefcase"></i> IT Administrator Information</span>
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
                            <div class="aum-input-wrap"><i class="fas fa-phone aum-input-icon"></i><input type="tel" name="contact" class="aum-control" placeholder="+63 912 345 6789" data-ph-mobile="true" maxlength="16" inputmode="tel" title="Use 09XXXXXXXXX or +63 9XX XXX XXXX format"></div>
                        </div>
                    </div>
                    <div class="aum-row" id="aumDepartmentRow" style="display:none;">
                        <div class="aum-group">
                            <label>Department / Grade Level</label>
                            <div class="aum-input-wrap"><i class="fas fa-building aum-input-icon"></i>
                                <select name="department" class="aum-control">
                                    <option value="">-- Select --</option>
                                    <option value="Senior High">Senior High only</option>
                                    <option value="High School">High School only</option>
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
                            <div class="aum-input-wrap"><i class="fas fa-crown aum-input-icon"></i><select name="position" class="aum-control" data-admin-required><option value="principal">Principal</option><option value="registrar">Registrar</option><option value="it_administrator" selected>IT Administrator</option></select></div>
                        </div>
                        <div class="aum-group">
                            <label>Access Level <span class="aum-req">*</span></label>
                            <div class="aum-input-wrap"><i class="fas fa-lock aum-input-icon"></i><select name="access_level" class="aum-control" data-admin-required><option value="full">Full</option><option value="limited" selected>Limited</option><option value="read_only">Read Only</option></select></div>
                        </div>
                    </div>
                    <div class="aum-row" id="aumFullDaysRow" style="display:none;">
                        <div class="aum-group">
                            <label>Full Access Duration <span class="aum-req">*</span> <small>(days)</small></label>
                            <div class="aum-input-wrap"><i class="fas fa-hourglass-half aum-input-icon"></i><input type="number" name="full_access_days" class="aum-control" min="1" max="365" step="1" placeholder="e.g. 7" disabled></div>
                            <small class="aum-help-text"><i class="fas fa-circle-info"></i> Full access ends automatically after this many days and the account becomes Read Only. The Principal is not time-limited.</small>
                        </div>
                    </div>
                    <div class="aum-row" id="aumPasswordRow">
                        <div class="aum-group">
                            <label id="aumPasswordLabel">Password <span class="aum-req">*</span> <small>(min 8 characters, 1 uppercase, 1 special character)</small></label>
                            <div class="aum-input-wrap aum-has-eye"><i class="fas fa-key aum-input-icon"></i><input type="password" name="password" id="aumStaffPassword" class="aum-control" placeholder="Enter secure password" minlength="8" pattern="(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}" autocomplete="new-password" data-staff-required><button type="button" class="aum-eye-btn" data-eye-for="aumStaffPassword" title="Show password" aria-label="Show password"><i class="fas fa-eye"></i></button></div>
                            <small id="aumTeacherPasswordHint" style="display:none;" class="aum-help-text"><i class="fas fa-envelope"></i> Teachers set their password through a secure link sent to their email.</small>
                        </div>
                    </div>
                    <div class="aum-row" id="aumPasswordConfirmRow">
                        <div class="aum-group">
                            <label id="aumPasswordConfirmLabel">Confirm Password <span class="aum-req">*</span></label>
                            <div class="aum-input-wrap aum-has-eye"><i class="fas fa-key aum-input-icon"></i><input type="password" name="password_confirm" id="aumStaffPasswordConfirm" class="aum-control" placeholder="Re-enter the password" autocomplete="new-password"><button type="button" class="aum-eye-btn" data-eye-for="aumStaffPasswordConfirm" title="Show password" aria-label="Show password"><i class="fas fa-eye"></i></button></div>
                            <small id="aumPasswordMatchHint" class="aum-help-text" style="display:none;"></small>
                        </div>
                    </div>
                </div>

                <div class="aum-actions">
                    <div class="aum-actions-right">
                        <button type="button" class="aum-btn-secondary" onclick="closeAddUserModal()"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" class="aum-btn-primary" id="aumSubmitBtn"><i class="fas fa-save"></i> Save User</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="assests/css/add_user_modal.css">

<script src="assests/js/input_guards.js"></script>
<script src="assests/js/add_user_modal.js"></script>