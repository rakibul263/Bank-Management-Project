<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'rakibul263');
define('DB_NAME', 'banking_system');

define('SITE_NAME', 'Daffodil Banking System');
define('SITE_URL', 'http://localhost:8000');

define('HASH_COST', 12); 
define('SESSION_LIFETIME', 3600); 
define('OTP_EXPIRY', 300); 


session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'secure' => false, 
    'httponly' => true,
    'samesite' => 'Lax' 
]);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?> 