<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Quiz.php';
require_once __DIR__ . '/../classes/Course.php';

// Check if user is logged in and is an instructor
if (!isLoggedIn() || !hasRole(ROLE_INSTRUCTOR)) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$instructor_id = $_SESSION['user_id'];
$quiz = new Quiz();
$course = new Course();

// Handle form submissions
$success_message = '';
$error_message = '';

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    switch ($_GET['ajax']) {
        case 'toggle_active':
            $result = $quiz->toggleActive($_GET['quiz_id']);
            echo json_encode(['success' => $result]);
            exit;

        case 'delete_quiz':
            $result = $quiz->delete($_GET['quiz_id']);
            echo json_encode(['success' => $result]);
            exit;
    }
}

// Get instructor's courses for filter
$instructor_courses = $course->getCoursesByInstructor($instructor_id);

// Get selected course filter
$selected_course = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Get quizzes
if ($selected_course > 0) {
    $quizzes = $quiz->getQuizzesByCourse($selected_course);
} else {
    $quizzes = $quiz->getQuizzesByInstructor($instructor_id);
}

// Check for success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $success_message = 'Quiz created successfully!';
            break;
        case 'updated':
            $success_message = 'Quiz updated successfully!';
            break;
        case 'deleted':
            $success_message = 'Quiz deleted successfully!';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Management - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            z-index: 1000;
            transition: all 0.3s;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        .quiz-card {
            transition: transform 0.2s;
        }
        .quiz-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.75rem;
        }
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
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
            <h1><i class="fas fa-question-circle me-2"></i>Quiz Management</h1>
            <div>
                <a href="create-quiz.php" class="btn btn-primary me-2">
                    <i class="fas fa-plus me-2"></i>Create Quiz
                </a>
                <button class="btn btn-outline-secondary" onclick="location.reload()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
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

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="course_id" class="form-label">Filter by Course</label>
                        <select name="course_id" id="course_id" class="form-select" onchange="this.form.submit()">
                            <option value="0">All Courses</option>
                            <?php foreach ($instructor_courses as $course_item): ?>
                                <option value="<?php echo $course_item['id']; ?>" 
                                        <?php echo $selected_course == $course_item['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course_item['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quizzes Grid -->
        <?php if (!empty($quizzes)): ?>
            <div class="row">
                <?php foreach ($quizzes as $quiz_item): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card quiz-card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><?php echo htmlspecialchars($quiz_item['title']); ?></h6>
                                <span class="badge <?php echo $quiz_item['is_active'] ? 'bg-success' : 'bg-secondary'; ?> status-badge">
                                    <?php echo $quiz_item['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-book me-1"></i><?php echo htmlspecialchars($quiz_item['course_title']); ?>
                                </p>
                                <?php if ($quiz_item['lesson_title']): ?>
                                    <p class="text-muted small mb-2">
                                        <i class="fas fa-play-circle me-1"></i><?php echo htmlspecialchars($quiz_item['lesson_title']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if ($quiz_item['description']): ?>
                                    <p class="text-muted small"><?php echo htmlspecialchars(substr($quiz_item['description'], 0, 100)) . (strlen($quiz_item['description']) > 100 ? '...' : ''); ?></p>
                                <?php endif; ?>

                                <div class="row text-center mt-3">
                                    <div class="col-4">
                                        <small class="text-muted">Questions</small>
                                        <div class="fw-bold"><?php echo $quiz_item['question_count']; ?></div>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Attempts</small>
                                        <div class="fw-bold"><?php echo $quiz_item['attempt_count']; ?></div>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Pass Score</small>
                                        <div class="fw-bold"><?php echo $quiz_item['passing_score']; ?>%</div>
                                    </div>
                                </div>

                                <?php if ($quiz_item['time_limit']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>Time Limit: <?php echo $quiz_item['time_limit']; ?> minutes
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <div class="btn-group w-100" role="group">
                                    <a href="edit-quiz.php?id=<?php echo $quiz_item['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <button class="btn btn-outline-<?php echo $quiz_item['is_active'] ? 'warning' : 'success'; ?> btn-sm" 
                                            onclick="toggleQuizStatus(<?php echo $quiz_item['id']; ?>)">
                                        <i class="fas fa-<?php echo $quiz_item['is_active'] ? 'pause' : 'play'; ?>"></i>
                                        <?php echo $quiz_item['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" 
                                            onclick="deleteQuiz(<?php echo $quiz_item['id']; ?>, '<?php echo htmlspecialchars($quiz_item['title']); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- No Quizzes -->
            <div class="text-center py-5">
                <i class="fas fa-question-circle fa-5x text-muted mb-4"></i>
                <h3 class="text-muted mb-3">No Quizzes Found</h3>
                <p class="text-muted mb-4">
                    <?php if ($selected_course > 0): ?>
                        No quizzes found for the selected course.
                    <?php else: ?>
                        You haven't created any quizzes yet. Start by creating your first quiz!
                    <?php endif; ?>
                </p>
                <a href="create-quiz.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create Your First Quiz
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleQuizStatus(quizId) {
            if (confirm('Are you sure you want to change the status of this quiz?')) {
                fetch(`?ajax=toggle_active&quiz_id=${quizId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Failed to update quiz status.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred.');
                    });
            }
        }

        function deleteQuiz(quizId, quizTitle) {
            if (confirm(`Are you sure you want to delete the quiz "${quizTitle}"? This action cannot be undone.`)) {
                fetch(`?ajax=delete_quiz&quiz_id=${quizId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Failed to delete quiz.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred.');
                    });
            }
        }
    </script>
</body>
</html>
