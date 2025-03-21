<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'rakibul263'); // Empty password for local development
define('DB_NAME', 'banking_system');

// Application configuration
define('SITE_NAME', 'Secure Banking System');
define('SITE_URL', 'http://localhost:8000');

// Security configuration
define('HASH_COST', 12); // For password_hash()
define('SESSION_LIFETIME', 3600); // 1 hour
define('OTP_EXPIRY', 300); // 5 minutes

// Set session cookie parameters before starting the session
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'secure' => false, // Set to true if using HTTPS
    'httponly' => true,
    'samesite' => 'Lax' // Changed from Strict to Lax for better compatibility
]);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?> 