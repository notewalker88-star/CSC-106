<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/InstructorReview.php';
require_once __DIR__ . '/../classes/User.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || !hasRole(ROLE_STUDENT)) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Get instructor ID from URL
$instructor_id = isset($_GET['instructor_id']) ? (int)$_GET['instructor_id'] : 0;

if (!$instructor_id) {
    header('Location: ../courses.php?error=invalid_instructor');
    exit();
}

// Get instructor details
$instructor = new User();
if (!$instructor->getUserById($instructor_id) || $instructor->role !== 'instructor') {
    header('Location: ../courses.php?error=instructor_not_found');
    exit();
}

$instructorReview = new InstructorReview();

// Check if student can review this instructor (must have 50% progress in at least one course)
try {
    if (!$instructorReview->canStudentReview($student_id, $instructor_id)) {
        // Redirect back to courses with error message
        header('Location: ../courses.php?error=insufficient_progress');
        exit();
    }
} catch (Exception $e) {
    error_log("Error checking student review permission: " . $e->getMessage());
    header('Location: ../courses.php?error=database_error');
    exit();
}

// Get existing review if any
try {
    $existing_review = $instructorReview->getReviewByStudentAndInstructor($student_id, $instructor_id);
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
        $instructorReview->student_id = $student_id;
        $instructorReview->instructor_id = $instructor_id;
        $instructorReview->rating = $rating;
        $instructorReview->review_text = $review_text;

        try {
            if ($existing_review) {
                // Update existing review
                $instructorReview->id = $existing_review['id'];
                if ($instructorReview->update()) {
                    $success_message = 'Your instructor review has been updated successfully!';
                    // Refresh existing review data
                    $existing_review = $instructorReview->getReviewByStudentAndInstructor($student_id, $instructor_id);
                } else {
                    $error_message = 'Failed to update your review. Please try again.';
                }
            } else {
                // Create new review
                if ($instructorReview->create()) {
                    $success_message = 'Thank you for rating this instructor!';
                    // Get the newly created review
                    $existing_review = $instructorReview->getReviewByStudentAndInstructor($student_id, $instructor_id);
                } else {
                    $error_message = 'Failed to submit your review. Please try again.';
                }
            }
        } catch (Exception $e) {
            error_log("Error submitting instructor review: " . $e->getMessage());
            $error_message = 'An error occurred while submitting your review. Please try again.';
        }
    }
}

// Get instructor's courses that the student is enrolled in with 50%+ progress
try {
    $database = new Database();
    $conn = $database->getConnection();

    $query = "SELECT c.title, c.id, e.progress_percentage
              FROM courses c
              JOIN enrollments e ON c.id = e.course_id
              WHERE c.instructor_id = :instructor_id
              AND e.student_id = :student_id
              AND e.progress_percentage >= 50
              ORDER BY c.title";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();
    $instructor_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error getting instructor courses: " . $e->getMessage());
    $instructor_courses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Instructor - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .rating-stars {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
        }
        .rating-stars .star {
            transition: color 0.2s;
            margin: 0 2px;
        }
        .rating-stars .star:hover,
        .rating-stars .star.active {
            color: #ffc107;
        }
        .instructor-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .course-badge {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
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

            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
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

        <!-- Instructor Information -->
        <div class="instructor-info">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <?php if (isset($instructor->profile_image) && $instructor->profile_image): ?>
                        <img src="<?php echo $instructor->getProfileImageUrl(); ?>"
                             alt="Instructor" class="rounded-circle mb-3" width="120" height="120"
                             style="object-fit: cover; border: 4px solid rgba(255,255,255,0.3);">
                    <?php else: ?>
                        <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3"
                             style="width: 120px; height: 120px; border: 4px solid rgba(255,255,255,0.3);">
                            <i class="fas fa-user fa-3x"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-9">
                    <h2 class="mb-2"><?php echo htmlspecialchars($instructor->getFullName()); ?></h2>
                    <p class="mb-3">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Course Instructor
                    </p>

                    <?php if (!empty($instructor_courses)): ?>
                        <div class="mb-3">
                            <h6>Your Courses with this Instructor:</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($instructor_courses as $course): ?>
                                    <span class="badge course-badge">
                                        <?php echo htmlspecialchars(isset($course['title']) ? $course['title'] : 'Untitled Course'); ?>
                                        (<?php echo round(isset($course['progress_percentage']) ? $course['progress_percentage'] : 0); ?>% complete)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <p class="mb-0">
                        <i class="fas fa-star me-2"></i>
                        Current Rating: <?php
                            $rating_avg = isset($instructor->instructor_rating_average) && $instructor->instructor_rating_average !== null
                                ? $instructor->instructor_rating_average : 0;
                            $rating_count = isset($instructor->instructor_rating_count) && $instructor->instructor_rating_count !== null
                                ? $instructor->instructor_rating_count : 0;
                            echo number_format($rating_avg, 1);
                        ?>/5.0
                        (<?php echo $rating_count; ?> reviews)
                    </p>
                </div>
            </div>
        </div>

        <!-- Rating Form -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-star me-2"></i><?php echo $existing_review ? 'Edit Your Instructor Review' : 'Rate This Instructor'; ?></h4>
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
                                <input type="hidden" name="rating" id="ratingInput" value="<?php echo ($existing_review && isset($existing_review['rating'])) ? $existing_review['rating'] : ''; ?>" required>
                                <div class="text-muted mt-2">
                                    <small>Click on the stars to rate this instructor</small>
                                </div>
                            </div>

                            <!-- Review Text -->
                            <div class="mb-4">
                                <label for="review_text" class="form-label">Your Review (Optional)</label>
                                <textarea class="form-control" id="review_text" name="review_text" rows="4"
                                          placeholder="Share your experience with this instructor..."><?php echo ($existing_review && isset($existing_review['review_text'])) ? htmlspecialchars($existing_review['review_text']) : ''; ?></textarea>
                                <div class="form-text">
                                    Help other students by sharing your thoughts about this instructor's teaching style, communication, and course quality.
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="../courses.php" class="btn btn-outline-secondary me-md-2">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-star me-1"></i>
                                    <?php echo $existing_review ? 'Update Review' : 'Submit Review'; ?>
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
