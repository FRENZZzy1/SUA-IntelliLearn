<?php
include 'assets/api/course_section_functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($section['section_name']) ?> · Courses · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/courses.css">
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
            <span class="section-title-grade">Grade <?= (int) $section['grade_level'] ?><?= !empty($section['strand']) ? ' · ' . htmlspecialchars($section['strand']) : '' ?></span>
        </h1>
        <?php if ($schoolYearLabel): ?>
            <p class="dash-subtitle">School Year <?= htmlspecialchars($schoolYearLabel) ?></p>
        <?php endif; ?>
    </div>

    <?php if (empty($sectionSubjects)): ?>
        <section class="panel">
            <div class="panel-empty">
                <i class="fas fa-book"></i>
                <p>No subjects here yet.</p>
                <span>You don't have any active classes in this section right now.</span>
            </div>
        </section>
    <?php else: ?>
        <section class="subject-grid">
            <?php foreach ($sectionSubjects as $subj): ?>
            <article class="subject-card">
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
            </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

</main>

</body>
</html>