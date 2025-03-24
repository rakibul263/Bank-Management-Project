<?php
require_once 'config.php';

try {
    // First, check if the is_approved column exists
    $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'is_approved'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // Column doesn't exist, create it
        $conn->exec("ALTER TABLE users ADD COLUMN is_approved ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
        echo "Added is_approved column to users table.<br>";
    }
    
    // Update all existing users to 'approved' status if they don't have a status yet
    $conn->exec("UPDATE users SET is_approved = 'approved' WHERE (is_approved IS NULL OR is_approved = '') AND created_at < NOW()");
    
    // Check if admin_notifications table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'admin_notifications'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // Table doesn't exist, create it
        $conn->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            user_id INT,
            is_read BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");
        echo "Created admin_notifications table.<br>";
    }
    
    echo "All existing users have been set to 'approved' status. Database update completed successfully!";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 