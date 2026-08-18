<?php
require_once '../../config/config.php';
require_once 'assets/api/at_risk_functions.php';
require_once '../../includes/gemini_client.php';
requireTeacher();

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();
if (!$teacher) {
    die('Teacher record not found for this account.');
}
$teacherId = (int) $teacher['teacher_id'];

$stmt = $pdo->prepare("
    SELECT co.offering_id
    FROM classofferings co
    WHERE co.teacher_id = ? AND co.status = 'active'
");
$stmt->execute([$teacherId]);
$offeringIds = array_column($stmt->fetchAll(), 'offering_id');

$roster = get_at_risk_roster($pdo, $offeringIds);

$counts = ['High' => 0, 'Medium' => 0, 'Low' => 0, 'Insufficient Data' => 0];
foreach ($roster as $r) {
    $counts[$r['risk_label']]++;
}

$aiEnabled = gemini_is_configured();

/**
 * Renders a small percentage + progress bar for a metric cell,
 * or an "insufficient data" note if the value is null.
 */
function render_metric_cell($pct)
{
    if ($pct === null) {
        return '<span class="metric-na">no data</span>';
    }
    $color = $pct >= 75 ? '#16a34a' : ($pct >= 50 ? '#d97706' : '#dc2626');
    $pctSafe = max(0, min(100, (float) $pct));
    return '<div class="metric-cell">'
        . '<span>' . number_format($pct, 1) . '%</span>'
        . '<span class="metric-bar"><span style="width:' . $pctSafe . '%;background:' . $color . ';"></span></span>'
        . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>At-Risk Students · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/at_risk_students.css">
</head>
<body>

<?php include '../../includes/teachers_sidebar.php'; ?>

<main class="main-content" id="dashMain">
    <?php if (file_exists('../../includes/teacher_header.php')) include '../../includes/teacher_header.php'; ?>

    <div class="ar-wrap">
        <div class="ar-header">
            <div>
                <h1>At-Risk Students</h1>
                <p>Attendance, assignments, and quizzes combined into one early-warning score, with AI-generated context per student.</p>
            </div>
        </div>

        <?php if (!$aiEnabled): ?>
            <div class="ar-config-note">
                <i class="fas fa-triangle-exclamation"></i>
                AI Insights are disabled — add a <code>GEMINI_API_KEY</code> in <code>config.php</code> to enable the "AI Insights" button (free key at aistudio.google.com/apikey).
            </div>
        <?php endif; ?>

        <div class="ar-summary">
            <div class="ar-stat high"><span class="n"><?= $counts['High'] ?></span><span class="l">High Risk</span></div>
            <div class="ar-stat medium"><span class="n"><?= $counts['Medium'] ?></span><span class="l">Medium Risk</span></div>
            <div class="ar-stat low"><span class="n"><?= $counts['Low'] ?></span><span class="l">Low Risk</span></div>
            <div class="ar-stat na"><span class="n"><?= $counts['Insufficient Data'] ?></span><span class="l">Not Enough Data</span></div>
        </div>

        <?php if (empty($roster)): ?>
            <div class="ar-table-wrap">
                <div class="ar-empty">
                    <i class="fas fa-shield-heart"></i>
                    <p>No active students found in your classes yet.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="ar-filters">
                <button class="ar-filter-btn active" data-filter="all">All (<?= count($roster) ?>)</button>
                <button class="ar-filter-btn" data-filter="high">High (<?= $counts['High'] ?>)</button>
                <button class="ar-filter-btn" data-filter="medium">Medium (<?= $counts['Medium'] ?>)</button>
                <button class="ar-filter-btn" data-filter="low">Low (<?= $counts['Low'] ?>)</button>
                <button class="ar-filter-btn" data-filter="na">Insufficient Data</button>
            </div>

            <div class="ar-table-wrap">
                <table class="ar-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Attendance</th>
                            <th>Assignments</th>
                            <th>Quizzes</th>
                            <th>Risk</th>
                            <th>AI Insights</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roster as $r):
                            $riskKey = strtolower(str_replace(' ', '-', $r['risk_label']));
                            $filterKey = $r['risk_label'] === 'Insufficient Data' ? 'na' : strtolower($r['risk_label']);
                        ?>
                        <tr data-filter="<?= $filterKey ?>">
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($r['name']) ?></div>
                                <div style="font-size:0.76rem;color:var(--text-muted);">Grade <?= $r['grade_level'] ?> · <?= htmlspecialchars($r['section']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($r['subject']) ?></td>
                            <td><?= render_metric_cell($r['attendance_pct']) ?></td>
                            <td>
                                <?= render_metric_cell($r['assignment_pct']) ?>
                                <?php if ($r['assignment_missing'] > 0): ?>
                                    <div style="font-size:0.72rem;color:#b91c1c;"><?= $r['assignment_missing'] ?>/<?= $r['assignment_total_due'] ?> missing</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= render_metric_cell($r['quiz_pct']) ?>
                                <?php if ($r['quiz_missing'] > 0): ?>
                                    <div style="font-size:0.72rem;color:#b91c1c;"><?= $r['quiz_missing'] ?>/<?= $r['quiz_total_due'] ?> missing</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="risk-pill <?= $riskKey === 'insufficient-data' ? 'na' : $riskKey ?>">
                                    <i class="fas fa-circle"></i>
                                    <?= htmlspecialchars($r['risk_label']) ?><?= $r['risk_score'] !== null ? ' · ' . $r['risk_score'] : '' ?>
                                </span>
                            </td>
                            <td>
                                <button class="ai-btn" data-student="<?= $r['student_id'] ?>" data-offering="<?= $r['offering_id'] ?>"
                                        data-name="<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>" data-subject="<?= htmlspecialchars($r['subject'], ENT_QUOTES) ?>"
                                        <?= (!$aiEnabled || $r['risk_label'] === 'Insufficient Data') ? 'disabled' : '' ?>>
                                    <i class="fas fa-wand-magic-sparkles"></i> Analyze
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- AI Insight Modal -->
<div class="ai-modal-overlay" id="aiModalOverlay">
    <div class="ai-modal">
        <div class="ai-modal-head">
            <div>
                <h3 id="aiModalName">Student</h3>
                <p id="aiModalSubject"></p>
            </div>
            <button class="ai-modal-close" id="aiModalClose"><i class="fas fa-times"></i></button>
        </div>
        <div class="ai-modal-body" id="aiModalBody">
            <div class="ai-loading">
                <i class="fas fa-spinner"></i>
                <p>Analyzing…</p>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // ---- Filters ----
    const filterBtns = document.querySelectorAll('.ar-filter-btn');
    const rows = document.querySelectorAll('table.ar-table tbody tr');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const f = btn.dataset.filter;
            rows.forEach(row => {
                row.style.display = (f === 'all' || row.dataset.filter === f) ? '' : 'none';
            });
        });
    });

    // ---- AI Insights modal ----
    const overlay = document.getElementById('aiModalOverlay');
    const modalBody = document.getElementById('aiModalBody');
    const modalName = document.getElementById('aiModalName');
    const modalSubject = document.getElementById('aiModalSubject');
    let currentStudent = null, currentOffering = null;

    function openModal(name, subject) {
        modalName.textContent = name;
        modalSubject.textContent = subject;
        modalBody.innerHTML = '<div class="ai-loading"><i class="fas fa-spinner"></i><p>Analyzing…</p></div>';
        overlay.classList.add('open');
    }
    function closeModal() { overlay.classList.remove('open'); }

    document.getElementById('aiModalClose').addEventListener('click', closeModal);
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    function renderInsight(data) {
        if (!data.success) {
            modalBody.innerHTML = `<div class="ai-error"><i class="fas fa-circle-exclamation"></i> ${escapeHtml(data.error || 'Something went wrong.')}</div>`;
            return;
        }
        const actions = (data.recommended_actions || []).map(a => `<li>${escapeHtml(a)}</li>`).join('');
        const when = data.generated_at ? new Date(data.generated_at).toLocaleString() : '';
        modalBody.innerHTML = `
            <div class="ai-section">
                <h4><i class="fas fa-circle-question"></i> Why</h4>
                <p>${escapeHtml(data.why || '')}</p>
            </div>
            <div class="ai-section">
                <h4><i class="fas fa-diagram-project"></i> How this was determined</h4>
                <p>${escapeHtml(data.how || '')}</p>
            </div>
            <div class="ai-section">
                <h4><i class="fas fa-list-check"></i> Recommended Actions</h4>
                <ul class="ai-actions-list">${actions || '<li>No specific actions returned.</li>'}</ul>
            </div>
            <div class="ai-meta">
                <span>${data.cached ? 'Cached · generated ' + when : 'Generated just now'}</span>
                <button class="ai-regen" id="aiRegenBtn"><i class="fas fa-rotate"></i> Regenerate</button>
            </div>
        `;
        document.getElementById('aiRegenBtn').addEventListener('click', () => {
            fetchInsight(currentStudent, currentOffering, true);
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function fetchInsight(studentId, offeringId, force) {
        modalBody.innerHTML = '<div class="ai-loading"><i class="fas fa-spinner"></i><p>' + (force ? 'Regenerating…' : 'Analyzing…') + '</p></div>';
        fetch('analyze_student_risk.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ student_id: studentId, offering_id: offeringId, force: !!force })
        })
        .then(res => res.json())
        .then(renderInsight)
        .catch(() => {
            modalBody.innerHTML = '<div class="ai-error"><i class="fas fa-circle-exclamation"></i> Network error — please try again.</div>';
        });
    }

    document.querySelectorAll('.ai-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (btn.disabled) return;
            currentStudent = btn.dataset.student;
            currentOffering = btn.dataset.offering;
            openModal(btn.dataset.name, btn.dataset.subject);
            fetchInsight(currentStudent, currentOffering, false);
        });
    });
})();
</script>

</body>
</html>