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

// Get student's enrolled courses with progress
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

    // Get recent lesson activity
    $query = "SELECT lp.*, l.title as lesson_title, c.title as course_title, c.id as course_id
              FROM lesson_progress lp
              JOIN lessons l ON lp.lesson_id = l.id
              JOIN courses c ON l.course_id = c.id
              WHERE lp.student_id = :student_id
              ORDER BY lp.updated_at DESC
              LIMIT 5";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get dashboard statistics
    $stats = [
        'total_courses' => count($enrolled_courses),
        'completed_courses' => count(array_filter($enrolled_courses, function($course) { return $course['is_completed']; })),
        'total_lessons' => array_sum(array_column($enrolled_courses, 'total_lessons')),
        'completed_lessons' => array_sum(array_column($enrolled_courses, 'completed_lessons'))
    ];

} catch (Exception $e) {
    $enrolled_courses = [];
    $recent_activity = [];
    $stats = ['total_courses' => 0, 'completed_courses' => 0, 'total_lessons' => 0, 'completed_lessons' => 0];
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
    <title>Student Dashboard - <?php echo SITE_NAME; ?></title>

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
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }

        .course-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .course-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .course-thumbnail {
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
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
            padding: 15px;
        }

        .course-thumbnail .fallback-icon i {
            font-size: 2rem;
            margin-bottom: 8px;
            opacity: 0.8;
        }

        .course-thumbnail .fallback-icon .course-title {
            font-size: 0.8rem;
            font-weight: 600;
            opacity: 0.9;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
            line-height: 1.2;
        }

        .progress-circle {
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-tachometer-alt me-2"></i>Student Dashboard</h1>
            <div class="text-muted">
                Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-primary me-3">
                            <i class="fas fa-book"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['total_courses']); ?></h3>
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
                            <h3 class="mb-0"><?php echo number_format($stats['completed_courses']); ?></h3>
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
                            <h3 class="mb-0"><?php echo number_format($stats['total_lessons']); ?></h3>
                            <small class="text-muted">Total Lessons</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-warning me-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['completed_lessons']); ?></h3>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

                <!-- Current Courses -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-book me-2"></i>My Courses
                                </h5>
                                <a href="courses.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>View All
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($enrolled_courses)): ?>
                                    <div class="row">
                                        <?php foreach (array_slice($enrolled_courses, 0, 4) as $course): ?>
                                            <?php
                                            $progress = $course['total_lessons'] > 0 ?
                                                round(($course['completed_lessons'] / $course['total_lessons']) * 100) : 0;
                                            ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card course-card h-100">
                                                    <div class="course-thumbnail">
                                                        <?php if ($course['thumbnail'] && file_exists('../uploads/courses/' . $course['thumbnail'])): ?>
                                                            <img src="../uploads/courses/<?php echo htmlspecialchars($course['thumbnail']); ?>"
                                                                 alt="Course Thumbnail">
                                                        <?php else: ?>
                                                            <div class="fallback-icon">
                                                                <i class="fas fa-graduation-cap"></i>
                                                                <div class="course-title">
                                                                    <?php echo htmlspecialchars(substr($course['title'], 0, 20)) . (strlen($course['title']) > 20 ? '...' : ''); ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h6 class="card-title mb-0">
                                                                <?php echo htmlspecialchars($course['title']); ?>
                                                            </h6>
                                                            <div class="progress-circle bg-primary" style="width: 40px; height: 40px; font-size: 12px;">
                                                                <?php echo $progress; ?>%
                                                            </div>
                                                        </div>
                                                        <p class="text-muted small mb-2">
                                                            <i class="fas fa-user me-1"></i>
                                                            <?php echo htmlspecialchars($course['instructor_first_name'] . ' ' . $course['instructor_last_name']); ?>
                                                        </p>
                                                        <div class="progress mb-2" style="height: 6px;">
                                                            <div class="progress-bar" role="progressbar"
                                                                 style="width: <?php echo $progress; ?>%"
                                                                 aria-valuenow="<?php echo $progress; ?>"
                                                                 aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">
                                                                <?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?> lessons
                                                            </small>
                                                            <?php if ($course['next_lesson_id']): ?>
                                                                <a href="../lesson.php?id=<?php echo $course['next_lesson_id']; ?>" class="btn btn-primary btn-sm">
                                                                    <i class="fas fa-play me-1"></i>Continue
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="../course.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                                    <i class="fas fa-eye me-1"></i>View Course
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted mb-3">No Courses Yet</h5>
                                        <p class="text-muted mb-4">Start your learning journey by enrolling in a course!</p>
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

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <a href="../courses.php" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Browse Courses
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="courses.php" class="btn btn-success w-100">
                                    <i class="fas fa-book me-2"></i>My Courses
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="progress.php" class="btn btn-info w-100">
                                    <i class="fas fa-chart-line me-2"></i>View Progress
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="profile.php" class="btn btn-warning w-100">
                                    <i class="fas fa-user-edit me-2"></i>Edit Profile
                                </a>
                            </div>
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