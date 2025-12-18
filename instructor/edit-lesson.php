<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Course.php';
require_once __DIR__ . '/../classes/Lesson.php';

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
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Set lesson properties
    $lesson->id = $lesson_id;
    $lesson->title = trim($_POST['title']);
    $lesson->description = trim($_POST['description']);

    $lesson->video_duration = (int)$_POST['video_duration'];
    $lesson->lesson_order = (int)$_POST['lesson_order'];
    $lesson->is_preview = isset($_POST['is_preview']) ? 1 : 0;
    $lesson->is_published = isset($_POST['is_published']) ? 1 : 0;

    // Handle video upload (only if new file uploaded)
    $lesson->video_url = $lesson_data['video_url']; // Keep existing
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] == 0) {
        // Delete old video file
        if ($lesson_data['video_url'] && file_exists(UPLOAD_PATH . 'lessons/' . $lesson_data['video_url'])) {
            unlink(UPLOAD_PATH . 'lessons/' . $lesson_data['video_url']);
        }

        $uploaded_video = $lesson->uploadFile($_FILES['video_file'], 'video');
        if ($uploaded_video) {
            $lesson->video_url = $uploaded_video;
        } else {
            $error_message = 'Failed to upload video file. Please check file type and size.';
        }
    }

    // Handle attachments (only if new files uploaded)
    $existing_attachments = $lesson_data['attachments'] ? json_decode($lesson_data['attachments'], true) : [];
    $new_attachments = [];

    if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
            if ($_FILES['attachments']['error'][$i] == 0) {
                $file = [
                    'name' => $_FILES['attachments']['name'][$i],
                    'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                    'size' => $_FILES['attachments']['size'][$i],
                    'type' => $_FILES['attachments']['type'][$i]
                ];

                $uploaded_file = $lesson->uploadFile($file, 'attachment');
                if ($uploaded_file) {
                    $new_attachments[] = [
                        'filename' => $uploaded_file,
                        'original_name' => $_FILES['attachments']['name'][$i],
                        'size' => $_FILES['attachments']['size'][$i]
                    ];
                }
            }
        }
    }

    // Merge existing and new attachments
    $all_attachments = array_merge($existing_attachments, $new_attachments);
    $lesson->attachments = !empty($all_attachments) ? json_encode($all_attachments) : null;

    // Validate required fields
    if (empty($lesson->title)) {
        $error_message = 'Please enter a lesson title.';
    } else {
        if ($lesson->update()) {
            $success_message = 'Lesson updated successfully!';
            // Refresh lesson data
            $lesson_data = $lesson->getLessonById($lesson_id);
        } else {
            $error_message = 'Failed to update lesson. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lesson - Instructor Dashboard</title>

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

        .attachment-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 5px;
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
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-chalkboard-teacher me-2"></i>
            Instructor Panel
        </div>

        <nav class="nav flex-column px-3 mt-3">
            <a class="nav-link" href="index.php">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </a>
            <a class="nav-link" href="courses.php">
                <i class="fas fa-book me-2"></i>My Courses
            </a>
            <a class="nav-link" href="create-course.php">
                <i class="fas fa-plus me-2"></i>Create Course
            </a>
            <a class="nav-link" href="courses.php">
                <i class="fas fa-book me-2"></i>My Courses
            </a>
            <a class="nav-link" href="students.php">
                <i class="fas fa-user-graduate me-2"></i>Students
            </a>
            <a class="nav-link" href="analytics.php">
                <i class="fas fa-chart-bar me-2"></i>Analytics
            </a>
            <hr class="text-white-50">
            <a class="nav-link" href="profile.php">
                <i class="fas fa-user-edit me-2"></i>My Profile
            </a>
            <a class="nav-link" href="<?php echo SITE_URL; ?>/index.php">
                <i class="fas fa-home me-2"></i>Back to Site
            </a>
            <a class="nav-link" href="<?php echo SITE_URL; ?>/auth/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-edit me-2"></i>Edit Lesson</h1>
            <div>
                <a href="view-lesson.php?id=<?php echo $lesson_id; ?>" class="btn btn-outline-success me-2">
                    <i class="fas fa-eye me-2"></i>Preview Lesson
                </a>
                <a href="lessons.php?course_id=<?php echo $course_id; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Lessons
                </a>
            </div>
        </div>

        <!-- Course Info -->
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Editing lesson for: <strong><?php echo htmlspecialchars($lesson_data['course_title']); ?></strong>
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

        <!-- Lesson Edit Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-play-circle me-2"></i>Lesson Information
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Basic Information -->
                        <div class="col-lg-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Lesson Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title"
                                       value="<?php echo htmlspecialchars($lesson_data['title']); ?>"
                                       maxlength="200" required>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Lesson Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"
                                          maxlength="500"><?php echo htmlspecialchars($lesson_data['description']); ?></textarea>
                            </div>


                        </div>

                        <!-- Settings -->
                        <div class="col-lg-4">
                            <div class="mb-3">
                                <label for="lesson_order" class="form-label">Lesson Order</label>
                                <input type="number" class="form-control" id="lesson_order" name="lesson_order"
                                       value="<?php echo $lesson_data['lesson_order']; ?>" min="1" required>
                            </div>

                            <div class="mb-3">
                                <label for="video_file" class="form-label">Video File</label>
                                <?php if ($lesson_data['video_url']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Current: <?php echo htmlspecialchars($lesson_data['video_url']); ?></small>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="video_file" name="video_file" accept="video/*">
                                <div class="form-text">Upload new video to replace current one</div>
                            </div>

                            <div class="mb-3">
                                <label for="video_duration" class="form-label">Video Duration (seconds)</label>
                                <input type="number" class="form-control" id="video_duration" name="video_duration"
                                       value="<?php echo $lesson_data['video_duration']; ?>" min="0">
                            </div>

                            <!-- Current Attachments -->
                            <?php if ($lesson_data['attachments']): ?>
                                <div class="mb-3">
                                    <label class="form-label">Current Attachments</label>
                                    <?php
                                    $attachments = json_decode($lesson_data['attachments'], true);
                                    if (is_array($attachments)):
                                        foreach ($attachments as $index => $attachment):
                                    ?>
                                        <div class="attachment-item d-flex justify-content-between align-items-center" id="attachment-<?php echo $index; ?>">
                                            <div class="d-flex align-items-center">
                                                <i class="<?php echo getFileIcon($attachment['original_name']); ?> me-2"></i>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($attachment['original_name']); ?></div>
                                                    <small class="text-muted"><?php echo formatFileSize($attachment['size']); ?></small>
                                                </div>
                                            </div>
                                            <div class="btn-group" role="group">
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
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="attachments" class="form-label">Add New Attachments</label>
                                <input type="file" class="form-control" id="attachments" name="attachments[]" multiple
                                       accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.xls,.xlsx,.rtf,.odt,.ods,.odp,.jpg,.jpeg,.png,.gif">
                                <div class="form-text">
                                    Upload additional files (will be added to existing)<br>
                                    <small class="text-muted">Supported: PDF, Word, Excel, PowerPoint, Images, Text files</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_preview" name="is_preview"
                                           <?php echo $lesson_data['is_preview'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_preview">
                                        Preview Lesson
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_published" name="is_published"
                                           <?php echo $lesson_data['is_published'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_published">
                                        Publish Lesson
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <a href="lessons.php?course_id=<?php echo $course_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Lesson
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>


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

            // Insert after the course info alert
            const courseInfo = document.querySelector('.alert-info');
            if (courseInfo) {
                courseInfo.parentNode.insertBefore(alertDiv, courseInfo.nextSibling);
            } else {
                // Fallback: insert at the beginning of main content
                const mainContent = document.querySelector('.main-content');
                mainContent.insertBefore(alertDiv, mainContent.firstChild);
            }

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
