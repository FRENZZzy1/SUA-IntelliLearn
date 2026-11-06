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
