<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';

// Logout user
$user = new User();
$user->logout();

// Clear remember me cookie
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/');
}

// Redirect to home page with success message
header('Location: ' . SITE_URL . '/index.php?message=logged_out');
exit();
?>
