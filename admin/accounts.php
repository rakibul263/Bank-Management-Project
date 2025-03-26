<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Get current admin info
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$current_admin = $stmt->fetch();

$error = '';
$success = '';

// Handle account creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_account'])) {
    $user_id = (int)$_POST['user_id'];
    $account_type = sanitize_input($_POST['account_type']);
    $initial_balance = (float)$_POST['initial_balance'];
    
    if ($initial_balance < 0) {
        $error = 'Initial balance cannot be negative';
    } else {
        try {
            // Check if user exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            if (!$stmt->fetch()) {
                $error = 'User not found';
            } else {
                $account_number = generate_account_number();
                
                $stmt = $conn->prepare("INSERT INTO accounts (user_id, account_number, account_type, balance) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $account_number, $account_type, $initial_balance]);
                
                if ($initial_balance > 0) {
                    create_transaction($conn->lastInsertId(), 'deposit', $initial_balance, 'Initial deposit');
                }
                
                $success = 'Account created successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Failed to create account. Please try again.';
        }
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $account_id = (int)$_POST['account_id'];
    
    try {
        // Begin transaction for safety
        $conn->beginTransaction();
        
        // First check if account exists
        $stmt = $conn->prepare("SELECT balance FROM accounts WHERE id = ?");
        $stmt->execute([$account_id]);
        $account = $stmt->fetch();
        
        if (!$account) {
            $error = 'Account not found';
            $conn->rollBack();
        } elseif ($account['balance'] > 0) {
            $error = 'Cannot delete account with balance';
            $conn->rollBack();
        } else {
            // First delete related records from all tables that reference account_id
            
            // Delete from withdrawal_requests
            $stmt = $conn->prepare("DELETE FROM withdrawal_requests WHERE account_id = ?");
            $stmt->execute([$account_id]);
            
            // Delete from loans
            $stmt = $conn->prepare("DELETE FROM loans WHERE account_id = ?");
            $stmt->execute([$account_id]);
            
            // Delete from transfers (both as sender and receiver)
            $stmt = $conn->prepare("DELETE FROM transfers WHERE from_account_id = ? OR to_account_id = ?");
            $stmt->execute([$account_id, $account_id]);
            
            // Delete related transactions
            $stmt = $conn->prepare("DELETE FROM transactions WHERE account_id = ?");
            $stmt->execute([$account_id]);
            
            // Then delete the account
            $stmt = $conn->prepare("DELETE FROM accounts WHERE id = ?");
            $stmt->execute([$account_id]);
            
            if ($stmt->rowCount() > 0) {
                $success = 'Account deleted successfully!';
                $conn->commit();
                // Redirect to refresh the page and prevent form resubmission
                header('Location: accounts.php?success=deleted');
                exit();
            } else {
                $error = 'Failed to delete account';
                $conn->rollBack();
            }
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = 'Failed to delete account. Error: ' . $e->getMessage();
    }
}

// Show success message after redirect
if (isset($_GET['success']) && $_GET['success'] === 'deleted') {
    $success = 'Account deleted successfully!';
}

// Handle deposit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit'])) {
    $account_id = (int)$_POST['account_id'];
    $amount = (float)$_POST['amount'];
    
    if ($amount <= 0) {
        $error = 'Amount must be greater than 0';
    } else {
        try {
            create_transaction($account_id, 'deposit', $amount, 'Admin deposit');
            $success = 'Deposit successful!';
        } catch (PDOException $e) {
            $error = 'Failed to process deposit. Please try again.';
        }
    }
}

// Get account details if viewing specific account
$account_details = null;
if (isset($_GET['view'])) {
    $account_id = (int)$_GET['view'];
    $stmt = $conn->prepare("
        SELECT a.*, u.full_name, u.email 
        FROM accounts a
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ?
    ");
    $stmt->execute([$account_id]);
    $account_details = $stmt->fetch();
    
    if ($account_details) {
        // Get account transactions
        $stmt = $conn->prepare("
            SELECT * FROM transactions 
            WHERE account_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$account_id]);
        $transactions = $stmt->fetchAll();
    }
}

// Get all users for the account creation form
$stmt = $conn->query("SELECT id, username, full_name, email FROM users ORDER BY full_name");
$users = $stmt->fetchAll();

// Get all accounts for the list
$stmt = $conn->query("
    SELECT a.*, u.full_name, u.email 
    FROM accounts a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
");
$accounts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Management - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #2c3e50;
            --light-color: #f8f9fc;
        }
        
        body {
            background-color: var(--light-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Modern Navbar Styles */
        .navbar {
            background: linear-gradient(135deg, var(--dark-color) 0%, #1a252f 100%);
            padding: 1rem 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .navbar-brand {
            color: white !important;
            font-weight: 600;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .navbar-brand i {
            font-size: 1.8rem;
            color: var(--primary-color);
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            padding: 0.5rem 1rem;
            margin: 0 0.2rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-link:hover {
            color: white !important;
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }
        
        .nav-link.active {
            color: white !important;
            background: var(--primary-color);
            box-shadow: 0 4px 15px rgba(78,115,223,0.3);
        }
        
        .nav-link i {
            font-size: 1.2rem;
        }
        
        .navbar-toggler {
            border: none;
            padding: 0.5rem;
            color: white;
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
        }
        
        .main-content {
            margin-top: 80px;
            padding: 2rem;
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            transition: all 0.3s;
            background: white;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background: linear-gradient(45deg, var(--primary-color), #6f42c1);
            color: white;
            padding: 1.25rem 1.5rem;
            border: none;
        }
        
        .card-header h5 {
            color: white;
            font-weight: 600;
            margin: 0;
        }
        
        /* Table Styles */
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: rgba(78,115,223,0.05);
            border-bottom: 2px solid rgba(78,115,223,0.1);
            color: var(--primary-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
            padding: 1rem;
        }
        
        .table td {
            vertical-align: middle;
            color: #5a5c69;
            font-size: 0.9rem;
            padding: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .table tr:hover {
            background: rgba(78,115,223,0.02);
        }
        
        /* Button Styles */
        .btn {
            border-radius: 0.5rem;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), #6f42c1);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(45deg, #6f42c1, var(--primary-color));
        }
        
        /* Form Styles */
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 2px solid #e1e1e1;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78,115,223,0.25);
        }
        
        /* Status Badge Styles */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-badge i {
            font-size: 1rem;
        }
        
        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 0.5rem;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        .alert-danger {
            background: #fff5f5;
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }
        
        .alert-success {
            background: #f0fff4;
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }
        
        .alert-info {
            background: #e8f4f8;
            color: var(--info-color);
            border-left: 4px solid var(--info-color);
        }
        
        /* Avatar Styles */
        .avatar {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(78,115,223,0.1);
            color: var(--primary-color);
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 1rem;
            }
            
            .main-content {
                margin-top: 60px;
                padding: 1rem;
            }
            
            .nav-link {
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Include the navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-bank"></i> Accounts Management</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAccountModal">
                <i class="bi bi-plus-circle"></i> Create Account
            </button>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($account_details): ?>
            <!-- Account Details -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-bank"></i> Account Details</h5>
                    <a href="accounts.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3"><i class="bi bi-info-circle"></i> Account Information</h6>
                            <table class="table">
                                <tr>
                                    <th>Account Number:</th>
                                    <td><?php echo $account_details['account_number']; ?></td>
                                </tr>
                                <tr>
                                    <th>Account Type:</th>
                                    <td><?php echo ucfirst($account_details['account_type']); ?></td>
                                </tr>
                                <tr>
                                    <th>Balance:</th>
                                    <td>$<?php echo format_currency($account_details['balance']); ?></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td><?php echo get_status_badge($account_details['status']); ?></td>
                                </tr>
                                <tr>
                                    <th>Created:</th>
                                    <td><?php echo date('M d, Y', strtotime($account_details['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="mb-3"><i class="bi bi-person"></i> Account Holder</h6>
                            <table class="table">
                                <tr>
                                    <th>Name:</th>
                                    <td><?php echo htmlspecialchars($account_details['full_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($account_details['email']); ?></td>
                                </tr>
                            </table>
                            
                            <!-- Deposit Form -->
                            <form method="POST" action="" class="mt-4">
                                <input type="hidden" name="account_id" value="<?php echo $account_details['id']; ?>">
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Deposit Amount</label>
                                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" required>
                                </div>
                                <button type="submit" name="deposit" class="btn btn-success">
                                    <i class="bi bi-cash-plus"></i> Deposit
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6 class="mb-3"><i class="bi bi-clock-history"></i> Recent Transactions</h6>
                        <?php if (empty($transactions)): ?>
                            <p class="text-muted">No transactions found</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Balance After</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></td>
                                            <td><?php echo ucfirst($transaction['transaction_type']); ?></td>
                                            <td>$<?php echo format_currency($transaction['amount']); ?></td>
                                            <td>$<?php echo format_currency($transaction['balance_after']); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Accounts List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-list"></i> Accounts List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Account Number</th>
                                    <th>Holder</th>
                                    <th>Type</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $account): ?>
                                <tr>
                                    <td><?php echo $account['account_number']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-light rounded-circle p-2 me-2">
                                                <i class="bi bi-person text-primary"></i>
                                            </div>
                                            <?php echo htmlspecialchars($account['full_name']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo ucfirst($account['account_type']); ?></td>
                                    <td>$<?php echo format_currency($account['balance']); ?></td>
                                    <td><?php echo get_status_badge($account['status']); ?></td>
                                    <td>
                                        <a href="?view=<?php echo $account['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <?php if ($account['balance'] == 0): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                            <button type="submit" name="delete_account" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this account? This action cannot be undone.');">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Create Account Modal -->
    <div class="modal fade" id="createAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Create New Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Account Holder</label>
                            <select class="form-select" id="user_id" name="user_id" required>
                                <option value="">Select user</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="account_type" class="form-label">Account Type</label>
                            <select class="form-select" id="account_type" name="account_type" required>
                                <option value="">Select type</option>
                                <option value="savings">Savings</option>
                                <option value="current">Current</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="initial_balance" class="form-label">Initial Balance</label>
                            <input type="number" class="form-control" id="initial_balance" name="initial_balance" step="0.01" min="0" value="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_account" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Create Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 