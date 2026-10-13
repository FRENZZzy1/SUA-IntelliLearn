<?php
/**
 * Student Evaluation Results
 *
 * Reuses the shared teacher-evaluation aggregation helpers used by
 * public/admin/analytics.php. Teachers can only see their own results.
 */
require_once '../../config/config.php';
require_once '../../includes/teacher_evaluation.php';

requireTeacher();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare("SELECT teacher_id, firstname, lastname FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();

$teacherId = $teacher ? (int) $teacher['teacher_id'] : 0;
$teacherName = $teacher
    ? trim(($teacher['firstname'] ?? '') . ' ' . ($teacher['lastname'] ?? ''))
    : '';

$tevalReady = teval_tables_ready($pdo);
$rounds = ($tevalReady && $teacherId > 0) ? teval_get_rounds($pdo, 50) : [];
$requestedRoundId = (int) ($_GET['round'] ?? 0);
$round = null;

foreach ($rounds as $candidate) {
    if ((int) $candidate['round_id'] === $requestedRoundId) {
        $round = $candidate;
        break;
    }
}
if (!$round && $rounds) {
    $round = $rounds[0];
}

$results = $round ? teval_round_results($pdo, $round) : null;
$teacherResult = ($results && $teacherId > 0)
    ? ($results['teachers'][$teacherId] ?? null)
    : null;

// Reuse the admin's class aggregation, but do not display raw written comments
// because the student survey tells respondents those comments go to administrators.
$teacherClasses = ($round && $teacherResult)
    ? teval_teacher_detail($pdo, (int) $round['round_id'], $teacherId)['classes']
    : [];

$categories = teval_categories();
$questions = teval_questions_flat();
$scale = teval_scale();

function ser_score(?float $value): string
{
    return $value === null ? '—' : number_format($value, 2);
}

function ser_pct(?float $value): int
{
    return $value === null ? 0 : (int) round(max(0, min(5, $value)) / 5 * 100);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Evaluation Results · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="../admin/assests/css/analytics.css">
    <style>
        :root { --bg-card: var(--card); --text-dark: var(--text); --radius-sm: 8px; }
        .ser-banner { margin-bottom: 20px; }
        .ser-subtitle { margin: 6px 0 0; opacity: .86; font-size: .92rem; }
        .ser-panels { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-bottom: 20px; }
        .ser-panel-title { display: flex; align-items: center; gap: 9px; margin: 0 0 16px; font-size: 1.02rem; color: var(--primary-dark); }
        .ser-panel-title i { color: var(--primary); }
        .ser-rating-note { margin: 12px 0 0; font-size: .78rem; color: var(--text-muted); line-height: 1.5; }
        .ser-cat-heading { margin: 18px 0 10px; font-size: .82rem; color: var(--primary); text-transform: uppercase; letter-spacing: .04em; }
        .ser-cat-heading:first-child { margin-top: 0; }
        .ser-question { white-space: normal; min-width: 260px; }
        @media (max-width: 900px) {
            .ser-panels { grid-template-columns: 1fr; }
            .an-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 560px) {
            .main-content { padding-left: 14px; padding-right: 14px; }
            .an-metrics { grid-template-columns: 1fr; }
            .an-toolbar-main { width: 100%; align-items: flex-start; flex-direction: column; }
            .an-toolbar-main select { max-width: 100%; width: 100%; }
            .an-bar-row { grid-template-columns: minmax(95px, 1fr) minmax(60px, 1fr) 48px; gap: 8px; }
        }
    </style>
</head>
<body>
<?php include '../../includes/teachers_sidebar.php'; ?>

<main class="main-content" id="dashMain">
    <?php include '../../includes/teacher_header.php'; ?>

    <section class="welcome-banner ser-banner">
        <div class="welcome-text">
            <h2><i class="fas fa-chart-column"></i> Student Evaluation Results</h2>
            <p class="ser-subtitle">
                <?= htmlspecialchars($teacherName !== '' ? $teacherName : 'Teacher account', ENT_QUOTES, 'UTF-8') ?>
                · View your aggregated student feedback by survey, category, question, and class.
            </p>
        </div>
    </section>

    <?php if (!$teacher): ?>
        <section class="an-empty">
            <i class="fas fa-user-slash"></i>
            <h3>Teacher profile not found</h3>
            <p>Your account is not linked to a teacher profile. Please contact the school administrator.</p>
        </section>

    <?php elseif (!$tevalReady): ?>
        <section class="an-empty">
            <i class="fas fa-database"></i>
            <h3>Evaluation results are not set up yet</h3>
            <p>The teacher evaluation tables are not available. Please contact the school administrator.</p>
        </section>

    <?php elseif (!$round): ?>
        <section class="an-empty">
            <i class="fas fa-clipboard-list"></i>
            <h3>No evaluation surveys yet</h3>
            <p>Results will appear here after the school creates a student evaluation survey.</p>
        </section>

    <?php else: ?>
        <form class="an-toolbar" method="get" action="peer_evaluation.php">
            <div class="an-toolbar-main">
                <label for="roundSelect">Survey</label>
                <select id="roundSelect" name="round" onchange="this.form.submit()">
                    <?php foreach ($rounds as $candidate): ?>
                    <option value="<?= (int) $candidate['round_id'] ?>" <?= (int) $candidate['round_id'] === (int) $round['round_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($candidate['title'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="an-toolbar-meta">
                <span class="an-status <?= teval_round_is_live($round) ? 'is-open' : 'is-closed' ?>">
                    <?= teval_round_is_live($round) ? 'Open' : 'Closed' ?>
                </span>
                <span><?= htmlspecialchars($round['school_year_label'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($round['term'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!empty($round['opened_at'])): ?>
                <span>Sent <?= htmlspecialchars(date('M j, Y', strtotime($round['opened_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if (!empty($round['closes_on'])): ?>
                <span>Deadline <?= htmlspecialchars(date('M j, Y', strtotime($round['closes_on'])), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!$teacherResult): ?>
        <section class="an-empty">
            <i class="fas fa-chart-simple"></i>
            <h3>No results for this survey</h3>
            <p>No student evaluations have been recorded for you in this survey round yet.</p>
        </section>
        <?php else: ?>
        <section class="an-metrics">
            <article class="an-metric">
                <span class="an-metric-label">Overall rating</span>
                <strong class="an-metric-value"><?= ser_score($teacherResult['overall']) ?><small> / 5</small></strong>
                <span class="an-pill <?= htmlspecialchars($teacherResult['band']['class'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($teacherResult['band']['label'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </article>
            <article class="an-metric">
                <span class="an-metric-label">Responses received</span>
                <strong class="an-metric-value"><?= (int) $teacherResult['responses'] ?></strong>
                <span class="an-metric-foot">Student class evaluations</span>
            </article>
            <article class="an-metric">
                <span class="an-metric-label">Classes covered</span>
                <strong class="an-metric-value"><?= count($teacherClasses) ?></strong>
                <span class="an-metric-foot">With one or more responses</span>
            </article>
        </section>

        <div class="ser-panels">
            <section class="panel">
                <h3 class="ser-panel-title"><i class="fas fa-layer-group"></i> Average by category</h3>
                <?php foreach ($categories as $catKey => $category):
                    $avg = $teacherResult['categories'][$catKey] ?? null;
                ?>
                <div class="an-bar-row">
                    <div class="an-bar-label"><?= htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="an-bar-track"><div class="an-bar-fill" style="width: <?= ser_pct($avg) ?>%"></div></div>
                    <div class="an-bar-value"><?= ser_score($avg) ?></div>
                </div>
                <?php endforeach; ?>
                <p class="ser-rating-note">Ratings use a 1–5 scale. Higher scores indicate stronger agreement with the evaluation statements.</p>
            </section>

            <section class="panel">
                <h3 class="ser-panel-title"><i class="fas fa-chart-simple"></i> Rating distribution</h3>
                <?php
                $distribution = $teacherResult['distribution'] ?? [];
                $distTotal = max(1, array_sum($distribution));
                foreach ([5, 4, 3, 2, 1] as $rating):
                    $count = (int) ($distribution[$rating] ?? 0);
                    $pct = (int) round($count / $distTotal * 100);
                ?>
                <div class="an-bar-row">
                    <div class="an-bar-label"><?= $rating ?> · <?= htmlspecialchars($scale[$rating], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="an-bar-track"><div class="an-bar-fill an-fill-<?= $rating ?>" style="width: <?= $pct ?>%"></div></div>
                    <div class="an-bar-value"><?= $count ?> <small>(<?= $pct ?>%)</small></div>
                </div>
                <?php endforeach; ?>
                <p class="ser-rating-note">Distribution counts are based on all individual rating answers submitted for this survey round.</p>
            </section>
        </div>

        <section class="panel" style="margin-bottom:20px;">
            <h3 class="ser-panel-title"><i class="fas fa-list-check"></i> Results by question</h3>
            <?php foreach ($categories as $catKey => $category): ?>
                <h4 class="ser-cat-heading"><?= htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8') ?></h4>
                <div class="table-scroll">
                    <table class="panel-table">
                        <thead><tr><th>Evaluation statement</th><th>Average rating</th></tr></thead>
                        <tbody>
                        <?php foreach ($category['questions'] as $questionKey => $questionText):
                            $avg = $teacherResult['questions'][$questionKey] ?? null;
                        ?>
                            <tr>
                                <td class="ser-question"><?= htmlspecialchars($questionText, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><strong><?= ser_score($avg) ?></strong> / 5</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="panel" style="margin-bottom:20px;">
            <h3 class="ser-panel-title"><i class="fas fa-book-open"></i> Results by class</h3>
            <?php if (!$teacherClasses): ?>
                <p class="an-muted">There are no class-level results in this survey round yet.</p>
            <?php else: ?>
            <div class="table-scroll">
                <table class="panel-table">
                    <thead><tr><th>Class</th><th>Responses</th><th>Average rating</th></tr></thead>
                    <tbody>
                    <?php foreach ($teacherClasses as $class): ?>
                        <tr>
                            <td><?= htmlspecialchars($class['label'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int) $class['responses'] ?></td>
                            <td><strong><?= ser_score($class['avg']) ?></strong> / 5</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <p class="ser-rating-note">Only your own aggregated evaluation results are shown here. Individual written comments remain available to school administrators.</p>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
