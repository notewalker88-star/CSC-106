<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Course.php';
require_once __DIR__ . '/../classes/User.php';

// Check if user is logged in and is an instructor
if (!isLoggedIn() || !hasRole(ROLE_INSTRUCTOR)) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$instructor_id = $_SESSION['user_id'];
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$course_id) {
    header('Location: courses.php');
    exit();
}

$course = new Course();
$course_data = $course->getCourseById($course_id);

// Check if course exists and belongs to instructor
if (!$course_data || $course_data['instructor_id'] != $instructor_id) {
    header('Location: courses.php?error=not_found');
    exit();
}

$success_message = '';
$error_message = '';

// Get categories for dropdown
try {
    $database = new Database();
    $conn = $database->getConnection();

    $query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Set course properties
    $course->id = $course_id;
    $course->title = trim($_POST['title']);
    $course->description = trim($_POST['description']);
    $course->short_description = trim($_POST['short_description']);
    $course->category_id = (int)$_POST['category_id'];
    $course->price = (float)$_POST['price'];
    $course->is_free = isset($_POST['is_free']) ? 1 : 0;
    $course->level = $_POST['level'];
    $course->language = $_POST['language'];

    $course->what_you_learn = trim($_POST['what_you_learn']);

    // Handle thumbnail upload (only if new file uploaded)
    $course->thumbnail = $course_data['thumbnail']; // Keep existing
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
        $upload_result = $course->uploadThumbnail($_FILES['thumbnail']);
        if (!$upload_result['success']) {
            $error_message = 'Failed to upload course image: ' . $upload_result['message'];
        }
    }

    // Validate required fields
    if (empty($course->title) || empty($course->description) || empty($course->short_description) ||
        empty($course->category_id) || empty($course->level)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        if ($course->update()) {
            $success_message = 'Course updated successfully!';
            // Refresh course data
            $course_data = $course->getCourseById($course_id);
        } else {
            $error_message = 'Failed to update course. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course - Instructor Dashboard</title>

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

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
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
            <a class="nav-link active" href="courses.php">
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
            <h1><i class="fas fa-edit me-2"></i>Edit Course</h1>
            <div>
                <a href="courses.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Courses
                </a>
                <a href="lessons.php?course_id=<?php echo $course_id; ?>" class="btn btn-outline-info">
                    <i class="fas fa-play-circle me-2"></i>Manage Lessons
                </a>
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

        <!-- Course Edit Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-book me-2"></i>Course Information
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Basic Information -->
                        <div class="col-lg-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Course Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title"
                                       value="<?php echo htmlspecialchars($course_data['title']); ?>"
                                       maxlength="200" required>
                            </div>

                            <div class="mb-3">
                                <label for="short_description" class="form-label">Short Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="short_description" name="short_description"
                                          rows="3" maxlength="500" required><?php echo htmlspecialchars($course_data['short_description']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Full Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description"
                                          rows="6" required><?php echo htmlspecialchars($course_data['description']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="what_you_learn" class="form-label">What Students Will Learn</label>
                                <textarea class="form-control" id="what_you_learn" name="what_you_learn"
                                          rows="4"><?php echo htmlspecialchars($course_data['what_you_learn']); ?></textarea>
                            </div>


                        </div>

                        <!-- Course Settings -->
                        <div class="col-lg-4">
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"
                                                <?php echo ($course_data['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="level" class="form-label">Difficulty Level <span class="text-danger">*</span></label>
                                <select class="form-select" id="level" name="level" required>
                                    <option value="">Select Level</option>
                                    <option value="beginner" <?php echo ($course_data['level'] == 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                                    <option value="intermediate" <?php echo ($course_data['level'] == 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                                    <option value="advanced" <?php echo ($course_data['level'] == 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="language" class="form-label">Language</label>
                                <input type="text" class="form-control" id="language" name="language"
                                       value="<?php echo htmlspecialchars($course_data['language']); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="thumbnail" class="form-label">Course Image</label>
                                <?php if ($course_data['thumbnail']): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo SITE_URL; ?>/uploads/courses/<?php echo htmlspecialchars($course_data['thumbnail']); ?>"
                                             alt="Current thumbnail" style="max-width: 200px; max-height: 150px; border-radius: 8px;">
                                        <div class="form-text">Current course image</div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="thumbnail" name="thumbnail" accept="image/*">
                                <div class="form-text">Upload a new course thumbnail image (JPG, PNG, GIF - Max 5MB). Leave empty to keep current image.</div>
                                <div id="image-preview" class="mt-2" style="display: none;">
                                    <img id="preview-img" src="" alt="Preview" style="max-width: 200px; max-height: 150px; border-radius: 8px;">
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_free" name="is_free"
                                           <?php echo $course_data['is_free'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_free">
                                        Free Course
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3" id="price_section" style="<?php echo $course_data['is_free'] ? 'display: none;' : ''; ?>">
                                <label for="price" class="form-label">Price (₱)</label>
                                <input type="number" class="form-control" id="price" name="price"
                                       min="0" step="0.01"
                                       value="<?php echo $course_data['price']; ?>">
                                <div class="form-text">Enter price in Philippine Peso (₱)</div>
                            </div>

                            <!-- Course Status -->
                            <div class="mb-3">
                                <label class="form-label">Course Status</label>
                                <div class="p-3 border rounded">
                                    <?php if ($course_data['is_published']): ?>
                                        <span class="badge bg-success mb-2">Published</span>
                                        <p class="small text-muted mb-0">Your course is live and visible to students.</p>
                                    <?php else: ?>
                                        <span class="badge bg-warning mb-2">Draft</span>
                                        <p class="small text-muted mb-0">Your course is saved as draft. Publish it to make it visible to students.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <a href="courses.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Course
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle price field based on free course checkbox
        document.getElementById('is_free').addEventListener('change', function() {
            const priceSection = document.getElementById('price_section');
            const priceInput = document.getElementById('price');

            if (this.checked) {
                priceSection.style.display = 'none';
                priceInput.value = '0';
            } else {
                priceSection.style.display = 'block';
            }
        });

        // Image preview functionality
        document.getElementById('thumbnail').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('image-preview');
            const previewImg = document.getElementById('preview-img');

            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    </script>
</body>
</html>
