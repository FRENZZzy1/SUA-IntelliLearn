<?php
/**
 * Admin chatbot - operational context.
 *
 * Adds to the keyword-search context in dashboard_functions.php so the assistant
 * understands the school's current state, not just record lookups.
 *
 * ALWAYS included (cheap, gives the model orientation):
 *   today's date, current school year + term, term intervals, enrollment open/closed,
 *   default capacity, school years, user counts by role/status, per-grade overview,
 *   attention items (full / nearly full classes, pending requests, sections without
 *   an adviser, classes with no students), announcements, teacher survey status.
 *
 * Intent-based (only when the question calls for it):
 *   capacity / seat lists, teacher workloads, section list, subject list,
 *   teacher evaluation ratings, school year details.
 *
 * Privacy: no LRN, birthdate, address, guardian details, passwords, or the
 * students' written survey comments are ever selected here.
 *
 * Place in: public/admin/assests/api/admin_chatbot_context.php
 */

/** Runs a query and returns all rows, or [] if it fails (e.g. a column/table is missing). */
function admin_ctx_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function admin_ctx_value(PDO $pdo, string $sql, array $params = [])
{
    $rows = admin_ctx_rows($pdo, $sql, $params);
    if (!$rows) return null;
    return array_values($rows[0])[0] ?? null;
}

function admin_chatbot_extra_context(PDO $pdo, string $question): string
{
    $q = strtolower($question);
    $L = [];

    // Intent detection (broad on purpose; extra context is cheap).
    $capacityIntent = (bool) preg_match('/\b(full|capacity|seat|seats|slot|slots|available|vacan\w*|open|nearly|almost|overload\w*|enrol+ment progress)\b/i', $q);
    $teacherIntent  = (bool) preg_match('/\b(teacher|teachers|faculty|instructor|workload|load|handles?|teaching|adviser|advisers|advisor)\b/i', $q);
    $sectionIntent  = (bool) preg_match('/\b(section|sections|adviser|advisers|advisor|grade level|per grade|each grade|homeroom)\b/i', $q);
    $subjectIntent  = (bool) preg_match('/\b(subject|subjects|curriculum|offered|offering|offerings)\b/i', $q);
    $evalIntent     = (bool) preg_match('/\b(evaluat\w*|survey|rating|ratings|rated|feedback|analytics|performance of teachers?|best teacher|top teacher|lowest)\b/i', $q);
    $yearIntent     = (bool) preg_match('/\b(school year|sy\b|archive\w*|restore|term|terms|trm|interval|semester|quarter|new year|next year|rollover)\b/i', $q);
    $userIntent     = (bool) preg_match('/\b(user|users|account|accounts|suspend\w*|inactive|deactivat\w*|admin|admins|access|permission|permissions)\b/i', $q);

    $L[] = "SCHOOL STATE (live):";
    $L[] = "TODAY: " . date('l, F j, Y');

    /* ---------- School year + term + settings ---------- */
    $years = admin_ctx_rows($pdo, "SELECT school_year_id, label, start_date, end_date, is_current,
                                          COALESCE(is_archived,0) AS is_archived
                                   FROM schoolyears ORDER BY start_date DESC LIMIT 10");
    $currentYear = null;
    foreach ($years as $y) {
        if ((int) $y['is_current'] === 1) { $currentYear = $y; break; }
    }
    $currentYearId = $currentYear ? (int) $currentYear['school_year_id'] : 0;

    $L[] = $currentYear
        ? "CURRENT SCHOOL YEAR: {$currentYear['label']} ({$currentYear['start_date']} to {$currentYear['end_date']})"
        : "CURRENT SCHOOL YEAR: none is marked current (the admin should set one in Settings > School Year & Terms).";

    $currentTerm = null;
    $intervals = [];
    if (function_exists('getTermIntervals') && function_exists('resolveCurrentTerm')) {
        try {
            $intervals = getTermIntervals($pdo);
            $currentTerm = resolveCurrentTerm($intervals);
        } catch (Throwable $e) { /* keep defaults */ }
    }
    $L[] = "CURRENT TERM (from term intervals and today's date): " . ($currentTerm ?? 'none - today is outside every configured term interval, so new classes cannot be created until the term intervals cover today');
    $parts = [];
    foreach (['TRM 1', 'TRM 2', 'TRM 3'] as $t) {
        $c = $intervals[$t] ?? null;
        $parts[] = $t . ': ' . (($c && !empty($c['start_date']) && !empty($c['end_date'])) ? "{$c['start_date']} to {$c['end_date']}" : 'not set');
    }
    $L[] = "TERM INTERVALS: " . implode(' | ', $parts);

    $settings = [];
    foreach (admin_ctx_rows($pdo, "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('school_name','default_class_capacity','enrollment_open')") as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    $enrollOpen = ($settings['enrollment_open'] ?? '1') === '1';
    $L[] = "ENROLLMENT SWITCH (Settings > Enrollment Rules): " . ($enrollOpen ? 'OPEN - students can send join requests with a class code' : 'CLOSED - students cannot send join requests');
    $L[] = "DEFAULT CLASS CAPACITY: " . ($settings['default_class_capacity'] ?? '50');

    if ($years && ($yearIntent || count($years) <= 4)) {
        $L[] = "SCHOOL YEARS:";
        foreach ($years as $y) {
            $flags = [];
            if ((int) $y['is_current'] === 1) $flags[] = 'current';
            if ((int) $y['is_archived'] === 1) $flags[] = 'archived';
            elseif ($y['end_date'] < date('Y-m-d')) $flags[] = 'end date passed';
            $L[] = "- {$y['label']} | {$y['start_date']} to {$y['end_date']}" . ($flags ? ' | ' . implode(', ', $flags) : '');
        }
    }

    /* ---------- Users ---------- */
    $roleRows = admin_ctx_rows($pdo, "SELECT role, status, COUNT(*) AS n FROM users GROUP BY role, status ORDER BY role, status");
    if ($roleRows) {
        $bits = [];
        foreach ($roleRows as $r) $bits[] = "{$r['role']} ({$r['status']}): {$r['n']}";
        $L[] = "ACCOUNTS BY ROLE AND STATUS: " . implode('; ', $bits);
    }

    /* ---------- Per-grade overview (current school year, not archived) ---------- */
    $yearFilterCo  = $currentYearId ? " AND co.school_year_id = " . $currentYearId : '';
    // When a term is active, count only that term's classes. Every class has one offering per term,
    // so without this a section's class count would be inflated by its other terms.
    $termFilterCo  = $currentTerm ? " AND co.quarter = " . $pdo->quote($currentTerm) : '';
    $yearTermCo    = $yearFilterCo . $termFilterCo;
    $gradeRows = admin_ctx_rows($pdo, "
        SELECT sec.grade_level,
               COUNT(DISTINCT sec.section_id) AS sections,
               COUNT(DISTINCT co.offering_id) AS classes,
               COUNT(DISTINCT CASE WHEN e.status = 'enrolled' THEN e.student_id END) AS students
        FROM sections sec
        LEFT JOIN classofferings co ON co.section_id = sec.section_id AND co.is_archived = 0 AND co.status = 'active' $yearTermCo
        LEFT JOIN enrollments e ON e.offering_id = co.offering_id
        WHERE sec.is_archived = 0 " . ($currentYearId ? " AND (sec.school_year_id = $currentYearId OR sec.school_year_id IS NULL)" : '') . "
        GROUP BY sec.grade_level
        ORDER BY sec.grade_level");
    if ($gradeRows) {
        $L[] = "GRADE-LEVEL OVERVIEW (" . ($currentTerm ? "{$currentTerm} classes" : 'all terms') . "; students = distinct currently enrolled):";
        foreach ($gradeRows as $g) {
            $L[] = "- Grade {$g['grade_level']}: {$g['sections']} section(s), {$g['classes']} active class(es), {$g['students']} enrolled student(s)";
        }
    }

    /* ---------- Class capacity picture ---------- */
    $classRows = admin_ctx_rows($pdo, "
        SELECT co.offering_id, co.quarter, co.capacity, co.status, co.class_code,
               sub.subject_name, sec.section_name, sec.grade_level,
               CONCAT(t.firstname, ' ', t.lastname) AS teacher_name,
               (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status = 'enrolled') AS enrolled,
               (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.offering_id AND e.status IN ('pending','requested')) AS pending
        FROM classofferings co
        JOIN subjects sub ON sub.subject_id = co.subject_id
        JOIN sections sec ON sec.section_id = co.section_id
        LEFT JOIN teachers t ON t.teacher_id = co.teacher_id
        WHERE co.is_archived = 0 AND sec.is_archived = 0 $yearFilterCo
        " . ($currentTerm ? " AND co.quarter = " . $pdo->quote($currentTerm) : '') . "
        ORDER BY sec.grade_level, sec.section_name, sub.subject_name
        LIMIT 600");

    $full = $nearFull = $empty = $pendingTotal = 0;
    $fullList = $nearList = $emptyList = $pendingList = [];
    foreach ($classRows as $c) {
        if ($c['status'] !== 'active') continue;
        $cap = (int) $c['capacity'];
        $enr = (int) $c['enrolled'];
        $label = "{$c['subject_name']} | Grade {$c['grade_level']} {$c['section_name']} | {$c['quarter']} | " . ($c['teacher_name'] ?: 'no teacher') . " | {$enr}/{$cap}";
        if ($cap > 0 && $enr >= $cap) { $full++; $fullList[] = $label . ' (FULL)'; }
        elseif ($cap > 0 && $enr / $cap >= 0.9) { $nearFull++; $nearList[] = $label . ' (' . ($cap - $enr) . ' seat(s) left)'; }
        if ($enr === 0) { $empty++; $emptyList[] = $label; }
        if ((int) $c['pending'] > 0) {
            $pendingTotal += (int) $c['pending'];
            $pendingList[] = "{$c['subject_name']} | Grade {$c['grade_level']} {$c['section_name']} | {$c['quarter']} | teacher " . ($c['teacher_name'] ?: 'none') . " | {$c['pending']} pending";
        }
    }
    $scope = $currentTerm ? "current term {$currentTerm}" : 'all terms';
    $L[] = "CLASS CAPACITY SUMMARY ({$scope}, active classes, current school year): total=" . count($classRows) .
           " | full={$full} | nearly full (90%+)={$nearFull} | with no enrolled students={$empty} | pending requests across these classes={$pendingTotal}";

    if ($pendingList) {
        $L[] = "CLASSES WITH PENDING ENROLLMENT REQUESTS (the class's teacher decides these, not the admin):";
        foreach (array_slice($pendingList, 0, 15) as $x) $L[] = "- $x";
        if (count($pendingList) > 15) $L[] = "- ... and " . (count($pendingList) - 15) . " more class(es)";
    }
    if ($fullList && ($capacityIntent || $full <= 10)) {
        $L[] = "FULL CLASSES:";
        foreach (array_slice($fullList, 0, 25) as $x) $L[] = "- $x";
    }
    if ($nearList && $capacityIntent) {
        $L[] = "NEARLY FULL CLASSES (90% or more):";
        foreach (array_slice($nearList, 0, 25) as $x) $L[] = "- $x";
    }
    if ($emptyList && $capacityIntent) {
        $L[] = "ACTIVE CLASSES WITH NO ENROLLED STUDENTS:";
        foreach (array_slice($emptyList, 0, 25) as $x) $L[] = "- $x";
        if (count($emptyList) > 25) $L[] = "- ... and " . (count($emptyList) - 25) . " more";
    }

    /* ---------- Attention items ---------- */
    $noAdviser = admin_ctx_rows($pdo, "SELECT section_name, grade_level FROM sections WHERE is_archived = 0 AND adviser_id IS NULL ORDER BY grade_level, section_name LIMIT 20");
    if ($noAdviser) {
        $L[] = "SECTIONS WITHOUT AN ADVISER: " . implode(', ', array_map(fn($s) => "Grade {$s['grade_level']} {$s['section_name']}", $noAdviser));
    }
    $noSchedule = (int) admin_ctx_value($pdo, "SELECT COUNT(*) FROM classofferings co JOIN sections sec ON sec.section_id = co.section_id
                                               WHERE co.is_archived = 0 AND sec.is_archived = 0 AND co.status = 'active'
                                                 AND (co.schedule_days IS NULL OR co.schedule_days = '') $yearTermCo");
    if ($noSchedule > 0) $L[] = "ACTIVE CLASSES WITHOUT A SCHEDULE: {$noSchedule}";

    /* ---------- Announcements ---------- */
    $annCounts = admin_ctx_rows($pdo, "SELECT a.status, COUNT(*) AS n FROM announcements a JOIN users u ON u.id = a.posted_by WHERE u.role = 'admin' GROUP BY a.status");
    if ($annCounts) {
        $bits = [];
        foreach ($annCounts as $r) $bits[] = "{$r['status']}: {$r['n']}";
        $L[] = "ADMIN ANNOUNCEMENTS: " . implode(', ', $bits);
    }
    $recentAnn = admin_ctx_rows($pdo, "SELECT a.title, a.audience, a.status, a.is_pinned, COALESCE(a.published_at, a.created_at) AS dt
                                       FROM announcements a JOIN users u ON u.id = a.posted_by
                                       WHERE u.role = 'admin' ORDER BY COALESCE(a.published_at, a.created_at) DESC LIMIT 5");
    if ($recentAnn) {
        $L[] = "LATEST ADMIN ANNOUNCEMENTS (title | audience | status | date):";
        foreach ($recentAnn as $a) {
            $L[] = "- {$a['title']} | {$a['audience']} | {$a['status']}" . ((int) $a['is_pinned'] ? ' (pinned)' : '') . " | " . date('M j, Y', strtotime($a['dt']));
        }
    }

    /* ---------- Teacher evaluation survey status ---------- */
    $tevalLoaded = function_exists('teval_tables_ready') && teval_tables_ready($pdo);
    $openRound = null;
    if ($tevalLoaded) {
        try {
            $openRound = teval_get_open_round($pdo);
            if ($openRound) {
                $ov = teval_round_results($pdo, $openRound)['overview'];
                $L[] = "TEACHER EVALUATION SURVEY: LIVE - \"{$openRound['title']}\" ({$openRound['school_year_label']}, {$openRound['term']})" .
                       ($openRound['closes_on'] ? ", deadline {$openRound['closes_on']}" : '') .
                       " | {$ov['submitted']} of {$ov['expected']} class evaluations submitted ({$ov['rate']}%), {$ov['students_responded']} student(s) responded";
            } else {
                $L[] = "TEACHER EVALUATION SURVEY: none is live right now (a Full Access admin can send one in Settings > Teacher Evaluation).";
            }
        } catch (Throwable $e) { /* ignore */ }
    } else {
        $L[] = "TEACHER EVALUATION SURVEY: the survey database tables are not set up yet (migration not run).";
    }

    /* ---------- Intent: teachers ---------- */
    if ($teacherIntent) {
        $rows = admin_ctx_rows($pdo, "
            SELECT t.teacher_id, CONCAT(t.firstname, ' ', t.lastname) AS name, t.department,
                   COUNT(DISTINCT CASE WHEN co.status = 'active' AND co.is_archived = 0 THEN co.offering_id END) AS classes,
                   COUNT(DISTINCT CASE WHEN co.status = 'active' AND co.is_archived = 0 THEN co.section_id END) AS sections
            FROM teachers t
            LEFT JOIN classofferings co ON co.teacher_id = t.teacher_id
                 " . ($currentYearId ? " AND co.school_year_id = $currentYearId" : '') . "
                 " . ($currentTerm ? " AND co.quarter = " . $pdo->quote($currentTerm) : '') . "
            GROUP BY t.teacher_id, t.firstname, t.lastname, t.department
            ORDER BY classes DESC, name
            LIMIT 80");
        if ($rows) {
            $L[] = "TEACHER WORKLOAD ({$scope}; active classes and distinct sections; name | department | classes | sections):";
            foreach ($rows as $r) {
                $L[] = "- {$r['name']} | " . ($r['department'] ?: 'no department') . " | {$r['classes']} class(es) | {$r['sections']} section(s)";
            }
            $idle = array_filter($rows, fn($r) => (int) $r['classes'] === 0);
            if ($idle) $L[] = "TEACHERS WITH NO ACTIVE CLASS ({$scope}): " . implode(', ', array_map(fn($r) => $r['name'], $idle));
        }
    }

    /* ---------- Intent: sections ---------- */
    if ($sectionIntent) {
        $rows = admin_ctx_rows($pdo, "
            SELECT sec.section_name, sec.grade_level, CONCAT(t.firstname, ' ', t.lastname) AS adviser,
                   COUNT(DISTINCT co.offering_id) AS classes,
                   COUNT(DISTINCT CASE WHEN e.status = 'enrolled' THEN e.student_id END) AS students
            FROM sections sec
            LEFT JOIN teachers t ON t.teacher_id = sec.adviser_id
            LEFT JOIN classofferings co ON co.section_id = sec.section_id AND co.is_archived = 0 AND co.status = 'active' $yearTermCo
            LEFT JOIN enrollments e ON e.offering_id = co.offering_id
            WHERE sec.is_archived = 0 " . ($currentYearId ? " AND (sec.school_year_id = $currentYearId OR sec.school_year_id IS NULL)" : '') . "
            GROUP BY sec.section_id, sec.section_name, sec.grade_level, t.firstname, t.lastname
            ORDER BY sec.grade_level, sec.section_name
            LIMIT 80");
        if ($rows) {
            $L[] = "SECTIONS (" . ($currentTerm ? "{$currentTerm}" : 'all terms') . "; grade | name | adviser | active classes | enrolled students):";
            foreach ($rows as $r) {
                $L[] = "- Grade {$r['grade_level']} | {$r['section_name']} | " . (trim((string) $r['adviser']) !== '' ? $r['adviser'] : 'no adviser') . " | {$r['classes']} | {$r['students']}";
            }
        }
    }

    /* ---------- Intent: subjects ---------- */
    if ($subjectIntent) {
        $rows = admin_ctx_rows($pdo, "
            SELECT sub.subject_name, COALESCE(sub.is_archived,0) AS is_archived,
                   COUNT(DISTINCT CASE WHEN co.is_archived = 0 AND co.status = 'active' THEN co.offering_id END) AS classes
            FROM subjects sub
            LEFT JOIN classofferings co ON co.subject_id = sub.subject_id $yearTermCo
            GROUP BY sub.subject_id, sub.subject_name, sub.is_archived
            ORDER BY sub.subject_name
            LIMIT 100");
        if ($rows) {
            $L[] = "SUBJECTS (name | active classes " . ($currentTerm ? "in {$currentTerm}" : 'this school year') . "):";
            foreach ($rows as $r) {
                $L[] = "- {$r['subject_name']} | {$r['classes']} class(es)" . ((int) $r['is_archived'] ? ' | archived' : '');
            }
        }
    }

    /* ---------- Intent: teacher evaluation results (aggregates only, no comments) ---------- */
    if ($evalIntent && $tevalLoaded) {
        try {
            $round = $openRound;
            if (!$round) {
                $rounds = teval_get_rounds($pdo, 1);
                $round = $rounds[0] ?? null;
            }
            if ($round) {
                $res = teval_round_results($pdo, $round);
                $ov = $res['overview'];
                $L[] = "TEACHER EVALUATION RESULTS for \"{$round['title']}\" ({$round['school_year_label']}, {$round['term']}, status {$round['status']}):";
                $L[] = "- Response rate: {$ov['submitted']} of {$ov['expected']} class evaluations ({$ov['rate']}%); {$ov['students_responded']} student(s) responded; {$ov['teachers_evaluated']} teacher(s) evaluated.";
                if ($res['school']['overall'] !== null) {
                    $L[] = "- School-wide overall rating: {$res['school']['overall']} / 5 ({$res['school']['band']['label']}).";
                }
                $catDefs = function_exists('teval_categories') ? teval_categories() : [];
                foreach (($res['school']['categories'] ?? []) as $key => $avg) {
                    $label = $catDefs[$key]['label'] ?? $key;
                    if ($avg !== null && is_numeric($avg)) $L[] = "- Category {$label}: " . round((float) $avg, 2) . " / 5";
                }
                $L[] = "RATINGS BY TEACHER (best first; teacher | overall /5 | band | responses):";
                foreach (array_slice($res['teachers'], 0, 60) as $t) {
                    $band = $t['overall'] !== null ? teval_rating_band((float) $t['overall'])['label'] : 'No data';
                    $L[] = "- {$t['name']} | " . ($t['overall'] !== null ? round((float) $t['overall'], 2) : 'N/A') . " | {$band} | {$t['responses']} response(s)";
                }
                $L[] = "NOTE: written student comments are intentionally not provided to this assistant. For comments and an AI summary the admin should open System Analytics.";
            } else {
                $L[] = "TEACHER EVALUATION RESULTS: no survey has been sent yet.";
            }
        } catch (Throwable $e) { /* ignore */ }
    }

    return implode("\n", $L);
}
