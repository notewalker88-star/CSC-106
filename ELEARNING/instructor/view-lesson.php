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
$lesson_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get lesson data and verify ownership
$lesson = new Lesson();
$lesson_data = $lesson->getLessonById($lesson_id);

if (!$lesson_data || $lesson_data['instructor_id'] != $instructor_id) {
    header('Location: ' . SITE_URL . '/instructor/lessons.php?error=invalid_lesson');
    exit();
}

$course_id = $lesson_data['course_id'];

// Get all lessons in this course for navigation
$course_lessons = $lesson->getLessonsByCourse($course_id);

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

// Parse attachments
$attachments = [];
if ($lesson_data['attachments']) {
    $attachments = json_decode($lesson_data['attachments'], true);
    if (!is_array($attachments)) {
        $attachments = [];
    }
}

// Check for quiz linked to this lesson
$quiz = new Quiz();
$lesson_quiz = $quiz->getQuizByLesson($lesson_id, false); // Show all quizzes for instructors
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lesson_data['title']); ?> - Lesson Preview</title>

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

        .preview-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
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

        .border-dashed {
            border: 2px dashed #dee2e6 !important;
        }
    </style>
</head>
<body>
    <!-- Preview Badge -->
    <div class="preview-badge">
        <span class="badge bg-warning text-dark fs-6">
            <i class="fas fa-eye me-1"></i>Instructor Preview
        </span>
    </div>

    <!-- Lesson Header -->
    <div class="lesson-header">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <nav aria-label="breadcrumb" class="mb-3">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="lessons.php?course_id=<?php echo $course_id; ?>" class="text-white-50">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Lessons
                                </a>
                            </li>
                            <li class="breadcrumb-item text-white-50"><?php echo htmlspecialchars($lesson_data['course_title']); ?></li>
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
                                <i class="fas fa-eye me-1"></i>Preview Lesson
                            </span>
                        <?php endif; ?>

                        <?php if (!$lesson_data['is_published']): ?>
                            <span class="badge bg-warning text-dark">
                                <i class="fas fa-draft2digital me-1"></i>Draft
                            </span>
                        <?php else: ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check me-1"></i>Published
                            </span>
                        <?php endif; ?>

                        <?php if ($lesson_data['video_duration']): ?>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-clock me-1"></i><?php echo gmdate("H:i:s", $lesson_data['video_duration']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="btn-group" role="group">
                        <a href="edit-lesson.php?id=<?php echo $lesson_id; ?>" class="btn btn-light">
                            <i class="fas fa-edit me-1"></i>Edit Lesson
                        </a>
                        <a href="lessons.php?course_id=<?php echo $course_id; ?>" class="btn btn-outline-light">
                            <i class="fas fa-list me-1"></i>All Lessons
                        </a>
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
                                <?php foreach ($attachments as $index => $attachment): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="attachment-item" id="attachment-<?php echo $index; ?>">
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
                                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                                            onclick="deleteAttachment(<?php echo $lesson_id; ?>, <?php echo $index; ?>, '<?php echo htmlspecialchars($attachment['original_name']); ?>')"
                                                            title="Delete Attachment">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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

                                            <div class="row text-center mt-3">
                                                <div class="col-3">
                                                    <small class="text-muted">Questions</small>
                                                    <div class="fw-bold"><?php echo count($quiz->getQuizQuestions($lesson_quiz['id'])); ?></div>
                                                </div>
                                                <div class="col-3">
                                                    <small class="text-muted">Pass Score</small>
                                                    <div class="fw-bold"><?php echo $lesson_quiz['passing_score']; ?>%</div>
                                                </div>
                                                <div class="col-3">
                                                    <small class="text-muted">Time Limit</small>
                                                    <div class="fw-bold"><?php echo $lesson_quiz['time_limit'] ? $lesson_quiz['time_limit'] . ' min' : 'No limit'; ?></div>
                                                </div>
                                                <div class="col-3">
                                                    <small class="text-muted">Status</small>
                                                    <div class="fw-bold">
                                                        <span class="badge <?php echo $lesson_quiz['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                            <?php echo $lesson_quiz['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <div class="d-grid gap-2">
                                                <a href="edit-quiz.php?id=<?php echo $lesson_quiz['id']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-edit me-1"></i>Edit Quiz
                                                </a>
                                                <a href="courses.php" class="btn btn-outline-primary">
                                                    <i class="fas fa-book me-1"></i>All Courses
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mt-4">
                            <div class="card border-dashed">
                                <div class="card-body text-center py-4">
                                    <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted mb-2">No Quiz Available</h5>
                                    <p class="text-muted mb-3">This lesson doesn't have a quiz yet.</p>
                                    <a href="create-quiz.php?lesson_id=<?php echo $lesson_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i>Create Quiz
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Navigation -->
                <div class="lesson-nav p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <?php if ($prev_lesson): ?>
                                <a href="view-lesson.php?id=<?php echo $prev_lesson['id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-chevron-left me-1"></i>
                                    Previous: <?php echo htmlspecialchars(substr($prev_lesson['title'], 0, 30)) . (strlen($prev_lesson['title']) > 30 ? '...' : ''); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($next_lesson): ?>
                                <a href="view-lesson.php?id=<?php echo $next_lesson['id']; ?>" class="btn btn-primary">
                                    Next: <?php echo htmlspecialchars(substr($next_lesson['title'], 0, 30)) . (strlen($next_lesson['title']) > 30 ? '...' : ''); ?>
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
                             onclick="window.location.href='view-lesson.php?id=<?php echo $course_lesson['id']; ?>'">
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
                                            <small class="badge bg-info">Preview</small>
                                        <?php endif; ?>

                                        <?php if (!$course_lesson['is_published']): ?>
                                            <small class="badge bg-warning text-dark">Draft</small>
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

    <!-- Custom JavaScript -->
    <script>
        // Delete attachment function
        function deleteAttachment(lessonId, attachmentIndex, fileName) {
            if (!confirm('Are you sure you want to delete "' + fileName + '"? This action cannot be undone.')) {
                return;
            }

            // Show loading state
            const button = event.target.closest('button');
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;

            // Send AJAX request
            fetch('delete-attachment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lesson_id: lessonId,
                    attachment_index: attachmentIndex
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the attachment item from DOM
                    const attachmentElement = document.getElementById('attachment-' + attachmentIndex);
                    if (attachmentElement) {
                        attachmentElement.style.transition = 'opacity 0.3s ease';
                        attachmentElement.style.opacity = '0';
                        setTimeout(() => {
                            attachmentElement.remove();
                        }, 300);
                    }

                    // Show success message
                    showAlert('success', data.message);
                } else {
                    // Show error message
                    showAlert('danger', data.message);

                    // Restore button
                    button.innerHTML = originalHtml;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while deleting the attachment.');

                // Restore button
                button.innerHTML = originalHtml;
                button.disabled = false;
            });
        }

        // Show alert function
        function showAlert(type, message) {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert-dynamic');
            existingAlerts.forEach(alert => alert.remove());

            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-dynamic`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            // Insert at the top of the container
            const container = document.querySelector('.container');
            container.insertBefore(alertDiv, container.firstChild);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>
