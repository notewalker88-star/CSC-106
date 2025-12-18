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
$quiz = new Quiz();

// Get parameters
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;

// Get quiz data
if ($quiz_id) {
    $quiz_data = $quiz->getQuizById($quiz_id);
} elseif ($attempt_id) {
    // Get quiz data from attempt
    $database = new Database();
    $conn = $database->getConnection();

    $query = "SELECT qa.*, q.*, c.title as course_title, l.title as lesson_title
              FROM quiz_attempts qa
              JOIN quizzes q ON qa.quiz_id = q.id
              JOIN courses c ON q.course_id = c.id
              LEFT JOIN lessons l ON q.lesson_id = l.id
              WHERE qa.id = :attempt_id AND qa.student_id = :student_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':attempt_id', $attempt_id);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();

    $attempt_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($attempt_data) {
        $quiz_data = $attempt_data;
        $quiz_id = $attempt_data['quiz_id'];
    }
}

if (!$quiz_data) {
    header('Location: ' . SITE_URL . '/student/courses.php?error=quiz_not_found');
    exit();
}

// Handle actions (delete functionality removed for security reasons)
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'mark_completed':
            if ($quiz->markQuizAsCompleted($quiz_id, $student_id)) {
                header('Location: ' . SITE_URL . '/student/quiz-results.php?quiz_id=' . $quiz_id . '&success=marked_completed');
                exit();
            } else {
                $error_message = 'Failed to mark quiz as completed.';
            }
            break;

        case 'mark_incomplete':
            if ($quiz->updateQuizProgress($quiz_id, $student_id, $quiz_data['course_id'], false)) {
                header('Location: ' . SITE_URL . '/student/quiz-results.php?quiz_id=' . $quiz_id . '&success=marked_incomplete');
                exit();
            } else {
                $error_message = 'Failed to update quiz progress.';
            }
            break;
    }
}

// Get student's attempts (only completed ones)
$all_attempts = $quiz->getStudentAttempts($quiz_id, $student_id);
$student_attempts = array_filter($all_attempts, function($attempt) {
    return !empty($attempt['completed_at']);
});

// Get specific attempt if provided
$current_attempt = null;
if ($attempt_id) {
    foreach ($student_attempts as $attempt) {
        if ($attempt['id'] == $attempt_id) {
            $current_attempt = $attempt;
            break;
        }
    }
}

// Get student answers for detailed review
$student_answers = [];
if ($current_attempt && $current_attempt['answers']) {
    $student_answers = json_decode($current_attempt['answers'], true);
}

// Get quiz questions for detailed results
$quiz_questions = $quiz->getQuizQuestions($quiz_id);

// Check if student can take another attempt (always true with unlimited attempts)
$can_retake = $quiz->canStudentTakeQuiz($quiz_id, $student_id);

// Get quiz completion status
$is_quiz_completed = $quiz->isQuizCompleted($quiz_id, $student_id);
$quiz_progress = $quiz->getQuizProgress($quiz_id, $student_id);

// Success message
$success_message = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'submitted':
            $success_message = 'Quiz submitted successfully!';
            break;
        // Delete success messages removed for security reasons
        case 'marked_completed':
            $success_message = 'Quiz marked as completed successfully!';
            break;
        case 'marked_incomplete':
            $success_message = 'Quiz progress updated successfully!';
            break;
    }
}

// Error message
$error_message = '';
if (isset($_GET['error']) && $_GET['error'] == 'quiz_inactive') {
    $error_message = 'This quiz is currently inactive.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results: <?php echo htmlspecialchars($quiz_data['title']); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .results-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto;
        }
        .score-passed {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        .score-failed {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            color: white;
        }
        .attempt-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .attempt-card:hover {
            transform: translateY(-2px);
        }
        .attempt-card.active {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .question-review {
            transition: all 0.3s ease;
        }
        .bg-light-success {
            background-color: rgba(40, 167, 69, 0.1) !important;
        }
        .bg-light-danger {
            background-color: rgba(220, 53, 69, 0.1) !important;
        }
        .answer-box {
            border: 2px solid rgba(255,255,255,0.3);
        }
        .question-review:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="courses.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to My Courses
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="results-container">
            <!-- Quiz Header -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i><?php echo htmlspecialchars($quiz_data['title']); ?> - Results
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

                            <!-- Quiz Completion Status -->
                            <div class="mt-3">
                                <?php if ($is_quiz_completed): ?>
                                    <span class="badge bg-success fs-6">
                                        <i class="fas fa-check-circle me-1"></i>Completed
                                    </span>
                                    <?php if ($quiz_progress && $quiz_progress['completion_date']): ?>
                                        <small class="text-muted d-block mt-1">
                                            Completed on <?php echo date('M j, Y g:i A', strtotime($quiz_progress['completion_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-warning fs-6">
                                        <i class="fas fa-clock me-1"></i>In Progress
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="mb-2">
                                <strong>Total Attempts:</strong> <?php echo count($student_attempts); ?>
                            </div>
                            <div class="mb-2">
                                <strong>Passing Score:</strong> <?php echo $quiz_data['passing_score']; ?>%
                            </div>
                            <div class="mb-2">
                                <strong>Attempts Allowed:</strong> 
                                <?php 
                                if ($quiz_data['max_attempts'] >= 999) {
                                    echo 'Unlimited';
                                } else {
                                    echo $quiz_data['max_attempts'];
                                }
                                ?>
                            </div>
                            <div class="d-grid gap-2">
                                <!-- Mark as Done Button -->
                                <?php if ($is_quiz_completed): ?>
                                    <a href="?quiz_id=<?php echo $quiz_id; ?>&action=mark_incomplete"
                                       class="btn btn-outline-warning"
                                       onclick="return confirm('Are you sure you want to mark this quiz as incomplete?')">
                                        <i class="fas fa-undo me-1"></i>Mark as Incomplete
                                    </a>
                                <?php else: ?>
                                    <a href="?quiz_id=<?php echo $quiz_id; ?>&action=mark_completed"
                                       class="btn btn-success">
                                        <i class="fas fa-check me-1"></i>Mark as Done
                                    </a>
                                <?php endif; ?>

                                <a href="take-quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-redo me-1"></i>Take Again
                                </a>
                                <!-- Delete functionality removed for security reasons -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Flash Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Current Attempt Results -->
            <?php if ($current_attempt): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy me-2"></i>Attempt <?php echo $current_attempt['attempt_number']; ?> Results
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="score-circle <?php echo $current_attempt['is_passed'] ? 'score-passed' : 'score-failed'; ?>">
                                    <?php echo number_format($current_attempt['score'], 1); ?>%
                                </div>
                                <h5 class="mt-3 <?php echo $current_attempt['is_passed'] ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $current_attempt['is_passed'] ? 'PASSED' : 'FAILED'; ?>
                                </h5>
                            </div>
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3 text-center">
                                            <h4 class="text-primary mb-1"><?php echo $current_attempt['correct_answers']; ?></h4>
                                            <small class="text-muted">Correct Answers</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3 text-center">
                                            <h4 class="text-info mb-1"><?php echo $current_attempt['total_questions']; ?></h4>
                                            <small class="text-muted">Total Questions</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3 text-center">
                                            <h4 class="text-warning mb-1"><?php echo gmdate("i:s", $current_attempt['time_taken']); ?></h4>
                                            <small class="text-muted">Time Taken</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3 text-center">
                                            <h4 class="text-secondary mb-1"><?php echo date('M j, Y', strtotime($current_attempt['completed_at'])); ?></h4>
                                            <small class="text-muted">Completed</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Detailed Answer Review -->
            <?php if ($current_attempt && !empty($student_answers)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-check me-2"></i>Answer Review - Attempt <?php echo $current_attempt['attempt_number']; ?>
                        </h5>
                        <small class="text-white-50">Compare your answers with the correct answers below</small>
                    </div>
                    <div class="card-body">
                        <!-- Quick Summary -->
                        <div class="alert alert-info mb-4">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <h5 class="text-primary"><?php echo $current_attempt['correct_answers']; ?></h5>
                                    <small>Correct Answers</small>
                                </div>
                                <div class="col-md-3">
                                    <h5 class="text-danger"><?php echo ($current_attempt['total_questions'] - $current_attempt['correct_answers']); ?></h5>
                                    <small>Incorrect Answers</small>
                                </div>
                                <div class="col-md-3">
                                    <h5 class="text-success"><?php echo number_format($current_attempt['score'], 1); ?>%</h5>
                                    <small>Final Score</small>
                                </div>
                                <div class="col-md-3">
                                    <h5 class="<?php echo $current_attempt['is_passed'] ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $current_attempt['is_passed'] ? 'PASSED' : 'FAILED'; ?>
                                    </h5>
                                    <small>Result</small>
                                </div>
                            </div>
                        </div>

                        <?php foreach ($quiz_questions as $index => $question): ?>
                            <?php
                            $student_answer = isset($student_answers[$question['id']]) ? $student_answers[$question['id']] : '';
                            $is_correct = false;

                            // Check if answer is correct
                            $is_correct = false;
                            $correct_answer = trim($question['correct_answer']);
                            $student_answer_trimmed = trim($student_answer);

                            if ($question['question_type'] === 'true_false') {
                                $is_correct = strtolower($student_answer_trimmed) === strtolower($correct_answer);
                            } elseif ($question['question_type'] === 'multiple_choice') {
                                // For multiple choice, check both exact match and option letter match
                                if ($student_answer_trimmed === $correct_answer) {
                                    $is_correct = true;
                                } elseif ($question['options'] && in_array(strtoupper($correct_answer), ['A', 'B', 'C', 'D'])) {
                                    // If correct answer is a letter (A, B, C, D), check against options
                                    $options = json_decode($question['options'], true);
                                    if (is_array($options)) {
                                        $letter_index = ord(strtoupper($correct_answer)) - ord('A');
                                        if (isset($options[$letter_index])) {
                                            $is_correct = $student_answer_trimmed === $options[$letter_index];
                                        }
                                    }
                                } elseif (in_array(strtoupper($student_answer_trimmed), ['A', 'B', 'C', 'D']) && $question['options']) {
                                    // If student answer is a letter, check against options
                                    $options = json_decode($question['options'], true);
                                    if (is_array($options)) {
                                        $letter_index = ord(strtoupper($student_answer_trimmed)) - ord('A');
                                        if (isset($options[$letter_index])) {
                                            $is_correct = $options[$letter_index] === $correct_answer;
                                        }
                                    }
                                }
                            } else {
                                $is_correct = $student_answer_trimmed === $correct_answer;
                            }
                            ?>

                            <div class="question-review mb-4 p-3 border rounded <?php echo $is_correct ? 'border-success bg-light-success' : 'border-danger bg-light-danger'; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="mb-0">
                                        Question <?php echo $index + 1; ?>
                                        <span class="badge <?php echo $is_correct ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $is_correct ? 'Correct' : 'Incorrect'; ?>
                                        </span>
                                        <span class="badge bg-secondary"><?php echo $question['points']; ?> point<?php echo $question['points'] > 1 ? 's' : ''; ?></span>
                                    </h6>
                                    <div class="text-end">
                                        <?php if ($is_correct): ?>
                                            <i class="fas fa-check-circle text-success fa-2x"></i>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle text-danger fa-2x"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <p class="fw-bold mb-3"><?php echo htmlspecialchars($question['question']); ?></p>

                                <!-- Show options for multiple choice -->
                                <?php if ($question['question_type'] == 'multiple_choice' && $question['options']): ?>
                                    <div class="mb-3">
                                        <strong>Options:</strong>
                                        <ul class="list-unstyled ms-3 mt-2">
                                            <?php
                                            $options = json_decode($question['options'], true);
                                            foreach ($options as $i => $option):
                                            ?>
                                                <li class="mb-1 p-2 rounded <?php
                                                    if ($option == $question['correct_answer']) {
                                                        echo 'bg-success text-white';
                                                    } elseif ($option == $student_answer && !$is_correct) {
                                                        echo 'bg-danger text-white';
                                                    } else {
                                                        echo 'bg-light';
                                                    }
                                                ?>">
                                                    <?php echo chr(65 + $i) . '. ' . htmlspecialchars($option); ?>
                                                    <?php if ($option == $question['correct_answer']): ?>
                                                        <i class="fas fa-check ms-2"></i>
                                                    <?php elseif ($option == $student_answer && !$is_correct): ?>
                                                        <i class="fas fa-times ms-2"></i>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="answer-box p-3 rounded <?php echo $is_correct ? 'bg-success' : 'bg-danger'; ?> text-white mb-2">
                                            <strong>Your Answer:</strong><br>
                                            <?php echo $student_answer ? htmlspecialchars($student_answer) : '<em>No answer provided</em>'; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="answer-box p-3 rounded bg-success text-white mb-2">
                                            <strong>Correct Answer:</strong><br>
                                            <?php echo htmlspecialchars($question['correct_answer']); ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($question['explanation']): ?>
                                    <div class="mt-3 p-3 bg-info text-white rounded">
                                        <strong><i class="fas fa-lightbulb me-2"></i>Explanation:</strong><br>
                                        <?php echo htmlspecialchars($question['explanation']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- All Attempts -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>All Attempts
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($student_attempts)): ?>
                        <div class="row">
                            <?php foreach ($student_attempts as $attempt): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card attempt-card <?php echo ($current_attempt && $current_attempt['id'] == $attempt['id']) ? 'active' : ''; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="flex-grow-1" style="cursor: pointer;" onclick="window.location.href='?attempt_id=<?php echo $attempt['id']; ?>'">
                                                    <h6 class="mb-1">Attempt <?php echo $attempt['attempt_number']; ?></h6>
                                                    <p class="mb-1">
                                                        <span class="badge <?php echo $attempt['is_passed'] ? 'bg-success' : 'bg-danger'; ?>">
                                                            <?php echo number_format($attempt['score'], 1); ?>%
                                                        </span>
                                                        <span class="text-muted ms-2">
                                                            <?php echo $attempt['correct_answers']; ?>/<?php echo $attempt['total_questions']; ?> correct
                                                        </span>
                                                    </p>
                                                    <small class="text-muted">
                                                        <?php echo !empty($attempt['completed_at']) ? date('M j, Y g:i A', strtotime($attempt['completed_at'])) : 'In Progress'; ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <div class="mb-2">
                                                        <i class="fas fa-<?php echo $attempt['is_passed'] ? 'check-circle text-success' : 'times-circle text-danger'; ?> fa-2x"></i>
                                                    </div>
                                                    <!-- Delete button removed for security reasons -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Best Score Summary -->
                        <?php
                        $best_attempt = null;
                        $best_score = -1;
                        foreach ($student_attempts as $attempt) {
                            if ($attempt['score'] > $best_score) {
                                $best_score = $attempt['score'];
                                $best_attempt = $attempt;
                            }
                        }
                        ?>

                        <?php if ($best_attempt): ?>
                            <div class="alert alert-info mt-3">
                                <h6 class="alert-heading">
                                    <i class="fas fa-star me-2"></i>Best Performance
                                </h6>
                                <p class="mb-0">
                                    Your best score is <strong><?php echo number_format($best_attempt['score'], 1); ?>%</strong>
                                    from Attempt <?php echo $best_attempt['attempt_number']; ?>
                                    <?php if ($best_attempt['is_passed']): ?>
                                        <span class="badge bg-success ms-2">PASSED</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Attempts Yet</h5>
                            <p class="text-muted mb-3">You haven't taken this quiz yet.</p>
                            <a href="take-quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-primary">
                                <i class="fas fa-play me-2"></i>Take Quiz Now
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="text-center">
                <a href="take-quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-primary me-2">
                    <i class="fas fa-redo me-2"></i>Take Again
                </a>

                <a href="courses.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to My Courses
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete functionality removed for security reasons
    </script>
</body>
</html>
