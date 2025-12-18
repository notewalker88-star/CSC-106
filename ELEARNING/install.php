<?php
require_once 'config/database.php';

echo "Starting database installation...\n";

try {
    // Create database connection without specifying database name
    $database = new Database();
    
    // Create database if it doesn't exist
    if ($database->createDatabase()) {
        echo "Database 'elearning_system' created or already exists.\n";
    } else {
        echo "Failed to create database.\n";
        exit(1);
    }
    
    // Connect to the database
    $conn = $database->getConnection();
    
    // Read the SQL file
    $sql = file_get_contents('database.sql');
    
    // Split the SQL file into individual statements
    $statements = explode(';', $sql);
    
    $success = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $conn->exec($statement);
                $success++;
            } catch (PDOException $e) {
                // Ignore errors for CREATE DATABASE and USE statements
                if (strpos($statement, 'CREATE DATABASE') === false && 
                    strpos($statement, 'USE ') === false) {
                    echo "Error executing statement: " . $e->getMessage() . "\n";
                    echo "Statement: " . substr($statement, 0, 100) . "...\n";
                    $errors++;
                }
            }
        }
    }
    
    echo "Database installation completed.\n";
    echo "Successful statements: $success\n";
    echo "Errors: $errors\n";
    
    if ($errors === 0) {
        echo "Installation successful! You can now access your e-learning platform.\n";
    } else {
        echo "Installation completed with some errors. Please check your database.\n";
    }
    
} catch (Exception $e) {
    echo "Installation failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>