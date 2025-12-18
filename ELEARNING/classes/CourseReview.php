<?php
/**
 * CourseReview Class
 * Handles course reviews and ratings
 */

require_once __DIR__ . '/../config/config.php';

class CourseReview {
    private $conn;
    private $table_name = "course_reviews";

    public $id;
    public $student_id;
    public $course_id;
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
     * Create a new review
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET student_id=:student_id, course_id=:course_id, rating=:rating,
                      review_text=:review_text, is_approved=:is_approved";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->student_id = htmlspecialchars(strip_tags($this->student_id));
        $this->course_id = htmlspecialchars(strip_tags($this->course_id));
        $this->rating = htmlspecialchars(strip_tags($this->rating));
        $this->review_text = htmlspecialchars(strip_tags($this->review_text));
        $this->is_approved = $this->is_approved ?? 1;

        // Bind values
        $stmt->bindParam(":student_id", $this->student_id);
        $stmt->bindParam(":course_id", $this->course_id);
        $stmt->bindParam(":rating", $this->rating);
        $stmt->bindParam(":review_text", $this->review_text);
        $stmt->bindParam(":is_approved", $this->is_approved);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            $this->updateCourseRating();
            return true;
        }

        return false;
    }

    /**
     * Update an existing review
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
            $this->updateCourseRating();
            return true;
        }

        return false;
    }

    /**
     * Get review by student and course
     */
    public function getReviewByStudentAndCourse($student_id, $course_id) {
        $query = "SELECT cr.*, u.first_name, u.last_name, u.profile_image
                  FROM " . $this->table_name . " cr
                  LEFT JOIN users u ON cr.student_id = u.id
                  WHERE cr.student_id = :student_id AND cr.course_id = :course_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":student_id", $student_id);
        $stmt->bindParam(":course_id", $course_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }

    /**
     * Get all reviews for a course
     */
    public function getCourseReviews($course_id, $limit = 10, $offset = 0) {
        $query = "SELECT cr.*, u.first_name, u.last_name, u.profile_image
                  FROM " . $this->table_name . " cr
                  LEFT JOIN users u ON cr.student_id = u.id
                  WHERE cr.course_id = :course_id AND cr.is_approved = 1
                  ORDER BY cr.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":course_id", $course_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get course rating statistics
     */
    public function getCourseRatingStats($course_id) {
        $query = "SELECT
                    AVG(rating) as average_rating,
                    COUNT(*) as total_reviews,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
                  FROM " . $this->table_name . "
                  WHERE course_id = :course_id AND is_approved = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":course_id", $course_id);
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
     * Update course rating in courses table
     */
    private function updateCourseRating() {
        $stats = $this->getCourseRatingStats($this->course_id);

        $query = "UPDATE courses
                  SET rating_average = :rating_average, rating_count = :rating_count
                  WHERE id = :course_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":rating_average", $stats['average_rating']);
        $stmt->bindParam(":rating_count", $stats['total_reviews']);
        $stmt->bindParam(":course_id", $this->course_id);

        return $stmt->execute();
    }

    /**
     * Check if student can review course (must be enrolled)
     */
    public function canStudentReview($student_id, $course_id) {
        $query = "SELECT COUNT(*) as count FROM enrollments
                  WHERE student_id = :student_id AND course_id = :course_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":student_id", $student_id);
        $stmt->bindParam(":course_id", $course_id);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * Delete a review
     */
    public function delete($review_id, $student_id) {
        $query = "DELETE FROM " . $this->table_name . "
                  WHERE id = :id AND student_id = :student_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $review_id);
        $stmt->bindParam(":student_id", $student_id);

        if ($stmt->execute()) {
            $this->updateCourseRating();
            return true;
        }

        return false;
    }
}
?>
