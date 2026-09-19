<?php
/**
 * SUA IntelliLearn - Admin System Analytics
 * St. Uriel Academy Admin Portal
 *
 * Teacher Evaluation results for a survey round: response rate, ratings by
 * category and teacher, and an on-demand Gemini summary.
 *
 * Access: the `analytics` module (enforced in config.php via access_control.php).
 * The Gemini summary is generated on request and is never stored.
 */

require_once '../../config/config.php';
require_once '../../includes/teacher_evaluation.php';

requireAdmin();

$csrfToken  = generateCSRFToken();
$tevalReady = teval_tables_ready($pdo);
$rounds     = $tevalReady ? teval_get_rounds($pdo, 50) : [];

// Which round to show: ?round=ID, else the newest.
$round = null;
$wantedId = (int) ($_GET['round'] ?? 0);
foreach ($rounds as $r) {
    if ((int) $r['round_id'] === $wantedId) { $round = $r; break; }
}
if (!$round && $rounds) $round = $rounds[0];

$results   = $round ? teval_round_results($pdo, $round) : null;
$cats      = teval_categories();
$qFlat     = teval_questions_flat();
$hasData   = $results && $results['overview']['submitted'] > 0;
$isLive    = $round ? teval_round_is_live($round) : false;
$minForAi  = TEACHER_EVAL_MIN_RESPONSES_FOR_AI;
$canSeeSettings = adminCanRead('settings');

// Data handed to analytics.js (rendered client-side with textContent, never innerHTML).
$clientTeachers = [];
if ($hasData) {
    foreach ($results['teachers'] as $t) {
        $t['detail'] = teval_teacher_detail($pdo, (int) $round['round_id'], (int) $t['teacher_id']);
        $clientTeachers[] = $t;
    }
}
$clientData = [
    'csrf'         => $csrfToken,
    'roundId'      => $round ? (int) $round['round_id'] : 0,
    'minResponses' => $minForAi,
    'categories'   => array_map(fn($c) => ['label' => $c['label'], 'icon' => $c['icon']], $cats),
    'questions'    => $qFlat,
    'scale'        => teval_scale(),
    'teachers'     => $clientTeachers,
];

/** Width (0-100) of a score bar for an average on the 1-5 scale. */
function an_pct(?float $avg): int
{
    return $avg === null ? 0 : (int) round(max(0, min(5, $avg)) / 5 * 100);
}
function an_score(?float $avg): string
{
    return $avg === null ? '—' : number_format($avg, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Analytics - SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assests/css/dashboard.css">
    <link rel="stylesheet" href="assests/css/settings.css">
    <link rel="stylesheet" href="assests/css/analytics.css">
</head>
<body>

    <?php include '../../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <?php include '../../includes/admin_header.php'; ?>

        <div class="content-wrapper">

            <div class="deco-circle deco-circle-1"></div>
            <div class="deco-circle deco-circle-2"></div>

            <div class="welcome-banner fade-in">
                <div class="welcome-banner-content">
                    <h1><i class="fas fa-chart-line"></i> System Analytics</h1>
                    <p>Teacher evaluation results and survey insights</p>
                </div>
                <div class="welcome-banner-accent">
                    <i class="fas fa-chart-pie"></i>
                </div>
            </div>

<?php if (!$tevalReady): ?>
            <div class="an-empty">
                <i class="fas fa-database"></i>
                <h3>Setup required</h3>
                <p>The teacher evaluation tables don't exist yet. Run <code>teacher_evaluation_migration.sql</code> against the <code>lms</code> database, then reload this page.</p>
            </div>

<?php elseif (!$round): ?>
            <div class="an-empty">
                <i class="fas fa-clipboard-list"></i>
                <h3>No teacher evaluation yet</h3>
                <p>Once a survey has been sent to students, its results and an AI summary will appear here.</p>
                <?php if ($canSeeSettings): ?>
                <a class="btn btn-primary" href="settings.php#teacher-evaluation"><i class="fas fa-paper-plane"></i> Go to Settings</a>
                <?php endif; ?>
            </div>

<?php else: ?>

            <!-- ================= ROUND PICKER ================= -->
            <div class="an-toolbar">
                <div class="an-toolbar-main">
                    <label for="roundSelect">Survey</label>
                    <select id="roundSelect" onchange="location.href='analytics.php?round=' + this.value">
                        <?php foreach ($rounds as $r): ?>
                        <option value="<?= (int) $r['round_id'] ?>" <?= (int) $r['round_id'] === (int) $round['round_id'] ? 'selected' : '' ?>>
                            <?= clean($r['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="an-toolbar-meta">
                    <span class="an-status <?= $isLive ? 'is-open' : 'is-closed' ?>"><?= $isLive ? 'Open' : 'Closed' ?></span>
                    <span><?= clean($round['school_year_label']) ?> &middot; <?= clean($round['term']) ?></span>
                    <span>Sent <?= clean(date('M j, Y', strtotime($round['opened_at']))) ?></span>
                    <?php if ($round['closes_on']): ?>
                    <span>Deadline <?= clean(date('M j, Y', strtotime($round['closes_on']))) ?></span>
                    <?php endif; ?>
                    <?php if ($canSeeSettings): ?>
                    <a href="settings.php#teacher-evaluation" class="an-link"><i class="fas fa-gear"></i> Survey settings</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ================= KEY NUMBERS ================= -->
            <?php $ov = $results['overview']; $sc = $results['school']; ?>
            <div class="an-metrics">
                <div class="an-metric">
                    <span class="an-metric-label">Response rate</span>
                    <strong class="an-metric-value"><?= (int) $ov['rate'] ?>%</strong>
                    <span class="an-metric-foot"><?= (int) $ov['submitted'] ?> of <?= (int) $ov['expected'] ?> class evaluations</span>
                </div>
                <div class="an-metric">
                    <span class="an-metric-label">Students responded</span>
                    <strong class="an-metric-value"><?= (int) $ov['students_responded'] ?></strong>
                    <span class="an-metric-foot">at least one evaluation</span>
                </div>
                <div class="an-metric">
                    <span class="an-metric-label">Teachers evaluated</span>
                    <strong class="an-metric-value"><?= (int) $ov['teachers_evaluated'] ?></strong>
                    <span class="an-metric-foot">with 1+ response</span>
                </div>
                <div class="an-metric">
                    <span class="an-metric-label">School-wide average</span>
                    <strong class="an-metric-value"><?= an_score($sc['overall']) ?><small> / 5</small></strong>
                    <span class="an-pill <?= clean($sc['band']['class']) ?>"><?= clean($sc['band']['label']) ?></span>
                </div>
            </div>

<?php if (!$hasData): ?>
            <div class="an-empty">
                <i class="fas fa-hourglass-half"></i>
                <h3>No responses yet</h3>
                <p>Students haven't submitted any evaluations for this survey. Check back once some responses come in.</p>
            </div>
<?php else: ?>

            <!-- ================= AI SUMMARY (school-wide) ================= -->
            <div class="card an-card">
                <div class="card-header">
                    <h2><i class="fas fa-wand-magic-sparkles"></i> AI Survey Summary</h2>
                    <span class="an-badge-note" title="Generated on request. Never saved to the database."><i class="fas fa-eye-slash"></i> Not saved</span>
                </div>
                <div class="card-body an-ai">
                    <p class="an-ai-intro">
                        Gemini reads the ratings and students' written comments and writes a summary for the whole school.
                        It's generated fresh each time and is <strong>not stored</strong>, so copy it if you want to keep it.
                    </p>
                    <div class="an-ai-actions">
                        <button type="button" class="btn btn-primary" id="schoolAiBtn"
                            <?= $ov['submitted'] < $minForAi ? 'disabled' : '' ?>>
                            <i class="fas fa-wand-magic-sparkles"></i> Generate summary
                        </button>
                        <?php if ($ov['submitted'] < $minForAi): ?>
                        <span class="an-hint">Needs at least <?= (int) $minForAi ?> responses (currently <?= (int) $ov['submitted'] ?>) to protect student anonymity.</span>
                        <?php endif; ?>
                    </div>
                    <div id="schoolAiResult" class="an-ai-result" aria-live="polite"></div>
                </div>
            </div>

            <!-- ================= CATEGORY + DISTRIBUTION ================= -->
            <div class="an-grid-2">
                <div class="card an-card">
                    <div class="card-header"><h2><i class="fas fa-layer-group"></i> Average by category</h2></div>
                    <div class="card-body">
                        <?php foreach ($cats as $key => $cat): $avg = $sc['categories'][$key]; ?>
                        <div class="an-bar-row">
                            <div class="an-bar-label"><i class="fas <?= clean($cat['icon']) ?>"></i> <?= clean($cat['label']) ?></div>
                            <div class="an-bar-track"><div class="an-bar-fill" style="width: <?= an_pct($avg) ?>%"></div></div>
                            <div class="an-bar-value"><?= an_score($avg) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card an-card">
                    <div class="card-header"><h2><i class="fas fa-chart-simple"></i> How students rated</h2></div>
                    <div class="card-body">
                        <?php
                        $dist = $sc['distribution'];
                        $distTotal = max(1, array_sum($dist));
                        $scale = teval_scale();
                        foreach ([5, 4, 3, 2, 1] as $n):
                            $pct = round($dist[$n] / $distTotal * 100);
                        ?>
                        <div class="an-bar-row">
                            <div class="an-bar-label"><?= $n ?> &middot; <?= clean($scale[$n]) ?></div>
                            <div class="an-bar-track"><div class="an-bar-fill an-fill-<?= $n ?>" style="width: <?= (int) $pct ?>%"></div></div>
                            <div class="an-bar-value"><?= (int) $pct ?>%</div>
                        </div>
                        <?php endforeach; ?>
                        <p class="an-foot">Share of all individual answers (<?= number_format(array_sum($dist)) ?> total).</p>
                    </div>
                </div>
            </div>

            <!-- ================= TEACHERS ================= -->
            <div class="card an-card">
                <div class="card-header">
                    <h2><i class="fas fa-chalkboard-user"></i> Results by teacher</h2>
                </div>
                <div class="card-body an-table-wrap">
                    <table class="data-table an-table">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th class="num">Responses</th>
                                <th>Overall</th>
                                <?php foreach ($cats as $cat): ?>
                                <th class="num an-cat-col" title="<?= clean($cat['label']) ?>"><?= clean(explode(' ', $cat['label'])[0]) ?></th>
                                <?php endforeach; ?>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['teachers'] as $t): ?>
                            <tr>
                                <td>
                                    <strong><?= clean($t['name']) ?></strong>
                                    <?php if (!empty($t['department'])): ?><div class="an-sub"><?= clean($t['department']) ?></div><?php endif; ?>
                                </td>
                                <td class="num"><?= (int) $t['responses'] ?></td>
                                <td>
                                    <div class="an-overall">
                                        <strong><?= an_score($t['overall']) ?></strong>
                                        <div class="an-mini-track"><div class="an-mini-fill <?= clean($t['band']['class']) ?>" style="width: <?= an_pct($t['overall']) ?>%"></div></div>
                                    </div>
                                    <span class="an-pill <?= clean($t['band']['class']) ?>"><?= clean($t['band']['label']) ?></span>
                                </td>
                                <?php foreach ($cats as $key => $cat): ?>
                                <td class="num an-cat-col"><?= an_score($t['categories'][$key]) ?></td>
                                <?php endforeach; ?>
                                <td class="num">
                                    <button type="button" class="btn btn-outline btn-sm" data-teacher="<?= (int) $t['teacher_id'] ?>">
                                        Details
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ================= RESPONSE RATE BY SECTION ================= -->
            <?php if (!empty($results['sections'])): ?>
            <div class="card an-card">
                <div class="card-header"><h2><i class="fas fa-people-group"></i> Response rate by section</h2></div>
                <div class="card-body">
                    <?php foreach ($results['sections'] as $s): ?>
                    <div class="an-bar-row">
                        <div class="an-bar-label"><?= clean($s['label']) ?></div>
                        <div class="an-bar-track"><div class="an-bar-fill" style="width: <?= (int) $s['rate'] ?>%"></div></div>
                        <div class="an-bar-value an-wide"><?= (int) $s['submitted'] ?>/<?= (int) $s['expected'] ?> &middot; <?= (int) $s['rate'] ?>%</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

<?php endif; /* hasData */ ?>
<?php endif; /* round */ ?>

        </div>
    </div>

    <!-- ================= TEACHER DETAIL DRAWER ================= -->
    <div class="an-overlay" id="anOverlay" hidden></div>
    <aside class="an-drawer" id="anDrawer" role="dialog" aria-modal="true" aria-labelledby="anDrawerTitle" hidden>
        <div class="an-drawer-head">
            <div>
                <h2 id="anDrawerTitle"></h2>
                <div id="anDrawerMeta" class="an-drawer-meta"></div>
            </div>
            <button type="button" class="an-close" id="anClose" aria-label="Close details"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="an-drawer-body" id="anDrawerBody"></div>
    </aside>

    <script id="evalData" type="application/json"><?= json_encode($clientData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
    <script src="assests/js/analytics.js"></script>
</body>
</html>
