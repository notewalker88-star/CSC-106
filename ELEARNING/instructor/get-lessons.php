<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Lesson.php';
require_once __DIR__ . '/../classes/Course.php';

// Check if user is logged in and is an instructor
if (!isLoggedIn() || !hasRole(ROLE_INSTRUCTOR)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if (!isset($_GET['course_id'])) {
    echo json_encode(['success' => false, 'message' => 'Course ID required']);
    exit();
}

$course_id = (int)$_GET['course_id'];
$instructor_id = $_SESSION['user_id'];

// Verify course ownership
$course = new Course();
$course_data = $course->getCourseById($course_id);

if (!$course_data || $course_data['instructor_id'] != $instructor_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid course']);
    exit();
}

// Get lessons
$lesson = new Lesson();
$lessons = $lesson->getLessonsByCourse($course_id, false); // Get all lessons including unpublished

echo json_encode([
    'success' => true,
    'lessons' => $lessons
]);
?>
