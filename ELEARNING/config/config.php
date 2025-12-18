<?php
/**
 * Main Configuration File
 * E-Learning System
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// Site Configuration
define('SITE_NAME', 'E-Learning Platform');
define('SITE_URL', 'http://localhost/elearning');
define('SITE_EMAIL', 'admin@school.com');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'elearning_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// File Upload Configuration
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'avi', 'mov', 'wmv']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'xls', 'xlsx', 'rtf', 'odt', 'ods', 'odp']);

// Security Configuration
define('PASSWORD_MIN_LENGTH', 6);
define('SESSION_TIMEOUT', 3600); // 1 hour
define('CSRF_TOKEN_NAME', 'csrf_token');

// Pagination
define('ITEMS_PER_PAGE', 12);
define('LESSONS_PER_PAGE', 10);

// Email Configuration (for future use)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');

// Course Configuration
define('MAX_COURSE_TITLE_LENGTH', 200);
define('MAX_LESSON_TITLE_LENGTH', 200);
define('DEFAULT_COURSE_THUMBNAIL', 'assets/images/default-course.jpg');

// User Roles
define('ROLE_STUDENT', 'student');
define('ROLE_INSTRUCTOR', 'instructor');
define('ROLE_ADMIN', 'admin');

// Include required files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Auto-create uploads directory if it doesn't exist
$upload_dirs = [
    UPLOAD_PATH,
    UPLOAD_PATH . 'courses/',
    UPLOAD_PATH . 'lessons/',
    UPLOAD_PATH . 'profiles/',
    UPLOAD_PATH . 'certificates/'
];

foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * Get site configuration from database
 */
function getSiteConfig($key = null) {
    static $config = null;

    if ($config === null) {
        try {
            $database = new Database();
            $conn = $database->getConnection();

            $query = "SELECT setting_key, setting_value FROM settings";
            $stmt = $conn->prepare($query);
            $stmt->execute();

            $config = [];
            while ($row = $stmt->fetch()) {
                $config[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $config = [];
        }
    }

    if ($key) {
        return isset($config[$key]) ? $config[$key] : null;
    }

    return $config;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/auth/login.php');
        exit();
    }
}

/**
 * Redirect to login if user doesn't have required role
 */
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ' . SITE_URL . '/index.php?error=access_denied');
        exit();
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Format file size
 */
function formatFileSize($size) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, 2) . ' ' . $units[$i];
}

/**
 * Format duration in seconds to human readable format
 */
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    } else {
        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

/**
 * Format currency based on site settings
 */
function formatCurrency($amount, $currency = null) {
    if ($currency === null) {
        $currency = getSiteConfig('currency') ?: 'PHP';
    }

    $amount = (float)$amount;

    switch ($currency) {
        case 'PHP':
            return '₱' . number_format($amount, 2);
        case 'USD':
            return '$' . number_format($amount, 2);
        case 'EUR':
            return '€' . number_format($amount, 2);
        case 'GBP':
            return '£' . number_format($amount, 2);
        case 'CAD':
            return 'C$' . number_format($amount, 2);
        case 'AUD':
            return 'A$' . number_format($amount, 2);
        default:
            return $currency . ' ' . number_format($amount, 2);
    }
}

/**
 * Get file icon based on file extension
 */
function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $icons = [
        // Documents
        'pdf' => 'fas fa-file-pdf text-danger',
        'doc' => 'fas fa-file-word text-primary',
        'docx' => 'fas fa-file-word text-primary',
        'txt' => 'fas fa-file-alt text-secondary',
        'rtf' => 'fas fa-file-alt text-secondary',
        'odt' => 'fas fa-file-word text-primary',

        // Spreadsheets
        'xls' => 'fas fa-file-excel text-success',
        'xlsx' => 'fas fa-file-excel text-success',
        'ods' => 'fas fa-file-excel text-success',

        // Presentations
        'ppt' => 'fas fa-file-powerpoint text-warning',
        'pptx' => 'fas fa-file-powerpoint text-warning',
        'odp' => 'fas fa-file-powerpoint text-warning',

        // Images
        'jpg' => 'fas fa-file-image text-info',
        'jpeg' => 'fas fa-file-image text-info',
        'png' => 'fas fa-file-image text-info',
        'gif' => 'fas fa-file-image text-info',

        // Videos
        'mp4' => 'fas fa-file-video text-dark',
        'avi' => 'fas fa-file-video text-dark',
        'mov' => 'fas fa-file-video text-dark',
        'wmv' => 'fas fa-file-video text-dark',

        // Audio
        'mp3' => 'fas fa-file-audio text-purple',
        'wav' => 'fas fa-file-audio text-purple',
        'ogg' => 'fas fa-file-audio text-purple',
    ];

    return $icons[$extension] ?? 'fas fa-file text-muted';
}
?>
