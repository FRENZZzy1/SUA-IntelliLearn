<?php
require_once __DIR__ . '/../../config/config.php';

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../../login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];
$quizId = filter_input(INPUT_GET, 'quiz_id', FILTER_VALIDATE_INT);

if (!$quizId) {
    die("Invalid Quiz ID.");
}

// 1. Get Student ID
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$userId]);
$studentId = $stmt->fetchColumn();

if (!$studentId) {
    die("Student record not found.");
}

// 2. Fetch Quiz Details
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE quiz_id = ? AND status = 'published'");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    die("Quiz not found or is not available.");
}

// 3. Count attempts
$stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
$stmt->execute([$quizId, $studentId]);
$attemptCount = (int)$stmt->fetchColumn();

if ($attemptCount >= $quiz['max_attempts']) {
    die("You have reached the maximum allowed attempts for this quiz.");
}

// 4. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = $_POST['answers'] ?? [];

    $stmt = $pdo->prepare("SELECT question_id, question_type, points FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index ASC");
    $stmt->execute([$quizId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalScore = 0.00;
    $maxScore = 0.00;

    $pdo->beginTransaction();
    try {
        $stmtAttempt = $pdo->prepare("
            INSERT INTO quiz_attempts (quiz_id, student_id, attempt_number, status, started_at, submitted_at)
            VALUES (?, ?, ?, 'submitted', NOW(), NOW())
        ");
        $stmtAttempt->execute([$quizId, $studentId, $attemptCount + 1]);
        $attemptId = $pdo->lastInsertId();

        foreach ($questions as $q) {
            $qId = (int)$q['question_id'];
            $qPoints = (float)$q['points'];
            $maxScore += $qPoints;

            $selectedChoiceId = null;
            $answerText = null;
            $isCorrect = 0;
            $pointsAwarded = 0.00;

            if ($q['question_type'] === 'mcq' || $q['question_type'] === 'true_false') {
                $selectedChoiceId = isset($answers[$qId]) ? (int)$answers[$qId] : null;

                if ($selectedChoiceId) {
                    $chk = $pdo->prepare("SELECT is_correct FROM quiz_choices WHERE choice_id = ? AND question_id = ?");
                    $chk->execute([$selectedChoiceId, $qId]);
                    $correctFlag = $chk->fetchColumn();

                    if ($correctFlag == 1) {
                        $isCorrect = 1;
                        $pointsAwarded = $qPoints;
                    }
                }
            } else if ($q['question_type'] === 'short_answer') {
                $answerText = trim($answers[$qId] ?? '');
                $isCorrect = null;
                $pointsAwarded = 0.00;
            }

            $totalScore += $pointsAwarded;

            $stmtAns = $pdo->prepare("
                INSERT INTO quiz_answers (attempt_id, question_id, selected_choice_id, answer_text, is_correct, points_awarded)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtAns->execute([$attemptId, $qId, $selectedChoiceId, $answerText, $isCorrect, $pointsAwarded]);
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE quiz_attempts 
            SET score = ?, max_score = ?, status = 'graded' 
            WHERE attempt_id = ?
        ");
        $stmtUpdate->execute([$totalScore, $maxScore, $attemptId]);

        $pdo->commit();
        header("Location: course_view.php?offering_id={$quiz['offering_id']}&view=quizzes");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error saving submission: " . $e->getMessage());
    }
}

// 5. Fetch questions and choices for rendering form
$stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index ASC");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all choices in one query for efficiency
$choiceMap = [];
if (!empty($questions)) {
    $qIds = array_column($questions, 'question_id');
    $placeholders = implode(',', array_fill(0, count($qIds), '?'));
    $cStmt = $pdo->prepare("SELECT * FROM quiz_choices WHERE question_id IN ($placeholders) ORDER BY question_id, order_index ASC");
    $cStmt->execute($qIds);
    $allChoices = $cStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allChoices as $c) {
        $choiceMap[(int)$c['question_id']][] = $c;
    }
}

$totalQuestions = count($questions);
$totalPoints = array_sum(array_column($questions, 'points'));
$timeLimit = (int) ($quiz['time_limit'] ?? 0); // minutes, 0 = no limit
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($quiz['title']) ?> · Take Quiz</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/take_quiz.css">
</head>
<body>

<!-- Sticky Header -->
<header class="quiz-header" id="quizHeader">
    <div class="quiz-header__inner">
        <div class="quiz-header__info">
            <h1><?= htmlspecialchars($quiz['title']) ?></h1>
            <p>
                <?= $totalQuestions ?> question<?= $totalQuestions !== 1 ? 's' : '' ?>
                · <?= $totalPoints ?> pts
                <?= $timeLimit > 0 ? ' · ' . $timeLimit . ' min' : '' ?>
                · Attempt <?= $attemptCount + 1 ?> of <?= (int)$quiz['max_attempts'] ?>
            </p>
        </div>
        <div class="quiz-header__badges">
            <?php if ($timeLimit > 0): ?>
            <div class="badge badge--timer" id="timerBadge">
                <i class="fas fa-clock"></i>
                <span id="timerDisplay">--:--</span>
            </div>
            <?php endif; ?>
            <div class="badge badge--progress">
                <i class="fas fa-check-circle"></i>
                <span id="answeredCount">0</span>/<?= $totalQuestions ?>
            </div>
        </div>
    </div>
    <div class="quiz-progress__bar">
        <div class="quiz-progress__fill" id="progressFill" style="width: 0%"></div>
    </div>
</header>

<!-- Main Content -->
<main class="quiz-main">
    <form method="POST" id="quizForm" class="quiz-form">

        <!-- Question Navigator -->
        <?php if ($totalQuestions > 1): ?>
        <nav class="question-nav" aria-label="Question navigation">
            <?php foreach ($questions as $idx => $q): ?>
                <a href="#q<?= (int)$q['question_id'] ?>" class="q-nav-dot" id="navDot<?= (int)$q['question_id'] ?>" data-qid="<?= (int)$q['question_id'] ?>" title="Go to Question <?= $idx + 1 ?>">
                    <?= $idx + 1 ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <!-- Quiz Description -->
        <?php if (!empty($quiz['description'])): ?>
        <div class="quiz-description">
            <?= nl2br(htmlspecialchars($quiz['description'])) ?>
        </div>
        <?php endif; ?>

        <!-- Questions -->
        <?php foreach ($questions as $idx => $q):
            $qId = (int)$q['question_id'];
            $qType = $q['question_type'];
            $qChoices = $choiceMap[$qId] ?? [];
            $typeLabel = match($qType) {
                'mcq' => 'Multiple Choice',
                'true_false' => 'True / False',
                'short_answer' => 'Short Answer',
                default => ucfirst(str_replace('_', ' ', $qType))
            };
            $accentHue = ($idx * 47) % 360;
            $accent = "hsl({$accentHue}, 70%, 45%)";
            $accentLight = "hsl({$accentHue}, 70%, 95%)";
        ?>
        <article class="question-card" id="q<?= $qId ?>" style="--q-accent: <?= $accent ?>; --q-accent-light: <?= $accentLight ?>;" data-qid="<?= $qId ?>">
            <div class="question-card__accent"></div>

            <div class="question-card__meta">
                <span class="question-number">Question <?= $idx + 1 ?></span>
                <span class="question-points"><?= (float)$q['points'] ?> pts</span>
                <span class="question-type"><?= $typeLabel ?></span>
            </div>

            <p class="question-text"><?= htmlspecialchars($q['question_text']) ?></p>

            <?php if ($qType === 'mcq' || $qType === 'true_false'): ?>
                <div class="choices-list">
                    <?php foreach ($qChoices as $choice): ?>
                    <label class="choice-label" data-choice-id="<?= (int)$choice['choice_id'] ?>">
                        <input type="radio" name="answers[<?= $qId ?>]" value="<?= (int)$choice['choice_id'] ?>" required data-qid="<?= $qId ?>">
                        <span class="choice-radio">
                            <span class="choice-radio__inner"></span>
                        </span>
                        <span class="choice-text"><?= htmlspecialchars($choice['choice_text']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="answer-area">
                    <textarea
                        name="answers[<?= $qId ?>]"
                        rows="5"
                        placeholder="Type your answer here..."
                        required
                        data-qid="<?= $qId ?>"
                        class="answer-textarea"
                        maxlength="5000"
                    ></textarea>
                    <div class="answer-counter"><span class="word-count">0</span> words</div>
                </div>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>

        <!-- Sticky Submit Bar -->
        <div class="submit-bar">
            <div class="submit-bar__info">
                <span class="submit-bar__status">
                    <strong id="submitAnswered">0</strong> of <strong><?= $totalQuestions ?></strong> answered
                </span>
                <?php if ($timeLimit > 0): ?>
                <span class="submit-bar__timer" id="submitTimer">Time remaining: <strong>--:--</strong></span>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn-submit" id="btnSubmit">
                <i class="fas fa-paper-plane"></i> Submit Quiz
            </button>
        </div>

    </form>
</main>

<!-- Confirmation Modal -->
<div class="modal-overlay" id="confirmModal" hidden>
    <div class="modal">
        <div class="modal__icon"><i class="fas fa-clipboard-check"></i></div>
        <h3 class="modal__title">Ready to submit?</h3>
        <p class="modal__text">You have answered <strong id="modalAnswered">0</strong> of <strong><?= $totalQuestions ?></strong> questions.</p>
        <p class="modal__hint" id="modalHint"> unanswered questions will be marked as skipped.</p>
        <div class="modal__actions">
            <button type="button" class="btn-secondary" id="btnCancelSubmit">Keep Working</button>
            <button type="button" class="btn-primary" id="btnConfirmSubmit">Yes, Submit</button>
        </div>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('quizForm');
    const btnSubmit = document.getElementById('btnSubmit');
    const modal = document.getElementById('confirmModal');
    const btnCancel = document.getElementById('btnCancelSubmit');
    const btnConfirm = document.getElementById('btnConfirmSubmit');
    const progressFill = document.getElementById('progressFill');
    const answeredCount = document.getElementById('answeredCount');
    const submitAnswered = document.getElementById('submitAnswered');
    const modalAnswered = document.getElementById('modalAnswered');
    const modalHint = document.getElementById('modalHint');
    const totalQuestions = <?= $totalQuestions ?>;

    // Track answered questions
    function updateProgress() {
        let answered = 0;
        document.querySelectorAll('.question-card').forEach(card => {
            const qid = card.dataset.qid;
            const inputs = card.querySelectorAll('input[type="radio"], textarea');
            let isAnswered = false;
            inputs.forEach(inp => {
                if (inp.type === 'radio' && inp.checked) isAnswered = true;
                if (inp.tagName === 'TEXTAREA' && inp.value.trim().length > 0) isAnswered = true;
            });

            const dot = document.getElementById('navDot' + qid);
            if (dot) {
                dot.classList.toggle('answered', isAnswered);
            }
            if (isAnswered) answered++;
        });

        const pct = totalQuestions > 0 ? (answered / totalQuestions) * 100 : 0;
        progressFill.style.width = pct + '%';
        answeredCount.textContent = answered;
        submitAnswered.textContent = answered;
        modalAnswered.textContent = answered;

        const unanswered = totalQuestions - answered;
        modalHint.textContent = unanswered > 0
            ? unanswered + ' unanswered question' + (unanswered !== 1 ? 's' : '') + ' will be marked as skipped.'
            : 'All questions answered. Good luck!';
    }

    // Listen for changes
    form.addEventListener('change', updateProgress);
    form.addEventListener('input', updateProgress);

    // Word counter for textareas
    document.querySelectorAll('.answer-textarea').forEach(ta => {
        const counter = ta.closest('.answer-area').querySelector('.word-count');
        ta.addEventListener('input', () => {
            const words = ta.value.trim().split(/\s+/).filter(w => w.length > 0).length;
            counter.textContent = words;
        });
    });

    // Custom radio selection styling
    document.querySelectorAll('.choices-list').forEach(list => {
        list.addEventListener('change', (e) => {
            if (e.target.type === 'radio') {
                list.querySelectorAll('.choice-label').forEach(lbl => lbl.classList.remove('selected'));
                e.target.closest('.choice-label').classList.add('selected');
            }
        });
    });

    // Modal logic
    btnSubmit.addEventListener('click', (e) => {
        e.preventDefault();
        updateProgress();
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    });

    btnCancel.addEventListener('click', () => {
        modal.hidden = true;
        document.body.style.overflow = '';
    });

    btnConfirm.addEventListener('click', () => {
        modal.hidden = true;
        form.submit();
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.hidden = true;
            document.body.style.overflow = '';
        }
    });

    // Timer logic
    <?php if ($timeLimit > 0): ?>
    const timeLimitSec = <?= $timeLimit * 60 ?>;
    let remaining = timeLimitSec;
    const timerDisplay = document.getElementById('timerDisplay');
    const submitTimer = document.getElementById('submitTimer');
    const timerBadge = document.getElementById('timerBadge');

    function formatTime(sec) {
        const m = Math.floor(sec / 60).toString().padStart(2, '0');
        const s = (sec % 60).toString().padStart(2, '0');
        return m + ':' + s;
    }

    function updateTimer() {
        timerDisplay.textContent = formatTime(remaining);
        if (submitTimer) submitTimer.innerHTML = 'Time remaining: <strong>' + formatTime(remaining) + '</strong>';

        if (remaining <= 60) {
            timerBadge.classList.add('badge--urgent');
        }
        if (remaining <= 0) {
            clearInterval(timerInterval);
            form.submit();
            return;
        }
        remaining--;
    }

    updateTimer();
    const timerInterval = setInterval(updateTimer, 1000);
    <?php endif; ?>

    // Smooth scroll for nav dots
    document.querySelectorAll('.q-nav-dot').forEach(dot => {
        dot.addEventListener('click', (e) => {
            e.preventDefault();
            const target = document.querySelector(dot.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                target.classList.add('highlight');
                setTimeout(() => target.classList.remove('highlight'), 1200);
            }
        });
    });

    // Highlight current question on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const qid = entry.target.dataset.qid;
                document.querySelectorAll('.q-nav-dot').forEach(d => d.classList.remove('current'));
                const dot = document.getElementById('navDot' + qid);
                if (dot) dot.classList.add('current');
            }
        });
    }, { rootMargin: '-20% 0px -60% 0px' });

    document.querySelectorAll('.question-card').forEach(card => observer.observe(card));

    // Prevent accidental leave
    let formDirty = false;
    form.addEventListener('input', () => { formDirty = true; });
    form.addEventListener('change', () => { formDirty = true; });
    window.addEventListener('beforeunload', (e) => {
        if (formDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    form.addEventListener('submit', () => { formDirty = false; });

    // Initial state
    updateProgress();
})();
</script>

</body>
</html>