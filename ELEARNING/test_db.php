<?php
require_once 'config/database.php';

echo "Testing database connection...\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "Database connection successful!\n";
        
        // Test if we can query the courses table
        $query = "SELECT COUNT(*) as count FROM courses";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Courses table exists and contains " . $result['count'] . " records.\n";
        
        // Test the specific query that was failing
        $query = "SELECT c.*, cat.name as category_name, u.first_name, u.last_name
                  FROM courses c
                  LEFT JOIN categories cat ON c.category_id = cat.id
                  LEFT JOIN users u ON c.instructor_id = u.id
                  WHERE c.is_published = 1 AND c.is_featured = 1
                  ORDER BY c.rating_average DESC, c.enrollment_count DESC
                  LIMIT 6";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        echo "Featured courses query executed successfully!\n";
        echo "Found " . $stmt->rowCount() . " featured courses.\n";
        
    } else {
        echo "Database connection failed.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>