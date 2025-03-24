<?php
require_once 'config.php';

try {
    // Add is_approved column to users table
    $conn->exec("ALTER TABLE users ADD COLUMN is_approved ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
    
    // Create admin_notifications table
    $conn->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        user_id INT,
        is_read BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    echo "Database updated successfully!";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 