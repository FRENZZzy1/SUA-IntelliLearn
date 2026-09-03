<?php
/**
 * Admin bulk enrollment page.
 * Allows selecting multiple students and enrolling them into the same
 * section/classes in one submission.
 */
require_once __DIR__ . '/../../config/config.php';
requireAdmin();

$csrfToken = generateCSRFToken();

$allStudents = $pdo->query("SELECT student_id, firstname, lastname, student_lrn FROM students ORDER BY lastname, firstname")->fetchAll();
$studentsJson = json_encode(array_map(function ($s) {
    return [
        'id' => (int) $s['student_id'],
        'name' => trim($s['firstname'] . ' ' . $s['lastname']),
        'label' => $s['lastname'] . ', ' . $s['firstname'] . ($s['student_lrn'] ? ' — LRN ' . $s['student_lrn'] : ''),
        'lrn' => (string) $s['student_lrn'],
    ];
}, $allStudents));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bulk Enrollment | SUA IntelliLearn Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assests/css/courses.css">
<link rel="stylesheet" href="assests/css/add_course.css">
<link rel="stylesheet" href="assests/css/enrollment.css">
<style>
.bulk-page{max-width:1100px;margin:0 auto;padding-bottom:40px}.bulk-card{background:#fff;border-radius:14px;padding:24px;box-shadow:0 4px 20px rgba(0,0,0,.06);margin-top:20px}.bulk-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.bulk-field{margin-bottom:18px}.bulk-field label{display:block;font-weight:600;margin-bottom:8px}.bulk-field select,.bulk-search{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #ddd;border-radius:8px;background:#fff}.student-picker{border:1px solid #ddd;border-radius:8px;padding:10px;background:#fff}.student-search{border:0;outline:0;width:100%;font:inherit;padding:4px}.student-list{max-height:280px;overflow:auto;margin-top:8px;border-top:1px solid #eee}.student-item{display:flex;align-items:center;gap:10px;padding:9px 4px;border-bottom:1px solid #f1f1f1}.student-item small{display:block;color:#777}.student-toolbar{display:flex;justify-content:space-between;align-items:center;margin-top:8px;font-size:13px}.subject-list{border:1px solid #ddd;border-radius:8px;max-height:280px;overflow:auto}.subject-item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;border-bottom:1px solid #eee}.subject-item:last-child{border-bottom:0}.subject-item small{color:#777}.bulk-actions{display:flex;gap:10px;align-items:center}.bulk-message{margin-top:15px;padding:12px;border-radius:8px;display:none}.bulk-message.show{display:block}.bulk-message.error{background:#fff0f0;color:#9b1c1c}.bulk-message.success{background:#effaf1;color:#176b2c}.result-list{margin:8px 0 0;padding-left:20px}.student-count{font-weight:700}
@media(max-width:800px){.bulk-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include '../../includes/admin_sidebar.php'; ?>
<?php include '../../includes/admin_header.php'; ?>
<div class="main-content" id="mainContent">
<div class="bulk-page">
    <div class="page-header"><div class="page-header-text"><h1>Bulk Enrollment</h1><p>Select multiple students and enroll them into the same section and subjects.</p></div></div>
    <div class="bulk-card">
        <form id="bulkEnrollmentForm">
            <input type="hidden" name="csrf" value="<?= clean($csrfToken) ?>">
            <div class="bulk-field">
                <label>Students <span class="student-count" id="studentCount">0 selected</span></label>
                <div class="student-picker">
                    <input class="student-search" id="studentSearch" type="text" placeholder="Search by name or LRN..." autocomplete="off">
                    <div class="student-toolbar"><span>Select the students to enroll.</span><span class="bulk-actions"><button type="button" class="link-btn" id="checkAllStudents">Select all</button><button type="button" class="link-btn" id="clearStudents">Clear</button></span></div>
                    <div class="student-list" id="studentList"></div>
                </div>
            </div>
            <div class="bulk-grid">
                <div>
                    <div class="bulk-field"><label for="gradeLevel">Grade Level</label><select id="gradeLevel" required><option value="">Select grade</option><?php foreach([7,8,9,10,11,12] as $g): ?><option value="<?= $g ?>">Grade <?= $g ?></option><?php endforeach; ?></select></div>
                    <div class="bulk-field"><label for="strand">Strand</label><select id="strand"><option value="">None</option><?php foreach(['STEM','ABM','HUMSS','TVL'] as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?></select></div>
                    <div class="bulk-field"><label for="term">Term</label><select id="term" required><option value="">Select term</option><?php foreach(['TRM 1','TRM 2','TRM 3'] as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?></select></div>
                    <div class="bulk-field"><label for="section">Section</label><select id="section" disabled required><option value="">Select grade, strand and term first</option></select></div>
                </div>
                <div>
                    <div class="bulk-field"><label>Subjects</label><div class="bulk-actions" style="margin-bottom:8px"><button type="button" class="link-btn" id="checkAllSubjects">Check all</button><button type="button" class="link-btn" id="clearSubjects">Uncheck all</button></div><div class="subject-list" id="subjectList"><div style="padding:20px;text-align:center;color:#777">Select a section first.</div></div></div>
                </div>
            </div>
            <div class="bulk-message" id="bulkMessage"></div>
            <div class="modal-footer" style="margin-top:20px;padding:0;border:0"><a class="btn-secondary" href="enrollment.php">Back to Enrollment</a><button type="submit" class="btn-primary" id="submitBulk"><i class="fas fa-users"></i> Enroll Selected Students</button></div>
        </form>
    </div>
</div></div>
<script>
const CSRF = <?= json_encode($csrfToken) ?>;
const STUDENTS = <?= $studentsJson ?>;
const selectedStudents = new Set();
const studentList = document.getElementById('studentList');
const studentSearch = document.getElementById('studentSearch');
const studentCount = document.getElementById('studentCount');
const grade = document.getElementById('gradeLevel');
const strand = document.getElementById('strand');
const term = document.getElementById('term');
const section = document.getElementById('section');
const subjectList = document.getElementById('subjectList');
const message = document.getElementById('bulkMessage');

function renderStudents(){
    const q=studentSearch.value.trim().toLowerCase();
    const matches=STUDENTS.filter(s=>!q||s.name.toLowerCase().includes(q)||s.lrn.toLowerCase().includes(q));
    studentList.innerHTML=matches.map(s=>`<label class="student-item"><input type="checkbox" value="${s.id}" ${selectedStudents.has(String(s.id))?'checked':''}><span>${escapeHtml(s.label)}<small>${escapeHtml(s.name)}</small></span></label>`).join('') || '<div style="padding:18px;text-align:center;color:#777">No students found.</div>';
    studentList.querySelectorAll('input[type=checkbox]').forEach(cb=>cb.addEventListener('change',()=>{cb.checked?selectedStudents.add(cb.value):selectedStudents.delete(cb.value);updateStudentCount();}));
}
function updateStudentCount(){studentCount.textContent=`${selectedStudents.size} selected`;}
function escapeHtml(v){return String(v).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
studentSearch.addEventListener('input',renderStudents);
document.getElementById('checkAllStudents').addEventListener('click',()=>{STUDENTS.forEach(s=>{if(!studentSearch.value||s.name.toLowerCase().includes(studentSearch.value.toLowerCase())||s.lrn.toLowerCase().includes(studentSearch.value.toLowerCase()))selectedStudents.add(String(s.id));});renderStudents();updateStudentCount();});
document.getElementById('clearStudents').addEventListener('click',()=>{selectedStudents.clear();renderStudents();updateStudentCount();});

async function loadSections(){
    subjectList.innerHTML='<div style="padding:20px;text-align:center;color:#777">Select a section first.</div>';
    if(!grade.value||!term.value){section.disabled=true;section.innerHTML='<option value="">Select grade, strand and term first</option>';return;}
    section.disabled=true;section.innerHTML='<option value="">Loading sections...</option>';
    const p=new URLSearchParams({grade_level:grade.value,term:term.value,strand:strand.value});
    try{const r=await fetch('get_offering_sections.php?'+p);const d=await r.json();if(!d.success)throw new Error((d.errors||['Unable to load sections.']).join(' '));section.innerHTML='<option value="">Select a section</option>'+d.options.map(o=>`<option value="${o.section_id}">${escapeHtml(o.label)}</option>`).join('');section.disabled=d.options.length===0;if(!d.options.length)section.innerHTML='<option value="">No open sections</option>';}catch(e){section.innerHTML='<option value="">Unable to load sections</option>';showMessage(e.message,true);}}
async function loadSubjects(){
    subjectList.innerHTML='<div style="padding:20px;text-align:center;color:#777">Loading subjects...</div>';
    if(!section.value){subjectList.innerHTML='<div style="padding:20px;text-align:center;color:#777">Select a section first.</div>';return;}
    const p=new URLSearchParams({section_id:section.value,grade_level:grade.value,term:term.value,strand:strand.value});
    try{const r=await fetch('get_section_offerings.php?'+p);const d=await r.json();if(!d.success)throw new Error((d.errors||['Unable to load subjects.']).join(' '));subjectList.innerHTML=d.options.map(o=>`<label class="subject-item"><span><input type="checkbox" name="offering" value="${o.offering_id}" data-subject-id="${o.subject_id}" ${o.full?'disabled':''} ${o.full?'':'checked'}> ${escapeHtml(o.subject_name)}</span><small>${o.full?'Full':escapeHtml(String(o.seats_left))+' seat(s) left'}</small></label>`).join('')||'<div style="padding:20px;text-align:center;color:#777">No subjects available.</div>';}catch(e){showMessage(e.message,true);subjectList.innerHTML='<div style="padding:20px;text-align:center;color:#777">Unable to load subjects.</div>';}}
[grade,strand,term].forEach(e=>e.addEventListener('change',loadSections));section.addEventListener('change',loadSubjects);
document.getElementById('checkAllSubjects').addEventListener('click',()=>subjectList.querySelectorAll('input[name=offering]:not(:disabled)').forEach(x=>x.checked=true));
document.getElementById('clearSubjects').addEventListener('click',()=>subjectList.querySelectorAll('input[name=offering]').forEach(x=>x.checked=false));
function showMessage(text,error=false){message.className='bulk-message show '+(error?'error':'success');message.innerHTML=text;}
document.getElementById('bulkEnrollmentForm').addEventListener('submit',async e=>{
    e.preventDefault();message.className='bulk-message';
    if(selectedStudents.size===0)return showMessage('Select at least one student.',true);
    if(!section.value)return showMessage('Select a section.',true);
    const subjects=[...subjectList.querySelectorAll('input[name=offering]:checked')];if(!subjects.length)return showMessage('Select at least one subject.',true);
    const btn=document.getElementById('submitBulk');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Enrolling...';
    const fd=new FormData();fd.append('csrf',CSRF);fd.append('grade_level',grade.value);fd.append('strand',strand.value);fd.append('offering_id',section.value);selectedStudents.forEach(id=>fd.append('student_ids[]',id));subjects.forEach(x=>fd.append('offering_ids[]',x.value));
    try{const r=await fetch('add_bulk_enrollment_request.php',{method:'POST',headers:{Accept:'application/json'},body:fd});const d=await r.json();if(!d.success){throw new Error((d.errors||['Bulk enrollment failed.']).join('<br>'));}let html=`<strong>${d.summary.success} student(s) processed successfully.</strong>`;if(d.summary.failed){html+=`<ul class="result-list">${d.failures.map(f=>`<li><strong>${escapeHtml(f.student)}</strong>: ${escapeHtml(f.errors.join(' '))}</li>`).join('')}</ul>`;}showMessage(html,!d.summary.success);selectedStudents.clear();renderStudents();updateStudentCount();}catch(e){showMessage(e.message,true);}finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-users"></i> Enroll Selected Students';}
});
renderStudents();updateStudentCount();
</script>
</body>
</html>
