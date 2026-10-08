<?php
require_once '../../config/config.php';
require_once 'assets/api/at_risk_functions.php';
require_once '../../includes/gemini_client.php';
requireTeacher();

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();
if (!$teacher) {
    die('Teacher record not found for this account.');
}
$teacherId = (int) $teacher['teacher_id'];

$stmt = $pdo->prepare("
    SELECT co.offering_id
    FROM classofferings co
    WHERE co.teacher_id = ? AND co.status = 'active'
");
$stmt->execute([$teacherId]);
$offeringIds = array_column($stmt->fetchAll(), 'offering_id');

$roster = get_at_risk_roster($pdo, $offeringIds);

// ---- Group roster by CLASS (subject + section), not by offering. ------
// The same class has one offering per term (Term 1 / 2 / 3). Showing each
// as its own card made the page messy, so each class gets ONE card that
// shows the current term, with the other terms tucked behind an expander.
$currentTerm = resolveCurrentTerm(getTermIntervals($pdo)); // e.g. 'TRM 2' or null

$classes = []; // "subjectId-sectionId" => class info + term instances
foreach ($roster as $r) {
    $ckey = $r['subject_id'] . '-' . $r['section_id'];
    if (!isset($classes[$ckey])) {
        $classes[$ckey] = [
            'key'         => 'class-' . $ckey,
            'subject'     => $r['subject'],
            'section'     => $r['section'],
            'grade_level' => $r['grade_level'],
            'terms'       => [], // offering_id => term instance
        ];
    }
    $oid = $r['offering_id'];
    if (!isset($classes[$ckey]['terms'][$oid])) {
        $classes[$ckey]['terms'][$oid] = [
            'offering_id'    => $oid,
            'quarter'        => $r['quarter'],
            'term_no'        => (int) substr($r['quarter'], -1),
            'school_year_id' => $r['school_year_id'],
            'sy_label'       => $r['school_year_label'],
            'sy_current'     => $r['school_year_is_current'],
            'students'       => [],
            'counts'         => ['High' => 0, 'Medium' => 0, 'Low' => 0, 'Insufficient Data' => 0],
        ];
    }
    $classes[$ckey]['terms'][$oid]['students'][] = $r;
    $classes[$ckey]['terms'][$oid]['counts'][$r['risk_label']]++;
}

// Per class: pick the term to show up front ("primary"); the rest go in
// the expander, newest first.
foreach ($classes as &$c) {
    $terms = array_values($c['terms']);
    usort($terms, function ($a, $b) {
        return [$b['sy_current'], $b['school_year_id'], $b['term_no']]
           <=> [$a['sy_current'], $a['school_year_id'], $a['term_no']];
    });
    // Prefer the term that is actually running right now, if this class has it.
    foreach ($terms as $i => $t) {
        if ($t['sy_current'] && $currentTerm !== null && $t['quarter'] === $currentTerm) {
            array_splice($terms, $i, 1);
            array_unshift($terms, $t);
            break;
        }
    }
    $c['primary'] = array_shift($terms);
    $c['others']  = $terms;
    unset($c['terms']);
}
unset($c);

// Top summary counts and filter counts only reflect the term being shown,
// so a student enrolled across 3 terms isn't counted 3 times.
$counts = ['High' => 0, 'Medium' => 0, 'Low' => 0, 'Insufficient Data' => 0];
$shownTotal = 0;
foreach ($classes as $c) {
    foreach ($c['primary']['counts'] as $label => $n) {
        $counts[$label] += $n;
        $shownTotal += $n;
    }
}

// Classes with the most/highest risk students first.
uasort($classes, function ($a, $b) {
    $ca = $a['primary']['counts']; $cb = $b['primary']['counts'];
    if ($ca['High'] !== $cb['High']) {
        return $cb['High'] <=> $ca['High'];
    }
    return $cb['Medium'] <=> $ca['Medium'];
});

$aiEnabled = gemini_is_configured();

/**
 * Renders a small percentage + progress bar for a metric cell.
 * If the category doesn't yet have enough logged items (< min), shows a
 * neutral "building data" note instead of a misleading percentage —
 * one missed assignment shouldn't look the same as a real trend.
 */
function render_metric_cell($pct, bool $enough, int $count, int $min)
{
    if (!$enough) {
        return '<span class="metric-na" data-i18n-metric="building" data-count="' . $count . '" data-min="' . $min . '" title="Needs at least ' . $min . ' logged to count toward risk">'
            . '<i class="fas fa-hourglass-half"></i> <span class="metric-na-text">building data (' . $count . '/' . $min . ')</span></span>';
    }
    if ($pct === null) {
        return '<span class="metric-na" data-i18n-metric="none"><span class="metric-na-text">no data</span></span>';
    }
    $color = $pct >= 75 ? '#16a34a' : ($pct >= 50 ? '#d97706' : '#dc2626');
    $pctSafe = max(0, min(100, (float) $pct));
    return '<div class="metric-cell">'
        . '<span>' . number_format($pct, 1) . '%</span>'
        . '<span class="metric-bar"><span style="width:' . $pctSafe . '%;background:' . $color . ';"></span></span>'
        . '</div>';
}

/** Renders the localized risk pill markup (JS swaps the label text on language toggle). */
function render_risk_pill($riskLabel, $riskKey, $riskScore)
{
    $cssKey = $riskKey === 'insufficient-data' ? 'na' : $riskKey;
    $i18nKey = $riskKey === 'insufficient-data' ? 'na' : $riskKey;
    $scoreSuffix = $riskScore !== null ? ' · ' . $riskScore : '';
    return '<span class="risk-pill ' . $cssKey . '">'
        . '<i class="fas fa-circle"></i>'
        . '<span class="risk-text" data-i18n-risk="' . $i18nKey . '">' . htmlspecialchars($riskLabel) . '</span>'
        . '<span class="risk-score-suffix">' . $scoreSuffix . '</span>'
        . '</span>';
}
/** Renders one term's roster table (shared by the primary term and the 'other terms' panels). */
function render_roster_table(array $students, bool $aiEnabled, string $termLabelSuffix)
{
    ob_start();
?>
                            <table class="ar-table">
                                <thead>
                                    <tr>
                                        <th data-i18n="th_student">Student</th>
                                        <th data-i18n="th_attendance">Attendance</th>
                                        <th data-i18n="th_assignments">Assignments</th>
                                        <th data-i18n="th_quizzes">Quizzes</th>
                                        <th data-i18n="th_risk">Risk</th>
                                        <th data-i18n="th_ai">AI Insights</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $r):
                                        $riskKey = strtolower(str_replace(' ', '-', $r['risk_label']));
                                        $filterKey = $r['risk_label'] === 'Insufficient Data' ? 'na' : strtolower($r['risk_label']);
                                    ?>
                                    <tr data-filter="<?= $filterKey ?>" data-name="<?= htmlspecialchars(strtolower($r['name']), ENT_QUOTES) ?>">
                                        <td data-label-i18n="th_student">
                                            <div style="font-weight:600;"><?= htmlspecialchars($r['name']) ?></div>
                                        </td>
                                        <td data-label-i18n="th_attendance"><?= render_metric_cell($r['attendance_pct'], $r['attendance_enough'], $r['attendance_total'], $r['min_data_points']) ?></td>
                                        <td data-label-i18n="th_assignments">
                                            <?= render_metric_cell($r['assignment_pct'], $r['assignment_enough'], $r['assignment_total_due'], $r['min_data_points']) ?>
                                            <?php if ($r['assignment_enough'] && $r['assignment_missing'] > 0): ?>
                                                <div class="ar-missing-note"><?= $r['assignment_missing'] ?>/<?= $r['assignment_total_due'] ?> <span data-i18n="missing">missing</span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label-i18n="th_quizzes">
                                            <?= render_metric_cell($r['quiz_pct'], $r['quiz_enough'], $r['quiz_total_due'], $r['min_data_points']) ?>
                                            <?php if ($r['quiz_enough'] && $r['quiz_missing'] > 0): ?>
                                                <div class="ar-missing-note"><?= $r['quiz_missing'] ?>/<?= $r['quiz_total_due'] ?> <span data-i18n="missing">missing</span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label-i18n="th_risk">
                                            <?= render_risk_pill($r['risk_label'], $riskKey, $r['risk_score']) ?>
                                        </td>
                                        <td data-label-i18n="th_ai">
                                            <button class="ai-btn"
                                                    data-student="<?= $r['student_id'] ?>" data-offering="<?= $r['offering_id'] ?>"
                                                    data-name="<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>" data-subject="<?= htmlspecialchars($r['subject'] . ' · ' . $termLabelSuffix, ENT_QUOTES) ?>"
                                                    data-risk-label="<?= htmlspecialchars($r['risk_label'], ENT_QUOTES) ?>" data-risk-key="<?= $riskKey === 'insufficient-data' ? 'na' : $riskKey ?>"
                                                    data-risk-score="<?= $r['risk_score'] !== null ? $r['risk_score'] : '' ?>"
                                                    data-att-pct="<?= $r['attendance_pct'] !== null ? $r['attendance_pct'] : '' ?>" data-att-enough="<?= $r['attendance_enough'] ? '1' : '0' ?>"
                                                    data-att-present="<?= $r['attendance_present'] ?>" data-att-late="<?= $r['attendance_late'] ?>"
                                                    data-att-absent="<?= $r['attendance_absent'] ?>" data-att-excused="<?= $r['attendance_excused'] ?>" data-att-total="<?= $r['attendance_total'] ?>"
                                                    data-asg-pct="<?= $r['assignment_pct'] !== null ? $r['assignment_pct'] : '' ?>" data-asg-enough="<?= $r['assignment_enough'] ? '1' : '0' ?>"
                                                    data-asg-missing="<?= $r['assignment_missing'] ?>" data-asg-total="<?= $r['assignment_total_due'] ?>"
                                                    data-quiz-pct="<?= $r['quiz_pct'] !== null ? $r['quiz_pct'] : '' ?>" data-quiz-enough="<?= $r['quiz_enough'] ? '1' : '0' ?>"
                                                    data-quiz-missing="<?= $r['quiz_missing'] ?>" data-quiz-total="<?= $r['quiz_total_due'] ?>"
                                                    data-min="<?= $r['min_data_points'] ?>"
                                                    <?= (!$aiEnabled || $r['risk_label'] === 'Insufficient Data') ? 'disabled' : '' ?>>
                                                <i class="fas fa-wand-magic-sparkles"></i> <span data-i18n="analyze_btn">Analyze</span>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
<?php
    return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>At-Risk Students · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/at_risk_students.css">
</head>
<body>

<?php include '../../includes/teachers_sidebar.php'; ?>

<main class="main-content" id="dashMain">
    <?php if (file_exists('../../includes/teacher_header.php')) include '../../includes/teacher_header.php'; ?>

    <div class="ar-wrap">
        <div class="ar-header">
            <div>
                <h1 data-i18n="title">At-Risk Students</h1>
                <p data-i18n="subtitle">Attendance, assignments, and quizzes combined into one early-warning score, with AI-generated context per student.</p>
            </div>
            <div class="ar-lang-toggle" id="arLangToggle" role="group" aria-label="Language">
                <button type="button" class="ar-lang-btn active" data-lang="en">EN</button>
                <button type="button" class="ar-lang-btn" data-lang="tl">TL</button>
            </div>
        </div>

        <?php if (!$aiEnabled): ?>
            <div class="ar-config-note">
                <i class="fas fa-triangle-exclamation"></i>
                <span data-i18n="ai_disabled_note">AI Insights are disabled — add a <code>GEMINI_API_KEY</code> in <code>config.php</code> to enable the "AI Insights" button (free key at aistudio.google.com/apikey).</span>
            </div>
        <?php endif; ?>

        <div class="ar-config-note ar-info-note">
            <i class="fas fa-circle-info"></i>
            <span data-i18n="min_data_note">A category (attendance, assignments, or quizzes) only counts toward a student's risk score once you've logged at least <?= RISK_MIN_DATA_POINTS ?> of it. Until then it's shown as "building data" and doesn't drag the score down — and if none of the three have enough yet, the student shows as Insufficient Data instead of a guess.</span>
        </div>

        <div class="ar-summary">
            <div class="ar-stat high"><span class="n"><?= $counts['High'] ?></span><span class="l" data-i18n="stat_high">High Risk</span></div>
            <div class="ar-stat medium"><span class="n"><?= $counts['Medium'] ?></span><span class="l" data-i18n="stat_medium">Medium Risk</span></div>
            <div class="ar-stat low"><span class="n"><?= $counts['Low'] ?></span><span class="l" data-i18n="stat_low">Low Risk</span></div>
            <div class="ar-stat na"><span class="n"><?= $counts['Insufficient Data'] ?></span><span class="l" data-i18n="stat_na">Not Enough Data</span></div>
        </div>

        <?php if (empty($roster)): ?>
            <div class="ar-table-wrap">
                <div class="ar-empty">
                    <i class="fas fa-shield-heart"></i>
                    <p data-i18n="empty_state">No active students found in your classes yet.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="ar-toolbar">
                <div class="ar-search">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" id="arSearchInput" data-i18n-attr="placeholder:search_placeholder" placeholder="Search student name…">
                </div>
                <div class="ar-filters">
                    <button class="ar-filter-btn active" data-filter="all"><span data-i18n="filter_all">All</span> (<?= $shownTotal ?>)</button>
                    <button class="ar-filter-btn" data-filter="high"><span data-i18n="filter_high">High</span> (<?= $counts['High'] ?>)</button>
                    <button class="ar-filter-btn" data-filter="medium"><span data-i18n="filter_medium">Medium</span> (<?= $counts['Medium'] ?>)</button>
                    <button class="ar-filter-btn" data-filter="low"><span data-i18n="filter_low">Low</span> (<?= $counts['Low'] ?>)</button>
                    <button class="ar-filter-btn" data-filter="na"><span data-i18n="filter_na">Insufficient Data</span></button>
                </div>
            </div>

            <div class="ar-class-tabs" id="arClassTabs">
                <button class="ar-class-tab active" data-class="all">
                    <i class="fas fa-layer-group"></i> <span data-i18n="all_classes">All Classes</span> <span class="ar-tab-count"><?= count($classes) ?></span>
                </button>
                <?php foreach ($classes as $c): ?>
                    <button class="ar-class-tab" data-class="<?= $c['key'] ?>">
                        <?= htmlspecialchars($c['subject']) ?> · <?= htmlspecialchars($c['section']) ?>
                        <?php if ($c['primary']['counts']['High'] > 0): ?>
                            <span class="ar-tab-badge high"><?= $c['primary']['counts']['High'] ?></span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="ar-classes" id="arClasses">
                <?php foreach ($classes as $c):
                    $p = $c['primary'];
                    $classTotal = count($p['students']);
                    $pTermLabel = 'Term ' . $p['term_no'];
                ?>
                <section class="ar-class-card" data-class="<?= $c['key'] ?>">
                    <button class="ar-class-head" type="button" aria-expanded="true">
                        <div class="ar-class-title">
                            <i class="fas fa-chevron-down ar-class-chevron"></i>
                            <div>
                                <h3><?= htmlspecialchars($c['subject']) ?> <span class="ar-class-sub">· <?= htmlspecialchars($c['section']) ?> · <span data-i18n="grade">Grade</span> <?= $c['grade_level'] ?></span>
                                    <span class="ar-term-pill"><span data-i18n="term">Term</span> <?= $p['term_no'] ?><?= (!$p['sy_current'] && $p['sy_label']) ? ' · SY ' . htmlspecialchars($p['sy_label']) : '' ?></span>
                                </h3>
                                <p><span class="ar-student-count" data-count="<?= $classTotal ?>"><?= $classTotal ?> <span data-i18n="<?= $classTotal === 1 ? 'student_singular' : 'student_plural' ?>"><?= $classTotal === 1 ? 'student' : 'students' ?></span></span></p>
                            </div>
                        </div>
                        <div class="ar-class-chips">
                            <?php if ($p['counts']['High'] > 0): ?><span class="ar-chip high"><?= $p['counts']['High'] ?> <span data-i18n="chip_high">High</span></span><?php endif; ?>
                            <?php if ($p['counts']['Medium'] > 0): ?><span class="ar-chip medium"><?= $p['counts']['Medium'] ?> <span data-i18n="chip_medium">Medium</span></span><?php endif; ?>
                            <?php if ($p['counts']['Low'] > 0): ?><span class="ar-chip low"><?= $p['counts']['Low'] ?> <span data-i18n="chip_low">Low</span></span><?php endif; ?>
                            <?php if ($p['counts']['Insufficient Data'] > 0): ?><span class="ar-chip na"><?= $p['counts']['Insufficient Data'] ?> <span data-i18n="chip_na">N/A</span></span><?php endif; ?>
                        </div>
                    </button>

                    <div class="ar-class-body">
                        <div class="ar-term-panel">
                            <div class="ar-table-wrap">
                                <?= render_roster_table($p['students'], $aiEnabled, $pTermLabel) ?>
                            </div>
                            <p class="ar-class-empty" data-i18n="no_match_class" style="display:none;">No students match the current filter/search in this class.</p>
                        </div>

                        <?php if (!empty($c['others'])): ?>
                            <button class="ar-terms-toggle" type="button" aria-expanded="false">
                                <i class="fas fa-clock-rotate-left"></i>
                                <span data-i18n="other_terms">Other terms</span>
                                <span class="ar-tab-count"><?= count($c['others']) ?></span>
                                <i class="fas fa-chevron-down ar-terms-chevron"></i>
                            </button>
                            <div class="ar-other-terms">
                                <?php foreach ($c['others'] as $o):
                                    $oTotal = count($o['students']);
                                    $oTermLabel = 'Term ' . $o['term_no'];
                                ?>
                                <div class="ar-term-panel ar-term-panel-other">
                                    <div class="ar-term-panel-head">
                                        <span class="ar-term-pill"><span data-i18n="term">Term</span> <?= $o['term_no'] ?><?= (!$o['sy_current'] && $o['sy_label']) ? ' · SY ' . htmlspecialchars($o['sy_label']) : '' ?></span>
                                        <span class="ar-term-panel-count"><?= $oTotal ?> <span data-i18n="<?= $oTotal === 1 ? 'student_singular' : 'student_plural' ?>"><?= $oTotal === 1 ? 'student' : 'students' ?></span></span>
                                        <span class="ar-class-chips">
                                            <?php if ($o['counts']['High'] > 0): ?><span class="ar-chip high"><?= $o['counts']['High'] ?> <span data-i18n="chip_high">High</span></span><?php endif; ?>
                                            <?php if ($o['counts']['Medium'] > 0): ?><span class="ar-chip medium"><?= $o['counts']['Medium'] ?> <span data-i18n="chip_medium">Medium</span></span><?php endif; ?>
                                            <?php if ($o['counts']['Low'] > 0): ?><span class="ar-chip low"><?= $o['counts']['Low'] ?> <span data-i18n="chip_low">Low</span></span><?php endif; ?>
                                            <?php if ($o['counts']['Insufficient Data'] > 0): ?><span class="ar-chip na"><?= $o['counts']['Insufficient Data'] ?> <span data-i18n="chip_na">N/A</span></span><?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="ar-table-wrap">
                                        <?= render_roster_table($o['students'], $aiEnabled, $oTermLabel) ?>
                                    </div>
                                    <p class="ar-class-empty" data-i18n="no_match_class" style="display:none;">No students match the current filter/search in this class.</p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endforeach; ?>
                <p class="ar-no-results" id="arNoResults" style="display:none;">
                    <i class="fas fa-magnifying-glass"></i> <span data-i18n="no_match_search">No students match your search/filter.</span>
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- AI Insight Modal -->
<div class="ai-modal-overlay" id="aiModalOverlay">
    <div class="ai-modal">
        <div class="ai-modal-head">
            <div>
                <h3 id="aiModalName">Student</h3>
                <p id="aiModalSubject"></p>
            </div>
            <button class="ai-modal-close" id="aiModalClose"><i class="fas fa-times"></i></button>
        </div>
        <div class="ai-modal-body" id="aiModalBody">
            <div class="ai-loading">
                <i class="fas fa-spinner"></i>
                <p data-i18n="analyzing">Analyzing…</p>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // =====================================================================
    // i18n — English / Tagalog dictionary + apply/toggle logic
    // =====================================================================
    const I18N = {
        en: {
            title: 'At-Risk Students',
            subtitle: 'Attendance, assignments, and quizzes combined into one early-warning score, with AI-generated context per student.',
            ai_disabled_note: 'AI Insights are disabled — add a <code>GEMINI_API_KEY</code> in <code>config.php</code> to enable the "AI Insights" button (free key at aistudio.google.com/apikey).',
            min_data_note: 'A category (attendance, assignments, or quizzes) only counts toward a student\'s risk score once you\'ve logged at least <?= RISK_MIN_DATA_POINTS ?> of it. Until then it\'s shown as "building data" and doesn\'t drag the score down — and if none of the three have enough yet, the student shows as Insufficient Data instead of a guess.',
            stat_high: 'High Risk', stat_medium: 'Medium Risk', stat_low: 'Low Risk', stat_na: 'Not Enough Data',
            empty_state: 'No active students found in your classes yet.',
            search_placeholder: 'Search student name…',
            filter_all: 'All', filter_high: 'High', filter_medium: 'Medium', filter_low: 'Low', filter_na: 'Insufficient Data',
            all_classes: 'All Classes',
            grade: 'Grade',
            term: 'Term', other_terms: 'Other terms',
            student_singular: 'student', student_plural: 'students',
            chip_high: 'High', chip_medium: 'Medium', chip_low: 'Low', chip_na: 'N/A',
            th_student: 'Student', th_attendance: 'Attendance', th_assignments: 'Assignments', th_quizzes: 'Quizzes', th_risk: 'Risk', th_ai: 'AI Insights',
            missing: 'missing',
            analyze_btn: 'Analyze',
            no_match_class: 'No students match the current filter/search in this class.',
            no_match_search: 'No students match your search/filter.',
            analyzing: 'Analyzing…',
            regenerating: 'Regenerating…',
            risk_high: 'High', risk_medium: 'Medium', risk_low: 'Low', risk_na: 'Insufficient Data',
            metric_building: 'building data',
            metric_none: 'no data',
            snapshot_title: 'Snapshot',
            snapshot_attendance: 'Attendance', snapshot_assignments: 'Assignments', snapshot_quizzes: 'Quizzes',
            snapshot_present: 'present', snapshot_late: 'late', snapshot_absent: 'absent', snapshot_excused: 'excused', snapshot_of: 'of',
            snapshot_missing: 'missing',
            key_observations_title: 'Key Observations',
            why_title: 'Why',
            how_title: 'How this was determined',
            actions_title: 'Recommended Actions',
            no_actions: 'No specific actions returned.',
            cached_label: 'Cached · generated ',
            generated_now: 'Generated just now',
            regen_btn: 'Regenerate',
            network_error: 'Network error — please try again.',
        },
        tl: {
            title: 'Mga Estudyanteng May Panganib (At-Risk)',
            subtitle: 'Pinagsamang attendance, assignments, at quizzes sa iisang early-warning score, may AI-generated na paliwanag para sa bawat estudyante.',
            ai_disabled_note: 'Naka-off muna ang AI Insights — magdagdag ng <code>GEMINI_API_KEY</code> sa <code>config.php</code> para ma-enable ang "AI Insights" na button (libreng key sa aistudio.google.com/apikey).',
            min_data_note: 'Isang category (attendance, assignments, o quizzes) lang ang bibilangin sa risk score ng estudyante kapag may naka-log na kahit <?= RISK_MIN_DATA_POINTS ?>. Bago mag-3, "naghihipon pa ng datos" ang lalabas at hindi ito nakaka-apekto sa score — at kung wala pang sapat sa tatlo, "Kulang ang Datos" ang malalabas sa halip na basta-bastang husga.',
            stat_high: 'Mataas na Panganib', stat_medium: 'Katamtamang Panganib', stat_low: 'Mababang Panganib', stat_na: 'Kulang ang Datos',
            empty_state: 'Wala pang aktibong estudyante sa iyong mga klase.',
            search_placeholder: 'Maghanap ng pangalan ng estudyante…',
            filter_all: 'Lahat', filter_high: 'Mataas', filter_medium: 'Katamtaman', filter_low: 'Mababa', filter_na: 'Kulang ang Datos',
            all_classes: 'Lahat ng Klase',
            grade: 'Baitang',
            term: 'Termino', other_terms: 'Iba pang termino',
            student_singular: 'estudyante', student_plural: 'estudyante',
            chip_high: 'Mataas', chip_medium: 'Katamtaman', chip_low: 'Mababa', chip_na: 'Kulang',
            th_student: 'Estudyante', th_attendance: 'Attendance', th_assignments: 'Mga Gawain', th_quizzes: 'Mga Pagsusulit', th_risk: 'Panganib', th_ai: 'AI Insights',
            missing: 'kulang',
            analyze_btn: 'Suriin',
            no_match_class: 'Walang estudyanteng tumutugma sa kasalukuyang filter/search sa klaseng ito.',
            no_match_search: 'Walang estudyanteng tumutugma sa iyong hanap/filter.',
            analyzing: 'Sinusuri…',
            regenerating: 'Muling sinusuri…',
            risk_high: 'Mataas', risk_medium: 'Katamtaman', risk_low: 'Mababa', risk_na: 'Kulang ang Datos',
            metric_building: 'naghihipon pa ng datos',
            metric_none: 'walang datos',
            snapshot_title: 'Buod ng Datos',
            snapshot_attendance: 'Attendance', snapshot_assignments: 'Mga Gawain', snapshot_quizzes: 'Mga Pagsusulit',
            snapshot_present: 'pumasok', snapshot_late: 'late', snapshot_absent: 'absent', snapshot_excused: 'excused', snapshot_of: 'sa',
            snapshot_missing: 'kulang',
            key_observations_title: 'Mahahalagang Obserbasyon',
            why_title: 'Bakit',
            how_title: 'Paano Ito Nabuo',
            actions_title: 'Mga Inirerekomendang Hakbang',
            no_actions: 'Walang espesipikong hakbang na naibalik.',
            cached_label: 'Naka-cache · nabuo noong ',
            generated_now: 'Kararaang nabuo',
            regen_btn: 'Buuin Muli',
            network_error: 'May problema sa koneksyon — subukan ulit.',
        }
    };

    let currentLang = 'en';
    try {
        const saved = localStorage.getItem('ar_lang');
        if (saved === 'tl' || saved === 'en') currentLang = saved;
    } catch (e) { /* localStorage unavailable — default to English */ }

    function t(key) {
        return (I18N[currentLang] && I18N[currentLang][key] !== undefined) ? I18N[currentLang][key] : (I18N.en[key] || key);
    }

    function applyStaticTranslations() {
        document.documentElement.setAttribute('lang', currentLang);

        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            el.innerHTML = t(key);
        });

        document.querySelectorAll('[data-i18n-attr]').forEach(el => {
            el.getAttribute('data-i18n-attr').split(',').forEach(pair => {
                const [attr, key] = pair.split(':');
                if (attr && key) el.setAttribute(attr.trim(), t(key.trim()));
            });
        });

        document.querySelectorAll('[data-i18n-risk]').forEach(el => {
            const key = 'risk_' + el.getAttribute('data-i18n-risk');
            el.textContent = t(key);
        });

        document.querySelectorAll('[data-i18n-metric]').forEach(el => {
            const kind = el.getAttribute('data-i18n-metric');
            const textEl = el.querySelector('.metric-na-text');
            if (!textEl) return;
            if (kind === 'building') {
                textEl.textContent = t('metric_building') + ' (' + el.getAttribute('data-count') + '/' + el.getAttribute('data-min') + ')';
            } else {
                textEl.textContent = t('metric_none');
            }
        });

        document.querySelectorAll('.ar-lang-btn').forEach(b => b.classList.toggle('active', b.dataset.lang === currentLang));

        // Mobile card view uses ::before { content: attr(data-mobile-label) }
        // to show a translated row label next to each value.
        document.querySelectorAll('td[data-label-i18n]').forEach(td => {
            td.setAttribute('data-mobile-label', t(td.getAttribute('data-label-i18n')));
        });
    }

    const langToggle = document.getElementById('arLangToggle');
    if (langToggle) {
        langToggle.querySelectorAll('.ar-lang-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                currentLang = btn.dataset.lang;
                try { localStorage.setItem('ar_lang', currentLang); } catch (e) {}
                applyStaticTranslations();
                // If the modal is open, refetch the AI content in the new language.
                if (overlay.classList.contains('open') && currentStudent) {
                    fetchInsight(currentStudent, currentOffering, false);
                }
            });
        });
    }

    applyStaticTranslations();

    // =====================================================================
    // Combined filtering: risk level + class tab + name search
    // =====================================================================
    const filterBtns   = document.querySelectorAll('.ar-filter-btn');
    const classTabs    = document.querySelectorAll('.ar-class-tab');
    const classCards   = document.querySelectorAll('.ar-class-card');
    const searchInput  = document.getElementById('arSearchInput');
    const noResultsMsg = document.getElementById('arNoResults');

    let activeRisk = 'all';
    let activeClass = 'all';

    function applyFilters() {
        const query = ((searchInput && searchInput.value) || '').trim().toLowerCase();
        let anyClassVisible = false;

        classCards.forEach(card => {
            const isTargetClass = activeClass === 'all' || card.dataset.class === activeClass;
            const filtering = activeRisk !== 'all' || !!query;

            // Each term (the visible one + any in the "Other terms" expander)
            // is its own panel with its own table / empty message.
            card.querySelectorAll('.ar-term-panel').forEach(panel => {
                let visibleInPanel = 0;
                panel.querySelectorAll('tbody tr').forEach(row => {
                    const matchesRisk = activeRisk === 'all' || row.dataset.filter === activeRisk;
                    const matchesSearch = !query || row.dataset.name.includes(query);
                    const show = isTargetClass && matchesRisk && matchesSearch;
                    row.style.display = show ? '' : 'none';
                    if (show) visibleInPanel++;
                });
                const emptyMsg = panel.querySelector('.ar-class-empty');
                const tableWrap = panel.querySelector('.ar-table-wrap');
                const noneVisible = visibleInPanel === 0 && filtering;
                if (emptyMsg) emptyMsg.style.display = noneVisible ? '' : 'none';
                if (tableWrap) tableWrap.style.display = noneVisible ? 'none' : '';
            });

            card.style.display = isTargetClass ? '' : 'none';
            if (isTargetClass) anyClassVisible = true;
        });

        if (noResultsMsg) {
            noResultsMsg.style.display = anyClassVisible ? 'none' : '';
        }
    }

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeRisk = btn.dataset.filter;
            applyFilters();
        });
    });

    classTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            classTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeClass = tab.dataset.class;
            applyFilters();
            // On small screens, scroll the picked tab into view.
            if (window.innerWidth <= 720) {
                tab.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    // ---- Class card accordion (expand/collapse) ----
    // On mobile, collapse every class by default except the first, so the
    // page isn't one giant scroll of tables.
    if (window.innerWidth <= 720) {
        document.querySelectorAll('.ar-class-card').forEach((card, idx) => {
            if (idx > 0) {
                card.classList.add('collapsed');
                const head = card.querySelector('.ar-class-head');
                if (head) head.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.querySelectorAll('.ar-class-head').forEach(head => {
        head.addEventListener('click', () => {
            const card = head.closest('.ar-class-card');
            const expanded = head.getAttribute('aria-expanded') === 'true';
            head.setAttribute('aria-expanded', String(!expanded));
            card.classList.toggle('collapsed', expanded);
        });
    });

    // ---- "Other terms" expander inside each class card ----
    document.querySelectorAll('.ar-terms-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const open = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', String(!open));
            btn.nextElementSibling.classList.toggle('open', !open);
        });
    });

    // =====================================================================
    // AI Insights modal
    // =====================================================================
    const overlay = document.getElementById('aiModalOverlay');
    const modalBody = document.getElementById('aiModalBody');
    const modalName = document.getElementById('aiModalName');
    const modalSubject = document.getElementById('aiModalSubject');
    let currentStudent = null, currentOffering = null, currentBtn = null;

    function openModal(name, subject) {
        modalName.textContent = name;
        modalSubject.textContent = subject;
        modalBody.innerHTML = snapshotHtml() + '<div class="ai-loading"><i class="fas fa-spinner"></i><p>' + escapeHtml(t('analyzing')) + '</p></div>';
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    document.getElementById('aiModalClose').addEventListener('click', closeModal);
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    /**
     * Always-available factual breakdown (attendance/assignments/quizzes),
     * shown above the AI narrative regardless of whether AI Insights are
     * enabled or the call succeeds — so the teacher always gets the raw
     * numbers, not just an AI paragraph.
     */
    function snapshotHtml() {
        if (!currentBtn) return '';
        const d = currentBtn.dataset;
        const min = d.min || 3;

        function metricRow(label, enough, pct, count, extra) {
            let valueHtml;
            if (enough === '0') {
                valueHtml = '<span class="metric-na"><i class="fas fa-hourglass-half"></i> ' + escapeHtml(t('metric_building')) + ' (' + count + '/' + min + ')</span>';
            } else if (pct === '') {
                valueHtml = '<span class="metric-na">' + escapeHtml(t('metric_none')) + '</span>';
            } else {
                const p = parseFloat(pct);
                const color = p >= 75 ? '#16a34a' : (p >= 50 ? '#d97706' : '#dc2626');
                valueHtml = '<div class="metric-cell"><span>' + p.toFixed(1) + '%</span>'
                    + '<span class="metric-bar"><span style="width:' + Math.max(0, Math.min(100, p)) + '%;background:' + color + ';"></span></span></div>';
            }
            return '<div class="ar-snap-row"><span class="ar-snap-label">' + escapeHtml(label) + '</span>'
                + '<span class="ar-snap-value">' + valueHtml + '</span>'
                + (extra ? '<span class="ar-snap-extra">' + escapeHtml(extra) + '</span>' : '')
                + '</div>';
        }

        const attExtra = d.attEnough === '1'
            ? d.attPresent + ' ' + t('snapshot_present') + ', ' + d.attLate + ' ' + t('snapshot_late') + ', '
              + d.attAbsent + ' ' + t('snapshot_absent') + ', ' + d.attExcused + ' ' + t('snapshot_excused')
              + ' (' + d.attTotal + ')'
            : '';
        const asgExtra = (d.asgEnough === '1' && parseInt(d.asgMissing, 10) > 0)
            ? d.asgMissing + '/' + d.asgTotal + ' ' + t('snapshot_missing') : '';
        const quizExtra = (d.quizEnough === '1' && parseInt(d.quizMissing, 10) > 0)
            ? d.quizMissing + '/' + d.quizTotal + ' ' + t('snapshot_missing') : '';

        return '<div class="ai-section ar-snapshot">'
            + '<h4><i class="fas fa-table-list"></i> ' + escapeHtml(t('snapshot_title')) + '</h4>'
            + metricRow(t('snapshot_attendance'), d.attEnough, d.attPct, d.attTotal, attExtra)
            + metricRow(t('snapshot_assignments'), d.asgEnough, d.asgPct, d.asgTotal, asgExtra)
            + metricRow(t('snapshot_quizzes'), d.quizEnough, d.quizPct, d.quizTotal, quizExtra)
            + '</div>';
    }

    function renderInsight(data) {
        const snapshot = snapshotHtml();
        if (!data.success) {
            modalBody.innerHTML = snapshot + `<div class="ai-error"><i class="fas fa-circle-exclamation"></i> ${escapeHtml(data.error || 'Something went wrong.')}</div>`;
            return;
        }
        const observations = (data.key_observations || []).map(o => `<li>${escapeHtml(o)}</li>`).join('');
        const actions = (data.recommended_actions || []).map(a => `<li>${escapeHtml(a)}</li>`).join('');
        const when = data.generated_at ? new Date(data.generated_at).toLocaleString() : '';
        modalBody.innerHTML = snapshot + `
            ${observations ? `
            <div class="ai-section">
                <h4><i class="fas fa-magnifying-glass-chart"></i> ${escapeHtml(t('key_observations_title'))}</h4>
                <ul class="ai-actions-list">${observations}</ul>
            </div>` : ''}
            <div class="ai-section">
                <h4><i class="fas fa-circle-question"></i> ${escapeHtml(t('why_title'))}</h4>
                <p>${escapeHtml(data.why || '')}</p>
            </div>
            <div class="ai-section">
                <h4><i class="fas fa-diagram-project"></i> ${escapeHtml(t('how_title'))}</h4>
                <p>${escapeHtml(data.how || '')}</p>
            </div>
            <div class="ai-section">
                <h4><i class="fas fa-list-check"></i> ${escapeHtml(t('actions_title'))}</h4>
                <ul class="ai-actions-list">${actions || '<li>' + escapeHtml(t('no_actions')) + '</li>'}</ul>
            </div>
            <div class="ai-meta">
                <span>${data.cached ? escapeHtml(t('cached_label')) + when : escapeHtml(t('generated_now'))}</span>
                <button class="ai-regen" id="aiRegenBtn"><i class="fas fa-rotate"></i> ${escapeHtml(t('regen_btn'))}</button>
            </div>
        `;
        document.getElementById('aiRegenBtn').addEventListener('click', () => {
            fetchInsight(currentStudent, currentOffering, true);
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }

    function fetchInsight(studentId, offeringId, force) {
        modalBody.innerHTML = snapshotHtml() + '<div class="ai-loading"><i class="fas fa-spinner"></i><p>' + escapeHtml(force ? t('regenerating') : t('analyzing')) + '</p></div>';
        fetch('analyze_student_risk.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ student_id: studentId, offering_id: offeringId, force: !!force, lang: currentLang })
        })
        .then(res => res.json())
        .then(renderInsight)
        .catch(() => {
            modalBody.innerHTML = snapshotHtml() + `<div class="ai-error"><i class="fas fa-circle-exclamation"></i> ${escapeHtml(t('network_error'))}</div>`;
        });
    }

    document.querySelectorAll('.ai-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (btn.disabled) return;
            currentStudent = btn.dataset.student;
            currentOffering = btn.dataset.offering;
            currentBtn = btn;
            openModal(btn.dataset.name, btn.dataset.subject);
            fetchInsight(currentStudent, currentOffering, false);
        });
    });
})();
</script>

</body>
</html>