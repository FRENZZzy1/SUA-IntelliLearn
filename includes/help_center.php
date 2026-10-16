<?php
/**
 * Shared help center for the admin, teacher, and student headers.
 * Add the published tutorial URLs below when the videos are ready.
 */
$helpStudentVideoUrl = '';
$helpTeacherVideoUrl = '';
?>
<style>
    .sua-help-trigger { position: relative; }
    .sua-help-overlay {
        position: fixed; inset: 0; z-index: 2500;
        display: flex; align-items: center; justify-content: center;
        padding: 22px; background: rgba(9, 22, 16, .58);
        -webkit-backdrop-filter: blur(7px); backdrop-filter: blur(7px);
        opacity: 0; visibility: hidden; pointer-events: none;
        transition: opacity .22s ease, visibility .22s ease;
    }
    .sua-help-overlay.is-open { opacity: 1; visibility: visible; pointer-events: auto; }
    .sua-help-modal {
        width: min(680px, 100%); max-height: min(88vh, 780px); overflow-y: auto;
        background: #fff; color: #17251d; border: 1px solid rgba(255,255,255,.65);
        border-radius: 24px; box-shadow: 0 28px 90px rgba(0,0,0,.28);
        transform: translateY(14px) scale(.985);
        transition: transform .24s cubic-bezier(.2,.8,.2,1);
        scrollbar-width: thin; overscroll-behavior: contain;
    }
    .sua-help-overlay.is-open .sua-help-modal { transform: translateY(0) scale(1); }
    .sua-help-hero {
        position: relative; overflow: hidden; padding: 28px 30px 25px;
        color: #fff; background: linear-gradient(125deg, #123c2a 0%, #1d6847 58%, #34835c 100%);
    }
    .sua-help-hero:before, .sua-help-hero:after {
        content: ""; position: absolute; border-radius: 50%; pointer-events: none;
        border: 1px solid rgba(255,255,255,.13);
    }
    .sua-help-hero:before { width: 240px; height: 240px; right: -60px; top: -125px; }
    .sua-help-hero:after { width: 170px; height: 170px; right: 8px; top: -82px; }
    .sua-help-hero-top { display:flex; align-items:center; gap:10px; margin-bottom:17px; position:relative; z-index:1; }
    .sua-help-brand-mark {
        width: 38px; height: 38px; display:grid; place-items:center; flex-shrink:0;
        border-radius: 12px; background: rgba(255,255,255,.15);
        border: 1px solid rgba(255,255,255,.2); font-size: 1.05rem;
    }
    .sua-help-eyebrow { margin:0; font-size:.7rem; font-weight:800; letter-spacing:.14em; text-transform:uppercase; opacity:.78; }
    .sua-help-hero h2 { position:relative; z-index:1; margin:0 42px 9px 0; color:#fff; font-size:clamp(1.55rem, 4vw, 2rem); line-height:1.15; letter-spacing:-.035em; }
    .sua-help-hero p { position:relative; z-index:1; margin:0; max-width:540px; color:rgba(255,255,255,.83); font-size:.92rem; line-height:1.65; }
    .sua-help-close {
        position:absolute; z-index:2; top:20px; right:20px; width:36px; height:36px;
        display:grid; place-items:center; border-radius:12px; cursor:pointer;
        color:#fff; background:rgba(255,255,255,.13); border:1px solid rgba(255,255,255,.2);
        transition:background .18s ease, transform .18s ease;
    }
    .sua-help-close:hover { background:rgba(255,255,255,.24); transform:rotate(4deg); }
    .sua-help-content { padding:24px 30px 28px; }
    .sua-help-section-heading { display:flex; align-items:center; gap:9px; margin:0 0 13px; color:#26392e; font-size:.9rem; font-weight:800; }
    .sua-help-section-heading i { color:#24734d; }
    .sua-help-videos { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .sua-help-video {
        min-width:0; display:flex; align-items:flex-start; gap:12px; padding:16px;
        border:1px solid #e4ece6; border-radius:16px; background:linear-gradient(145deg,#fff,#f7faf8);
        text-decoration:none; color:inherit; transition:transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .sua-help-video:not(.is-disabled):hover { transform:translateY(-3px); border-color:#9fc8ad; box-shadow:0 10px 24px rgba(25,85,52,.09); }
    .sua-help-video-icon {
        width:42px; height:42px; display:grid; place-items:center; flex-shrink:0;
        border-radius:13px; color:#fff; background:linear-gradient(145deg,#1d6847,#319363); font-size:1rem;
    }
    .sua-help-video.teacher .sua-help-video-icon { background:linear-gradient(145deg,#305b8e,#4d83bc); }
    .sua-help-video-copy { min-width:0; flex:1; }
    .sua-help-video-title { margin:1px 0 5px; color:#21372a; font-size:.88rem; font-weight:800; }
    .sua-help-video-desc { margin:0; color:#68786e; font-size:.76rem; line-height:1.5; }
    .sua-help-video-meta { display:flex; align-items:center; gap:5px; margin-top:11px; color:#26764e; font-size:.72rem; font-weight:800; }
    .sua-help-video.teacher .sua-help-video-meta { color:#3c6e9f; }
    .sua-help-video.is-disabled { cursor:default; opacity:.8; }
    .sua-help-video.is-disabled .sua-help-video-meta { color:#8b978f; }
    .sua-help-creator {
        display:flex; align-items:center; gap:13px; margin-top:22px; padding:15px 16px;
        border-radius:15px; background:#f3f7f4; border:1px solid #e6eee8;
    }
    .sua-help-creator-icon { width:42px; height:42px; display:grid; place-items:center; flex-shrink:0; border-radius:50%; background:#deeee3; color:#216a45; font-size:1rem; }
    .sua-help-creator-label { margin:0 0 3px; color:#78877d; font-size:.68rem; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
    .sua-help-creator-name { margin:0; color:#23382a; font-size:.86rem; font-weight:800; }
    .sua-help-creator-sub { margin:3px 0 0; color:#6c7a70; font-size:.75rem; line-height:1.4; }
    .sua-help-footer { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:18px; color:#849087; font-size:.7rem; }
    .sua-help-footer span { display:flex; align-items:center; gap:6px; }
    .sua-help-footer i { color:#39805a; }
    @media (max-width:560px) {
        .sua-help-overlay { padding:10px; align-items:center; }
        .sua-help-modal { max-height:92vh; border-radius:19px; }
        .sua-help-hero { padding:23px 21px 21px; }
        .sua-help-close { top:14px; right:14px; }
        .sua-help-hero h2 { margin-right:32px; }
        .sua-help-content { padding:20px 18px 22px; }
        .sua-help-videos { grid-template-columns:1fr; }
        .sua-help-video { padding:13px; }
        .sua-help-footer { align-items:flex-start; flex-direction:column; gap:7px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .sua-help-overlay, .sua-help-modal, .sua-help-close, .sua-help-video { transition:none !important; }
    }
</style>

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
            <div class="sua-help-creator">
                <div class="sua-help-creator-icon"><i class="fas fa-code"></i></div>
                <div>
                    <p class="sua-help-creator-label">Created as a capstone project by</p>
                    <p class="sua-help-creator-name">SUA IntelliLearn Development Team</p>
                    <p class="sua-help-creator-sub">Project Leader: Frenz Allen Paller · Saint Uriel Academy of Taguig City</p>
                </div>
            </div>
            <div class="sua-help-footer">
                <span><i class="fas fa-shield-halved"></i> Designed to support organized learning</span>
                <span>Need help? Start with the guides above.</span>
            </div>
        </div>
    </section>
</div>

<script>
(function () {
    var overlay = document.getElementById('suaHelpOverlay');
    var modal = overlay && overlay.querySelector('.sua-help-modal');
    var trigger = document.getElementById('suaHelpTrigger');
    var closeButton = document.getElementById('suaHelpClose');
    if (!overlay || !modal || !trigger || !closeButton) return;

    function openHelp() {
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        window.setTimeout(function () { modal.focus(); }, 40);
    }
    function closeHelp() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        trigger.focus();
    }
    trigger.addEventListener('click', openHelp);
    closeButton.addEventListener('click', closeHelp);
    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) closeHelp();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && overlay.classList.contains('is-open')) closeHelp();
    });
    overlay.querySelectorAll('.sua-help-video.is-disabled').forEach(function (link) {
        link.addEventListener('click', function (event) { event.preventDefault(); });
    });
})();
</script>
