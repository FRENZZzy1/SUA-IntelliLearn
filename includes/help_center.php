<?php
/**
 * Shared help center for the admin, teacher, and student headers.
 * Add the published tutorial URLs below when the videos are ready.
 */
$helpStudentVideoUrl = 'https://youtu.be/VlcoKz39ZKA?si=ymzNeNbUtWdctsVD';
$helpTeacherVideoUrl = '';
?>
<link rel="stylesheet" href="/SUA-INTELLILEARN/includes/css/help_center.css">

<div class="sua-help-overlay" id="suaHelpOverlay" aria-hidden="true">
    <section class="sua-help-modal" role="dialog" aria-modal="true" aria-labelledby="suaHelpTitle" aria-describedby="suaHelpDescription" tabindex="-1">
        <div class="sua-help-hero">
            <button type="button" class="sua-help-close" id="suaHelpClose" aria-label="Close help center"><i class="fas fa-xmark"></i></button>
            <div class="sua-help-hero-top">
                <div class="sua-help-brand-mark"><i class="fas fa-graduation-cap"></i></div>
                <p class="sua-help-eyebrow">SUA IntelliLearn · Help Center</p>
            </div>
            <h2 id="suaHelpTitle">Learning, all in one place.</h2>
            <p id="suaHelpDescription">SUA IntelliLearn is an electronic learning management system for Saint Uriel Academy of Taguig City. It brings classes, learning materials, assignments, quizzes, attendance, and academic progress together in one convenient platform, with intelligent assistance for teachers.</p>
        </div>
        <div class="sua-help-content">
            <h3 class="sua-help-section-heading"><i class="fas fa-circle-play"></i> Quick video guides</h3>
            <div class="sua-help-videos">
                <a class="sua-help-video <?php echo $helpStudentVideoUrl ? '' : 'is-disabled'; ?>" <?php if ($helpStudentVideoUrl): ?>href="<?php echo htmlspecialchars($helpStudentVideoUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"<?php else: ?>href="#" aria-disabled="true" tabindex="-1"<?php endif; ?>>
                    <div class="sua-help-video-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="sua-help-video-copy">
                        <p class="sua-help-video-title">Student guide</p>
                        <p class="sua-help-video-desc">Explore your dashboard, learning materials, assignments, quizzes, grades, and attendance.</p>
                        <div class="sua-help-video-meta"><?php if ($helpStudentVideoUrl): ?>Watch tutorial <i class="fas fa-arrow-up-right-from-square"></i><?php else: ?>Video link coming soon<?php endif; ?></div>
                    </div>
                </a>
                <a class="sua-help-video teacher <?php echo $helpTeacherVideoUrl ? '' : 'is-disabled'; ?>" <?php if ($helpTeacherVideoUrl): ?>href="<?php echo htmlspecialchars($helpTeacherVideoUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"<?php else: ?>href="#" aria-disabled="true" tabindex="-1"<?php endif; ?>>
                    <div class="sua-help-video-icon"><i class="fas fa-chalkboard-user"></i></div>
                    <div class="sua-help-video-copy">
                        <p class="sua-help-video-title">Teacher guide</p>
                        <p class="sua-help-video-desc">Learn to manage classes, share resources, create quizzes, and monitor student progress.</p>
                        <div class="sua-help-video-meta"><?php if ($helpTeacherVideoUrl): ?>Watch tutorial <i class="fas fa-arrow-up-right-from-square"></i><?php else: ?>Video link coming soon<?php endif; ?></div>
                    </div>
                </a>
            </div>
           
            <div class="sua-help-footer">
                <span><i class="fas fa-shield-halved"></i> Designed to support organized learning</span>
                <span>Need help? Start with the guides above.</span>
            </div>
        </div>
    </section>
</div>

<script src="/SUA-INTELLILEARN/includes/js/help_center.js"></script>
