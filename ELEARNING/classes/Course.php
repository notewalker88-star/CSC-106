<?php
/**
 * Course Class
 * Handles course management
 */

require_once __DIR__ . '/../config/config.php';

class Course {
    private $conn;
    private $table_name = "courses";

    public $id;
    public $title;
    public $description;
    public $short_description;
    public $instructor_id;
    public $category_id;
    public $price;
    public $is_free;
    public $thumbnail;
    public $video_preview;
    public $level;
    public $duration_hours;
    public $language;
    public $requirements;
    public $what_you_learn;
    public $is_published;
    public $is_featured;
    public $enrollment_count;
    public $rating_average;
    public $rating_count;
    public $created_at;
    public $updated_at;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Create a new course
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET title=:title, description=:description, short_description=:short_description,
                      instructor_id=:instructor_id, category_id=:category_id, price=:price,
                      is_free=:is_free, thumbnail=:thumbnail, level=:level, language=:language,
                      requirements=:requirements, what_you_learn=:what_you_learn";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->short_description = htmlspecialchars(strip_tags($this->short_description));
        $this->level = htmlspecialchars(strip_tags($this->level));
        $this->language = htmlspecialchars(strip_tags($this->language));
        $this->requirements = htmlspecialchars(strip_tags($this->requirements));
        $this->what_you_learn = htmlspecialchars(strip_tags($this->what_you_learn));

        // Bind values
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":short_description", $this->short_description);
        $stmt->bindParam(":instructor_id", $this->instructor_id);
        $stmt->bindParam(":category_id", $this->category_id);
        $stmt->bindParam(":price", $this->price);
        $stmt->bindParam(":is_free", $this->is_free);
        $stmt->bindParam(":thumbnail", $this->thumbnail);
        $stmt->bindParam(":level", $this->level);
        $stmt->bindParam(":language", $this->language);
        $stmt->bindParam(":requirements", $this->requirements);
        $stmt->bindParam(":what_you_learn", $this->what_you_learn);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Get course by ID
     */
    public function getCourseById($id) {
        $query = "SELECT c.*, cat.name as category_name, cat.slug as category_slug,
                         u.first_name, u.last_name, u.username as instructor_username,
                         u.profile_image as instructor_image
                  FROM " . $this->table_name . " c
                  LEFT JOIN categories cat ON c.category_id = cat.id
                  LEFT JOIN users u ON c.instructor_id = u.id
                  WHERE c.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->id = $row['id'];
            $this->title = $row['title'];
            $this->description = $row['description'];
            $this->short_description = $row['short_description'];
            $this->instructor_id = $row['instructor_id'];
            $this->category_id = $row['category_id'];
            $this->price = $row['price'];
            $this->is_free = $row['is_free'];
            $this->thumbnail = $row['thumbnail'];
            $this->video_preview = $row['video_preview'];
            $this->level = $row['level'];
            $this->duration_hours = $row['duration_hours'];
            $this->language = $row['language'];
            $this->requirements = $row['requirements'];
            $this->what_you_learn = $row['what_you_learn'];
            $this->is_published = $row['is_published'];
            $this->is_featured = $row['is_featured'];
            $this->enrollment_count = $row['enrollment_count'];
            $this->rating_average = $row['rating_average'];
            $this->rating_count = $row['rating_count'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];

            return $row;
        }

        return false;
    }

    /**
     * Get all courses with pagination and filters
     */
    public function getAllCourses($page = 1, $limit = 12, $filters = []) {
        $offset = ($page - 1) * $limit;

        $where_conditions = [];
        $params = [];

        // Build WHERE clause based on filters
        if (isset($filters['category_id']) && !empty($filters['category_id'])) {
            $where_conditions[] = "c.category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }

        if (isset($filters['level']) && !empty($filters['level'])) {
            $where_conditions[] = "c.level = :level";
            $params[':level'] = $filters['level'];
        }

        if (isset($filters['is_free']) && $filters['is_free'] !== '') {
            $where_conditions[] = "c.is_free = :is_free";
            $params[':is_free'] = $filters['is_free'];
        }

        if (isset($filters['instructor_id']) && !empty($filters['instructor_id'])) {
            $where_conditions[] = "c.instructor_id = :instructor_id";
            $params[':instructor_id'] = $filters['instructor_id'];
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $where_conditions[] = "(c.title LIKE :search OR c.description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // Only show published courses for non-instructors
        if (!isset($filters['show_unpublished']) || !$filters['show_unpublished']) {
            $where_conditions[] = "c.is_published = 1";
        }

        $where_clause = "";
        if (!empty($where_conditions)) {
            $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        }

        // Order by
        $order_by = "ORDER BY c.created_at DESC";
        if (isset($filters['order_by'])) {
            switch ($filters['order_by']) {
                case 'rating':
                    $order_by = "ORDER BY c.rating_average DESC";
                    break;
                case 'enrollment':
                    $order_by = "ORDER BY c.enrollment_count DESC";
                    break;
                case 'price_low':
                    $order_by = "ORDER BY c.price ASC";
                    break;
                case 'price_high':
                    $order_by = "ORDER BY c.price DESC";
                    break;
                case 'newest':
                    $order_by = "ORDER BY c.created_at DESC";
                    break;
                case 'oldest':
                    $order_by = "ORDER BY c.created_at ASC";
                    break;
            }
        }

        $query = "SELECT c.*, cat.name as category_name, cat.slug as category_slug,
                         u.first_name, u.last_name, u.username as instructor_username
                  FROM " . $this->table_name . " c
                  LEFT JOIN categories cat ON c.category_id = cat.id
                  LEFT JOIN users u ON c.instructor_id = u.id
                  " . $where_clause . "
                  " . $order_by . "
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);

        // Bind filter parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total course count with filters
     */
    public function getTotalCourses($filters = []) {
        $where_conditions = [];
        $params = [];

        // Build WHERE clause based on filters (same as getAllCourses)
        if (isset($filters['category_id']) && !empty($filters['category_id'])) {
            $where_conditions[] = "category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }

        if (isset($filters['level']) && !empty($filters['level'])) {
            $where_conditions[] = "level = :level";
            $params[':level'] = $filters['level'];
        }

        if (isset($filters['is_free']) && $filters['is_free'] !== '') {
            $where_conditions[] = "is_free = :is_free";
            $params[':is_free'] = $filters['is_free'];
        }

        if (isset($filters['instructor_id']) && !empty($filters['instructor_id'])) {
            $where_conditions[] = "instructor_id = :instructor_id";
            $params[':instructor_id'] = $filters['instructor_id'];
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $where_conditions[] = "(title LIKE :search OR description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!isset($filters['show_unpublished']) || !$filters['show_unpublished']) {
            $where_conditions[] = "is_published = 1";
        }

        $where_clause = "";
        if (!empty($where_conditions)) {
            $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        }

        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " " . $where_clause;
        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['total'];
    }

    /**
     * Update course
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET title=:title, description=:description, short_description=:short_description,
                      category_id=:category_id, price=:price, is_free=:is_free, thumbnail=:thumbnail,
                      level=:level, language=:language, requirements=:requirements,
                      what_you_learn=:what_you_learn, updated_at=CURRENT_TIMESTAMP
                  WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->short_description = htmlspecialchars(strip_tags($this->short_description));
        $this->level = htmlspecialchars(strip_tags($this->level));
        $this->language = htmlspecialchars(strip_tags($this->language));
        $this->requirements = htmlspecialchars(strip_tags($this->requirements));
        $this->what_you_learn = htmlspecialchars(strip_tags($this->what_you_learn));

        // Bind values
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":short_description", $this->short_description);
        $stmt->bindParam(":category_id", $this->category_id);
        $stmt->bindParam(":price", $this->price);
        $stmt->bindParam(":is_free", $this->is_free);
        $stmt->bindParam(":thumbnail", $this->thumbnail);
        $stmt->bindParam(":level", $this->level);
        $stmt->bindParam(":language", $this->language);
        $stmt->bindParam(":requirements", $this->requirements);
        $stmt->bindParam(":what_you_learn", $this->what_you_learn);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * Delete course
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    /**
     * Toggle course published status
     */
    public function togglePublished($id) {
        $query = "UPDATE " . $this->table_name . "
                  SET is_published = NOT is_published, updated_at=CURRENT_TIMESTAMP
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    /**
     * Toggle course featured status
     */
    public function toggleFeatured($id) {
        $query = "UPDATE " . $this->table_name . "
                  SET is_featured = NOT is_featured, updated_at=CURRENT_TIMESTAMP
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    /**
     * Set course featured status
     */
    public function setFeatured($id, $featured = true) {
        $query = "UPDATE " . $this->table_name . "
                  SET is_featured = :featured, updated_at=CURRENT_TIMESTAMP
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":featured", $featured, PDO::PARAM_BOOL);

        return $stmt->execute();
    }

    /**
     * Get courses by instructor
     */
    public function getCoursesByInstructor($instructor_id, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;

        $query = "SELECT c.*, cat.name as category_name, cat.slug as category_slug
                  FROM " . $this->table_name . " c
                  LEFT JOIN categories cat ON c.category_id = cat.id
                  WHERE c.instructor_id = :instructor_id
                  ORDER BY c.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":instructor_id", $instructor_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total courses by instructor
     */
    public function getTotalCoursesByInstructor($instructor_id) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE instructor_id = :instructor_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":instructor_id", $instructor_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['total'];
    }

    /**
     * Get course statistics
     */
    public function getCourseStats($course_id) {
        try {
            // Get enrollment count
            $query = "SELECT COUNT(*) as enrollment_count FROM enrollments WHERE course_id = :course_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":course_id", $course_id);
            $stmt->execute();
            $enrollment_count = $stmt->fetch(PDO::FETCH_ASSOC)['enrollment_count'];

            // Get completion count
            $query = "SELECT COUNT(*) as completion_count FROM enrollments WHERE course_id = :course_id AND is_completed = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":course_id", $course_id);
            $stmt->execute();
            $completion_count = $stmt->fetch(PDO::FETCH_ASSOC)['completion_count'];

            // Get average rating
            $query = "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM course_reviews WHERE course_id = :course_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":course_id", $course_id);
            $stmt->execute();
            $rating_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get lesson count
            $query = "SELECT COUNT(*) as lesson_count FROM lessons WHERE course_id = :course_id AND is_published = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":course_id", $course_id);
            $stmt->execute();
            $lesson_count = $stmt->fetch(PDO::FETCH_ASSOC)['lesson_count'];

            return [
                'enrollment_count' => $enrollment_count,
                'completion_count' => $completion_count,
                'completion_rate' => $enrollment_count > 0 ? round(($completion_count / $enrollment_count) * 100, 2) : 0,
                'avg_rating' => round($rating_data['avg_rating'] ?: 0, 2),
                'review_count' => $rating_data['review_count'],
                'lesson_count' => $lesson_count
            ];
        } catch (Exception $e) {
            return [
                'enrollment_count' => 0,
                'completion_count' => 0,
                'completion_rate' => 0,
                'avg_rating' => 0,
                'review_count' => 0,
                'lesson_count' => 0
            ];
        }
    }

    /**
     * Get course thumbnail URL
     */
    public function getThumbnailUrl() {
        if ($this->thumbnail && file_exists(UPLOAD_PATH . 'courses/' . $this->thumbnail)) {
            return SITE_URL . '/uploads/courses/' . $this->thumbnail;
        }

        return SITE_URL . '/assets/images/default-course.jpg';
    }

    /**
     * Upload course thumbnail
     */
    public function uploadThumbnail($file) {
        $allowed_types = ALLOWED_IMAGE_TYPES;
        $upload_dir = UPLOAD_PATH . 'courses/';

        $result = uploadFile($file, $upload_dir, $allowed_types, 5 * 1024 * 1024); // 5MB limit for thumbnails

        if ($result['success']) {
            // Delete old thumbnail if exists
            if ($this->thumbnail && file_exists($upload_dir . $this->thumbnail)) {
                unlink($upload_dir . $this->thumbnail);
            }

            $this->thumbnail = $result['filename'];
            return $result;
        }

        return $result;
    }

    /**
     * Get featured courses
     */
    public function getFeaturedCourses($limit = 6) {
        $query = "SELECT c.*, cat.name as category_name, u.first_name, u.last_name
                  FROM " . $this->table_name . " c
                  LEFT JOIN categories cat ON c.category_id = cat.id
                  LEFT JOIN users u ON c.instructor_id = u.id
                  WHERE c.is_published = 1 AND c.is_featured = 1
                  ORDER BY c.rating_average DESC, c.enrollment_count DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get popular courses
     */
    public function getPopularCourses($limit = 6) {
        $query = "SELECT c.*, cat.name as category_name, u.first_name, u.last_name
                  FROM " . $this->table_name . " c
                  LEFT JOIN categories cat ON c.category_id = cat.id
                  LEFT JOIN users u ON c.instructor_id = u.id
                  WHERE c.is_published = 1
                  ORDER BY c.enrollment_count DESC, c.rating_average DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total number of students
     */
    public function getTotalStudents() {
        $query = "SELECT COUNT(*) as total FROM users WHERE role = 'student'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['total'];
    }

    /**
     * Get total number of instructors
     */
    public function getTotalInstructors() {
        $query = "SELECT COUNT(*) as total FROM users WHERE role = 'instructor'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['total'];
    }
}
?>
