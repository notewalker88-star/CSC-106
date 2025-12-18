<?php
/**
 * Helper Functions
 * E-Learning System
 */

/**
 * Redirect with message
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit();
}

/**
 * Display flash message
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';

        $alert_class = 'alert-success';
        $icon = 'fas fa-check-circle';

        switch ($type) {
            case 'error':
                $alert_class = 'alert-danger';
                $icon = 'fas fa-exclamation-circle';
                break;
            case 'warning':
                $alert_class = 'alert-warning';
                $icon = 'fas fa-exclamation-triangle';
                break;
            case 'info':
                $alert_class = 'alert-info';
                $icon = 'fas fa-info-circle';
                break;
        }

        echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">
                <i class="' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';

        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

/**
 * Upload file
 */
function uploadFile($file, $upload_dir, $allowed_types = [], $max_size = null) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload.'];
    }

    // Check for upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'message' => 'No file was uploaded.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'message' => 'File is too large.'];
        default:
            return ['success' => false, 'message' => 'Unknown upload error.'];
    }

    // Check file size
    $max_size = $max_size ?: MAX_FILE_SIZE;
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File is too large. Maximum size is ' . formatFileSize($max_size) . '.'];
    }

    // Check file type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!empty($allowed_types) && !in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowed_types)];
    }

    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;

    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => str_replace(UPLOAD_PATH, SITE_URL . '/uploads/', $filepath)
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file.'];
    }
}

/**
 * Delete file
 */
function deleteFile($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return true;
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    $errors = [];

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }

    return $errors;
}

/**
 * Generate pagination
 */
function generatePagination($current_page, $total_pages, $base_url, $params = []) {
    if ($total_pages <= 1) {
        return '';
    }

    $pagination = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';

    // Build query string
    $query_params = $params;
    $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';

    // Previous button
    if ($current_page > 1) {
        $prev_page = $current_page - 1;
        $pagination .= '<li class="page-item">
                          <a class="page-link" href="' . $base_url . '?page=' . $prev_page . $query_string . '">
                            <i class="fas fa-chevron-left"></i>
                          </a>
                        </li>';
    }

    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);

    if ($start > 1) {
        $pagination .= '<li class="page-item">
                          <a class="page-link" href="' . $base_url . '?page=1' . $query_string . '">1</a>
                        </li>';
        if ($start > 2) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $current_page) ? 'active' : '';
        $pagination .= '<li class="page-item ' . $active . '">
                          <a class="page-link" href="' . $base_url . '?page=' . $i . $query_string . '">' . $i . '</a>
                        </li>';
    }

    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $pagination .= '<li class="page-item">
                          <a class="page-link" href="' . $base_url . '?page=' . $total_pages . $query_string . '">' . $total_pages . '</a>
                        </li>';
    }

    // Next button
    if ($current_page < $total_pages) {
        $next_page = $current_page + 1;
        $pagination .= '<li class="page-item">
                          <a class="page-link" href="' . $base_url . '?page=' . $next_page . $query_string . '">
                            <i class="fas fa-chevron-right"></i>
                          </a>
                        </li>';
    }

    $pagination .= '</ul></nav>';

    return $pagination;
}

/**
 * Time ago function
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);

    if ($time < 60) {
        return 'just now';
    } elseif ($time < 3600) {
        return floor($time / 60) . ' minutes ago';
    } elseif ($time < 86400) {
        return floor($time / 3600) . ' hours ago';
    } elseif ($time < 2592000) {
        return floor($time / 86400) . ' days ago';
    } elseif ($time < 31536000) {
        return floor($time / 2592000) . ' months ago';
    } else {
        return floor($time / 31536000) . ' years ago';
    }
}

/**
 * Truncate text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }

    return substr($text, 0, $length) . $suffix;
}

/**
 * Generate slug from text
 */
function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = trim($text, '-');

    return $text;
}

/**
 * Get user avatar URL
 */
function getUserAvatar($user_id, $profile_image = null, $email = null) {
    // Check for uploaded profile image
    if ($profile_image && file_exists(UPLOAD_PATH . 'profiles/' . $profile_image)) {
        return SITE_URL . '/uploads/profiles/' . $profile_image;
    }

    // Try Gravatar if email is provided
    if ($email) {
        $gravatar_url = getGravatarUrl($email, 150);
        return $gravatar_url;
    }

    // Default avatar fallback
    return SITE_URL . '/assets/images/default-avatar.png';
}

/**
 * Get Gravatar URL
 */
function getGravatarUrl($email, $size = 150, $default = 'mp') {
    $hash = md5(strtolower(trim($email)));
    $default_url = urlencode(SITE_URL . '/assets/images/default-avatar.png');
    return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d={$default_url}";
}

/**
 * Generate avatar HTML with multiple fallbacks
 */
function generateAvatarHtml($user_data, $size = 40, $class = 'rounded-circle') {
    $profile_image = $user_data['profile_image'] ?? null;
    $email = $user_data['email'] ?? null;
    $first_name = $user_data['first_name'] ?? '';
    $last_name = $user_data['last_name'] ?? '';
    $username = $user_data['username'] ?? '';

    // Get avatar URL
    $avatar_url = getUserAvatar($user_data['id'] ?? 0, $profile_image, $email);

    // Generate initials for fallback
    $initials = '';
    if ($first_name) $initials .= strtoupper(substr($first_name, 0, 1));
    if ($last_name) $initials .= strtoupper(substr($last_name, 0, 1));
    if (!$initials && $username) $initials = strtoupper(substr($username, 0, 2));

    $name = trim($first_name . ' ' . $last_name) ?: $username;

    return '<img src="' . htmlspecialchars($avatar_url) . '"
                 alt="' . htmlspecialchars($name) . '"
                 class="' . htmlspecialchars($class) . '"
                 style="width: ' . (int)$size . 'px; height: ' . (int)$size . 'px; object-fit: cover;"
                 onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">
            <div class="avatar-initials ' . htmlspecialchars($class) . '"
                 style="width: ' . (int)$size . 'px; height: ' . (int)$size . 'px; font-size: ' . round($size/3) . 'px; display: none;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;
                        align-items: center; justify-content: center; font-weight: bold;">
                ' . htmlspecialchars($initials) . '
            </div>';
}

/**
 * Check if user can access course
 */
function canAccessCourse($user_id, $course_id) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Check if user is enrolled or is the instructor
        $query = "SELECT COUNT(*) as count FROM enrollments
                  WHERE student_id = :user_id AND course_id = :course_id
                  UNION ALL
                  SELECT COUNT(*) as count FROM courses
                  WHERE instructor_id = :user_id AND id = :course_id";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $result) {
            if ($result['count'] > 0) {
                return true;
            }
        }

        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Log activity
 */
function logActivity($user_id, $action, $details = null) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $query = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at)
                  VALUES (:user_id, :action, :details, :ip_address, :user_agent, NOW())";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':details', $details);
        $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
        $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);

        return $stmt->execute();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if user is enrolled in a course
 */
function isUserEnrolled($user_id, $course_id) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $query = "SELECT id FROM enrollments WHERE student_id = :student_id AND course_id = :course_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $user_id);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get enrollment details for a user and course
 */
function getEnrollmentDetails($user_id, $course_id) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $query = "SELECT * FROM enrollments WHERE student_id = :student_id AND course_id = :course_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $user_id);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Process course enrollment with payment
 */
function processEnrollment($student_id, $course_id, $payment_amount, $payment_method = 'card') {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Start transaction
        $conn->beginTransaction();

        // Create enrollment
        $query = "INSERT INTO enrollments (student_id, course_id, enrollment_date, progress_percentage, is_completed, payment_status, payment_amount)
                  VALUES (:student_id, :course_id, NOW(), 0, 0, 'completed', :payment_amount)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->bindParam(':payment_amount', $payment_amount);

        if (!$stmt->execute()) {
            $conn->rollBack();
            return false;
        }

        // Update course enrollment count
        $query = "UPDATE courses SET enrollment_count = enrollment_count + 1 WHERE id = :course_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':course_id', $course_id);

        if (!$stmt->execute()) {
            $conn->rollBack();
            return false;
        }

        // Commit transaction
        $conn->commit();
        return true;

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        return false;
    }
}

/**
 * Validate payment card details (basic validation)
 */
function validateCardDetails($card_number, $expiry_month, $expiry_year, $cvv) {
    $errors = [];

    // Remove spaces and validate card number
    $card_number = str_replace(' ', '', $card_number);
    if (empty($card_number) || !ctype_digit($card_number) || strlen($card_number) < 13 || strlen($card_number) > 19) {
        $errors[] = 'Invalid card number';
    }

    // Validate expiry date
    if (empty($expiry_month) || empty($expiry_year)) {
        $errors[] = 'Expiry date is required';
    } else {
        $current_year = (int)date('Y');
        $current_month = (int)date('m');
        $exp_year = (int)$expiry_year;
        $exp_month = (int)$expiry_month;

        if ($exp_year < $current_year || ($exp_year == $current_year && $exp_month < $current_month)) {
            $errors[] = 'Card has expired';
        }
    }

    // Validate CVV
    if (empty($cvv) || !ctype_digit($cvv) || strlen($cvv) < 3 || strlen($cvv) > 4) {
        $errors[] = 'Invalid CVV';
    }

    return $errors;
}

/**
 * Unenroll student from course
 */
function unenrollStudent($student_id, $course_id) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Start transaction
        $conn->beginTransaction();

        // Get enrollment details before deletion
        $query = "SELECT e.*, c.title FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = :student_id AND e.course_id = :course_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Enrollment not found'];
        }

        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

        // Delete lesson progress
        $query = "DELETE lp FROM lesson_progress lp
                 JOIN lessons l ON lp.lesson_id = l.id
                 WHERE lp.student_id = :student_id AND l.course_id = :course_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();

        // Delete enrollment
        $query = "DELETE FROM enrollments WHERE student_id = :student_id AND course_id = :course_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':course_id', $course_id);

        if (!$stmt->execute()) {
            $conn->rollBack();
            return ['success' => false, 'message' => 'Failed to delete enrollment'];
        }

        // Update course enrollment count
        $query = "UPDATE courses SET enrollment_count = GREATEST(enrollment_count - 1, 0) WHERE id = :course_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        // Log activity
        logActivity($student_id, 'course_unenrolled', "Unenrolled from course: {$enrollment['title']}");

        return ['success' => true, 'message' => 'Successfully unenrolled from course', 'enrollment' => $enrollment];

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        return ['success' => false, 'message' => 'An error occurred during unenrollment'];
    }
}

/**
 * Check if student can unenroll from course
 */
function canUnenrollFromCourse($student_id, $course_id) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Check if enrollment exists
        $query = "SELECT e.*, c.is_free, c.price FROM enrollments e
                 JOIN courses c ON e.course_id = c.id
                 WHERE e.student_id = :student_id AND e.course_id = :course_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            return ['can_unenroll' => false, 'reason' => 'Not enrolled in this course'];
        }

        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if course is completed
        if ($enrollment['is_completed']) {
            return ['can_unenroll' => true, 'reason' => 'Course completed - unenrollment will remove certificate access'];
        }

        // Check if it's a paid course
        if (!$enrollment['is_free'] && $enrollment['payment_amount'] > 0) {
            return ['can_unenroll' => true, 'reason' => 'Paid course - contact support for refund requests'];
        }

        return ['can_unenroll' => true, 'reason' => 'Can unenroll'];

    } catch (Exception $e) {
        return ['can_unenroll' => false, 'reason' => 'Error checking enrollment status'];
    }
}

/**
 * Get unenrollment statistics for admin/instructor
 */
function getUnenrollmentStats($course_id = null, $instructor_id = null) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $where_conditions = [];
        $params = [];

        if ($course_id) {
            $where_conditions[] = "course_id = :course_id";
            $params[':course_id'] = $course_id;
        }

        if ($instructor_id) {
            $where_conditions[] = "course_id IN (SELECT id FROM courses WHERE instructor_id = :instructor_id)";
            $params[':instructor_id'] = $instructor_id;
        }

        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

        // Get unenrollment activity from logs
        $query = "SELECT COUNT(*) as unenroll_count,
                         DATE(created_at) as unenroll_date
                  FROM activity_logs
                  {$where_clause} AND action = 'course_unenrolled'
                  GROUP BY DATE(created_at)
                  ORDER BY unenroll_date DESC
                  LIMIT 30";

        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        return [];
    }
}

/**
 * Send email notification (placeholder for future implementation)
 */
function sendEmail($to, $subject, $message, $from = null) {
    // This is a placeholder function
    // In a real implementation, you would use PHPMailer or similar
    $from = $from ?: SITE_EMAIL;

    $headers = "From: " . $from . "\r\n";
    $headers .= "Reply-To: " . $from . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    return mail($to, $subject, $message, $headers);
}

/**
 * Calculate course progress
 */
function calculateCourseProgress($user_id, $course_id) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Get total lessons in course
        $query = "SELECT COUNT(*) as total_lessons FROM lessons WHERE course_id = :course_id AND is_published = 1";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();
        $total_lessons = $stmt->fetch(PDO::FETCH_ASSOC)['total_lessons'];

        if ($total_lessons == 0) {
            return 0;
        }

        // Get completed lessons
        $query = "SELECT COUNT(*) as completed_lessons
                  FROM lesson_progress lp
                  JOIN lessons l ON lp.lesson_id = l.id
                  WHERE lp.student_id = :user_id AND l.course_id = :course_id AND lp.is_completed = 1";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();
        $completed_lessons = $stmt->fetch(PDO::FETCH_ASSOC)['completed_lessons'];

        return round(($completed_lessons / $total_lessons) * 100, 2);
    } catch (Exception $e) {
        return 0;
    }
}
?>
