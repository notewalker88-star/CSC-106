<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/User.php';

// Check if user is logged in
requireLogin();

$user = new User();
$user->getUserById($_SESSION['user_id']);

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $user->first_name = sanitizeInput($_POST['first_name']);
                $user->last_name = sanitizeInput($_POST['last_name']);
                $user->bio = sanitizeInput($_POST['bio']);
                $user->phone = sanitizeInput($_POST['phone']);
                $user->date_of_birth = $_POST['date_of_birth'] ?: null;

                if ($user->updateProfile()) {
                    // Update session name
                    $_SESSION['user_name'] = $user->getFullName();
                    $success_message = 'Profile updated successfully!';
                } else {
                    $error_message = 'Failed to update profile.';
                }
                break;

            case 'upload_avatar':
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
                    $result = $user->uploadProfileImage($_FILES['avatar']);

                    if ($result['success']) {
                        $success_message = 'Avatar uploaded successfully!';
                    } else {
                        $error_message = $result['message'];
                    }
                } else {
                    $error_message = 'Please select an image file to upload.';
                }
                break;

            case 'delete_avatar':
                if ($user->deleteProfileImage()) {
                    $success_message = 'Avatar deleted successfully!';
                } else {
                    $error_message = 'Failed to delete avatar.';
                }
                break;

            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];

                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error_message = 'All password fields are required.';
                } elseif ($new_password !== $confirm_password) {
                    $error_message = 'New passwords do not match.';
                } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
                    $error_message = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
                } else {
                    if ($user->changePassword($current_password, $new_password)) {
                        $success_message = 'Password changed successfully!';
                    } else {
                        $error_message = 'Current password is incorrect.';
                    }
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
    <title>My Profile - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        .avatar-container {
            position: relative;
            display: inline-block;
        }
        .avatar-large {
            width: 150px;
            height: 150px;
            border: 5px solid white;
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
            margin-top: -50px;
            position: relative;
            z-index: 2;
        }
        .nav-pills .nav-link {
            border-radius: 25px;
            margin: 0 5px;
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .avatar-initials {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
                    <li class="nav-item">
                        <a class="nav-link" href="courses.php">Courses</a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo $user->getAvatarHtml(30, 'rounded-circle me-2'); ?>
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
                            <li><a class="dropdown-item active" href="profile.php">
                                <i class="fas fa-user-edit me-2"></i>Profile
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container text-center">
            <div class="avatar-container">
                <?php if ($user->profile_image): ?>
                    <img src="<?php echo $user->getProfileImageUrl(); ?>"
                         alt="Profile Picture"
                         class="rounded-circle avatar-large">
                <?php else: ?>
                    <div class="rounded-circle avatar-initials">
                        <?php echo $user->getAvatarInitials(); ?>
                    </div>
                <?php endif; ?>

                <div class="avatar-upload-overlay" data-bs-toggle="modal" data-bs-target="#avatarModal">
                    <i class="fas fa-camera fa-2x text-white"></i>
                </div>
            </div>

            <h2 class="mt-3 mb-1"><?php echo htmlspecialchars($user->getFullName()); ?></h2>
            <p class="mb-0">
                <span class="badge bg-light text-dark">
                    <?php echo ucfirst($user->role); ?>
                </span>
            </p>
        </div>
    </div>

    <!-- Profile Content -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card profile-card">
                    <div class="card-body p-4">
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
                                        <div class="form-text">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</div>
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
                        <i class="fas fa-camera me-2"></i>Profile Picture
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
                                <?php echo $user->getAvatarInitials(); ?>
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

        // Preview uploaded image
        document.getElementById('avatar').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // You could add image preview here if needed
                };
                reader.readAsDataURL(file);
            }
        });

        // Auto-submit avatar form when file is selected
        document.getElementById('avatar').addEventListener('change', function() {
            if (this.files.length > 0) {
                // Optional: Auto-submit the form
                // document.getElementById('avatarUploadForm').submit();
            }
        });
    </script>
</body>
</html>
