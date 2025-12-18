<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Quiz.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isLoggedIn() || !hasRole(ROLE_STUDENT)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['quiz_id']) || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$quiz_id = (int)$input['quiz_id'];
$action = $input['action'];
$student_id = $_SESSION['user_id'];

$quiz = new Quiz();

try {
    // Verify quiz exists and student has access
    $quiz_data = $quiz->getQuizById($quiz_id);
    if (!$quiz_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Quiz not found']);
        exit();
    }

    // Check if student is enrolled in the course
    $database = new Database();
    $conn = $database->getConnection();
    
    $enrollment_query = "SELECT id FROM enrollments 
                        WHERE student_id = :student_id AND course_id = :course_id";
    $enrollment_stmt = $conn->prepare($enrollment_query);
    $enrollment_stmt->bindParam(':student_id', $student_id);
    $enrollment_stmt->bindParam(':course_id', $quiz_data['course_id']);
    $enrollment_stmt->execute();
    
    if ($enrollment_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not enrolled in this course']);
        exit();
    }

    switch ($action) {
        case 'mark_completed':
            $result = $quiz->markQuizAsCompleted($quiz_id, $student_id);
            if ($result) {
                // Log activity
                logActivity($student_id, 'quiz_completed', "Marked quiz as completed: $quiz_id");
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Quiz marked as completed!',
                    'is_completed' => true
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to mark quiz as completed']);
            }
            break;

        case 'mark_incomplete':
            $result = $quiz->updateQuizProgress($quiz_id, $student_id, $quiz_data['course_id'], false);
            if ($result) {
                // Log activity
                logActivity($student_id, 'quiz_progress', "Marked quiz as incomplete: $quiz_id");
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Quiz marked as incomplete',
                    'is_completed' => false
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update quiz progress']);
            }
            break;

        case 'get_status':
            $is_completed = $quiz->isQuizCompleted($quiz_id, $student_id);
            $progress = $quiz->getQuizProgress($quiz_id, $student_id);
            
            echo json_encode([
                'success' => true,
                'is_completed' => $is_completed,
                'progress' => $progress
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
