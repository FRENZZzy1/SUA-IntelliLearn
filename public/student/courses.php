<?php
include 'assets/api/courses_functions.php';

// Compute summary stats
$totalCourses     = count($myCourses);
$totalMaterials   = array_sum(array_column($myCourses, 'materials_count'));
$totalAssignments = array_sum(array_column($myCourses, 'assignments_count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/courses.css">
</head>
<body>

<?php include '../../includes/student_sidebar.php'; ?>

<main class="main-content" id="dashMain">

    <?php include '../../includes/student_header.php'; ?>

    <div class="dash-page-title">
        <h1 class="dash-title">My Courses</h1>
        <?php if ($schoolYearLabel): ?>
            <p class="dash-subtitle">School Year <?= htmlspecialchars($schoolYearLabel) ?></p>
        <?php endif; ?>
    </div>

    <?php if (empty($myCourses)): ?>
        <section class="panel">
            <div class="panel-empty panel-empty--enhanced">
                <div class="panel-empty__icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <h3>You're not enrolled in any classes yet</h3>
                <p>Once your enrollment is approved, your classes will show up here.</p>
                <span class="panel-empty__hint">Check the Enrollment page or contact your administrator.</span>
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
                    <span class="stat-card__value"><?= (int) $totalCourses ?></span>
                    <span class="stat-card__label"><?= $totalCourses === 1 ? 'Course' : 'Courses' ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--blue">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="stat-card__info">
                    <span class="stat-card__value"><?= (int) $totalMaterials ?></span>
                    <span class="stat-card__label">Materials</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--amber">
                    <i class="fas fa-marker"></i>
                </div>
                <div class="stat-card__info">
                    <span class="stat-card__value"><?= (int) $totalAssignments ?></span>
                    <span class="stat-card__label">Assignments</span>
                </div>
            </div>
        </section>

        <!-- Toolbar: Search -->
        <section class="section-toolbar">
            <div class="toolbar-search">
                <i class="fas fa-search"></i>
                <input type="text" id="courseSearch" placeholder="Search your courses by subject or teacher..." autocomplete="off">
            </div>
        </section>

        <!-- Course Cards Grid -->
        <section class="subject-grid" id="courseGrid">
            <?php foreach ($myCourses as $course):
                $colorSeed = md5($course['subject_name']);
                $hue = hexdec(substr($colorSeed, 0, 2)) % 360;
                $accent = "hsl({$hue}, 70%, 45%)";
                $accentLight = "hsl({$hue}, 70%, 95%)";
                $teacherName = trim($course['teacher_first'] . ' ' . $course['teacher_last']);
            ?>
            <a class="subject-card"
               href="course_view.php?subject_id=<?= (int) $course['subject_id'] ?>"
               data-name="<?= htmlspecialchars(strtolower($course['subject_name'])) ?>"
               data-teacher="<?= htmlspecialchars(strtolower($teacherName)) ?>"
               style="--card-accent: <?= $accent ?>; --card-accent-light: <?= $accentLight ?>;">

                <div class="subject-card__accent"></div>

                <div class="subject-card-top">
                    <span class="quarter-chip">
                        <?= (int) $course['term_count'] ?> term<?= $course['term_count'] == 1 ? '' : 's' ?>
                    </span>
                </div>

                <h3 class="subject-name"><?= htmlspecialchars($course['subject_name']) ?></h3>

                <p class="subject-schedule">
                    <i class="fas fa-chalkboard-user"></i>
                    <?= htmlspecialchars($teacherName) ?>
                </p>

                <p class="subject-schedule">
                    <i class="fas fa-clock"></i>
                    <?= htmlspecialchars($course['schedule_days'] ?? 'TBA') ?>
                    <?php if ($course['start_time']): ?>
                        · <?= date('g:i A', strtotime($course['start_time'])) ?>–<?= date('g:i A', strtotime($course['end_time'])) ?>
                    <?php endif; ?>
                </p>

                <div class="subject-card__footer">
                    <span class="subject-card__cta">
                        Open course <i class="fas fa-arrow-right"></i>
                    </span>
                    <div class="subject-card__quick-actions">
                        <span class="qa-btn" title="<?= (int) $course['materials_count'] ?> materials"><i class="fas fa-book-open"></i></span>
                        <span class="qa-btn" title="<?= (int) $course['assignments_count'] ?> assignments"><i class="fas fa-marker"></i></span>
                        <span class="qa-btn" title="<?= (int) $course['quizzes_count'] ?> quizzes"><i class="fas fa-file-circle-question"></i></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </section>

    <?php endif; ?>

</main>

<script>
(function () {
    const searchInput = document.getElementById('courseSearch');
    const cards = document.querySelectorAll('.subject-card');

    function filterCards() {
        const term = (searchInput?.value || '').toLowerCase().trim();
        cards.forEach(card => {
            const name = card.dataset.name || '';
            const teacher = card.dataset.teacher || '';
            card.style.display = (!term || name.includes(term) || teacher.includes(term)) ? '' : 'none';
        });
    }

    searchInput?.addEventListener('input', filterCards);
})();
</script>

</body>
</html>
