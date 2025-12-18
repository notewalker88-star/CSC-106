<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Course.php';
require_once __DIR__ . '/classes/InstructorReview.php';

// Get instructor ID from URL
$instructor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$instructor_id) {
    header('Location: courses.php?error=invalid_instructor');
    exit();
}

// Get instructor details
$instructor = new User();
if (!$instructor->getUserById($instructor_id) || $instructor->role !== 'instructor') {
    header('Location: courses.php?error=instructor_not_found');
    exit();
}

// Get instructor's courses
$course = new Course();
$instructor_courses = $course->getCoursesByInstructor($instructor_id, true); // Only published courses

// Get instructor reviews
$instructorReview = new InstructorReview();
$rating_stats = $instructorReview->getInstructorRatingStats($instructor_id);
$instructor_reviews = $instructorReview->getInstructorReviews($instructor_id, 5); // Get latest 5 reviews

// Initialize default values for rating stats to prevent undefined property warnings
if (!$rating_stats) {
    $rating_stats = [
        'average_rating' => 0,
        'total_reviews' => 0,
        'one_star' => 0,
        'two_star' => 0,
        'three_star' => 0,
        'four_star' => 0,
        'five_star' => 0
    ];
}

// Check if current user can rate this instructor
$can_rate = false;
$user_review = false;
if (isLoggedIn() && hasRole(ROLE_STUDENT)) {
    try {
        $can_rate = $instructorReview->canStudentReview($_SESSION['user_id'], $instructor_id);
        $user_review = $instructorReview->getReviewByStudentAndInstructor($_SESSION['user_id'], $instructor_id);
    } catch (Exception $e) {
        error_log("Error checking student review permission: " . $e->getMessage());
        $can_rate = false;
        $user_review = false;
    }
}

// Calculate instructor stats
$total_students = 0;
$total_courses = count($instructor_courses);
if (is_array($instructor_courses)) {
    foreach ($instructor_courses as $course_item) {
        $total_students += isset($course_item['enrollment_count']) ? (int)$course_item['enrollment_count'] : 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($instructor->getFullName()); ?> - Instructor Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .instructor-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
        }
        .instructor-avatar {
            width: 150px;
            height: 150px;
            border: 5px solid rgba(255,255,255,0.3);
            object-fit: cover;
        }
        .stat-card {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            color: white;
        }
        .course-card {
            transition: transform 0.2s;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .rating-breakdown {
            font-size: 0.9rem;
        }
        .rating-bar {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        .rating-fill {
            height: 100%;
            background-color: #ffc107;
            transition: width 0.3s ease;
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
                <a class="nav-link" href="courses.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Courses
                </a>
            </div>
        </div>
    </nav>

    <!-- Instructor Header -->
    <div class="instructor-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-4 text-center">
                    <?php if (isset($instructor->profile_image) && $instructor->profile_image): ?>
                        <img src="<?php echo $instructor->getProfileImageUrl(); ?>"
                             alt="Instructor" class="rounded-circle instructor-avatar">
                    <?php else: ?>
                        <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center mx-auto instructor-avatar">
                            <i class="fas fa-user fa-4x"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-8">
                    <h1 class="mb-3"><?php echo htmlspecialchars($instructor->getFullName()); ?></h1>
                    <p class="lead mb-4">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Course Instructor
                    </p>

                    <?php if (isset($instructor->bio) && $instructor->bio): ?>
                        <p class="mb-4"><?php echo nl2br(htmlspecialchars($instructor->bio)); ?></p>
                    <?php endif; ?>

                    <!-- Rating Display -->
                    <div class="d-flex align-items-center mb-4">
                        <div class="me-3">
                            <div class="text-warning">
                                <?php
                                $avg_rating = isset($rating_stats['average_rating']) ? $rating_stats['average_rating'] : 0;
                                for ($i = 1; $i <= 5; $i++):
                                ?>
                                    <i class="fas fa-star<?php echo $i <= round($avg_rating) ? '' : '-o'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <small><?php echo number_format($avg_rating, 1); ?>/5.0 (<?php echo isset($rating_stats['total_reviews']) ? $rating_stats['total_reviews'] : 0; ?> reviews)</small>
                        </div>

                        <?php if ($can_rate): ?>
                            <a href="student/rate-instructor.php?instructor_id=<?php echo $instructor_id; ?>" class="btn btn-light btn-sm">
                                <i class="fas fa-star me-1"></i>
                                <?php echo $user_review ? 'Edit Review' : 'Rate Instructor'; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row mt-4">
                <div class="col-md-4 mb-3">
                    <div class="stat-card">
                        <h3 class="mb-1"><?php echo $total_courses; ?></h3>
                        <small>Courses</small>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stat-card">
                        <h3 class="mb-1"><?php echo $total_students; ?></h3>
                        <small>Students</small>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stat-card">
                        <h3 class="mb-1"><?php echo number_format($avg_rating, 1); ?></h3>
                        <small>Rating</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-5">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Courses Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-book me-2"></i>Courses by <?php echo htmlspecialchars(isset($instructor->first_name) ? $instructor->first_name : 'Instructor'); ?></h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($instructor_courses)): ?>
                            <div class="row">
                                <?php foreach ($instructor_courses as $course_item): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card course-card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars(isset($course_item['title']) ? $course_item['title'] : 'Untitled Course'); ?></h6>
                                                <p class="card-text text-muted small">
                                                    <?php
                                                    $description = isset($course_item['short_description']) ? $course_item['short_description'] : '';
                                                    echo htmlspecialchars(substr($description, 0, 100)) . (strlen($description) > 100 ? '...' : '');
                                                    ?>
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-users me-1"></i><?php echo isset($course_item['enrollment_count']) ? $course_item['enrollment_count'] : 0; ?> students
                                                    </small>
                                                    <small class="text-warning">
                                                        <i class="fas fa-star me-1"></i><?php echo number_format(isset($course_item['rating_average']) ? $course_item['rating_average'] : 0, 1); ?>
                                                    </small>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-primary fw-bold">
                                                        <?php
                                                        $is_free = isset($course_item['is_free']) ? $course_item['is_free'] : true;
                                                        $price = isset($course_item['price']) ? $course_item['price'] : 0;
                                                        echo $is_free ? 'Free' : formatCurrency($price);
                                                        ?>
                                                    </span>
                                                    <a href="course.php?id=<?php echo isset($course_item['id']) ? $course_item['id'] : 0; ?>" class="btn btn-outline-primary btn-sm">
                                                        View Course
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No courses available</h5>
                                <p class="text-muted">This instructor hasn't published any courses yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Reviews Section -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-star me-2"></i>Student Reviews</h4>
                        <?php if ($can_rate): ?>
                            <a href="student/rate-instructor.php?instructor_id=<?php echo $instructor_id; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-star me-1"></i>
                                <?php echo $user_review ? 'Edit Review' : 'Rate Instructor'; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (isset($rating_stats['total_reviews']) && $rating_stats['total_reviews'] > 0): ?>
                            <!-- Individual Reviews -->
                            <?php if (!empty($instructor_reviews)): ?>
                                <?php foreach ($instructor_reviews as $review): ?>
                                    <div class="review-item mb-3 pb-3 border-bottom">
                                        <div class="d-flex align-items-start">
                                            <div class="me-3">
                                                <?php if (isset($review['profile_image']) && $review['profile_image']): ?>
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
                                                    <h6 class="mb-0"><?php
                                                        $first_name = isset($review['first_name']) ? $review['first_name'] : '';
                                                        $last_name = isset($review['last_name']) ? $review['last_name'] : '';
                                                        echo htmlspecialchars($first_name . ' ' . $last_name);
                                                    ?></h6>
                                                    <small class="text-muted"><?php echo isset($review['created_at']) ? date('M j, Y', strtotime($review['created_at'])) : ''; ?></small>
                                                </div>
                                                <div class="text-warning mb-2">
                                                    <?php
                                                    $review_rating = isset($review['rating']) ? $review['rating'] : 0;
                                                    for ($i = 1; $i <= 5; $i++):
                                                    ?>
                                                        <i class="fas fa-star<?php echo $i <= $review_rating ? '' : '-o'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <?php if (isset($review['review_text']) && $review['review_text']): ?>
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
                                <p class="text-muted">Be the first to review this instructor!</p>
                                <?php if ($can_rate): ?>
                                    <a href="student/rate-instructor.php?instructor_id=<?php echo $instructor_id; ?>" class="btn btn-primary">
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
                <!-- Rating Breakdown -->
                <?php if (isset($rating_stats['total_reviews']) && $rating_stats['total_reviews'] > 0): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-bar me-2"></i>Rating Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <h2 class="text-warning"><?php echo number_format($avg_rating, 1); ?></h2>
                                <div class="text-warning mb-1">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= round($avg_rating) ? '' : '-o'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <small class="text-muted"><?php echo $rating_stats['total_reviews']; ?> reviews</small>
                            </div>

                            <div class="rating-breakdown">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="me-2"><?php echo $i; ?> star</span>
                                        <div class="rating-bar flex-grow-1 me-2">
                                            <?php
                                            $star_key = strtolower(numberToWord($i)) . '_star';
                                            $star_count = isset($rating_stats[$star_key]) ? $rating_stats[$star_key] : 0;
                                            $percentage = $rating_stats['total_reviews'] > 0 ?
                                                ($star_count / $rating_stats['total_reviews']) * 100 : 0;
                                            ?>
                                            <div class="rating-fill" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $star_count; ?></small>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Contact Info -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>Instructor Info</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Member since:</strong><br>
                        <?php echo isset($instructor->created_at) ? date('F Y', strtotime($instructor->created_at)) : 'Unknown'; ?></p>

                        <p><strong>Total Courses:</strong><br>
                        <?php echo $total_courses; ?></p>

                        <p><strong>Total Students:</strong><br>
                        <?php echo $total_students; ?></p>

                        <?php if (isset($instructor->phone) && $instructor->phone): ?>
                            <p><strong>Phone:</strong><br>
                            <?php echo htmlspecialchars($instructor->phone); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
function numberToWord($number) {
    $words = ['', 'one', 'two', 'three', 'four', 'five'];
    return isset($words[$number]) ? $words[$number] : '';
}
?>
