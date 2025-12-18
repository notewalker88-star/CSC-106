<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Course.php';

// Check if user is admin
requireRole(ROLE_ADMIN);

$course = new Course();
$error_message = '';
$success_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_published':
                if ($course->togglePublished($_POST['course_id'])) {
                    $success_message = 'Course status updated successfully!';
                } else {
                    $error_message = 'Failed to update course status.';
                }
                break;

            case 'delete_course':
                if ($course->delete($_POST['course_id'])) {
                    $success_message = 'Course deleted successfully!';
                } else {
                    $error_message = 'Failed to delete course.';
                }
                break;

            case 'toggle_featured':
                try {
                    $database = new Database();
                    $conn = $database->getConnection();

                    $query = "UPDATE courses SET is_featured = NOT is_featured WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':id', $_POST['course_id']);

                    if ($stmt->execute()) {
                        $success_message = 'Course featured status updated!';
                    } else {
                        $error_message = 'Failed to update featured status.';
                    }
                } catch (Exception $e) {
                    $error_message = 'Error updating featured status.';
                }
                break;
        }
    }
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    switch ($_GET['ajax']) {
        case 'toggle_published':
            $result = $course->togglePublished($_GET['course_id']);
            echo json_encode(['success' => $result]);
            exit;

        case 'toggle_featured':
            try {
                $database = new Database();
                $conn = $database->getConnection();

                $query = "UPDATE courses SET is_featured = NOT is_featured WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':id', $_GET['course_id']);

                $result = $stmt->execute();
                echo json_encode(['success' => $result]);
            } catch (Exception $e) {
                echo json_encode(['success' => false]);
            }
            exit;

        case 'get_course':
            $course_data = $course->getCourseById($_GET['course_id']);
            if ($course_data) {
                echo json_encode(['success' => true, 'course' => $course_data]);
            } else {
                echo json_encode(['success' => false]);
            }
            exit;
    }
}

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build filters
$filters = ['show_unpublished' => true]; // Show all courses for admin
if (!empty($status_filter)) {
    if ($status_filter === 'published') {
        $filters['is_published'] = 1;
    } elseif ($status_filter === 'draft') {
        $filters['is_published'] = 0;
    } elseif ($status_filter === 'featured') {
        $filters['is_featured'] = 1;
    }
}
if (!empty($category_filter)) {
    $filters['category_id'] = $category_filter;
}
if (!empty($search)) {
    $filters['search'] = $search;
}

// Get courses
$courses = $course->getAllCourses($page, $limit, $filters);
$total_courses = $course->getTotalCourses($filters);
$total_pages = ceil($total_courses / $limit);

// Get categories for filter
try {
    $database = new Database();
    $conn = $database->getConnection();

    $query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get course statistics
    $stats = [];

    $query = "SELECT COUNT(*) as count FROM courses";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(*) as count FROM courses WHERE is_published = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['published'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(*) as count FROM courses WHERE is_published = 0";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['draft'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(*) as count FROM courses WHERE is_featured = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['featured'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

} catch (Exception $e) {
    $categories = [];
    $stats = ['total' => 0, 'published' => 0, 'draft' => 0, 'featured' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management - <?php echo SITE_NAME; ?></title>
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
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .course-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            height: 100%;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
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
        .course-status {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .course-featured {
            position: absolute;
            top: 10px;
            left: 10px;
        }
        .course-price {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
        }
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
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
            <h1><i class="fas fa-book me-2"></i>Course Management</h1>
            <div>
                <a href="../instructor/create-course.php" class="btn btn-success me-2">
                    <i class="fas fa-plus me-2"></i>Add Course
                </a>
                <button class="btn btn-primary" onclick="refreshCourses()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo number_format($stats['total']); ?></h3>
                                <p class="mb-0">Total Courses</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-book fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo number_format($stats['published']); ?></h3>
                                <p class="mb-0">Published</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo number_format($stats['draft']); ?></h3>
                                <p class="mb-0">Pending Review</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo number_format($stats['featured']); ?></h3>
                                <p class="mb-0">Featured</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-star fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Filter by Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft/Pending</option>
                            <option value="featured" <?php echo $status_filter === 'featured' ? 'selected' : ''; ?>>Featured</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="category" class="form-label">Filter by Category</label>
                        <select name="category" id="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Courses</label>
                        <input type="text" name="search" id="search" class="form-control"
                               placeholder="Search by title or description..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Courses Grid -->
        <div class="row">
            <?php if (!empty($courses)): ?>
                <?php foreach ($courses as $c): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card course-card">
                            <div class="course-thumbnail">
                                <?php if ($c['thumbnail'] && file_exists('../uploads/courses/' . $c['thumbnail'])): ?>
                                    <img src="../uploads/courses/<?php echo htmlspecialchars($c['thumbnail']); ?>"
                                         alt="Course Thumbnail" class="w-100 h-100" style="object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-play-circle"></i>
                                <?php endif; ?>

                                <!-- Status Badge -->
                                <div class="course-status">
                                    <span class="badge bg-<?php echo $c['is_published'] ? 'success' : 'warning'; ?>">
                                        <?php echo $c['is_published'] ? 'Published' : 'Draft'; ?>
                                    </span>
                                </div>

                                <!-- Featured Badge -->
                                <?php if ($c['is_featured']): ?>
                                    <div class="course-featured">
                                        <span class="badge bg-danger">
                                            <i class="fas fa-star me-1"></i>Featured
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <!-- Price -->
                                <div class="course-price">
                                    <?php echo $c['is_free'] ? 'Free' : formatCurrency($c['price']); ?>
                                </div>
                            </div>

                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($c['title']); ?></h5>
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                                    <span class="ms-2">
                                        <i class="fas fa-tag me-1"></i>
                                        <?php echo htmlspecialchars($c['category_name']); ?>
                                    </span>
                                </p>
                                <p class="card-text small">
                                    <?php echo htmlspecialchars(substr($c['short_description'] ?: $c['description'], 0, 100)) . '...'; ?>
                                </p>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $c['rating_average'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                        <small class="text-muted ms-1">(<?php echo $c['rating_count']; ?>)</small>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-users me-1"></i><?php echo $c['enrollment_count']; ?> enrolled
                                    </small>
                                </div>

                                <div class="btn-group w-100" role="group">
                                    <a href="../course.php?id=<?php echo $c['id']; ?>" class="btn btn-outline-primary btn-sm" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="btn btn-outline-<?php echo $c['is_published'] ? 'warning' : 'success'; ?> btn-sm"
                                            onclick="togglePublished(<?php echo $c['id']; ?>)"
                                            title="<?php echo $c['is_published'] ? 'Unpublish' : 'Publish'; ?>">
                                        <i class="fas fa-<?php echo $c['is_published'] ? 'eye-slash' : 'check'; ?>"></i>
                                    </button>
                                    <button class="btn btn-outline-<?php echo $c['is_featured'] ? 'danger' : 'info'; ?> btn-sm"
                                            onclick="toggleFeatured(<?php echo $c['id']; ?>)"
                                            title="<?php echo $c['is_featured'] ? 'Remove from Featured' : 'Add to Featured'; ?>">
                                        <i class="fas fa-star"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm"
                                            onclick="deleteCourse(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['title']); ?>')"
                                            title="Delete Course">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="card-footer bg-light">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    Created <?php echo timeAgo($c['created_at']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-book fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No courses found</h5>
                        <p class="text-muted">Try adjusting your search criteria or encourage instructors to create courses.</p>
                        <a href="../instructor/create-course.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Create First Course
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Courses pagination" class="mt-4">
                <?php
                $params = [];
                if ($status_filter) $params['status'] = $status_filter;
                if ($category_filter) $params['category'] = $category_filter;
                if ($search) $params['search'] = $search;
                echo generatePagination($page, $total_pages, 'courses.php', $params);
                ?>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePublished(courseId) {
            if (confirm('Are you sure you want to change the publication status of this course?')) {
                fetch(`courses.php?ajax=toggle_published&course_id=${courseId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Failed to update course status');
                        }
                    });
            }
        }

        function toggleFeatured(courseId) {
            if (confirm('Are you sure you want to change the featured status of this course?')) {
                fetch(`courses.php?ajax=toggle_featured&course_id=${courseId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Failed to update featured status');
                        }
                    });
            }
        }

        function deleteCourse(courseId, courseTitle) {
            if (confirm(`Are you sure you want to delete the course "${courseTitle}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_course">
                    <input type="hidden" name="course_id" value="${courseId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function refreshCourses() {
            location.reload();
        }

        // Auto-refresh every 5 minutes
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 300000);
    </script>
</body>
</html>
