-- Admin Database Tables

-- Create admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    role ENUM('super_admin', 'admin', 'manager') DEFAULT 'admin',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create admin_notifications table
CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    type ENUM('user_registration', 'loan_request', 'withdrawal_request', 'system_alert') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_user_id INT,
    is_read BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id),
    FOREIGN KEY (related_user_id) REFERENCES users(id)
);

-- Create admin_activities table
CREATE TABLE IF NOT EXISTS admin_activities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id)
);

-- Create admin_settings table
CREATE TABLE IF NOT EXISTS admin_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES admins(id)
);

-- Insert default super admin
INSERT INTO admins (username, password, full_name, email, phone, role) 
VALUES (
    'admin',
    '$2y$12$YourHashedPasswordHere', -- Replace with actual hashed password
    'System Administrator',
    'admin@bank.com',
    '1234567890',
    'super_admin'
);

-- Create indexes for better performance
CREATE INDEX idx_admin_username ON admins(username);
CREATE INDEX idx_admin_email ON admins(email);
CREATE INDEX idx_admin_status ON admins(status);
CREATE INDEX idx_notification_type ON admin_notifications(type);
CREATE INDEX idx_notification_read ON admin_notifications(is_read);
CREATE INDEX idx_activity_admin ON admin_activities(admin_id);
CREATE INDEX idx_activity_type ON admin_activities(activity_type); 