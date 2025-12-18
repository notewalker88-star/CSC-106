<?php
/**
 * Instructor Sidebar with Profile
 */

// Include required classes if not already included
if (!class_exists('User')) {
    require_once __DIR__ . '/../../classes/User.php';
}

// Get instructor profile information if not already loaded
if (!isset($instructor_user)) {
    $instructor_user = new User();
    $instructor_user->getUserById($_SESSION['user_id']);
}
?>

<!-- Sidebar -->
<div class="sidebar position-fixed" style="width: 250px; z-index: 1000;">
    <div class="p-3">
        <h4 class="text-white">
            <i class="fas fa-chalkboard-teacher me-2"></i>
            Instructor Panel
        </h4>
    </div>

    <!-- Instructor Profile Section -->
    <div class="instructor-profile">
        <img src="<?php echo $instructor_user->getProfileImageUrl(); ?>"
             alt="Profile" class="instructor-avatar"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <div class="instructor-initials" style="display: none;">
            <?php echo strtoupper(substr($instructor_user->first_name, 0, 1) . substr($instructor_user->last_name, 0, 1)); ?>
        </div>

        <h5 class="text-white mb-1 mt-2"><?php echo htmlspecialchars($instructor_user->first_name . ' ' . $instructor_user->last_name); ?></h5>
        <small class="text-white-50">Instructor</small>
    </div>

    <nav class="nav flex-column px-3 mt-3">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : ''; ?>" href="courses.php">
            <i class="fas fa-book me-2"></i>My Courses
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'create-course.php' ? 'active' : ''; ?>" href="create-course.php">
            <i class="fas fa-plus me-2"></i>Create Course
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>" href="students.php">
            <i class="fas fa-user-graduate me-2"></i>Students
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
            <i class="fas fa-chart-bar me-2"></i>Analytics
        </a>
        <hr class="text-white-50">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
            <i class="fas fa-user-edit me-2"></i>My Profile
        </a>
        <a class="nav-link" href="<?php echo SITE_URL; ?>/index.php">
            <i class="fas fa-home me-2"></i>Back to Site
        </a>
        <a class="nav-link" href="<?php echo SITE_URL; ?>/auth/logout.php">
            <i class="fas fa-sign-out-alt me-2"></i>Logout
        </a>
    </nav>
</div>

<style>
.sidebar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    height: 100vh;
    overflow-y: auto;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.instructor-profile {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    text-align: center;
}

.instructor-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,0.3);
    margin-bottom: 10px;
    display: block;
    margin-left: auto;
    margin-right: auto;
}

.instructor-initials {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.8rem;
    margin: 0 auto 10px;
    border: 3px solid rgba(255,255,255,0.3);
}

.nav-link {
    color: rgba(255,255,255,0.8) !important;
    padding: 12px 15px;
    margin: 2px 0;
    border-radius: 8px;
    transition: all 0.3s ease;
    text-decoration: none;
}

.nav-link:hover {
    background: rgba(255,255,255,0.1);
    color: white !important;
    transform: translateX(5px);
}

.nav-link.active {
    background: rgba(255,255,255,0.2);
    color: white !important;
    font-weight: 600;
}

.nav-link i {
    width: 20px;
    text-align: center;
}

.sidebar h4 {
    font-weight: 600;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.sidebar h5 {
    font-weight: 500;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }

    .sidebar.show {
        transform: translateX(0);
    }
}
</style>
