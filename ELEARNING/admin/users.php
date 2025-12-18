<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';

// Check if user is admin
requireRole(ROLE_ADMIN);

$user = new User();
$action = $_GET['action'] ?? 'list';
$user_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $new_user = new User();
                $new_user->username = sanitizeInput($_POST['username']);
                $new_user->email = sanitizeInput($_POST['email']);
                $new_user->password = $_POST['password'];
                $new_user->first_name = sanitizeInput($_POST['first_name']);
                $new_user->last_name = sanitizeInput($_POST['last_name']);
                $new_user->role = sanitizeInput($_POST['role']);

                // Validation
                $errors = [];
                if ($new_user->usernameExists($new_user->username)) {
                    $errors[] = 'Username already exists.';
                }
                if ($new_user->emailExists($new_user->email)) {
                    $errors[] = 'Email already exists.';
                }
                if (strlen($_POST['password']) < PASSWORD_MIN_LENGTH) {
                    $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
                }

                if (empty($errors)) {
                    if ($new_user->register()) {
                        redirectWithMessage('users.php', 'User created successfully!', 'success');
                    } else {
                        $error_message = 'Failed to create user.';
                    }
                } else {
                    $error_message = implode('<br>', $errors);
                }
                break;

            case 'edit':
                $edit_user = new User();
                if ($edit_user->getUserById($_POST['user_id'])) {
                    $edit_user->first_name = sanitizeInput($_POST['first_name']);
                    $edit_user->last_name = sanitizeInput($_POST['last_name']);
                    $edit_user->role = sanitizeInput($_POST['role']);

                    if ($edit_user->updateProfile()) {
                        redirectWithMessage('users.php', 'User updated successfully!', 'success');
                    } else {
                        $error_message = 'Failed to update user.';
                    }
                }
                break;

            case 'toggle_status':
                if ($user->toggleActiveStatus($_POST['user_id'])) {
                    redirectWithMessage('users.php', 'User status updated successfully!', 'success');
                } else {
                    $error_message = 'Failed to update user status.';
                }
                break;

            case 'delete':
                $delete_user = new User();
                $result = $delete_user->deleteUser($_POST['user_id']);

                if ($result['success']) {
                    redirectWithMessage('users.php', $result['message'], 'success');
                } else {
                    $error_message = $result['message'];
                }
                break;
        }
    }
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    switch ($_GET['ajax']) {
        case 'toggle_status':
            $result = $user->toggleActiveStatus($_GET['user_id']);
            echo json_encode(['success' => $result]);
            exit;

        case 'get_user':
            $edit_user = new User();
            if ($edit_user->getUserById($_GET['user_id'])) {
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'id' => $edit_user->id,
                        'username' => $edit_user->username,
                        'email' => $edit_user->email,
                        'first_name' => $edit_user->first_name,
                        'last_name' => $edit_user->last_name,
                        'role' => $edit_user->role,
                        'is_active' => $edit_user->is_active
                    ]
                ]);
            } else {
                echo json_encode(['success' => false]);
            }
            exit;

        case 'delete_user':
            $delete_user = new User();
            $result = $delete_user->deleteUser($_GET['user_id']);
            echo json_encode($result);
            exit;
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$role_filter = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';

// Build filters
$filters = [];
if (!empty($role_filter)) {
    $filters['role'] = $role_filter;
}
if (!empty($search)) {
    $filters['search'] = $search;
}

// Get users
$users = $user->getAllUsers($page, $limit, $role_filter);
$total_users = $user->getTotalUsers($role_filter);
$total_pages = ceil($total_users / $limit);

// Get user counts by role
$student_count = $user->getTotalUsers('student');
$instructor_count = $user->getTotalUsers('instructor');
$admin_count = $user->getTotalUsers('admin');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo SITE_NAME; ?></title>
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
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .status-badge {
            font-size: 0.8rem;
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
            <h1><i class="fas fa-users me-2"></i>User Management</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-user-plus me-2"></i>Add User
            </button>
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
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo number_format($total_users); ?></h4>
                                <p class="mb-0">Total Users</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo number_format($student_count); ?></h4>
                                <p class="mb-0">Students</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user-graduate fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo number_format($instructor_count); ?></h4>
                                <p class="mb-0">Instructors</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-chalkboard-teacher fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo number_format($admin_count); ?></h4>
                                <p class="mb-0">Administrators</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user-shield fa-2x"></i>
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
                    <div class="col-md-4">
                        <label for="role" class="form-label">Filter by Role</label>
                        <select name="role" id="role" class="form-select">
                            <option value="">All Roles</option>
                            <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Students</option>
                            <option value="instructor" <?php echo $role_filter === 'instructor' ? 'selected' : ''; ?>>Instructors</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrators</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="search" class="form-label">Search Users</label>
                        <input type="text" name="search" id="search" class="form-control"
                               placeholder="Search by name, username, or email..."
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

        <!-- Users Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Users List
                    <span class="badge bg-secondary ms-2"><?php echo number_format($total_users); ?> total</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($users)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php
                                                $avatar_url = SITE_URL . '/assets/images/default-avatar.png';
                                                if ($u['profile_image'] && file_exists(UPLOAD_PATH . 'profiles/' . $u['profile_image'])) {
                                                    $avatar_url = SITE_URL . '/uploads/profiles/' . $u['profile_image'];
                                                }
                                                ?>
                                                <img src="<?php echo $avatar_url; ?>"
                                                     alt="Avatar" class="user-avatar me-3"
                                                     onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default-avatar.png'">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></div>
                                                    <small class="text-muted">@<?php echo htmlspecialchars($u['username']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo $u['role'] === 'admin' ? 'danger' :
                                                    ($u['role'] === 'instructor' ? 'success' : 'primary');
                                            ?>">
                                                <?php echo ucfirst($u['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge status-badge bg-<?php echo $u['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($u['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-primary"
                                                        onclick="editUser(<?php echo $u['id']; ?>)"
                                                        title="Edit User">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-<?php echo $u['is_active'] ? 'warning' : 'success'; ?>"
                                                        onclick="toggleUserStatus(<?php echo $u['id']; ?>)"
                                                        title="<?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?> User">
                                                    <i class="fas fa-<?php echo $u['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                </button>
                                                <button class="btn btn-outline-danger"
                                                        onclick="deleteUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name'], ENT_QUOTES); ?>')"
                                                        title="Delete User">
                                                    <i class="fas fa-trash"></i>
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
                        <nav aria-label="Users pagination" class="mt-4">
                            <?php
                            $params = [];
                            if ($role_filter) $params['role'] = $role_filter;
                            if ($search) $params['search'] = $search;
                            echo generatePagination($page, $total_pages, 'users.php', $params);
                            ?>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No users found</h5>
                        <p class="text-muted">Try adjusting your search criteria or add a new user.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</div>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="student">Student</option>
                                <option value="instructor">Instructor</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>Edit User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="edit_user_id">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" readonly>
                            <div class="form-text">Username cannot be changed</div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" readonly>
                            <div class="form-text">Email cannot be changed</div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="student">Student</option>
                                <option value="instructor">Instructor</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(userId) {
            fetch(`users.php?ajax=get_user&user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_user_id').value = data.user.id;
                        document.getElementById('edit_first_name').value = data.user.first_name;
                        document.getElementById('edit_last_name').value = data.user.last_name;
                        document.getElementById('edit_username').value = data.user.username;
                        document.getElementById('edit_email').value = data.user.email;
                        document.getElementById('edit_role').value = data.user.role;

                        new bootstrap.Modal(document.getElementById('editUserModal')).show();
                    }
                });
        }

        function toggleUserStatus(userId) {
            if (confirm('Are you sure you want to change this user\'s status?')) {
                fetch(`users.php?ajax=toggle_status&user_id=${userId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Failed to update user status');
                        }
                    });
            }
        }

        function deleteUser(userId, userName) {
            if (confirm(`Are you sure you want to delete the user "${userName}"? This action cannot be undone.`)) {
                // Show loading state
                const deleteButtons = document.querySelectorAll(`button[onclick*="deleteUser(${userId}"]`);
                deleteButtons.forEach(btn => {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                });

                fetch(`users.php?ajax=delete_user&user_id=${userId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            location.reload();
                        } else {
                            alert(`Failed to delete user: ${data.message}`);
                            // Re-enable buttons
                            deleteButtons.forEach(btn => {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-trash"></i>';
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Delete user error:', error);
                        alert(`An error occurred while deleting the user: ${error.message}`);
                        // Re-enable buttons
                        deleteButtons.forEach(btn => {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-trash"></i>';
                        });
                    });
            }
        }
    </script>
</body>
</html>
