<?php
require_once '../config.php';

// Make sure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all session values
$_SESSION = array();

// Get session parameters 
$params = session_get_cookie_params();

// Delete the actual cookie
setcookie(
    session_name(),
    '',
    time() - 42000,
    $params["path"],
    $params["domain"],
    $params["secure"],
    $params["httponly"]
);

// Destroy session
session_destroy();

// Clear any other cookies that might be set
foreach ($_COOKIE as $name => $value) {
    setcookie($name, '', time() - 3600, '/');
}

// Clear output buffer
if (ob_get_length()) {
    ob_end_clean();
}

// Exit with redirect
header('Location: login.php');
exit();
?> 