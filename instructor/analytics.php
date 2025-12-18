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

// Get analytics data
try {
    $database = new Database();
    $conn = $database->getConnection();

    // Course performance data
    $query = "SELECT c.id, c.title, c.enrollment_count, c.rating_average, c.rating_count
              FROM courses c
              WHERE c.instructor_id = :instructor_id
              ORDER BY c.enrollment_count DESC";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $course_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly enrollment data for chart
    $query = "SELECT DATE_FORMAT(e.enrollment_date, '%Y-%m') as month,
                     COUNT(*) as enrollments
              FROM enrollments e
              JOIN courses c ON e.course_id = c.id
              WHERE c.instructor_id = :instructor_id
              AND e.enrollment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              GROUP BY DATE_FORMAT(e.enrollment_date, '%Y-%m')
              ORDER BY month";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $monthly_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top performing courses
    $query = "SELECT c.title, c.enrollment_count, c.rating_average
              FROM courses c
              WHERE c.instructor_id = :instructor_id
              AND c.is_published = 1
              ORDER BY c.enrollment_count DESC
              LIMIT 5";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $top_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $course_performance = [];
    $monthly_enrollments = [];
    $top_courses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Instructor Dashboard</title>

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

        .sidebar-brand {
            padding: 20px;
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .metric-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .metric-card:hover {
            transform: translateY(-5px);
        }

        .metric-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
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
            <h1><i class="fas fa-chart-bar me-2"></i>Analytics</h1>
            <div class="text-muted">
                Course Performance Overview
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Enrollment Trend Chart -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>Enrollment Trends (Last 12 Months)
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="enrollmentChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Courses -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy me-2"></i>Top Performing Courses
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_courses)): ?>
                            <?php foreach ($top_courses as $index => $course): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($course['title']); ?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-star text-warning"></i>
                                            <?php echo number_format($course['rating_average'], 1); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-primary"><?php echo $course['enrollment_count']; ?> students</span>
                                </div>
                                <?php if ($index < count($top_courses) - 1): ?>
                                    <hr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No course data available yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Performance Table -->
        <?php if (!empty($course_performance)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-table me-2"></i>Course Performance Details
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Course</th>
                                    <th>Enrollments</th>
                                    <th>Rating</th>
                                    <th>Reviews</th>
                                    
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($course_performance as $course): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($course['title']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $course['enrollment_count']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-star text-warning me-1"></i>
                                                <?php echo number_format($course['rating_average'], 1); ?>
                                            </div>
                                        </td>
                                        <td><?php echo $course['rating_count']; ?></td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="text-center py-5">
                <i class="fas fa-chart-bar fa-5x text-muted mb-4"></i>
                <h3 class="text-muted mb-3">No Analytics Data</h3>
                <p class="text-muted mb-4">Create and publish courses to see analytics data.</p>
                <a href="create-course.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create Your First Course
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Enrollment trend chart
        const enrollmentData = <?php echo json_encode($monthly_enrollments); ?>;

        if (enrollmentData.length > 0) {
            const ctx = document.getElementById('enrollmentChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: enrollmentData.map(item => {
                        const date = new Date(item.month + '-01');
                        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Enrollments',
                        data: enrollmentData.map(item => item.enrollments),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
