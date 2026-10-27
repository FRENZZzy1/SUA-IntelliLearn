<?php
include 'assets/api/quiz_api/quiz_generator_functions.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Generator · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/quiz_generator.css">
</head>

<body>

    <?php include '../../includes/teachers_sidebar.php'; ?>

    <main class="main-content" id="dashMain">

        <?php include '../../includes/teacher_header.php'; ?>

        <input type="hidden" id="qgCsrfToken" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="qg-header">
            <h2 class="qg-title"><i class="fas fa-file-circle-question"></i> AI Quiz Generator</h2>
            <p class="qg-subtitle">Turn your class PDF modules (or a topic) into a quiz draft — review and edit
                everything before it goes live.</p>
        </div>

        <?php if (empty($teacherOfferings)): ?>
            <div class="qg-panel">
                <div class="panel-empty">
                    <i class="fas fa-chalkboard"></i>
                    <?php if ($currentTerm === null): ?>
                        <p>No term is running right now.</p>
                        <span>Quizzes can be generated for the ongoing term's classes once a term starts.</span>
                    <?php else: ?>
                        <p>No classes assigned for <?= htmlspecialchars($currentTermLabel) ?> yet.</p>
                        <span>You'll be able to generate quizzes once the admin assigns you a class this term.</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>

            <div class="qg-layout">

                <!-- ==================== Main column ==================== -->
                <div>

                    <!-- Generator form -->
                    <section class="qg-panel">
                        <h3><i class="fas fa-wand-magic-sparkles"></i> Generate a New Quiz</h3>

                        <form id="qgGenerateForm">
                            <div class="qg-form-grid">

                                <div class="qg-field span-2">
                                    <label for="qgOfferingSearch">Subject / Class <span class="hint">— <?= htmlspecialchars($currentTermLabel) ?> (ongoing term)</span></label>
                                    <div class="qg-combo" id="qgCombo">
                                        <!-- Native select stays as the source of truth (value + options); the
                                             searchable picker below is built from it by quiz_class_picker.js -->
                                        <select id="qgOffering" name="offering_id" class="qg-combo__native" tabindex="-1" aria-hidden="true">
                                            <option value="">Select a class…</option>
                                            <?php foreach ($teacherOfferings as $o): ?>
                                                <option value="<?= (int) $o['offering_id'] ?>"
                                                    data-subject="<?= htmlspecialchars($o['subject_name']) ?>"
                                                    data-meta="Grade <?= (int) $o['grade_level'] ?> · <?= htmlspecialchars($o['section_name']) ?>"
                                                    <?= (int) $o['offering_id'] === $preselectOfferingId ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($o['subject_name']) ?> — Grade <?= (int) $o['grade_level'] ?> <?= htmlspecialchars($o['section_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="qg-combo__control">
                                            <i class="fas fa-magnifying-glass qg-combo__icon"></i>
                                            <input type="text" id="qgOfferingSearch" class="qg-combo__input"
                                                placeholder="Search or select a class…" autocomplete="off" required
                                                role="combobox" aria-expanded="false" aria-controls="qgComboList" aria-autocomplete="list">
                                            <button type="button" class="qg-combo__toggle" id="qgComboToggle" tabindex="-1" aria-label="Show classes">
                                                <i class="fas fa-chevron-down"></i>
                                            </button>
                                        </div>
                                        <ul class="qg-combo__list" id="qgComboList" role="listbox" hidden></ul>
                                    </div>
                                </div>

                                <div class="qg-field">
                                    <label for="qgNumItems">Number of Items</label>
                                    <input type="number" id="qgNumItems" name="num_items" min="1" max="50" value="10"
                                        required>
                                </div>

                                <div class="qg-field">
                                    <label for="qgDifficulty">Difficulty</label>
                                    <select id="qgDifficulty" name="difficulty" required>
                                        <option value="easy">Easy</option>
                                        <option value="average" selected>Average</option>
                                        <option value="difficult">Difficult</option>
                                        <option value="custom">Custom mix (set items per difficulty)…</option>
                                    </select>
                                </div>

                                <!-- Custom difficulty mix (shown only when Difficulty = Custom mix) -->
                                <div class="qg-field span-2" id="qgMixField" style="display:none;">
                                    <label>Items per difficulty <span class="hint">— must add up to the Number of Items</span></label>
                                    <div class="qg-mix-row">
                                        <label class="qg-mix-item qg-mix-easy">Easy
                                            <input type="number" id="qgMixEasy" min="0" max="50" value="0">
                                        </label>
                                        <label class="qg-mix-item qg-mix-average">Average
                                            <input type="number" id="qgMixAverage" min="0" max="50" value="0">
                                        </label>
                                        <label class="qg-mix-item qg-mix-difficult">Difficult
                                            <input type="number" id="qgMixDifficult" min="0" max="50" value="0">
                                        </label>
                                        <span class="qg-mix-total" id="qgMixTotal"></span>
                                    </div>
                                </div>

                                <div class="qg-field span-2">
                                    <label>Question Type</label>
                                    <div class="qg-type-pills">
                                        <label class="qg-pill active">
                                            <input type="radio" name="question_type" value="mixed" checked> Mix of types
                                        </label>
                                        <label class="qg-pill">
                                            <input type="radio" name="question_type" value="mcq"> Multiple Choice
                                        </label>
                                        <label class="qg-pill">
                                            <input type="radio" name="question_type" value="true_false"> True / False
                                        </label>
                                        <label class="qg-pill">
                                            <input type="radio" name="question_type" value="identification"> Identification
                                        </label>
                                    </div>
                                </div>

                                <div class="qg-field span-2">
                                    <label>Quiz Source</label>
                                    <div class="qg-source-pills">
                                        <label class="qg-source-pill active">
                                            <input type="radio" name="source_type" value="pdf" checked>
                                            <i class="fas fa-file-pdf"></i> From class PDF modules
                                        </label>
                                        <label class="qg-source-pill">
                                            <input type="radio" name="source_type" value="topic">
                                            <i class="fas fa-keyboard"></i> From a topic I describe
                                        </label>
                                    </div>
                                </div>

                                <!-- PDF modules of the selected class (filled by quiz_generator.js) -->
                                <div class="qg-field span-2" id="qgPdfField">
                                    <label>PDF Modules <span class="hint">— pick up to 3 text-based PDFs; questions
                                            are based only on their text</span></label>
                                    <div class="qg-pdf-list" id="qgPdfList"></div>
                                </div>

                                <div class="qg-field span-2">
                                    <label for="qgTopic" id="qgTopicLabel">Topic <span class="hint">— describe what the
                                            quiz should cover</span></label>
                                    <textarea id="qgTopic" name="topic"
                                        placeholder="e.g. Cell structure and function: organelles, their roles, plant vs. animal cell differences"></textarea>
                                </div>

                                <div class="qg-field span-2">
                                    <label for="qgTitleInput">Quiz Title <span class="hint">— optional, AI will suggest one
                                            if left blank</span></label>
                                    <input type="text" id="qgTitleInput" name="title"
                                        placeholder="e.g. Quarter 2 Quiz: Cell Biology">
                                </div>

                            </div>

                            <div class="qg-actions">
                                <button type="submit" class="qg-btn qg-btn--primary" id="qgGenerateBtn">
                                    <i class="fas fa-sparkles"></i> Generate Quiz
                                </button>
                            </div>
                        </form>

                        <div class="qg-loading" id="qgLoading">
                            <div class="qg-spinner"></div>
                            <span>Reading your module and talking to the AI model — this can take up to 30 seconds…</span>
                        </div>

                        <div class="qg-alert" id="qgGenerateAlert"></div>
                    </section>

                    <!-- Review / edit -->
                    <section class="qg-panel" id="qgReviewSection">
                        <div class="qg-review-head">
                            <h3 style="margin:0;"><i class="fas fa-pen-to-square"></i> Review &amp; Edit</h3>
                            <span class="qg-model-badge" id="qgModelBadge"></span>
                        </div>

                        <div class="qg-meta-grid">
                            <div class="qg-field">
                                <label for="qgQuizTitle">Quiz Title</label>
                                <input type="text" id="qgQuizTitle">
                            </div>
                            <div class="qg-field">
                                <label for="qgQuizDescription">Description</label>
                                <input type="text" id="qgQuizDescription">
                            </div>
                            <div class="qg-field">
                                <label for="qgTimeLimit">Time Limit (minutes) <span class="hint">optional</span></label>
                                <input type="number" id="qgTimeLimit" min="1">
                            </div>
                            <div class="qg-field">
                                <label for="qgMaxAttempts">Max Attempts</label>
                                <input type="number" id="qgMaxAttempts" min="1" value="1">
                            </div>
                            <div class="qg-field" style="flex-direction:row; align-items:center; gap:8px;">
                                <input type="checkbox" id="qgShuffle" style="width:auto;">
                                <label for="qgShuffle" style="margin:0;">Shuffle questions for students</label>
                            </div>

                            <!-- Insert inside <div class="qg-meta-grid"> in quiz_generator.php -->
                            <div class="qg-field">
                                <label for="qgAvailableFrom">Available From</label>
                                <input type="datetime-local" id="qgAvailableFrom">
                            </div>

                            <div class="qg-field">
                                <label for="qgAvailableUntil">Available Until (Deadline) <span
                                        class="hint">optional</span></label>
                                <input type="datetime-local" id="qgAvailableUntil">
                            </div>

                        </div>

                        <div class="qg-question-list" id="qgQuestionList"></div>

                        <button type="button" class="qg-add-question" id="qgAddQuestion">
                            <i class="fas fa-plus"></i> Add Question Manually
                        </button>

                        <div class="qg-alert" id="qgSaveAlert"></div>

                        <div class="qg-save-bar">
                            <button type="button" class="qg-btn qg-btn--ghost" id="qgSaveDraftBtn">
                                <i class="fas fa-floppy-disk"></i> Save as Draft
                            </button>
                            <button type="button" class="qg-btn qg-btn--accent" id="qgPublishBtn">
                                <i class="fas fa-check"></i> Publish Quiz
                            </button>
                        </div>
                    </section>

                </div>

                <!-- ==================== Sidebar column (sticky) ==================== -->
                <div class="qg-side">

                    <!-- Quiz summary for the ongoing term -->
                    <section class="qg-panel">
                        <h3><i class="fas fa-chart-simple"></i> Quiz Summary <span class="qg-side-term"><?= htmlspecialchars($currentTermLabel) ?></span></h3>
                        <div class="qg-stats">
                            <div class="qg-stat qg-stat--draft">
                                <span class="qg-stat__num"><?= (int) $quizCounts['draft'] ?></span>
                                <span class="qg-stat__lbl">Drafts</span>
                            </div>
                            <div class="qg-stat qg-stat--published">
                                <span class="qg-stat__num"><?= (int) $quizCounts['published'] ?></span>
                                <span class="qg-stat__lbl">Published</span>
                            </div>
                            <div class="qg-stat qg-stat--closed">
                                <span class="qg-stat__num"><?= (int) $quizCounts['closed'] ?></span>
                                <span class="qg-stat__lbl">Closed</span>
                            </div>
                            <div class="qg-stat qg-stat--grade">
                                <span class="qg-stat__num"><?= (int) $quizToGrade ?></span>
                                <span class="qg-stat__lbl">To grade</span>
                            </div>
                        </div>
                    </section>

                    <!-- Needs attention -->
                    <section class="qg-panel">
                        <h3><i class="fas fa-bell"></i> Needs Attention</h3>
                        <?php if (empty($attentionItems)): ?>
                            <p class="qg-attn-empty"><i class="fas fa-circle-check"></i> You're all caught up — nothing to grade, no deadlines this week, no unfinished drafts.</p>
                        <?php else: ?>
                            <ul class="qg-recent-list">
                                <?php foreach ($attentionItems as $it): ?>
                                    <li>
                                        <a class="qg-recent-item" href="<?= htmlspecialchars($it['url']) ?>">
                                            <span class="qg-recent-icon qg-attn-icon--<?= htmlspecialchars($it['kind']) ?>"><i class="fas <?= htmlspecialchars($it['icon']) ?>"></i></span>
                                            <span class="qg-recent-body">
                                                <span class="qg-recent-title"><?= htmlspecialchars($it['title']) ?></span>
                                                <span class="qg-recent-sub"><?= htmlspecialchars($it['subject']) ?> · <strong class="qg-attn-label qg-attn-label--<?= htmlspecialchars($it['kind']) ?>"><?= htmlspecialchars($it['label']) ?></strong></span>
                                            </span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>

                    <section class="qg-panel">
                        <h3><i class="fas fa-clock-rotate-left"></i> Recent Quizzes</h3>

                        <?php if (empty($recentQuizzes)): ?>
                            <div class="panel-empty">
                                <i class="fas fa-file-circle-question"></i>
                                <p>No quizzes yet.</p>
                                <span>Quizzes you generate or create will show up here.</span>
                            </div>
                        <?php else: ?>
                            <ul class="qg-recent-list">
                                <?php foreach ($recentQuizzes as $rq): ?>
                                    <?php
                                    $rqUrl = 'class_overview.php?' . http_build_query([
                                        'subject_id' => (int) $rq['subject_id'],
                                        'section_id' => (int) $rq['section_id'],
                                        'view'       => 'quizzes',
                                        'term'       => $rq['quarter'],
                                        'quiz_id'    => (int) $rq['quiz_id'],
                                    ]);
                                    $rqStatus = $rq['status'];
                                    $rqIcon = $rqStatus === 'published' ? 'fa-circle-check' : ($rqStatus === 'closed' ? 'fa-lock' : 'fa-pen-ruler');
                                    $rqTerm = $termLabelMap[$rq['quarter']] ?? $rq['quarter'];
                                    ?>
                                    <li>
                                        <a class="qg-recent-item" href="<?= htmlspecialchars($rqUrl) ?>"
                                           title="Created <?= date('M j, Y', strtotime($rq['created_at'])) ?> · open in Class Overview">
                                            <span class="qg-recent-icon qg-recent-icon--<?= htmlspecialchars($rqStatus) ?>"><i class="fas <?= $rqIcon ?>"></i></span>
                                            <span class="qg-recent-body">
                                                <span class="qg-recent-title"><?= htmlspecialchars($rq['title']) ?></span>
                                                <span class="qg-recent-sub">
                                                    <?= htmlspecialchars($rq['subject_name']) ?> · Grade <?= (int) $rq['grade_level'] ?> <?= htmlspecialchars($rq['section_name']) ?> · <?= htmlspecialchars($rqTerm) ?>
                                                </span>
                                            </span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                </div>

            </div>

        <?php endif; ?>

    </main>

    <script type="application/json" id="qgPdfData"><?= json_encode($pdfMaterialsByOffering ?: new stdClass(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
    <script src="assets/js/quiz_generator.js"></script>
    <script src="assets/js/quiz_class_picker.js"></script>
</body>

</html>