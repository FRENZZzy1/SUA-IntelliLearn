<?php
include 'assets/api/courses_functions.php';
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
        <h1 class="dash-title">Courses</h1>
        <?php if ($schoolYearLabel): ?>
            <p class="dash-subtitle">School Year <?= htmlspecialchars($schoolYearLabel) ?></p>
        <?php endif; ?>
    </div>

    <?php if (empty($mySections)): ?>
        <section class="panel">
            <div class="panel-empty">
                <i class="fas fa-chalkboard"></i>
                <p>No active classes assigned yet.</p>
                <span>Once the admin assigns you a class, its section will show up here.</span>
            </div>
        </section>
    <?php else: ?>
        <section class="section-grid">
            <?php foreach ($mySections as $sec): ?>
            <a class="section-card" href="course_section.php?section_id=<?= (int) $sec['section_id'] ?>">
                <div class="section-card-top">
                    <span class="section-grade">Grade <?= (int) $sec['grade_level'] ?></span>
                    <?php if (!empty($sec['strand'])): ?>
                        <span class="section-strand"><?= htmlspecialchars($sec['strand']) ?></span>
                    <?php endif; ?>
                </div>

                <h3 class="section-name"><?= htmlspecialchars($sec['section_name']) ?></h3>

                <div class="section-card-stats">
                    <span><i class="fas fa-book"></i> <?= (int) $sec['subject_count'] ?> <?= $sec['subject_count'] == 1 ? 'subject' : 'subjects' ?></span>
                    <span><i class="fas fa-users"></i> <?= (int) $sec['student_count'] ?> <?= $sec['student_count'] == 1 ? 'student' : 'students' ?></span>
                </div>

                <span class="section-card-cta">View subjects <i class="fas fa-arrow-right"></i></span>
            </a>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

</main>

</body>
</html>