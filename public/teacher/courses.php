<?php
include 'assets/api/courses_functions.php';

// Compute summary stats
$totalSubjects = array_sum(array_column($mySections, 'subject_count'));
$totalStudents = array_sum(array_column($mySections, 'student_count'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/courses.css">
</head>

<body>

    <?php include '../../includes/teachers_sidebar.php'; ?>

    <main class="main-content" id="dashMain">

        <?php include '../../includes/teacher_header.php'; ?>

        <div class="dash-page-title">
            <h1 class="dash-title">Sections</h1>
            <?php if ($schoolYearLabel): ?>
                <p class="dash-subtitle">School Year <?= htmlspecialchars($schoolYearLabel) ?></p>
            <?php endif; ?>
        </div>

        <?php if (empty($mySections)): ?>
            <section class="panel">
                <div class="panel-empty panel-empty--enhanced">
                    <div class="panel-empty__icon">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <h3>No active classes assigned yet</h3>
                    <p>Once the admin assigns you a class, its section will show up here.</p>
                    <span class="panel-empty__hint">Check back later or contact your administrator.</span>
                </div>
            </section>
        <?php else: ?>

            <!-- Stats Summary Bar -->
            <section class="stats-bar">
                <button type="button" class="stat-card stat-card--link" data-open="sectionsDialog" aria-haspopup="dialog">
                    <div class="stat-card__icon stat-card__icon--green">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= count($mySections) ?></span>
                        <span class="stat-card__label">Active Sections</span>
                    </div>
                </button>
                <button type="button" class="stat-card stat-card--link" data-open="subjectsDialog" aria-haspopup="dialog">
                    <div class="stat-card__icon stat-card__icon--blue">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= (int) $totalSubjects ?></span>
                        <span class="stat-card__label">Total Subjects</span>
                    </div>
                </button>
                <button type="button" class="stat-card stat-card--link" data-open="studentsDialog" aria-haspopup="dialog">
                    <div class="stat-card__icon stat-card__icon--amber">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= (int) $totalStudents ?></span>
                        <span class="stat-card__label">Total Students</span>
                    </div>
                </button>
            </section>

            <!-- Detail dialogs opened by the three summary cards -->
            <dialog class="stat-dialog" id="sectionsDialog" aria-labelledby="sectionsDialogTitle">
                <div class="stat-dialog__head">
                    <h3 id="sectionsDialogTitle">Active Sections</h3>
                    <button type="button" class="stat-dialog__close" data-close aria-label="Close"><i class="fas fa-xmark"></i></button>
                </div>
                <ul class="stat-dialog__list">
                    <?php foreach ($mySections as $sec): ?>
                        <li>
                            <a href="course_section.php?section_id=<?= (int) $sec['section_id'] ?>">
                                <span class="stat-dialog__info">
                                    <strong><?= htmlspecialchars($sec['section_name']) ?></strong>
                                    <small>Grade <?= (int) $sec['grade_level'] ?> · <?= (int) $sec['subject_count'] ?> <?= $sec['subject_count'] == 1 ? 'subject' : 'subjects' ?> · <?= (int) $sec['student_count'] ?> <?= $sec['student_count'] == 1 ? 'student' : 'students' ?></small>
                                </span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </dialog>

            <dialog class="stat-dialog" id="subjectsDialog" aria-labelledby="subjectsDialogTitle">
                <div class="stat-dialog__head">
                    <h3 id="subjectsDialogTitle">Total Subjects</h3>
                    <button type="button" class="stat-dialog__close" data-close aria-label="Close"><i class="fas fa-xmark"></i></button>
                </div>
                <ul class="stat-dialog__list">
                    <?php foreach ($subjectList as $o): ?>
                        <li>
                            <a href="class_overview.php?<?= htmlspecialchars(http_build_query(['subject_id' => (int) $o['subject_id'], 'section_id' => (int) $o['section_id'], 'term' => $o['quarter']])) ?>">
                                <span class="stat-dialog__info">
                                    <strong><?= htmlspecialchars($o['subject_name']) ?></strong>
                                    <small>Grade <?= (int) $o['grade_level'] ?> · <?= htmlspecialchars($o['section_name']) ?></small>
                                </span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </dialog>

            <dialog class="stat-dialog stat-dialog--wide" id="studentsDialog" aria-labelledby="studentsDialogTitle">
                <div class="stat-dialog__head">
                    <h3 id="studentsDialogTitle">Total Students</h3>
                    <button type="button" class="stat-dialog__close" data-close aria-label="Close"><i class="fas fa-xmark"></i></button>
                </div>
                <input type="search" class="stat-dialog__search" id="studentsDialogSearch" placeholder="Search students…" autocomplete="off">
                <ul class="stat-dialog__list" id="studentsDialogList">
                    <?php foreach ($studentList as $st): ?>
                        <li class="stat-dialog__student<?= count($st['subjects']) === 1 ? ' stat-dialog__student--single' : '' ?>" data-search="<?= htmlspecialchars(strtolower($st['name'] . ' ' . $st['section'])) ?>">
                            <div class="stat-dialog__info">
                                <strong><?= htmlspecialchars($st['name']) ?></strong>
                                <small>Grade <?= (int) $st['grade'] ?> · <?= htmlspecialchars($st['section']) ?></small>
                            </div>
                            <div class="stat-dialog__chips">
                                <?php foreach ($st['subjects'] as $sj): ?>
                                    <a class="stat-dialog__chip" href="<?= htmlspecialchars($sj['url']) ?>" title="View grades in <?= htmlspecialchars($sj['name']) ?>"><?= htmlspecialchars($sj['name']) ?></a>
                                <?php endforeach; ?>
                                <?php if (count($st['subjects']) === 1): ?>
                                    <i class="fas fa-arrow-right stat-dialog__arrow"></i>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="stat-dialog__empty" id="studentsDialogEmpty" hidden>No students match your search.</p>
            </dialog>

            <!-- Section Cards Grid -->
            <section class="section-grid" id="sectionGrid">
                <?php

                $themeAccents = [
                    ['accent' => '#1b4332', 'light' => '#e6f0ea'], // primary (deep green)
                    ['accent' => '#2d6a4f', 'light' => '#eaf5ef'], // primary-light
                    ['accent' => '#2d9d63', 'light' => '#e6f5ec'], // ok (green)
                    ['accent' => '#0f766e', 'light' => '#e6f4f3'], // teal
                    ['accent' => '#f4a300', 'light' => '#fdf3e0'], // accent (amber)
                    ['accent' => '#d97706', 'light' => '#fdf0dd'], // warn/amber-deep
                ];


                foreach ($mySections as $index => $sec):
                    // Pick a consistent (not random-looking) accent based on grade level,
                    // cycling through the theme palette instead of a random HSL hue.
                    $colorSeed = md5($sec['grade_level'] . 'General');
                    $paletteIndex = hexdec(substr($colorSeed, 0, 2)) % count($themeAccents);
                    $accent = $themeAccents[$paletteIndex]['accent'];
                    $accentLight = $themeAccents[$paletteIndex]['light'];

                    // Mock progress (replace with real data from DB)
                    $progress = min(100, max(10, (hexdec(substr($colorSeed, 2, 2)) % 100)));
                    ?>



                    <a class="section-card" href="course_section.php?section_id=<?= (int) $sec['section_id'] ?>"
                        data-grade="grade-<?= (int) $sec['grade_level'] ?>"
                        data-name="<?= htmlspecialchars(strtolower($sec['section_name'])) ?>"
                        style="--card-accent: <?= $accent ?>; --card-accent-light: <?= $accentLight ?>;">

                        <div class="section-card__accent"></div>

                        <div class="section-card__top">
                            <span class="section-grade">Grade <?= (int) $sec['grade_level'] ?></span>
                        </div>

                        <h3 class="section-name"><?= htmlspecialchars($sec['section_name']) ?></h3>

                        <div class="section-card__stats">
                            <span><i class="fas fa-book"></i> <?= (int) $sec['subject_count'] ?>
                                <?= $sec['subject_count'] == 1 ? 'subject' : 'subjects' ?></span>
                            <span><i class="fas fa-users"></i> <?= (int) $sec['student_count'] ?>
                                <?= $sec['student_count'] == 1 ? 'student' : 'students' ?></span>
                        </div>

                        <div class="section-card__footer">
                            <span class="section-card__cta">
                                View subjects <i class="fas fa-arrow-right"></i>
                            </span>
                            <div class="section-card__quick-actions">
                                <span class="qa-btn" title="Announcements"><i class="fas fa-bullhorn"></i></span>
                                <span class="qa-btn" title="Gradebook"><i class="fas fa-chart-line"></i></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </section>



        <?php endif; ?>

    </main>


    <script>
        /* Summary cards open their detail dialogs */
        (function () {
            document.querySelectorAll('[data-open]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var dlg = document.getElementById(btn.dataset.open);
                    if (!dlg) return;
                    if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
                });
            });
            document.querySelectorAll('.stat-dialog').forEach(function (dlg) {
                dlg.addEventListener('click', function (e) {
                    if (e.target === dlg || e.target.closest('[data-close]')) dlg.close();
                });
            });
            var search = document.getElementById('studentsDialogSearch');
            if (search) {
                var rows = document.querySelectorAll('#studentsDialogList .stat-dialog__student');
                var empty = document.getElementById('studentsDialogEmpty');
                search.addEventListener('input', function () {
                    var q = search.value.toLowerCase().trim(), shown = 0;
                    rows.forEach(function (r) {
                        var ok = !q || r.dataset.search.indexOf(q) !== -1;
                        r.style.display = ok ? '' : 'none';
                        if (ok) shown++;
                    });
                    empty.hidden = shown !== 0;
                });
            }
        })();
    </script>

    <script>
        /* Simple client-side search & filter */
        (function () {
            const searchInput = document.getElementById('sectionSearch');
            const cards = document.querySelectorAll('.section-card');
            const chips = document.querySelectorAll('.chip');
            let activeFilter = 'all';

            function filterCards() {
                const term = (searchInput?.value || '').toLowerCase().trim();
                cards.forEach(card => {
                    const name = card.dataset.name || '';
                    const grade = card.dataset.grade || '';
                    const matchesSearch = !term || name.includes(term) || grade.includes(term);
                    const matchesChip = activeFilter === 'all'
                        || activeFilter === grade;
                    card.style.display = (matchesSearch && matchesChip) ? '' : 'none';
                });
            }

            searchInput?.addEventListener('input', filterCards);

            chips.forEach(chip => {
                chip.addEventListener('click', () => {
                    chips.forEach(c => c.classList.remove('chip--active'));
                    chip.classList.add('chip--active');
                    activeFilter = chip.dataset.filter;
                    filterCards();
                });
            });
        })();
    </script>

</body>

</html>