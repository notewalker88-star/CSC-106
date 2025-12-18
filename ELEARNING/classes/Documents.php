<?php
require_once __DIR__ . '/../config/config.php';

class Documents {
    private $conn;
    private $table_name = 'documents';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function uploadDocument($title, $description, $file_path, $file_type, $file_size, $category, $uploaded_by) {
        $query = "INSERT INTO {$this->table_name} (title, description, file_path, file_type, file_size, category, uploaded_by) VALUES (:title, :description, :file_path, :file_type, :file_size, :category, :uploaded_by)";
        $stmt = $this->conn->prepare($query);

        $title = htmlspecialchars(strip_tags($title));
        $description = htmlspecialchars(strip_tags($description));
        $file_path = trim($file_path);
        $file_type = strtolower(trim($file_type));
        $file_size = (int)$file_size;
        $category = htmlspecialchars(strip_tags($category));
        $uploaded_by = (int)$uploaded_by;

        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':file_path', $file_path);
        $stmt->bindParam(':file_type', $file_type);
        $stmt->bindParam(':file_size', $file_size, PDO::PARAM_INT);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':uploaded_by', $uploaded_by, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return (int)$this->conn->lastInsertId();
        }
        return false;
    }

    public function getAllDocuments($category = null) {
        if ($category !== null && $category !== '') {
            $query = "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) AS uploaded_by_name FROM {$this->table_name} d LEFT JOIN users u ON d.uploaded_by = u.id WHERE d.category = :category ORDER BY d.uploaded_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':category', $category);
            $stmt->execute();
        } else {
            $query = "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) AS uploaded_by_name FROM {$this->table_name} d LEFT JOIN users u ON d.uploaded_by = u.id ORDER BY d.uploaded_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDocumentById($document_id) {
        $query = "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) AS uploaded_by_name FROM {$this->table_name} d LEFT JOIN users u ON d.uploaded_by = u.id WHERE d.document_id = :document_id";
        $stmt = $this->conn->prepare($query);
        $document_id = (int)$document_id;
        $stmt->bindParam(':document_id', $document_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteDocument($document_id) {
        $doc = $this->getDocumentById($document_id);
        if ($doc && !empty($doc['file_path'])) {
            $file_rel = ltrim($doc['file_path'], '/');
            $base = rtrim(UPLOAD_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $file_path = $base . $file_rel;
            if (strpos(realpath($file_path) ?: '', realpath($base) ?: '') === 0 && file_exists($file_path)) {
                @unlink($file_path);
            }
        }

        $query = "DELETE FROM {$this->table_name} WHERE document_id = :document_id";
        $stmt = $this->conn->prepare($query);
        $document_id = (int)$document_id;
        $stmt->bindParam(':document_id', $document_id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
?>
