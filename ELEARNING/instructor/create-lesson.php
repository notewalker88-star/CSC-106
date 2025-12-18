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
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Verify course belongs to instructor
$course = new Course();
$course_data = $course->getCourseById($course_id);

if (!$course_data || $course_data['instructor_id'] != $instructor_id) {
    header('Location: ' . SITE_URL . '/instructor/lessons.php?error=invalid_course');
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lesson = new Lesson();

    // Set lesson properties
    $lesson->course_id = $course_id;
    $lesson->title = trim($_POST['title']);
    $lesson->description = trim($_POST['description']);

    $lesson->video_duration = (int)$_POST['video_duration'];
    $lesson->is_preview = isset($_POST['is_preview']) ? 1 : 0;
    $lesson->is_published = isset($_POST['is_published']) ? 1 : 0;

    // Get next lesson order
    $lesson->lesson_order = $lesson->getNextLessonOrder($course_id);

    // Handle video upload
    $lesson->video_url = '';
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] == 0) {
        $uploaded_video = $lesson->uploadFile($_FILES['video_file'], 'video');
        if ($uploaded_video) {
            $lesson->video_url = $uploaded_video;
        } else {
            $error_message = 'Failed to upload video file. Please check file type and size.';
        }
    }

    // Handle attachments
    $attachments = [];
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
                    $attachments[] = [
                        'filename' => $uploaded_file,
                        'original_name' => $_FILES['attachments']['name'][$i],
                        'size' => $_FILES['attachments']['size'][$i]
                    ];
                }
            }
        }
    }
    $lesson->attachments = !empty($attachments) ? json_encode($attachments) : null;

    // Validate required fields
    if (empty($lesson->title)) {
        $error_message = 'Please enter a lesson title.';
    } else {
        if ($lesson->create()) {
            $success_message = 'Lesson created successfully!';
            // Redirect to view the new lesson
            header('Location: ' . SITE_URL . '/instructor/view-lesson.php?id=' . $lesson->id);
            exit();
        } else {
            $error_message = 'Failed to create lesson. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Lesson - Instructor Dashboard</title>

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
            <h1><i class="fas fa-plus me-2"></i>Create Lesson</h1>
            <div>
                <a href="lessons.php?course_id=<?php echo $course_id; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Lessons
                </a>
            </div>
        </div>

        <!-- Course Info -->
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Creating lesson for: <strong><?php echo htmlspecialchars($course_data['title']); ?></strong>
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

        <!-- Lesson Creation Form -->
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
                                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                                       maxlength="200" required>
                                <div class="form-text">Choose a clear, descriptive title for your lesson</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Lesson Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"
                                          maxlength="500"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <div class="form-text">Brief description of what students will learn in this lesson</div>
                            </div>


                        </div>

                        <!-- Settings -->
                        <div class="col-lg-4">
                            <div class="mb-3">
                                <label for="video_file" class="form-label">Video File</label>
                                <input type="file" class="form-control" id="video_file" name="video_file" accept="video/*">
                                <div class="form-text">Upload lesson video (MP4, AVI, MOV, WMV)</div>
                            </div>

                            <div class="mb-3">
                                <label for="video_duration" class="form-label">Video Duration (seconds)</label>
                                <input type="number" class="form-control" id="video_duration" name="video_duration"
                                       value="<?php echo isset($_POST['video_duration']) ? (int)$_POST['video_duration'] : 0; ?>"
                                       min="0">
                                <div class="form-text">Duration in seconds (optional)</div>
                            </div>

                            <div class="mb-3">
                                <label for="attachments" class="form-label">Attachments</label>
                                <input type="file" class="form-control" id="attachments" name="attachments[]" multiple
                                       accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.xls,.xlsx,.rtf,.odt,.ods,.odp,.jpg,.jpeg,.png,.gif">
                                <div class="form-text">
                                    Upload lesson materials and resources<br>
                                    <small class="text-muted">Supported: PDF, Word, Excel, PowerPoint, Images, Text files (Max: 50MB each)</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_preview" name="is_preview"
                                           <?php echo isset($_POST['is_preview']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_preview">
                                        Preview Lesson
                                    </label>
                                    <div class="form-text">Allow free preview of this lesson</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_published" name="is_published"
                                           <?php echo isset($_POST['is_published']) ? 'checked' : 'checked'; ?>>
                                    <label class="form-check-label" for="is_published">
                                        Publish Lesson
                                    </label>
                                    <div class="form-text">Make lesson visible to students</div>
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
                            <i class="fas fa-save me-2"></i>Create Lesson
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>


    <script>
        // File upload validation and preview
        document.getElementById('attachments').addEventListener('change', function(e) {
            const files = e.target.files;
            const maxSize = 50 * 1024 * 1024; // 50MB
            const allowedTypes = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'xls', 'xlsx', 'rtf', 'odt', 'ods', 'odp', 'jpg', 'jpeg', 'png', 'gif'];

            // Remove existing preview
            const existingPreview = document.getElementById('file-preview');
            if (existingPreview) {
                existingPreview.remove();
            }

            if (files.length > 0) {
                const preview = document.createElement('div');
                preview.id = 'file-preview';
                preview.className = 'mt-2';

                let validFiles = 0;
                let totalSize = 0;

                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const extension = file.name.split('.').pop().toLowerCase();

                    const fileItem = document.createElement('div');
                    fileItem.className = 'border rounded p-2 mb-2 d-flex justify-content-between align-items-center';

                    let iconClass = 'fas fa-file text-muted';
                    let isValid = true;
                    let errorMsg = '';

                    // Check file type
                    if (!allowedTypes.includes(extension)) {
                        isValid = false;
                        errorMsg = 'File type not allowed';
                        iconClass = 'fas fa-exclamation-triangle text-danger';
                    }
                    // Check file size
                    else if (file.size > maxSize) {
                        isValid = false;
                        errorMsg = 'File too large (max 50MB)';
                        iconClass = 'fas fa-exclamation-triangle text-danger';
                    }
                    else {
                        validFiles++;
                        totalSize += file.size;

                        // Set appropriate icon
                        const icons = {
                            'pdf': 'fas fa-file-pdf text-danger',
                            'doc': 'fas fa-file-word text-primary',
                            'docx': 'fas fa-file-word text-primary',
                            'xls': 'fas fa-file-excel text-success',
                            'xlsx': 'fas fa-file-excel text-success',
                            'ppt': 'fas fa-file-powerpoint text-warning',
                            'pptx': 'fas fa-file-powerpoint text-warning',
                            'txt': 'fas fa-file-alt text-secondary',
                            'jpg': 'fas fa-file-image text-info',
                            'jpeg': 'fas fa-file-image text-info',
                            'png': 'fas fa-file-image text-info',
                            'gif': 'fas fa-file-image text-info'
                        };
                        iconClass = icons[extension] || 'fas fa-file text-muted';
                    }

                    fileItem.innerHTML = `
                        <div class="d-flex align-items-center">
                            <i class="${iconClass} me-2"></i>
                            <div>
                                <div class="fw-bold">${file.name}</div>
                                <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                                ${!isValid ? `<div class="text-danger small">${errorMsg}</div>` : ''}
                            </div>
                        </div>
                    `;

                    preview.appendChild(fileItem);
                }

                // Add summary
                if (validFiles > 0) {
                    const summary = document.createElement('div');
                    summary.className = 'alert alert-info small';
                    summary.innerHTML = `<i class="fas fa-info-circle me-1"></i> ${validFiles} valid file(s) selected (${(totalSize / 1024 / 1024).toFixed(2)} MB total)`;
                    preview.appendChild(summary);
                }

                this.parentNode.appendChild(preview);
            }
        });
    </script>
</body>
</html>
