<?php
require_once 'config/database.php';

echo "Resetting admin password...\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        // Hash the new password
        $newPassword = 'admin123';
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update the admin user's password
        $query = "UPDATE users SET password = :password WHERE email = 'admin@elearning.com'";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':password', $hashedPassword);
        
        if ($stmt->execute()) {
            echo "Admin password successfully reset!\n";
            echo "Username: admin\n";
            echo "New Password: $newPassword\n";
            echo "Please change this password after logging in for security reasons.\n";
        } else {
            echo "Failed to reset password.\n";
        }
    } else {
        echo "Database connection failed.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>