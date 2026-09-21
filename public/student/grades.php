<?php
include 'assets/api/grades_functions.php';

$termLabels = [
    'TRM 1' => 'Term 1',
    'TRM 2' => 'Term 2',
    'TRM 3' => 'Term 3',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/grades.css">
</head>
<body>

<?php include '../../includes/student_sidebar.php'; ?>

<main class="main-content" id="dashMain">

    <?php include '../../includes/student_header.php'; ?>

    <div class="dash-page-title">
        <h1 class="dash-title">My Grades</h1>
        <?php if ($selectedYearLabel): ?>
            <p class="dash-subtitle">School Year <?= htmlspecialchars($selectedYearLabel) ?></p>
        <?php endif; ?>
    </div>

    <?php if (empty($schoolYears) || $totalClasses === 0): ?>
        <section class="panel">
            <div class="panel-empty panel-empty--enhanced">
                <div class="panel-empty__icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>No grades to show yet</h3>
                <p>Once you're enrolled in a class and your teacher posts a grade, it will show up here.</p>
                <span class="panel-empty__hint">Check the My Courses page to see your enrolled classes.</span>
            </div>
        </section>
    <?php else: ?>

        <!-- Stats Summary Bar -->
        <section class="stats-bar">
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--green">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-card__info">
                    <span class="stat-card__value"><?= $gwa !== null ? htmlspecialchars((string) $gwa) : '—' ?></span>
                    <span class="stat-card__label">General Average</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--blue">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-card__info">
                    <span class="stat-card__value"><?= (int) $passingCount ?></span>
                    <span class="stat-card__label">Passing</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--amber">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-card__info">
                    <span class="stat-card__value"><?= (int) $pendingCount ?></span>
                    <span class="stat-card__label">Not Yet Posted</span>
                </div>
            </div>
            <?php if ($atRiskCount > 0): ?>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--red">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <div class="stat-card__info">
                    <span class="stat-card__value"><?= (int) $atRiskCount ?></span>
                    <span class="stat-card__label">Needs Attention</span>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- School year selector (only meaningful if the student has more than one) -->
        <?php if (count($schoolYears) > 1): ?>
        <section class="grades-toolbar">
            <form method="get" id="yearForm">
                <select name="school_year_id" class="year-select" onchange="document.getElementById('yearForm').submit()">
                    <?php foreach ($schoolYears as $sy): ?>
                        <option value="<?= (int) $sy['school_year_id'] ?>" <?= (int) $sy['school_year_id'] === $selectedYearId ? 'selected' : '' ?>>
                            School Year <?= htmlspecialchars($sy['label']) ?><?= $sy['is_current'] ? ' (Current)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </section>
        <?php endif; ?>

        <!-- Term Tabs -->
        <div class="term-tabs" id="termTabs">
            <?php $first = true; foreach ($termGroups as $term => $rows):
                if (empty($rows)) continue;
                $label = $termLabels[$term] ?? $term;
            ?>
                <button type="button" class="term-tab <?= $first ? 'active' : '' ?>" data-term="<?= htmlspecialchars($term) ?>">
                    <?= htmlspecialchars($label) ?>
                </button>
            <?php $first = false; endforeach; ?>
        </div>

        <!-- Term Panels -->
        <?php $first = true; foreach ($termGroups as $term => $rows):
            if (empty($rows)) continue;
            $label = $termLabels[$term] ?? $term;
            $termFinals = array_column(array_filter($rows, fn($g) => $g['is_posted']), 'final_grade');
            $termAvg = $termFinals ? round(array_sum($termFinals) / count($termFinals), 2) : null;
        ?>
        <section class="term-panel <?= $first ? 'active' : '' ?>" data-term="<?= htmlspecialchars($term) ?>">

            <div class="term-panel-head">
                <h3><?= htmlspecialchars($label) ?> Class<?= count($rows) === 1 ? '' : 'es' ?></h3>
                <?php if ($termAvg !== null): ?>
                    <span class="term-avg">Term average: <strong><?= htmlspecialchars((string) $termAvg) ?></strong></span>
                <?php endif; ?>
            </div>

            <div class="grade-cards">
                <?php foreach ($rows as $g):
                    $statusClass = 'status-pill--pending';
                    $statusText  = 'Not Yet Posted';
                    if ($g['is_posted']) {
                        $statusClass = $g['is_passing'] ? 'status-pill--pass' : 'status-pill--fail';
                        $statusText  = $g['is_passing'] ? 'Passed' : 'Failed';
                    }
                    $scheduleText = $g['schedule_days'] ?? 'TBA';
                ?>
                <article class="grade-card">
                    <div class="grade-card-top">
                        <div class="grade-card-subject">
                            <h4><?= htmlspecialchars($g['subject_name']) ?></h4>
                            <p><i class="fas fa-chalkboard-user"></i> <?= htmlspecialchars($g['teacher_name']) ?></p>
                        </div>
                        <span class="status-pill <?= $statusClass ?>"><?= htmlspecialchars($statusText) ?></span>
                    </div>

                    <?php if ($g['is_posted']): ?>
                        <div class="grade-display">
                            <span class="grade-number <?= $g['is_passing'] ? 'pass' : 'fail' ?>">
                                <?= htmlspecialchars(number_format((float) $g['final_grade'], 2)) ?>
                            </span>
                            <div class="grade-meta">
                                <strong>Final Grade</strong>
                                <span>Posted <?= $g['posted_at'] ? date('M j, Y', strtotime($g['posted_at'])) : '' ?></span>
                            </div>
                        </div>
                        <?php if (!empty($g['remarks'])): ?>
                            <div class="grade-remarks">
                                <i class="fas fa-comment-dots"></i>
                                <span><?= htmlspecialchars($g['remarks']) ?></span>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($g['current_standing'] !== null): ?>
                        <div class="grade-display">
                            <span class="grade-number">
                                <?= htmlspecialchars(number_format((float) $g['current_standing'], 2)) ?>
                            </span>
                            <div class="grade-meta">
                                <strong>Current Standing</strong>
                                <span>Unofficial estimate &middot; not yet posted by teacher</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="grade-pending-note">
                            <i class="fas fa-circle-info"></i>
                            <span>No graded work yet for this class.</span>
                        </div>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php $first = false; endforeach; ?>

    <?php endif; ?>

</main>

<script>
(function () {
    const tabs = document.querySelectorAll('.term-tab');
    const panels = document.querySelectorAll('.term-panel');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const term = tab.dataset.term;
            tabs.forEach(t => t.classList.toggle('active', t === tab));
            panels.forEach(p => p.classList.toggle('active', p.dataset.term === term));
        });
    });
})();
</script>

</body>
</html>