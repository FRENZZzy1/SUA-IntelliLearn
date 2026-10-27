<?php
/**
 * quiz_generator_functions.php
 *
 * Data loader for public/teacher/quiz_generator.php — same contract as
 * dashboard_functions.php: session already started by config.php,
 * this file just guards access and prepares the variables the view uses.
 *
 * NOTE: adjust the require_once path below if config.php lives somewhere
 * else in your project — this assumes the same depth used elsewhere
 * under public/teacher/assets/api/.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/SUA-IntelliLearn/config/config.php';

requireTeacher();

// ------------------------------------------------------------------
// Resolve the logged-in user to a teachers.teacher_id
// ------------------------------------------------------------------
$stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$teacherRow = $stmt->fetch();

if (!$teacherRow) {
    // Logged in as 'teacher' role but no matching teachers row — bail safely.
    setFlashMessage('error', 'Your teacher profile could not be found. Please contact the administrator.');
    header('Location: dashboard.php');
    exit();
}

$teacherId   = (int) $teacherRow['teacher_id'];
$teacherName = trim($teacherRow['firstname'] . ' ' . $teacherRow['lastname']);

// ------------------------------------------------------------------
// Classes/subjects this teacher is actually assigned to (active only).
// This is what populates the "Subject / Class" dropdown — a teacher can
// only generate quizzes for offerings they own.
//
// Only classes of the ONGOING term (in the current school year) are offered.
// When no term is running today the list is empty.
// ------------------------------------------------------------------
$currentTerm = resolveCurrentTerm(getTermIntervals($pdo)); // e.g. 'TRM 2' or null
$termLabelMap = ['TRM 1' => 'Term 1', 'TRM 2' => 'Term 2', 'TRM 3' => 'Term 3'];
$currentTermLabel = $currentTerm !== null ? ($termLabelMap[$currentTerm] ?? $currentTerm) : null;

$schoolYearId = $pdo->query("SELECT school_year_id FROM schoolyears WHERE is_current = 1 LIMIT 1")->fetchColumn() ?: null;

$teacherOfferings = [];
if ($currentTerm !== null) {
    $stmt = $pdo->prepare("
        SELECT
            co.offering_id,
            co.quarter,
            s.subject_id,
            s.subject_name,
            sec.section_id,
            sec.section_name,
            sec.grade_level
        FROM classofferings co
        JOIN subjects s ON s.subject_id = co.subject_id
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1) AND co.teacher_id = ? AND co.status = 'active'
          AND co.quarter = ?
          AND (co.school_year_id = ? OR ? IS NULL)
        ORDER BY sec.grade_level ASC, s.subject_name ASC, sec.section_name ASC
    ");
    $stmt->execute([$teacherId, $currentTerm, $schoolYearId, $schoolYearId]);
    $teacherOfferings = $stmt->fetchAll();
}

// Deep link from Class Overview -> "New Quiz" (?subject_id=&section_id=): preselect that class.
$preselectOfferingId = 0;
$reqSubject = filter_input(INPUT_GET, 'subject_id', FILTER_VALIDATE_INT);
$reqSection = filter_input(INPUT_GET, 'section_id', FILTER_VALIDATE_INT);
if ($reqSubject && $reqSection) {
    foreach ($teacherOfferings as $o) {
        if ((int) $o['subject_id'] === $reqSubject && (int) $o['section_id'] === $reqSection) {
            $preselectOfferingId = (int) $o['offering_id'];
            break;
        }
    }
}

// ------------------------------------------------------------------
// PDF modules (learning_materials) of each of this teacher's active
// classes. The generator page lets the teacher pick from these instead
// of typing a topic. Grouped by offering_id so the browser can filter
// the list when the class dropdown changes.
// ------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT lm.material_id, lm.offering_id, lm.title, lm.file_size
    FROM learning_materials lm
    JOIN classofferings co ON co.offering_id = lm.offering_id AND co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1)
    WHERE co.teacher_id = ? AND co.status = 'active'
      AND lm.file_path IS NOT NULL
      AND LOWER(lm.file_path) LIKE '%.pdf'
    ORDER BY lm.created_at DESC
");
$stmt->execute([$teacherId]);

$pdfMaterialsByOffering = [];
foreach ($stmt->fetchAll() as $m) {
    $pdfMaterialsByOffering[(int) $m['offering_id']][] = [
        'id'    => (int) $m['material_id'],
        'title' => $m['title'],
        'size'  => (int) $m['file_size'],
    ];
}

// ------------------------------------------------------------------
// Recent quizzes this teacher has generated/created, for the sidebar list
// on the quiz generator page.
// ------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        q.quiz_id,
        q.title,
        q.status,
        q.generation_source,
        q.created_at,
        q.available_until,
        co.quarter,
        co.subject_id,
        co.section_id,
        s.subject_name,
        sec.section_name,
        sec.grade_level,
        (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.quiz_id) AS question_count,
        (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.quiz_id AND qa.status IN ('submitted', 'graded')) AS attempts_submitted
    FROM quizzes q
    JOIN classofferings co ON co.offering_id = q.offering_id AND co.status = 'active' AND co.section_id NOT IN (SELECT section_id FROM sections WHERE is_archived = 1)
    JOIN subjects s ON s.subject_id = co.subject_id
    JOIN sections sec ON sec.section_id = co.section_id
    WHERE q.created_by = ?
    ORDER BY q.created_at DESC
    LIMIT 8
");
$stmt->execute([$teacherId]);
$recentQuizzes = $stmt->fetchAll();

// ------------------------------------------------------------------
// Sidebar widgets (ongoing-term classes only): quiz counts by status and a
// short "needs your attention" list (ungraded attempts, deadlines within
// 7 days, unfinished drafts).
// ------------------------------------------------------------------
/** Link to a quiz's scores page in Class Overview. */
function qg_quiz_url(array $row): string
{
    return 'class_overview.php?' . http_build_query([
        'subject_id' => (int) $row['subject_id'],
        'section_id' => (int) $row['section_id'],
        'view'       => 'quizzes',
        'term'       => $row['quarter'],
        'quiz_id'    => (int) $row['quiz_id'],
    ]);
}

$quizCounts = ['draft' => 0, 'published' => 0, 'closed' => 0];
$quizToGrade = 0;
$attentionItems = [];

$offeringIdsNow = array_map('intval', array_column($teacherOfferings, 'offering_id'));
if ($offeringIdsNow) {
    $ph = implode(',', array_fill(0, count($offeringIdsNow), '?'));

    $stmt = $pdo->prepare("SELECT status, COUNT(*) FROM quizzes WHERE offering_id IN ($ph) GROUP BY status");
    $stmt->execute($offeringIdsNow);
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$st, $n]) {
        if (isset($quizCounts[$st])) {
            $quizCounts[$st] = (int) $n;
        }
    }

    $quizBase = "
        SELECT q.quiz_id, q.title, q.available_until, co.quarter, co.subject_id, co.section_id, s.subject_name
        FROM quizzes q
        JOIN classofferings co ON co.offering_id = q.offering_id
        JOIN subjects s ON s.subject_id = co.subject_id
    ";

    // 1) Submitted attempts nobody has graded yet (latest attempt per student, enrolled students only)
    $stmt = $pdo->prepare("
        SELECT q.quiz_id, q.title, co.quarter, co.subject_id, co.section_id, s.subject_name, COUNT(*) AS pending
        FROM quiz_attempts qa
        JOIN quizzes q ON q.quiz_id = qa.quiz_id
        JOIN classofferings co ON co.offering_id = q.offering_id
        JOIN subjects s ON s.subject_id = co.subject_id
        JOIN enrollments e ON e.offering_id = q.offering_id AND e.student_id = qa.student_id AND e.status = 'enrolled'
        WHERE q.offering_id IN ($ph)
          AND qa.status = 'submitted'
          AND qa.attempt_number = (SELECT MAX(y.attempt_number) FROM quiz_attempts y
                                    WHERE y.quiz_id = qa.quiz_id AND y.student_id = qa.student_id
                                      AND y.status IN ('submitted', 'graded'))
        GROUP BY q.quiz_id, q.title, co.quarter, co.subject_id, co.section_id, s.subject_name
        ORDER BY pending DESC
    ");
    $stmt->execute($offeringIdsNow);
    foreach ($stmt->fetchAll() as $r) {
        $quizToGrade += (int) $r['pending'];
        $attentionItems[] = [
            'kind' => 'grade', 'icon' => 'fa-pen-to-square', 'title' => $r['title'], 'subject' => $r['subject_name'],
            'label' => (int) $r['pending'] . ' to grade', 'url' => qg_quiz_url($r),
        ];
    }

    // 2) Published quizzes whose deadline falls within the next 7 days
    $stmt = $pdo->prepare($quizBase . "
        WHERE q.offering_id IN ($ph) AND q.status = 'published'
          AND q.available_until IS NOT NULL
          AND q.available_until >= NOW() AND q.available_until <= DATE_ADD(NOW(), INTERVAL 7 DAY)
        ORDER BY q.available_until ASC
    ");
    $stmt->execute($offeringIdsNow);
    foreach ($stmt->fetchAll() as $r) {
        $secs = strtotime($r['available_until']) - time();
        if ($secs < 86400) {
            $when = date('Y-m-d', strtotime($r['available_until'])) === date('Y-m-d') ? 'Closes today' : 'Closes tomorrow';
        } else {
            $when = 'Closes in ' . (int) floor($secs / 86400) . ' days';
        }
        $attentionItems[] = [
            'kind' => 'closing', 'icon' => 'fa-hourglass-half', 'title' => $r['title'], 'subject' => $r['subject_name'],
            'label' => $when, 'url' => qg_quiz_url($r),
        ];
    }

    // 3) Unfinished drafts
    $stmt = $pdo->prepare($quizBase . "
        WHERE q.offering_id IN ($ph) AND q.status = 'draft'
        ORDER BY q.created_at DESC
        LIMIT 3
    ");
    $stmt->execute($offeringIdsNow);
    foreach ($stmt->fetchAll() as $r) {
        $attentionItems[] = [
            'kind' => 'draft', 'icon' => 'fa-pen-ruler', 'title' => $r['title'], 'subject' => $r['subject_name'],
            'label' => 'Draft', 'url' => qg_quiz_url($r),
        ];
    }
    $attentionItems = array_slice($attentionItems, 0, 6);
}

$csrfToken = generateCSRFToken();
