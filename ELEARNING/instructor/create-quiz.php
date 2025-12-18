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

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $quiz->course_id = (int)$_POST['course_id'];
    $quiz->lesson_id = !empty($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : null;
    $quiz->title = trim($_POST['title']);
    $quiz->description = trim($_POST['description']);
    $quiz->time_limit = !empty($_POST['time_limit']) ? (int)$_POST['time_limit'] : null;
    $quiz->passing_score = (int)$_POST['passing_score'];
    $quiz->max_attempts = (int)$_POST['max_attempts'];
    $quiz->is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validate required fields
    if (empty($quiz->title)) {
        $error_message = 'Please enter a quiz title.';
    } elseif (empty($quiz->course_id)) {
        $error_message = 'Please select a course.';
    } else {
        // Verify course ownership
        $course_data = $course->getCourseById($quiz->course_id);
        if (!$course_data || $course_data['instructor_id'] != $instructor_id) {
            $error_message = 'Invalid course selected.';
        } else {
            if ($quiz->create()) {
                $success_message = 'Quiz created successfully! You can now add questions.';
                // Redirect to edit quiz page to add questions
                header('Location: ' . SITE_URL . '/instructor/edit-quiz.php?id=' . $quiz->id . '&success=created');
                exit();
            } else {
                $error_message = 'Failed to create quiz. Please try again.';
            }
        }
    }
}

// Get instructor's courses
$instructor_courses = $course->getCoursesByInstructor($instructor_id);

// Get lessons for selected course (AJAX will handle this)
$course_lessons = [];
if (isset($_GET['course_id'])) {
    $selected_course_id = (int)$_GET['course_id'];
    $course_lessons = $lesson->getLessonsByCourse($selected_course_id);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - <?php echo SITE_NAME; ?></title>
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
            <h1><i class="fas fa-plus-circle me-2"></i>Create New Quiz</h1>
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

        <!-- Create Quiz Form -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Quiz Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="createQuizForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                                    <select name="course_id" id="course_id" class="form-select" required onchange="loadLessons()">
                                        <option value="">Select a course</option>
                                        <?php foreach ($instructor_courses as $course_item): ?>
                                            <option value="<?php echo $course_item['id']; ?>"
                                                    <?php echo (isset($_POST['course_id']) && $_POST['course_id'] == $course_item['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($course_item['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="lesson_id" class="form-label">Lesson (Optional)</label>
                                    <select name="lesson_id" id="lesson_id" class="form-select">
                                        <option value="">Not linked to specific lesson</option>
                                    </select>
                                    <div class="form-text">Link this quiz to a specific lesson or leave it as a general course quiz.</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="title" class="form-label">Quiz Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control"
                                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                                       required maxlength="200">
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="time_limit" class="form-label">Time Limit (minutes)</label>
                                    <input type="number" name="time_limit" id="time_limit" class="form-control"
                                           value="<?php echo isset($_POST['time_limit']) ? $_POST['time_limit'] : ''; ?>"
                                           min="1" max="300">
                                    <div class="form-text">Leave empty for no time limit</div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="passing_score" class="form-label">Passing Score (%) <span class="text-danger">*</span></label>
                                    <input type="number" name="passing_score" id="passing_score" class="form-control"
                                           value="<?php echo isset($_POST['passing_score']) ? $_POST['passing_score'] : '70'; ?>"
                                           min="1" max="100" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="max_attempts" class="form-label">Max Attempts</label>
                                    <select name="max_attempts" id="max_attempts" class="form-select">
                                        <option value="1">1 attempt</option>
                                        <option value="2">2 attempts</option>
                                        <option value="3" selected>3 attempts</option>
                                        <option value="5">5 attempts</option>
                                        <option value="10">10 attempts</option>
                                        <option value="999">Unlimited attempts</option>
                                    </select>
                                    <div class="form-text">Maximum number of times students can take this quiz</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" id="is_active" class="form-check-input"
                                           <?php echo (isset($_POST['is_active']) || !isset($_POST['title'])) ? 'checked' : ''; ?>>
                                    <label for="is_active" class="form-check-label">
                                        Make quiz active immediately
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="courses.php" class="btn btn-outline-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Create Quiz
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadLessons() {
            const courseId = document.getElementById('course_id').value;
            const lessonSelect = document.getElementById('lesson_id');

            // Clear existing options
            lessonSelect.innerHTML = '<option value="">Not linked to specific lesson</option>';

            if (courseId) {
                fetch(`get-lessons.php?course_id=${courseId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.lessons) {
                            data.lessons.forEach(lesson => {
                                const option = document.createElement('option');
                                option.value = lesson.id;
                                option.textContent = lesson.title;
                                lessonSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error loading lessons:', error);
                    });
            }
        }

        // Load lessons if course is already selected
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('course_id').value) {
                loadLessons();
            }
        });
    </script>
</body>
</html>
