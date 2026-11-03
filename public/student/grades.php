<?php
include 'assets/api/grades_functions.php';

$termLabels = [
    'TRM 1' => 'Term 1',
    'TRM 2' => 'Term 2',
    'TRM 3' => 'Term 3',
];
$categoryIcons = [
    'ww'   => 'fa-file-circle-question',
    'pt'   => 'fa-clipboard-check',
    'exam' => 'fa-file-pen',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Submissions · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/grades.css">
</head>
<body>

<?php include '../../includes/student_sidebar.php'; ?>

<main class="main-content" id="dashMain">

    <?php include '../../includes/student_header.php'; ?>

    <div class="dash-page-title">
        <h1 class="dash-title">My Submissions</h1>
        <?php if ($selectedYearLabel): ?>
            <p class="dash-subtitle">School Year <?= htmlspecialchars($selectedYearLabel) ?> &middot; your quizzes, activities and exams across all enrolled subjects</p>
        <?php endif; ?>
    </div>

    <?php if (empty($schoolYears) || $totalClasses === 0): ?>
        <section class="panel">
            <div class="panel-empty panel-empty--enhanced">
                <div class="panel-empty__icon">
                    <i class="fas fa-file-circle-check"></i>
                </div>
                <h3>No submissions to show yet</h3>
                <p>Once you're enrolled in a class and your teacher posts work, it will show up here.</p>
                <span class="panel-empty__hint">Check the My Courses page to see your enrolled classes.</span>
            </div>
        </section>
    <?php else: ?>

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
            <?php $first = true; foreach ($termGroups as $term => $subjects):
                if (empty($subjects)) continue;
                $label = $termLabels[$term] ?? $term;
            ?>
                <button type="button" class="term-tab <?= $first ? 'active' : '' ?>" data-term="<?= htmlspecialchars($term) ?>">
                    <?= htmlspecialchars($label) ?>
                </button>
            <?php $first = false; endforeach; ?>
        </div>

        <!-- Term Panels -->
        <?php $first = true; foreach ($termGroups as $term => $subjects):
            if (empty($subjects)) continue;
            $label = $termLabels[$term] ?? $term;
        ?>
        <section class="term-panel <?= $first ? 'active' : '' ?>" data-term="<?= htmlspecialchars($term) ?>">

            <?php foreach ($subjects as $s): ?>
            <article class="subj-card is-collapsed">
                <button type="button" class="subj-head" aria-expanded="false">
                    <div class="subj-head__info">
                        <h2><?= htmlspecialchars($s['subject_name']) ?></h2>
                        <p>
                            <i class="fas fa-chalkboard-user"></i> <?= htmlspecialchars($s['teacher_name']) ?>
                            <?php if (!empty($s['section_name'])): ?>
                                &middot; <?= htmlspecialchars($s['section_name']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="subj-head__meta">
                        <?php if ($s['total_items'] > 0): ?>
                            <span class="subj-summary <?= $s['submitted'] === $s['total_items'] ? 'subj-summary--done' : '' ?>">
                                <?= (int) $s['submitted'] ?> of <?= (int) $s['total_items'] ?> submitted
                            </span>
                        <?php else: ?>
                            <span class="subj-summary">Nothing posted yet</span>
                        <?php endif; ?>
                        <i class="fas fa-chevron-down subj-chevron"></i>
                    </div>
                </button>

                <div class="subj-body">
                    <?php foreach ($s['categories'] as $key => $cat): ?>
                    <section class="detail-panel">
                        <h3><i class="fas <?= $categoryIcons[$key] ?>"></i> <?= htmlspecialchars($cat['label']) ?></h3>
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
                                <tbody>
                                    <?php foreach ($cat['rows'] as $r): ?>
                                    <tr>
                                        <td title="<?= htmlspecialchars($r['title']) ?>">
                                            <?php if ($r['url']): ?>
                                                <a class="item-link" href="<?= htmlspecialchars($r['url']) ?>"><strong><?= htmlspecialchars($r['title']) ?></strong></a>
                                            <?php else: ?>
                                                <strong><?= htmlspecialchars($r['title']) ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($r['kind']) ?></td>
                                        <td><span class="score-value <?= htmlspecialchars($r['score_class']) ?>"><?= htmlspecialchars($r['score_text']) ?></span></td>
                                        <td>
                                            <span class="sub-pill <?= $r['submitted'] ? 'sub-pill--ok' : 'sub-pill--no' ?>">
                                                <?= $r['submitted'] ? 'Submitted' : 'Not Submitted' ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$cat['rows']): ?>
                                    <tr>
                                        <td colspan="4" class="empty-row">Nothing posted in this category yet.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <?php endforeach; ?>
                </div>
            </article>
            <?php endforeach; ?>

        </section>
        <?php $first = false; endforeach; ?>

    <?php endif; ?>

</main>

<script>
(function () {
    // Term tabs
    const tabs = document.querySelectorAll('.term-tab');
    const panels = document.querySelectorAll('.term-panel');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const term = tab.dataset.term;
            tabs.forEach(t => t.classList.toggle('active', t === tab));
            panels.forEach(p => p.classList.toggle('active', p.dataset.term === term));
        });
    });

    // Collapse / expand a subject's tables
    document.querySelectorAll('.subj-head').forEach(head => {
        head.addEventListener('click', () => {
            const card = head.closest('.subj-card');
            const collapsed = card.classList.toggle('is-collapsed');
            head.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        });
    });
})();
</script>

</body>
</html>
