<?php
/**
 * Lesson Class
 * Handles lesson management
 */

require_once __DIR__ . '/../config/config.php';

class Lesson {
    private $conn;
    private $table_name = "lessons";

    public $id;
    public $course_id;
    public $title;
    public $description;
    public $content;
    public $video_url;
    public $video_duration;
    public $lesson_order;
    public $is_preview;
    public $attachments;
    public $quiz_id;
    public $is_published;
    public $created_at;
    public $updated_at;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Create a new lesson
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET course_id=:course_id, title=:title, description=:description,
                      content=:content, video_url=:video_url, video_duration=:video_duration,
                      lesson_order=:lesson_order, is_preview=:is_preview,
                      attachments=:attachments, is_published=:is_published";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->video_url = htmlspecialchars(strip_tags($this->video_url));

        // Bind values
        $stmt->bindParam(":course_id", $this->course_id);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":content", $this->content);
        $stmt->bindParam(":video_url", $this->video_url);
        $stmt->bindParam(":video_duration", $this->video_duration);
        $stmt->bindParam(":lesson_order", $this->lesson_order);
        $stmt->bindParam(":is_preview", $this->is_preview);
        $stmt->bindParam(":attachments", $this->attachments);
        $stmt->bindParam(":is_published", $this->is_published);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Get lesson by ID
     */
    public function getLessonById($id) {
        $query = "SELECT l.*, c.title as course_title, c.instructor_id
                  FROM " . $this->table_name . " l
                  LEFT JOIN courses c ON l.course_id = c.id
                  WHERE l.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->id = $row['id'];
            $this->course_id = $row['course_id'];
            $this->title = $row['title'];
            $this->description = $row['description'];
            $this->content = $row['content'];
            $this->video_url = $row['video_url'];
            $this->video_duration = $row['video_duration'];
            $this->lesson_order = $row['lesson_order'];
            $this->is_preview = $row['is_preview'];
            $this->attachments = $row['attachments'];
            $this->quiz_id = $row['quiz_id'];
            $this->is_published = $row['is_published'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];

            return $row;
        }

        return false;
    }

    /**
     * Update lesson
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET title=:title, description=:description, content=:content,
                      video_url=:video_url, video_duration=:video_duration,
                      lesson_order=:lesson_order, is_preview=:is_preview,
                      attachments=:attachments, is_published=:is_published,
                      updated_at=CURRENT_TIMESTAMP
                  WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->video_url = htmlspecialchars(strip_tags($this->video_url));

        // Bind values
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":content", $this->content);
        $stmt->bindParam(":video_url", $this->video_url);
        $stmt->bindParam(":video_duration", $this->video_duration);
        $stmt->bindParam(":lesson_order", $this->lesson_order);
        $stmt->bindParam(":is_preview", $this->is_preview);
        $stmt->bindParam(":attachments", $this->attachments);
        $stmt->bindParam(":is_published", $this->is_published);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * Delete lesson
     */
    public function delete($id) {
        // First get lesson data to delete associated files
        $lesson_data = $this->getLessonById($id);

        if ($lesson_data) {
            // Delete video file if exists
            if ($lesson_data['video_url'] && file_exists(UPLOAD_PATH . 'lessons/' . $lesson_data['video_url'])) {
                unlink(UPLOAD_PATH . 'lessons/' . $lesson_data['video_url']);
            }

            // Delete attachments if exist
            if ($lesson_data['attachments']) {
                $attachments = json_decode($lesson_data['attachments'], true);
                if (is_array($attachments)) {
                    foreach ($attachments as $attachment) {
                        if (isset($attachment['filename']) && file_exists(UPLOAD_PATH . 'lessons/' . $attachment['filename'])) {
                            unlink(UPLOAD_PATH . 'lessons/' . $attachment['filename']);
                        }
                    }
                }
            }
        }

        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    /**
     * Get lessons by course ID
     */
    public function getLessonsByCourse($course_id, $published_only = false) {
        $where_clause = "WHERE l.course_id = :course_id";
        if ($published_only) {
            $where_clause .= " AND l.is_published = 1";
        }

        $query = "SELECT l.*, c.title as course_title
                  FROM " . $this->table_name . " l
                  LEFT JOIN courses c ON l.course_id = c.id
                  " . $where_clause . "
                  ORDER BY l.lesson_order ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":course_id", $course_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get next lesson order for a course
     */
    public function getNextLessonOrder($course_id) {
        $query = "SELECT MAX(lesson_order) as max_order FROM " . $this->table_name . " WHERE course_id = :course_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":course_id", $course_id);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_order'] ?? 0) + 1;
    }

    /**
     * Update lesson order
     */
    public function updateLessonOrder($lesson_id, $new_order) {
        $query = "UPDATE " . $this->table_name . " SET lesson_order = :order WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":order", $new_order);
        $stmt->bindParam(":id", $lesson_id);

        return $stmt->execute();
    }

    /**
     * Toggle lesson published status
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
     * Remove specific attachment from lesson
     */
    public function removeAttachment($lesson_id, $attachment_index) {
        // Get current lesson data
        $lesson_data = $this->getLessonById($lesson_id);

        if (!$lesson_data || !$lesson_data['attachments']) {
            return false;
        }

        $attachments = json_decode($lesson_data['attachments'], true);
        if (!is_array($attachments) || !isset($attachments[$attachment_index])) {
            return false;
        }

        // Get attachment to delete
        $attachment_to_delete = $attachments[$attachment_index];

        // Delete physical file
        $file_path = UPLOAD_PATH . 'lessons/' . $attachment_to_delete['filename'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Remove from array
        unset($attachments[$attachment_index]);

        // Reindex array to maintain proper indices
        $attachments = array_values($attachments);

        // Update lesson in database
        $new_attachments_json = !empty($attachments) ? json_encode($attachments) : null;

        $query = "UPDATE " . $this->table_name . "
                  SET attachments = :attachments, updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":attachments", $new_attachments_json);
        $stmt->bindParam(":id", $lesson_id);

        return $stmt->execute();
    }

    /**
     * Handle file upload for lessons
     */
    public function uploadFile($file, $type = 'video') {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return false;
        }

        $upload_dir = UPLOAD_PATH . 'lessons/';
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Validate file type
        $allowed_types = [];
        if ($type === 'video') {
            $allowed_types = ALLOWED_VIDEO_TYPES;
        } else {
            $allowed_types = array_merge(ALLOWED_DOCUMENT_TYPES, ALLOWED_IMAGE_TYPES);
        }

        if (!in_array($file_extension, $allowed_types)) {
            return false;
        }

        // Generate unique filename
        $filename = uniqid() . '_' . time() . '.' . $file_extension;
        $target_path = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            return $filename;
        }

        return false;
    }

    /**
     * Sanitize lesson content for safe display
     * Allows basic HTML formatting while removing dangerous elements
     */
    public static function sanitizeContent($content) {
        if (empty($content)) {
            return '';
        }

        // Allow common formatting tags
        $allowed_tags = '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><a><img><div><span>';
        $content = strip_tags($content, $allowed_tags);

        // Remove potentially dangerous attributes but keep basic ones
        $content = preg_replace('/(<[^>]+) (on\w+|javascript:|vbscript:|data:)[^>]*>/i', '$1>', $content);

        // Allow basic attributes for links and images
        $content = preg_replace('/(<a[^>]*) href="([^"]*)"([^>]*>)/i', '$1 href="$2" target="_blank" rel="noopener noreferrer"$3', $content);
        $content = preg_replace('/(<img[^>]*) src="([^"]*)"([^>]*>)/i', '$1 src="$2" style="max-width: 100%; height: auto;"$3', $content);

        return $content;
    }
}
?>
