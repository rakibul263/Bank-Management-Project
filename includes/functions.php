<?php
require_once __DIR__ . '/../config.php';

// Security functions
function sanitize_input($data) {
    // Handle null values
    if ($data === null) {
        return '';
    }
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
        $new_balance = $current_balance;
        
        // Determine how to adjust the balance based on transaction type
        if ($type === 'deposit' || $type === 'loan') {
            $new_balance = $current_balance + $amount;
        } else if ($type === 'withdrawal' || $type === 'transfer_out') {
            $new_balance = $current_balance - $amount;
        }
        
        $stmt = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$account_id, $type, $amount, $new_balance, $description]);
        
        // Only update the account balance if not already updated in loan processing
        if ($type !== 'loan') {
            $stmt = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
            $stmt->execute([$new_balance, $account_id]);
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollBack();
        return false;
    }
}

// Withdrawal request functions
function create_withdrawal_request($account_id, $admin_id, $amount, $description = '') {
    global $conn;
    try {
        $conn->beginTransaction();
        
        // Check if the account has sufficient balance
        $current_balance = get_account_balance($account_id);
        if ($current_balance < $amount) {
            $conn->rollBack();
            return false;
        }
        
        // Create a withdrawal request
        $stmt = $conn->prepare("INSERT INTO withdrawal_requests (account_id, admin_id, amount, description) 
                               VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$account_id, $admin_id, $amount, $description]);
        
        $conn->commit();
        return $result;
    } catch (Exception $e) {
        $conn->rollBack();
        return false;
    }
}

function process_withdrawal_request($request_id, $status, $admin_id) {
    global $conn;
    try {
        $conn->beginTransaction();
        
        // Get the withdrawal request
        $stmt = $conn->prepare("SELECT * FROM withdrawal_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            $conn->rollBack();
            return false;
        }
        
        // Update the request status
        $stmt = $conn->prepare("UPDATE withdrawal_requests SET status = ?, processed_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $request_id]);
        
        // If approved, create a transaction and update balance directly (without using create_transaction)
        if ($status === 'approved') {
            $account_id = $request['account_id'];
            $amount = $request['amount'];
            $description = 'Withdrawal approved by admin ID: ' . $admin_id;
            
            // Get current balance
            $current_balance = get_account_balance($account_id);
            $new_balance = $current_balance - $amount;
            
            // Insert transaction record
            $stmt = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description) VALUES (?, 'withdrawal', ?, ?, ?)");
            $stmt->execute([$account_id, $amount, $new_balance, $description]);
            
            // Update account balance
            $stmt = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
            $stmt->execute([$new_balance, $account_id]);
        }
        
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
    // Handle null values
    if ($amount === null || $amount === '') {
        $amount = 0;
    }
    return number_format((float)$amount, 2);
}

function get_status_badge($status) {
    $badges = [
        'active' => 'success',
        'pending' => 'warning',
        'completed' => 'success',
        'failed' => 'danger',
        'frozen' => 'secondary',
        'inactive' => 'secondary',
        'approved' => 'success',
        'rejected' => 'danger'
    ];
    
    $color = $badges[$status] ?? 'primary';
    return "<span class='badge bg-{$color}'>{$status}</span>";
}

// Loan calculation function
function calculate_monthly_payment($loan_amount, $interest_rate, $term_months) {
    // Convert annual interest rate to monthly and decimal form
    $monthly_interest_rate = ($interest_rate / 100) / 12;
    
    // Calculate monthly payment using the formula: P * r * (1+r)^n / ((1+r)^n - 1)
    if ($monthly_interest_rate > 0) {
        $monthly_payment = $loan_amount * $monthly_interest_rate * pow(1 + $monthly_interest_rate, $term_months) / (pow(1 + $monthly_interest_rate, $term_months) - 1);
    } else {
        // If interest rate is 0, simply divide the principal by the term
        $monthly_payment = $loan_amount / $term_months;
    }
    
    return round($monthly_payment, 2);
}
?> 