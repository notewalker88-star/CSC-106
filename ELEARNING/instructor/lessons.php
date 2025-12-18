<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Course.php';
require_once __DIR__ . '/../classes/Lesson.php';
require_once __DIR__ . '/../classes/Quiz.php';

// Check if user is logged in and is an instructor
if (!isLoggedIn() || !hasRole(ROLE_INSTRUCTOR)) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$instructor_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Initialize message variables
$success_message = '';
$error_message = '';

// Handle success/error messages from redirects
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'lesson_created':
            $success_message = 'Lesson created successfully.';
            break;
        case 'lesson_updated':
            $success_message = 'Lesson updated successfully.';
            break;
        case 'lesson_deleted':
            $success_message = 'Lesson deleted successfully.';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'delete_failed':
            $error_message = 'Failed to delete lesson. Please try again.';
            break;
        case 'unauthorized':
            $error_message = 'Unauthorized access.';
            break;
        case 'invalid_lesson':
            $error_message = 'Invalid lesson.';
            break;
    }
}

// Get instructor's courses for dropdown
try {
    $database = new Database();
    $conn = $database->getConnection();

    $query = "SELECT id, title FROM courses WHERE instructor_id = :instructor_id ORDER BY title";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $instructor_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get lessons for selected course
    $lessons = [];
    if ($course_id > 0) {
        $query = "SELECT l.*, c.title as course_title
                  FROM lessons l
                  JOIN courses c ON l.course_id = c.id
                  WHERE l.course_id = :course_id AND c.instructor_id = :instructor_id
                  ORDER BY l.lesson_order";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->bindParam(':instructor_id', $instructor_id);
        $stmt->execute();
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $instructor_courses = [];
    $lessons = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lessons - Instructor Dashboard</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }

        body {
            background-color: #f8f9fa;
        }

        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            z-index: 1000;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 10px;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
        }

        .sidebar-brand {
            padding: 20px;
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .lesson-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .lesson-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
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
            <h1><i class="fas fa-play-circle me-2"></i>Lessons</h1>
            <div>
                <?php if ($course_id > 0): ?>
                    <a href="create-lesson.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Lesson
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Course Selection -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-book me-2"></i>Select Course
                </h5>
            </div>
            <div class="card-body">
                <form method="GET">
                    <div class="row align-items-end">
                        <div class="col-md-8">
                            <label for="course_id" class="form-label">Choose a course to manage lessons</label>
                            <select class="form-select" id="course_id" name="course_id" onchange="this.form.submit()">
                                <option value="">Select a course...</option>
                                <?php foreach ($instructor_courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"
                                            <?php echo $course_id == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
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

        <!-- Lessons List -->
        <?php if ($course_id > 0): ?>
            <?php if (!empty($lessons)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Course Lessons
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        // Initialize quiz object to check for lesson quizzes
                        $quiz_checker = new Quiz();
                        foreach ($lessons as $lesson): 
                            // Check if this lesson has a quiz
                            $lesson_quiz = $quiz_checker->getQuizByLesson($lesson['id'], false);
                        ?>
                            <div class="lesson-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="badge bg-secondary me-2"><?php echo $lesson['lesson_order']; ?></span>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($lesson['title']); ?></h6>
                                            <?php if ($lesson['is_preview']): ?>
                                                <span class="badge bg-info ms-2">Preview</span>
                                            <?php endif; ?>
                                            <?php if (!$lesson['is_published']): ?>
                                                <span class="badge bg-warning ms-2">Draft</span>
                                            <?php endif; ?>
                                            <?php if ($lesson_quiz): ?>
                                                <span class="badge bg-success ms-2">Quiz Added</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($lesson['description']): ?>
                                            <p class="text-muted mb-2"><?php echo htmlspecialchars(substr($lesson['description'], 0, 150)) . '...'; ?></p>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <?php if ($lesson['video_duration']): ?>
                                                <i class="fas fa-clock me-1"></i><?php echo gmdate("H:i:s", $lesson['video_duration']); ?>
                                            <?php endif; ?>
                                            <i class="fas fa-calendar ms-3 me-1"></i><?php echo date('M j, Y', strtotime($lesson['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="btn-group" role="group">
                                        <a href="view-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-outline-success btn-sm" title="View Lesson">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-outline-primary btn-sm" title="Edit Lesson">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($lesson_quiz): ?>
                                            <a href="edit-quiz.php?id=<?php echo $lesson_quiz['id']; ?>" class="btn btn-outline-info btn-sm" title="Edit Quiz">
                                                <i class="fas fa-question-circle"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="create-quiz.php?lesson_id=<?php echo $lesson['id']; ?>" class="btn btn-outline-warning btn-sm" title="Create Quiz">
                                                <i class="fas fa-plus-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn btn-outline-danger btn-sm" title="Delete Lesson"
                                                onclick="deleteLesson(<?php echo $lesson['id']; ?>, '<?php echo htmlspecialchars($lesson['title']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- No Lessons -->
                <div class="text-center py-5">
                    <i class="fas fa-play-circle fa-5x text-muted mb-4"></i>
                    <h3 class="text-muted mb-3">No Lessons Yet</h3>
                    <p class="text-muted mb-4">Start building your course by adding lessons.</p>
                    <a href="create-lesson.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Your First Lesson
                    </a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- No Course Selected -->
            <div class="text-center py-5">
                <i class="fas fa-book fa-5x text-muted mb-4"></i>
                <h3 class="text-muted mb-3">Select a Course</h3>
                <p class="text-muted mb-4">Choose a course from the dropdown above to manage its lessons.</p>
                <?php if (empty($instructor_courses)): ?>
                    <a href="create-course.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Create Your First Course
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        function deleteLesson(lessonId, lessonTitle) {
            if (confirm('Are you sure you want to delete the lesson "' + lessonTitle + '"? This action cannot be undone.')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete-lesson.php';

                const lessonIdInput = document.createElement('input');
                lessonIdInput.type = 'hidden';
                lessonIdInput.name = 'lesson_id';
                lessonIdInput.value = lessonId;

                form.appendChild(lessonIdInput);
                document.body.appendChild(form);

                // Submit form
                form.submit();
            }
        }
    </script>
</body>
</html>
