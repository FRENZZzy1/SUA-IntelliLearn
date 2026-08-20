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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Attendance · SUA IntelliLearn</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
:root {
  --primary: #1b4332;
  --primary-light: #2d6a4f;
  --primary-soft: #40916c;
  --bg: #f8fafc;
  --surface: #ffffff;
  --border: #e5e7eb;
  --border-light: #edf0f2;
  --text: #0f172a;
  --text-secondary: #64748b;
  --danger: #b91c1c;
  --danger-soft: #fef2f2;
  --success: #15803d;
  --warning: #a16207;
  --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
  --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
  --radius: 14px;
  --radius-sm: 10px;
  --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.att-page { max-width: 1400px; margin: auto; padding: 0 16px 40px; }

/* Header */
.att-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}
.att-head h1 { margin: 0; font-size: clamp(1.4rem, 3vw, 1.8rem); letter-spacing: -0.02em; }
.att-sub { color: var(--text-secondary); margin: 6px 0 0; font-size: 0.95rem; }
.sy-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--surface);
  border: 1px solid var(--border);
  padding: 8px 16px;
  border-radius: 999px;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--primary);
  box-shadow: var(--shadow-sm);
}

/* Stats Grid */
.att-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 14px;
  margin-bottom: 24px;
}
.att-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 20px;
  box-shadow: var(--shadow-sm);
  transition: var(--transition);
  cursor: default;
  position: relative;
  overflow: hidden;
}
.att-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: var(--primary-soft);
  opacity: 0;
  transition: var(--transition);
}
.att-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}
.att-card:hover::before { opacity: 1; }
.att-card .num {
  font-size: clamp(1.5rem, 3vw, 1.8rem);
  font-weight: 700;
  color: var(--text);
  line-height: 1.2;
}
.att-card .label {
  display: block;
  color: var(--text-secondary);
  font-size: 0.8rem;
  margin-top: 6px;
  font-weight: 500;
}
.att-card .icon {
  position: absolute;
  top: 16px; right: 16px;
  width: 32px; height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  opacity: 0.15;
  color: var(--primary);
  background: var(--primary);
  transition: var(--transition);
}
.att-card:hover .icon { opacity: 0.25; transform: scale(1.1); }

/* Panels */
.att-panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 22px;
  box-shadow: var(--shadow-sm);
  transition: var(--transition);
}
.att-panel:hover { box-shadow: var(--shadow); }
.att-panel h2 {
  font-size: 1.05rem;
  margin: 0 0 18px;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text);
}
.att-panel h2 i { color: var(--primary-light); }

/* Layout */
.att-layout {
  display: grid;
  grid-template-columns: 1.5fr 1fr;
  gap: 18px;
  margin-bottom: 18px;
}

/* Search */
.search-wrap {
  position: relative;
  margin-bottom: 14px;
}
.search-wrap i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-secondary);
  font-size: 0.85rem;
}
.search-wrap input {
  width: 100%;
  padding: 10px 12px 10px 36px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  font-size: 0.9rem;
  background: var(--bg);
  transition: var(--transition);
  outline: none;
  -webkit-appearance: none;
}
.search-wrap input:focus {
  border-color: var(--primary-soft);
  box-shadow: 0 0 0 3px rgb(45 106 79 / 0.1);
  background: var(--surface);
}

/* Class List */
.class-list { display: grid; gap: 10px; }
.class-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  border: 1px solid var(--border-light);
  border-radius: var(--radius-sm);
  padding: 14px 16px;
  transition: var(--transition);
  cursor: pointer;
  background: var(--surface);
  position: relative;
}
.class-row:hover {
  border-color: var(--primary-soft);
  background: #f0fdf4;
  transform: translateX(3px);
}
.class-row:active { transform: translateX(3px) scale(0.995); }
.class-name { font-weight: 600; font-size: 0.95rem; }
.class-meta { font-size: 0.8rem; color: var(--text-secondary); margin-top: 3px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.class-meta .badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 600;
  background: #f1f5f9;
  color: var(--text-secondary);
}
.class-actions { display: flex; align-items: center; gap: 14px; }
.rate-pill {
  display: inline-flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}
.rate-pill .rate { font-weight: 700; font-size: 1rem; }
.rate-pill .rate-bar {
  width: 60px;
  height: 4px;
  background: var(--border-light);
  border-radius: 2px;
  overflow: hidden;
}
.rate-pill .rate-bar > div {
  height: 100%;
  border-radius: 2px;
  transition: width 0.6s ease;
}
.btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 14px;
  border-radius: 8px;
  background: var(--primary);
  color: #fff;
  text-decoration: none;
  font-size: 0.8rem;
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: var(--transition);
  white-space: nowrap;
  min-height: 40px;
  justify-content: center;
}
.btn:hover { background: var(--primary-light); transform: translateY(-1px); box-shadow: var(--shadow); }
.btn:active { transform: translateY(0); }

/* Trend Chart */
.trend-wrap { position: relative; }
.trend {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  height: 200px;
  padding: 10px 0 30px;
}
.bar-wrap {
  flex: 1;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
  position: relative;
  cursor: pointer;
  padding: 4px;
  border-radius: 8px;
  transition: var(--transition);
}
.bar-wrap:hover { background: #f0fdf4; }
.bar-wrap .tooltip {
  position: absolute;
  bottom: calc(100% + 8px);
  left: 50%;
  transform: translateX(-50%) scale(0.9);
  background: var(--text);
  color: #fff;
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: var(--transition);
  z-index: 10;
}
.bar-wrap .tooltip::after {
  content: '';
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-top-color: var(--text);
}
.bar-wrap:hover .tooltip { opacity: 1; transform: translateX(-50%) scale(1); }
.bar {
  width: 100%;
  max-width: 44px;
  background: linear-gradient(180deg, var(--primary-soft) 0%, var(--primary-light) 100%);
  border-radius: 6px 6px 2px 2px;
  min-height: 4px;
  transition: height 0.6s cubic-bezier(0.34, 1.56, 0.64, 1), filter 0.2s;
  position: relative;
}
.bar-wrap:hover .bar { filter: brightness(1.1); }
.bar-label { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; }
.bar-value { font-size: 0.75rem; font-weight: 700; color: var(--primary); }

/* At Risk Table */
.table-wrap {
  overflow-x: auto;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-light);
  -webkit-overflow-scrolling: touch;
}
.table-wrap::-webkit-scrollbar { height: 6px; }
.table-wrap::-webkit-scrollbar-track { background: transparent; }
.table-wrap::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
.risk-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 0.85rem;
  min-width: 520px;
}
.risk-table th {
  background: #f8fafc;
  color: var(--text-secondary);
  font-weight: 600;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 12px 14px;
  text-align: left;
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 1;
}
.risk-table td {
  padding: 14px;
  border-bottom: 1px solid var(--border-light);
  vertical-align: middle;
  transition: background 0.15s;
}
.risk-table tbody tr { transition: var(--transition); }
.risk-table tbody tr:hover td { background: #f8fafc; }
.risk-table tbody tr:last-child td { border-bottom: none; }
.risk-table .student-cell { font-weight: 600; color: var(--text); }
.risk-table .class-cell { color: var(--text-secondary); font-size: 0.8rem; }
.risk-table .rate-cell { font-weight: 700; }
.risk-table .rate-cell .mini-bar {
  width: 50px;
  height: 4px;
  background: var(--border-light);
  border-radius: 2px;
  margin-top: 4px;
  overflow: hidden;
}
.risk-table .rate-cell .mini-bar > div {
  height: 100%;
  border-radius: 2px;
  background: var(--danger);
}
.risk { color: var(--danger); font-weight: 700; font-size: 0.75rem; }
.risk-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: var(--danger-soft);
  color: var(--danger);
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

/* Empty States */
.empty {
  color: var(--text-secondary);
  padding: 40px 20px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}
.empty i { font-size: 2rem; opacity: 0.3; color: var(--primary); }
.empty p { margin: 0; font-size: 0.9rem; }

/* Date Picker */
.date-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.date-bar input[type="date"] {
  padding: 8px 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  font-size: 0.9rem;
  font-family: inherit;
  background: var(--surface);
  color: var(--text);
  outline: none;
  transition: var(--transition);
  -webkit-appearance: none;
}
.date-bar input[type="date"]:focus {
  border-color: var(--primary-soft);
  box-shadow: 0 0 0 3px rgb(45 106 79 / 0.1);
}
.date-bar .date-nav {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-secondary);
  cursor: pointer;
  transition: var(--transition);
  font-size: 0.8rem;
}
.date-bar .date-nav:hover { background: var(--bg); color: var(--primary); border-color: var(--primary-soft); }
.date-bar .date-nav:active { transform: scale(0.95); }

/* Animations */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes countUp {
  from { opacity: 0; transform: scale(0.8); }
  to { opacity: 1; transform: scale(1); }
}
.animate-in { animation: fadeUp 0.5s ease forwards; opacity: 0; }
.animate-in:nth-child(1) { animation-delay: 0.05s; }
.animate-in:nth-child(2) { animation-delay: 0.1s; }
.animate-in:nth-child(3) { animation-delay: 0.15s; }
.animate-in:nth-child(4) { animation-delay: 0.2s; }
.animate-in:nth-child(5) { animation-delay: 0.25s; }

/* Responsive */
@media (max-width: 1024px) {
  .att-grid { grid-template-columns: repeat(3, 1fr); }
  .att-layout { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .att-page { padding: 0 12px 30px; }
  .att-head { flex-direction: column; align-items: flex-start; }
  .att-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .att-card { padding: 16px; }
  .att-card .num { font-size: 1.4rem; }
  .att-panel { padding: 16px; }
  .class-row { flex-wrap: wrap; gap: 10px; padding: 12px; }
  .class-actions { width: 100%; justify-content: space-between; }
  .rate-pill { flex-direction: row; align-items: center; gap: 8px; }
  .rate-pill .rate-bar { width: 80px; }
  .btn { flex: 1; justify-content: center; min-height: 44px; }
  .trend { height: 160px; gap: 6px; }
  .risk-table th, .risk-table td { padding: 10px 12px; }
}
@media (max-width: 480px) {
  .att-grid { grid-template-columns: 1fr 1fr; }
  .att-card { padding: 14px; }
  .att-card .icon { display: none; }
  .bar { max-width: 32px; }
  .trend { height: 140px; }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
  * { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
}

/* Print */
@media print {
  .att-head .sy-badge, .btn, .search-wrap, .date-bar { display: none !important; }
  .att-card, .att-panel { box-shadow: none; border: 1px solid #ddd; }
}
</style>
</head>
<body>
<?php include '../../includes/teachers_sidebar.php'; ?>
<main class="main-content" id="dashMain">
<?php include '../../includes/teacher_header.php'; ?>
<div class="att-page">

  <!-- Header -->
  <div class="att-head animate-in">
    <div>
      <h1>Attendance</h1>
      <p class="att-sub">Manage attendance and monitor student attendance performance.</p>
    </div>
    <div class="sy-badge">
      <i class="fas fa-calendar-day"></i>
      <?= htmlspecialchars($schoolYearLabel) ?>
    </div>
  </div>

  <!-- Date Navigation -->
  <div class="date-bar animate-in">
    <button class="date-nav" onclick="changeDate(-1)" aria-label="Previous day"><i class="fas fa-chevron-left"></i></button>
    <input type="date" id="datePicker" value="<?= htmlspecialchars($selectedDate) ?>" onchange="goToDate(this.value)">
    <button class="date-nav" onclick="changeDate(1)" aria-label="Next day"><i class="fas fa-chevron-right"></i></button>
    <button class="date-nav" onclick="goToDate('<?= date('Y-m-d') ?>')" title="Today" aria-label="Go to today"><i class="fas fa-calendar-check"></i></button>
  </div>

  <!-- Stats Cards -->
  <div class="att-grid">
    <div class="att-card animate-in">
      <div class="icon"><i class="fas fa-chart-line"></i></div>
      <div class="num" data-count="<?= $attendanceRate ?>"><?= $attendanceRate ?>%</div>
      <span class="label">Overall Attendance</span>
    </div>
    <div class="att-card animate-in">
      <div class="icon"><i class="fas fa-check-circle"></i></div>
      <div class="num" data-count="<?= $present ?>"><?= $present ?></div>
      <span class="label">Present</span>
    </div>
    <div class="att-card animate-in">
      <div class="icon"><i class="fas fa-times-circle"></i></div>
      <div class="num" data-count="<?= $absent ?>"><?= $absent ?></div>
      <span class="label">Absent</span>
    </div>
    <div class="att-card animate-in">
      <div class="icon"><i class="fas fa-clock"></i></div>
      <div class="num" data-count="<?= $late ?>"><?= $late ?></div>
      <span class="label">Late</span>
    </div>
    <div class="att-card animate-in">
      <div class="icon"><i class="fas fa-file-medical"></i></div>
      <div class="num" data-count="<?= $excused ?>"><?= $excused ?></div>
      <span class="label">Excused</span>
    </div>
  </div>

  <!-- Main Layout -->
  <div class="att-layout">
    <!-- Classes Panel -->
    <section class="att-panel animate-in">
      <h2><i class="fas fa-chalkboard"></i> My Classes</h2>
      <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="classSearch" placeholder="Search classes..." oninput="filterClasses(this.value)">
      </div>
      <div class="class-list" id="classList">
        <?php if (!$classes): ?>
          <div class="empty">
            <i class="fas fa-chalkboard"></i>
            <p>No active classes assigned.</p>
          </div>
        <?php endif; ?>
        <?php foreach ($classes as $class):
          $cs = $classStats[$class['offering_id']];
          $barColor = $cs['rate'] >= 90 ? 'var(--success)' : ($cs['rate'] >= 75 ? 'var(--warning)' : 'var(--danger)');
        ?>
        <div class="class-row" data-name="<?= strtolower(htmlspecialchars($class['subject_name'].' '.$class['section_name'].' '.$class['grade_level'])) ?>">
          <div style="min-width:0">
            <div class="class-name"><?= htmlspecialchars($class['subject_name']) ?></div>
            <div class="class-meta">
              <span class="badge">Grade <?= (int)$class['grade_level'] ?></span>
              <span><?= htmlspecialchars($class['section_name']) ?></span>
              <?php if ($class['strand']): ?><span class="badge"><?= htmlspecialchars($class['strand']) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="class-actions">
            <div class="rate-pill">
              <span class="rate" style="color:<?= $barColor ?>"><?= $cs['rate'] ?>%</span>
              <div class="rate-bar"><div style="width:<?= $cs['rate'] ?>%;background:<?= $barColor ?>"></div></div>
            </div>
            <a class="btn" href="class_overview.php?subject_id=<?= (int)$class['subject_id'] ?>&section_id=<?= (int)$class['section_id'] ?>&view=attendance&date=<?= htmlspecialchars($selectedDate) ?>">
              <i class="fas fa-clipboard-check"></i> <span class="btn-text">Manage</span>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Trend Panel -->
    <section class="att-panel animate-in">
      <h2><i class="fas fa-chart-bar"></i> Attendance Trend</h2>
      <?php if (!$trend): ?>
        <div class="empty">
          <i class="fas fa-chart-bar"></i>
          <p>Attendance trends will appear after records are saved.</p>
        </div>
      <?php else: ?>
      <div class="trend-wrap">
        <div class="trend">
          <?php foreach ($trend as $t):
            $h = max(3, min(100, (float)$t['rate']));
            $barColor = $t['rate'] >= 90 ? '#15803d' : ($t['rate'] >= 75 ? '#a16207' : '#b91c1c');
          ?>
          <div class="bar-wrap">
            <div class="tooltip"><?= (float)$t['rate'] ?>% on <?= date('M j, Y', strtotime($t['attendance_date'])) ?></div>
            <span class="bar-value" style="color:<?= $barColor ?>"><?= htmlspecialchars($t['rate']) ?>%</span>
            <div class="bar" style="height:<?= $h ?>%;background:linear-gradient(180deg, <?= $barColor ?> 0%, <?= $barColor ?>cc 100%)"></div>
            <span class="bar-label"><?= date('M j', strtotime($t['attendance_date'])) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </section>
  </div>

  <!-- At Risk Panel -->
  <section class="att-panel animate-in">
    <h2><i class="fas fa-triangle-exclamation"></i> Students Needing Attention</h2>
    <?php if (!$atRisk): ?>
      <div class="empty">
        <i class="fas fa-check-circle"></i>
        <p>No students currently fall below the 75% attendance threshold.</p>
      </div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="risk-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Class</th>
            <th>Attendance</th>
            <th>Absent</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($atRisk as $r):
            $rate = round(((int)$r['present'] + (int)$r['late']) / (int)$r['total'] * 100, 1);
          ?>
          <tr>
            <td class="student-cell"><?= htmlspecialchars($r['student_name']) ?></td>
            <td class="class-cell"><?= htmlspecialchars($r['subject_name']) ?> · <?= htmlspecialchars($r['section_name']) ?></td>
            <td class="rate-cell">
              <?= $rate ?>%
              <div class="mini-bar"><div style="width:<?= $rate ?>%"></div></div>
            </td>
            <td><?= (int)$r['absent'] ?></td>
            <td><span class="risk-badge"><i class="fas fa-exclamation-circle"></i> At Risk</span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

</div>
</main>

<script>
// Date navigation
function goToDate(date) {
  if (!date) return;
  const url = new URL(window.location.href);
  url.searchParams.set('date', date);
  window.location.href = url.toString();
}
function changeDate(days) {
  const picker = document.getElementById('datePicker');
  const d = new Date(picker.value);
  d.setDate(d.getDate() + days);
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  goToDate(`${yyyy}-${mm}-${dd}`);
}

// Class search/filter
function filterClasses(query) {
  const q = query.toLowerCase().trim();
  document.querySelectorAll('.class-row').forEach(row => {
    const name = row.dataset.name || '';
    row.style.display = name.includes(q) ? '' : 'none';
  });
}

// Animate numbers on load
function animateNumbers() {
  document.querySelectorAll('.num[data-count]').forEach(el => {
    const target = parseFloat(el.dataset.count);
    const isPercent = el.textContent.includes('%');
    const duration = 800;
    const start = performance.now();
    const startVal = 0;
    function step(now) {
      const progress = Math.min((now - start) / duration, 1);
      const ease = 1 - Math.pow(1 - progress, 3);
      const current = startVal + (target - startVal) * ease;
      if (isPercent) {
        el.textContent = current.toFixed(1) + '%';
      } else {
        el.textContent = Math.round(current);
      }
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  });
}

// Animate bars on scroll
function animateBars() {
  document.querySelectorAll('.bar').forEach(bar => {
    const h = bar.style.height;
    bar.style.height = '0%';
    setTimeout(() => { bar.style.height = h; }, 100);
  });
  document.querySelectorAll('.rate-bar > div').forEach(bar => {
    const w = bar.style.width;
    bar.style.width = '0%';
    setTimeout(() => { bar.style.width = w; }, 100);
  });
}

// Keyboard shortcuts
document.addEventListener('keydown', e => {
  if (e.target.tagName === 'INPUT') return;
  if (e.key === 'ArrowLeft') changeDate(-1);
  if (e.key === 'ArrowRight') changeDate(1);
  if (e.key === 't' || e.key === 'T') goToDate(new Date().toISOString().split('T')[0]);
  if (e.key === '/' || e.key === 's' || e.key === 'S') {
    e.preventDefault();
    document.getElementById('classSearch').focus();
  }
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  animateNumbers();
  animateBars();
});
</script>
</body>
</html>