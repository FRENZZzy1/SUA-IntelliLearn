<?php
require_once '../../config/config.php';
require_once '../../includes/teacher_evaluation.php';

// ---- Access control -------------------------------------------------
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../public/login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT student_id, firstname, lastname FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch();
if (!$student) {
    die('Student record not found for this account.');
}
$studentId       = (int) $student['student_id'];
$studentFullName = trim($student['firstname'] . ' ' . $student['lastname']);

$csrfToken = generateCSRFToken();
$round     = teval_get_open_round($pdo);
$classes   = $round ? teval_student_classes($pdo, $studentId, $round) : [];
$total     = count($classes);
$done      = 0;
foreach ($classes as $c) { if ((int) $c['done']) $done++; }

$categories = teval_categories();
$scale      = teval_scale();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Evaluation · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/teacher_evaluation.css">
</head>

<body>

    <?php include '../../includes/student_sidebar.php'; ?>

    <main class="main-content" id="dashMain">

        <?php include '../../includes/student_header.php'; ?>

        <div class="dash-page-title">
            <h1 class="dash-title">Teacher Evaluation</h1>
            <?php if ($round): ?>
            <p class="dash-subtitle">
                <?= htmlspecialchars($round['title']) ?> &middot; <?= htmlspecialchars($round['term']) ?>,
                <?= htmlspecialchars($round['school_year_label']) ?>
                <?php if ($round['closes_on']): ?>
                    &middot; Closes <?= htmlspecialchars(date('M j, Y', strtotime($round['closes_on']))) ?>
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </div>

        <?php if (!$round): ?>
        <section class="panel">
            <div class="panel-empty">
                <i class="fas fa-clipboard-list"></i>
                <p>There's no teacher evaluation open right now.</p>
                <span>When your school sends one, it will show up here and on your dashboard.</span>
            </div>
        </section>

        <?php elseif ($total === 0): ?>
        <section class="panel">
            <div class="panel-empty">
                <i class="fas fa-book-open"></i>
                <p>You don't have any classes to evaluate for <?= htmlspecialchars($round['term']) ?>.</p>
                <span>Only classes you're enrolled in for this term can be evaluated.</span>
            </div>
        </section>

        <?php else: ?>

        <section class="tev-intro">
            <div class="tev-intro-icon"><i class="fas fa-user-shield"></i></div>
            <div>
                <strong>Your answers are anonymous.</strong>
                They're saved without your name or student ID, and your teachers won't know who said what.
                Please be honest and respectful. Your feedback helps improve teaching.
            </div>
        </section>

        <section class="tev-progress" id="tevProgress" aria-live="polite">
            <div class="tev-progress-head">
                <span id="tevProgressText"><?= $done ?> of <?= $total ?> completed</span>
                <strong id="tevProgressPct"><?= $total ? (int) round($done / $total * 100) : 0 ?>%</strong>
            </div>
            <div class="tev-progress-track"><div class="tev-progress-fill" id="tevProgressFill" style="width: <?= $total ? (int) round($done / $total * 100) : 0 ?>%"></div></div>
        </section>

        <div class="tev-allDone" id="tevAllDone" <?= $done === $total ? '' : 'hidden' ?>>
            <i class="fas fa-circle-check"></i>
            <div><strong>All done. Thank you!</strong> You've evaluated every teacher for this survey.</div>
        </div>

        <section class="tev-grid" id="tevGrid">
            <?php foreach ($classes as $c):
                $teacherName = trim($c['firstname'] . ' ' . $c['lastname']);
                $isDone = (int) $c['done'] > 0;
            ?>
            <article class="tev-card <?= $isDone ? 'is-done' : '' ?>" data-offering="<?= (int) $c['offering_id'] ?>">
                <div class="tev-card-avatar"><i class="fas fa-chalkboard-user"></i></div>
                <div class="tev-card-main">
                    <h3><?= htmlspecialchars($c['subject_name']) ?></h3>
                    <p class="tev-teacher"><?= htmlspecialchars($teacherName) ?></p>
                    <p class="tev-meta">Grade <?= (int) $c['grade_level'] ?> &middot; <?= htmlspecialchars($c['section_name']) ?></p>
                </div>
                <div class="tev-card-action">
                    <span class="tev-done-badge" <?= $isDone ? '' : 'hidden' ?>><i class="fas fa-check"></i> Submitted</span>
                    <button type="button" class="tev-btn tev-evaluate" <?= $isDone ? 'hidden' : '' ?>
                        data-offering="<?= (int) $c['offering_id'] ?>"
                        data-teacher="<?= htmlspecialchars($teacherName, ENT_QUOTES) ?>"
                        data-subject="<?= htmlspecialchars($c['subject_name'], ENT_QUOTES) ?>">
                        Evaluate <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </article>
            <?php endforeach; ?>
        </section>

        <!-- ================= EVALUATION FORM ================= -->
        <dialog class="tev-dialog" id="tevDialog" aria-labelledby="tevDialogTitle">
            <form id="tevForm" method="dialog" novalidate>
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="offering_id" id="tevOffering" value="">

                <header class="tev-dialog-head">
                    <div>
                        <h2 id="tevDialogTitle">Evaluate teacher</h2>
                        <p id="tevDialogSub"></p>
                    </div>
                    <button type="button" class="tev-x" id="tevCancelX" aria-label="Close"><i class="fas fa-xmark"></i></button>
                </header>

                <div class="tev-dialog-body">
                    <div class="tev-scale-legend">
                        Rate how much you agree:
                        <?php foreach ([1, 5] as $n): ?>
                            <strong><?= $n ?></strong> = <?= htmlspecialchars($scale[$n]) ?><?= $n === 1 ? ' &middot; ' : '' ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="tev-error" id="tevError" role="alert" hidden></div>

                    <?php $qNum = 0; foreach ($categories as $catKey => $cat): ?>
                    <h3 class="tev-cat"><i class="fas <?= htmlspecialchars($cat['icon']) ?>"></i> <?= htmlspecialchars($cat['label']) ?></h3>
                        <?php foreach ($cat['questions'] as $qKey => $qText): $qNum++; ?>
                        <fieldset class="tev-q" data-q="<?= htmlspecialchars($qKey) ?>">
                            <legend><span class="tev-qn"><?= $qNum ?>.</span> <?= htmlspecialchars($qText) ?></legend>
                            <div class="tev-scale">
                                <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
                                <label title="<?= $n ?> &ndash; <?= htmlspecialchars($scale[$n]) ?>">
                                    <input type="radio" name="ratings[<?= htmlspecialchars($qKey) ?>]" value="<?= $n ?>"
                                        aria-label="<?= $n ?> &ndash; <?= htmlspecialchars($scale[$n]) ?>">
                                    <span><?= $n ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php endforeach; ?>
                    <?php endforeach; ?>

                    <h3 class="tev-cat"><i class="fas fa-pen"></i> In your own words <small>(optional)</small></h3>
                    <label class="tev-text">
                        <span>What does this teacher do well?</span>
                        <textarea name="strengths" rows="3" maxlength="<?= (int) TEACHER_EVAL_COMMENT_MAX_LEN ?>"></textarea>
                    </label>
                    <label class="tev-text">
                        <span>What could this teacher improve?</span>
                        <textarea name="improvements" rows="3" maxlength="<?= (int) TEACHER_EVAL_COMMENT_MAX_LEN ?>"></textarea>
                    </label>
                    <p class="tev-tip"><i class="fas fa-circle-info"></i> Please don't write your name or other students' names, since your comment is shown to school administrators.</p>
                </div>

                <footer class="tev-dialog-foot">
                    <button type="button" class="tev-btn tev-btn-ghost" id="tevCancel">Cancel</button>
                    <button type="submit" class="tev-btn" id="tevSubmit"><i class="fas fa-paper-plane"></i> Submit evaluation</button>
                </footer>
            </form>
        </dialog>

        <div class="tev-toast" id="tevToast" role="status" hidden></div>
        <?php endif; ?>

    </main>

<?php if ($round && $total > 0): ?>
    <script>
    (function () {
        var TOTAL = <?= (int) $total ?>;
        var done = <?= (int) $done ?>;

        var dialog = document.getElementById('tevDialog');
        var form = document.getElementById('tevForm');
        var errorBox = document.getElementById('tevError');
        var submitBtn = document.getElementById('tevSubmit');
        var toast = document.getElementById('tevToast');
        var openerBtn = null;

        function showToast(msg) {
            toast.textContent = msg;
            toast.hidden = false;
            clearTimeout(showToast.t);
            showToast.t = setTimeout(function () { toast.hidden = true; }, 3500);
        }

        function showError(msg) {
            errorBox.textContent = msg;
            errorBox.hidden = false;
            errorBox.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }

        function updateProgress() {
            var pct = TOTAL ? Math.round(done / TOTAL * 100) : 0;
            document.getElementById('tevProgressText').textContent = done + ' of ' + TOTAL + ' completed';
            document.getElementById('tevProgressPct').textContent = pct + '%';
            document.getElementById('tevProgressFill').style.width = pct + '%';
            document.getElementById('tevAllDone').hidden = done < TOTAL;

            // Keep the sidebar badge in step (it lives in includes/student_sidebar.php).
            var badge = document.getElementById('tevNavBadge');
            if (badge) {
                var left = TOTAL - done;
                if (left > 0) { badge.textContent = left; } else { badge.remove(); }
            }
        }

        document.querySelectorAll('.tev-evaluate').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openerBtn = btn;
                form.reset();
                errorBox.hidden = true;
                document.querySelectorAll('.tev-q.is-missing').forEach(function (q) { q.classList.remove('is-missing'); });
                document.getElementById('tevOffering').value = btn.dataset.offering;
                document.getElementById('tevDialogTitle').textContent = 'Evaluate ' + btn.dataset.teacher;
                document.getElementById('tevDialogSub').textContent = btn.dataset.subject;
                document.querySelector('.tev-dialog-body').scrollTop = 0;
                dialog.showModal();
            });
        });

        function closeDialog() { dialog.close(); if (openerBtn && !openerBtn.hidden) openerBtn.focus(); }
        document.getElementById('tevCancel').addEventListener('click', closeDialog);
        document.getElementById('tevCancelX').addEventListener('click', closeDialog);
        // Click on the backdrop closes it too.
        dialog.addEventListener('click', function (e) { if (e.target === dialog) closeDialog(); });

        // Clear the "missing" highlight as soon as a question is answered.
        form.addEventListener('change', function (e) {
            var q = e.target.closest('.tev-q');
            if (q) q.classList.remove('is-missing');
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            errorBox.hidden = true;

            // Every rating question must be answered.
            var firstMissing = null;
            document.querySelectorAll('.tev-q').forEach(function (q) {
                var ok = q.querySelector('input:checked');
                q.classList.toggle('is-missing', !ok);
                if (!ok && !firstMissing) firstMissing = q;
            });
            if (firstMissing) {
                showError('Please answer every rating question. The ones you missed are highlighted.');
                firstMissing.scrollIntoView({ block: 'center', behavior: 'smooth' });
                return;
            }

            var original = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

            fetch('assets/api/teacher_evaluation_submit.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new FormData(form)
            })
            .then(function (res) {
                return res.json().catch(function () { return null; }).then(function (json) {
                    return { ok: res.ok, json: json };
                });
            })
            .then(function (r) {
                if (r.ok && r.json && r.json.success) {
                    var card = document.querySelector('.tev-card[data-offering="' + document.getElementById('tevOffering').value + '"]');
                    if (card) {
                        card.classList.add('is-done');
                        card.querySelector('.tev-done-badge').hidden = false;
                        card.querySelector('.tev-evaluate').hidden = true;
                    }
                    done++;
                    updateProgress();
                    dialog.close();
                    showToast('Thank you! Your evaluation was submitted.');
                    return;
                }
                var msg = (r.json && r.json.error) || 'Something went wrong. Please try again.';
                // "Already evaluated" means another tab got there first: reflect it.
                showError(msg);
            })
            .catch(function () { showError('Could not reach the server. Please check your connection and try again.'); })
            .then(function () { submitBtn.disabled = false; submitBtn.innerHTML = original; });
        });
    })();
    </script>
<?php endif; ?>

</body>

</html>
