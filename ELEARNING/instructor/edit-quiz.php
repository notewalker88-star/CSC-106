<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Quiz.php';
require_once __DIR__ . '/../classes/Course.php';
require_once __DIR__ . '/../classes/Lesson.php';

// Check if user is logged in and is an instructor
if (!isLoggedIn() || !hasRole(ROLE_INSTRUCTOR)) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$instructor_id = $_SESSION['user_id'];
$quiz = new Quiz();
$course = new Course();
$lesson = new Lesson();

// Get quiz ID
if (!isset($_GET['id'])) {
    header('Location: ' . SITE_URL . '/instructor/quizzes.php');
    exit();
}

$quiz_id = (int)$_GET['id'];
$quiz_data = $quiz->getQuizById($quiz_id);

if (!$quiz_data || $quiz_data['instructor_id'] != $instructor_id) {
    header('Location: ' . SITE_URL . '/instructor/quizzes.php?error=unauthorized');
    exit();
}

// Handle form submissions
$success_message = '';
$error_message = '';

// Handle quiz update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_quiz') {
    $quiz->id = $quiz_id;
    $quiz->course_id = $quiz_data['course_id']; // Keep original course
    $quiz->lesson_id = !empty($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : null;
    $quiz->title = trim($_POST['title']);
    $quiz->description = trim($_POST['description']);
    $quiz->time_limit = !empty($_POST['time_limit']) ? (int)$_POST['time_limit'] : null;
    $quiz->passing_score = (int)$_POST['passing_score'];
    $quiz->max_attempts = (int)$_POST['max_attempts'];
    $quiz->is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($quiz->title)) {
        $error_message = 'Please enter a quiz title.';
    } else {
        if ($quiz->update()) {
            $success_message = 'Quiz updated successfully!';
            // Refresh quiz data
            $quiz_data = $quiz->getQuizById($quiz_id);
        } else {
            $error_message = 'Failed to update quiz. Please try again.';
        }
    }
}

// Handle question addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_question') {
    $question = trim($_POST['question']);
    $question_type = $_POST['question_type'];
    $correct_answer = trim($_POST['correct_answer']);
    $points = (int)$_POST['points'];
    $explanation = trim($_POST['explanation']);

    // Handle options for multiple choice
    $options = null;
    if ($question_type == 'multiple_choice') {
        $option_array = [];
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($_POST["option_$i"])) {
                $option_array[] = trim($_POST["option_$i"]);
            }
        }
        $options = json_encode($option_array);
    }

    if (empty($question) || empty($correct_answer)) {
        $error_message = 'Please fill in all required fields for the question.';
    } else {
        if ($quiz->addQuestion($quiz_id, $question, $question_type, $options, $correct_answer, $points, $explanation)) {
            $success_message = 'Question added successfully!';
        } else {
            $error_message = 'Failed to add question. Please try again.';
        }
    }
}

// Handle question deletion
if (isset($_GET['delete_question'])) {
    $question_id = (int)$_GET['delete_question'];
    if ($quiz->deleteQuestion($question_id)) {
        header('Location: ' . SITE_URL . '/instructor/edit-quiz.php?id=' . $quiz_id . '&success=question_deleted');
        exit();
    } else {
        $error_message = 'Failed to delete question.';
    }
}

// Handle delete all attempts
if (isset($_GET['delete_all_attempts'])) {
    if ($quiz->deleteAllQuizAttempts($quiz_id)) {
        header('Location: ' . SITE_URL . '/instructor/edit-quiz.php?id=' . $quiz_id . '&success=attempts_deleted');
        exit();
    } else {
        $error_message = 'Failed to delete all attempts.';
    }
}

// Get course lessons
$course_lessons = $lesson->getLessonsByCourse($quiz_data['course_id'], false);

// Get quiz questions
$quiz_questions = $quiz->getQuizQuestions($quiz_id);

// Get quiz statistics
$quiz_stats = $quiz->getQuizStats($quiz_id);

// Check for success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $success_message = 'Quiz created successfully! Now add questions below.';
            break;
        case 'question_deleted':
            $success_message = 'Question deleted successfully!';
            break;
        case 'attempts_deleted':
            $success_message = 'All quiz attempts deleted successfully!';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        .question-card {
            transition: transform 0.2s;
        }
        .question-card:hover {
            transform: translateY(-2px);
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-edit me-2"></i>Edit Quiz: <?php echo htmlspecialchars($quiz_data['title']); ?></h1>
            <a href="courses.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Courses
            </a>
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

        <div class="row">
            <!-- Quiz Information -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quiz Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_quiz">

                            <div class="mb-3">
                                <label for="title" class="form-label">Quiz Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control"
                                       value="<?php echo htmlspecialchars($quiz_data['title']); ?>"
                                       required maxlength="200">
                            </div>

                            <div class="mb-3">
                                <label for="lesson_id" class="form-label">Linked Lesson</label>
                                <select name="lesson_id" id="lesson_id" class="form-select">
                                    <option value="">Not linked to specific lesson</option>
                                    <?php foreach ($course_lessons as $lesson_item): ?>
                                        <option value="<?php echo $lesson_item['id']; ?>"
                                                <?php echo $quiz_data['lesson_id'] == $lesson_item['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lesson_item['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="3"><?php echo htmlspecialchars($quiz_data['description']); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="time_limit" class="form-label">Time Limit (minutes)</label>
                                    <input type="number" name="time_limit" id="time_limit" class="form-control"
                                           value="<?php echo $quiz_data['time_limit']; ?>"
                                           min="1" max="300">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="passing_score" class="form-label">Passing Score (%)</label>
                                    <input type="number" name="passing_score" id="passing_score" class="form-control"
                                           value="<?php echo $quiz_data['passing_score']; ?>"
                                           min="1" max="100" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="max_attempts" class="form-label">Max Attempts</label>
                                <select name="max_attempts" id="max_attempts" class="form-select">
                                    <option value="1" <?php echo $quiz_data['max_attempts'] == 1 ? 'selected' : ''; ?>>1 attempt</option>
                                    <option value="2" <?php echo $quiz_data['max_attempts'] == 2 ? 'selected' : ''; ?>>2 attempts</option>
                                    <option value="3" <?php echo $quiz_data['max_attempts'] == 3 ? 'selected' : ''; ?>>3 attempts</option>
                                    <option value="5" <?php echo $quiz_data['max_attempts'] == 5 ? 'selected' : ''; ?>>5 attempts</option>
                                    <option value="10" <?php echo $quiz_data['max_attempts'] == 10 ? 'selected' : ''; ?>>10 attempts</option>
                                    <option value="999" <?php echo $quiz_data['max_attempts'] >= 999 ? 'selected' : ''; ?>>Unlimited attempts</option>
                                </select>
                                <div class="form-text">Maximum number of times students can take this quiz</div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" id="is_active" class="form-check-input"
                                           <?php echo $quiz_data['is_active'] ? 'checked' : ''; ?>>
                                    <label for="is_active" class="form-check-label">
                                        Quiz is active
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Quiz
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Quiz Statistics -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Quiz Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-primary mb-1"><?php echo count($quiz_questions); ?></h4>
                                    <small class="text-muted">Questions</small>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-info mb-1"><?php echo $quiz_stats['total_attempts'] ?? 0; ?></h4>
                                    <small class="text-muted">Total Attempts</small>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-success mb-1"><?php echo $quiz_stats['passed_count'] ?? 0; ?></h4>
                                    <small class="text-muted">Passed</small>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-warning mb-1"><?php echo $quiz_stats['average_score'] ? number_format($quiz_stats['average_score'], 1) . '%' : 'N/A'; ?></h4>
                                    <small class="text-muted">Avg Score</small>
                                </div>
                            </div>
                        </div>

                        <p class="text-muted small mb-0">
                            <i class="fas fa-book me-1"></i>Course: <?php echo htmlspecialchars($quiz_data['course_title']); ?>
                        </p>
                        <?php if ($quiz_data['lesson_title']): ?>
                            <p class="text-muted small mb-0">
                                <i class="fas fa-play-circle me-1"></i>Lesson: <?php echo htmlspecialchars($quiz_data['lesson_title']); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($quiz_stats['total_attempts'] > 0): ?>
                            <div class="mt-3">
                                <button class="btn btn-outline-danger btn-sm" onclick="deleteAllAttempts()">
                                    <i class="fas fa-trash me-1"></i>Delete All Attempts (<?php echo $quiz_stats['total_attempts']; ?>)
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Question Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Question</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addQuestionForm">
                    <input type="hidden" name="action" value="add_question">

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="question" class="form-label">Question <span class="text-danger">*</span></label>
                            <textarea name="question" id="question" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="question_type" class="form-label">Question Type</label>
                            <select name="question_type" id="question_type" class="form-select" onchange="toggleOptions()">
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="true_false">True/False</option>
                                <option value="short_answer">Short Answer</option>
                            </select>
                        </div>
                    </div>

                    <!-- Multiple Choice Options -->
                    <div id="multipleChoiceOptions">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Option A</label>
                                <input type="text" name="option_1" class="form-control">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Option B</label>
                                <input type="text" name="option_2" class="form-control">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Option C</label>
                                <input type="text" name="option_3" class="form-control">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Option D</label>
                                <input type="text" name="option_4" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="correct_answer" class="form-label">Correct Answer <span class="text-danger">*</span></label>
                            <input type="text" name="correct_answer" id="correct_answer" class="form-control" required>
                            <div class="form-text" id="answerHelp">For multiple choice, enter the exact option text OR just the letter (A, B, C, D). For true/false, enter "True" or "False".</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="points" class="form-label">Points</label>
                            <input type="number" name="points" id="points" class="form-control" value="1" min="1" max="10">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="explanation" class="form-label">Explanation (Optional)</label>
                        <textarea name="explanation" id="explanation" class="form-control" rows="2"></textarea>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Add Question
                    </button>
                </form>
            </div>
        </div>

        <!-- Questions List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Quiz Questions (<?php echo count($quiz_questions); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($quiz_questions)): ?>
                    <?php foreach ($quiz_questions as $index => $question): ?>
                        <div class="card question-card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="card-title">
                                            Question <?php echo $index + 1; ?>
                                            <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></span>
                                            <span class="badge bg-primary"><?php echo $question['points']; ?> point<?php echo $question['points'] > 1 ? 's' : ''; ?></span>
                                        </h6>
                                        <p class="card-text"><?php echo htmlspecialchars($question['question']); ?></p>

                                        <?php if ($question['question_type'] == 'multiple_choice' && $question['options']): ?>
                                            <div class="mt-2">
                                                <strong>Options:</strong>
                                                <ul class="list-unstyled ms-3">
                                                    <?php
                                                    $options = json_decode($question['options'], true);
                                                    foreach ($options as $i => $option):
                                                    ?>
                                                        <li class="<?php echo $option == $question['correct_answer'] ? 'text-success fw-bold' : ''; ?>">
                                                            <?php echo chr(65 + $i) . '. ' . htmlspecialchars($option); ?>
                                                            <?php if ($option == $question['correct_answer']): ?>
                                                                <i class="fas fa-check text-success ms-1"></i>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php else: ?>
                                            <p class="mb-1"><strong>Correct Answer:</strong> <span class="text-success"><?php echo htmlspecialchars($question['correct_answer']); ?></span></p>
                                        <?php endif; ?>

                                        <?php if ($question['explanation']): ?>
                                            <p class="mb-0"><strong>Explanation:</strong> <?php echo htmlspecialchars($question['explanation']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ms-3">
                                        <a href="?id=<?php echo $quiz_id; ?>&delete_question=<?php echo $question['id']; ?>"
                                           class="btn btn-outline-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this question?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Questions Added Yet</h5>
                        <p class="text-muted">Add your first question using the form above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleOptions() {
            const questionType = document.getElementById('question_type').value;
            const optionsDiv = document.getElementById('multipleChoiceOptions');
            const answerHelp = document.getElementById('answerHelp');

            if (questionType === 'multiple_choice') {
                optionsDiv.style.display = 'block';
                answerHelp.textContent = 'Enter the exact option text OR just the letter (A, B, C, D). Example: "test1" or "A"';
            } else {
                optionsDiv.style.display = 'none';
                if (questionType === 'true_false') {
                    answerHelp.textContent = 'Enter "True" or "False".';
                } else {
                    answerHelp.textContent = 'Enter the correct answer text.';
                }
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleOptions();
        });

        function deleteAllAttempts() {
            if (confirm('Are you sure you want to delete ALL student attempts for this quiz? This action cannot be undone and will remove all student progress and scores.')) {
                window.location.href = '?id=<?php echo $quiz_id; ?>&delete_all_attempts=1';
            }
        }
    </script>
</body>
</html>
