<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || !hasRole(ROLE_STUDENT)) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$user = new User();
$user->getUserById($_SESSION['user_id']);

// Get student learning statistics
try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get learning statistics
    $query = "SELECT
                COUNT(DISTINCT e.course_id) as total_enrolled,
                COUNT(DISTINCT CASE WHEN e.is_completed = 1 THEN e.course_id END) as completed_courses,
                COUNT(DISTINCT lp.lesson_id) as completed_lessons,
                AVG(e.progress_percentage) as avg_progress
              FROM enrollments e
              LEFT JOIN courses c ON e.course_id = c.id
              LEFT JOIN lessons l ON c.id = l.course_id AND l.is_published = 1
              LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.student_id = e.student_id AND lp.is_completed = 1
              WHERE e.student_id = :student_id";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->execute();
    $learning_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get total available lessons in enrolled courses
    $query = "SELECT COUNT(DISTINCT l.id) as total_lessons
              FROM enrollments e
              JOIN courses c ON e.course_id = c.id
              JOIN lessons l ON c.id = l.course_id
              WHERE e.student_id = :student_id AND l.is_published = 1";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->execute();
    $total_lessons_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $learning_stats['total_lessons'] = $total_lessons_result['total_lessons'] ?? 0;

    // Calculate completion rate
    $learning_stats['completion_rate'] = $learning_stats['total_lessons'] > 0
        ? round(($learning_stats['completed_lessons'] / $learning_stats['total_lessons']) * 100)
        : 0;

} catch (Exception $e) {
    $learning_stats = [
        'total_enrolled' => 0,
        'completed_courses' => 0,
        'completed_lessons' => 0,
        'total_lessons' => 0,
        'completion_rate' => 0,
        'avg_progress' => 0
    ];
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'change_password') {
        // Handle password change
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'Please fill in all password fields.';
        } else if ($new_password !== $confirm_password) {
            $error_message = 'New passwords do not match.';
        } else if (strlen($new_password) < 6) {
            $error_message = 'New password must be at least 6 characters long.';
        } else {
            if ($user->changePassword($current_password, $new_password)) {
                $success_message = 'Password changed successfully!';
            } else {
                $error_message = 'Current password is incorrect or failed to update password.';
            }
        }
    } else if (isset($_POST['action']) && $_POST['action'] == 'upload_avatar') {
        // Handle avatar upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $result = $user->uploadProfileImage($_FILES['avatar']);

            if ($result['success']) {
                $success_message = 'Profile picture uploaded successfully!';
            } else {
                $error_message = $result['message'];
            }
        } else {
            $error_message = 'Please select an image file to upload.';
        }
    } else if (isset($_POST['action']) && $_POST['action'] == 'delete_avatar') {
        // Handle avatar deletion
        if ($user->deleteProfileImage()) {
            $success_message = 'Profile picture deleted successfully!';
        } else {
            $error_message = 'Failed to delete profile picture.';
        }
    } else {
        // Handle profile update
        $user->first_name = trim($_POST['first_name']);
        $user->last_name = trim($_POST['last_name']);
        $user->email = trim($_POST['email']);
        $user->bio = trim($_POST['bio']);
        $user->phone = trim($_POST['phone']);
        $user->date_of_birth = $_POST['date_of_birth'] ?: null;

    // Validate required fields
    if (empty($user->first_name) || empty($user->last_name) || empty($user->email)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        // Check if email is being changed and if it already exists
        $new_email = trim($_POST['email']);
        $email_changed = ($user->email !== $new_email);
        if ($email_changed && $user->emailExists($new_email)) {
            $error_message = 'Email address is already in use by another account.';
        } else {
            // Update email separately if changed
            if ($email_changed) {
                try {
                    $database = new Database();
                    $conn = $database->getConnection();

                    $query = "UPDATE users SET email = :email, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':email', $user->email);
                    $stmt->bindParam(':id', $user->id);
                    $stmt->execute();
                } catch (Exception $e) {
                    $error_message = 'Failed to update email. Please try again.';
                }
            }

            // Update profile information
            if (!$error_message && $user->updateProfile()) {
                $success_message = 'Profile updated successfully!';
                $_SESSION['user_name'] = $user->first_name . ' ' . $user->last_name;
            } else if (!$error_message) {
                $error_message = 'Failed to update profile. Please try again.';
            }
        }
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Dashboard</title>

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
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        .avatar-container {
            position: relative;
            display: inline-block;
        }
        .avatar-large {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .avatar-initials {
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .avatar-upload-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            cursor: pointer;
        }
        .avatar-container:hover .avatar-upload-overlay {
            opacity: 1;
        }
        .stat-widget {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .profile-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="avatar-container me-4">
                            <?php if ($user->profile_image): ?>
                                <img src="<?php echo $user->getProfileImageUrl(); ?>"
                                     alt="Profile Picture"
                                     class="rounded-circle avatar-large">
                            <?php else: ?>
                                <div class="rounded-circle avatar-initials">
                                    <?php echo strtoupper(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1)); ?>
                                </div>
                            <?php endif; ?>

                            <div class="avatar-upload-overlay" data-bs-toggle="modal" data-bs-target="#avatarModal">
                                <i class="fas fa-camera fa-lg text-white"></i>
                            </div>
                        </div>

                        <div>
                            <h2 class="mb-1"><?php echo htmlspecialchars($user->first_name . ' ' . $user->last_name); ?></h2>
                            <p class="mb-2">
                                <i class="fas fa-graduation-cap me-2"></i>
                                Student
                            </p>
                            <p class="mb-0">
                                <i class="fas fa-envelope me-2"></i>
                                <?php echo htmlspecialchars($user->email); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="text-white-50">
                        <small>Member since</small><br>
                        <strong><?php echo date('F Y', strtotime($user->created_at)); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-widget">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?php echo number_format($learning_stats['total_enrolled'] ?? 0); ?></h3>
                            <small>Enrolled Courses</small>
                        </div>
                        <i class="fas fa-book fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-widget">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?php echo number_format($learning_stats['completed_courses'] ?? 0); ?></h3>
                            <small>Completed Courses</small>
                        </div>
                        <i class="fas fa-trophy fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-widget">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?php echo number_format($learning_stats['completed_lessons'] ?? 0); ?></h3>
                            <small>Lessons Completed</small>
                        </div>
                        <i class="fas fa-play-circle fa-2x opacity-75"></i>
                    </div>
                </div>
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

        <!-- Profile Content -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card profile-card">
                    <div class="card-body p-4">
                        <!-- Navigation Tabs -->
                        <ul class="nav nav-pills justify-content-center mb-4" id="profileTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="profile-tab" data-bs-toggle="pill" data-bs-target="#profile" type="button" role="tab">
                                    <i class="fas fa-user me-2"></i>Profile Information
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security" type="button" role="tab">
                                    <i class="fas fa-lock me-2"></i>Security
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="learning-tab" data-bs-toggle="pill" data-bs-target="#learning" type="button" role="tab">
                                    <i class="fas fa-graduation-cap me-2"></i>Learning Preferences
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="profileTabsContent">
                            <!-- Profile Information Tab -->
                            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="first_name" class="form-label">First Name</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name"
                                                   value="<?php echo htmlspecialchars($user->first_name); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="last_name" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name"
                                                   value="<?php echo htmlspecialchars($user->last_name); ?>" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="username" class="form-label">Username</label>
                                            <input type="text" class="form-control" id="username"
                                                   value="<?php echo htmlspecialchars($user->username); ?>" readonly>
                                            <div class="form-text">Username cannot be changed</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                   value="<?php echo htmlspecialchars($user->email); ?>" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone"
                                                   value="<?php echo htmlspecialchars($user->phone); ?>"
                                                   pattern="[0-9]*"
                                                   inputmode="numeric"
                                                   maxlength="15"
                                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                                   title="Please enter numbers only">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                                   value="<?php echo $user->date_of_birth; ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="bio" class="form-label">Bio</label>
                                        <textarea class="form-control" id="bio" name="bio" rows="4"
                                                  placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user->bio); ?></textarea>
                                    </div>

                                    <div class="text-center">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save me-2"></i>Update Profile
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Security Tab -->
                            <div class="tab-pane fade" id="security" role="tabpanel">
                                <form method="POST" id="passwordForm">
                                    <input type="hidden" name="action" value="change_password">

                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>

                                    <div class="text-center">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-key me-2"></i>Change Password
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Learning Preferences Tab -->
                            <div class="tab-pane fade" id="learning" role="tabpanel">
                                <div class="text-center">
                                    <h5 class="mb-4">Student Quick Actions</h5>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <a href="courses.php" class="btn btn-outline-primary w-100">
                                                <i class="fas fa-book fa-2x d-block mb-2"></i>
                                                My Courses
                                            </a>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <a href="../courses.php" class="btn btn-outline-success w-100">
                                                <i class="fas fa-search fa-2x d-block mb-2"></i>
                                                Browse Courses
                                            </a>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <a href="progress.php" class="btn btn-outline-warning w-100">
                                                <i class="fas fa-chart-line fa-2x d-block mb-2"></i>
                                                View Progress
                                            </a>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <a href="../index.php" class="btn btn-outline-info w-100">
                                                <i class="fas fa-home fa-2x d-block mb-2"></i>
                                                Back to Site
                                            </a>
                                        </div>
                                    </div>

                                    <hr class="my-4">

                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Student Account:</strong> You can enroll in courses, track your progress, and manage your learning journey.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Account Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Account Type</small>
                            <div><span class="badge bg-primary">Student</span></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Member Since</small>
                            <div><?php echo date('F j, Y', strtotime($user->created_at)); ?></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Last Updated</small>
                            <div><?php echo $user->updated_at ? date('F j, Y', strtotime($user->updated_at)) : 'Never'; ?></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Account Status</small>
                            <div>
                                <span class="badge bg-success">
                                    <i class="fas fa-check me-1"></i>Active
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>Learning Stats
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small>Enrolled courses</small>
                            <span class="badge bg-primary"><?php echo number_format($learning_stats['total_enrolled'] ?? 0); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small>Completed courses</small>
                            <span class="badge bg-success"><?php echo number_format($learning_stats['completed_courses'] ?? 0); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small>Lessons completed</small>
                            <span class="badge bg-info"><?php echo number_format($learning_stats['completed_lessons'] ?? 0); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small>Completion rate</small>
                            <span class="badge bg-warning"><?php echo $learning_stats['completion_rate'] ?? 0; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Avatar Upload Modal -->
    <div class="modal fade" id="avatarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-camera me-2"></i>Student Profile Picture
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-4">
                        <?php if ($user->profile_image): ?>
                            <img src="<?php echo $user->getProfileImageUrl(); ?>"
                                 alt="Current Avatar"
                                 class="rounded-circle"
                                 style="width: 120px; height: 120px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle mx-auto"
                                 style="width: 120px; height: 120px; background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
                                        display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold;">
                                <?php
                                $first_initial = $user->first_name ? strtoupper(substr($user->first_name, 0, 1)) : 'U';
                                $last_initial = $user->last_name ? strtoupper(substr($user->last_name, 0, 1)) : 'S';
                                echo $first_initial . $last_initial;
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="avatarUploadForm">
                        <input type="hidden" name="action" value="upload_avatar">

                        <div class="mb-3">
                            <input type="file" class="form-control" id="avatar" name="avatar"
                                   accept="image/*" required>
                            <div class="form-text">
                                Supported formats: JPG, PNG, GIF. Maximum size: 5MB
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Upload New Picture
                            </button>

                            <?php if ($user->profile_image): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_avatar">
                                    <button type="submit" class="btn btn-outline-danger w-100"
                                            onclick="return confirm('Are you sure you want to delete your profile picture?')">
                                        <i class="fas fa-trash me-2"></i>Delete Current Picture
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>



    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;

            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>