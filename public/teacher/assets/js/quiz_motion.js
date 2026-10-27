/**
 * quiz_motion.js — motion + interactivity layer for the Quiz Generator
 * Load BEFORE quiz_generator.js. Exposes:
 *   window.QGLoader  { start(opts), complete() -> Promise, stop() }
 *   window.QGMotion  { autoGrow(root) }
 * quiz_generator.js treats both as optional, so the page keeps working
 * (with the old behaviour) if this file ever fails to load.
 */
(function () {
    'use strict';

    var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

    // ---------------------------------------------------------
    // 1. Staggered entrance for the header + panels
    // ---------------------------------------------------------
    function initReveal() {
        if (reduceMotion) return;
        var nodes = document.querySelectorAll('.qg-header, .qg-panel:not(#qgReviewSection)');
        Array.prototype.forEach.call(nodes, function (el, i) {
            el.style.setProperty('--d', (i * 0.07).toFixed(2) + 's');
            el.classList.add('qg-reveal');
            el.addEventListener('animationend', function () { el.classList.remove('qg-reveal'); }, { once: true });
        });
    }

    // ---------------------------------------------------------
    // 2. Click ripple on buttons
    // ---------------------------------------------------------
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.qg-btn, .qg-add-question');
        if (!btn || btn.disabled || reduceMotion) return;
        var r = btn.getBoundingClientRect();
        var size = Math.max(r.width, r.height) * 2;
        // keyboard-triggered clicks report 0,0 -> ripple from the centre instead
        var x = e.detail === 0 ? r.width / 2 : e.clientX - r.left;
        var y = e.detail === 0 ? r.height / 2 : e.clientY - r.top;
        var s = document.createElement('span');
        s.className = 'qg-ripple';
        s.style.cssText = 'width:' + size + 'px;height:' + size + 'px;left:' + (x - size / 2) + 'px;top:' + (y - size / 2) + 'px';
        btn.appendChild(s);
        s.addEventListener('animationend', function () { s.remove(); });
    });

    // ---------------------------------------------------------
    // 3. Count-up on the Quiz Summary numbers
    // ---------------------------------------------------------
    function initCountUp() {
        if (reduceMotion) return;
        Array.prototype.forEach.call(document.querySelectorAll('.qg-stat__num'), function (el) {
            var end = parseInt(el.textContent, 10);
            if (isNaN(end) || end <= 0) return;
            el.textContent = '0';
            var dur = 900, delay = 350, t0 = null;
            function step(ts) {
                if (t0 === null) t0 = ts;
                var p = Math.min(1, (ts - t0) / dur);
                el.textContent = String(Math.round(end * (1 - Math.pow(1 - p, 3))));
                if (p < 1) requestAnimationFrame(step); else el.textContent = String(end);
            }
            setTimeout(function () { requestAnimationFrame(step); }, delay);
        });
    }

    // ---------------------------------------------------------
    // 4. Keep the "Question Type" pills in sync when changed by keyboard
    //    (quiz_generator.js only reacts to clicks on them)
    // ---------------------------------------------------------
    function initPillSync() {
        Array.prototype.forEach.call(document.querySelectorAll('.qg-pill input'), function (input) {
            input.addEventListener('change', function () {
                Array.prototype.forEach.call(document.querySelectorAll('.qg-pill'), function (p) {
                    p.classList.toggle('active', p.querySelector('input').checked);
                });
            });
        });
    }

    // ---------------------------------------------------------
    // 5. Auto-growing textareas
    // ---------------------------------------------------------
    function grow(t) {
        t.style.height = 'auto';
        t.style.height = (t.scrollHeight + 2) + 'px';
    }
    document.addEventListener('input', function (e) {
        if (e.target.matches && e.target.matches('.qg-qtext')) grow(e.target);
    });
    var QGMotion = {
        autoGrow: function (root) {
            Array.prototype.forEach.call((root || document).querySelectorAll('.qg-qtext'), grow);
        }
    };

    // ---------------------------------------------------------
    // 6. Loading screen
    // ---------------------------------------------------------
    var STEP_LABELS = {
        pdf: ['Opening your PDF modules', 'Reading & extracting key concepts', 'Drafting questions', 'Balancing difficulty', 'Polishing answer choices'],
        topic: ['Understanding your topic', 'Outlining the key concepts', 'Drafting questions', 'Balancing difficulty', 'Polishing answer choices']
    };
    var STEP_STARTS = [0, 16, 42, 68, 84]; // progress % at which each step becomes active
    var TIPS = [
        'You can edit every question, choice and answer before anything goes live.',
        'Use “Custom mix” to set exactly how many easy, average and difficult items you want.',
        'Add a time limit and a deadline once the draft is ready.',
        'Turn on “Shuffle questions” so each student sees a different order.',
        'Smaller PDF modules usually give more focused questions.',
        'Save as a draft first if you want to review the quiz again later.'
    ];
    var CONFETTI_COLORS = ['#f4a300', '#2d6a4f', '#2d9d63', '#1b4332', '#f7c65a'];

    var QGLoader = (function () {
        var root = document.getElementById('qgLoading');
        if (!root) return null;

        var card = root.querySelector('.qg-ldr');
        var titleEl = root.querySelector('.qg-ldr__title');
        var subEl = root.querySelector('.qg-ldr__sub');
        var stepEls = Array.prototype.slice.call(root.querySelectorAll('.qg-ldr__step'));
        var fillEl = root.querySelector('.qg-ldr__fill');
        var barEl = root.querySelector('.qg-ldr__bar');
        var pctEl = root.querySelector('.qg-ldr__pct');
        var timeEl = root.querySelector('.qg-ldr__time');
        var tipEl = root.querySelector('.qg-ldr__tip');
        var tipText = tipEl ? tipEl.querySelector('span') : null;
        var cancelBtn = root.querySelector('.qg-ldr__cancel');
        var confettiEl = root.querySelector('.qg-ldr__confetti');

        var tickTimer = null, tipTimer = null, doneTimer = null, doneResolve = null;
        var t0 = 0, currentStep = -1, tipIndex = 0, running = false, cancelCb = null, prevFocus = null, prevOverflow = '';

        function fmt(sec) {
            var s = Math.floor(sec);
            return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
        }

        function setProgress(p) {
            fillEl.style.width = p.toFixed(1) + '%';
            pctEl.textContent = Math.round(p) + '%';
            barEl.setAttribute('aria-valuenow', String(Math.round(p)));
        }

        function setStep(i) {
            currentStep = i;
            stepEls.forEach(function (el, idx) {
                el.classList.toggle('is-done', idx < i);
                el.classList.toggle('is-active', idx === i);
            });
        }

        function tick() {
            var t = (performance.now() - t0) / 1000;
            var p = 94 * (1 - Math.exp(-t / 12)); // eases toward ~94% and waits for the server
            setProgress(p);
            var idx = 0;
            STEP_STARTS.forEach(function (start, k) { if (p >= start) idx = k; });
            if (idx !== currentStep) setStep(idx);
            timeEl.textContent = fmt(t);
            if (t > 28 && subEl.dataset.slow !== '1') {
                subEl.dataset.slow = '1';
                subEl.textContent = 'Still working — longer modules take a little more time.';
            }
        }

        function rotateTip() {
            if (!tipEl || !tipText) return;
            tipEl.classList.add('is-swapping');
            setTimeout(function () {
                tipIndex = (tipIndex + 1) % TIPS.length;
                tipText.textContent = TIPS[tipIndex];
                tipEl.classList.remove('is-swapping');
            }, 300);
        }

        function burst() {
            if (!confettiEl || reduceMotion) return;
            confettiEl.innerHTML = '';
            for (var i = 0; i < 18; i++) {
                var a = (Math.PI * 2 * i) / 18 + Math.random() * 0.4;
                var d = 70 + Math.random() * 60;
                var bit = document.createElement('i');
                bit.style.setProperty('--dx', Math.cos(a) * d + 'px');
                bit.style.setProperty('--dy', Math.sin(a) * d + 'px');
                bit.style.setProperty('--r', (Math.random() * 540 - 270) + 'deg');
                bit.style.setProperty('--c', CONFETTI_COLORS[i % CONFETTI_COLORS.length]);
                confettiEl.appendChild(bit);
            }
        }

        function onKey(e) {
            if (!running) return;
            if (e.key === 'Escape') { e.preventDefault(); cancel(); }
            else if (e.key === 'Tab') { e.preventDefault(); if (cancelBtn) cancelBtn.focus(); } // single focusable control
        }

        function cancel() {
            if (card.classList.contains('is-done')) return; // the request already succeeded — nothing to cancel
            var cb = cancelCb;
            stop();
            if (cb) cb();
        }

        function reset() {
            clearInterval(tickTimer);
            clearInterval(tipTimer);
            clearTimeout(doneTimer);
            card.classList.remove('is-done');
            if (confettiEl) confettiEl.innerHTML = '';
            currentStep = -1;
            subEl.dataset.slow = '0';
        }

        function start(opts) {
            opts = opts || {};
            if (running) reset();
            running = true;
            cancelCb = typeof opts.onCancel === 'function' ? opts.onCancel : null;

            var labels = STEP_LABELS[opts.sourceType === 'topic' ? 'topic' : 'pdf'];
            stepEls.forEach(function (el, i) { el.querySelector('.qg-ldr__label').textContent = labels[i]; });

            titleEl.textContent = 'Crafting your quiz…';
            subEl.textContent = opts.summary || 'Hang tight — this usually takes under 30 seconds.';
            timeEl.textContent = '0:00';
            tipIndex = Math.floor(Math.random() * TIPS.length);
            if (tipText) tipText.textContent = TIPS[tipIndex];
            setProgress(0);
            setStep(0);

            prevFocus = document.activeElement;
            prevOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            root.classList.add('active');
            root.setAttribute('aria-hidden', 'false');
            if (cancelBtn) cancelBtn.focus();
            document.addEventListener('keydown', onKey, true);

            t0 = performance.now();
            tickTimer = setInterval(tick, 150);
            tipTimer = setInterval(rotateTip, 5000);
        }

        function stop() {
            reset();
            running = false;
            if (doneResolve) { var r = doneResolve; doneResolve = null; r(); } // never leave complete() hanging
            cancelCb = null;
            root.classList.remove('active');
            root.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = prevOverflow;
            document.removeEventListener('keydown', onKey, true);
            if (prevFocus && typeof prevFocus.focus === 'function') {
                try { prevFocus.focus({ preventScroll: true }); } catch (e) { /* ignore */ }
            }
            prevFocus = null;
        }

        // Success: fill the bar, burst, hold briefly, then close. Resolves when the overlay is gone.
        function complete() {
            if (!running) return Promise.resolve();
            clearInterval(tickTimer);
            clearInterval(tipTimer);
            setProgress(100);
            stepEls.forEach(function (el) { el.classList.remove('is-active'); el.classList.add('is-done'); });
            titleEl.textContent = 'Your quiz draft is ready!';
            subEl.textContent = 'Opening the review editor…';
            card.classList.add('is-done');
            burst();
            return new Promise(function (resolve) {
                doneResolve = resolve;
                doneTimer = setTimeout(stop, reduceMotion ? 250 : 1100);
            });
        }

        if (cancelBtn) cancelBtn.addEventListener('click', cancel);

        return { start: start, complete: complete, stop: stop };
    })();

    window.QGLoader = QGLoader;
    window.QGMotion = QGMotion;

    function init() {
        initReveal();
        initCountUp();
        initPillSync();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
