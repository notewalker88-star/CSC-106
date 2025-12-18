<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Course.php';
require_once __DIR__ . '/../classes/CourseReview.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/InstructorReview.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || !hasRole(ROLE_STUDENT)) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Handle unenrollment
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'unenroll') {
    $course_id = (int)$_POST['course_id'];

    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Verify enrollment exists and belongs to current student
        $query = "SELECT e.*, c.title, c.enrollment_count
                  FROM enrollments e
                  JOIN courses c ON e.course_id = c.id
                  WHERE e.student_id = :student_id AND e.course_id = :course_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

            // Start transaction
            $conn->beginTransaction();

            // Delete enrollment
            $query = "DELETE FROM enrollments WHERE student_id = :student_id AND course_id = :course_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':course_id', $course_id);

            if ($stmt->execute()) {
                // Update course enrollment count
                $query = "UPDATE courses SET enrollment_count = GREATEST(enrollment_count - 1, 0) WHERE id = :course_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':course_id', $course_id);
                $stmt->execute();

                // Delete lesson progress
                $query = "DELETE lp FROM lesson_progress lp
                         JOIN lessons l ON lp.lesson_id = l.id
                         WHERE lp.student_id = :student_id AND l.course_id = :course_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':student_id', $student_id);
                $stmt->bindParam(':course_id', $course_id);
                $stmt->execute();

                // Commit transaction
                $conn->commit();

                // Log activity
                logActivity($student_id, 'course_unenrolled', "Unenrolled from course: {$enrollment['title']}");

                $success_message = 'Successfully unenrolled from the course.';
            } else {
                $conn->rollBack();
                $error_message = 'Failed to unenroll from the course. Please try again.';
            }
        } else {
            $error_message = 'Enrollment not found.';
        }
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        $error_message = 'An error occurred during unenrollment. Please try again.';
    }
}

// Get student's enrolled courses with detailed progress
try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get enrolled courses with progress
    $query = "SELECT c.*, e.enrollment_date, e.progress_percentage, e.is_completed,
                     cat.name as category_name,
                     u.first_name as instructor_first_name, u.last_name as instructor_last_name,
                     (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id AND l.is_published = 1) as total_lessons,
                     (SELECT COUNT(*) FROM lesson_progress lp
                      JOIN lessons l ON lp.lesson_id = l.id
                      WHERE lp.student_id = :student_id AND l.course_id = c.id AND lp.is_completed = 1) as completed_lessons,
                     (SELECT l.id FROM lessons l
                      LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.student_id = :student_id2
                      WHERE l.course_id = c.id AND l.is_published = 1
                      ORDER BY l.lesson_order ASC, lp.is_completed ASC, l.id ASC
                      LIMIT 1) as next_lesson_id
              FROM enrollments e
              JOIN courses c ON e.course_id = c.id
              JOIN categories cat ON c.category_id = cat.id
              JOIN users u ON c.instructor_id = u.id
              WHERE e.student_id = :student_id3
              ORDER BY e.enrollment_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':student_id2', $student_id);
    $stmt->bindParam(':student_id3', $student_id);
    $stmt->execute();
    $enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $enrolled_courses = [];
}

// Get current user info
$current_user = new User();
$current_user->getUserById($student_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 0;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }

        .course-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            height: 100%;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .course-thumbnail {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            position: relative;
            overflow: hidden;
        }

        .course-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .course-thumbnail .fallback-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
        }

        .course-thumbnail .fallback-icon i {
            font-size: 3rem;
            margin-bottom: 10px;
            opacity: 0.8;
        }

        .course-thumbnail .fallback-icon .course-title {
            font-size: 1rem;
            font-weight: 600;
            opacity: 0.9;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        .progress-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 14px;
        }

        .completion-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
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
<body class="bg-light">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-book me-2"></i>My Courses</h1>
            <div>
                <a href="../courses.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Browse More Courses
                </a>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

                <!-- Courses Grid -->
                <?php if (!empty($enrolled_courses)): ?>
                    <div class="row">
                        <?php foreach ($enrolled_courses as $course): ?>
                            <?php
                            $progress = $course['total_lessons'] > 0 ?
                                round(($course['completed_lessons'] / $course['total_lessons']) * 100) : 0;
                            ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card course-card position-relative">
                                    <?php if ($course['is_completed']): ?>
                                        <div class="completion-badge">
                                            <span class="badge bg-success">
                                                <i class="fas fa-trophy me-1"></i>Completed
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="course-thumbnail">
                                        <?php if ($course['thumbnail'] && file_exists('../uploads/courses/' . $course['thumbnail'])): ?>
                                            <img src="../uploads/courses/<?php echo htmlspecialchars($course['thumbnail']); ?>"
                                                 alt="Course Thumbnail">
                                        <?php else: ?>
                                            <div class="fallback-icon">
                                                <i class="fas fa-graduation-cap"></i>
                                                <div class="course-title">
                                                    <?php echo htmlspecialchars(substr($course['title'], 0, 30)) . (strlen($course['title']) > 30 ? '...' : ''); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title mb-0">
                                                <?php echo htmlspecialchars($course['title']); ?>
                                            </h5>
                                            <div class="progress-circle bg-primary">
                                                <?php echo $progress; ?>%
                                            </div>
                                        </div>

                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($course['instructor_first_name'] . ' ' . $course['instructor_last_name']); ?>
                                        </p>

                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-tag me-1"></i>
                                            <?php echo htmlspecialchars($course['category_name']); ?>
                                        </p>

                                        <p class="card-text mb-3">
                                            <?php echo htmlspecialchars(substr($course['short_description'], 0, 100)) . '...'; ?>
                                        </p>

                                        <div class="progress mb-3" style="height: 8px;">
                                            <div class="progress-bar" role="progressbar"
                                                 style="width: <?php echo $progress; ?>%"
                                                 aria-valuenow="<?php echo $progress; ?>"
                                                 aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <small class="text-muted">
                                                <?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?> lessons completed
                                            </small>
                                            <small class="text-muted">
                                                Enrolled: <?php echo date('M j, Y', strtotime($course['enrollment_date'])); ?>
                                            </small>
                                        </div>

                                        <div class="d-grid gap-2">
                                            <?php if ($course['next_lesson_id']): ?>
                                                <a href="../lesson.php?id=<?php echo $course['next_lesson_id']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-play me-2"></i>Continue Learning
                                                </a>
                                            <?php else: ?>
                                                <a href="../course.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-eye me-2"></i>View Course
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($progress >= 50): // Allow rating if at least 50% complete ?>
                                                <?php
                                                // Check if user has already reviewed this course
                                                $courseReview = new CourseReview();
                                                $existing_review = $courseReview->getReviewByStudentAndCourse($student_id, $course['id']);

                                                // Check if user can rate the instructor (also requires 50% progress)
                                                $instructorReview = new InstructorReview();
                                                $can_rate_instructor = $instructorReview->canStudentReview($student_id, $course['instructor_id']);
                                                $existing_instructor_review = $instructorReview->getReviewByStudentAndInstructor($student_id, $course['instructor_id']);
                                                ?>
                                                <a href="rate-course.php?course_id=<?php echo $course['id']; ?>" class="btn btn-outline-warning btn-sm">
                                                    <i class="fas fa-star me-1"></i>
                                                    <?php echo $existing_review ? 'Edit Review' : 'Rate Course'; ?>
                                                </a>

                                                <?php if ($can_rate_instructor): ?>
                                                    <a href="rate-instructor.php?instructor_id=<?php echo $course['instructor_id']; ?>" class="btn btn-outline-info btn-sm">
                                                        <i class="fas fa-chalkboard-teacher me-1"></i>
                                                        <?php echo $existing_instructor_review ? 'Edit Instructor Review' : 'Rate Instructor'; ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <!-- Unenroll Button -->
                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                    onclick="confirmUnenroll('<?php echo $course['id']; ?>', '<?php echo htmlspecialchars($course['title'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-times me-1"></i>Unenroll
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="text-center py-5">
                        <i class="fas fa-book fa-5x text-muted mb-4"></i>
                        <h3 class="text-muted mb-3">No Courses Yet</h3>
                        <p class="text-muted mb-4">You haven't enrolled in any courses yet. Start your learning journey today!</p>
                        <a href="../courses.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-search me-2"></i>Browse Courses
                        </a>
                    </div>
                <?php endif; ?>
    </div>

    <!-- Unenroll Confirmation Modal -->
    <div class="modal fade" id="unenrollModal" tabindex="-1" aria-labelledby="unenrollModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="unenrollModalLabel">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>Confirm Unenrollment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to unenroll from <strong id="courseTitle"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Warning:</strong> This action will:
                        <ul class="mb-0 mt-2">
                            <li>Remove your access to all course materials</li>
                            <li>Delete all your progress and lesson completion data</li>
                            <li>Remove any certificates earned</li>
                            <li><strong>This action cannot be undone</strong></li>
                        </ul>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-money-bill-wave me-2"></i>
                        <strong>Note:</strong> For paid courses, please contact support for refund requests.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="unenroll">
                        <input type="hidden" name="course_id" id="unenrollCourseId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-check me-2"></i>Yes, Unenroll Me
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function confirmUnenroll(courseId, courseTitle) {
            document.getElementById('courseTitle').textContent = courseTitle;
            document.getElementById('unenrollCourseId').value = courseId;

            const modal = new bootstrap.Modal(document.getElementById('unenrollModal'));
            modal.show();
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>
