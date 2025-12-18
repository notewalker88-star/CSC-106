<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Course.php';
require_once __DIR__ . '/classes/CourseReview.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/InstructorReview.php';

// Get course ID from URL
$course_id = $_GET['id'] ?? 0;

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

// Get course rating data
$courseReview = new CourseReview();
$rating_stats = $courseReview->getCourseRatingStats($course_id);
$course_reviews = $courseReview->getCourseReviews($course_id, 5); // Get latest 5 reviews

// Check if current user has reviewed this course
$user_review = false;
if (isLoggedIn() && hasRole(ROLE_STUDENT)) {
    $user_review = $courseReview->getReviewByStudentAndCourse($_SESSION['user_id'], $course_id);
}

// Get instructor rating data
$instructorReview = new InstructorReview();
$instructor_rating_stats = $instructorReview->getInstructorRatingStats($course_details['instructor_id']);

// Check if current user can rate the instructor
$can_rate_instructor = false;
$user_instructor_review = false;
if (isLoggedIn() && hasRole(ROLE_STUDENT)) {
    $can_rate_instructor = $instructorReview->canStudentReview($_SESSION['user_id'], $course_details['instructor_id']);
    $user_instructor_review = $instructorReview->getReviewByStudentAndInstructor($_SESSION['user_id'], $course_details['instructor_id']);
}

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Check if user is enrolled
$is_enrolled = false;
$enrollment_id = null;
if (isLoggedIn()) {
    try {
        $query = "SELECT id FROM enrollments WHERE student_id = :student_id AND course_id = :course_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $_SESSION['user_id']);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $is_enrolled = true;
            $enrollment_id = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
        }
    } catch (Exception $e) {
        // Handle error silently
    }
}

// Get course lessons
try {
    $query = "SELECT * FROM lessons WHERE course_id = :course_id AND is_published = 1 ORDER BY lesson_order ASC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lessons = [];
}

// Handle enrollment
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enroll'])) {
    if (!isLoggedIn()) {
        header('Location: auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }

    if (!hasRole(ROLE_STUDENT)) {
        $error_message = 'Only students can enroll in courses.';
    } elseif ($is_enrolled) {
        $error_message = 'You are already enrolled in this course.';
    } else {
        // Check if course is free or paid
        if ($course_details['is_free']) {
            // Free course - direct enrollment
            try {
                $query = "INSERT INTO enrollments (student_id, course_id, enrollment_date, progress_percentage, is_completed, payment_status, payment_amount)
                          VALUES (:student_id, :course_id, NOW(), 0, 0, 'completed', 0.00)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':student_id', $_SESSION['user_id']);
                $stmt->bindParam(':course_id', $course_id);

                if ($stmt->execute()) {
                    $is_enrolled = true;
                    $success_message = 'Successfully enrolled in the course!';
                } else {
                    $error_message = 'Failed to enroll in the course. Please try again.';
                }
            } catch (Exception $e) {
                $error_message = 'An error occurred during enrollment. Please try again.';
            }
        } else {
            // Paid course - redirect to payment
            header('Location: payment/checkout.php?course_id=' . $course_id);
            exit();
        }
    }
}

// Helper function to convert numbers to words for rating stats
function numberToWord($number) {
    $words = ['one', 'two', 'three', 'four', 'five'];
    return isset($words[$number - 1]) ? $words[$number - 1] : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course_details['title']); ?> - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        .course-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
        }

        .course-thumbnail {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .course-info-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }

        .lesson-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .lesson-item:hover {
            background-color: #f8f9fa;
            border-color: #667eea;
        }

        .instructor-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .price-badge {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .enroll-btn {
            font-size: 1.1rem;
            padding: 12px 30px;
            border-radius: 25px;
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
                        <a class="nav-link" href="courses.php">Courses</a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if (hasRole(ROLE_STUDENT)): ?>
                                    <li><a class="dropdown-item" href="student/index.php">
                                        <i class="fas fa-tachometer-alt me-2"></i>Student Dashboard
                                    </a></li>
                                <?php endif; ?>
                                <?php if (hasRole(ROLE_INSTRUCTOR)): ?>
                                    <li><a class="dropdown-item" href="instructor/index.php">
                                        <i class="fas fa-chalkboard-teacher me-2"></i>Instructor Dashboard
                                    </a></li>
                                <?php endif; ?>
                                <?php if (hasRole(ROLE_ADMIN)): ?>
                                    <li><a class="dropdown-item" href="admin/index.php">
                                        <i class="fas fa-cog me-2"></i>Admin Dashboard
                                    </a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Course Hero Section -->
    <section class="course-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <nav aria-label="breadcrumb" class="mb-3">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="text-white-50">Home</a></li>
                            <li class="breadcrumb-item"><a href="courses.php" class="text-white-50">Courses</a></li>
                            <li class="breadcrumb-item active text-white"><?php echo htmlspecialchars($course_details['title']); ?></li>
                        </ol>
                    </nav>

                    <h1 class="display-5 fw-bold mb-3"><?php echo htmlspecialchars($course_details['title']); ?></h1>
                    <p class="lead mb-4"><?php echo htmlspecialchars($course_details['short_description']); ?></p>

                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <span class="badge bg-light text-dark fs-6">
                            <i class="fas fa-signal me-1"></i><?php echo ucfirst($course_details['level']); ?>
                        </span>
                        <span class="badge bg-light text-dark fs-6">
                            <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($course_details['category_name']); ?>
                        </span>
                        <span class="badge bg-light text-dark fs-6">
                            <i class="fas fa-play-circle me-1"></i><?php echo count($lessons); ?> Lessons
                        </span>
                        <span class="badge bg-light text-dark fs-6">
                            <i class="fas fa-clock me-1"></i><?php echo isset($course_details['duration']) && $course_details['duration'] ? $course_details['duration'] : '2-3 hours'; ?>
                        </span>
                    </div>

                    <div class="d-flex align-items-center">
                        <div class="me-4">
                            <div class="text-warning mb-1">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo $i <= $rating_stats['average_rating'] ? '' : '-o'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <small><?php echo number_format($rating_stats['average_rating'], 1); ?> (<?php echo $rating_stats['total_reviews']; ?> reviews)</small>
                        </div>
                        <div>
                            <small>Created by</small><br>
                            <strong><?php echo htmlspecialchars($course_details['first_name'] . ' ' . $course_details['last_name']); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="course-thumbnail">
                        <?php if ($course_details['thumbnail'] && file_exists('uploads/courses/' . $course_details['thumbnail'])): ?>
                            <img src="uploads/courses/<?php echo htmlspecialchars($course_details['thumbnail']); ?>"
                                 alt="Course Thumbnail" class="w-100" style="height: 250px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-white text-primary d-flex align-items-center justify-content-center" style="height: 250px;">
                                <i class="fas fa-book fa-5x"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container mt-5">
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

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Course Description -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-info-circle me-2"></i>About This Course</h4>
                    </div>
                    <div class="card-body">
                        <div class="course-description">
                            <?php echo nl2br(htmlspecialchars($course_details['description'])); ?>
                        </div>
                    </div>
                </div>

                <!-- Course Curriculum -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-list me-2"></i>Course Curriculum</h4>
                        <small class="text-muted"><?php echo count($lessons); ?> lessons</small>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($lessons)): ?>
                            <?php foreach ($lessons as $index => $lesson): ?>
                                <div class="lesson-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <span class="text-muted me-2"><?php echo $index + 1; ?>.</span>
                                                <?php echo htmlspecialchars($lesson['title']); ?>
                                            </h6>
                                            <?php if ($lesson['description']): ?>
                                                <p class="text-muted small mb-0">
                                                    <?php echo htmlspecialchars(substr($lesson['description'], 0, 100)) . '...'; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ms-3">
                                            <?php if ($is_enrolled): ?>
                                                <a href="lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-play me-1"></i>Start
                                                </a>
                                            <?php else: ?>
                                                <i class="fas fa-lock text-muted"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-list fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No lessons available yet</h5>
                                <p class="text-muted">The instructor is still preparing the course content.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Instructor Info -->
                <div class="card instructor-card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-chalkboard-teacher me-2"></i>Instructor</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <?php if ($course_details['instructor_image']): ?>
                                    <img src="uploads/profiles/<?php echo htmlspecialchars($course_details['instructor_image']); ?>"
                                         alt="Instructor" class="rounded-circle" width="80" height="80"
                                         onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-avatar.png'">
                                <?php else: ?>
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                                         style="width: 80px; height: 80px;">
                                        <i class="fas fa-user fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1">
                                            <a href="instructor-profile.php?id=<?php echo $course_details['instructor_id']; ?>"
                                               class="text-decoration-none">
                                                <?php echo htmlspecialchars($course_details['first_name'] . ' ' . $course_details['last_name']); ?>
                                            </a>
                                        </h5>
                                        <p class="text-muted mb-2">Course Instructor</p>
                                        <div class="text-warning">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= round($instructor_rating_stats['average_rating']) ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                            <small class="text-muted ms-1">
                                                <?php echo number_format($instructor_rating_stats['average_rating'], 1); ?> instructor rating
                                                (<?php echo $instructor_rating_stats['total_reviews']; ?> reviews)
                                            </small>
                                        </div>
                                    </div>

                                    <?php if ($can_rate_instructor): ?>
                                        <div>
                                            <a href="student/rate-instructor.php?instructor_id=<?php echo $course_details['instructor_id']; ?>"
                                               class="btn btn-outline-warning btn-sm">
                                                <i class="fas fa-star me-1"></i>
                                                <?php echo $user_instructor_review ? 'Edit Instructor Review' : 'Rate Instructor'; ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course Reviews -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-star me-2"></i>Student Reviews</h4>
                        <?php if ($is_enrolled && !$user_review): ?>
                            <a href="student/rate-course.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-star me-1"></i>Rate Course
                            </a>
                        <?php elseif ($user_review): ?>
                            <a href="student/rate-course.php?course_id=<?php echo $course_id; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-edit me-1"></i>Edit Review
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($rating_stats['total_reviews'] > 0): ?>
                            <!-- Rating Summary -->
                            <div class="row mb-4">
                                <div class="col-md-4 text-center">
                                    <div class="display-4 fw-bold text-warning"><?php echo number_format($rating_stats['average_rating'], 1); ?></div>
                                    <div class="text-warning mb-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $rating_stats['average_rating'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <small class="text-muted"><?php echo $rating_stats['total_reviews']; ?> reviews</small>
                                </div>
                                <div class="col-md-8">
                                    <?php for ($star = 5; $star >= 1; $star--): ?>
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="me-2"><?php echo $star; ?> star</span>
                                            <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                <?php
                                                $percentage = $rating_stats['total_reviews'] > 0 ?
                                                    ($rating_stats[strtolower(numberToWord($star)) . '_star'] / $rating_stats['total_reviews']) * 100 : 0;
                                                ?>
                                                <div class="progress-bar bg-warning" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?php echo $rating_stats[strtolower(numberToWord($star)) . '_star']; ?></small>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <!-- Individual Reviews -->
                            <?php if (!empty($course_reviews)): ?>
                                <hr>
                                <h6 class="mb-3">Recent Reviews</h6>
                                <?php foreach ($course_reviews as $review): ?>
                                    <div class="review-item mb-3 pb-3 border-bottom">
                                        <div class="d-flex align-items-start">
                                            <div class="me-3">
                                                <?php if ($review['profile_image']): ?>
                                                    <img src="uploads/profiles/<?php echo htmlspecialchars($review['profile_image']); ?>"
                                                         alt="Student" class="rounded-circle" width="40" height="40"
                                                         onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-avatar.png'">
                                                <?php else: ?>
                                                    <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center"
                                                         style="width: 40px; height: 40px;">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></h6>
                                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></small>
                                                </div>
                                                <div class="text-warning mb-2">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <?php if ($review['review_text']): ?>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-star fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No reviews yet</h5>
                                <p class="text-muted">Be the first to review this course!</p>
                                <?php if ($is_enrolled): ?>
                                    <a href="student/rate-course.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-star me-1"></i>Write a Review
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="card course-info-card">
                    <div class="card-body text-center">
                        <div class="price-badge mb-3">
                            <?php if ($course_details['is_free']): ?>
                                <span class="text-success">Free</span>
                            <?php else: ?>
                                <span class="text-primary"><?php echo formatCurrency($course_details['price']); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($is_enrolled): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>You are enrolled in this course
                            </div>
                            <a href="student/courses.php" class="btn btn-success enroll-btn w-100 mb-3">
                                <i class="fas fa-play me-2"></i>Continue Learning
                            </a>
                        <?php elseif (isLoggedIn() && hasRole(ROLE_STUDENT)): ?>
                            <form method="POST">
                                <button type="submit" name="enroll" class="btn btn-primary enroll-btn w-100 mb-3">
                                    <?php if ($course_details['is_free']): ?>
                                        <i class="fas fa-plus me-2"></i>Enroll Now
                                    <?php else: ?>
                                        <i class="fas fa-credit-card me-2"></i>Buy Now - <?php echo formatCurrency($course_details['price']); ?>
                                    <?php endif; ?>
                                </button>
                            </form>
                        <?php elseif (isLoggedIn()): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>Only students can enroll in courses
                            </div>
                        <?php else: ?>
                            <a href="auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                               class="btn btn-primary enroll-btn w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Enroll
                            </a>
                        <?php endif; ?>

                        <div class="course-includes">
                            <h6 class="mb-3">This course includes:</h6>
                            <ul class="list-unstyled text-start">
                                <li class="mb-2">
                                    <i class="fas fa-play-circle text-primary me-2"></i>
                                    <?php echo count($lessons); ?> video lessons
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-file-alt text-primary me-2"></i>
                                    Downloadable resources
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-infinity text-primary me-2"></i>
                                    Lifetime access
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-mobile-alt text-primary me-2"></i>
                                    Access on mobile and desktop
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-certificate text-primary me-2"></i>
                                    Certificate of completion
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
