<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Course.php';
require_once __DIR__ . '/classes/User.php';

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

// Get some statistics
$course = new Course();
$total_courses = $course->getTotalCourses();
$total_students = $course->getTotalStudents();
$total_instructors = $course->getTotalInstructors();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - <?php echo SITE_NAME; ?></title>
    <meta name="description" content="Learn more about <?php echo SITE_NAME; ?> - our mission, vision, and commitment to quality online education.">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .feature-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
        }

        .stats-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            display: block;
        }

        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .team-card {
            transition: transform 0.3s ease;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .team-card:hover {
            transform: translateY(-5px);
        }

        .team-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
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
                        <a class="nav-link" href="<?php echo SITE_URL; ?>">Home</a>
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
                        <a class="nav-link active" href="about.php">About</a>
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
                                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
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

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <div class="hero-content">
                        <h1 class="display-4 fw-bold mb-4">About <?php echo SITE_NAME; ?></h1>
                        <p class="lead mb-4">
                            Empowering learners worldwide with quality online education.
                            We believe that everyone deserves access to world-class learning opportunities.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Content -->
    <section class="py-5">
        <div class="container">
            <div class="row align-items-center mb-5">
                <div class="col-lg-6">
                    <h2 class="mb-4">Our Mission</h2>
                    <p class="lead mb-4">
                        To democratize education by providing accessible, high-quality online learning experiences
                        that empower individuals to achieve their personal and professional goals.
                    </p>
                    <p>
                        We are committed to breaking down barriers to education and creating a platform where
                        knowledge knows no boundaries. Our comprehensive e-learning system connects passionate
                        instructors with eager learners from around the globe.
                    </p>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-graduation-cap" style="font-size: 8rem; color: #667eea; opacity: 0.8;"></i>
                    </div>
                </div>
            </div>

            <div class="row align-items-center">
                <div class="col-lg-6 order-lg-2">
                    <h2 class="mb-4">Our Vision</h2>
                    <p class="lead mb-4">
                        To become the leading global platform for online education, fostering a community
                        of lifelong learners and expert educators.
                    </p>
                    <p>
                        We envision a world where quality education is accessible to everyone, regardless of
                        their location, background, or circumstances. Through innovative technology and
                        pedagogical excellence, we strive to make learning engaging, effective, and enjoyable.
                    </p>
                </div>
                <div class="col-lg-6 order-lg-1">
                    <div class="text-center">
                        <i class="fas fa-globe" style="font-size: 8rem; color: #764ba2; opacity: 0.8;"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="mb-4">Why Choose <?php echo SITE_NAME; ?>?</h2>
                    <p class="lead">Discover the features that make our platform the perfect choice for your learning journey.</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <h5 class="card-title">Interactive Learning</h5>
                            <p class="card-text">
                                Engage with multimedia content, interactive quizzes, and hands-on projects
                                that make learning both effective and enjoyable.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5 class="card-title">Expert Instructors</h5>
                            <p class="card-text">
                                Learn from industry professionals and subject matter experts who bring
                                real-world experience to every lesson.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h5 class="card-title">Learn at Your Pace</h5>
                            <p class="card-text">
                                Access courses 24/7 and learn at your own schedule. Perfect for busy
                                professionals and students alike.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-certificate"></i>
                            </div>
                            <h5 class="card-title">Certificates</h5>
                            <p class="card-text">
                                Earn certificates upon course completion to showcase your new skills
                                and advance your career prospects.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <h5 class="card-title">Mobile Friendly</h5>
                            <p class="card-text">
                                Access your courses from any device - desktop, tablet, or smartphone.
                                Learn anywhere, anytime.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <h5 class="card-title">Community Support</h5>
                            <p class="card-text">
                                Join a vibrant community of learners. Participate in discussions,
                                ask questions, and share knowledge.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="mb-4">Our Impact in Numbers</h2>
                    <p class="lead">Join thousands of learners who have already transformed their lives through our platform.</p>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($total_courses); ?>+</span>
                        <span class="stat-label">Courses Available</span>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($total_students); ?>+</span>
                        <span class="stat-label">Active Students</span>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($total_instructors); ?>+</span>
                        <span class="stat-label">Expert Instructors</span>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <span class="stat-number">95%</span>
                        <span class="stat-label">Satisfaction Rate</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="mb-4">Meet Our Team</h2>
                    <p class="lead">Passionate educators and technology experts dedicated to your success.</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card team-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="team-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <h5 class="card-title">Dr. Maria Santos</h5>
                            <p class="text-muted mb-3">Chief Education Officer</p>
                            <p class="card-text">
                                With over 15 years in educational technology, Dr. Santos leads our
                                curriculum development and ensures the highest quality learning experiences.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card team-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="team-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <h5 class="card-title">John Rodriguez</h5>
                            <p class="text-muted mb-3">Head of Technology</p>
                            <p class="card-text">
                                A seasoned software architect who ensures our platform remains cutting-edge,
                                secure, and user-friendly for learners worldwide.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card team-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="team-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <h5 class="card-title">Sarah Chen</h5>
                            <p class="text-muted mb-3">Student Success Manager</p>
                            <p class="card-text">
                                Dedicated to helping every student achieve their goals, Sarah oversees
                                our support systems and community engagement initiatives.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <h2 class="mb-4">Ready to Start Learning?</h2>
                    <p class="lead mb-4">
                        Join our community of learners and start your educational journey today.
                        Discover courses that will help you achieve your goals.
                    </p>
                    <div class="d-flex flex-wrap gap-3 justify-content-center">
                        <a href="courses.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-search me-2"></i>Browse Courses
                        </a>
                        <?php if (!isLoggedIn()): ?>
                            <a href="auth/register.php" class="btn btn-outline-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i>Sign Up Free
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
                    <div class="social-links">
                        <a href="#" class="text-muted me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-muted me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-muted me-3"><i class="fab fa-linkedin fa-lg"></i></a>
                        <a href="#" class="text-muted"><i class="fab fa-youtube fa-lg"></i></a>
                    </div>
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