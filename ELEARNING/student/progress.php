<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Course.php';
require_once __DIR__ . '/../classes/User.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || !hasRole(ROLE_STUDENT)) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Get student's progress data
try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get overall progress statistics
    $query = "SELECT
                COUNT(DISTINCT e.course_id) as total_enrolled,
                COUNT(DISTINCT CASE WHEN e.is_completed = 1 THEN e.course_id END) as completed_courses,
                SUM(CASE WHEN l.is_published = 1 THEN 1 ELSE 0 END) as total_lessons,
                COUNT(DISTINCT lp.lesson_id) as completed_lessons,
                AVG(e.progress_percentage) as avg_progress
              FROM enrollments e
              LEFT JOIN courses c ON e.course_id = c.id
              LEFT JOIN lessons l ON c.id = l.course_id
              LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.student_id = e.student_id AND lp.is_completed = 1
              WHERE e.student_id = :student_id";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();
    $overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get course-wise progress
    $query = "SELECT c.*, e.enrollment_date, e.progress_percentage, e.is_completed,
                     cat.name as category_name,
                     u.first_name as instructor_first_name, u.last_name as instructor_last_name,
                     (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id AND l.is_published = 1) as total_lessons,
                     (SELECT COUNT(*) FROM lesson_progress lp
                      JOIN lessons l ON lp.lesson_id = l.id
                      WHERE lp.student_id = :student_id AND l.course_id = c.id AND lp.is_completed = 1) as completed_lessons,
                     (SELECT SUM(lp.time_spent) FROM lesson_progress lp
                      JOIN lessons l ON lp.lesson_id = l.id
                      WHERE lp.student_id = :student_id2 AND l.course_id = c.id) as total_time_spent
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
    $course_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent activity
    $query = "SELECT lp.*, l.title as lesson_title, c.title as course_title, c.id as course_id
              FROM lesson_progress lp
              JOIN lessons l ON lp.lesson_id = l.id
              JOIN courses c ON l.course_id = c.id
              WHERE lp.student_id = :student_id
              ORDER BY lp.updated_at DESC
              LIMIT 10";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $overall_stats = ['total_enrolled' => 0, 'completed_courses' => 0, 'total_lessons' => 0, 'completed_lessons' => 0, 'avg_progress' => 0];
    $course_progress = [];
    $recent_activity = [];
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
    <title>Learning Progress - <?php echo SITE_NAME; ?></title>

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
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
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

        .progress-card {
            border: none;
            border-radius: 15px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .progress-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .activity-item {
            border-left: 3px solid #667eea;
            padding-left: 15px;
            margin-bottom: 15px;
        }

        .circular-progress {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 16px;
        }
        .activity-item {
            border-left: 3px solid #667eea;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        @media (max-width: 768px) {
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
            <h1><i class="fas fa-chart-line me-2"></i>Learning Progress</h1>
        </div>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-primary me-3">
                            <i class="fas fa-book"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($overall_stats['total_enrolled'] ?: 0); ?></h3>
                            <small class="text-muted">Enrolled Courses</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-success me-3">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($overall_stats['completed_courses'] ?: 0); ?></h3>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-info me-3">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($overall_stats['completed_lessons'] ?: 0); ?></h3>
                            <small class="text-muted">Lessons Done</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-warning me-3">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo round($overall_stats['avg_progress'] ?: 0); ?>%</h3>
                            <small class="text-muted">Avg Progress</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

                <!-- Course Progress -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>Course Progress
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($course_progress)): ?>
                                    <?php foreach ($course_progress as $course): ?>
                                        <?php
                                        $progress = $course['total_lessons'] > 0 ?
                                            round(($course['completed_lessons'] / $course['total_lessons']) * 100) : 0;
                                        $time_spent_hours = $course['total_time_spent'] ? round($course['total_time_spent'] / 3600, 1) : 0;
                                        ?>
                                        <div class="card progress-card mb-3">
                                            <div class="card-body">
                                                <div class="row align-items-center">
                                                    <div class="col-md-6">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                        <p class="text-muted small mb-2">
                                                            <i class="fas fa-user me-1"></i>
                                                            <?php echo htmlspecialchars($course['instructor_first_name'] . ' ' . $course['instructor_last_name']); ?>
                                                        </p>
                                                        <p class="text-muted small mb-0">
                                                            <i class="fas fa-tag me-1"></i>
                                                            <?php echo htmlspecialchars($course['category_name']); ?>
                                                        </p>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="text-center">
                                                            <div class="circular-progress bg-primary mx-auto">
                                                                <?php echo $progress; ?>%
                                                            </div>
                                                            <small class="text-muted mt-2 d-block">
                                                                <?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?> lessons
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="text-center">
                                                            <?php if ($course['is_completed']): ?>
                                                                <span class="badge bg-success fs-6 mb-2">
                                                                    <i class="fas fa-trophy me-1"></i>Completed
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-primary fs-6 mb-2">
                                                                    <i class="fas fa-play me-1"></i>In Progress
                                                                </span>
                                                            <?php endif; ?>
                                                            <div class="small text-muted">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?php echo $time_spent_hours; ?>h spent
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="progress mt-3" style="height: 8px;">
                                                    <div class="progress-bar" role="progressbar"
                                                         style="width: <?php echo $progress; ?>%"
                                                         aria-valuenow="<?php echo $progress; ?>"
                                                         aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted mb-3">No Progress Data</h5>
                                        <p class="text-muted mb-4">Enroll in courses to start tracking your progress!</p>
                                        <a href="../courses.php" class="btn btn-primary">
                                            <i class="fas fa-search me-2"></i>Browse Courses
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-clock me-2"></i>Recent Activity
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($recent_activity)): ?>
                                    <div style="max-height: 400px; overflow-y: auto;">
                                        <?php foreach ($recent_activity as $activity): ?>
                                            <div class="activity-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($activity['lesson_title']); ?></h6>
                                                        <p class="text-muted small mb-1">
                                                            <?php echo htmlspecialchars($activity['course_title']); ?>
                                                        </p>
                                                        <small class="text-muted">
                                                            <?php echo date('M j, g:i A', strtotime($activity['updated_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <div class="ms-2">
                                                        <?php if ($activity['is_completed']): ?>
                                                            <i class="fas fa-check-circle text-success"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-play-circle text-primary"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">No recent activity</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>