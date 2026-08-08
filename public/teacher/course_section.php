<?php
include 'assets/api/course_section_functions.php';

// Compute summary stats
$totalSubjects   = count($sectionSubjects);
$totalEnrolled   = array_sum(array_column($sectionSubjects, 'enrolled_count'));
$totalCapacity   = array_sum(array_column($sectionSubjects, 'capacity'));
$avgEnrollment   = $totalCapacity > 0 ? round(($totalEnrolled / $totalCapacity) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($section['section_name']) ?> · Courses · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/course_section.css">
</head>
<body>

<?php include '../../includes/teachers_sidebar.php'; ?>

<main class="main-content" id="dashMain">

    <?php include '../../includes/teacher_header.php'; ?>

    <a href="courses.php" class="breadcrumb-back">
        <i class="fas fa-arrow-left"></i> Back to Courses
    </a>

    <div class="dash-page-title">
        <h1 class="dash-title">
            <?= htmlspecialchars($section['section_name']) ?>
            <span class="section-title-grade">
                Grade <?= (int) $section['grade_level'] ?>
                <?= !empty($section['strand']) ? ' · ' . htmlspecialchars($section['strand']) : '' ?>
            </span>
        </h1>
        <?php if ($schoolYearLabel): ?>
            <p class="dash-subtitle">School Year <?= htmlspecialchars($schoolYearLabel) ?></p>
        <?php endif; ?>
    </div>

    <?php if (empty($sectionSubjects)): ?>
        <section class="panel">
            <div class="panel-empty panel-empty--enhanced">
                <div class="panel-empty__icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <h3>No subjects here yet</h3>
                <p>You don't have any active classes in this section right now.</p>
                <span class="panel-empty__hint">Contact your administrator to assign subjects.</span>
            </div>
        </section>
    <?php else: ?>

        <!-- Stats Summary Bar -->
        <section class="stats-bar">
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--green">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-card__info">
                    <span class="stat-card__value"><?= (int) $totalSubjects ?></span>
                    <span class="stat-card__label">Subjects</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-card__info">
                    <span class="stat-card__value"><?= (int) $totalEnrolled ?></span>
                    <span class="stat-card__label">Total Enrolled</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--amber">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-card__info">
                    <span class="stat-card__value"><?= (int) $avgEnrollment ?>%</span>
                    <span class="stat-card__label">Avg. Capacity</span>
                </div>
            </div>
        </section>

        <!-- Toolbar: Search + Filters + Sort -->
        <section class="section-toolbar">
            <div class="toolbar-search">
                <i class="fas fa-search"></i>
                <input type="text" id="subjectSearch" placeholder="Search subjects by name, quarter, or schedule..." autocomplete="off">
            </div>
            <div class="toolbar-actions">
                <button class="btn-toolbar" id="btnFilter" title="Filter subjects">
                    <i class="fas fa-filter"></i> <span>Filter</span>
                </button>
                <button class="btn-toolbar" id="btnSort" title="Sort subjects">
                    <i class="fas fa-sort-amount-down"></i> <span>Sort</span>
                </button>
            </div>
        </section>

        

        <!-- Subject Cards Grid -->
        <section class="subject-grid" id="subjectGrid">
            <?php foreach ($sectionSubjects as $subj): 
                // Generate a consistent accent color based on subject name hash
                $colorSeed = md5($subj['subject_name']);
                $hue = hexdec(substr($colorSeed, 0, 2)) % 360;
                $accent = "hsl({$hue}, 70%, 45%)";
                $accentLight = "hsl({$hue}, 70%, 95%)";

                // Enrollment percentage
                $cap = max(1, (int) $subj['capacity']);
                $enrolledPct = min(100, round(((int) $subj['enrolled_count'] / $cap) * 100));
            ?>
            <a class="subject-card"
               href="class_overview.php?subject_id=<?= (int) $subj['subject_id'] ?>&section_id=<?= (int) $section['section_id'] ?>"
               data-name="<?= htmlspecialchars(strtolower($subj['subject_name'])) ?>"
               data-quarter="<?= htmlspecialchars(strtolower($subj['quarter'])) ?>"
               data-schedule="<?= htmlspecialchars(strtolower($subj['schedule_days'] ?? '')) ?>"
               style="--card-accent: <?= $accent ?>; --card-accent-light: <?= $accentLight ?>;">

                <div class="subject-card__accent"></div>

                <div class="subject-card-top">
                    <span class="quarter-chip"><?= htmlspecialchars($subj['quarter']) ?></span>
                    <span class="subject-enrolled">
                        <i class="fas fa-users"></i>
                        <?= (int) $subj['enrolled_count'] ?>/<?= (int) $subj['capacity'] ?>
                    </span>
                </div>

                <h3 class="subject-name"><?= htmlspecialchars($subj['subject_name']) ?></h3>

                <p class="subject-schedule">
                    <i class="fas fa-clock"></i>
                    <?= htmlspecialchars($subj['schedule_days'] ?? 'TBA') ?>
                    <?php if ($subj['start_time']): ?>
                        · <?= date('g:i A', strtotime($subj['start_time'])) ?>–<?= date('g:i A', strtotime($subj['end_time'])) ?>
                    <?php endif; ?>
                </p>

                <!-- Enrollment Capacity Bar -->
                <div class="subject-progress">
                    <div class="subject-progress__header">
                        <span>Enrollment</span>
                        <span class="subject-progress__value"><?= $enrolledPct ?>%</span>
                    </div>
                    <div class="subject-progress__bar">
                        <div class="subject-progress__fill" style="width: <?= $enrolledPct ?>%"></div>
                    </div>
                </div>

                <div class="subject-card__footer">
                    <span class="subject-card__cta">
                        Open class <i class="fas fa-arrow-right"></i>
                    </span>
                    <div class="subject-card__quick-actions">
                        <span class="qa-btn" title="Attendance"><i class="fas fa-clipboard-list"></i></span>
                        <span class="qa-btn" title="Gradebook"><i class="fas fa-chart-line"></i></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </section>

       

    <?php endif; ?>

</main>

<script>
/* Client-side search & filter */
(function() {
    const searchInput = document.getElementById('subjectSearch');
    const cards = document.querySelectorAll('.subject-card');
    const chips = document.querySelectorAll('.chip');
    let activeFilter = 'all';

    function filterCards() {
        const term = (searchInput?.value || '').toLowerCase().trim();
        cards.forEach(card => {
            const name = card.dataset.name || '';
            const quarter = card.dataset.quarter || '';
            const schedule = card.dataset.schedule || '';
            const matchesSearch = !term || name.includes(term) || quarter.includes(term) || schedule.includes(term);
            const matchesChip = activeFilter === 'all' || quarter.includes(activeFilter.toLowerCase());
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