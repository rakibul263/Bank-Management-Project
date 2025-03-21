<?php
require_once __DIR__ . '/../config.php';

// Security functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generate_otp() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
}

// Account functions
function generate_account_number() {
    global $conn;
    do {
        $account_number = 'ACC' . date('Ymd') . rand(1000, 9999);
        $stmt = $conn->prepare("SELECT id FROM accounts WHERE account_number = ?");
        $stmt->execute([$account_number]);
    } while ($stmt->rowCount() > 0);
    return $account_number;
}

function get_account_balance($account_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT balance FROM accounts WHERE id = ?");
    $stmt->execute([$account_id]);
    return $stmt->fetchColumn();
}

function update_account_balance($account_id, $amount, $type = 'add') {
    global $conn;
    try {
        $conn->beginTransaction();
        
        $current_balance = get_account_balance($account_id);
        $new_balance = $type === 'add' ? $current_balance + $amount : $current_balance - $amount;
        
        $stmt = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
        $stmt->execute([$new_balance, $account_id]);
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollBack();
        return false;
    }
}

// Transaction functions
function create_transaction($account_id, $type, $amount, $description = '') {
    global $conn;
    try {
        $conn->beginTransaction();
        
        $current_balance = get_account_balance($account_id);
        $new_balance = $type === 'deposit' ? $current_balance + $amount : $current_balance - $amount;
        
        $stmt = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$account_id, $type, $amount, $new_balance, $description]);
        
        $stmt = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
        $stmt->execute([$new_balance, $account_id]);
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollBack();
        return false;
    }
}

// Loan functions
function can_apply_loan($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ? AND status IN ('pending', 'approved')");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() < 2;
}

// Validation functions
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_password($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return strlen($password) >= 8 && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[a-z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

// UI Helper functions
function format_currency($amount) {
    return number_format($amount, 2);
}

function get_status_badge($status) {
    $badges = [
        'active' => 'success',
        'pending' => 'warning',
        'completed' => 'success',
        'failed' => 'danger',
        'frozen' => 'secondary',
        'inactive' => 'secondary'
    ];
    
    $color = $badges[$status] ?? 'primary';
    return "<span class='badge bg-{$color}'>{$status}</span>";
}
?> 