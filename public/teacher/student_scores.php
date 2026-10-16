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
$stmt = $pdo->prepare("SELECT co.offering_id,co.quarter,sub.subject_name,sec.section_name,sy.label school_year,s.student_id,s.student_lrn,s.firstname,s.lastname,s.middlename FROM classofferings co JOIN subjects sub ON sub.subject_id=co.subject_id JOIN sections sec ON sec.section_id=co.section_id JOIN schoolyears sy ON sy.school_year_id=co.school_year_id JOIN enrollments e ON e.offering_id=co.offering_id AND e.student_id=? AND e.status='enrolled' JOIN students s ON s.student_id=e.student_id WHERE co.offering_id=? AND co.teacher_id=? AND co.status='active' LIMIT 1");
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
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $name ?> · Grade Details</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/grade_book.css">
    <style>
        .details-page {
            max-width: 1300px;
            margin: auto
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #4f46e5;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 18px
        }

        .student-hero {
            background: #fff;
            border: 1px solid #e5eaf0;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px
        }

        .student-profile {
            display: flex;
            align-items: center;
            gap: 14px
        }

        .big-avatar {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #eef2ff;
            color: #4f46e5;
            font-weight: 900
        }

        .student-profile h2 {
            margin: 0;
            font-size: 22px
        }

        .student-profile p {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 13px
        }

        .score-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin: 16px 0
        }

        .score-card {
            background: #fff;
            border: 1px solid #e5eaf0;
            border-radius: 15px;
            padding: 18px
        }

        .score-card span,
        .score-card small {
            display: block;
            color: #64748b;
            font-size: 12px
        }

        .score-card strong {
            font-size: 26px;
            display: block;
            margin: 6px 0
        }

        .detail-panel {
            background: #fff;
            border: 1px solid #e5eaf0;
            border-radius: 15px;
            margin-top: 16px;
            overflow: hidden
        }

        .detail-panel h3 {
            margin: 0;
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f4;
            font-size: 16px
        }

        .score-list {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed
        }

        /* Same column widths in every category table so they line up */
        .score-list col.col-name {
            width: 40%
        }

        .score-list col.col-type {
            width: 20%
        }

        .score-list col.col-score {
            width: 20%
        }

        .score-list col.col-status {
            width: 20%
        }

        .score-list td:first-child {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap
        }

        .score-list th,
        .score-list td {
            text-align: left;
            padding: 13px 18px;
            border-bottom: 1px solid #eef1f4;
            font-size: 13px
        }

        .score-list th {
            font-size: 11px;
            text-transform: uppercase;
            color: #64748b;
            background: #f8fafc
        }

        .score-list td:nth-child(3),
        .score-list th:nth-child(3),
        .score-list td:last-child,
        .score-list th:last-child {
            text-align: right
        }

        .score-value {
            font-weight: 800
        }

        .score-value.excellent {
            color: #047857
        }

        .score-value.good {
            color: #2563eb
        }

        .score-value.fair {
            color: #b45309
        }

        .score-value.low {
            color: #dc2626
        }

        .score-value.none {
            color: #94a3b8
        }

        .status {
            font-size: 11px;
            color: #64748b
        }

        .mobile-table {
            overflow: auto
        }

        .empty-row {
            text-align: center;
            color: #94a3b8;
            padding: 28px !important
        }

        /* REQ021 - performance trends */
        .trend-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 16px 20px 0
        }

        .trend-chip {
            background: #f8fafc;
            border: 1px solid #e5eaf0;
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 12px;
            color: #64748b
        }

        .trend-chip strong {
            color: #1c2b24;
            margin-left: 4px
        }

        .trend-chip.up strong {
            color: #047857
        }

        .trend-chip.down strong {
            color: #dc2626
        }

        .trend-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 18px;
            padding: 18px 20px 20px
        }

        .trend-box h4 {
            margin: 0 0 10px;
            font-size: 12px;
            text-transform: uppercase;
            color: #64748b
        }

        .chart-wrap {
            position: relative;
            height: 260px
        }

        @media(max-width:900px) {
            .trend-grid {
                grid-template-columns: 1fr
            }
        }

        @media(max-width:700px) {
            .student-hero {
                align-items: flex-start
            }

            .score-cards {
                grid-template-columns: 1fr
            }

            .student-profile h2 {
                font-size: 18px
            }

            .score-list {
                min-width: 560px
            }

            .student-hero {
                display: block
            }

            .student-hero .eyebrow {
                margin-top: 14px
            }
        }
    </style>
</head>

<body><?php include '../../includes/teachers_sidebar.php'; ?><main class="main-content">
        <div class="page-wrap details-page"><a class="back-link" href="grade_book.php?offering_id=<?= $offeringId ?>"><i class="fas fa-arrow-left"></i> Back to Grade Book</a>
            <section class="student-hero">
                <div class="student-profile">
                    <div class="big-avatar"><?= strtoupper(substr($student['firstname'], 0, 1) . substr($student['lastname'], 0, 1)) ?></div>
                    <div>
                        <h2><?= $name ?></h2>
                        <p>LRN: <?= htmlspecialchars($student['student_lrn']) ?></p>
                    </div>
                </div>
                <div>
                    <p class="eyebrow">CLASS</p><strong><?= htmlspecialchars($student['subject_name'] . ' · ' . $student['section_name']) ?></strong>
                    <p class="page-subtitle"><?= htmlspecialchars($student['quarter'] . ' · ' . $student['school_year']) ?></p>
                </div>
            </section>
            <section class="score-cards">
                <?php foreach (GB_CATEGORIES as $key => $label): ?>
                    <article class="score-card"><span><?= htmlspecialchars($label) ?></span><strong><span class="status-pill status-<?= $catStatus[$key] ?>"><?= gb_status_label($catStatus[$key]) ?></span></strong><small><?= count($items[$key]) ?> item<?= count($items[$key]) == 1 ? '' : 's' ?></small></article>
                <?php endforeach; ?>
            </section>
            <section class="detail-panel">
                <h3><i class="fas fa-chart-line"></i> Performance Trends</h3>
                <?php if ($trendCount): ?>
                    <div class="trend-summary">
                        <span class="trend-chip">Average score<strong><?= ss_fmt($overall) ?>%</strong></span>
                        <span class="trend-chip">Scored items<strong><?= $trendCount ?></strong></span>
                        <span class="trend-chip <?= $trendDir ?>">Trend<strong>
                                <?php if ($trendCount < 2): ?>Not enough data
                                <?php elseif ($trendDir === 'up'): ?><i class="fas fa-arrow-trend-up"></i> Improving
                                <?php elseif ($trendDir === 'down'): ?><i class="fas fa-arrow-trend-down"></i> Declining
                                <?php else: ?><i class="fas fa-minus"></i> Steady<?php endif; ?></strong></span>
                    </div>
                    <div class="trend-grid">
                        <div class="trend-box">
                            <h4>Score per item (%) vs class average</h4>
                            <div class="chart-wrap"><canvas id="trendLine"></canvas></div>
                        </div>
                        <div class="trend-box">
                            <h4>Average by category (%)</h4>
                            <div class="chart-wrap"><canvas id="trendBar"></canvas></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-row" style="padding:28px">No scored work yet, so there is no trend to show.</div>
                <?php endif; ?>
            </section>
            <?php $icons = ['ww' => 'fa-file-circle-question', 'pt' => 'fa-clipboard-check', 'exam' => 'fa-file-pen'];
            foreach (GB_CATEGORIES as $key => $label): ?>
                <section class="detail-panel">
                    <h3><i class="fas <?= $icons[$key] ?>"></i> <?= htmlspecialchars($label) ?></h3>
                    <div class="mobile-table">
                        <table class="score-list">
                            <colgroup>
                                <col class="col-name"><col class="col-type"><col class="col-score"><col class="col-status">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($items[$key] as $it):
                                $ok = !empty($studentDone[$it['uid']]);
                                $sc = $studentScores[$it['uid']] ?? null;
                                if (!$ok) {
                                    $scoreHtml = '<span class="score-value none">—</span>';
                                } elseif ($sc === null || $sc['score'] === null) {
                                    $scoreHtml = '<span class="score-value none">Pending</span>';
                                } else {
                                    $pct = $sc['max'] > 0 ? ($sc['score'] / $sc['max']) * 100 : null;
                                    $cls = $pct === null ? 'none' : ($pct >= 90 ? 'excellent' : ($pct >= 75 ? 'good' : ($pct >= 60 ? 'fair' : 'low')));
                                    $scoreHtml = '<span class="score-value ' . $cls . '">' . ss_fmt($sc['score']) . ($sc['max'] > 0 ? ' / ' . ss_fmt($sc['max']) : '') . '</span>';
                                } ?><tr>
                                        <td><strong><?= htmlspecialchars($it['title']) ?></strong></td>
                                        <td><?= htmlspecialchars($it['kind']) ?></td>
                                        <td><?= $scoreHtml ?></td>
                                        <td><span class="status-pill <?= $ok ? 'status-submitted' : 'status-not-submitted' ?>"><?= $ok ? 'Submitted' : 'Not Submitted' ?></span></td>
                                    </tr><?php endforeach; ?><?php if (!$items[$key]): ?><tr>
                                        <td colspan="4" class="empty-row">Nothing posted in this category yet.</td>
                                    </tr><?php endif; ?></tbody>
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
                var green = '#2d9d63', grey = '#94a3b8';
                var pctScale = { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } } };
                new Chart(document.getElementById('trendLine'), {
                    type: 'line',
                    data: {
                        labels: trend.map(function (t) { return t.label; }),
                        datasets: [
                            { label: 'Student', data: trend.map(function (t) { return t.student; }), borderColor: green, backgroundColor: 'rgba(45,157,99,.12)', fill: true, tension: .3, pointRadius: 4 },
                            { label: 'Class average', data: trend.map(function (t) { return t.class; }), borderColor: grey, borderDash: [6, 4], tension: .3, pointRadius: 3, fill: false }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: pctScale, x: { ticks: { maxRotation: 40, autoSkip: true } } },
                        plugins: {
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
                            { label: 'Class average', data: cat.class, backgroundColor: '#cbd5e1', borderRadius: 6 }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: pctScale },
                        plugins: { tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + (c.parsed.y === null ? 'N/A' : c.parsed.y + '%'); } } } }
                    }
                });
            })();
        </script>
    <?php endif; ?>
</body>

</html>