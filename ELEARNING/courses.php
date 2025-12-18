<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Course.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/InstructorReview.php';

// Get filters from URL
$category_filter = $_GET['category'] ?? '';
$level_filter = $_GET['level'] ?? '';
$price_filter = $_GET['price'] ?? '';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;

// Get categories for filter dropdown
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

// Build filters for course query
$filters = ['is_published' => 1]; // Only show published courses
if (!empty($category_filter)) {
    $filters['category_id'] = $category_filter;
}
if (!empty($level_filter)) {
    $filters['level'] = $level_filter;
}
if ($price_filter === 'free') {
    $filters['is_free'] = 1;
} elseif ($price_filter === 'paid') {
    $filters['is_free'] = 0;
}
if (!empty($search)) {
    $filters['search'] = $search;
}

// Get courses
$course = new Course();
$courses = $course->getAllCourses($page, $limit, $filters);

// Get total count for pagination
try {
    $count_query = "SELECT COUNT(*) as total FROM courses c
                    LEFT JOIN categories cat ON c.category_id = cat.id
                    LEFT JOIN users u ON c.instructor_id = u.id
                    WHERE c.is_published = 1";

    $count_params = [];

    if (!empty($category_filter)) {
        $count_query .= " AND c.category_id = :category_id";
        $count_params[':category_id'] = $category_filter;
    }
    if (!empty($level_filter)) {
        $count_query .= " AND c.level = :level";
        $count_params[':level'] = $level_filter;
    }
    if ($price_filter === 'free') {
        $count_query .= " AND c.is_free = 1";
    } elseif ($price_filter === 'paid') {
        $count_query .= " AND c.is_free = 0";
    }
    if (!empty($search)) {
        $count_query .= " AND (c.title LIKE :search OR c.short_description LIKE :search OR c.description LIKE :search)";
        $count_params[':search'] = '%' . $search . '%';
    }

    $stmt = $conn->prepare($count_query);
    foreach ($count_params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_courses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_courses / $limit);
} catch (Exception $e) {
    $total_courses = 0;
    $total_pages = 1;
}

// Check if user is enrolled in courses (for logged in users)
$user_enrollments = [];
if (isLoggedIn()) {
    try {
        $query = "SELECT course_id FROM enrollments WHERE student_id = :student_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $_SESSION['user_id']);
        $stmt->execute();
        $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $user_enrollments = array_column($enrollments, 'course_id');
    } catch (Exception $e) {
        $user_enrollments = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Courses - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
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
        }

        .course-price {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .course-level {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(255,255,255,0.9);
            color: #333;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .filter-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
        }

        .enrolled-badge {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(40, 167, 69, 0.9);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap me-2"></i><?php echo SITE_NAME; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="courses.php">Courses</a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <?php
                        // Get user profile for avatar display
                        $nav_user = new User();
                        $nav_user->getUserById($_SESSION['user_id']);
                        ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <?php echo $nav_user->getAvatarHtml(30, 'rounded-circle me-2'); ?>
                                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if (hasRole(ROLE_STUDENT)): ?>
                                    <li><a class="dropdown-item" href="student/index.php">
                                        <i class="fas fa-tachometer-alt me-2"></i>Student Dashboard
                                    </a></li>
                                <?php endif; ?>
                                <?php if (hasRole(ROLE_INSTRUCTOR)): ?>
                                    <li><a class="dropdown-item" href="instructor/index.php">
                                        <i class="fas fa-chalkboard-teacher me-2"></i>Instructor Dashboard
                                    </a></li>
                                <?php endif; ?>
                                <?php if (hasRole(ROLE_ADMIN)): ?>
                                    <li><a class="dropdown-item" href="admin/index.php">
                                        <i class="fas fa-cog me-2"></i>Admin Dashboard
                                    </a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user me-2"></i>My Profile
                                </a></li>
                                <li><a class="dropdown-item" href="auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Error Messages -->
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php
                switch ($_GET['error']) {
                    case 'insufficient_progress':
                        echo 'You need at least 50% progress in a course to rate the instructor.';
                        break;
                    case 'invalid_instructor':
                        echo 'Invalid instructor selected.';
                        break;
                    case 'instructor_not_found':
                        echo 'Instructor not found.';
                        break;
                    case 'database_error':
                        echo 'A database error occurred. Please try again later.';
                        break;
                    default:
                        echo 'An error occurred.';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="fas fa-book me-2"></i>Browse Courses</h1>
                <p class="text-muted">Discover and enroll in courses to enhance your skills</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card filter-card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search"
                                       value="<?php echo htmlspecialchars($search); ?>" placeholder="Search courses...">
                            </div>
                            <div class="col-md-2">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"
                                                <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="level" class="form-label">Level</label>
                                <select class="form-select" id="level" name="level">
                                    <option value="">All Levels</option>
                                    <option value="beginner" <?php echo $level_filter == 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                    <option value="intermediate" <?php echo $level_filter == 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                    <option value="advanced" <?php echo $level_filter == 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="price" class="form-label">Price</label>
                                <select class="form-select" id="price" name="price">
                                    <option value="">All Prices</option>
                                    <option value="free" <?php echo $price_filter == 'free' ? 'selected' : ''; ?>>Free</option>
                                    <option value="paid" <?php echo $price_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="courses.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Info -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <p class="text-muted mb-0">
                        Showing <?php echo count($courses); ?> of <?php echo $total_courses; ?> courses
                        <?php if (!empty($search)): ?>
                            for "<?php echo htmlspecialchars($search); ?>"
                        <?php endif; ?>
                    </p>
                    <div class="text-muted">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Courses Grid -->
        <?php if (!empty($courses)): ?>
            <div class="row">
                <?php foreach ($courses as $course_item): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card course-card">
                            <div class="course-thumbnail">
                                <?php if ($course_item['thumbnail'] && file_exists('uploads/courses/' . $course_item['thumbnail'])): ?>
                                    <img src="uploads/courses/<?php echo htmlspecialchars($course_item['thumbnail']); ?>"
                                         alt="Course Thumbnail" class="w-100 h-100" style="object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-book"></i>
                                <?php endif; ?>

                                <div class="course-level"><?php echo ucfirst($course_item['level']); ?></div>
                                <div class="course-price">
                                    <?php echo $course_item['is_free'] ? 'Free' : formatCurrency($course_item['price']); ?>
                                </div>

                                <?php if (in_array($course_item['id'], $user_enrollments)): ?>
                                    <div class="enrolled-badge">
                                        <i class="fas fa-check me-1"></i>Enrolled
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($course_item['title']); ?></h5>
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-user me-1"></i>
                                    <a href="instructor-profile.php?id=<?php echo $course_item['instructor_id']; ?>"
                                       class="text-decoration-none text-muted">
                                        <?php echo htmlspecialchars($course_item['first_name'] . ' ' . $course_item['last_name']); ?>
                                    </a>
                                    <?php
                                    // Get instructor rating
                                    $instructorReview = new InstructorReview();
                                    $instructor_rating = $instructorReview->getInstructorRatingStats($course_item['instructor_id']);
                                    if ($instructor_rating['total_reviews'] > 0):
                                    ?>
                                        <span class="text-warning ms-2">
                                            <i class="fas fa-star"></i>
                                            <?php echo number_format($instructor_rating['average_rating'], 1); ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-tag me-1"></i>
                                    <?php echo htmlspecialchars($course_item['category_name']); ?>
                                </p>
                                <p class="card-text"><?php echo htmlspecialchars(substr($course_item['short_description'], 0, 100)) . '...'; ?></p>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $course_item['rating_average'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                        <small class="text-muted ms-1">(<?php echo number_format($course_item['rating_average'], 1); ?>)</small>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo $course_item['duration_hours'] ? $course_item['duration_hours'] . ' hours' : '2-3 hours'; ?>
                                    </small>
                                </div>

                                <div class="d-grid">
                                    <?php if (in_array($course_item['id'], $user_enrollments)): ?>
                                        <a href="student/courses.php" class="btn btn-success">
                                            <i class="fas fa-play me-2"></i>Continue Learning
                                        </a>
                                    <?php elseif (isLoggedIn() && hasRole(ROLE_STUDENT)): ?>
                                        <a href="course.php?id=<?php echo $course_item['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-eye me-2"></i>View Course
                                        </a>
                                    <?php else: ?>
                                        <a href="course.php?id=<?php echo $course_item['id']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-eye me-2"></i>View Details
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <nav aria-label="Course pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Empty State -->
            <div class="row">
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-5x text-muted mb-4"></i>
                        <h3 class="text-muted mb-3">No Courses Found</h3>
                        <p class="text-muted mb-4">
                            <?php if (!empty($search) || !empty($category_filter) || !empty($level_filter) || !empty($price_filter)): ?>
                                Try adjusting your filters to find more courses.
                            <?php else: ?>
                                No courses are available at the moment. Please check back later.
                            <?php endif; ?>
                        </p>
                        <a href="courses.php" class="btn btn-primary">
                            <i class="fas fa-refresh me-2"></i>View All Courses
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>