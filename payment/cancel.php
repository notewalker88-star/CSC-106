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

// Get course details
try {
    $database = new Database();
    $conn = $database->getConnection();

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

} catch (Exception $e) {
    header('Location: ' . SITE_URL . '/courses.php');
    exit();
}

$page_title = 'Payment Cancelled';
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
        .cancel-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .course-thumbnail {
            max-height: 120px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-warning">
        <div class="container">
            <a class="navbar-brand text-dark" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-dark" href="<?php echo SITE_URL; ?>/courses.php">
                    <i class="fas fa-search me-1"></i>Browse Courses
                </a>
            </div>
        </div>
    </nav>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card cancel-card border-warning">
                <div class="card-header bg-warning text-dark text-center">
                    <h4 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>Payment Cancelled
                    </h4>
                </div>
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="fas fa-times-circle text-warning" style="font-size: 4rem;"></i>
                    </div>

                    <h5 class="mb-3">Payment was cancelled</h5>
                    <p class="text-muted mb-4">
                        Your payment was cancelled and no charges were made to your account.
                        You can try again or choose a different payment method.
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
                                        <span class="badge bg-secondary">Not Enrolled</span>
                                        <strong class="text-primary"><?php echo formatCurrency($course['price']); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reasons for cancellation -->
                    <div class="alert alert-info text-start">
                        <h6><i class="fas fa-info-circle me-2"></i>Common reasons for payment cancellation:</h6>
                        <ul class="mb-0">
                            <li>You clicked the back button or closed the payment window</li>
                            <li>Payment session timed out</li>
                            <li>You chose to cancel the transaction</li>
                            <li>Technical issues with the payment provider</li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex flex-column flex-md-row gap-3 justify-content-center">
                        <a href="<?php echo SITE_URL . '/payment/checkout.php?course_id=' . $course_id; ?>" class="btn btn-primary">
                            <i class="fas fa-credit-card me-2"></i>Try Payment Again
                        </a>
                        <a href="<?php echo SITE_URL . '/course.php?id=' . $course_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Course
                        </a>
                        <a href="<?php echo SITE_URL . '/courses.php'; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-search me-2"></i>Browse Other Courses
                        </a>
                    </div>

                    <!-- Help Section -->
                    <div class="mt-4">
                        <h6>Need Help?</h6>
                        <p class="text-muted small">
                            If you're experiencing issues with payment, please contact our support team.
                            We're here to help you complete your enrollment.
                        </p>
                        <a href="mailto:support@elearning.com" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-envelope me-2"></i>Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto-redirect to courses after 30 seconds (optional)
        setTimeout(function() {
            const browseCourses = document.querySelector('a[href*="courses.php"]');
            if (browseCourses) {
                // Uncomment the line below to enable auto-redirect
                // window.location.href = browseCourses.href;
            }
        }, 30000);
    </script>
</body>
</html>
