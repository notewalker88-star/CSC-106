<?php
/**
 * Update Lesson Progress
 * AJAX endpoint to track lesson progress and completion
 */

require_once __DIR__ . '/config/config.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || !hasRole(ROLE_STUDENT)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

$lesson_id = isset($input['lesson_id']) ? (int)$input['lesson_id'] : 0;
$course_id = isset($input['course_id']) ? (int)$input['course_id'] : 0;
$is_completed = isset($input['is_completed']) ? (bool)$input['is_completed'] : false;
$time_spent = isset($input['time_spent']) ? (int)$input['time_spent'] : 0;
$last_position = isset($input['last_position']) ? (int)$input['last_position'] : 0;
$force_update = isset($input['force_update']) ? (bool)$input['force_update'] : false;

if (!$lesson_id || !$course_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$student_id = $_SESSION['user_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Verify student is enrolled in the course
    $query = "SELECT COUNT(*) FROM enrollments WHERE student_id = :student_id AND course_id = :course_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();

    if ($stmt->fetchColumn() == 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not enrolled in this course']);
        exit();
    }

    // Verify lesson belongs to the course
    $query = "SELECT COUNT(*) FROM lessons WHERE id = :lesson_id AND course_id = :course_id AND is_published = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':lesson_id', $lesson_id);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();

    if ($stmt->fetchColumn() == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Lesson not found']);
        exit();
    }

    // Start transaction
    $conn->beginTransaction();

    // Check if progress record exists
    $query = "SELECT id, time_spent FROM lesson_progress
              WHERE student_id = :student_id AND lesson_id = :lesson_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':lesson_id', $lesson_id);
    $stmt->execute();
    $existing_progress = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_progress) {
        // Update existing progress
        $new_time_spent = $existing_progress['time_spent'] + $time_spent;

        // Handle completion date based on completion status and force update
        $completion_date_sql = "completion_date";
        $update_completion = false;

        if ($is_completed) {
            $completion_date_sql = "CURRENT_TIMESTAMP";
            $update_completion = true;
        } elseif ($force_update && !$is_completed) {
            // Force unmarking - set completion_date to NULL
            $completion_date_sql = "NULL";
            $update_completion = true;
        }

        // If this is just a time tracking update (not force update and not marking complete),
        // don't change the completion status
        if ($update_completion) {
            $query = "UPDATE lesson_progress
                      SET is_completed = :is_completed,
                          time_spent = :time_spent,
                          last_position = :last_position,
                          completion_date = " . $completion_date_sql . ",
                          updated_at = CURRENT_TIMESTAMP
                      WHERE student_id = :student_id AND lesson_id = :lesson_id";

            $stmt = $conn->prepare($query);
            $is_completed_int = $is_completed ? 1 : 0;
            $stmt->bindParam(':is_completed', $is_completed_int, PDO::PARAM_INT);
        } else {
            // Just update time and position, don't touch completion status
            $query = "UPDATE lesson_progress
                      SET time_spent = :time_spent,
                          last_position = :last_position,
                          updated_at = CURRENT_TIMESTAMP
                      WHERE student_id = :student_id AND lesson_id = :lesson_id";

            $stmt = $conn->prepare($query);
        }

        $stmt->bindParam(':time_spent', $new_time_spent);
        $stmt->bindParam(':last_position', $last_position);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':lesson_id', $lesson_id);
    } else {
        // Create new progress record
        $query = "INSERT INTO lesson_progress
                  (student_id, lesson_id, course_id, is_completed, time_spent, last_position, completion_date)
                  VALUES (:student_id, :lesson_id, :course_id, :is_completed, :time_spent, :last_position, " .
                  ($is_completed ? "CURRENT_TIMESTAMP" : "NULL") . ")";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':lesson_id', $lesson_id);
        $stmt->bindParam(':course_id', $course_id);
        $is_completed_int = $is_completed ? 1 : 0;
        $stmt->bindParam(':is_completed', $is_completed_int, PDO::PARAM_INT);
        $stmt->bindParam(':time_spent', $time_spent);
        $stmt->bindParam(':last_position', $last_position);
    }

    if (!$stmt->execute()) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update progress']);
        exit();
    }

    // Update course progress when lesson completion status changes
    if ($is_completed || $force_update) {
        // Get total lessons and completed lessons for this course
        $query = "SELECT
                    (SELECT COUNT(*) FROM lessons WHERE course_id = :course_id AND is_published = 1) as total_lessons,
                    (SELECT COUNT(*) FROM lesson_progress lp
                     JOIN lessons l ON lp.lesson_id = l.id
                     WHERE lp.student_id = :student_id AND l.course_id = :course_id2 AND lp.is_completed = 1) as completed_lessons";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':course_id2', $course_id);
        $stmt->execute();
        $course_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $progress_percentage = $course_stats['total_lessons'] > 0 ?
            round(($course_stats['completed_lessons'] / $course_stats['total_lessons']) * 100, 2) : 0;

        $is_course_completed = ($progress_percentage >= 100);

        // Handle course completion date
        $course_completion_sql = "completion_date";
        if ($is_course_completed) {
            $course_completion_sql = "CURRENT_TIMESTAMP";
        } elseif ($force_update && !$is_completed) {
            // If unmarking lesson and course was completed, unmark course too
            $course_completion_sql = "NULL";
        }

        // Update enrollment progress
        $query = "UPDATE enrollments
                  SET progress_percentage = :progress_percentage,
                      is_completed = :is_completed,
                      completion_date = " . $course_completion_sql . "
                  WHERE student_id = :student_id AND course_id = :course_id";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':progress_percentage', $progress_percentage);
        $is_course_completed_int = $is_course_completed ? 1 : 0;
        $stmt->bindParam(':is_completed', $is_course_completed_int, PDO::PARAM_INT);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();
    }

    // Commit transaction
    $conn->commit();

    // Log activity
    if ($is_completed) {
        logActivity($student_id, 'lesson_completed', "Completed lesson ID: $lesson_id");
    } elseif ($force_update && !$is_completed) {
        logActivity($student_id, 'lesson_progress', "Marked lesson as incomplete ID: $lesson_id");
    } else {
        logActivity($student_id, 'lesson_progress', "Updated progress for lesson ID: $lesson_id");
    }

    echo json_encode([
        'success' => true,
        'message' => $is_completed ? 'Lesson completed!' : 'Progress updated',
        'is_completed' => $is_completed
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }

    error_log("Lesson progress update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>
