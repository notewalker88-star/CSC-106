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
$course = new Course();

// Handle form submissions
$success_message = '';
$error_message = '';

// Check for success message from course creation
if (isset($_GET['success']) && $_GET['success'] == 'created') {
    $success_message = 'Course created successfully! You can now add lessons and publish your course.';
}

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
    }
}

// Get instructor's courses
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$courses = $course->getCoursesByInstructor($instructor_id, $page, $limit);

// Get total count for pagination
try {
    $database = new Database();
    $conn = $database->getConnection();

    $query = "SELECT COUNT(*) as total FROM courses WHERE instructor_id = :instructor_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $total_courses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_courses / $limit);
} catch (Exception $e) {
    $total_courses = 0;
    $total_pages = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Instructor Dashboard</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

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

        .course-card {
            transition: transform 0.3s ease;
        }

        .course-card:hover {
            transform: translateY(-5px);
        }

        .course-thumbnail {
            height: 200px;
            background-size: cover;
            background-position: center;
            border-radius: 10px;
            position: relative;
        }

        .course-status {
            position: absolute;
            top: 10px;
            right: 10px;
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
            <h1><i class="fas fa-book me-2"></i>My Courses</h1>
            <div>
                <a href="create-course.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create New Course
                </a>
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

        <!-- Courses Grid -->
        <?php if (!empty($courses)): ?>
            <div class="row">
                <?php foreach ($courses as $course_item): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card course-card h-100">
                            <div class="course-thumbnail" style="background-image: url('<?php
                                if ($course_item['thumbnail'] && file_exists(UPLOAD_PATH . 'courses/' . $course_item['thumbnail'])) {
                                    echo SITE_URL . '/uploads/courses/' . htmlspecialchars($course_item['thumbnail']);
                                } else {
                                    echo SITE_URL . '/assets/images/default-course.jpg';
                                }
                            ?>');">
                                <div class="course-status">
                                    <?php if ($course_item['is_published']): ?>
                                        <span class="badge bg-success">Published</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Draft</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($course_item['title']); ?></h5>
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-tag me-1"></i>
                                    <?php echo htmlspecialchars($course_item['category_name']); ?>
                                </p>
                                <p class="card-text"><?php echo htmlspecialchars(substr($course_item['short_description'], 0, 100)) . '...'; ?></p>

                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <small class="text-muted">Students</small>
                                        <div class="fw-bold"><?php echo $course_item['enrollment_count']; ?></div>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Rating</small>
                                        <div class="fw-bold"><?php echo number_format($course_item['rating_average'], 1); ?></div>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Price</small>
                                        <div class="fw-bold">
                                            <?php echo $course_item['is_free'] ? 'Free' : formatCurrency($course_item['price']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="d-flex justify-content-between">
                                    <div class="btn-group" role="group">
                                        <a href="edit-course.php?id=<?php echo $course_item['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="lessons.php?course_id=<?php echo $course_item['id']; ?>" class="btn btn-outline-info btn-sm">
                                            <i class="fas fa-play-circle"></i>
                                        </a>
                                    </div>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-outline-<?php echo $course_item['is_published'] ? 'warning' : 'success'; ?> btn-sm"
                                                onclick="togglePublished(<?php echo $course_item['id']; ?>)">
                                            <i class="fas fa-<?php echo $course_item['is_published'] ? 'eye-slash' : 'eye'; ?>"></i>
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm"
                                                onclick="deleteCourse(<?php echo $course_item['id']; ?>, '<?php echo htmlspecialchars($course_item['title']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Courses pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <!-- Empty State -->
            <div class="text-center py-5">
                <i class="fas fa-book fa-5x text-muted mb-4"></i>
                <h3 class="text-muted mb-3">No Courses Yet</h3>
                <p class="text-muted mb-4">You haven't created any courses yet. Start by creating your first course!</p>
                <a href="create-course.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>Create Your First Course
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the course "<span id="courseTitle"></span>"?</p>
                    <p class="text-danger"><strong>This action cannot be undone!</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_course">
                        <input type="hidden" name="course_id" id="deleteCourseId">
                        <button type="submit" class="btn btn-danger">Delete Course</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function togglePublished(courseId) {
            fetch(`?ajax=toggle_published&course_id=${courseId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Failed to update course status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred');
                });
        }

        function deleteCourse(courseId, courseTitle) {
            document.getElementById('courseTitle').textContent = courseTitle;
            document.getElementById('deleteCourseId').value = courseId;

            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>
