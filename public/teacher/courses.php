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
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--green">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= count($mySections) ?></span>
                        <span class="stat-card__label">Active Sections</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--blue">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= (int) $totalSubjects ?></span>
                        <span class="stat-card__label">Total Subjects</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--amber">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= (int) $totalStudents ?></span>
                        <span class="stat-card__label">Total Students</span>
                    </div>
                </div>
            </section>

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
                    // Pick a consistent (not random-looking) accent based on grade + strand,
                    // cycling through the theme palette instead of a random HSL hue.
                    $colorSeed = md5($sec['grade_level'] . ($sec['strand'] ?? 'General'));
                    $paletteIndex = hexdec(substr($colorSeed, 0, 2)) % count($themeAccents);
                    $accent = $themeAccents[$paletteIndex]['accent'];
                    $accentLight = $themeAccents[$paletteIndex]['light'];

                    // Mock progress (replace with real data from DB)
                    $progress = min(100, max(10, (hexdec(substr($colorSeed, 2, 2)) % 100)));
                    ?>



                    <a class="section-card" href="course_section.php?section_id=<?= (int) $sec['section_id'] ?>"
                        data-grade="grade-<?= (int) $sec['grade_level'] ?>"
                        data-strand="<?= htmlspecialchars($sec['strand'] ?? '') ?>"
                        data-name="<?= htmlspecialchars(strtolower($sec['section_name'])) ?>"
                        style="--card-accent: <?= $accent ?>; --card-accent-light: <?= $accentLight ?>;">

                        <div class="section-card__accent"></div>

                        <div class="section-card__top">
                            <span class="section-grade">Grade <?= (int) $sec['grade_level'] ?></span>
                            <?php if (!empty($sec['strand'])): ?>
                                <span class="section-strand"><?= htmlspecialchars($sec['strand']) ?></span>
                            <?php endif; ?>
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
                    const strand = (card.dataset.strand || '').toLowerCase();
                    const matchesSearch = !term || name.includes(term) || grade.includes(term) || strand.includes(term);
                    const matchesChip = activeFilter === 'all'
                        || activeFilter === grade
                        || activeFilter.toLowerCase() === strand;
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