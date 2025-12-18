<?php
/**
 * Secure File Download Handler
 * Handles downloading of lesson attachments and course materials
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Course.php';
require_once __DIR__ . '/classes/Lesson.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied. Please log in.');
}

// Get parameters
$type = isset($_GET['type']) ? $_GET['type'] : '';
$file = isset($_GET['file']) ? $_GET['file'] : '';
$lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
$disposition = isset($_GET['disposition']) ? strtolower($_GET['disposition']) : 'attachment';

// Validate parameters
if (empty($type) || empty($file)) {
    header('HTTP/1.0 400 Bad Request');
    exit('Invalid parameters.');
}

// Sanitize filename
$file = basename($file);

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($type === 'lesson') {
        // Handle lesson attachment downloads
        if ($lesson_id <= 0) {
            header('HTTP/1.0 400 Bad Request');
            exit('Invalid lesson ID.');
        }
        
        // Get lesson data
        $lesson = new Lesson();
        $lesson_data = $lesson->getLessonById($lesson_id);
        
        if (!$lesson_data) {
            header('HTTP/1.0 404 Not Found');
            exit('Lesson not found.');
        }
        
        // Check if user has access to this lesson
        $user_id = $_SESSION['user_id'];
        $user_role = $_SESSION['user_role'];
        
        // Instructors can download their own lesson files
        if ($user_role === ROLE_INSTRUCTOR && $lesson_data['instructor_id'] == $user_id) {
            $has_access = true;
        }
        // Admins can download any files
        elseif ($user_role === ROLE_ADMIN) {
            $has_access = true;
        }
        // Students: allow preview inline view; downloads require enrollment
        elseif ($user_role === ROLE_STUDENT) {
            $is_preview = !empty($lesson_data['is_preview']);
            if ($is_preview && $disposition === 'inline') {
                $has_access = true;
            } else {
                $query = "SELECT COUNT(*) FROM enrollments WHERE student_id = :user_id AND course_id = :course_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':course_id', $lesson_data['course_id']);
                $stmt->execute();
                $has_access = $stmt->fetchColumn() > 0;
            }
        }
        else {
            $has_access = false;
        }
        
        if (!$has_access) {
            header('HTTP/1.0 403 Forbidden');
            exit('You do not have permission to download this file.');
        }
        
        // Verify file exists in lesson attachments
        $attachments = json_decode($lesson_data['attachments'], true);
        $file_found = false;
        $original_name = $file;
        
        if (is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if ($attachment['filename'] === $file) {
                    $file_found = true;
                    $original_name = $attachment['original_name'];
                    break;
                }
            }
        }
        
        if (!$file_found) {
            header('HTTP/1.0 404 Not Found');
            exit('File not found in lesson attachments.');
        }
        
        $file_path = UPLOAD_PATH . 'lessons/' . $file;
        
    } else {
        header('HTTP/1.0 400 Bad Request');
        exit('Invalid download type.');
    }
    
    // Check if file exists on disk
    if (!file_exists($file_path)) {
        header('HTTP/1.0 404 Not Found');
        exit('File not found on server.');
    }
    
    // Get file info
    $file_size = filesize($file_path);
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    // Set appropriate content type
    $content_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'rtf' => 'application/rtf',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'mp4' => 'video/mp4',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
        'wmv' => 'video/x-ms-wmv',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg'
    ];
    
    $content_type = $content_types[$file_extension] ?? 'application/octet-stream';
    
    // Prepare headers
    $content_disposition = ($disposition === 'inline') ? 'inline' : 'attachment';
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: ' . $content_disposition . '; filename="' . $original_name . '"');

    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Support HTTP Range requests for PDF.js and media clients
    $range = isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : null;
    if ($range) {
        // Parse range: bytes=start-end
        if (preg_match('/bytes\s*=\s*(\d*)-(\d*)/i', $range, $matches)) {
            $start = ($matches[1] !== '') ? (int)$matches[1] : 0;
            $end = ($matches[2] !== '') ? (int)$matches[2] : ($file_size - 1);
            if ($start > $end || $start >= $file_size) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header('Content-Range: bytes */' . $file_size);
                exit();
            }
            $length = ($end - $start) + 1;
            header('HTTP/1.1 206 Partial Content');
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $file_size);
            header('Content-Length: ' . $length);

            $fp = fopen($file_path, 'rb');
            if ($fp === false) {
                header('HTTP/1.1 500 Internal Server Error');
                exit();
            }
            fseek($fp, $start);
            $bytes_to_send = $length;
            $chunk_size = 8192;
            while ($bytes_to_send > 0 && !feof($fp)) {
                $read = ($bytes_to_send > $chunk_size) ? $chunk_size : $bytes_to_send;
                $buffer = fread($fp, $read);
                echo $buffer;
                flush();
                $bytes_to_send -= strlen($buffer);
            }
            fclose($fp);
            exit();
        }
    }

    // No range: send full file
    header('Content-Length: ' . $file_size);
    readfile($file_path);
    exit();
    
} catch (Exception $e) {
    error_log('Download error: ' . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    exit('An error occurred while processing your download request.');
}
?>
