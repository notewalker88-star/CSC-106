<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Course.php';
require_once __DIR__ . '/classes/User.php';

// Get featured and popular courses
$course = new Course();
$featured_courses = $course->getFeaturedCourses(6);
$popular_courses = $course->getPopularCourses(6);

// Get categories for navigation
try {
    $database = new Database();
    $conn = $database->getConnection();

    $query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// Handle messages
$message = '';
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'logged_out':
            $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>You have been successfully logged out.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                       </div>';
            break;
        case 'access_denied':
            $message = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>Access denied. You don\'t have permission to access that page.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                       </div>';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Learn New Skills Online</title>
    <meta name="description" content="<?php echo getSiteConfig('site_description') ?: 'Learn new skills with our comprehensive online courses'; ?>">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 100px 0;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="white" opacity="0.1"><polygon points="1000,100 1000,0 0,100"/></svg>');
            background-size: cover;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .course-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .course-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }

        .course-thumbnail {
            height: 200px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .course-level {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }

        .course-price {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: var(--primary-color);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
        }

        .stats-section {
            background: #f8f9fa;
            padding: 60px 0;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .category-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }

        .category-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }

        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap text-primary me-2"></i>
                <?php echo SITE_NAME; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo SITE_URL; ?>">Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="coursesDropdown" role="button" data-bs-toggle="dropdown">
                            Courses
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="courses.php">All Courses</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php foreach (array_slice($categories, 0, 8) as $category): ?>
                                <li><a class="dropdown-item" href="courses.php?category=<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <?php
                        // Get current user for avatar
                        $current_user = new User();
                        $current_user->getUserById($_SESSION['user_id']);
                        ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <?php echo $current_user->getAvatarHtml(30, 'rounded-circle me-2'); ?>
                                <?php echo htmlspecialchars($_SESSION['user_name'] ?? $current_user->getFullName()); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if (hasRole(ROLE_ADMIN)): ?>
                                    <li><a class="dropdown-item" href="admin/index.php">
                                        <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                                    </a></li>
                                <?php elseif (hasRole(ROLE_INSTRUCTOR)): ?>
                                    <li><a class="dropdown-item" href="instructor/index.php">
                                        <i class="fas fa-chalkboard-teacher me-2"></i>Instructor Dashboard
                                    </a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="student/index.php">
                                        <i class="fas fa-user-graduate me-2"></i>My Dashboard
                                    </a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user-edit me-2"></i>Profile
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary ms-2" href="auth/register.php">
                                <i class="fas fa-user-plus me-1"></i>Sign Up
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="container mt-3">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h1 class="display-4 fw-bold mb-4">
                            Learn New Skills<br>
                            <span class="text-warning">Anytime, Anywhere</span>
                        </h1>
                        <p class="lead mb-4">
                            Join thousands of students learning from expert instructors.
                            Build your skills with our comprehensive online courses.
                        </p>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="courses.php" class="btn btn-light btn-lg">
                                <i class="fas fa-play me-2"></i>Browse Courses
                            </a>
                            <?php if (!isLoggedIn()): ?>
                                <a href="auth/register.php" class="btn btn-outline-light btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Get Started Free
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-graduation-cap" style="font-size: 15rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">1000+</div>
                        <h5>Students</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">50+</div>
                        <h5>Courses</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">25+</div>
                        <h5>Instructors</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">95%</div>
                        <h5>Success Rate</h5>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Courses -->
    <?php if (!empty($featured_courses)): ?>
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Featured Courses</h2>
                <p class="text-muted">Hand-picked courses by our experts</p>
            </div>

            <div class="row">
                <?php foreach ($featured_courses as $course): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card course-card h-100">
                            <div class="course-thumbnail" style="background-image: url('<?php
                                if ($course['thumbnail'] && file_exists(UPLOAD_PATH . 'courses/' . $course['thumbnail'])) {
                                    echo SITE_URL . '/uploads/courses/' . htmlspecialchars($course['thumbnail']);
                                } else {
                                    echo SITE_URL . '/assets/images/default-course.jpg';
                                }
                            ?>');">
                                <span class="course-level"><?php echo ucfirst($course['level']); ?></span>
                                <span class="course-price">
                                    <?php echo $course['is_free'] ? 'Free' : formatCurrency($course['price']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                </p>
                                <p class="card-text"><?php echo htmlspecialchars(substr($course['short_description'], 0, 100)) . '...'; ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $course['rating_average'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                        <small class="text-muted ms-1">(<?php echo $course['rating_count']; ?>)</small>
                                    </div>
                                    <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                        View Course
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-4">
                <a href="courses.php" class="btn btn-outline-primary">
                    <i class="fas fa-th-large me-2"></i>View All Courses
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Popular Courses -->
    <?php if (!empty($popular_courses)): ?>
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Popular Courses</h2>
                <p class="text-muted">Most enrolled courses by our students</p>
            </div>

            <div class="row">
                <?php foreach ($popular_courses as $course): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card course-card h-100">
                            <div class="course-thumbnail" style="background-image: url('<?php
                                if ($course['thumbnail'] && file_exists(UPLOAD_PATH . 'courses/' . $course['thumbnail'])) {
                                    echo SITE_URL . '/uploads/courses/' . htmlspecialchars($course['thumbnail']);
                                } else {
                                    echo SITE_URL . '/assets/images/default-course.jpg';
                                }
                            ?>');">
                                <span class="course-level"><?php echo ucfirst($course['level']); ?></span>
                                <span class="course-price">
                                    <?php echo $course['is_free'] ? 'Free' : formatCurrency($course['price']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                </p>
                                <p class="card-text"><?php echo htmlspecialchars(substr($course['short_description'], 0, 100)) . '...'; ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $course['rating_average'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                        <small class="text-muted ms-1">(<?php echo $course['rating_count']; ?>)</small>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-users me-1"></i><?php echo $course['enrollment_count']; ?> enrolled
                                    </small>
                                </div>
                                <div class="mt-2">
                                    <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm w-100">
                                        View Course
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-4">
                <a href="courses.php" class="btn btn-outline-primary">
                    <i class="fas fa-fire me-2"></i>View All Popular Courses
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Categories -->
    <?php if (!empty($categories)): ?>
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Browse by Category</h2>
                <p class="text-muted">Find courses in your area of interest</p>
            </div>

            <div class="row">
                <?php
                $category_icons = [
                    'programming' => 'fas fa-code',
                    'web-development' => 'fas fa-globe',
                    'data-science' => 'fas fa-chart-bar',
                    'design' => 'fas fa-paint-brush',
                    'business' => 'fas fa-briefcase',
                    'marketing' => 'fas fa-bullhorn',
                    'photography' => 'fas fa-camera',
                    'music' => 'fas fa-music'
                ];

                foreach (array_slice($categories, 0, 8) as $category):
                    $icon = $category_icons[$category['slug']] ?? 'fas fa-book';
                ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <a href="courses.php?category=<?php echo $category['id']; ?>" class="text-decoration-none">
                            <div class="category-card">
                                <div class="category-icon">
                                    <i class="<?php echo $icon; ?>"></i>
                                </div>
                                <h5 class="mb-2"><?php echo htmlspecialchars($category['name']); ?></h5>
                                <p class="text-muted small mb-0"><?php echo htmlspecialchars($category['description']); ?></p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5><i class="fas fa-graduation-cap me-2"></i><?php echo SITE_NAME; ?></h5>
                    <p class="text-muted">
                        Empowering learners worldwide with quality online education.
                        Learn new skills, advance your career, and achieve your goals.
                    </p>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="courses.php" class="text-muted">Courses</a></li>
                        <li><a href="about.php" class="text-muted">About</a></li>
                        <li><a href="contact.php" class="text-muted">Contact</a></li>
                        <li><a href="help.php" class="text-muted">Help</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6>Categories</h6>
                    <ul class="list-unstyled">
                        <?php foreach (array_slice($categories, 0, 4) as $category): ?>
                            <li><a href="courses.php?category=<?php echo $category['id']; ?>" class="text-muted">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-lg-4 mb-4">
                    <h6>Contact Info</h6>
                    <p class="text-muted mb-1">
                        <i class="fas fa-envelope me-2"></i>
                        <?php echo SITE_EMAIL; ?>
                    </p>
                    <p class="text-muted mb-3">
                        <i class="fas fa-phone me-2"></i>
                        +63 (02) 123-4567
                    </p>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="privacy.php" class="text-muted me-3">Privacy Policy</a>
                    <a href="terms.php" class="text-muted">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
