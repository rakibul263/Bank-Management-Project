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
        
        // First update the account balance
        $stmt = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
        $stmt->execute([$new_balance, $account_id]);
        
        // Then create the transaction record
        $stmt = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$account_id, $type, $amount, $new_balance, $description]);
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollBack();
        return false;
    }
}

function process_transfer($from_account_id, $to_account_number, $amount, $description = '') {
    global $conn;
    try {
        $conn->beginTransaction();
        
        // Validate amount
        if ($amount <= 0) {
            error_log("Invalid amount: {$amount}");
            $conn->rollBack();
            return false;
        }
        
        // Get source account details
        $stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ? AND status = 'active'");
        $stmt->execute([$from_account_id]);
        $from_account = $stmt->fetch();
        
        if (!$from_account) {
            error_log("Source account not found or inactive: {$from_account_id}");
            $conn->rollBack();
            return false;
        }
        
        // Get destination account details
        $stmt = $conn->prepare("SELECT * FROM accounts WHERE account_number = ? AND status = 'active'");
        $stmt->execute([$to_account_number]);
        $to_account = $stmt->fetch();
        
        if (!$to_account) {
            error_log("Destination account not found or inactive: {$to_account_number}");
            $conn->rollBack();
            return false;
        }
        
        // Check if source account has sufficient funds
        if ($from_account['balance'] < $amount) {
            error_log("Insufficient funds in account {$from_account_id}. Balance: {$from_account['balance']}, Amount: {$amount}");
            $conn->rollBack();
            return false;
        }
        
        // Update source account balance
        $new_from_balance = $from_account['balance'] - $amount;
        $stmt = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
        if (!$stmt->execute([$new_from_balance, $from_account_id])) {
            error_log("Failed to update source account balance");
            $conn->rollBack();
            return false;
        }
        
        // Update destination account balance
        $new_to_balance = $to_account['balance'] + $amount;
        $stmt = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
        if (!$stmt->execute([$new_to_balance, $to_account['id']])) {
            error_log("Failed to update destination account balance");
            $conn->rollBack();
            return false;
        }
        
        // Create transfer record
        $stmt = $conn->prepare("INSERT INTO transfers (from_account_id, to_account_id, amount, status) 
                               VALUES (?, ?, ?, 'completed')");
        if (!$stmt->execute([$from_account_id, $to_account['id'], $amount])) {
            error_log("Failed to create transfer record");
            $conn->rollBack();
            return false;
        }
        $transfer_id = $conn->lastInsertId();
        
        // Create transaction record for source account
        $stmt = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description, status) 
                               VALUES (?, 'transfer_out', ?, ?, ?, 'completed')");
        if (!$stmt->execute([
            $from_account_id, 
            $amount, 
            $new_from_balance, 
            "Transfer #{$transfer_id} to {$to_account_number}: {$description}"
        ])) {
            error_log("Failed to create source transaction record");
            $conn->rollBack();
            return false;
        }
        
        // Create transaction record for destination account
        $stmt = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description, status) 
                               VALUES (?, 'transfer_in', ?, ?, ?, 'completed')");
        if (!$stmt->execute([
            $to_account['id'], 
            $amount, 
            $new_to_balance, 
            "Transfer #{$transfer_id} from {$from_account['account_number']}: {$description}"
        ])) {
            error_log("Failed to create destination transaction record");
            $conn->rollBack();
            return false;
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Transfer failed: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
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
        
        // Create a withdrawal request - but DON'T deduct the money yet
        // Money will be deducted only when admin approves
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
        $stmt = $conn->prepare("SELECT * FROM withdrawal_requests WHERE id = ? AND status = 'pending'");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            $conn->rollBack();
            return false;
        }
        
        // Update the request status
        $stmt = $conn->prepare("UPDATE withdrawal_requests SET status = ?, processed_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $request_id]);
        
        // If approved, create a transaction and update balance
        if ($status === 'approved') {
            $account_id = $request['account_id'];
            $amount = $request['amount'];
            $description = 'Withdrawal approved by admin ID: ' . $admin_id . ' (Request ID: ' . $request_id . ')';
            
            // Get current balance
            $current_balance = get_account_balance($account_id);
            $new_balance = $current_balance - $amount;
            
            // Create transaction record
            $stmt = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description) 
                                   VALUES (?, 'withdrawal', ?, ?, ?)");
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
    return '৳' . number_format((float)$amount, 2);
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

// Admin access control functions
function check_admin_login() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit();
    }
}

function get_admin_role($admin_id) {
    global $conn;
    
    // Check if role column exists in admins table
    $role_column_exists = false;
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM admins LIKE 'role'");
        $stmt->execute();
        $role_column_exists = $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        // Column doesn't exist or there was an error
    }
    
    if (!$role_column_exists) {
        // If no role column, check if this is the superadmin
        $stmt = $conn->prepare("SELECT username FROM admins WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch();
        
        return ($admin && $admin['username'] === 'admin') ? 'super_admin' : 'user_manager';
    }
    
    // Get the admin's role
    $stmt = $conn->prepare("SELECT role, username FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        return null;
    }
    
    // Always ensure the main admin has super_admin role
    if ($admin['username'] === 'admin') {
        return 'super_admin';
    }
    
    return $admin['role'] ?? 'user_manager';
}

function admin_has_permission($page, $admin_id) {
    global $conn;
    
    // Define admin role permissions
    $admin_permissions = [
        'super_admin' => ['all'],
        'user_manager' => ['index.php', 'users.php', 'pending_users.php', 'profile.php'],
        'account_manager' => ['index.php', 'accounts.php', 'withdrawals.php', 'profile.php'],
        'loan_manager' => ['index.php', 'loans.php', 'profile.php'],
        'transaction_manager' => ['index.php', 'transactions.php', 'profile.php']
    ];
    
    // Common pages that all admins can access
    $common_pages = ['index.php', 'profile.php', 'logout.php'];
    if (in_array($page, $common_pages)) {
        return true;
    }
    
    $admin_role = get_admin_role($admin_id);
    
    // Super admin can access everything
    if ($admin_role === 'super_admin' || in_array('all', $admin_permissions[$admin_role])) {
        return true;
    }
    
    // Check if the admin has permission for this page
    return in_array($page, $admin_permissions[$admin_role]);
}

function require_admin_permission($page) {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit();
    }
    
    if (!admin_has_permission($page, $_SESSION['admin_id'])) {
        header('Location: index.php?error=permission');
        exit();
    }
}
?> 