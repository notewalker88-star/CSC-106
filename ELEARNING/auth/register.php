<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';

$error_message = '';
$success_message = '';

// Check if user is already logged in
if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $role = ROLE_STUDENT; // All registrations are students

    // Validation
    $errors = [];

    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters long.';
    }

    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($first_name)) {
        $errors[] = 'First name is required.';
    }

    if (empty($last_name)) {
        $errors[] = 'Last name is required.';
    }

    // Role is automatically set to student
    $role = ROLE_STUDENT;

    if (empty($errors)) {
        $user = new User();

        // Check if username already exists
        if ($user->usernameExists($username)) {
            $errors[] = 'Username already exists. Please choose a different one.';
        }

        // Check if email already exists
        if ($user->emailExists($email)) {
            $errors[] = 'Email already exists. Please use a different email or login.';
        }

        if (empty($errors)) {
            // Set user properties
            $user->username = $username;
            $user->email = $email;
            $user->password = $password;
            $user->first_name = $first_name;
            $user->last_name = $last_name;
            $user->role = $role;

            if ($user->register()) {
                $success_message = 'Registration successful! You can now login with your credentials.';

                // Clear form data
                $username = $email = $first_name = $last_name = '';
            } else {
                $error_message = 'Registration failed. Please try again.';
            }
        }
    }

    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .register-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .register-form {
            padding: 2rem;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        /* Role selection removed - simplified registration */

        .password-toggle {
            border-left: none;
            background-color: #f8f9fa;
            border-color: #ced4da;
        }
        .password-toggle:hover {
            background-color: #e9ecef;
            border-color: #adb5bd;
        }
        .password-toggle:focus {
            box-shadow: none;
            border-color: #667eea;
            background-color: #e9ecef;
        }
        .input-group .form-control:focus + .password-toggle {
            border-color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="register-container">
                    <div class="register-header">
                        <h2><i class="fas fa-graduation-cap me-2"></i><?php echo SITE_NAME; ?></h2>
                        <p class="mb-0">Create your student account</p>
                    </div>

                    <div class="register-form">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-sm btn-success">
                                        <i class="fas fa-sign-in-alt me-1"></i>Login Now
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="registerForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">
                                        <i class="fas fa-user me-2"></i>First Name
                                    </label>
                                    <input type="text"
                                           class="form-control"
                                           id="first_name"
                                           name="first_name"
                                           value="<?php echo htmlspecialchars($first_name ?? ''); ?>"
                                           required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">
                                        <i class="fas fa-user me-2"></i>Last Name
                                    </label>
                                    <input type="text"
                                           class="form-control"
                                           id="last_name"
                                           name="last_name"
                                           value="<?php echo htmlspecialchars($last_name ?? ''); ?>"
                                           required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-at me-2"></i>Username
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="username"
                                       name="username"
                                       value="<?php echo htmlspecialchars($username ?? ''); ?>"
                                       required>
                                <div class="form-text">Username must be at least 3 characters long.</div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Email Address
                                </label>
                                <input type="email"
                                       class="form-control"
                                       id="email"
                                       name="email"
                                       value="<?php echo htmlspecialchars($email ?? ''); ?>"
                                       required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password"
                                               class="form-control"
                                               id="password"
                                               name="password"
                                               required>
                                        <button class="btn btn-outline-secondary password-toggle"
                                                type="button"
                                                id="togglePassword"
                                                onclick="togglePasswordVisibility('password', 'togglePassword')">
                                            <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters.</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Confirm Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password"
                                               class="form-control"
                                               id="confirm_password"
                                               name="confirm_password"
                                               required>
                                        <button class="btn btn-outline-secondary password-toggle"
                                                type="button"
                                                id="toggleConfirmPassword"
                                                onclick="togglePasswordVisibility('confirm_password', 'toggleConfirmPassword')">
                                            <i class="fas fa-eye" id="toggleConfirmPasswordIcon"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden field to set role as student -->
                            <input type="hidden" name="role" value="student">

                            <button type="submit" class="btn btn-primary btn-register w-100">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                        </form>

                        <hr class="my-4">

                        <div class="text-center">
                            <p class="mb-2">Already have an account?</p>
                            <a href="login.php" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </a>
                        </div>

                        <div class="text-center mt-3">
                            <a href="<?php echo SITE_URL; ?>/index.php" class="text-muted">
                                <i class="fas fa-arrow-left me-2"></i>Back to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Role selection removed - all users register as students

        // Password visibility toggle function
        function togglePasswordVisibility(passwordFieldId, buttonId) {
            const passwordField = document.getElementById(passwordFieldId);
            const toggleButton = document.getElementById(buttonId);
            const icon = toggleButton.querySelector('i');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                toggleButton.setAttribute('title', 'Hide password');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                toggleButton.setAttribute('title', 'Show password');
            }
        }

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;

            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Add tooltips to password toggle buttons
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('togglePassword').setAttribute('title', 'Show password');
            document.getElementById('toggleConfirmPassword').setAttribute('title', 'Show password');
        });
    </script>
</body>
</html>
