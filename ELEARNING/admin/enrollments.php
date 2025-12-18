<?php
/**
 * Admin Enrollments Management
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
requireLogin();
if (!hasRole(ROLE_ADMIN)) {
    redirect('/index.php');
}

$database = new Database();
$conn = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'cancel_enrollment':
                $enrollment_id = (int)$_POST['enrollment_id'];

                // Get enrollment details
                $query = "SELECT e.*, c.title as course_title, u.first_name, u.last_name
                         FROM enrollments e
                         JOIN courses c ON e.course_id = c.id
                         JOIN users u ON e.student_id = u.id
                         WHERE e.id = :enrollment_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':enrollment_id', $enrollment_id);
                $stmt->execute();
                $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($enrollment) {
                    // Delete enrollment
                    $query = "DELETE FROM enrollments WHERE id = :enrollment_id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':enrollment_id', $enrollment_id);

                    if ($stmt->execute()) {
                        // Update course enrollment count
                        $query = "UPDATE courses SET enrollment_count = GREATEST(enrollment_count - 1, 0) WHERE id = :course_id";
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':course_id', $enrollment['course_id']);
                        $stmt->execute();

                        redirectWithMessage('enrollments.php', 'Enrollment cancelled successfully!', 'success');
                    } else {
                        $error_message = 'Failed to cancel enrollment.';
                    }
                } else {
                    $error_message = 'Enrollment not found.';
                }
                break;

            case 'update_payment_status':
                $enrollment_id = (int)$_POST['enrollment_id'];
                $payment_status = sanitizeInput($_POST['payment_status']);

                $query = "UPDATE enrollments SET payment_status = :payment_status WHERE id = :enrollment_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':payment_status', $payment_status);
                $stmt->bindParam(':enrollment_id', $enrollment_id);

                if ($stmt->execute()) {
                    redirectWithMessage('enrollments.php', 'Payment status updated successfully!', 'success');
                } else {
                    $error_message = 'Failed to update payment status.';
                }
                break;
        }
    }
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    switch ($_GET['ajax']) {
        case 'get_enrollment':
            $enrollment_id = (int)$_GET['enrollment_id'];
            $query = "SELECT e.*, c.title as course_title, u.first_name, u.last_name, u.email
                     FROM enrollments e
                     JOIN courses c ON e.course_id = c.id
                     JOIN users u ON e.student_id = u.id
                     WHERE e.id = :enrollment_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':enrollment_id', $enrollment_id);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'enrollment' => $enrollment]);
            } else {
                echo json_encode(['success' => false]);
            }
            exit;
    }
}

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$course_filter = $_GET['course'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$search = $_GET['search'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($course_filter)) {
    $where_conditions[] = "c.id = :course_filter";
    $params[':course_filter'] = $course_filter;
}

if (!empty($status_filter)) {
    if ($status_filter === 'completed') {
        $where_conditions[] = "e.is_completed = 1";
    } elseif ($status_filter === 'in_progress') {
        $where_conditions[] = "e.is_completed = 0";
    }
}

if (!empty($payment_filter)) {
    $where_conditions[] = "e.payment_status = :payment_filter";
    $params[':payment_filter'] = $payment_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR c.title LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get enrollments
$query = "SELECT e.*, c.title as course_title, c.price as course_price, c.is_free,
                 u.first_name, u.last_name, u.email, u.profile_image,
                 cat.name as category_name
          FROM enrollments e
          JOIN courses c ON e.course_id = c.id
          JOIN users u ON e.student_id = u.id
          JOIN categories cat ON c.category_id = cat.id
          {$where_clause}
          ORDER BY e.enrollment_date DESC
          LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$count_query = "SELECT COUNT(*) as total
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                JOIN users u ON e.student_id = u.id
                JOIN categories cat ON c.category_id = cat.id
                {$where_clause}";

$stmt = $conn->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_enrollments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_enrollments / $limit);

// Get statistics
$stats_query = "SELECT
    COUNT(*) as total_enrollments,
    COUNT(CASE WHEN e.is_completed = 1 THEN 1 END) as completed_enrollments,
    COUNT(CASE WHEN e.payment_status = 'completed' THEN 1 END) as paid_enrollments,
    SUM(CASE WHEN e.payment_status = 'completed' THEN e.payment_amount ELSE 0 END) as total_revenue,
    COUNT(CASE WHEN DATE(e.enrollment_date) = CURDATE() THEN 1 END) as today_enrollments
FROM enrollments e";

$stmt = $conn->prepare($stats_query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get courses for filter dropdown
$courses_query = "SELECT id, title FROM courses ORDER BY title";
$stmt = $conn->prepare($courses_query);
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Management - <?php echo SITE_NAME; ?></title>
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
            margin-left: 280px;
            padding: 20px;
        }
        .stats-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .status-badge {
            font-size: 0.75rem;
        }
        .enrollment-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-user-graduate me-2"></i>Enrollment Management</h1>
            <div>
                <button class="btn btn-primary" onclick="refreshPage()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php displayFlashMessage(); ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2 mb-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-user-graduate fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo number_format($stats['total_enrollments']); ?></h4>
                        <small>Total Enrollments</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo number_format($stats['completed_enrollments']); ?></h4>
                        <small>Completed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-credit-card fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo number_format($stats['paid_enrollments']); ?></h4>
                        <small>Paid</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-peso-sign fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo formatCurrency($stats['total_revenue']); ?></h4>
                        <small>Total Revenue</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-secondary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-day fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo number_format($stats['today_enrollments']); ?></h4>
                        <small>Today's Enrollments</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i>Filters
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search"
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Student name, email, course...">
                    </div>
                    <div class="col-md-2">
                        <label for="course" class="form-label">Course</label>
                        <select class="form-select" id="course" name="course">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>"
                                        <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="payment" class="form-label">Payment</label>
                        <select class="form-select" id="payment" name="payment">
                            <option value="">All Payments</option>
                            <option value="completed" <?php echo $payment_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="pending" <?php echo $payment_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="failed" <?php echo $payment_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="refunded" <?php echo $payment_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-1"></i>Filter
                        </button>
                        <a href="enrollments.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Enrollments Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Enrollments List
                    <span class="badge bg-secondary ms-2"><?php echo number_format($total_enrollments); ?> total</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($enrollments)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Category</th>
                                    <th>Enrolled</th>
                                    <th>Progress</th>
                                    <th>Payment</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrollments as $enrollment): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php
                                                $avatar_url = SITE_URL . '/assets/images/default-avatar.png';
                                                if ($enrollment['profile_image'] && file_exists(UPLOAD_PATH . 'profiles/' . $enrollment['profile_image'])) {
                                                    $avatar_url = SITE_URL . '/uploads/profiles/' . $enrollment['profile_image'];
                                                }
                                                ?>
                                                <img src="<?php echo $avatar_url; ?>"
                                                     alt="Avatar" class="user-avatar me-3"
                                                     onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-avatar.png'">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($enrollment['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($enrollment['course_title']); ?></div>
                                            <small class="text-muted">
                                                <?php echo $enrollment['is_free'] ? 'Free Course' : 'Paid Course'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($enrollment['category_name']); ?></span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($enrollment['enrollment_date'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress me-2" style="width: 60px; height: 8px;">
                                                    <div class="progress-bar bg-success"
                                                         style="width: <?php echo $enrollment['progress_percentage']; ?>%"></div>
                                                </div>
                                                <small><?php echo number_format($enrollment['progress_percentage'], 1); ?>%</small>
                                            </div>
                                            <?php if ($enrollment['is_completed']): ?>
                                                <span class="badge bg-success status-badge">Completed</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning status-badge">In Progress</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo $enrollment['payment_status'] === 'completed' ? 'success' :
                                                    ($enrollment['payment_status'] === 'pending' ? 'warning' :
                                                    ($enrollment['payment_status'] === 'failed' ? 'danger' : 'secondary'));
                                            ?>">
                                                <?php echo ucfirst($enrollment['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo formatCurrency($enrollment['payment_amount']); ?></strong>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-info"
                                                        onclick="viewEnrollment(<?php echo $enrollment['id']; ?>)"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-warning"
                                                        onclick="editPaymentStatus(<?php echo $enrollment['id']; ?>, '<?php echo $enrollment['payment_status']; ?>')"
                                                        title="Edit Payment Status">
                                                    <i class="fas fa-credit-card"></i>
                                                </button>
                                                <button class="btn btn-outline-danger"
                                                        onclick="cancelEnrollment(<?php echo $enrollment['id']; ?>, '<?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($enrollment['course_title'], ENT_QUOTES); ?>')"
                                                        title="Cancel Enrollment">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Enrollments pagination" class="mt-4">
                            <?php
                            $params = [];
                            if ($course_filter) $params['course'] = $course_filter;
                            if ($status_filter) $params['status'] = $status_filter;
                            if ($payment_filter) $params['payment'] = $payment_filter;
                            if ($search) $params['search'] = $search;
                            echo generatePagination($page, $total_pages, 'enrollments.php', $params);
                            ?>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No enrollments found</h5>
                        <p class="text-muted">Try adjusting your search criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Enrollment Modal -->
    <div class="modal fade" id="viewEnrollmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>Enrollment Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="enrollmentDetails">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Payment Status Modal -->
    <div class="modal fade" id="editPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-credit-card me-2"></i>Edit Payment Status
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editPaymentForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_payment_status">
                        <input type="hidden" name="enrollment_id" id="edit_enrollment_id">

                        <div class="mb-3">
                            <label for="edit_payment_status" class="form-label">Payment Status</label>
                            <select class="form-select" id="edit_payment_status" name="payment_status" required>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                                <option value="refunded">Refunded</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshPage() {
            location.reload();
        }

        function viewEnrollment(enrollmentId) {
            fetch(`enrollments.php?ajax=get_enrollment&enrollment_id=${enrollmentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const enrollment = data.enrollment;
                        const details = `
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Student Information</h6>
                                    <p><strong>Name:</strong> ${enrollment.first_name} ${enrollment.last_name}</p>
                                    <p><strong>Email:</strong> ${enrollment.email}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Course Information</h6>
                                    <p><strong>Course:</strong> ${enrollment.course_title}</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Enrollment Details</h6>
                                    <p><strong>Enrolled:</strong> ${new Date(enrollment.enrollment_date).toLocaleDateString()}</p>
                                    <p><strong>Progress:</strong> ${parseFloat(enrollment.progress_percentage).toFixed(1)}%</p>
                                    <p><strong>Status:</strong> ${enrollment.is_completed == 1 ? 'Completed' : 'In Progress'}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Payment Information</h6>
                                    <p><strong>Amount:</strong> ₱${parseFloat(enrollment.payment_amount).toFixed(2)}</p>
                                    <p><strong>Status:</strong> ${enrollment.payment_status.charAt(0).toUpperCase() + enrollment.payment_status.slice(1)}</p>
                                </div>
                            </div>
                        `;
                        document.getElementById('enrollmentDetails').innerHTML = details;
                        new bootstrap.Modal(document.getElementById('viewEnrollmentModal')).show();
                    } else {
                        alert('Failed to load enrollment details');
                    }
                })
                .catch(error => {
                    alert('An error occurred while loading enrollment details');
                    console.error('Error:', error);
                });
        }

        function editPaymentStatus(enrollmentId, currentStatus) {
            document.getElementById('edit_enrollment_id').value = enrollmentId;
            document.getElementById('edit_payment_status').value = currentStatus;
            new bootstrap.Modal(document.getElementById('editPaymentModal')).show();
        }

        function cancelEnrollment(enrollmentId, studentName, courseName) {
            if (confirm(`Are you sure you want to cancel the enrollment of "${studentName}" in "${courseName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancel_enrollment">
                    <input type="hidden" name="enrollment_id" value="${enrollmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
