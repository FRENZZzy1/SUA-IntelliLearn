/* =====================================================================
   At-Risk Students — enhancement layer (SUA IntelliLearn)
   Adds Uriel's insights header + motion on top of the existing page.
   It only READS the page (the Analyze buttons already carry each
   student's numbers) and never changes the original layout or logic.
   Remove the <script>/<link> tags for this file to switch it all off.
   ===================================================================== */
(function () {
    'use strict';
    var app = document.getElementById('arApp');
    var host = document.getElementById('arInsight');
    if (!app || !host) return;

    var MASCOT = app.dataset.mascot || '';
    var NAME = app.dataset.teacher || '';
    var MIN = parseInt(app.dataset.min, 10) || 3;
    var REDUCE = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ------------------------------------------------------------------
    // Text (follows the page's EN/TL toggle)
    // ------------------------------------------------------------------
    var TXT = {
        en: {
            title: 'Uriel\u2019s Insights', again: 'Analyze again', scope: 'All your classes \u00b7 current term',
            hello_concern: 'Hi {name}! I looked through your class data and found something that may need your attention.',
            hello_calm: 'Hi {name}! I looked through your class data, and it\u2019s good news.',
            scan_hello: 'Hi {name}, give me a moment to look at your classes.',
            scan: ['Reading attendance records\u2026', 'Checking assignments and missing work\u2026', 'Reviewing quiz results\u2026', 'Putting it all together\u2026'],
            ov_h: 'Class overview', obs_h: 'Key observation', act_h: 'Recommended action',
            ov_many: '{n} students may need additional academic support.', ov_one: '1 student may need additional academic support.', ov_high: '{h} of them are high priority.',
            obs_most_two: 'Most students flagged have {a} and {b}.', obs_many_two: 'Many students flagged have {a} and {b}.',
            obs_most_one: 'Most students flagged have {a}.', obs_many_one: 'Many students flagged have {a}.',
            obs_single_two: 'This student shows {a} and {b}.', obs_single_one: 'This student shows {a}.',
            obs_none: 'No single factor stands out. Each student\u2019s reasons differ, so open their rows to see why.',
            fac_quiz: 'low quiz scores', fac_asg: 'missing or low assignments', fac_att: 'poor attendance',
            act_quiz: 'Consider reviewing the most frequently missed topics and giving extra practice activities.',
            act_asg: 'Consider opening a short catch-up window for missing assignments and checking in with students who haven\u2019t submitted.',
            act_att: 'Consider reaching out to students with frequent absences and agreeing on a plan to catch up on missed lessons.',
            act_generic: 'Open the flagged students one by one to see what each of them needs.',
            drv_h: 'What\u2019s driving risk', drv_att: 'Attendance', drv_asg: 'Assignments', drv_quiz: 'Quizzes',
            show_high: 'Show high-risk students', show_medium: 'Show medium-risk students',
            calm_t: 'No students need immediate intervention', calm_s: 'Everyone with enough data is on track. I\u2019ll keep watching as new attendance, assignments and quizzes come in.',
            na_t: 'I need a bit more class data first', na_s: 'Risk is only calculated once at least {min} attendance records, assignments or quizzes are logged. Keep logging and I\u2019ll analyze again.',
            empty_t: 'No students to analyze yet', empty_s: 'Once students are enrolled in your classes and you log attendance, assignments and quizzes, I\u2019ll show who may need support.',
            thinking: 'Uriel is looking at this student\u2019s data\u2026'
        },
        tl: {
            title: 'Mga Obserbasyon ni Uriel', again: 'Suriin muli', scope: 'Lahat ng klase mo \u00b7 kasalukuyang termino',
            hello_concern: 'Kumusta, {name}! Tiningnan ko ang datos ng iyong mga klase at may nakita akong maaaring kailangan ng iyong pansin.',
            hello_calm: 'Kumusta, {name}! Tiningnan ko ang datos ng iyong mga klase, at magandang balita ito.',
            scan_hello: 'Kumusta, {name}! Sandali lang, tinitingnan ko ang iyong mga klase.',
            scan: ['Binabasa ang attendance\u2026', 'Sinusuri ang mga assignment at kulang na pasahin\u2026', 'Tinitingnan ang resulta ng mga quiz\u2026', 'Pinagsasama ang lahat\u2026'],
            ov_h: 'Buod ng klase', obs_h: 'Mahalagang obserbasyon', act_h: 'Inirerekomendang hakbang',
            ov_many: '{n} estudyante ang maaaring mangailangan ng dagdag na suporta sa pag-aaral.', ov_one: '1 estudyante ang maaaring mangailangan ng dagdag na suporta sa pag-aaral.', ov_high: '{h} sa kanila ay mataas ang priyoridad.',
            obs_most_two: 'Karamihan sa mga estudyanteng nabanggit ay may {a} at {b}.', obs_many_two: 'Marami sa mga estudyanteng nabanggit ay may {a} at {b}.',
            obs_most_one: 'Karamihan sa mga estudyanteng nabanggit ay may {a}.', obs_many_one: 'Marami sa mga estudyanteng nabanggit ay may {a}.',
            obs_single_two: 'Ang estudyanteng ito ay may {a} at {b}.', obs_single_one: 'Ang estudyanteng ito ay may {a}.',
            obs_none: 'Walang iisang dahilang namumukod-tangi. Magkakaiba ang dahilan ng bawat estudyante, kaya buksan ang kanilang row.',
            fac_quiz: 'mababang quiz scores', fac_asg: 'kulang o mababang assignment', fac_att: 'madalas na pagliban',
            act_quiz: 'Pag-isipang balikan ang mga paksang madalas na mali at magbigay ng dagdag na pagsasanay.',
            act_asg: 'Pag-isipang magbukas ng maikling panahon para makahabol sa mga kulang na assignment at kumustahin ang mga hindi pa nakapagpasa.',
            act_att: 'Pag-isipang kausapin ang mga madalas lumiban at pagkasunduan ang plano para makahabol sa mga namiss na aralin.',
            act_generic: 'Buksan isa-isa ang mga estudyanteng nabanggit para makita ang kailangan ng bawat isa.',
            drv_h: 'Ano ang nagpapataas ng panganib', drv_att: 'Attendance', drv_asg: 'Mga Assignment', drv_quiz: 'Mga Quiz',
            show_high: 'Ipakita ang mga estudyanteng mataas ang panganib', show_medium: 'Ipakita ang mga estudyanteng katamtaman ang panganib',
            calm_t: 'Walang estudyanteng nangangailangan ng agarang aksyon', calm_s: 'Maayos ang lahat ng may sapat na datos. Patuloy akong magbabantay habang may bagong attendance, assignment, at quiz.',
            na_t: 'Kailangan ko pa ng kaunting datos', na_s: 'Kakalkulahin lang ang panganib kapag may naka-log na kahit {min} na attendance, assignment, o quiz. Ituloy ang pag-log at susuriin ko ulit.',
            empty_t: 'Wala pang estudyanteng susuriin', empty_s: 'Kapag may naka-enroll na estudyante sa iyong mga klase at nakapag-log ka na ng attendance, assignment, at quiz, ipapakita ko kung sino ang nangangailangan ng suporta.',
            thinking: 'Tinitingnan ni Uriel ang datos ng estudyanteng ito\u2026'
        }
    };
    function lang() { return document.documentElement.getAttribute('lang') === 'tl' ? 'tl' : 'en'; }
    function t(k, v) {
        var s = (TXT[lang()][k] !== undefined) ? TXT[lang()][k] : TXT.en[k];
        if (v) Object.keys(v).forEach(function (x) { s = s.split('{' + x + '}').join(v[x]); });
        return s;
    }
    function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    // ------------------------------------------------------------------
    // Read the page's data (primary-term rows only, like the stat cards)
    // ------------------------------------------------------------------
    var RANK = { high: 3, medium: 2, low: 1, na: 0 };
    function readStudents() {
        var rows = [];
        document.querySelectorAll('.ai-btn').forEach(function (b) {
            if (b.closest('.ar-other-terms')) return;
            var d = b.dataset, num = function (x) { return x === '' || x == null ? null : parseFloat(x); };
            rows.push({
                id: d.student, risk: d.riskKey || 'na',
                attOk: d.attEnough === '1', att: num(d.attPct),
                asgOk: d.asgEnough === '1', asg: num(d.asgPct), asgMissing: parseInt(d.asgMissing, 10) || 0,
                quizOk: d.quizEnough === '1', quiz: num(d.quizPct), quizMissing: parseInt(d.quizMissing, 10) || 0
            });
        });
        return rows;
    }
    function factorsOf(e) {
        var f = [];
        if (e.attOk && e.att != null && e.att < 75) f.push('att');
        if (e.asgOk && (e.asgMissing > 0 || (e.asg != null && e.asg < 75))) f.push('asg');
        if (e.quizOk && ((e.quiz != null && e.quiz < 75) || e.quizMissing > 0)) f.push('quiz');
        return f;
    }
    function analyze(rows) {
        var worst = {}, flagged = {};
        rows.forEach(function (e) {
            if (worst[e.id] === undefined || RANK[e.risk] > worst[e.id]) worst[e.id] = RANK[e.risk];
            if (e.risk === 'high' || e.risk === 'medium') {
                flagged[e.id] = flagged[e.id] || {};
                factorsOf(e).forEach(function (k) { flagged[e.id][k] = 1; });
            }
        });
        var c = { high: 0, medium: 0, low: 0, na: 0 };
        Object.keys(worst).forEach(function (id) { c[['na', 'low', 'medium', 'high'][worst[id]]]++; });
        var fc = { att: 0, asg: 0, quiz: 0 }, n = 0;
        Object.keys(flagged).forEach(function (id) { n++; Object.keys(flagged[id]).forEach(function (k) { fc[k]++; }); });
        var ranked = Object.keys(fc).filter(function (k) { return fc[k] > 0; }).sort(function (a, b) { return fc[b] - fc[a]; });
        return { counts: c, total: c.high + c.medium + c.low + c.na, fc: fc, flagged: n, ranked: ranked };
    }

    // ------------------------------------------------------------------
    // Uriel mascot (single PNG, animated with CSS + state badges)
    // ------------------------------------------------------------------
    var BADGE = { scanning: 'fa-magnifying-glass', concern: 'fa-exclamation', calm: 'fa-heart', insufficient: 'fa-question', empty: 'fa-book-open', thinking: 'fa-ellipsis' };
    var ICON = { att: 'fa-calendar-check', asg: 'fa-list-check', quiz: 'fa-pen-to-square' };
    function uriel(state, size) {
        return '<div class="uriel ' + (size || '') + '" data-state="' + state + '" aria-hidden="true"><span class="uriel-ring"></span>'
            + '<span class="uriel-photo"><img src="' + esc(MASCOT) + '" alt="" draggable="false"></span>'
            + '<span class="uriel-badge"><i class="fas ' + BADGE[state] + '"></i></span>'
            + '<span class="uriel-spark s1"></span><span class="uriel-spark s2"></span><span class="uriel-spark s3"></span></div>';
    }

    // ------------------------------------------------------------------
    // Counters on the original stat cards
    // ------------------------------------------------------------------
    var summary = document.querySelector('.ar-summary');
    function countUp(node, to, dur) {
        node._cid = (node._cid || 0) + 1; var id = node._cid;
        if (REDUCE) { node.textContent = to; return; }
        var t0 = performance.now();
        (function step(now) {
            if (node._cid !== id) return;
            var p = Math.min(1, (now - t0) / dur);
            node.textContent = Math.round(to * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(step);
        })(t0);
    }
    function runCounters() {
        if (!summary) return;
        summary.classList.remove('arx-loading');
        summary.querySelectorAll('.ar-stat .n').forEach(function (n) {
            var to = n._to != null ? n._to : (n._to = parseInt(n.textContent, 10) || 0);
            countUp(n, to, 800);
        });
    }

    // ------------------------------------------------------------------
    // Banner rendering
    // ------------------------------------------------------------------
    var timers = [], data = null;
    function clearTimers() { timers.forEach(clearTimeout); timers = []; }

    function stateOf(a) {
        if (!a.total) return 'empty';
        if (a.counts.high + a.counts.medium + a.counts.low === 0) return 'insufficient';
        if (a.counts.high + a.counts.medium === 0) return 'calm';
        return 'concern';
    }
    function bodyFor(state, a) {
        var hello = esc(t('hello_concern', { name: NAME }));
        if (state === 'concern') {
            var n = a.counts.high + a.counts.medium, top = a.ranked.slice(0, 2), obs;
            if (!top.length) obs = t('obs_none');
            else {
                var most = a.fc[top[0]] / Math.max(1, a.flagged) >= 0.5;
                if (a.flagged === 1) obs = top.length > 1 ? t('obs_single_two', { a: t('fac_' + top[0]), b: t('fac_' + top[1]) }) : t('obs_single_one', { a: t('fac_' + top[0]) });
                else if (top.length > 1) obs = t(most ? 'obs_most_two' : 'obs_many_two', { a: t('fac_' + top[0]), b: t('fac_' + top[1]) });
                else obs = t(most ? 'obs_most_one' : 'obs_many_one', { a: t('fac_' + top[0]) });
            }
            var act = top.length ? t('act_' + top[0]) : t('act_generic');
            var overview = (n === 1 ? t('ov_one') : t('ov_many', { n: n })) + (a.counts.high > 0 && n > 1 ? ' ' + t('ov_high', { h: a.counts.high }) : '');
            var maxC = Math.max(1, a.flagged);
            var drivers = ['att', 'asg', 'quiz'].filter(function (k) { return a.fc[k] > 0; }).map(function (k, i) {
                return '<div class="drv arx-seq" style="--d:' + (i + 4) + '"><span class="drv-l"><i class="fas ' + ICON[k] + '"></i>' + esc(t('drv_' + k)) + '</span>'
                    + '<span class="drv-bar"><span style="--w:' + (a.fc[k] / maxC * 100) + '%"></span></span><b>' + a.fc[k] + '</b></div>';
            }).join('');
            var target = a.counts.high > 0 ? 'high' : 'medium';
            return '<p class="ins-hello arx-seq" style="--d:0">' + hello + '</p>'
                + '<div class="ins-grid"><div class="ins-rows">'
                + '<div class="ins-row overview arx-seq" style="--d:1"><span class="ins-ic"><i class="fas fa-users-viewfinder"></i></span><div><span class="ins-lbl">' + esc(t('ov_h')) + '</span><p class="ins-big">' + esc(overview) + '</p></div></div>'
                + '<div class="ins-row arx-seq" style="--d:2"><span class="ins-ic"><i class="fas fa-magnifying-glass-chart"></i></span><div><span class="ins-lbl">' + esc(t('obs_h')) + '</span><p>' + esc(obs) + '</p></div></div>'
                + '<div class="ins-row action arx-seq" style="--d:3"><span class="ins-ic"><i class="fas fa-lightbulb"></i></span><div><span class="ins-lbl">' + esc(t('act_h')) + '</span><p>' + esc(act) + '</p>'
                + '<button type="button" class="arx-btn" data-act="show" data-target="' + target + '"><i class="fas fa-arrow-down"></i> ' + esc(t('show_' + target)) + '</button></div></div>'
                + '</div>' + (drivers ? '<div class="ins-drivers"><h4>' + esc(t('drv_h')) + '</h4>' + drivers + '</div>' : '') + '</div>';
        }
        var m = { calm: ['calm_t', 'calm_s'], insufficient: ['na_t', 'na_s'], empty: ['empty_t', 'empty_s'] }[state];
        return (state === 'calm' ? '<p class="ins-hello arx-seq" style="--d:0">' + esc(t('hello_calm', { name: NAME })) + '</p>' : '')
            + '<h3 class="ins-state-title arx-seq" style="--d:1">' + esc(t(m[0])) + '</h3><p class="ins-state-sub arx-seq" style="--d:2">' + esc(t(m[1], { min: MIN })) + '</p>';
    }
    function shell(mascotHtml, inner, withAgain) {
        host.innerHTML = '<div class="ins-inner"><div class="ins-mascot">' + mascotHtml + '</div><div class="ins-main"><div class="ins-head">'
            + '<span class="ins-title"><i class="fas fa-wand-magic-sparkles"></i> ' + esc(t('title')) + '</span><span class="ins-scope">' + esc(t('scope')) + '</span>'
            + (withAgain ? '<button type="button" class="ins-again" data-act="again"><i class="fas fa-rotate"></i> <span>' + esc(t('again')) + '</span></button>' : '')
            + '</div><div class="ins-body">' + inner + '</div></div></div>';
    }
    function renderDone(celebrate) {
        var st = stateOf(data), ms = st === 'concern' ? 'concern' : st;
        host.className = 'ar-insight arx-st-' + st;
        shell(uriel(ms, 'lg'), bodyFor(st, data), data.total > 0);
        var u = host.querySelector('.uriel');
        if (celebrate && !REDUCE && u) { u.classList.add('wave'); timers.push(setTimeout(function () { u.classList.remove('wave'); }, 1400)); }
    }
    function renderScan() {
        host.className = 'ar-insight arx-st-scanning';
        var steps = t('scan');
        shell(uriel('scanning', 'lg'),
            '<p class="ins-hello">' + esc(t('scan_hello', { name: NAME })) + '</p><div class="scan-line"><span class="scan-text" id="arxScanText">' + esc(steps[0]) + '</span></div>'
            + '<div class="scan-bar"><span id="arxScanFill"></span></div><div class="scan-steps">' + steps.map(function (s, i) { return '<i class="' + (i === 0 ? 'on' : '') + '"></i>'; }).join('') + '</div>', false);
        var txt = document.getElementById('arxScanText'), fill = document.getElementById('arxScanFill'), dots = host.querySelectorAll('.scan-steps i'), total = 1700;
        requestAnimationFrame(function () { fill.style.transition = 'width ' + total + 'ms cubic-bezier(.3,.1,.3,1)'; fill.style.width = '100%'; });
        steps.forEach(function (s, i) {
            timers.push(setTimeout(function () {
                txt.classList.remove('swap'); void txt.offsetWidth; txt.classList.add('swap'); txt.textContent = s;
                dots.forEach(function (d, j) { d.classList.toggle('on', j <= i); });
            }, i * (total / 4)));
        });
    }
    function run() {
        clearTimers();
        data = analyze(readStudents());
        if (REDUCE || !data.total) { renderDone(true); runCounters(); return; }
        if (summary) summary.classList.add('arx-loading');
        renderScan();
        timers.push(setTimeout(function () { renderDone(true); runCounters(); }, 1800));
    }

    // ------------------------------------------------------------------
    // Page-level effects (purely visual)
    // ------------------------------------------------------------------
    function stagger() {
        document.querySelectorAll('.ar-summary .ar-stat').forEach(function (el, i) { el.style.setProperty('--i', i); });
        document.querySelectorAll('#arClasses > .ar-grade-group').forEach(function (el, i) { el.style.setProperty('--i', Math.min(i, 8)); });
        document.querySelectorAll('.ar-table tbody').forEach(function (tb) {
            tb.querySelectorAll('tr').forEach(function (tr, i) { tr.style.setProperty('--r', Math.min(i, 10)); });
        });
    }

    // Banner buttons: re-run the scan, or jump to the existing risk filter.
    host.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-act]'); if (!b) return;
        if (b.dataset.act === 'again') run();
        if (b.dataset.act === 'show') {
            var f = document.querySelector('.ar-filter-btn[data-filter="' + b.dataset.target + '"]');
            if (f) f.click();
            var bar = document.querySelector('.ar-toolbar');
            if (bar) bar.scrollIntoView({ behavior: REDUCE ? 'auto' : 'smooth', block: 'start' });
        }
    });
    // Rewrite banner text when the page's EN/TL toggle is used (the original handler runs first).
    document.querySelectorAll('.ar-lang-btn').forEach(function (b) {
        b.addEventListener('click', function () { setTimeout(function () { if (data) { clearTimers(); renderDone(false); } }, 0); });
    });

    // ------------------------------------------------------------------
    // AI modal: Uriel "thinking" while loading + sequential reveal of results
    // ------------------------------------------------------------------
    var modalBody = document.getElementById('aiModalBody');
    if (modalBody && window.MutationObserver) {
        var obs = new MutationObserver(function () {
            obs.disconnect();
            var loading = modalBody.querySelector('.ai-loading');
            if (loading && !loading.querySelector('.uriel')) loading.insertAdjacentHTML('afterbegin', uriel('thinking', 'md'));
            var i = 0;
            modalBody.querySelectorAll('.ai-section:not(.ar-snapshot), .ai-meta, .ai-error').forEach(function (el) {
                el.classList.add('arx-seq'); el.style.setProperty('--d', i++);
            });
            obs.observe(modalBody, { childList: true });
        });
        obs.observe(modalBody, { childList: true });
    }

    stagger();
    run();
})();
