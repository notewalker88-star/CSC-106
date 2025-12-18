<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Course.php';
require_once __DIR__ . '/classes/Lesson.php';
require_once __DIR__ . '/classes/Quiz.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$lesson_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get lesson data
$lesson = new Lesson();
$lesson_data = $lesson->getLessonById($lesson_id);

if (!$lesson_data) {
    header('Location: ' . SITE_URL . '/index.php?error=lesson_not_found');
    exit();
}

$course_id = $lesson_data['course_id'];

// Check access permissions
$has_access = false;

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($user_role === ROLE_ADMIN) {
        // Admins can access any lesson
        $has_access = true;
    } elseif ($user_role === ROLE_INSTRUCTOR && $lesson_data['instructor_id'] == $user_id) {
        // Instructors can access their own lessons
        $has_access = true;
    } elseif ($user_role === ROLE_STUDENT) {
        // Students need to be enrolled or lesson must be preview
        if ($lesson_data['is_preview']) {
            $has_access = true;
        } else {
            // Check enrollment
            $query = "SELECT COUNT(*) FROM enrollments WHERE student_id = :user_id AND course_id = :course_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':course_id', $course_id);
            $stmt->execute();
            $has_access = $stmt->fetchColumn() > 0;
        }
    }

    if (!$has_access) {
        header('Location: ' . SITE_URL . '/course.php?id=' . $course_id . '&error=access_denied');
        exit();
    }

    // Get all lessons in this course for navigation (only published ones for students)
    $published_only = ($user_role === ROLE_STUDENT);
    $course_lessons = $lesson->getLessonsByCourse($course_id, $published_only);

    // Find current lesson position
    $current_position = 0;
    $prev_lesson = null;
    $next_lesson = null;

    for ($i = 0; $i < count($course_lessons); $i++) {
        if ($course_lessons[$i]['id'] == $lesson_id) {
            $current_position = $i;
            $prev_lesson = $i > 0 ? $course_lessons[$i - 1] : null;
            $next_lesson = $i < count($course_lessons) - 1 ? $course_lessons[$i + 1] : null;
            break;
        }
    }

} catch (Exception $e) {
    header('Location: ' . SITE_URL . '/index.php?error=database_error');
    exit();
}

// Parse attachments
$attachments = [];
if ($lesson_data['attachments']) {
    $attachments = json_decode($lesson_data['attachments'], true);
    if (!is_array($attachments)) {
        $attachments = [];
    }
}

// Get lesson progress for student
$lesson_progress = null;
$is_lesson_completed = false;
if ($user_role === ROLE_STUDENT) {
    $progress_query = "SELECT * FROM lesson_progress
                       WHERE student_id = :student_id AND lesson_id = :lesson_id";
    $progress_stmt = $conn->prepare($progress_query);
    $progress_stmt->bindParam(':student_id', $user_id);
    $progress_stmt->bindParam(':lesson_id', $lesson_id);
    $progress_stmt->execute();
    $lesson_progress = $progress_stmt->fetch(PDO::FETCH_ASSOC);
    $is_lesson_completed = $lesson_progress && $lesson_progress['is_completed'];
}

// Check for quiz linked to this lesson
$quiz = new Quiz();
$lesson_quiz = $quiz->getQuizByLesson($lesson_id);
$student_quiz_attempts = [];
$can_take_quiz = false;
$is_quiz_completed = false;

if ($lesson_quiz && $user_role === ROLE_STUDENT) {
    $all_attempts = $quiz->getStudentAttempts($lesson_quiz['id'], $user_id);
    // Filter out incomplete attempts (where completed_at is NULL)
    $student_quiz_attempts = array_filter($all_attempts, function($attempt) {
        return !empty($attempt['completed_at']);
    });
    $can_take_quiz = $quiz->canStudentTakeQuiz($lesson_quiz['id'], $user_id);
    $is_quiz_completed = $quiz->isQuizCompleted($lesson_quiz['id'], $user_id);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lesson_data['title']); ?> - <?php echo htmlspecialchars($lesson_data['course_title']); ?></title>

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

        .lesson-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
        }

        .lesson-content {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-top: -30px;
            position: relative;
            z-index: 10;
        }

        .video-container {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            background: #000;
            border-radius: 10px;
            overflow: hidden;
        }

        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }



        .lesson-nav {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .attachment-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .attachment-item:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }

        .lesson-sidebar {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-height: 600px;
            overflow-y: auto;
        }

        .lesson-list-item {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .lesson-list-item:hover {
            background: #f8f9fa;
        }

        .lesson-list-item.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .lesson-list-item.active .text-muted {
            color: rgba(255,255,255,0.8) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }

        .btn-outline-primary {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .navbar {
            background: white !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }



        .attachment-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            transition: all 0.3s ease;
        }

        .attachment-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .lesson-completion {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 2px solid #e9ecef;
        }

        .lesson-completion.completed {
            border-color: #28a745;
            background: #f8fff9;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap me-2"></i>E-Learning
            </a>

            <div class="navbar-nav ms-auto">
                <?php if ($user_role === ROLE_INSTRUCTOR): ?>
                    <a class="nav-link" href="instructor/view-lesson.php?id=<?php echo $lesson_id; ?>">
                        <i class="fas fa-cog me-1"></i>Instructor View
                    </a>
                <?php endif; ?>
                <?php if ($user_role === ROLE_STUDENT): ?>
                    <a class="nav-link" href="student/courses.php">
                        <i class="fas fa-arrow-left me-1"></i>Back to My Courses
                    </a>
                <?php else: ?>
                    <a class="nav-link" href="course.php?id=<?php echo $course_id; ?>">
                        <i class="fas fa-arrow-left me-1"></i>Back to Course
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Lesson Header -->
    <div class="lesson-header">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <nav aria-label="breadcrumb" class="mb-3">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="<?php echo SITE_URL; ?>" class="text-white-50">Home</a>
                            </li>
                            <li class="breadcrumb-item">
                                <?php if ($user_role === ROLE_STUDENT): ?>
                                    <a href="student/courses.php" class="text-white-50">My Courses</a>
                                <?php else: ?>
                                    <a href="course.php?id=<?php echo $course_id; ?>" class="text-white-50">
                                        <?php echo htmlspecialchars($lesson_data['course_title']); ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                            <li class="breadcrumb-item active text-white">Lesson <?php echo $lesson_data['lesson_order']; ?></li>
                        </ol>
                    </nav>

                    <h1 class="mb-3"><?php echo htmlspecialchars($lesson_data['title']); ?></h1>

                    <?php if ($lesson_data['description']): ?>
                        <p class="lead mb-3"><?php echo htmlspecialchars($lesson_data['description']); ?></p>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php if ($lesson_data['is_preview']): ?>
                            <span class="badge bg-info">
                                <i class="fas fa-eye me-1"></i>Free Preview
                            </span>
                        <?php endif; ?>

                        <?php if ($lesson_data['video_duration']): ?>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-clock me-1"></i><?php echo gmdate("H:i:s", $lesson_data['video_duration']); ?>
                            </span>
                        <?php endif; ?>

                        <span class="badge bg-light text-dark">
                            <i class="fas fa-list me-1"></i>Lesson <?php echo $lesson_data['lesson_order']; ?> of <?php echo count($course_lessons); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <div class="row">
            <!-- Lesson Content -->
            <div class="col-lg-8">
                <div class="lesson-content p-4 mb-4">
                    <!-- Video Section -->
                    <?php if ($lesson_data['video_url']): ?>
                        <div class="video-container mb-4">
                            <video controls class="w-100">
                                <source src="<?php echo SITE_URL; ?>/uploads/lessons/<?php echo htmlspecialchars($lesson_data['video_url']); ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                    <?php endif; ?>



                    <!-- Attachments -->
                    <?php if (!empty($attachments)): ?>
                        <div class="mt-4">
                            <h4 class="mb-3">
                                <i class="fas fa-paperclip me-2"></i>Lesson Materials
                            </h4>
                            <div class="row">
                                <?php foreach ($attachments as $attachment): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="attachment-item">
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <i class="<?php echo getFileIcon($attachment['original_name']); ?> fa-2x"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($attachment['original_name']); ?></h6>
                                                    <small class="text-muted"><?php echo formatFileSize($attachment['size']); ?></small>
                                                </div>
                                                <div class="btn-group" role="group">
                                                    <?php
                                                    // Check if file can be viewed in browser
                                                    $file_extension = strtolower(pathinfo($attachment['original_name'], PATHINFO_EXTENSION));
                                                    $viewable_types = ['pdf', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];
                                                    $can_view = in_array($file_extension, $viewable_types);
                                                    ?>

                                                    <?php if ($can_view): ?>
                                                        <a href="<?php echo SITE_URL; ?>/view-document.php?type=lesson&lesson_id=<?php echo $lesson_id; ?>&file=<?php echo urlencode($attachment['filename']); ?>"
                                                           class="btn btn-primary btn-sm" title="View Document">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    <?php endif; ?>

                                                    <a href="<?php echo SITE_URL; ?>/download.php?type=lesson&lesson_id=<?php echo $lesson_id; ?>&file=<?php echo urlencode($attachment['filename']); ?>"
                                                       class="btn btn-outline-primary btn-sm" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Quiz Section -->
                    <?php if ($lesson_quiz): ?>
                        <div class="mt-4">
                            <h4 class="mb-3">
                                <i class="fas fa-question-circle me-2"></i>Lesson Quiz
                            </h4>
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h5 class="card-title"><?php echo htmlspecialchars($lesson_quiz['title']); ?></h5>
                                            <?php if ($lesson_quiz['description']): ?>
                                                <p class="card-text"><?php echo htmlspecialchars($lesson_quiz['description']); ?></p>
                                            <?php endif; ?>

                                            <div class="d-flex flex-wrap gap-2 mb-3">
                                                <span class="badge bg-primary">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo $lesson_quiz['time_limit'] ? $lesson_quiz['time_limit'] . ' minutes' : 'No time limit'; ?>
                                                </span>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-target me-1"></i>
                                                    <?php echo $lesson_quiz['passing_score']; ?>% to pass
                                                </span>
                                                <span class="badge bg-info">
                                                    <i class="fas fa-redo me-1"></i>
                                                    <?php 
                                                    if ($lesson_quiz['max_attempts'] >= 999) {
                                                        echo 'Unlimited attempts';
                                                    } else {
                                                        echo $lesson_quiz['max_attempts'] . ' attempts allowed';
                                                    }
                                                    ?>
                                                </span>
                                            </div>

                                            <?php if ($user_role === ROLE_STUDENT): ?>
                                                <?php if (!empty($student_quiz_attempts)): ?>
                                                    <div class="mb-3">
                                                        <h6>Your Attempts:</h6>
                                                        <?php foreach ($student_quiz_attempts as $attempt): ?>
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <span>
                                                                    Attempt <?php echo $attempt['attempt_number']; ?>:
                                                                    <span class="badge <?php echo $attempt['is_passed'] ? 'bg-success' : 'bg-danger'; ?>">
                                                                        <?php echo number_format($attempt['score'], 1); ?>%
                                                                    </span>
                                                                </span>
                                                                <small class="text-muted">
                                                                    <?php echo !empty($attempt['completed_at']) ? date('M j, Y g:i A', strtotime($attempt['completed_at'])) : 'In Progress'; ?>
                                                                </small>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <?php if ($user_role === ROLE_STUDENT): ?>
                                                <!-- Quiz Completion Status -->
                                                <div class="mb-3">
                                                    <?php if ($is_quiz_completed): ?>
                                                        <span class="badge bg-success fs-6">
                                                            <i class="fas fa-check-circle me-1"></i>Completed
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning fs-6">
                                                            <i class="fas fa-clock me-1"></i>Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="d-grid gap-2">
                                                    <a href="student/take-quiz.php?quiz_id=<?php echo $lesson_quiz['id']; ?>"
                                                       class="btn btn-primary">
                                                        <i class="fas fa-play me-2"></i>
                                                        <?php echo empty($student_quiz_attempts) ? 'Take Quiz' : 'Retake Quiz'; ?>
                                                    </a>

                                                    <?php if (!empty($student_quiz_attempts)): ?>
                                                        <a href="student/quiz-results.php?quiz_id=<?php echo $lesson_quiz['id']; ?>"
                                                           class="btn btn-outline-primary">
                                                            <i class="fas fa-chart-bar me-1"></i>View Results
                                                        </a>
                                                    <?php endif; ?>

                                                    <!-- Mark as Done Button -->
                                                    <?php if ($is_quiz_completed): ?>
                                                        <a href="student/quiz-results.php?quiz_id=<?php echo $lesson_quiz['id']; ?>&action=mark_incomplete"
                                                           class="btn btn-outline-warning btn-sm"
                                                           onclick="return confirm('Are you sure you want to mark this quiz as incomplete?')">
                                                            <i class="fas fa-undo me-1"></i>Mark Incomplete
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="student/quiz-results.php?quiz_id=<?php echo $lesson_quiz['id']; ?>&action=mark_completed"
                                                           class="btn btn-success btn-sm">
                                                            <i class="fas fa-check me-1"></i>Mark as Done
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif ($user_role === ROLE_INSTRUCTOR): ?>
                                                <a href="instructor/edit-quiz.php?id=<?php echo $lesson_quiz['id']; ?>"
                                                   class="btn btn-outline-primary">
                                                    <i class="fas fa-edit me-1"></i>Edit Quiz
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Lesson Completion -->
                <?php if ($user_role === ROLE_STUDENT): ?>
                    <div class="lesson-completion <?php echo $is_lesson_completed ? 'completed' : ''; ?> p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Lesson Progress</h6>
                                <?php if ($is_lesson_completed): ?>
                                    <div class="mb-2">
                                        <span class="badge bg-success fs-6">
                                            <i class="fas fa-check-circle me-1"></i>Completed
                                        </span>
                                        <?php if ($lesson_progress && !empty($lesson_progress['completion_date']) && $lesson_progress['completion_date'] !== '0000-00-00 00:00:00'): ?>
                                            <small class="text-muted d-block mt-1">
                                                Completed on <?php echo date('M j, Y g:i A', strtotime($lesson_progress['completion_date'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">You can unmark this lesson if needed</small>
                                <?php else: ?>
                                    <small class="text-muted">Mark as complete when you finish this lesson</small>
                                <?php endif; ?>
                            </div>
                            <div class="d-grid gap-2">
                                <?php if ($is_lesson_completed): ?>
                                    <button id="completeBtn" class="btn btn-outline-warning" onclick="markLessonIncomplete()">
                                        <i class="fas fa-undo me-1"></i>Mark as Incomplete
                                    </button>
                                <?php else: ?>
                                    <button id="completeBtn" class="btn btn-success" onclick="markLessonComplete()">
                                        <i class="fas fa-check me-1"></i>Mark as Complete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Navigation -->
                <div class="lesson-nav p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <?php if ($prev_lesson): ?>
                                <a href="lesson.php?id=<?php echo $prev_lesson['id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-chevron-left me-1"></i>
                                    Previous Lesson
                                </a>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($next_lesson): ?>
                                <a href="lesson.php?id=<?php echo $next_lesson['id']; ?>" class="btn btn-primary">
                                    Next Lesson
                                    <i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="lesson-sidebar">
                    <div class="p-3 border-bottom">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Course Lessons
                        </h5>
                        <small class="text-muted"><?php echo count($course_lessons); ?> lessons</small>
                    </div>

                    <?php foreach ($course_lessons as $index => $course_lesson): ?>
                        <div class="lesson-list-item <?php echo $course_lesson['id'] == $lesson_id ? 'active' : ''; ?>"
                             onclick="window.location.href='lesson.php?id=<?php echo $course_lesson['id']; ?>'">
                            <div class="d-flex align-items-start">
                                <div class="me-3">
                                    <span class="badge <?php echo $course_lesson['id'] == $lesson_id ? 'bg-light text-dark' : 'bg-secondary'; ?>">
                                        <?php echo $course_lesson['lesson_order']; ?>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($course_lesson['title']); ?></h6>
                                    <div class="d-flex align-items-center">
                                        <?php if ($course_lesson['video_duration']): ?>
                                            <small class="text-muted me-2">
                                                <i class="fas fa-clock me-1"></i><?php echo gmdate("i:s", $course_lesson['video_duration']); ?>
                                            </small>
                                        <?php endif; ?>

                                        <?php if ($course_lesson['is_preview']): ?>
                                            <small class="badge bg-info">Free</small>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($course_lesson['id'] == $lesson_id): ?>
                                    <div class="ms-2">
                                        <i class="fas fa-play text-light"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($user_role === ROLE_STUDENT): ?>
    <script>
        // Lesson progress tracking
        const lessonId = <?php echo $lesson_id; ?>;
        const courseId = <?php echo $course_id; ?>;
        let startTime = Date.now();
        let isCompleted = false;

        // Check if lesson is already completed
        isCompleted = <?php echo $is_lesson_completed ? 'true' : 'false'; ?>;
        checkLessonStatus();

        // Track time spent on page
        let timeTracker = setInterval(function() {
            if (!isCompleted) {
                updateProgress(false);
            }
        }, 30000); // Update every 30 seconds

        // Update progress before page unload
        window.addEventListener('beforeunload', function() {
            if (!isCompleted) {
                updateProgress(false);
            }
        });

        function checkLessonStatus() {
            // You can add code here to check if lesson is already completed
            // For now, we'll assume it's not completed
        }

        function updateProgress(completed = false, forceUpdate = false) {
            const timeSpent = Math.floor((Date.now() - startTime) / 1000);

            fetch('update-lesson-progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lesson_id: lessonId,
                    course_id: courseId,
                    is_completed: completed,
                    time_spent: timeSpent,
                    last_position: 0,
                    force_update: forceUpdate
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (completed) {
                        showCompletionMessage();
                    } else if (forceUpdate) {
                        showIncompleteMessage();
                    }
                } else {
                    console.error('Server error:', data.message);
                }
            })
            .catch(error => {
                console.error('Error updating progress:', error);
                // Reset button state on error
                const btn = document.getElementById('completeBtn');
                btn.disabled = false;
                if (completed) {
                    btn.innerHTML = '<i class="fas fa-check me-1"></i>Mark as Complete';
                    btn.className = 'btn btn-success';
                } else {
                    btn.innerHTML = '<i class="fas fa-undo me-1"></i>Mark as Incomplete';
                    btn.className = 'btn btn-outline-warning';
                }
            });

            // Reset start time for next interval
            startTime = Date.now();
        }

        function markLessonComplete() {
            const btn = document.getElementById('completeBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Completing...';

            isCompleted = true;
            clearInterval(timeTracker);
            updateProgress(true);
        }

        function markLessonIncomplete() {
            if (!confirm('Are you sure you want to mark this lesson as incomplete?')) {
                return;
            }

            const btn = document.getElementById('completeBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';

            isCompleted = false;
            updateProgress(false, true); // Pass true to force update as incomplete
        }

        function showCompletionMessage() {
            const btn = document.getElementById('completeBtn');
            btn.className = 'btn btn-outline-warning';
            btn.innerHTML = '<i class="fas fa-undo me-1"></i>Mark as Incomplete';
            btn.disabled = false;
            btn.onclick = markLessonIncomplete;

            // Show success message
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show mt-3';
            alert.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>
                <strong>Great job!</strong> You've completed this lesson.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.querySelector('.lesson-completion').appendChild(alert);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);

            // Reload page to show updated status
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }

        function showIncompleteMessage() {
            const btn = document.getElementById('completeBtn');
            btn.className = 'btn btn-success';
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Mark as Complete';
            btn.disabled = false;
            btn.onclick = markLessonComplete;

            // Show info message
            const alert = document.createElement('div');
            alert.className = 'alert alert-info alert-dismissible fade show mt-3';
            alert.innerHTML = `
                <i class="fas fa-info-circle me-2"></i>
                <strong>Updated!</strong> Lesson marked as incomplete.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.querySelector('.lesson-completion').appendChild(alert);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);

            // Reload page to show updated status
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
    </script>
    <?php endif; ?>
</body>
</html>
