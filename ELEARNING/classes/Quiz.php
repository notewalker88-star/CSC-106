<?php
/**
 * Quiz Class
 * Handles quiz management
 */

require_once __DIR__ . '/../config/config.php';

class Quiz {
    private $conn;
    private $table_name = "quizzes";

    public $id;
    public $lesson_id;
    public $course_id;
    public $title;
    public $description;
    public $time_limit;
    public $passing_score;
    public $max_attempts;
    public $is_active;
    public $created_at;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Create a new quiz
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET lesson_id=:lesson_id, course_id=:course_id, title=:title,
                      description=:description, time_limit=:time_limit,
                      passing_score=:passing_score, max_attempts=:max_attempts,
                      is_active=:is_active";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));

        // Bind values
        $stmt->bindParam(":lesson_id", $this->lesson_id);
        $stmt->bindParam(":course_id", $this->course_id);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":time_limit", $this->time_limit);
        $stmt->bindParam(":passing_score", $this->passing_score);
        $stmt->bindParam(":max_attempts", $this->max_attempts);
        $stmt->bindParam(":is_active", $this->is_active);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Get quiz by ID
     */
    public function getQuizById($id) {
        $query = "SELECT q.*, c.title as course_title, c.instructor_id,
                         l.title as lesson_title
                  FROM " . $this->table_name . " q
                  LEFT JOIN courses c ON q.course_id = c.id
                  LEFT JOIN lessons l ON q.lesson_id = l.id
                  WHERE q.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }

    /**
     * Get quizzes by course
     */
    public function getQuizzesByCourse($course_id) {
        $query = "SELECT q.*, c.title as course_title, l.title as lesson_title,
                         (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
                         (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) as attempt_count
                  FROM " . $this->table_name . " q
                  LEFT JOIN courses c ON q.course_id = c.id
                  LEFT JOIN lessons l ON q.lesson_id = l.id
                  WHERE q.course_id = :course_id
                  ORDER BY q.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":course_id", $course_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get quizzes by instructor
     */
    public function getQuizzesByInstructor($instructor_id) {
        $query = "SELECT q.*, c.title as course_title, l.title as lesson_title,
                         (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
                         (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) as attempt_count
                  FROM " . $this->table_name . " q
                  LEFT JOIN courses c ON q.course_id = c.id
                  LEFT JOIN lessons l ON q.lesson_id = l.id
                  WHERE c.instructor_id = :instructor_id
                  ORDER BY q.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":instructor_id", $instructor_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update quiz
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET lesson_id=:lesson_id, title=:title, description=:description,
                      time_limit=:time_limit, passing_score=:passing_score,
                      max_attempts=:max_attempts, is_active=:is_active
                  WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));

        // Bind values
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":lesson_id", $this->lesson_id);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":time_limit", $this->time_limit);
        $stmt->bindParam(":passing_score", $this->passing_score);
        $stmt->bindParam(":max_attempts", $this->max_attempts);
        $stmt->bindParam(":is_active", $this->is_active);

        return $stmt->execute();
    }

    /**
     * Delete quiz
     */
    public function delete($id) {
        // First delete all quiz questions and attempts (cascade should handle this)
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    /**
     * Toggle quiz active status
     */
    public function toggleActive($id) {
        $query = "UPDATE " . $this->table_name . "
                  SET is_active = NOT is_active
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    /**
     * Add question to quiz
     */
    public function addQuestion($quiz_id, $question, $question_type, $options, $correct_answer, $points = 1, $explanation = null) {
        $query = "INSERT INTO quiz_questions
                  SET quiz_id=:quiz_id, question=:question, question_type=:question_type,
                      options=:options, correct_answer=:correct_answer, points=:points,
                      explanation=:explanation, question_order=:question_order";

        $stmt = $this->conn->prepare($query);

        // Get next question order
        $order_query = "SELECT COALESCE(MAX(question_order), 0) + 1 as next_order
                       FROM quiz_questions WHERE quiz_id = :quiz_id";
        $order_stmt = $this->conn->prepare($order_query);
        $order_stmt->bindParam(":quiz_id", $quiz_id);
        $order_stmt->execute();
        $next_order = $order_stmt->fetch(PDO::FETCH_ASSOC)['next_order'];

        // Sanitize inputs
        $question = htmlspecialchars(strip_tags($question));
        $correct_answer = htmlspecialchars(strip_tags($correct_answer));
        $explanation = $explanation ? htmlspecialchars(strip_tags($explanation)) : null;

        // Bind values
        $stmt->bindParam(":quiz_id", $quiz_id);
        $stmt->bindParam(":question", $question);
        $stmt->bindParam(":question_type", $question_type);
        $stmt->bindParam(":options", $options);
        $stmt->bindParam(":correct_answer", $correct_answer);
        $stmt->bindParam(":points", $points);
        $stmt->bindParam(":explanation", $explanation);
        $stmt->bindParam(":question_order", $next_order);

        return $stmt->execute();
    }

    /**
     * Get quiz questions
     */
    public function getQuizQuestions($quiz_id) {
        $query = "SELECT * FROM quiz_questions
                  WHERE quiz_id = :quiz_id
                  ORDER BY question_order ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":quiz_id", $quiz_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete question
     */
    public function deleteQuestion($question_id) {
        $query = "DELETE FROM quiz_questions WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $question_id);

        return $stmt->execute();
    }

    /**
     * Get quiz statistics
     */
    public function getQuizStats($quiz_id) {
        $query = "SELECT
                    COUNT(*) as total_attempts,
                    AVG(score) as average_score,
                    MAX(score) as highest_score,
                    MIN(score) as lowest_score,
                    COUNT(CASE WHEN is_passed = 1 THEN 1 END) as passed_count
                  FROM quiz_attempts
                  WHERE quiz_id = :quiz_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":quiz_id", $quiz_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get student's quiz attempts
     */
    public function getStudentAttempts($quiz_id, $student_id) {
        $query = "SELECT * FROM quiz_attempts
                  WHERE quiz_id = :quiz_id AND student_id = :student_id
                  ORDER BY attempt_number DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":quiz_id", $quiz_id);
        $stmt->bindParam(":student_id", $student_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get student's completed quiz attempts only
     */
    public function getCompletedAttempts($quiz_id, $student_id) {
        $query = "SELECT * FROM quiz_attempts
                  WHERE quiz_id = :quiz_id AND student_id = :student_id AND completed_at IS NOT NULL
                  ORDER BY attempt_number DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":quiz_id", $quiz_id);
        $stmt->bindParam(":student_id", $student_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if student can take quiz
     */
    public function canStudentTakeQuiz($quiz_id, $student_id) {
        $quiz_data = $this->getQuizById($quiz_id);
        if (!$quiz_data || !$quiz_data['is_active']) {
            return false;
        }

        // Check if max attempts is set to unlimited (999 or higher)
        if ($quiz_data['max_attempts'] >= 999) {
            return true;
        }

        // Get completed attempts count
        $completed_attempts = $this->getCompletedAttempts($quiz_id, $student_id);
        $attempt_count = count($completed_attempts);

        // Allow student to take quiz if they haven't reached max attempts
        return $attempt_count < $quiz_data['max_attempts'];
    }

    /**
     * Start quiz attempt
     */
    public function startQuizAttempt($quiz_id, $student_id) {
        // Clean up any incomplete attempts first
        $cleanup_query = "DELETE FROM quiz_attempts
                         WHERE quiz_id = :quiz_id AND student_id = :student_id AND completed_at IS NULL";
        $cleanup_stmt = $this->conn->prepare($cleanup_query);
        $cleanup_stmt->bindParam(":quiz_id", $quiz_id);
        $cleanup_stmt->bindParam(":student_id", $student_id);
        $cleanup_stmt->execute();

        // Get next attempt number (only count completed attempts)
        $completed_attempts = $this->getCompletedAttempts($quiz_id, $student_id);
        $attempt_number = count($completed_attempts) + 1;

        // Get total questions
        $questions = $this->getQuizQuestions($quiz_id);
        $total_questions = count($questions);

        $query = "INSERT INTO quiz_attempts
                  SET quiz_id=:quiz_id, student_id=:student_id,
                      attempt_number=:attempt_number, total_questions=:total_questions,
                      started_at=CURRENT_TIMESTAMP";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":quiz_id", $quiz_id);
        $stmt->bindParam(":student_id", $student_id);
        $stmt->bindParam(":attempt_number", $attempt_number);
        $stmt->bindParam(":total_questions", $total_questions);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    /**
     * Submit quiz attempt
     */
    public function submitQuizAttempt($attempt_id, $answers, $time_taken) {
        // Get attempt data
        $query = "SELECT qa.*, q.passing_score FROM quiz_attempts qa
                  JOIN quizzes q ON qa.quiz_id = q.id
                  WHERE qa.id = :attempt_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":attempt_id", $attempt_id);
        $stmt->execute();
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attempt) {
            return false;
        }

        // Get quiz questions
        $questions = $this->getQuizQuestions($attempt['quiz_id']);

        // Calculate score
        $correct_answers = 0;
        $total_points = 0;
        $earned_points = 0;

        foreach ($questions as $question) {
            $total_points += $question['points'];
            $student_answer = isset($answers[$question['id']]) ? trim($answers[$question['id']]) : '';

            if ($this->isAnswerCorrect($question, $student_answer)) {
                $correct_answers++;
                $earned_points += $question['points'];
            }
        }

        $score = $total_points > 0 ? round(($earned_points / $total_points) * 100, 2) : 0;
        $is_passed = $score >= $attempt['passing_score'];

        // Update attempt
        $query = "UPDATE quiz_attempts
                  SET score=:score, correct_answers=:correct_answers,
                      time_taken=:time_taken, is_passed=:is_passed,
                      answers=:answers, completed_at=CURRENT_TIMESTAMP
                  WHERE id=:attempt_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":score", $score);
        $stmt->bindParam(":correct_answers", $correct_answers);
        $stmt->bindParam(":time_taken", $time_taken);
        $stmt->bindParam(":is_passed", $is_passed, PDO::PARAM_BOOL);
        $stmt->bindParam(":answers", json_encode($answers));
        $stmt->bindParam(":attempt_id", $attempt_id);

        $result = $stmt->execute();

        // Update quiz progress after submission
        if ($result) {
            // Get quiz data for course_id
            $quiz_query = "SELECT course_id FROM quizzes WHERE id = (SELECT quiz_id FROM quiz_attempts WHERE id = :attempt_id)";
            $quiz_stmt = $this->conn->prepare($quiz_query);
            $quiz_stmt->bindParam(":attempt_id", $attempt_id);
            $quiz_stmt->execute();
            $quiz_data = $quiz_stmt->fetch(PDO::FETCH_ASSOC);

            if ($quiz_data) {
                $this->updateQuizProgress($attempt['quiz_id'], $attempt['student_id'], $quiz_data['course_id'], false, $score);
            }
        }

        return $result;
    }

    /**
     * Check if answer is correct
     */
    private function isAnswerCorrect($question, $student_answer) {
        $correct_answer = trim($question['correct_answer']);
        $student_answer = trim($student_answer);

        if ($question['question_type'] === 'true_false') {
            return strtolower($student_answer) === strtolower($correct_answer);
        } elseif ($question['question_type'] === 'multiple_choice') {
            // For multiple choice, check both exact match and option letter match
            if ($student_answer === $correct_answer) {
                return true;
            }

            // If correct answer is a letter (A, B, C, D), check against options
            if ($question['options'] && in_array(strtoupper($correct_answer), ['A', 'B', 'C', 'D'])) {
                $options = json_decode($question['options'], true);
                if (is_array($options)) {
                    $letter_index = ord(strtoupper($correct_answer)) - ord('A');
                    if (isset($options[$letter_index])) {
                        return $student_answer === $options[$letter_index];
                    }
                }
            }

            // If student answer is a letter, check against options
            if (in_array(strtoupper($student_answer), ['A', 'B', 'C', 'D']) && $question['options']) {
                $options = json_decode($question['options'], true);
                if (is_array($options)) {
                    $letter_index = ord(strtoupper($student_answer)) - ord('A');
                    if (isset($options[$letter_index])) {
                        return $options[$letter_index] === $correct_answer;
                    }
                }
            }

            return false;
        } else {
            return $student_answer === $correct_answer;
        }
    }

    /**
     * Get quiz by lesson ID
     */
    public function getQuizByLesson($lesson_id, $active_only = true) {
        $where_clause = "WHERE q.lesson_id = :lesson_id";
        if ($active_only) {
            $where_clause .= " AND q.is_active = 1";
        }

        $query = "SELECT q.*, c.title as course_title, c.instructor_id,
                         l.title as lesson_title
                  FROM " . $this->table_name . " q
                  LEFT JOIN courses c ON q.course_id = c.id
                  LEFT JOIN lessons l ON q.lesson_id = l.id
                  " . $where_clause;

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":lesson_id", $lesson_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }

    /**
     * Delete all attempts for a specific student and quiz
     */
    public function deleteStudentAttempts($quiz_id, $student_id) {
        $query = "DELETE FROM quiz_attempts
                  WHERE quiz_id = :quiz_id AND student_id = :student_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":quiz_id", $quiz_id);
        $stmt->bindParam(":student_id", $student_id);

        return $stmt->execute();
    }

    /**
     * Delete all attempts for a quiz (instructor only)
     */
    public function deleteAllQuizAttempts($quiz_id) {
        $query = "DELETE FROM quiz_attempts WHERE quiz_id = :quiz_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":quiz_id", $quiz_id);

        return $stmt->execute();
    }

    /**
     * Delete a specific attempt
     */
    public function deleteAttempt($attempt_id, $student_id = null) {
        $query = "DELETE FROM quiz_attempts WHERE id = :attempt_id";

        // Add student verification if student_id is provided
        if ($student_id) {
            $query .= " AND student_id = :student_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":attempt_id", $attempt_id);

        if ($student_id) {
            $stmt->bindParam(":student_id", $student_id);
        }

        return $stmt->execute();
    }

    /**
     * Get or create quiz progress for a student
     */
    public function getQuizProgress($quiz_id, $student_id) {
        $query = "SELECT * FROM quiz_progress
                  WHERE quiz_id = :quiz_id AND student_id = :student_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":quiz_id", $quiz_id);
        $stmt->bindParam(":student_id", $student_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update quiz progress
     */
    public function updateQuizProgress($quiz_id, $student_id, $course_id, $is_completed = false, $score = null) {
        // Get existing progress
        $existing_progress = $this->getQuizProgress($quiz_id, $student_id);

        // Get student attempts to calculate stats
        $attempts = $this->getStudentAttempts($quiz_id, $student_id);
        $total_attempts = count($attempts);
        $best_score = 0;

        foreach ($attempts as $attempt) {
            if ($attempt['score'] > $best_score) {
                $best_score = $attempt['score'];
            }
        }

        if ($existing_progress) {
            // Update existing progress
            $query = "UPDATE quiz_progress
                      SET is_completed = :is_completed,
                          completion_date = " . ($is_completed ? "CURRENT_TIMESTAMP" : "completion_date") . ",
                          best_score = :best_score,
                          total_attempts = :total_attempts,
                          updated_at = CURRENT_TIMESTAMP
                      WHERE quiz_id = :quiz_id AND student_id = :student_id";
        } else {
            // Create new progress record
            $query = "INSERT INTO quiz_progress
                      (student_id, quiz_id, course_id, is_completed, best_score, total_attempts, completion_date)
                      VALUES (:student_id, :quiz_id, :course_id, :is_completed, :best_score, :total_attempts, " .
                      ($is_completed ? "CURRENT_TIMESTAMP" : "NULL") . ")";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":is_completed", $is_completed, PDO::PARAM_BOOL);
        $stmt->bindParam(":best_score", $best_score);
        $stmt->bindParam(":total_attempts", $total_attempts);
        $stmt->bindParam(":quiz_id", $quiz_id);
        $stmt->bindParam(":student_id", $student_id);

        if (!$existing_progress) {
            $stmt->bindParam(":course_id", $course_id);
        }

        return $stmt->execute();
    }

    /**
     * Mark quiz as completed
     */
    public function markQuizAsCompleted($quiz_id, $student_id) {
        // Get quiz data to get course_id
        $quiz_data = $this->getQuizById($quiz_id);
        if (!$quiz_data) {
            return false;
        }

        return $this->updateQuizProgress($quiz_id, $student_id, $quiz_data['course_id'], true);
    }

    /**
     * Check if quiz is completed by student
     */
    public function isQuizCompleted($quiz_id, $student_id) {
        $progress = $this->getQuizProgress($quiz_id, $student_id);
        return $progress && $progress['is_completed'];
    }
}
?>
