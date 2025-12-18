<?php
/**
 * InstructorReview Class
 * Handles instructor reviews and ratings
 */

require_once __DIR__ . '/../config/config.php';

class InstructorReview {
    private $conn;
    private $table_name = "instructor_reviews";

    public $id;
    public $student_id;
    public $instructor_id;
    public $rating;
    public $review_text;
    public $is_approved;
    public $created_at;
    public $updated_at;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Create a new instructor review
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET student_id=:student_id, instructor_id=:instructor_id, rating=:rating,
                      review_text=:review_text, is_approved=:is_approved";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->student_id = htmlspecialchars(strip_tags($this->student_id));
        $this->instructor_id = htmlspecialchars(strip_tags($this->instructor_id));
        $this->rating = htmlspecialchars(strip_tags($this->rating));
        $this->review_text = htmlspecialchars(strip_tags($this->review_text));
        $this->is_approved = $this->is_approved ?? 1;

        // Bind values
        $stmt->bindParam(":student_id", $this->student_id);
        $stmt->bindParam(":instructor_id", $this->instructor_id);
        $stmt->bindParam(":rating", $this->rating);
        $stmt->bindParam(":review_text", $this->review_text);
        $stmt->bindParam(":is_approved", $this->is_approved);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            $this->updateInstructorRating();
            return true;
        }

        return false;
    }

    /**
     * Update an existing instructor review
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET rating=:rating, review_text=:review_text, updated_at=CURRENT_TIMESTAMP
                  WHERE id=:id AND student_id=:student_id";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->rating = htmlspecialchars(strip_tags($this->rating));
        $this->review_text = htmlspecialchars(strip_tags($this->review_text));
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->student_id = htmlspecialchars(strip_tags($this->student_id));

        // Bind values
        $stmt->bindParam(":rating", $this->rating);
        $stmt->bindParam(":review_text", $this->review_text);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":student_id", $this->student_id);

        if ($stmt->execute()) {
            $this->updateInstructorRating();
            return true;
        }

        return false;
    }

    /**
     * Delete an instructor review
     */
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id=:id AND student_id=:student_id";

        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->student_id = htmlspecialchars(strip_tags($this->student_id));

        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":student_id", $this->student_id);

        if ($stmt->execute()) {
            $this->updateInstructorRating();
            return true;
        }

        return false;
    }

    /**
     * Get review by student and instructor
     */
    public function getReviewByStudentAndInstructor($student_id, $instructor_id) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE student_id = :student_id AND instructor_id = :instructor_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":student_id", $student_id);
        $stmt->bindParam(":instructor_id", $instructor_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all reviews for an instructor
     */
    public function getInstructorReviews($instructor_id, $limit = 10, $offset = 0) {
        $query = "SELECT ir.*, u.first_name, u.last_name, u.profile_image
                  FROM " . $this->table_name . " ir
                  LEFT JOIN users u ON ir.student_id = u.id
                  WHERE ir.instructor_id = :instructor_id AND ir.is_approved = 1
                  ORDER BY ir.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":instructor_id", $instructor_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get instructor rating statistics
     */
    public function getInstructorRatingStats($instructor_id) {
        $query = "SELECT
                    AVG(rating) as average_rating,
                    COUNT(*) as total_reviews,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
                  FROM " . $this->table_name . "
                  WHERE instructor_id = :instructor_id AND is_approved = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":instructor_id", $instructor_id);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['total_reviews'] > 0) {
            return [
                'average_rating' => round($result['average_rating'], 1),
                'total_reviews' => $result['total_reviews'],
                'five_star' => $result['five_star'],
                'four_star' => $result['four_star'],
                'three_star' => $result['three_star'],
                'two_star' => $result['two_star'],
                'one_star' => $result['one_star']
            ];
        }

        return [
            'average_rating' => 0,
            'total_reviews' => 0,
            'five_star' => 0,
            'four_star' => 0,
            'three_star' => 0,
            'two_star' => 0,
            'one_star' => 0
        ];
    }

    /**
     * Check if student can review instructor
     */
    public function canStudentReview($student_id, $instructor_id) {
        // Check if student is enrolled in any course by this instructor
        // and has at least 50% progress in at least one course
        $query = "SELECT COUNT(*) as can_review
                  FROM enrollments e
                  JOIN courses c ON e.course_id = c.id
                  WHERE e.student_id = :student_id
                  AND c.instructor_id = :instructor_id
                  AND e.progress_percentage >= 50";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":student_id", $student_id);
        $stmt->bindParam(":instructor_id", $instructor_id);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['can_review'] > 0;
    }

    /**
     * Update instructor's overall rating in users table
     */
    private function updateInstructorRating() {
        $stats = $this->getInstructorRatingStats($this->instructor_id);

        $query = "UPDATE users
                  SET instructor_rating_average = :rating_average,
                      instructor_rating_count = :rating_count
                  WHERE id = :instructor_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":rating_average", $stats['average_rating']);
        $stmt->bindParam(":rating_count", $stats['total_reviews']);
        $stmt->bindParam(":instructor_id", $this->instructor_id);

        return $stmt->execute();
    }

    /**
     * Get instructors that a student can review
     */
    public function getReviewableInstructors($student_id) {
        $query = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.profile_image,
                         u.instructor_rating_average, u.instructor_rating_count
                  FROM users u
                  JOIN courses c ON u.id = c.instructor_id
                  JOIN enrollments e ON c.id = e.course_id
                  WHERE e.student_id = :student_id
                  AND e.progress_percentage >= 50
                  AND u.role = 'instructor'
                  ORDER BY u.first_name, u.last_name";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":student_id", $student_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
