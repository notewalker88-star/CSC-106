<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';

// Check if user is logged in and is an instructor
if (!isLoggedIn() || !hasRole(ROLE_INSTRUCTOR)) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$user = new User();
$user->getUserById($_SESSION['user_id']);

$success_message = '';
$error_message = '';

// Get instructor statistics
$instructor_id = $_SESSION['user_id'];
try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get instructor's course statistics
    $stats = [];

    // Total courses created
    $query = "SELECT COUNT(*) as count FROM courses WHERE instructor_id = :instructor_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $stats['courses_created'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Active/Published courses
    $query = "SELECT COUNT(*) as count FROM courses WHERE instructor_id = :instructor_id AND is_published = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $stats['active_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total students enrolled in instructor's courses
    $query = "SELECT COUNT(DISTINCT e.student_id) as count
              FROM enrollments e
              JOIN courses c ON e.course_id = c.id
              WHERE c.instructor_id = :instructor_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':instructor_id', $instructor_id);
    $stmt->execute();
    $stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

} catch (Exception $e) {
    $stats = ['courses_created' => 0, 'total_students' => 0, 'active_courses' => 0];
}

// Simple fallback for user methods
function getUserFullName($user) {
    if (method_exists($user, 'getFullName')) {
        return $user->getFullName();
    }
    return trim($user->first_name . ' ' . $user->last_name);
}

function getUserInitials($user) {
    if (method_exists($user, 'getAvatarInitials')) {
        return $user->getAvatarInitials();
    }
    $initials = '';
    if ($user->first_name) $initials .= strtoupper(substr($user->first_name, 0, 1));
    if ($user->last_name) $initials .= strtoupper(substr($user->last_name, 0, 1));
    return $initials ?: strtoupper(substr($user->username, 0, 2));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $user->first_name = trim($_POST['first_name']);
                $user->last_name = trim($_POST['last_name']);
                $user->bio = trim($_POST['bio']);
                $user->phone = trim($_POST['phone']);
                $user->date_of_birth = $_POST['date_of_birth'] ?: null;

                if ($user->updateProfile()) {
                    $success_message = 'Profile updated successfully!';
                    $_SESSION['user_name'] = getUserFullName($user);
                    $user->getUserById($_SESSION['user_id']); // Refresh data
                } else {
                    $error_message = 'Failed to update profile.';
                }
                break;

            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];

                if ($new_password !== $confirm_password) {
                    $error_message = 'New passwords do not match.';
                } elseif (strlen($new_password) < 6) {
                    $error_message = 'Password must be at least 6 characters long.';
                } elseif (method_exists($user, 'changePassword') && $user->changePassword($current_password, $new_password)) {
                    $success_message = 'Password changed successfully!';
                } else {
                    $error_message = 'Current password is incorrect or failed to update password.';
                }
                break;

            case 'upload_avatar':
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
                    if (method_exists($user, 'uploadProfileImage')) {
                        $result = $user->uploadProfileImage($_FILES['avatar']);
                        if ($result['success']) {
                            $success_message = 'Profile picture uploaded successfully!';
                            $user->getUserById($_SESSION['user_id']); // Refresh data
                        } else {
                            $error_message = $result['message'];
                        }
                    } else {
                        $error_message = 'Avatar upload not supported.';
                    }
                } else {
                    $error_message = 'Please select an image file to upload.';
                }
                break;

            case 'delete_avatar':
                if (method_exists($user, 'deleteProfileImage') && $user->deleteProfileImage()) {
                    $success_message = 'Profile picture deleted successfully!';
                    $user->getUserById($_SESSION['user_id']); // Refresh data
                } else {
                    $error_message = 'Failed to delete profile picture.';
                }
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Profile - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            z-index: 1000;
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
            min-height: 100vh;
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
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 50%;
            cursor: pointer;
        }
        .avatar-container:hover .avatar-upload-overlay {
            opacity: 1;
        }
        .profile-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .nav-pills .nav-link {
            border-radius: 25px;
            margin: 0 5px;
            color: #495057 !important;
            background-color: #f8f9fa !important;
            border: 2px solid #e9ecef !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
        }
        .nav-pills .nav-link:hover {
            color: #667eea !important;
            background-color: rgba(102, 126, 234, 0.1) !important;
            border-color: #667eea !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            border-color: transparent !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4) !important;
        }

        /* Enhanced tab container */
        #profileTabs {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 30px !important;
        }

        /* Form improvements */
        .form-control {
            background-color: #ffffff !important;
            border: 2px solid #e9ecef !important;
            color: #212529 !important;
            font-weight: 500;
        }

        .form-control:focus {
            background-color: #ffffff !important;
            border-color: #667eea !important;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25) !important;
            color: #212529 !important;
        }

        .form-label {
            color: #212529 !important;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .stat-widget {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .avatar-initials {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
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
                                    <?php echo getUserInitials($user); ?>
                                </div>
                            <?php endif; ?>

                            <div class="avatar-upload-overlay" data-bs-toggle="modal" data-bs-target="#avatarModal">
                                <i class="fas fa-camera fa-lg text-white"></i>
                            </div>
                        </div>

                        <div>
                            <h2 class="mb-1"><?php echo htmlspecialchars(getUserFullName($user)); ?></h2>
                            <p class="mb-2">
                                <i class="fas fa-chalkboard-teacher me-2"></i>
                                Course Instructor
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
                            <h3 class="mb-0"><?php echo number_format($stats['courses_created']); ?></h3>
                            <small>Courses Created</small>
                        </div>
                        <i class="fas fa-book fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-widget">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['total_students']); ?></h3>
                            <small>Total Students</small>
                        </div>
                        <i class="fas fa-user-graduate fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-widget">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?php echo number_format($stats['active_courses']); ?></h3>
                            <small>Active Courses</small>
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
                                <button class="nav-link" id="instructor-tab" data-bs-toggle="pill" data-bs-target="#instructor" type="button" role="tab">
                                    <i class="fas fa-chalkboard-teacher me-2"></i>Instructor Settings
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="profileTabsContent">
                            <!-- Profile Information Tab -->
                            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">

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
                                            <input type="email" class="form-control" id="email"
                                                   value="<?php echo htmlspecialchars($user->email); ?>" readonly>
                                            <div class="form-text">Email cannot be changed</div>
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
                                                  placeholder="Tell students about yourself, your experience, and expertise..."><?php echo htmlspecialchars($user->bio); ?></textarea>
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

                            <!-- Instructor Settings Tab -->
                            <div class="tab-pane fade" id="instructor" role="tabpanel">
                                <div class="text-center">
                                    <h5 class="mb-4">Instructor Quick Actions</h5>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <a href="courses.php" class="btn btn-outline-primary w-100">
                                                <i class="fas fa-book fa-2x d-block mb-2"></i>
                                                Manage Courses
                                            </a>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <a href="students.php" class="btn btn-outline-success w-100">
                                                <i class="fas fa-user-graduate fa-2x d-block mb-2"></i>
                                                View Students
                                            </a>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <a href="create_course.php" class="btn btn-outline-warning w-100">
                                                <i class="fas fa-plus fa-2x d-block mb-2"></i>
                                                Create New Course
                                            </a>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <a href="analytics.php" class="btn btn-outline-info w-100">
                                                <i class="fas fa-chart-line fa-2x d-block mb-2"></i>
                                                Analytics
                                            </a>
                                        </div>
                                    </div>

                                    <hr class="my-4">

                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Instructor Privileges:</strong> You can create and manage courses, view enrolled students, and track your teaching performance.
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
                            <div><span class="badge bg-primary">Instructor</span></div>
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
                            <i class="fas fa-chart-line me-2"></i>Teaching Activity
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small>Courses created</small>
                            <span class="badge bg-primary"><?php echo number_format($stats['courses_created']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small>Active courses</small>
                            <span class="badge bg-success"><?php echo number_format($stats['active_courses']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small>Total students</small>
                            <span class="badge bg-info"><?php echo number_format($stats['total_students']); ?></span>
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
                        <i class="fas fa-camera me-2"></i>Instructor Profile Picture
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
                                 style="width: 120px; height: 120px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                        display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold;">
                                <?php echo getUserInitials($user); ?>
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

        // Avatar upload form handling
        document.getElementById('avatarUploadForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('avatar');
            const file = fileInput.files[0];

            if (!file) {
                e.preventDefault();
                alert('Please select an image file to upload.');
                return false;
            }

            // Check file size (5MB limit)
            if (file.size > 5 * 1024 * 1024) {
                e.preventDefault();
                alert('File is too large. Maximum size is 5MB.');
                return false;
            }

            // Check file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                e.preventDefault();
                alert('Invalid file type. Please select a JPG, PNG, or GIF image.');
                return false;
            }

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading...';
            submitBtn.disabled = true;

            // Re-enable button after 10 seconds (in case of error)
            setTimeout(function() {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 10000);
        });

        // File input change handler for preview
        document.getElementById('avatar').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Show file info
                const fileInfo = document.createElement('div');
                fileInfo.className = 'mt-2 text-muted small';
                fileInfo.innerHTML = `Selected: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;

                // Remove existing file info
                const existingInfo = this.parentNode.querySelector('.file-info');
                if (existingInfo) {
                    existingInfo.remove();
                }

                fileInfo.className += ' file-info';
                this.parentNode.appendChild(fileInfo);
            }
        });
    </script>
</body>
</html>
