<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Course.php';
require_once __DIR__ . '/../classes/CourseReview.php';
require_once __DIR__ . '/../classes/User.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || !hasRole(ROLE_STUDENT)) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if (!$course_id) {
    header('Location: courses.php');
    exit();
}

// Get course details
$course = new Course();
$course_details = $course->getCourseById($course_id);

if (!$course_details) {
    header('Location: courses.php');
    exit();
}

$courseReview = new CourseReview();

// Check if student can review this course
try {
    if (!$courseReview->canStudentReview($student_id, $course_id)) {
        header('Location: courses.php?error=not_enrolled');
        exit();
    }
} catch (Exception $e) {
    error_log("Error checking student review permission: " . $e->getMessage());
    header('Location: courses.php?error=database_error');
    exit();
}

// Get existing review if any
try {
    $existing_review = $courseReview->getReviewByStudentAndCourse($student_id, $course_id);
} catch (Exception $e) {
    error_log("Error getting existing review: " . $e->getMessage());
    $existing_review = false;
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';

    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $error_message = 'Please select a rating between 1 and 5 stars.';
    } else {
        $courseReview->student_id = $student_id;
        $courseReview->course_id = $course_id;
        $courseReview->rating = $rating;
        $courseReview->review_text = $review_text;

        try {
            if ($existing_review) {
                // Update existing review
                $courseReview->id = $existing_review['id'];
                if ($courseReview->update()) {
                    $success_message = 'Your review has been updated successfully!';
                    $existing_review = $courseReview->getReviewByStudentAndCourse($student_id, $course_id);
                } else {
                    $error_message = 'Failed to update your review. Please try again.';
                }
            } else {
                // Create new review
                if ($courseReview->create()) {
                    $success_message = 'Thank you for your review!';
                    $existing_review = $courseReview->getReviewByStudentAndCourse($student_id, $course_id);
                } else {
                    $error_message = 'Failed to submit your review. Please try again.';
                }
            }
        } catch (Exception $e) {
            error_log("Error saving review: " . $e->getMessage());
            $error_message = 'Database error occurred. Please try again later.';
        }
    }
}

$page_title = ($existing_review ? 'Edit' : 'Rate') . ' Course - ' . htmlspecialchars($course_details['title'] ?? 'Course');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        .rating-stars {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
        }

        .rating-stars .star {
            transition: color 0.2s;
        }

        .rating-stars .star:hover,
        .rating-stars .star.active {
            color: #ffc107;
        }

        .course-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap me-2"></i><?php echo SITE_NAME; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="courses.php">My Courses</a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Course Info Header -->
        <div class="card course-info mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="mb-2"><?php echo htmlspecialchars($course_details['title'] ?? 'Course Title'); ?></h3>
                        <p class="mb-1">by <?php echo htmlspecialchars(($course_details['first_name'] ?? '') . ' ' . ($course_details['last_name'] ?? '')); ?></p>
                        <small><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($course_details['category_name'] ?? 'Uncategorized'); ?></small>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <?php if ($course_details['thumbnail'] && file_exists('../uploads/courses/' . $course_details['thumbnail'])): ?>
                            <img src="../uploads/courses/<?php echo htmlspecialchars($course_details['thumbnail']); ?>"
                                 alt="Course Thumbnail" class="img-fluid rounded" style="max-height: 100px;">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
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

        <!-- Rating Form -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-star me-2"></i><?php echo $existing_review ? 'Edit Your Review' : 'Rate This Course'; ?></h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="ratingForm">
                            <!-- Rating Stars -->
                            <div class="mb-4 text-center">
                                <label class="form-label">Your Rating</label>
                                <div class="rating-stars" id="ratingStars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star" data-rating="<?php echo $i; ?>">
                                            <i class="fas fa-star"></i>
                                        </span>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="rating" id="ratingInput" value="<?php echo $existing_review ? $existing_review['rating'] : ''; ?>" required>
                                <div class="text-muted mt-2">Click on the stars to rate</div>
                            </div>

                            <!-- Review Text -->
                            <div class="mb-4">
                                <label for="review_text" class="form-label">Your Review (Optional)</label>
                                <textarea class="form-control" id="review_text" name="review_text" rows="5"
                                          placeholder="Share your experience with this course..."><?php echo $existing_review ? htmlspecialchars($existing_review['review_text']) : ''; ?></textarea>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-flex justify-content-between">
                                <a href="courses.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Courses
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-star me-2"></i><?php echo $existing_review ? 'Update Review' : 'Submit Review'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star');
            const ratingInput = document.getElementById('ratingInput');

            // Set initial rating if exists
            const currentRating = ratingInput.value;
            if (currentRating) {
                updateStars(currentRating);
            }

            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = this.getAttribute('data-rating');
                    ratingInput.value = rating;
                    updateStars(rating);
                });

                star.addEventListener('mouseover', function() {
                    const rating = this.getAttribute('data-rating');
                    highlightStars(rating);
                });
            });

            document.getElementById('ratingStars').addEventListener('mouseleave', function() {
                const currentRating = ratingInput.value;
                updateStars(currentRating);
            });

            function updateStars(rating) {
                stars.forEach((star, index) => {
                    if (index < rating) {
                        star.classList.add('active');
                    } else {
                        star.classList.remove('active');
                    }
                });
            }

            function highlightStars(rating) {
                stars.forEach((star, index) => {
                    if (index < rating) {
                        star.style.color = '#ffc107';
                    } else {
                        star.style.color = '#ddd';
                    }
                });
            }
        });
    </script>
</body>
</html>
