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
    $course = new Course();

    // Set course properties
    $course->title = trim($_POST['title']);
    $course->description = trim($_POST['description']);
    $course->short_description = trim($_POST['short_description']);
    $course->instructor_id = $instructor_id;
    $course->category_id = (int)$_POST['category_id'];
    $course->price = (float)$_POST['price'];
    $course->is_free = isset($_POST['is_free']) ? 1 : 0;
    $course->level = $_POST['level'];
    $course->language = $_POST['language'];

    $course->what_you_learn = trim($_POST['what_you_learn']);

    // Handle thumbnail upload
    $course->thumbnail = null;
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
        // Debug: Log the course data
        error_log("Creating course with data: " . json_encode([
            'title' => $course->title,
            'instructor_id' => $course->instructor_id,
            'category_id' => $course->category_id,
            'level' => $course->level
        ]));

        if ($course->create()) {
            $success_message = 'Course created successfully! You can now add lessons to your course.';
            // Redirect to courses page instead of edit page (which doesn't exist yet)
            header('Location: courses.php?success=created');
            exit();
        } else {
            $error_message = 'Failed to create course. Please try again. Check that all required fields are filled.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Course - Instructor Dashboard</title>

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

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-plus me-2"></i>Create New Course</h1>
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

        <!-- Course Creation Form -->
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
                                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                                       maxlength="200" required>
                                <div class="form-text">Choose a clear, descriptive title for your course</div>
                            </div>

                            <div class="mb-3">
                                <label for="short_description" class="form-label">Short Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="short_description" name="short_description"
                                          rows="3" maxlength="500" required><?php echo isset($_POST['short_description']) ? htmlspecialchars($_POST['short_description']) : ''; ?></textarea>
                                <div class="form-text">Brief summary that appears in course listings (max 500 characters)</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Full Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description"
                                          rows="6" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <div class="form-text">Detailed description of your course content and objectives</div>
                            </div>

                            <div class="mb-3">
                                <label for="what_you_learn" class="form-label">What Students Will Learn</label>
                                <textarea class="form-control" id="what_you_learn" name="what_you_learn"
                                          rows="4"><?php echo isset($_POST['what_you_learn']) ? htmlspecialchars($_POST['what_you_learn']) : ''; ?></textarea>
                                <div class="form-text">List the key learning outcomes (one per line)</div>
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
                                                <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="level" class="form-label">Difficulty Level <span class="text-danger">*</span></label>
                                <select class="form-select" id="level" name="level" required>
                                    <option value="">Select Level</option>
                                    <option value="beginner" <?php echo (isset($_POST['level']) && $_POST['level'] == 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                                    <option value="intermediate" <?php echo (isset($_POST['level']) && $_POST['level'] == 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                                    <option value="advanced" <?php echo (isset($_POST['level']) && $_POST['level'] == 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="language" class="form-label">Language</label>
                                <input type="text" class="form-control" id="language" name="language"
                                       value="<?php echo isset($_POST['language']) ? htmlspecialchars($_POST['language']) : 'English'; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="thumbnail" class="form-label">Course Image</label>
                                <input type="file" class="form-control" id="thumbnail" name="thumbnail" accept="image/*">
                                <div class="form-text">Upload a course thumbnail image (JPG, PNG, GIF - Max 5MB)</div>
                                <div id="image-preview" class="mt-2" style="display: none;">
                                    <img id="preview-img" src="" alt="Preview" style="max-width: 200px; max-height: 150px; border-radius: 8px;">
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_free" name="is_free"
                                           <?php echo (isset($_POST['is_free']) || !isset($_POST['price'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_free">
                                        Free Course
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3" id="price_section" style="<?php echo (isset($_POST['is_free']) || !isset($_POST['price'])) ? 'display: none;' : ''; ?>">
                                <label for="price" class="form-label">Price (₱)</label>
                                <input type="number" class="form-control" id="price" name="price"
                                       min="0" step="0.01"
                                       value="<?php echo isset($_POST['price']) ? $_POST['price'] : '0'; ?>">
                                <div class="form-text">Enter price in Philippine Peso (₱)</div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <a href="courses.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Course
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

        // Character counter for short description
        const shortDescTextarea = document.getElementById('short_description');
        const maxLength = 500;

        // Create character counter element
        const counterDiv = document.createElement('div');
        counterDiv.className = 'form-text text-end';
        counterDiv.id = 'char-counter';
        shortDescTextarea.parentNode.appendChild(counterDiv);

        function updateCharCounter() {
            const remaining = maxLength - shortDescTextarea.value.length;
            counterDiv.textContent = `${remaining} characters remaining`;
            counterDiv.className = remaining < 50 ? 'form-text text-end text-warning' : 'form-text text-end';
        }

        shortDescTextarea.addEventListener('input', updateCharCounter);
        updateCharCounter(); // Initial count

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
