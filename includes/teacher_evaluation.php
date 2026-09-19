<?php
/**
 * teacher_evaluation.php
 * -----------------------------------------------------------------
 * Shared helpers for the Teacher Evaluation (student survey) feature.
 * Requires the tables from teacher_evaluation_migration.sql.
 *
 * Used by:
 *   - admin/settings.php            (send / close a survey round)
 *   - admin/analytics.php           (results + AI summary)
 *   - student/teacher_evaluation.php, student sidebar / dashboard
 *
 * Privacy model: answers live in teacher_evaluation_responses/_answers with
 * NO student_id. "Who has submitted" lives separately in
 * teacher_evaluation_submissions. Gemini output is never persisted.
 * -----------------------------------------------------------------
 */

// Minimum number of responses before the AI summary is allowed. Small samples
// make it easy to guess who wrote a comment. Lower it (e.g. to 1) when testing
// with seed data; keep it at 3+ for real use.
if (!defined('TEACHER_EVAL_MIN_RESPONSES_FOR_AI')) {
    define('TEACHER_EVAL_MIN_RESPONSES_FOR_AI', 3);
}
// Caps on how much free text is sent to Gemini per request.
if (!defined('TEACHER_EVAL_MAX_COMMENTS_TO_AI')) {
    define('TEACHER_EVAL_MAX_COMMENTS_TO_AI', 60);
}
if (!defined('TEACHER_EVAL_COMMENT_MAX_LEN')) {
    define('TEACHER_EVAL_COMMENT_MAX_LEN', 1000);
}

const TEACHER_EVAL_TERMS = ['TRM 1', 'TRM 2', 'TRM 3'];

/** 5-point agreement scale shown to students. */
function teval_scale(): array
{
    return [
        5 => 'Strongly agree',
        4 => 'Agree',
        3 => 'Neutral',
        2 => 'Disagree',
        1 => 'Strongly disagree',
    ];
}

/**
 * The survey itself. Keys are stored in the DB (question_key), so don't
 * rename an existing key once real responses exist. Add new ones freely.
 */
function teval_categories(): array
{
    return [
        'clarity' => [
            'label' => 'Teaching Clarity',
            'icon'  => 'fa-chalkboard-user',
            'questions' => [
                'clarity_explains'  => 'My teacher explains lessons in a way I can understand.',
                'clarity_knowledge' => 'My teacher knows the subject well and answers questions correctly.',
                'clarity_examples'  => 'My teacher uses examples and activities that help me learn.',
            ],
        ],
        'environment' => [
            'label' => 'Classroom Environment',
            'icon'  => 'fa-people-roof',
            'questions' => [
                'environment_respect'   => 'My teacher treats every student with respect and fairness.',
                'environment_order'     => 'My teacher keeps the class orderly so we can focus on learning.',
                'environment_punctual'  => 'My teacher is prepared and starts and ends class on time.',
            ],
        ],
        'support' => [
            'label' => 'Support & Engagement',
            'icon'  => 'fa-hand-holding-heart',
            'questions' => [
                'support_participation' => 'My teacher encourages us to ask questions and take part.',
                'support_approachable'  => 'My teacher is approachable and willing to help when I struggle.',
                'support_care'          => 'My teacher shows that they care about my learning and progress.',
            ],
        ],
        'assessment' => [
            'label' => 'Assessments & Feedback',
            'icon'  => 'fa-clipboard-check',
            'questions' => [
                'assessment_aligned'  => 'Quizzes, activities, and exams match what was taught in class.',
                'assessment_feedback' => 'My teacher returns my work on time and gives useful feedback.',
                'assessment_grading'  => 'I understand how my grades are computed, and grading is fair.',
            ],
        ],
    ];
}

/** [question_key => ['text' => ..., 'category' => category_key]] */
function teval_questions_flat(): array
{
    $out = [];
    foreach (teval_categories() as $catKey => $cat) {
        foreach ($cat['questions'] as $qKey => $text) {
            $out[$qKey] = ['text' => $text, 'category' => $catKey];
        }
    }
    return $out;
}

/**
 * Map an average (1-5) to a DepEd-style descriptive rating.
 * @return array{label:string, class:string}
 */
function teval_rating_band(?float $avg): array
{
    if ($avg === null) return ['label' => 'No data', 'class' => 'band-none'];
    if ($avg >= 4.5)   return ['label' => 'Outstanding',       'class' => 'band-outstanding'];
    if ($avg >= 3.5)   return ['label' => 'Very Satisfactory', 'class' => 'band-very'];
    if ($avg >= 2.5)   return ['label' => 'Satisfactory',      'class' => 'band-satisfactory'];
    if ($avg >= 1.5)   return ['label' => 'Unsatisfactory',    'class' => 'band-unsatisfactory'];
    return ['label' => 'Poor', 'class' => 'band-poor'];
}

/** Trim, strip control characters, and cap the length of a free-text answer. */
function teval_clean_comment($value): ?string
{
    $value = trim((string) $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    if ($value === '') return null;
    return mb_substr($value, 0, TEACHER_EVAL_COMMENT_MAX_LEN);
}

function teval_teacher_name(array $row): string
{
    return trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
}

// =====================================================================
// Setup / rounds
// =====================================================================

/** False if the migration hasn't been run yet (lets pages degrade gracefully). */
function teval_tables_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $pdo->query("SELECT 1 FROM teacher_evaluation_rounds LIMIT 1");
        $pdo->query("SELECT 1 FROM teacher_evaluation_submissions LIMIT 1");
        $pdo->query("SELECT 1 FROM teacher_evaluation_responses LIMIT 1");
        $pdo->query("SELECT 1 FROM teacher_evaluation_answers LIMIT 1");
        return $ready = true;
    } catch (Throwable $e) {
        return $ready = false;
    }
}

/** A round is "live" only while status=open AND its deadline (if any) hasn't passed. */
function teval_round_is_live(array $round): bool
{
    if (($round['status'] ?? '') !== 'open') return false;
    if (!empty($round['closes_on']) && $round['closes_on'] < date('Y-m-d')) return false;
    return true;
}

/** The currently live round, or null. */
function teval_get_open_round(PDO $pdo): ?array
{
    if (!teval_tables_ready($pdo)) return null;
    $stmt = $pdo->query("
        SELECT r.*, sy.label AS school_year_label
        FROM teacher_evaluation_rounds r
        JOIN schoolyears sy ON sy.school_year_id = r.school_year_id
        WHERE r.status = 'open' AND (r.closes_on IS NULL OR r.closes_on >= CURDATE())
        ORDER BY r.round_id DESC
        LIMIT 1
    ");
    return $stmt->fetch() ?: null;
}

function teval_get_round(PDO $pdo, int $roundId): ?array
{
    if (!teval_tables_ready($pdo)) return null;
    $stmt = $pdo->prepare("
        SELECT r.*, sy.label AS school_year_label
        FROM teacher_evaluation_rounds r
        JOIN schoolyears sy ON sy.school_year_id = r.school_year_id
        WHERE r.round_id = ?
    ");
    $stmt->execute([$roundId]);
    return $stmt->fetch() ?: null;
}

/** Newest first. */
function teval_get_rounds(PDO $pdo, int $limit = 50): array
{
    if (!teval_tables_ready($pdo)) return [];
    $limit = max(1, min(200, $limit));
    $stmt = $pdo->query("
        SELECT r.*, sy.label AS school_year_label
        FROM teacher_evaluation_rounds r
        JOIN schoolyears sy ON sy.school_year_id = r.school_year_id
        ORDER BY r.round_id DESC
        LIMIT {$limit}
    ");
    return $stmt->fetchAll();
}

// =====================================================================
// Student side
// =====================================================================

/**
 * Classes a student can evaluate in a round: their active enrollments for the
 * round's school year + term. Each row carries `done` (0/1).
 */
function teval_student_classes(PDO $pdo, int $studentId, array $round): array
{
    $stmt = $pdo->prepare("
        SELECT co.offering_id, co.teacher_id, s.subject_name,
               sec.section_name, sec.grade_level,
               t.firstname, t.lastname,
               (SELECT COUNT(*) FROM teacher_evaluation_submissions ts
                 WHERE ts.round_id = ? AND ts.student_id = e.student_id
                   AND ts.offering_id = co.offering_id) AS done
        FROM enrollments e
        JOIN classofferings co ON co.offering_id = e.offering_id
        JOIN subjects s   ON s.subject_id = co.subject_id
        JOIN sections sec ON sec.section_id = co.section_id
        JOIN teachers t   ON t.teacher_id = co.teacher_id
        WHERE e.student_id = ? AND e.status = 'active'
          AND co.status = 'active'
          AND co.school_year_id = ? AND co.quarter = ?
        ORDER BY s.subject_name, t.lastname
    ");
    $stmt->execute([(int) $round['round_id'], $studentId, (int) $round['school_year_id'], $round['term']]);
    return $stmt->fetchAll();
}

/**
 * A logged-in student's standing in the live survey:
 *   round   - the live round (or null)
 *   total   - how many classes they can evaluate
 *   pending - how many of those they haven't submitted yet
 *
 * Never throws: it runs on every student page (sidebar), so a missing
 * migration or a DB hiccup must not break the portal. Cached per request.
 *
 * @return array{round:?array, total:int, pending:int}
 */
function teval_status_for_user(PDO $pdo, int $userId): array
{
    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];

    $none = ['round' => null, 'total' => 0, 'pending' => 0];
    try {
        $round = teval_get_open_round($pdo);
        if (!$round) return $cache[$userId] = $none;

        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $studentId = (int) $stmt->fetchColumn();
        if ($studentId <= 0) return $cache[$userId] = $none;

        $classes = teval_student_classes($pdo, $studentId, $round);
        $pending = 0;
        foreach ($classes as $row) {
            if (!(int) $row['done']) $pending++;
        }
        return $cache[$userId] = ['round' => $round, 'total' => count($classes), 'pending' => $pending];
    } catch (Throwable $e) {
        return $cache[$userId] = $none;
    }
}

// =====================================================================
// Admin analytics
// =====================================================================

/** Mean of [value, weight] pairs; null when there is no weight. */
function teval_weighted_avg(array $pairs): ?float
{
    $sum = 0.0;
    $weight = 0;
    foreach ($pairs as [$value, $w]) {
        $sum += $value * $w;
        $weight += $w;
    }
    return $weight > 0 ? $sum / $weight : null;
}

/**
 * Everything the analytics page needs for one round, computed from the
 * anonymous answer tables:
 *   overview  - response counts / rate
 *   school    - school-wide averages (overall, per category, per question, distribution)
 *   teachers  - per-teacher averages, sorted best -> lowest
 *   sections  - response rate per section
 */
function teval_round_results(PDO $pdo, array $round): array
{
    $roundId  = (int) $round['round_id'];
    $qFlat    = teval_questions_flat();
    $cats     = teval_categories();

    // ---- per teacher x question averages --------------------------------
    $stmt = $pdo->prepare("
        SELECT r.teacher_id, a.question_key, COUNT(*) AS n, AVG(a.rating) AS avg_rating
        FROM teacher_evaluation_responses r
        JOIN teacher_evaluation_answers a ON a.response_id = r.response_id
        WHERE r.round_id = ?
        GROUP BY r.teacher_id, a.question_key
    ");
    $stmt->execute([$roundId]);
    $qRows = $stmt->fetchAll();

    // ---- per teacher x rating distribution ------------------------------
    $stmt = $pdo->prepare("
        SELECT r.teacher_id, a.rating, COUNT(*) AS c
        FROM teacher_evaluation_responses r
        JOIN teacher_evaluation_answers a ON a.response_id = r.response_id
        WHERE r.round_id = ?
        GROUP BY r.teacher_id, a.rating
    ");
    $stmt->execute([$roundId]);
    $distRows = $stmt->fetchAll();

    // ---- teacher identity + response counts -----------------------------
    $stmt = $pdo->prepare("
        SELECT r.teacher_id, t.firstname, t.lastname, t.department,
               COUNT(*) AS responses, COUNT(DISTINCT r.offering_id) AS classes
        FROM teacher_evaluation_responses r
        JOIN teachers t ON t.teacher_id = r.teacher_id
        WHERE r.round_id = ?
        GROUP BY r.teacher_id, t.firstname, t.lastname, t.department
    ");
    $stmt->execute([$roundId]);
    $teacherRows = $stmt->fetchAll();

    // ---- assemble per-teacher -------------------------------------------
    $teachers = [];
    foreach ($teacherRows as $tr) {
        $tid = (int) $tr['teacher_id'];
        $teachers[$tid] = [
            'teacher_id' => $tid,
            'name'       => teval_teacher_name($tr),
            'department' => $tr['department'],
            'responses'  => (int) $tr['responses'],
            'classes'    => (int) $tr['classes'],
            'questions'  => [],
            'categories' => [],
            'overall'    => null,
            'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
        ];
    }

    $schoolQ = [];      // question_key => [[avg, n], ...]
    $teacherPairs = []; // teacher_id => [[avg, n], ...]
    $teacherCatPairs = []; // teacher_id => cat => [[avg, n], ...]
    $schoolCatPairs = [];
    $schoolPairs = [];

    foreach ($qRows as $row) {
        $key = $row['question_key'];
        if (!isset($qFlat[$key])) continue; // question retired from the survey
        $tid = (int) $row['teacher_id'];
        if (!isset($teachers[$tid])) continue;
        $avg = (float) $row['avg_rating'];
        $n   = (int) $row['n'];
        $cat = $qFlat[$key]['category'];

        $teachers[$tid]['questions'][$key] = round($avg, 2);
        $teacherPairs[$tid][]              = [$avg, $n];
        $teacherCatPairs[$tid][$cat][]     = [$avg, $n];
        $schoolQ[$key][]                   = [$avg, $n];
        $schoolCatPairs[$cat][]            = [$avg, $n];
        $schoolPairs[]                     = [$avg, $n];
    }

    $schoolDist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    foreach ($distRows as $row) {
        $tid = (int) $row['teacher_id'];
        $rating = (int) $row['rating'];
        if ($rating < 1 || $rating > 5) continue;
        if (isset($teachers[$tid])) $teachers[$tid]['distribution'][$rating] += (int) $row['c'];
        $schoolDist[$rating] += (int) $row['c'];
    }

    foreach ($teachers as $tid => &$t) {
        $overall = teval_weighted_avg($teacherPairs[$tid] ?? []);
        $t['overall'] = $overall !== null ? round($overall, 2) : null;
        $t['band']    = teval_rating_band($overall);
        foreach ($cats as $catKey => $_) {
            $avg = teval_weighted_avg($teacherCatPairs[$tid][$catKey] ?? []);
            $t['categories'][$catKey] = $avg !== null ? round($avg, 2) : null;
        }
    }
    unset($t);

    // Best first; teachers without data sink to the bottom.
    uasort($teachers, fn($a, $b) => ($b['overall'] ?? -1) <=> ($a['overall'] ?? -1));

    // ---- school-wide -----------------------------------------------------
    $schoolQuestions = [];
    foreach ($qFlat as $key => $_) {
        $avg = teval_weighted_avg($schoolQ[$key] ?? []);
        $schoolQuestions[$key] = $avg !== null ? round($avg, 2) : null;
    }
    $schoolCategories = [];
    foreach ($cats as $catKey => $_) {
        $avg = teval_weighted_avg($schoolCatPairs[$catKey] ?? []);
        $schoolCategories[$catKey] = $avg !== null ? round($avg, 2) : null;
    }
    $schoolOverall = teval_weighted_avg($schoolPairs);

    // ---- response rate, per section --------------------------------------
    $stmt = $pdo->prepare("
        SELECT sec.section_id, sec.grade_level, sec.section_name,
               COUNT(e.enrollment_id) AS expected,
               SUM(CASE WHEN ts.submission_id IS NOT NULL THEN 1 ELSE 0 END) AS submitted
        FROM enrollments e
        JOIN classofferings co ON co.offering_id = e.offering_id
        JOIN sections sec ON sec.section_id = co.section_id
        LEFT JOIN teacher_evaluation_submissions ts
               ON ts.round_id = ? AND ts.student_id = e.student_id AND ts.offering_id = e.offering_id
        WHERE e.status = 'active' AND co.status = 'active'
          AND co.school_year_id = ? AND co.quarter = ?
        GROUP BY sec.section_id, sec.grade_level, sec.section_name
        ORDER BY sec.grade_level, sec.section_name
    ");
    $stmt->execute([$roundId, (int) $round['school_year_id'], $round['term']]);
    $sections = [];
    $expectedTotal = 0;
    foreach ($stmt->fetchAll() as $row) {
        $expected  = (int) $row['expected'];
        $submitted = (int) $row['submitted'];
        $expectedTotal += $expected;
        $sections[] = [
            'section_id'  => (int) $row['section_id'],
            'label'       => 'Grade ' . $row['grade_level'] . ' - ' . $row['section_name'],
            'expected'    => $expected,
            'submitted'   => $submitted,
            'rate'        => $expected > 0 ? min(100, round($submitted / $expected * 100)) : 0,
        ];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_evaluation_submissions WHERE round_id = ?");
    $stmt->execute([$roundId]);
    $submittedTotal = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM teacher_evaluation_submissions WHERE round_id = ?");
    $stmt->execute([$roundId]);
    $studentsResponded = (int) $stmt->fetchColumn();

    return [
        'overview' => [
            'expected'           => $expectedTotal,
            'submitted'          => $submittedTotal,
            'rate'               => $expectedTotal > 0 ? min(100, round($submittedTotal / $expectedTotal * 100)) : 0,
            'students_responded' => $studentsResponded,
            'teachers_evaluated' => count($teachers),
        ],
        'school' => [
            'overall'      => $schoolOverall !== null ? round($schoolOverall, 2) : null,
            'band'         => teval_rating_band($schoolOverall),
            'categories'   => $schoolCategories,
            'questions'    => $schoolQuestions,
            'distribution' => $schoolDist,
            'responses'    => $submittedTotal,
        ],
        'teachers' => $teachers,
        'sections' => $sections,
    ];
}

/**
 * Per-class breakdown + written comments for one teacher in one round.
 * Comments come back in random order so the list can't leak submission order.
 */
function teval_teacher_detail(PDO $pdo, int $roundId, int $teacherId): array
{
    $stmt = $pdo->prepare("
        SELECT r.offering_id, s.subject_name, sec.grade_level, sec.section_name,
               COUNT(DISTINCT r.response_id) AS responses, AVG(a.rating) AS avg_rating
        FROM teacher_evaluation_responses r
        JOIN teacher_evaluation_answers a ON a.response_id = r.response_id
        JOIN classofferings co ON co.offering_id = r.offering_id
        JOIN subjects s   ON s.subject_id = co.subject_id
        JOIN sections sec ON sec.section_id = co.section_id
        WHERE r.round_id = ? AND r.teacher_id = ?
        GROUP BY r.offering_id, s.subject_name, sec.grade_level, sec.section_name
        ORDER BY s.subject_name, sec.grade_level, sec.section_name
    ");
    $stmt->execute([$roundId, $teacherId]);
    $classes = [];
    foreach ($stmt->fetchAll() as $row) {
        $avg = $row['avg_rating'] !== null ? (float) $row['avg_rating'] : null;
        $classes[] = [
            'label'     => $row['subject_name'] . ' — Grade ' . $row['grade_level'] . ' ' . $row['section_name'],
            'responses' => (int) $row['responses'],
            'avg'       => $avg !== null ? round($avg, 2) : null,
        ];
    }

    $stmt = $pdo->prepare("
        SELECT strengths, improvements
        FROM teacher_evaluation_responses
        WHERE round_id = ? AND teacher_id = ?
          AND (COALESCE(strengths, '') <> '' OR COALESCE(improvements, '') <> '')
        ORDER BY RAND()
        LIMIT 200
    ");
    $stmt->execute([$roundId, $teacherId]);
    $strengths = [];
    $improvements = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!empty($row['strengths']))    $strengths[]    = $row['strengths'];
        if (!empty($row['improvements'])) $improvements[] = $row['improvements'];
    }

    return ['classes' => $classes, 'strengths' => $strengths, 'improvements' => $improvements];
}

/** Random sample of written comments across the whole round (for school-wide AI summary). */
function teval_round_comments(PDO $pdo, int $roundId, int $limit): array
{
    $limit = max(1, min(500, $limit));
    $stmt = $pdo->prepare("
        SELECT strengths, improvements
        FROM teacher_evaluation_responses
        WHERE round_id = ?
          AND (COALESCE(strengths, '') <> '' OR COALESCE(improvements, '') <> '')
        ORDER BY RAND()
        LIMIT {$limit}
    ");
    $stmt->execute([$roundId]);
    $strengths = [];
    $improvements = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!empty($row['strengths']))    $strengths[]    = $row['strengths'];
        if (!empty($row['improvements'])) $improvements[] = $row['improvements'];
    }
    return ['strengths' => $strengths, 'improvements' => $improvements];
}
