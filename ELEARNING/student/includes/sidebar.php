<?php
/**
 * Student Sidebar with Profile
 */

// Include required classes if not already included
if (!class_exists('User')) {
    require_once __DIR__ . '/../../classes/User.php';
}

// Get student profile information if not already loaded
if (!isset($student_user)) {
    $student_user = new User();
    $student_user->getUserById($_SESSION['user_id']);
}
?>

<!-- Sidebar -->
<div class="sidebar position-fixed" style="width: 250px; z-index: 1000;">
    <div class="p-3">
        <h4 class="text-white">
            <i class="fas fa-graduation-cap me-2"></i>
            Student Portal
        </h4>
    </div>

    <!-- Student Profile Section -->
    <div class="admin-profile">
        <img src="<?php echo $student_user->getProfileImageUrl(); ?>"
             alt="Profile" class="admin-avatar"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <div class="admin-initials" style="display: none;">
            <?php echo strtoupper(substr($student_user->first_name, 0, 1) . substr($student_user->last_name, 0, 1)); ?>
        </div>

        <h5 class="text-white mb-1 mt-2"><?php echo htmlspecialchars($student_user->first_name . ' ' . $student_user->last_name); ?></h5>
        <small class="text-white-50">Student</small>
    </div>

    <nav class="nav flex-column px-3 mt-3">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : ''; ?>" href="courses.php">
            <i class="fas fa-book me-2"></i>My Courses
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'progress.php' ? 'active' : ''; ?>" href="progress.php">
            <i class="fas fa-chart-line me-2"></i>Progress
        </a>
        <a class="nav-link" href="../courses.php">
            <i class="fas fa-search me-2"></i>Browse Courses
        </a>
        <hr class="text-white-50">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
            <i class="fas fa-user-edit me-2"></i>My Profile
        </a>
        <a class="nav-link" href="../index.php">
            <i class="fas fa-home me-2"></i>Back to Site
        </a>
        <a class="nav-link" href="../auth/logout.php">
            <i class="fas fa-sign-out-alt me-2"></i>Logout
        </a>
    </nav>
</div>

<style>
.admin-profile {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    text-align: center;
}

.admin-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,0.3);
    margin-bottom: 10px;
}

.admin-initials {
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
</style>
