<?php
/**
 * Delete Lesson Attachment Handler
 * Handles AJAX requests to delete specific attachments from lessons
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Lesson.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in and is an instructor
if (!isLoggedIn() || !hasRole(ROLE_INSTRUCTOR)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // Fallback to regular POST data
    $lesson_id = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
    $attachment_index = isset($_POST['attachment_index']) ? (int)$_POST['attachment_index'] : -1;
} else {
    $lesson_id = isset($input['lesson_id']) ? (int)$input['lesson_id'] : 0;
    $attachment_index = isset($input['attachment_index']) ? (int)$input['attachment_index'] : -1;
}

// Validate parameters
if ($lesson_id <= 0 || $attachment_index < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

$instructor_id = $_SESSION['user_id'];

try {
    $lesson = new Lesson();
    
    // Get lesson data to verify ownership
    $lesson_data = $lesson->getLessonById($lesson_id);
    
    if (!$lesson_data) {
        echo json_encode(['success' => false, 'message' => 'Lesson not found']);
        exit();
    }
    
    if ($lesson_data['instructor_id'] != $instructor_id) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this lesson']);
        exit();
    }
    
    // Check if attachment exists
    $attachments = [];
    if ($lesson_data['attachments']) {
        $attachments = json_decode($lesson_data['attachments'], true);
        if (!is_array($attachments)) {
            $attachments = [];
        }
    }
    
    if (!isset($attachments[$attachment_index])) {
        echo json_encode(['success' => false, 'message' => 'Attachment not found']);
        exit();
    }
    
    // Get attachment info before deletion
    $attachment_name = $attachments[$attachment_index]['original_name'];
    
    // Remove the attachment
    if ($lesson->removeAttachment($lesson_id, $attachment_index)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Attachment "' . $attachment_name . '" deleted successfully',
            'attachment_index' => $attachment_index
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete attachment']);
    }
    
} catch (Exception $e) {
    error_log('Delete attachment error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the attachment']);
}
?>
