<?php
// public/teacher/assets/api/export_gradebook.php?offering_id=12
// Exports one class's gradebook (one term) to a modern-styled .xlsx file.
// Uses the same writer/format as the admin exports (export_xlsx_modern), and
// the same grade math as grade_book.php / grade_finalize.php.
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../admin/assests/api/lib/xlsx_writer.php';

requireTeacher();

$userId     = (int) $_SESSION['user_id'];
$offeringId = isset($_GET['offering_id']) && ctype_digit($_GET['offering_id']) ? (int) $_GET['offering_id'] : 0;

if ($offeringId <= 0) {
    http_response_code(400);
    exit('Missing or invalid offering_id.');
}

// ---- Resolve the logged-in teacher -----------------------------------------
$stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();
if (!$teacher) {
    http_response_code(403);
    exit('Teacher record not found for this account.');
}
$teacherId   = (int) $teacher['teacher_id'];
$teacherName = trim($teacher['firstname'] . ' ' . $teacher['lastname']);

// ---- The class must belong to this teacher (authorization) ------------------
$stmt = $pdo->prepare("
    SELECT co.offering_id, co.quarter,
           sub.subject_name, sec.section_name, sec.grade_level, sec.strand,
           sy.label AS school_year_label
    FROM classofferings co
    JOIN subjects sub ON sub.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    LEFT JOIN schoolyears sy ON sy.school_year_id = co.school_year_id
    WHERE co.offering_id = ? AND co.teacher_id = ? AND co.status = 'active'
    LIMIT 1
");
$stmt->execute([$offeringId, $teacherId]);
$class = $stmt->fetch();
if (!$class) {
    http_response_code(403);
    exit('Class not found or you do not have access to it.');
}

// ---- Active students ---------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT e.enrollment_id, s.student_id, s.student_lrn, s.firstname, s.lastname, s.middlename
    FROM enrollments e
    JOIN students s ON s.student_id = e.student_id
    WHERE e.offering_id = ? AND e.status = 'enrolled'
    ORDER BY s.lastname, s.firstname
");
$stmt->execute([$offeringId]);
$students = $stmt->fetchAll();

// ---- Assignments (latest submission per student) ----------------------------
$stmt = $pdo->prepare("
    SELECT a.assignment_id, a.title, a.points, a.type, sub.student_id, sub.score
    FROM assignments a
    LEFT JOIN submissions sub ON sub.assignment_id = a.assignment_id
        AND sub.attempt_number = (
            SELECT MAX(x.attempt_number) FROM submissions x
            WHERE x.assignment_id = a.assignment_id AND x.student_id = sub.student_id
        )
    WHERE a.offering_id = ?
    ORDER BY a.created_at, a.assignment_id
");
$stmt->execute([$offeringId]);
$assignments = [];
$aScores     = [];
foreach ($stmt->fetchAll() as $r) {
    $id = (int) $r['assignment_id'];
    if (!isset($assignments[$id])) {
        $assignments[$id] = ['id' => $id, 'title' => $r['title'], 'points' => (float) $r['points'], 'type' => $r['type'] ?: 'Activity'];
    }
    if ($r['student_id'] !== null) {
        $aScores[(int) $r['student_id']][$id] = $r['score'] !== null ? (float) $r['score'] : null;
    }
}
$assignments = array_values($assignments);

// ---- Quizzes (latest attempt per student, as a percentage) -------------------
$stmt = $pdo->prepare("
    SELECT q.quiz_id, q.title, qa.student_id, qa.score, qa.max_score
    FROM quizzes q
    LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.quiz_id
        AND qa.attempt_number = (
            SELECT MAX(x.attempt_number) FROM quiz_attempts x
            WHERE x.quiz_id = q.quiz_id AND x.student_id = qa.student_id
        )
    WHERE q.offering_id = ?
    ORDER BY q.created_at, q.quiz_id
");
$stmt->execute([$offeringId]);
$quizzes = [];
$qScores = [];
foreach ($stmt->fetchAll() as $r) {
    $id = (int) $r['quiz_id'];
    if (!isset($quizzes[$id])) {
        $quizzes[$id] = ['id' => $id, 'title' => $r['title']];
    }
    if ($r['student_id'] !== null) {
        $max = (float) ($r['max_score'] ?? 0);
        $qScores[(int) $r['student_id']][$id] = ($r['score'] !== null && $max > 0) ? ((float) $r['score'] / $max) * 100 : null;
    }
}
$quizzes = array_values($quizzes);

// ---- Official (finalized) grades ----------------------------------------------
$stmt = $pdo->prepare("
    SELECT g.enrollment_id, g.grade
    FROM grades g
    JOIN enrollments e ON e.enrollment_id = g.enrollment_id
    WHERE e.offering_id = ? AND g.quarter = 'Final'
");
$stmt->execute([$offeringId]);
$finalized = [];
foreach ($stmt->fetchAll() as $r) {
    $finalized[(int) $r['enrollment_id']] = (float) $r['grade'];
}

// ---- Helpers --------------------------------------------------------------------
function gb_avg(array $values): ?float
{
    $values = array_values(array_filter($values, fn($x) => $x !== null));
    return $values ? round(array_sum($values) / count($values), 1) : null;
}

// Numbers stay numeric in the sheet (so Excel can sort/compute); no data = "N/A", same as the page.
function gb_cell($value, int $decimals = 1)
{
    return $value === null ? 'N/A' : round((float) $value, $decimals);
}

// ---- Build the rows ----------------------------------------------------------------
$rows         = [];
$allOverall   = [];
$allQuiz      = [];
$allAssign    = [];
$finalizedCnt = 0;

foreach ($students as $s) {
    $sid = (int) $s['student_id'];
    $eid = (int) $s['enrollment_id'];

    $av = $ptv = $examv = $qv = [];
    $assignCells = [];
    $quizCells   = [];

    foreach ($assignments as $a) {
        $x   = $aScores[$sid][$a['id']] ?? null;
        $pct = ($x !== null && $a['points'] > 0) ? ($x / $a['points']) * 100 : null;
        $av[] = $pct;
        if ($a['type'] === 'Exam') {
            $examv[] = $pct;
        } else {
            $ptv[] = $pct;
        }
        $assignCells[] = gb_cell($pct);
    }
    foreach ($quizzes as $q) {
        $pct = $qScores[$sid][$q['id']] ?? null;
        $qv[]        = $pct;
        $quizCells[] = gb_cell($pct);
    }

    $aAvg     = gb_avg($av);
    $qAvg     = gb_avg($qv);
    $ptAvg    = gb_avg($ptv);
    $examAvg  = gb_avg($examv);
    $overall  = gb_avg(array_filter([$aAvg, $qAvg], fn($x) => $x !== null));
    $computed = computeWeightedFinalGrade($qAvg, $ptAvg, $examAvg);
    $official = $finalized[$eid] ?? null;

    if ($official !== null) {
        $finalizedCnt++;
        $status = ($computed !== null && abs($official - $computed) >= 0.01) ? 'Needs re-finalizing' : 'Up to date';
    } else {
        $status = 'Not finalized';
    }

    if ($aAvg !== null)    $allAssign[]  = $aAvg;
    if ($qAvg !== null)    $allQuiz[]    = $qAvg;
    if ($overall !== null) $allOverall[] = $overall;

    $rows[] = array_merge(
        [
            (string) $s['student_lrn'], // text, so long LRNs never turn into 1.2E+11
            $s['lastname'],
            $s['firstname'],
            $s['middlename'] ?? '',
        ],
        $quizCells,
        $assignCells,
        [
            gb_cell($qAvg),
            gb_cell($aAvg),
            gb_cell($overall),
            gb_cell($ptAvg),
            gb_cell($examAvg),
            gb_cell($computed, 2),
            gb_cell($official, 2),
            $status,
        ]
    );
}

// ---- Columns -----------------------------------------------------------------------
$columns = [
    ['label' => 'LRN',         'width' => 18],
    ['label' => 'Last Name',   'width' => 18],
    ['label' => 'First Name',  'width' => 18],
    ['label' => 'Middle Name', 'width' => 16],
];
foreach ($quizzes as $q) {
    $columns[] = ['label' => $q['title'] . "\n(Quiz %)", 'width' => 16];
}
foreach ($assignments as $a) {
    $columns[] = ['label' => $a['title'] . "\n(" . $a['type'] . ' %)', 'width' => 16];
}
$columns[] = ['label' => 'Quiz Average (Written Work)', 'width' => 16];
$columns[] = ['label' => 'Assignment Average',          'width' => 16];
$columns[] = ['label' => 'Overall',                     'width' => 12];
$columns[] = ['label' => 'Performance Task',            'width' => 16];
$columns[] = ['label' => 'Exam',                        'width' => 12];
$columns[] = ['label' => 'Computed Final',              'width' => 14];
$columns[] = ['label' => 'Official Grade',              'width' => 14];
$columns[] = ['label' => 'Grade Status',                'width' => 20];
$statusCol = count($columns) - 1;

// ---- Titles / filename ---------------------------------------------------------------
$termNo = (int) substr($class['quarter'], -1);

$subtitle = 'Grade ' . $class['grade_level']
    . ($class['strand'] ? ' · ' . $class['strand'] : '')
    . ' · Term ' . $termNo
    . ' · School Year ' . ($class['school_year_label'] ?: '—')
    . ' · Teacher ' . $teacherName;

$weights = 'Final grade weighting: Written Work ' . (int) round(GRADE_COMPONENT_WEIGHTS['written_work'] * 100) . '%'
    . ' · Performance Task ' . (int) round(GRADE_COMPONENT_WEIGHTS['performance_task'] * 100) . '%'
    . ' · Exam ' . (int) round(GRADE_COMPONENT_WEIGHTS['exam'] * 100) . '%'
    . ' (all scores shown as percentages)';

$fmtAvg = fn($v) => $v !== null ? $v . '%' : '—';

$namePart = preg_replace('/[^A-Za-z0-9]+/', '', $class['subject_name'])
    . '-Grade' . $class['grade_level']
    . preg_replace('/[^A-Za-z0-9]+/', '', $class['section_name']);

export_xlsx_modern([
    'filename'   => $namePart . '_gradebook_Term' . $termNo . '_' . date('Y-m-d') . '.xlsx',
    'sheet_name' => 'Gradebook',
    'band_title' => $class['subject_name'] . ' - ' . $class['section_name'],
    'subtitle_lines' => [
        $subtitle,
        $weights,
        'Generated: ' . date('F j, Y g:i A') . ' · ' . count($students) . ' student' . (count($students) === 1 ? '' : 's'),
    ],
    'columns'    => $columns,
    'rows'       => $rows,
    'status_col' => $statusCol,
    'footer_text' => 'Class average: ' . $fmtAvg(gb_avg($allOverall))
        . ' · Quiz average: ' . $fmtAvg(gb_avg($allQuiz))
        . ' · Assignment average: ' . $fmtAvg(gb_avg($allAssign))
        . ' · Finalized: ' . $finalizedCnt . ' of ' . count($students),
]);
exit();