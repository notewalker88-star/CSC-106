<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Course.php';

// Check if user is admin
requireRole(ROLE_ADMIN);

// Get dashboard statistics
try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get total counts
    $stats = [];

    // Total users
    $query = "SELECT COUNT(*) as count FROM users";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total students
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'student'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total instructors
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'instructor'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_instructors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total courses
    $query = "SELECT COUNT(*) as count FROM courses";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Published courses
    $query = "SELECT COUNT(*) as count FROM courses WHERE is_published = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['published_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total enrollments
    $query = "SELECT COUNT(*) as count FROM enrollments";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_enrollments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Recent users
    $query = "SELECT id, username, first_name, last_name, email, role, created_at
              FROM users
              ORDER BY created_at DESC
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent courses
    $query = "SELECT c.id, c.title, c.is_published, c.created_at,
                     u.first_name, u.last_name
              FROM courses c
              LEFT JOIN users u ON c.instructor_id = u.id
              ORDER BY c.created_at DESC
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $recent_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $stats = [];
    $recent_users = [];
    $recent_courses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Dashboard</h1>
            <div class="text-muted">
                Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php displayFlashMessage(); ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-primary me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['total_users'] ?? 0); ?></h3>
                            <small class="text-muted">Total Users</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-success me-3">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['total_students'] ?? 0); ?></h3>
                            <small class="text-muted">Students</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-info me-3">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['total_instructors'] ?? 0); ?></h3>
                            <small class="text-muted">Instructors</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-warning me-3">
                            <i class="fas fa-book"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['total_courses'] ?? 0); ?></h3>
                            <small class="text-muted">Total Courses</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Stats -->
        <div class="row mb-4">
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-success me-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['published_courses'] ?? 0); ?></h3>
                            <small class="text-muted">Published Courses</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-purple me-3" style="background: #6f42c1;">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['total_enrollments'] ?? 0); ?></h3>
                            <small class="text-muted">Total Enrollments</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-danger me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo number_format(($stats['total_courses'] ?? 0) - ($stats['published_courses'] ?? 0)); ?></h3>
                            <small class="text-muted">Pending Courses</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>Recent Users
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_users)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_users as $user): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h6>
                                            <small class="text-muted">
                                                @<?php echo htmlspecialchars($user['username']); ?> •
                                                <span class="badge bg-<?php echo $user['role'] === 'instructor' ? 'success' : 'primary'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </small>
                                        </div>
                                        <small class="text-muted"><?php echo timeAgo($user['created_at']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No recent users found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
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
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($course['title']); ?></h6>
                                            <small class="text-muted">
                                                by <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?> •
                                                <span class="badge bg-<?php echo $course['is_published'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $course['is_published'] ? 'Published' : 'Draft'; ?>
                                                </span>
                                            </small>
                                        </div>
                                        <small class="text-muted"><?php echo timeAgo($course['created_at']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No recent courses found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
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
                                <a href="users.php?action=add" class="btn btn-primary w-100">
                                    <i class="fas fa-user-plus me-2"></i>Add User
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="courses.php" class="btn btn-success w-100">
                                    <i class="fas fa-eye me-2"></i>Review Courses
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="categories.php?action=add" class="btn btn-info w-100">
                                    <i class="fas fa-tags me-2"></i>Add Category
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="settings.php" class="btn btn-warning w-100">
                                    <i class="fas fa-cog me-2"></i>Site Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
