<?php
require_once __DIR__ . '/../../config/config.php';

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') { header('Location: ../login.php'); exit(); }
$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();
if (!$teacher) die('Teacher record not found for this account.');
$teacherId = (int) $teacher['teacher_id'];

$schoolYear = $pdo->query("SELECT school_year_id, label FROM schoolyears WHERE is_current = 1 LIMIT 1")->fetch();
$schoolYearId = $schoolYear['school_year_id'] ?? null;
$schoolYearLabel = $schoolYear['label'] ?? 'Current School Year';
$selectedDate = $_GET['date'] ?? date('Y-m-d');
if (!DateTime::createFromFormat('Y-m-d', $selectedDate)) $selectedDate = date('Y-m-d');

$stmt = $pdo->prepare("SELECT co.offering_id, co.subject_id, co.section_id, s.subject_name, sec.section_name, sec.grade_level, sec.strand
FROM classofferings co JOIN subjects s ON s.subject_id = co.subject_id JOIN sections sec ON sec.section_id = co.section_id
WHERE co.teacher_id = ? AND co.status = 'active' AND (co.school_year_id = ? OR ? IS NULL)
ORDER BY sec.grade_level, sec.section_name, s.subject_name");
$stmt->execute([$teacherId, $schoolYearId, $schoolYearId]);
$classes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) total_records, SUM(a.status = 'Present') present_count, SUM(a.status = 'Absent') absent_count, SUM(a.status = 'Late') late_count, SUM(a.status = 'Excused') excused_count
FROM attendance a JOIN classofferings co ON co.offering_id = a.offering_id
WHERE co.teacher_id = ? AND co.status = 'active' AND (co.school_year_id = ? OR ? IS NULL)");
$stmt->execute([$teacherId, $schoolYearId, $schoolYearId]);
$overall = $stmt->fetch() ?: [];
$totalRecords = (int)($overall['total_records'] ?? 0);
$present = (int)($overall['present_count'] ?? 0); $absent = (int)($overall['absent_count'] ?? 0); $late = (int)($overall['late_count'] ?? 0); $excused = (int)($overall['excused_count'] ?? 0);
$attendanceRate = $totalRecords ? round((($present + $late) / $totalRecords) * 100, 1) : 0;

$classStats = [];
foreach ($classes as $class) {
    $stmt = $pdo->prepare("SELECT COUNT(*) total, SUM(status='Present') present, SUM(status='Absent') absent, SUM(status='Late') late, SUM(status='Excused') excused FROM attendance WHERE offering_id = ?");
    $stmt->execute([(int)$class['offering_id']]);
    $stat = $stmt->fetch() ?: []; $total = (int)($stat['total'] ?? 0);
    $classStats[$class['offering_id']] = ['total'=>$total,'present'=>(int)($stat['present']??0),'absent'=>(int)($stat['absent']??0),'late'=>(int)($stat['late']??0),'excused'=>(int)($stat['excused']??0),'rate'=>$total ? round(((($stat['present']??0)+($stat['late']??0))/$total)*100,1) : 0];
}

// MySQL does not allow SELECT aliases such as `present` or `total` to be
// referenced as expressions in HAVING. Use the aggregate expressions directly.
$atRiskStmt = $pdo->prepare("SELECT st.student_id, CONCAT(st.firstname, ' ', st.lastname) student_name,
sec.section_name, s.subject_name, COUNT(a.attendance_id) total,
SUM(a.status='Present') present, SUM(a.status='Late') late, SUM(a.status='Absent') absent
FROM attendance a JOIN students st ON st.student_id = a.student_id JOIN classofferings co ON co.offering_id = a.offering_id
JOIN subjects s ON s.subject_id = co.subject_id JOIN sections sec ON sec.section_id = co.section_id
WHERE co.teacher_id = ? AND co.status = 'active' AND (co.school_year_id = ? OR ? IS NULL)
GROUP BY st.student_id, st.firstname, st.lastname, sec.section_name, s.subject_name
HAVING COUNT(a.attendance_id) > 0 AND ((SUM(a.status='Present') + SUM(a.status='Late')) / COUNT(a.attendance_id)) < 0.75
ORDER BY ((SUM(a.status='Present') + SUM(a.status='Late')) / COUNT(a.attendance_id)) ASC, SUM(a.status='Absent') DESC LIMIT 10");
$atRiskStmt->execute([$teacherId, $schoolYearId, $schoolYearId]);
$atRisk = $atRiskStmt->fetchAll();

$trendStmt = $pdo->prepare("SELECT a.attendance_date, ROUND(100 * SUM(a.status IN ('Present','Late')) / COUNT(*), 1) rate
FROM attendance a JOIN classofferings co ON co.offering_id = a.offering_id
WHERE co.teacher_id = ? AND co.status = 'active' AND (co.school_year_id = ? OR ? IS NULL)
GROUP BY a.attendance_date ORDER BY a.attendance_date DESC LIMIT 7");
$trendStmt->execute([$teacherId, $schoolYearId, $schoolYearId]);
$trend = array_reverse($trendStmt->fetchAll());
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Attendance · SUA IntelliLearn</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/dashboard.css">
<style>
.att-page{max-width:1400px;margin:auto;padding-bottom:40px}.att-head{display:flex;justify-content:space-between;align-items:end;margin-bottom:22px}.att-head h1{margin:0}.att-sub{color:#64748b;margin:5px 0 0}.att-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px}.att-card,.att-panel{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 2px 8px rgba(0,0,0,.04)}.att-card .num{font-size:28px;font-weight:700}.att-card .label{display:block;color:#64748b;font-size:13px;margin-top:5px}.att-layout{display:grid;grid-template-columns:1.5fr 1fr;gap:18px;margin-bottom:18px}.att-panel h2{font-size:17px;margin:0 0 16px}.class-list{display:grid;gap:10px}.class-row{display:flex;justify-content:space-between;align-items:center;border:1px solid #edf0f2;border-radius:10px;padding:13px}.class-name{font-weight:600}.class-meta{font-size:12px;color:#64748b;margin-top:3px}.rate{font-weight:700}.btn{display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border-radius:8px;background:#1b4332;color:#fff;text-decoration:none;font-size:13px}.risk-table{width:100%;border-collapse:collapse}.risk-table th,.risk-table td{text-align:left;padding:10px;border-bottom:1px solid #eef0f2;font-size:13px}.risk{color:#b91c1c;font-weight:700}.trend{display:flex;align-items:end;gap:10px;height:180px}.bar-wrap{flex:1;height:100%;display:flex;flex-direction:column;justify-content:end;align-items:center;gap:6px}.bar{width:100%;max-width:42px;background:#2d6a4f;border-radius:6px 6px 2px 2px;min-height:3px}.bar-label{font-size:11px;color:#64748b}.bar-value{font-size:11px;font-weight:600}.empty{color:#64748b;padding:20px;text-align:center}@media(max-width:900px){.att-grid{grid-template-columns:repeat(2,1fr)}.att-layout{grid-template-columns:1fr}}@media(max-width:600px){.att-grid{grid-template-columns:1fr}.att-head{display:block}.class-row{gap:10px;flex-wrap:wrap}}
</style></head><body>
<?php include '../../includes/teachers_sidebar.php'; ?><main class="main-content" id="dashMain"><?php include '../../includes/teacher_header.php'; ?><div class="att-page">
<div class="att-head"><div><h1>Attendance</h1><p class="att-sub">Manage attendance and monitor student attendance performance.</p></div><strong><?= htmlspecialchars($schoolYearLabel) ?></strong></div>
<div class="att-grid"><div class="att-card"><div class="num"><?= $attendanceRate ?>%</div><span class="label">Overall Attendance</span></div><div class="att-card"><div class="num"><?= $present ?></div><span class="label">Present</span></div><div class="att-card"><div class="num"><?= $absent ?></div><span class="label">Absent</span></div><div class="att-card"><div class="num"><?= $late ?></div><span class="label">Late</span></div><div class="att-card"><div class="num"><?= $excused ?></div><span class="label">Excused</span></div></div>
<div class="att-layout"><section class="att-panel"><h2>My Classes</h2><div class="class-list"><?php if (!$classes): ?><div class="empty">No active classes assigned.</div><?php endif; ?>
<?php foreach ($classes as $class): $cs=$classStats[$class['offering_id']]; ?><div class="class-row"><div><div class="class-name"><?= htmlspecialchars($class['subject_name']) ?></div><div class="class-meta">Grade <?= (int)$class['grade_level'] ?> · <?= htmlspecialchars($class['section_name']) ?></div></div><div><span class="rate"><?= $cs['rate'] ?>%</span> <a class="btn" href="class_overview.php?subject_id=<?= (int)$class['subject_id'] ?>&section_id=<?= (int)$class['section_id'] ?>&view=attendance&date=<?= htmlspecialchars($selectedDate) ?>"><i class="fas fa-clipboard-check"></i> Manage</a></div></div><?php endforeach; ?></div></section>
<section class="att-panel"><h2>Attendance Trend</h2><?php if (!$trend): ?><div class="empty">Attendance trends will appear after records are saved.</div><?php else: ?><div class="trend"><?php foreach($trend as $t): ?><div class="bar-wrap"><span class="bar-value"><?= htmlspecialchars($t['rate']) ?>%</span><div class="bar" style="height:<?= max(3,min(100,(float)$t['rate'])) ?>%"></div><span class="bar-label"><?= date('M j',strtotime($t['attendance_date'])) ?></span></div><?php endforeach; ?></div><?php endif; ?></section></div>
<section class="att-panel"><h2><i class="fas fa-triangle-exclamation"></i> Students Needing Attention</h2><?php if (!$atRisk): ?><div class="empty">No students currently fall below the 75% attendance threshold.</div><?php else: ?><div style="overflow-x:auto"><table class="risk-table"><thead><tr><th>Student</th><th>Class</th><th>Attendance</th><th>Absent</th><th>Status</th></tr></thead><tbody><?php foreach($atRisk as $r): $rate=round(((int)$r['present']+(int)$r['late'])/(int)$r['total']*100,1); ?><tr><td><?= htmlspecialchars($r['student_name']) ?></td><td><?= htmlspecialchars($r['subject_name'].' · '.$r['section_name']) ?></td><td><?= $rate ?>%</td><td><?= (int)$r['absent'] ?></td><td class="risk">At Risk</td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</div></main></body></html>