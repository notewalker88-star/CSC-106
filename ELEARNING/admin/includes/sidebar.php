<?php
/**
 * Admin Sidebar with Profile
 */

// Include required classes if not already included
if (!class_exists('User')) {
    require_once __DIR__ . '/../../classes/User.php';
}

// Get admin profile information if not already loaded
if (!isset($admin_user)) {
    $admin_user = new User();
    $admin_user->getUserById($_SESSION['user_id']);
}
?>

<!-- Sidebar -->
<div class="sidebar position-fixed" style="width: 250px; z-index: 1000; height: 100vh; overflow-y: auto;">
    <div class="p-3">
        <h4 class="text-white">
            <i class="fas fa-graduation-cap me-2"></i>
            Admin Panel
        </h4>
    </div>

    <!-- Admin Profile Section -->
    <div class="admin-profile">
        <img src="<?php echo $admin_user->getProfileImageUrl(); ?>"
             alt="Profile" class="admin-avatar"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <div class="admin-initials" style="display: none;">
            <?php echo strtoupper(substr($admin_user->first_name, 0, 1) . substr($admin_user->last_name, 0, 1)); ?>
        </div>

        <h5 class="text-white mb-1 mt-2"><?php echo htmlspecialchars($admin_user->first_name . ' ' . $admin_user->last_name); ?></h5>
        <small class="text-white-50">Administrator</small>
    </div>

    <nav class="nav flex-column px-3 mt-3 pb-5">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
            <i class="fas fa-users me-2"></i>Users
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : ''; ?>" href="courses.php">
            <i class="fas fa-book me-2"></i>Courses
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" href="categories.php">
            <i class="fas fa-tags me-2"></i>Categories
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'enrollments.php' ? 'active' : ''; ?>" href="enrollments.php">
            <i class="fas fa-user-graduate me-2"></i>Enrollments
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contact-messages.php' ? 'active' : ''; ?>" href="contact-messages.php">
            <i class="fas fa-envelope me-2"></i>Contact Messages
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
            <i class="fas fa-cog me-2"></i>Settings
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

/* Ensure sidebar is scrollable */
.sidebar {
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.3) transparent;
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.3);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.5);
}
</style>
