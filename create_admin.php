<?php
// Include configuration file
require_once 'config.php';

// Create admins table if it doesn't exist
try {
    $sql = "DROP TABLE IF EXISTS admins";
    $conn->exec($sql);
    
    $sql = "CREATE TABLE admins (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    
    // Create a default admin user
    $username = 'admin';
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    $sql = "INSERT INTO admins (username, password) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$username, $hashed_password]);
    
    echo "Admin table created successfully!<br>";
    echo "Default admin credentials:<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 