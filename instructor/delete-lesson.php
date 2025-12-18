<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Lesson.php';

// Check if user is logged in and is an instructor
if (!isLoggedIn() || !hasRole(ROLE_INSTRUCTOR)) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$instructor_id = $_SESSION['user_id'];

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['lesson_id'])) {
    $lesson_id = (int)$_POST['lesson_id'];
    $lesson = new Lesson();

    // Get lesson data to verify ownership
    $lesson_data = $lesson->getLessonById($lesson_id);

    if (!$lesson_data || $lesson_data['instructor_id'] != $instructor_id) {
        header('Location: ' . SITE_URL . '/instructor/lessons.php?error=unauthorized');
        exit();
    }

    $course_id = $lesson_data['course_id'];

    // Delete the lesson
    if ($lesson->delete($lesson_id)) {
        header('Location: ' . SITE_URL . '/instructor/lessons.php?course_id=' . $course_id . '&success=lesson_deleted');
    } else {
        header('Location: ' . SITE_URL . '/instructor/lessons.php?course_id=' . $course_id . '&error=delete_failed');
    }
    exit();
}

// Handle direct GET request (redirect to lessons page)
if (isset($_GET['id']) && isset($_GET['course_id'])) {
    $lesson_id = (int)$_GET['id'];
    $course_id = (int)$_GET['course_id'];

    $lesson = new Lesson();
    $lesson_data = $lesson->getLessonById($lesson_id);

    if ($lesson_data && $lesson_data['instructor_id'] == $instructor_id) {
        if ($lesson->delete($lesson_id)) {
            header('Location: ' . SITE_URL . '/instructor/lessons.php?course_id=' . $course_id . '&success=lesson_deleted');
        } else {
            header('Location: ' . SITE_URL . '/instructor/lessons.php?course_id=' . $course_id . '&error=delete_failed');
        }
    } else {
        header('Location: ' . SITE_URL . '/instructor/lessons.php?error=unauthorized');
    }
    exit();
}

// Invalid request
header('Location: ' . SITE_URL . '/instructor/lessons.php');
exit();
?>
