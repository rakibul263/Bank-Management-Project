<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

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
        // Check if account has balance
        $stmt = $conn->prepare("SELECT balance FROM accounts WHERE id = ?");
        $stmt->execute([$account_id]);
        $balance = $stmt->fetchColumn();
        
        if ($balance > 0) {
            $error = 'Cannot delete account with balance';
        } else {
            // Delete account
            $stmt = $conn->prepare("DELETE FROM accounts WHERE id = ?");
            $stmt->execute([$account_id]);
            
            $success = 'Account deleted successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Failed to delete account. Please try again.';
    }
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
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.75);
        }
        .sidebar .nav-link:hover {
            color: rgba(255,255,255,1);
        }
        .sidebar .nav-link.active {
            color: white;
        }
        .main-content {
            padding: 20px;
        }
        .card {
            border: none;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="p-3">
                    <h4>Admin Panel</h4>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="bi bi-people"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="accounts.php">
                            <i class="bi bi-wallet2"></i> Accounts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="loans.php">
                            <i class="bi bi-bank"></i> Loans
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_admin.php">
                            <i class="bi bi-person-plus"></i> Create Admin
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">
                            <i class="bi bi-cash"></i> Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person-circle"></i> My Profile
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Accounts Management</h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAccountModal">
                        <i class="bi bi-plus-circle"></i> Create Account
                    </button>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($account_details): ?>
                    <!-- Account Details -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Account Details</h5>
                            <a href="accounts.php" class="btn btn-secondary btn-sm">
                                <i class="bi bi-arrow-left"></i> Back to List
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Account Information</h6>
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
                                    <h6>Account Holder</h6>
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
                                <h6>Recent Transactions</h6>
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
                                            <td><?php echo htmlspecialchars($account['full_name']); ?></td>
                                            <td><?php echo ucfirst($account['account_type']); ?></td>
                                            <td>$<?php echo format_currency($account['balance']); ?></td>
                                            <td><?php echo get_status_badge($account['status']); ?></td>
                                            <td>
                                                <a href="?view=<?php echo $account['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($account['balance'] == 0): ?>
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this account?');">
                                                    <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                                    <button type="submit" name="delete_account" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
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
        </div>
    </div>
    
    <!-- Create Account Modal -->
    <div class="modal fade" id="createAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Account</h5>
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
                        <button type="submit" name="create_account" class="btn btn-primary">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 