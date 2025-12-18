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

// Initialize message variables
$success_message = '';
$error_message = '';

// Get instructor profile information
$instructor_user = new User();
$instructor_user->getUserById($instructor_id);

// Handle form submissions for removing students from courses
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'remove_student') {
    $student_id = (int)$_POST['student_id'];
    $course_id = (int)$_POST['course_id'];
    
    try {
        // Verify that the course belongs to this instructor
        $database = new Database();
        $conn = $database->getConnection();
        
        $query = "SELECT id FROM courses WHERE id = :course_id AND instructor_id = :instructor_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->bindParam(':instructor_id', $instructor_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            $error_message = 'Invalid course or unauthorized access.';
        } else {
            // Remove student from course using existing function
            $result = unenrollStudent($student_id, $course_id);
            
            if ($result['success']) {
                $success_message = 'Student successfully removed from the course.';
            } else {
                $error_message = $result['message'];
            }
        }
    } catch (Exception $e) {
        $error_message = 'An error occurred while removing the student.';
    }
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    switch ($_GET['ajax']) {
        case 'get_student_details':
            $student_id = (int)$_GET['student_id'];

            try {
                $database = new Database();
                $conn = $database->getConnection();

                // Get student basic info
                $query = "SELECT u.*, DATE(u.created_at) as join_date
                         FROM users u
                         WHERE u.id = :student_id AND u.role = 'student'";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':student_id', $student_id);
                $stmt->execute();
                $student = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$student) {
                    echo json_encode(['success' => false, 'message' => 'Student not found']);
                    exit;
                }

                // Get student's enrollments in instructor's courses
                $query = "SELECT c.id, c.title, c.price, c.is_free, e.enrollment_date,
                                e.progress_percentage, e.is_completed, e.payment_amount, e.payment_status,
                                cat.name as category_name
                         FROM enrollments e
                         JOIN courses c ON e.course_id = c.id
                         JOIN categories cat ON c.category_id = cat.id
                         WHERE e.student_id = :student_id AND c.instructor_id = :instructor_id
                         ORDER BY e.enrollment_date DESC";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':student_id', $student_id);
                $stmt->bindParam(':instructor_id', $instructor_id);
                $stmt->execute();
                $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculate statistics
                $total_courses = count($enrollments);
                $completed_courses = array_filter($enrollments, function($e) { return $e['is_completed']; });
                $avg_progress = $total_courses > 0 ? array_sum(array_column($enrollments, 'progress_percentage')) / $total_courses : 0;
                $total_paid = array_sum(array_column($enrollments, 'payment_amount'));

                echo json_encode([
                    'success' => true,
                    'student' => $student,
                    'enrollments' => $enrollments,
                    'stats' => [
                        'total_courses' => $total_courses,
                        'completed_courses' => count($completed_courses),
                        'avg_progress' => round($avg_progress, 1),
                        'total_paid' => $total_paid
                    ]
                ]);

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading student details']);
            }
            exit;
            
        case 'remove_student_from_course':
            $student_id = (int)$_GET['student_id'];
            $course_id = (int)$_GET['course_id'];
            
            try {
                // Verify that the course belongs to this instructor
                $database = new Database();
                $conn = $database->getConnection();
                
                $query = "SELECT c.title FROM courses c WHERE c.id = :course_id AND c.instructor_id = :instructor_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':course_id', $course_id);
                $stmt->bindParam(':instructor_id', $instructor_id);
                $stmt->execute();
                
                if ($stmt->rowCount() == 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid course or unauthorized access.']);
                    exit;
                }
                
                $course = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Remove student from course
                $result = unenrollStudent($student_id, $course_id);
                
                if ($result['success']) {
                    // Add log entry for instructor action
                    logActivity($instructor_id, 'student_removed', "Removed student from course: {$course['title']}");
                    echo json_encode(['success' => true, 'message' => 'Student successfully removed from the course.']);
                } else {
                    echo json_encode(['success' => false, 'message' => $result['message']]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'An error occurred while removing the student.']);
            }
            exit;
    }
}

// Get instructor's students
try {
    $database = new Database();
    $conn = $database->getConnection();

    $query = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.profile_image, u.created_at,
                     COUNT(e.id) as enrolled_courses,
                     AVG(e.progress_percentage) as avg_progress
              FROM users u
              JOIN enrollments e ON u.id = e.student_id
              JOIN courses c ON e.course_id = c.id
              WHERE c.instructor_id = :instructor_id
              GROUP BY u.id, u.first_name, u.last_name, u.email, u.profile_image, u.created_at
              ORDER BY u.first_name, u.last_name";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get instructor's courses for filter
    $query = "SELECT id, title FROM courses WHERE instructor_id = :instructor_id ORDER BY title";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $instructor_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $students = [];
    $instructor_courses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students - Instructor Dashboard</title>

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

        .sidebar-brand {
            padding: 20px;
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .progress {
            height: 8px;
        }

        .student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
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
        <!-- Flash Messages -->
        <?php if (isset($success_message) && $success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message) && $error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-user-graduate me-2"></i>My Students</h1>
            <div class="text-muted">
                Total: <?php echo count($students); ?> students
            </div>
        </div>

        <!-- Students List -->
        <?php if (!empty($students)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Enrolled Students
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Email</th>
                                    <th>Enrolled Courses</th>
                                    <th>Avg Progress</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php
                                                $avatar_url = SITE_URL . '/assets/images/default-avatar.png';
                                                if ($student['profile_image'] && file_exists(UPLOAD_PATH . 'profiles/' . $student['profile_image'])) {
                                                    $avatar_url = SITE_URL . '/uploads/profiles/' . $student['profile_image'];
                                                }
                                                ?>
                                                <img src="<?php echo $avatar_url; ?>"
                                                     alt="Avatar" class="rounded-circle me-3"
                                                     style="width: 40px; height: 40px; object-fit: cover;"
                                                     onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-avatar.png'">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $student['enrolled_courses']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress me-2" style="width: 100px;">
                                                    <div class="progress-bar" role="progressbar"
                                                         style="width: <?php echo round($student['avg_progress']); ?>%"
                                                         aria-valuenow="<?php echo round($student['avg_progress']); ?>"
                                                         aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <small class="text-muted"><?php echo round($student['avg_progress']); ?>%</small>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($student['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <button class="btn btn-outline-primary btn-sm"
                                                    onclick="viewStudentDetails(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
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
                <i class="fas fa-user-graduate fa-5x text-muted mb-4"></i>
                <h3 class="text-muted mb-3">No Students Yet</h3>
                <p class="text-muted mb-4">You don't have any students enrolled in your courses yet.</p>
                <a href="create-course.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create Your First Course
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Student Details Modal -->
    <div class="modal fade" id="studentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Student Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="studentModalBody">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function viewStudentDetails(studentId) {
            // Show loading state
            document.getElementById('studentModalBody').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading student details...</p>
                </div>
            `;

            const modal = new bootstrap.Modal(document.getElementById('studentModal'));
            modal.show();

            // Fetch student details
            fetch(`students.php?ajax=get_student_details&student_id=${studentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const student = data.student;
                        const enrollments = data.enrollments;
                        const stats = data.stats;

                        // Build profile image
                        let profileImage = '';
                        if (student.profile_image) {
                            profileImage = `<img src="<?php echo SITE_URL; ?>/uploads/profiles/${student.profile_image}"
                                               alt="Profile" class="rounded-circle"
                                               style="width: 80px; height: 80px; object-fit: cover;"
                                               onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-avatar.png'">`;
                        } else {
                            profileImage = `<div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                                               style="width: 80px; height: 80px; font-size: 24px; font-weight: bold;">
                                               ${student.first_name.charAt(0)}${student.last_name.charAt(0)}
                                           </div>`;
                        }

                        // Build enrollments list
                        let enrollmentsList = '';
                        if (enrollments.length > 0) {
                            enrollmentsList = enrollments.map(enrollment => `
                                <div class="border rounded p-3 mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">${enrollment.title}</h6>
                                            <small class="text-muted">${enrollment.category_name}</small>
                                        </div>
                                        <span class="badge bg-${enrollment.is_completed ? 'success' : 'warning'}">
                                            ${enrollment.is_completed ? 'Completed' : 'In Progress'}
                                        </span>
                                    </div>
                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small>Progress</small>
                                            <small>${parseFloat(enrollment.progress_percentage).toFixed(1)}%</small>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: ${enrollment.progress_percentage}%"></div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">Enrolled: ${new Date(enrollment.enrollment_date).toLocaleDateString()}</small>
                                    </div>
                                    <div class="mt-2">
                                        <button class="btn btn-outline-danger btn-sm" 
                                                onclick="removeStudentFromCourse(${student.id}, ${enrollment.id}, '${enrollment.title.replace(/'/g, "\\'")}')"
                                            <i class="fas fa-user-times me-1"></i>Remove from Course
                                        </button>
                                    </div>
                                </div>
                            `).join('');
                        } else {
                            enrollmentsList = '<p class="text-muted text-center">No enrollments found</p>';
                        }

                        const content = `
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    ${profileImage}
                                    <h5 class="mt-3 mb-1">${student.first_name} ${student.last_name}</h5>
                                    <p class="text-muted">${student.email}</p>
                                    ${student.bio ? `<p class="small">${student.bio}</p>` : ''}
                                </div>
                                <div class="col-md-8">
                                    <h6>Student Information</h6>
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Phone:</small><br>
                                            <span>${student.phone || 'Not provided'}</span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Date of Birth:</small><br>
                                            <span>${student.date_of_birth ? new Date(student.date_of_birth).toLocaleDateString() : 'Not provided'}</span>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Joined:</small><br>
                                            <span>${new Date(student.join_date).toLocaleDateString()}</span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Status:</small><br>
                                            <span class="badge bg-${student.is_active ? 'success' : 'secondary'}">
                                                ${student.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="row mb-3">
                                <div class="col-4 text-center">
                                    <div class="bg-primary text-white rounded p-2">
                                        <h5 class="mb-0">${stats.total_courses}</h5>
                                        <small>Courses</small>
                                    </div>
                                </div>
                                <div class="col-4 text-center">
                                    <div class="bg-success text-white rounded p-2">
                                        <h5 class="mb-0">${stats.completed_courses}</h5>
                                        <small>Completed</small>
                                    </div>
                                </div>
                                <div class="col-4 text-center">
                                    <div class="bg-info text-white rounded p-2">
                                        <h5 class="mb-0">${stats.avg_progress}%</h5>
                                        <small>Avg Progress</small>
                                    </div>
                                </div>
                            </div>

                            <h6>Course Enrollments</h6>
                            <div style="max-height: 300px; overflow-y: auto;">
                                ${enrollmentsList}
                            </div>
                        `;

                        document.getElementById('studentModalBody').innerHTML = content;
                    } else {
                        document.getElementById('studentModalBody').innerHTML = `
                            <div class="text-center py-4">
                                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                <h5>Error</h5>
                                <p class="text-muted">${data.message || 'Failed to load student details'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('studentModalBody').innerHTML = `
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                            <h5>Error</h5>
                            <p class="text-muted">An error occurred while loading student details</p>
                        </div>
                    `;
                    console.error('Error:', error);
                });
        }

        function removeStudentFromCourse(studentId, courseId, courseTitle) {
            const message = `Are you sure you want to remove this student from "${courseTitle}"?\n\n` +
                           `This action will:\n` +
                           `• Remove their access to all course materials\n` +
                           `• Delete all their progress and lesson completion data\n` +
                           `• Remove any certificates earned\n\n` +
                           `This action cannot be undone.`;
            
            if (confirm(message)) {
                // Show loading state
                const btn = event.target;
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
                btn.disabled = true;
                
                // Send AJAX request to remove student
                fetch(`students.php?ajax=remove_student_from_course&student_id=${studentId}&course_id=${courseId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message on page
                            showMessage(data.message, 'success');
                            // Reload student details to reflect changes
                            viewStudentDetails(studentId);
                        } else {
                            // Show error message on page
                            showMessage('Error: ' + data.message, 'danger');
                            // Restore button
                            btn.innerHTML = originalHtml;
                            btn.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showMessage('An error occurred while removing the student.', 'danger');
                        // Restore button
                        btn.innerHTML = originalHtml;
                        btn.disabled = false;
                    });
            }
        }

        function showMessage(message, type) {
            // Remove any existing alerts
            const existingAlerts = document.querySelectorAll('.dynamic-alert');
            existingAlerts.forEach(alert => alert.remove());
            
            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show dynamic-alert`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insert at the top of the modal body or main content
            const modalBody = document.getElementById('studentModalBody');
            if (modalBody && modalBody.closest('.modal.show')) {
                // If modal is open, insert at the top of modal body
                modalBody.insertBefore(alertDiv, modalBody.firstChild);
            } else {
                // Otherwise, insert at the top of main content
                const mainContent = document.querySelector('.main-content');
                if (mainContent) {
                    mainContent.insertBefore(alertDiv, mainContent.firstChild);
                }
            }
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    const bsAlert = new bootstrap.Alert(alertDiv);
                    bsAlert.close();
                }
            }, 5000);
        }
    </script>
</body>
</html>
