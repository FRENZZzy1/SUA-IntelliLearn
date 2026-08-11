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
            <p class="qg-subtitle">Describe a topic and let AI draft the quiz — review and edit everything before it
                goes live.</p>
        </div>

        <?php if (empty($teacherOfferings)): ?>
            <div class="qg-panel">
                <div class="panel-empty">
                    <i class="fas fa-chalkboard"></i>
                    <p>No active classes assigned yet.</p>
                    <span>You'll be able to generate quizzes once the admin assigns you a class.</span>
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
                                    <label for="qgOffering">Subject / Class</label>
                                    <select id="qgOffering" name="offering_id" required>
                                        <option value="">Select a class…</option>
                                        <?php foreach ($teacherOfferings as $o): ?>
                                            <option value="<?= (int) $o['offering_id'] ?>">
                                                <?= htmlspecialchars($o['subject_name']) ?> — Grade
                                                <?= (int) $o['grade_level'] ?>         <?= htmlspecialchars($o['section_name']) ?>
                                                (<?= htmlspecialchars($o['quarter']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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
                                    </select>
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
                                    <label for="qgTopic">Topic <span class="hint">— describe what the quiz should
                                            cover</span></label>
                                    <textarea id="qgTopic" name="topic" required
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
                            <span>Talking to the AI model — this can take up to 30 seconds…</span>
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

                <!-- ==================== Sidebar column ==================== -->
                <div>
                    <section class="qg-panel">
                        <h3><i class="fas fa-clock-rotate-left"></i> Recent Quizzes</h3>

                        <?php if (empty($recentQuizzes)): ?>
                            <div class="panel-empty">
                                <i class="fas fa-file-circle-question"></i>
                                <p>No quizzes yet.</p>
                                <span>Quizzes you generate or create will show up here.</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentQuizzes as $rq): ?>
                                <div class="qg-recent-item">
                                    <span class="qg-recent-title"><?= htmlspecialchars($rq['title']) ?></span>
                                    <span class="qg-recent-meta">
                                        <?= htmlspecialchars($rq['subject_name']) ?> · <?= htmlspecialchars($rq['section_name']) ?>
                                        · <?= (int) $rq['question_count'] ?> items
                                        <span
                                            class="qg-status-tag <?= htmlspecialchars($rq['status']) ?>"><?= htmlspecialchars($rq['status']) ?></span>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                </div>

            </div>

        <?php endif; ?>

    </main>

    <script src="assets/js/quiz_generator.js"></script>
</body>

</html>