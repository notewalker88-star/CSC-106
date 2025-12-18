<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Quiz.php';
require_once __DIR__ . '/../classes/Course.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || !hasRole(ROLE_STUDENT)) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

if (!$quiz_id) {
    header('Location: ' . SITE_URL . '/student/courses.php');
    exit();
}

$quiz = new Quiz();
$course = new Course();

// Get quiz data
$quiz_data = $quiz->getQuizById($quiz_id);

if (!$quiz_data || !$quiz_data['is_active']) {
    header('Location: ' . SITE_URL . '/student/courses.php?error=quiz_not_found');
    exit();
}

// Check if student is enrolled in the course
$database = new Database();
$conn = $database->getConnection();

$query = "SELECT COUNT(*) FROM enrollments WHERE student_id = :student_id AND course_id = :course_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->bindParam(':course_id', $quiz_data['course_id']);
$stmt->execute();
$is_enrolled = $stmt->fetchColumn() > 0;

if (!$is_enrolled) {
    header('Location: ' . SITE_URL . '/student/courses.php?error=not_enrolled');
    exit();
}

// Check if student can take the quiz (always allowed now with unlimited attempts)
if (!$quiz->canStudentTakeQuiz($quiz_id, $student_id)) {
    header('Location: ' . SITE_URL . '/student/quiz-results.php?quiz_id=' . $quiz_id . '&error=quiz_inactive');
    exit();
}

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_quiz'])) {
    $attempt_id = (int)$_POST['attempt_id'];
    $answers = $_POST['answers'] ?? [];
    $time_taken = (int)$_POST['time_taken'];

    if ($quiz->submitQuizAttempt($attempt_id, $answers, $time_taken)) {
        header('Location: ' . SITE_URL . '/student/quiz-results.php?attempt_id=' . $attempt_id . '&success=submitted');
        exit();
    } else {
        $error_message = 'Failed to submit quiz. Please try again.';
    }
}

// Start new attempt if not already started
$attempt_id = null;
if (isset($_GET['attempt_id'])) {
    $attempt_id = (int)$_GET['attempt_id'];
} else {
    $attempt_id = $quiz->startQuizAttempt($quiz_id, $student_id);
    if ($attempt_id) {
        header('Location: ' . SITE_URL . '/student/take-quiz.php?quiz_id=' . $quiz_id . '&attempt_id=' . $attempt_id);
        exit();
    } else {
        $error_message = 'Failed to start quiz attempt.';
    }
}

// Get quiz questions
$quiz_questions = $quiz->getQuizQuestions($quiz_id);

// Get student's previous attempts
$student_attempts = $quiz->getStudentAttempts($quiz_id, $student_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Quiz: <?php echo htmlspecialchars($quiz_data['title']); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .quiz-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .question-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .progress-bar {
            height: 8px;
        }
        .option-label {
            cursor: pointer;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 5px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .option-label:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .option-label.selected {
            border-color: #007bff;
            background-color: #e3f2fd;
        }
    </style>
</head>
<body>
    <!-- Timer (if time limit is set) -->
    <?php if ($quiz_data['time_limit']): ?>
        <div class="timer">
            <div class="text-center">
                <i class="fas fa-clock text-warning"></i>
                <div class="fw-bold" id="timer-display"><?php echo $quiz_data['time_limit']; ?>:00</div>
                <small class="text-muted">Time Left</small>
            </div>
        </div>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white">
                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="quiz-container">
            <!-- Quiz Header -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-question-circle me-2"></i><?php echo htmlspecialchars($quiz_data['title']); ?>
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <?php if ($quiz_data['description']): ?>
                                <p class="mb-2"><?php echo htmlspecialchars($quiz_data['description']); ?></p>
                            <?php endif; ?>
                            <p class="text-muted mb-0">
                                <i class="fas fa-book me-1"></i>Course: <?php echo htmlspecialchars($quiz_data['course_title']); ?>
                            </p>
                            <?php if ($quiz_data['lesson_title']): ?>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-play-circle me-1"></i>Lesson: <?php echo htmlspecialchars($quiz_data['lesson_title']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="mb-2">
                                <strong>Questions:</strong> <?php echo count($quiz_questions); ?>
                            </div>
                            <div class="mb-2">
                                <strong>Passing Score:</strong> <?php echo $quiz_data['passing_score']; ?>%
                            </div>
                            <div class="mb-2">
                                <strong>Attempts:</strong> 
                                <?php 
                                if ($quiz_data['max_attempts'] >= 999) {
                                    echo 'Unlimited';
                                } else {
                                    echo $quiz_data['max_attempts'] . ' allowed';
                                }
                                ?>
                            </div>
                            <?php if ($quiz_data['time_limit']): ?>
                                <div class="mb-2">
                                    <strong>Time Limit:</strong> <?php echo $quiz_data['time_limit']; ?> minutes
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error Message -->
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Quiz Form -->
            <form method="POST" id="quizForm">
                <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                <input type="hidden" name="time_taken" id="timeTaken" value="0">

                <!-- Progress Bar -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Progress</span>
                            <span id="progress-text">0 of <?php echo count($quiz_questions); ?> answered</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" id="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <!-- Questions -->
                <?php foreach ($quiz_questions as $index => $question): ?>
                    <div class="card question-card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                Question <?php echo $index + 1; ?>
                                <span class="badge bg-secondary"><?php echo $question['points']; ?> point<?php echo $question['points'] > 1 ? 's' : ''; ?></span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="fw-bold mb-3"><?php echo htmlspecialchars($question['question']); ?></p>

                            <?php if ($question['question_type'] == 'multiple_choice'): ?>
                                <?php
                                $options = json_decode($question['options'], true);
                                foreach ($options as $i => $option):
                                ?>
                                    <label class="option-label d-block" onclick="selectOption(this, <?php echo $question['id']; ?>)">
                                        <input type="radio" name="answers[<?php echo $question['id']; ?>]"
                                               value="<?php echo htmlspecialchars($option); ?>"
                                               class="me-2" onchange="updateProgress()">
                                        <?php echo chr(65 + $i) . '. ' . htmlspecialchars($option); ?>
                                    </label>
                                <?php endforeach; ?>

                            <?php elseif ($question['question_type'] == 'true_false'): ?>
                                <label class="option-label d-block" onclick="selectOption(this, <?php echo $question['id']; ?>)">
                                    <input type="radio" name="answers[<?php echo $question['id']; ?>]"
                                           value="True" class="me-2" onchange="updateProgress()">
                                    True
                                </label>
                                <label class="option-label d-block" onclick="selectOption(this, <?php echo $question['id']; ?>)">
                                    <input type="radio" name="answers[<?php echo $question['id']; ?>]"
                                           value="False" class="me-2" onchange="updateProgress()">
                                    False
                                </label>

                            <?php else: // short_answer ?>
                                <textarea name="answers[<?php echo $question['id']; ?>]"
                                          class="form-control" rows="3"
                                          placeholder="Enter your answer here..."
                                          onchange="updateProgress()"></textarea>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Submit Button -->
                <div class="card">
                    <div class="card-body text-center">
                        <button type="button" class="btn btn-success btn-lg" onclick="submitQuiz()">
                            <i class="fas fa-paper-plane me-2"></i>Submit Quiz
                        </button>
                        <div class="mt-3">
                            <small class="text-muted">
                                Make sure you have answered all questions before submitting.
                                You cannot change your answers after submission.
                            </small>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let startTime = Date.now();
        let timeLimit = <?php echo $quiz_data['time_limit'] ? $quiz_data['time_limit'] * 60 : 0; ?>; // in seconds
        let totalQuestions = <?php echo count($quiz_questions); ?>;

        // Timer functionality
        <?php if ($quiz_data['time_limit']): ?>
        let timeLeft = timeLimit;
        let timerInterval = setInterval(function() {
            timeLeft--;

            let minutes = Math.floor(timeLeft / 60);
            let seconds = timeLeft % 60;

            document.getElementById('timer-display').textContent =
                minutes + ':' + (seconds < 10 ? '0' : '') + seconds;

            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                alert('Time is up! The quiz will be submitted automatically.');
                submitQuiz();
            }
        }, 1000);
        <?php endif; ?>

        function selectOption(label, questionId) {
            // Remove selected class from all options for this question
            let questionCard = label.closest('.question-card');
            questionCard.querySelectorAll('.option-label').forEach(opt => {
                opt.classList.remove('selected');
            });

            // Add selected class to clicked option
            label.classList.add('selected');
        }

        function updateProgress() {
            let answered = 0;
            let formData = new FormData(document.getElementById('quizForm'));

            for (let i = 1; i <= totalQuestions; i++) {
                if (formData.has('answers[' + i + ']') && formData.get('answers[' + i + ']').trim() !== '') {
                    answered++;
                }
            }

            let percentage = (answered / totalQuestions) * 100;
            document.getElementById('progress-bar').style.width = percentage + '%';
            document.getElementById('progress-text').textContent = answered + ' of ' + totalQuestions + ' answered';
        }

        function submitQuiz() {
            if (confirm('Are you sure you want to submit the quiz? You cannot change your answers after submission.')) {
                // Calculate time taken
                let timeTaken = Math.floor((Date.now() - startTime) / 1000);
                document.getElementById('timeTaken').value = timeTaken;

                // Add submit flag
                let submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'submit_quiz';
                submitInput.value = '1';
                document.getElementById('quizForm').appendChild(submitInput);

                document.getElementById('quizForm').submit();
            }
        }

        // Prevent accidental page refresh
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = '';
        });

        // Initialize progress
        updateProgress();
    </script>
</body>
</html>
