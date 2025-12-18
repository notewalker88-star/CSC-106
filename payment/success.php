<?php
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || !hasRole(ROLE_STUDENT)) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

// Get course ID
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if (!$course_id) {
    header('Location: ' . SITE_URL . '/courses.php');
    exit();
}

// Get course details and verify enrollment
try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get course details
    $query = "SELECT c.*, cat.name as category_name, u.first_name, u.last_name
              FROM courses c
              JOIN categories cat ON c.category_id = cat.id
              JOIN users u ON c.instructor_id = u.id
              WHERE c.id = :course_id AND c.is_published = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        header('Location: ' . SITE_URL . '/courses.php');
        exit();
    }

    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify enrollment exists
    $query = "SELECT * FROM enrollments WHERE student_id = :student_id AND course_id = :course_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        header('Location: ' . SITE_URL . '/course.php?id=' . $course_id);
        exit();
    }

    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    header('Location: ' . SITE_URL . '/courses.php');
    exit();
}

$page_title = 'Payment Successful';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
        }
        .success-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .course-thumbnail {
            max-height: 120px;
            object-fit: cover;
        }
        .success-icon {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="<?php echo SITE_URL; ?>/student/courses.php">
                    <i class="fas fa-book me-1"></i>My Courses
                </a>
                <a class="nav-link" href="<?php echo SITE_URL; ?>/courses.php">
                    <i class="fas fa-search me-1"></i>Browse Courses
                </a>
            </div>
        </div>
    </nav>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card success-card border-success">
                <div class="card-header bg-success text-white text-center">
                    <h4 class="mb-0">
                        <i class="fas fa-check-circle me-2"></i>Payment Successful!
                    </h4>
                </div>
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success success-icon" style="font-size: 4rem;"></i>
                    </div>

                    <h5 class="mb-3">Thank you for your purchase!</h5>
                    <p class="text-muted mb-4">
                        You have successfully enrolled in the course. You can now start learning immediately.
                    </p>

                    <!-- Course Details -->
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <?php if ($course['thumbnail']): ?>
                                        <img src="<?php echo SITE_URL . '/uploads/courses/' . $course['thumbnail']; ?>"
                                             alt="<?php echo htmlspecialchars($course['title']); ?>"
                                             class="img-fluid rounded course-thumbnail">
                                    <?php else: ?>
                                        <div class="bg-primary text-white d-flex align-items-center justify-content-center rounded"
                                             style="height: 120px;">
                                            <i class="fas fa-book fa-3x"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9 text-start">
                                    <h6 class="mb-2"><?php echo htmlspecialchars($course['title']); ?></h6>
                                    <p class="text-muted small mb-1">
                                        by <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                    </p>
                                    <p class="text-muted small mb-2">
                                        Category: <?php echo htmlspecialchars($course['category_name']); ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-success">Enrolled</span>
                                        <strong class="text-primary"><?php echo formatCurrency($enrollment['payment_amount']); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Details -->
                    <div class="row text-start mb-4">
                        <div class="col-md-6">
                            <h6>Payment Details</h6>
                            <ul class="list-unstyled">
                                <li><strong>Amount Paid:</strong> <?php echo formatCurrency($enrollment['payment_amount']); ?></li>
                                <li><strong>Payment Status:</strong>
                                    <span class="badge bg-success">Completed</span>
                                </li>
                                <li><strong>Enrollment Date:</strong> <?php echo date('F j, Y g:i A', strtotime($enrollment['enrollment_date'])); ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Course Access</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>Lifetime access</li>
                                <li><i class="fas fa-check text-success me-2"></i>All course materials</li>
                                <li><i class="fas fa-check text-success me-2"></i>Certificate upon completion</li>
                                <li><i class="fas fa-check text-success me-2"></i>Mobile and desktop access</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex flex-column flex-md-row gap-3 justify-content-center">
                        <a href="<?php echo SITE_URL . '/course.php?id=' . $course_id; ?>" class="btn btn-primary">
                            <i class="fas fa-play me-2"></i>Start Learning Now
                        </a>
                        <a href="<?php echo SITE_URL . '/student/courses.php'; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-book me-2"></i>View My Courses
                        </a>
                        <a href="<?php echo SITE_URL . '/courses.php'; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-search me-2"></i>Browse More Courses
                        </a>
                    </div>

                    <!-- Receipt Notice -->
                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle me-2"></i>
                        A receipt has been sent to your registered email address. You can also view your purchase history in your student dashboard.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto-redirect to course after 10 seconds (optional)
        setTimeout(function() {
            const startLearningBtn = document.querySelector('a[href*="course.php"]');
            if (startLearningBtn) {
                // Uncomment the line below to enable auto-redirect
                // window.location.href = startLearningBtn.href;
            }
        }, 10000);

        // Confetti effect (optional)
        document.addEventListener('DOMContentLoaded', function() {
            // Simple celebration effect
            const successIcon = document.querySelector('.success-icon');
            if (successIcon) {
                successIcon.addEventListener('click', function() {
                    this.style.transform = 'scale(1.2)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 200);
                });
            }
        });
    </script>
</body>
</html>
