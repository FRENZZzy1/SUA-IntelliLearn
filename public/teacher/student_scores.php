<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/assets/api/gradebook_status_functions.php';
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../login.php');
    exit();
}
$teacherUser = (int)($_SESSION['user_id'] ?? 0);
$studentId = (int)(filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT) ?: 0);
$offeringId = (int)(filter_input(INPUT_GET, 'offering_id', FILTER_VALIDATE_INT) ?: 0);
$stmt = $pdo->prepare("SELECT t.teacher_id FROM teachers t WHERE t.user_id=? LIMIT 1");
$stmt->execute([$teacherUser]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$teacher || !$studentId || !$offeringId) die('Invalid request.');
$stmt = $pdo->prepare("SELECT co.offering_id,co.quarter,sub.subject_name,sec.section_name,sy.label school_year,s.student_id,s.student_lrn,s.firstname,s.lastname,s.middlename FROM classofferings co JOIN subjects sub ON sub.subject_id=co.subject_id JOIN sections sec ON sec.section_id=co.section_id JOIN schoolyears sy ON sy.school_year_id=co.school_year_id JOIN enrollments e ON e.offering_id=co.offering_id AND e.student_id=? AND e.status IN ('enrolled','completed') JOIN students s ON s.student_id=e.student_id WHERE co.offering_id=? AND co.teacher_id=? AND co.status='active' LIMIT 1");
$stmt->execute([$studentId, $offeringId, $teacher['teacher_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) die('Student or class not found.');
$loaded = gb_load_items($pdo, $offeringId);
$items = $loaded['items'];
$studentDone = $loaded['done'][$studentId] ?? [];
$studentScores = $loaded['scores'][$studentId] ?? [];

function ss_fmt($n): string
{
    return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.');
}
$catStatus = [];
foreach (GB_CATEGORIES as $key => $label)
    $catStatus[$key] = gb_category_status($items[$key], $studentDone);
$name = htmlspecialchars($student['firstname'] . ' ' . $student['lastname']);

// REQ021 - performance trend data (score % per scored item, student vs class average)
$trend = [];
$catSum = [];
foreach (GB_CATEGORIES as $key => $label) {
    $catSum[$key] = ['stu' => [], 'cls' => []];
    foreach ($items[$key] as $it) {
        if ($it['max'] <= 0) continue;
        $classPcts = [];
        foreach ($loaded['scores'] as $sid => $rows) {
            if (isset($rows[$it['uid']]) && $rows[$it['uid']]['score'] !== null && !empty($loaded['done'][$sid][$it['uid']]))
                $classPcts[] = $rows[$it['uid']]['score'] / $it['max'] * 100;
        }
        $sc = $studentScores[$it['uid']] ?? null;
        $hasScore = !empty($studentDone[$it['uid']]) && $sc !== null && $sc['score'] !== null;
        $stuPct = $hasScore ? round($sc['score'] / $it['max'] * 100, 1) : null;
        $clsPct = $classPcts ? round(array_sum($classPcts) / count($classPcts), 1) : null;
        if ($stuPct !== null) $catSum[$key]['stu'][] = $stuPct;
        if ($clsPct !== null) $catSum[$key]['cls'][] = $clsPct;
        if ($stuPct === null) continue;
        $trend[] = ['label' => $it['title'], 'cat' => $label, 'date' => $it['created_at'] ?? '', 'student' => $stuPct, 'class' => $clsPct];
    }
}
usort($trend, fn($a, $b) => strcmp($a['date'], $b['date']));
$trendCount = count($trend);
$avgOf = fn(array $a) => $a ? round(array_sum($a) / count($a), 1) : null;
$overall = $avgOf(array_column($trend, 'student'));
$trendDir = 'steady';
if ($trendCount >= 2) {
    $half = intdiv($trendCount, 2);
    $first = $avgOf(array_column(array_slice($trend, 0, $half), 'student'));
    $last = $avgOf(array_column(array_slice($trend, -$half), 'student'));
    if ($last - $first >= 3) $trendDir = 'up';
    elseif ($first - $last >= 3) $trendDir = 'down';
}
$catChart = ['labels' => [], 'student' => [], 'class' => []];
foreach (GB_CATEGORIES as $key => $label) {
    $catChart['labels'][] = $label;
    $catChart['student'][] = $avgOf($catSum[$key]['stu']);
    $catChart['class'][] = $avgOf($catSum[$key]['cls']);
}
?>
<?php
/* ---- Presentation-only helpers (no change to the data logic above) ---- */
function ss_grade_class($pct): string
{
    if ($pct === null) return 'none';
    return $pct >= 90 ? 'excellent' : ($pct >= 75 ? 'good' : ($pct >= 60 ? 'fair' : 'low'));
}
$totalItems = 0;
$doneItems = 0;
foreach (GB_CATEGORIES as $k => $l) {
    foreach ($items[$k] as $it) {
        $totalItems++;
        if (!empty($studentDone[$it['uid']])) $doneItems++;
    }
}
$completion = $totalItems ? (int)round($doneItems / $totalItems * 100) : 0;
$initialsStu = strtoupper(substr($student['firstname'], 0, 1) . substr($student['lastname'], 0, 1));
$fullName = trim($student['firstname'] . ' ' . ($student['middlename'] ? substr($student['middlename'], 0, 1) . '. ' : '') . $student['lastname']);
$icons = ['ww' => 'fa-file-circle-question', 'pt' => 'fa-clipboard-check', 'exam' => 'fa-file-pen'];
$trendClass = $trendCount < 2 ? '' : ($trendDir === 'up' ? 'up' : ($trendDir === 'down' ? 'down' : ''));
$trendIcon = $trendDir === 'up' ? 'fa-arrow-trend-up' : ($trendDir === 'down' ? 'fa-arrow-trend-down' : 'fa-minus');
$trendLabel = $trendCount < 2 ? 'Not enough data' : ($trendDir === 'up' ? 'Improving' : ($trendDir === 'down' ? 'Declining' : 'Steady'));
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $name ?> · Grade Details · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/grade_book.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/grade_book.css') ?>">
    <link rel="stylesheet" href="assets/css/student_scores.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/student_scores.css') ?>">
</head>

<body><?php include '../../includes/teachers_sidebar.php';
    // School logo: reuse the URL the sidebar just resolved (works under any base path)
    $logoUrl = (!empty($customSidebarIcon) && !empty($customSidebarIconFile) && file_exists($customSidebarIconFile)) ? $customSidebarIcon : null;
    ?>
    <main class="main-content"><?php include '../../includes/teacher_header.php'; ?>
        <div class="page-wrap ss-page">

            <a class="ss-back" href="grade_book.php?offering_id=<?= $offeringId ?>"><i class="fas fa-arrow-left"></i> Back to Grade Book</a>

            <!-- ============ Hero ============ -->
            <section class="ss-hero">
                <?php if ($logoUrl): ?><img class="ss-hero-watermark" src="<?= htmlspecialchars($logoUrl) ?>" alt="" aria-hidden="true"><?php endif; ?>

                <div class="ss-hero-main">
                    <div class="ss-avatar" aria-hidden="true"><?= htmlspecialchars($initialsStu) ?></div>
                    <div class="ss-hero-text">
                        <p class="eyebrow">STUDENT GRADE DETAILS</p>
                        <h1><?= htmlspecialchars($fullName) ?></h1>
                        <div class="ss-meta">
                            <span class="ss-chip"><i class="fas fa-id-card"></i> LRN: <?= htmlspecialchars($student['student_lrn']) ?></span>
                            <span class="ss-chip"><i class="fas fa-users"></i> <?= htmlspecialchars($student['section_name']) ?></span>
                            <span class="ss-chip"><i class="fas fa-calendar-days"></i> <?= htmlspecialchars($student['quarter'] . ' · ' . $student['school_year']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="ss-hero-side">
                    <?php if ($logoUrl): ?>
                        <img class="ss-logo" src="<?= htmlspecialchars($logoUrl) ?>" alt="St. Uriel Academy logo">
                    <?php else: ?>
                        <div class="ss-logo-fallback"><i class="fas fa-graduation-cap"></i></div>
                    <?php endif; ?>
                    <div class="ss-side-text">
                        <small>Class</small>
                        <strong><?= htmlspecialchars($student['subject_name']) ?></strong>
                        <span>St. Uriel Academy</span>
                    </div>
                </div>
            </section>

            <!-- ============ Quick stats ============ -->
            <section class="ss-stats" aria-label="Summary">
                <article class="ss-stat">
                    <div class="ss-stat-icon"><i class="fas fa-percent"></i></div>
                    <div>
                        <span>Average score</span>
                        <strong><?= $overall !== null ? ss_fmt($overall) . '%' : '—' ?></strong>
                        <small>Across scored items</small>
                    </div>
                </article>
                <article class="ss-stat blue">
                    <div class="ss-stat-icon"><i class="fas fa-list-check"></i></div>
                    <div>
                        <span>Submitted</span>
                        <strong><?= $doneItems ?> / <?= $totalItems ?></strong>
                        <small><?= $completion ?>% completion</small>
                    </div>
                </article>
                <article class="ss-stat gold">
                    <div class="ss-stat-icon"><i class="fas fa-star"></i></div>
                    <div>
                        <span>Scored items</span>
                        <strong><?= $trendCount ?></strong>
                        <small>With recorded scores</small>
                    </div>
                </article>
                <article class="ss-stat <?= $trendClass ?>">
                    <div class="ss-stat-icon"><i class="fas <?= $trendIcon ?>"></i></div>
                    <div>
                        <span>Trend</span>
                        <strong><?= $trendLabel ?></strong>
                        <small>Recent vs earlier work</small>
                    </div>
                </article>
            </section>

            <!-- ============ Category status ============ -->
            <section class="ss-cats">
                <?php foreach (GB_CATEGORIES as $key => $label): ?>
                    <article class="ss-cat">
                        <span><i class="fas <?= $icons[$key] ?>"></i> <?= htmlspecialchars($label) ?></span>
                        <strong><span class="status-pill status-<?= $catStatus[$key] ?>"><?= gb_status_label($catStatus[$key]) ?></span></strong>
                        <small><?= count($items[$key]) ?> item<?= count($items[$key]) == 1 ? '' : 's' ?></small>
                    </article>
                <?php endforeach; ?>
            </section>

            <!-- ============ Performance trends ============ -->
            <section class="ss-panel">
                <div class="ss-panel-head">
                    <h3><i class="fas fa-chart-line"></i> Performance Trends</h3>
                </div>
                <?php if ($trendCount): ?>
                    <div class="ss-trend-chips">
                        <span class="ss-trend-chip">Average score<strong><?= ss_fmt($overall) ?>%</strong></span>
                        <span class="ss-trend-chip">Scored items<strong><?= $trendCount ?></strong></span>
                        <span class="ss-trend-chip <?= $trendClass ?>">Trend<strong><i class="fas <?= $trendIcon ?>"></i> <?= $trendLabel ?></strong></span>
                    </div>
                    <div class="ss-trend-grid">
                        <div class="ss-trend-box">
                            <h4>Score per item (%) vs class average</h4>
                            <div class="ss-chart"><canvas id="trendLine"></canvas></div>
                        </div>
                        <div class="ss-trend-box">
                            <h4>Average by category (%)</h4>
                            <div class="ss-chart"><canvas id="trendBar"></canvas></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="ss-trend-empty"><i class="fas fa-chart-simple" style="display:block;font-size:24px;margin-bottom:8px;color:var(--primary-light);opacity:.55"></i>No scored work yet, so there is no trend to show.</div>
                <?php endif; ?>
            </section>

            <!-- ============ Score tables ============ -->
            <?php foreach (GB_CATEGORIES as $key => $label): ?>
                <section class="ss-panel">
                    <div class="ss-panel-head">
                        <h3><i class="fas <?= $icons[$key] ?>"></i> <?= htmlspecialchars($label) ?></h3>
                        <span class="ss-count"><?= count($items[$key]) ?> item<?= count($items[$key]) == 1 ? '' : 's' ?></span>
                    </div>
                    <div class="ss-table-wrap">
                        <table class="ss-table">
                            <thead>
                                <tr>
                                    <th class="col-name">Name</th>
                                    <th class="col-type">Type</th>
                                    <th class="col-score">Score</th>
                                    <th class="col-status">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items[$key] as $it):
                                    $ok = !empty($studentDone[$it['uid']]);
                                    $sc = $studentScores[$it['uid']] ?? null;
                                    $pct = null;
                                    if (!$ok) {
                                        $valText = '—';
                                    } elseif ($sc === null || $sc['score'] === null) {
                                        $valText = 'Pending';
                                    } else {
                                        $pct = $sc['max'] > 0 ? ($sc['score'] / $sc['max']) * 100 : null;
                                        $valText = ss_fmt($sc['score']) . ($sc['max'] > 0 ? ' / ' . ss_fmt($sc['max']) : '');
                                    }
                                    $gc = ss_grade_class($pct);
                                ?>
                                    <tr>
                                        <td class="col-name" data-label="Name"><span class="ss-item-title"><?= htmlspecialchars($it['title']) ?></span></td>
                                        <td class="col-type" data-label="Type"><span class="ss-type"><?= htmlspecialchars($it['kind']) ?></span></td>
                                        <td class="col-score" data-label="Score">
                                            <div class="ss-score <?= $gc ?>">
                                                <span class="ss-score-val"><?= htmlspecialchars($valText) ?></span>
                                                <?php if ($pct !== null): ?>
                                                    <span class="ss-bar" title="<?= ss_fmt($pct) ?>%"><i style="width:<?= max(0, min(100, round($pct))) ?>%"></i></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="col-status" data-label="Status"><span class="status-pill <?= $ok ? 'status-submitted' : 'status-not-submitted' ?>"><?= $ok ? 'Submitted' : 'Not Submitted' ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$items[$key]): ?>
                                    <tr class="ss-empty-row">
                                        <td colspan="4" class="ss-empty"><i class="fas fa-inbox"></i>Nothing posted in this category yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>

        </div>
    </main>

    <?php if ($trendCount): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
        <script>
            (function () {
                var trend = <?= json_encode($trend, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                var cat = <?= json_encode($catChart) ?>;
                var green = '#2d6a4f', gold = '#f4a300';
                var small = window.matchMedia('(max-width: 768px)').matches;
                var pctScale = { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } }, grid: { color: 'rgba(16,40,30,.06)' } };
                var legend = { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { size: small ? 11 : 12 } } };

                new Chart(document.getElementById('trendLine'), {
                    type: 'line',
                    data: {
                        labels: trend.map(function (t) { return t.label; }),
                        datasets: [
                            { label: 'Student', data: trend.map(function (t) { return t.student; }), borderColor: green, backgroundColor: 'rgba(45,106,79,.14)', fill: true, tension: .35, pointRadius: 4, pointBackgroundColor: green },
                            { label: 'Class average', data: trend.map(function (t) { return t.class; }), borderColor: gold, borderDash: [6, 4], tension: .35, pointRadius: 3, pointBackgroundColor: gold, fill: false }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: pctScale, x: { grid: { display: false }, ticks: { maxRotation: 40, autoSkip: true, font: { size: small ? 10 : 12 }, callback: function (v) { var l = this.getLabelForValue(v); return l.length > 16 ? l.slice(0, 15) + '…' : l; } } } },
                        plugins: {
                            legend: legend,
                            tooltip: {
                                callbacks: {
                                    afterTitle: function (c) { return trend[c[0].dataIndex].cat; },
                                    label: function (c) { return c.dataset.label + ': ' + c.parsed.y + '%'; }
                                }
                            }
                        }
                    }
                });

                new Chart(document.getElementById('trendBar'), {
                    type: 'bar',
                    data: {
                        labels: cat.labels,
                        datasets: [
                            { label: 'Student', data: cat.student, backgroundColor: green, borderRadius: 6 },
                            { label: 'Class average', data: cat.class, backgroundColor: gold, borderRadius: 6 }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: pctScale, x: { grid: { display: false }, ticks: { font: { size: small ? 10 : 12 } } } },
                        plugins: {
                            legend: legend,
                            tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + (c.parsed.y === null ? 'N/A' : c.parsed.y + '%'); } } }
                        }
                    }
                });
            })();
        </script>
    <?php endif; ?>
</body>

</html>