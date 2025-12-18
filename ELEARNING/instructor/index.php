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

// Get dashboard statistics
try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get instructor's course statistics
    $stats = [];

    // Total courses
    $query = "SELECT COUNT(*) as count FROM courses WHERE instructor_id = :instructor_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $stats['total_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Published courses
    $query = "SELECT COUNT(*) as count FROM courses WHERE instructor_id = :instructor_id AND is_published = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $stats['published_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total students enrolled
    $query = "SELECT COUNT(DISTINCT e.student_id) as count
              FROM enrollments e
              JOIN courses c ON e.course_id = c.id
              WHERE c.instructor_id = :instructor_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // No revenue calculation for instructors

    // Recent enrollments
    $query = "SELECT e.*, c.title as course_title, u.first_name, u.last_name, u.email
              FROM enrollments e
              JOIN courses c ON e.course_id = c.id
              JOIN users u ON e.student_id = u.id
              WHERE c.instructor_id = :instructor_id
              ORDER BY e.enrollment_date DESC
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $recent_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent courses
    $query = "SELECT c.*, cat.name as category_name
              FROM courses c
              LEFT JOIN categories cat ON c.category_id = cat.id
              WHERE c.instructor_id = :instructor_id
              ORDER BY c.created_at DESC
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $recent_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $stats = [
        'total_courses' => 0,
        'published_courses' => 0,
        'total_students' => 0,
        'total_revenue' => 0
    ];
    $recent_enrollments = [];
    $recent_courses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
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

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
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

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001;
                background: var(--primary-color);
                color: white;
                border: none;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            }
        }

        .mobile-menu-btn {
            display: none;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-tachometer-alt me-2"></i>Instructor Dashboard</h1>
            <div class="text-muted">
                Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['total_courses']; ?></div>
                            <h6 class="text-muted mb-0">Total Courses</h6>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-book fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['published_courses']; ?></div>
                            <h6 class="text-muted mb-0">Published</h6>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                            <h6 class="text-muted mb-0">Students</h6>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-user-graduate fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Recent Activity -->
        <div class="row">
            <!-- Recent Enrollments -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-plus me-2"></i>Recent Enrollments
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_enrollments)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_enrollments as $enrollment): ?>
                                    <div class="list-group-item border-0 px-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></h6>
                                                <p class="mb-1 text-muted small"><?php echo htmlspecialchars($enrollment['course_title']); ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo date('M j, Y', strtotime($enrollment['enrollment_date'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="students.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>View All Students
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No recent enrollments</p>
                                <a href="create-course.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Create Your First Course
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Courses -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-book me-2"></i>Recent Courses
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_courses)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_courses as $course): ?>
                                    <div class="list-group-item border-0 px-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                <p class="mb-1 text-muted small"><?php echo htmlspecialchars($course['category_name']); ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo date('M j, Y', strtotime($course['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <?php if ($course['is_published']): ?>
                                                    <span class="badge bg-success mb-1">Published</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning mb-1">Draft</span>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo $course['enrollment_count']; ?> students
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="courses.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>View All Courses
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No courses created yet</p>
                                <a href="create-course.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Create Your First Course
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const menuBtn = document.querySelector('.mobile-menu-btn');

            if (window.innerWidth <= 768 &&
                !sidebar.contains(event.target) &&
                !menuBtn.contains(event.target) &&
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
    </script>
</body>
</html>
