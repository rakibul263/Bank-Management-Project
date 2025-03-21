<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Require login
require_login();

$error = '';
$success = '';

// Get user's accounts
$stmt = $conn->prepare("SELECT * FROM accounts WHERE user_id = ? AND status = 'active'");
$stmt->execute([$_SESSION['user_id']]);
$accounts = $stmt->fetchAll();

// Handle transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_id = (int)$_POST['account_id'];
    $amount = (float)$_POST['amount'];
    $type = sanitize_input($_POST['type']);
    $description = sanitize_input($_POST['description']);
    
    // Validate account ownership
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$account_id, $_SESSION['user_id']]);
    $account = $stmt->fetch();
    
    if (!$account) {
        $error = 'Invalid account';
    } elseif ($amount <= 0) {
        $error = 'Amount must be greater than 0';
    } elseif ($type === 'withdrawal' && $amount > $account['balance']) {
        $error = 'Insufficient funds';
    } else {
        try {
            if (create_transaction($account_id, $type, $amount, $description)) {
                $success = ucfirst($type) . ' transaction completed successfully!';
            } else {
                $error = 'Transaction failed. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'Transaction failed. Please try again.';
        }
    }
}

// Get transaction history
$account_filter = isset($_GET['account']) ? (int)$_GET['account'] : null;
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : null;
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : null;
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : null;

$query = "
    SELECT t.*, a.account_number, a.account_type 
    FROM transactions t 
    JOIN accounts a ON t.account_id = a.id 
    WHERE a.user_id = ?
";
$params = [$_SESSION['user_id']];

if ($account_filter) {
    $query .= " AND t.account_id = ?";
    $params[] = $account_filter;
}

if ($type_filter) {
    $query .= " AND t.transaction_type = ?";
    $params[] = $type_filter;
}

if ($date_from) {
    $query .= " AND DATE(t.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(t.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - <?php echo SITE_NAME; ?></title>
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
        .transaction-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .transaction-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="p-3">
                    <h4><?php echo SITE_NAME; ?></h4>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="accounts.php">
                            <i class="bi bi-wallet2"></i> Accounts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="transactions.php">
                            <i class="bi bi-cash"></i> Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transfer.php">
                            <i class="bi bi-arrow-left-right"></i> Transfer
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="loans.php">
                            <i class="bi bi-bank"></i> Loans
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person"></i> Profile
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
                    <h2>Transactions</h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transactionModal">
                        <i class="bi bi-plus-circle"></i> New Transaction
                    </button>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Transaction Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="account" class="form-label">Account</label>
                                <select class="form-select" id="account" name="account">
                                    <option value="">All Accounts</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo $account['id']; ?>" <?php echo $account_filter == $account['id'] ? 'selected' : ''; ?>>
                                            <?php echo $account['account_number']; ?> (<?php echo ucfirst($account['account_type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="type" class="form-label">Transaction Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Types</option>
                                    <option value="deposit" <?php echo $type_filter === 'deposit' ? 'selected' : ''; ?>>Deposit</option>
                                    <option value="withdrawal" <?php echo $type_filter === 'withdrawal' ? 'selected' : ''; ?>>Withdrawal</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="transactions.php" class="btn btn-secondary">Clear Filters</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Transaction History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Transaction History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transactions)): ?>
                            <p class="text-muted">No transactions found</p>
                        <?php else: ?>
                            <?php foreach ($transactions as $transaction): ?>
                            <div class="transaction-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0"><?php echo ucfirst($transaction['transaction_type']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo $transaction['account_number']; ?> (<?php echo ucfirst($transaction['account_type']); ?>)
                                        </small>
                                        <?php if ($transaction['description']): ?>
                                            <br>
                                            <small class="text-muted"><?php echo $transaction['description']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="<?php echo $transaction['transaction_type'] === 'deposit' ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $transaction['transaction_type'] === 'deposit' ? '+' : '-'; ?>
                                            $<?php echo format_currency($transaction['amount']); ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transaction Modal -->
    <div class="modal fade" id="transactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="account_id" class="form-label">Account</label>
                            <select class="form-select" id="account_id" name="account_id" required>
                                <option value="">Select account</option>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?php echo $account['id']; ?>">
                                        <?php echo $account['account_number']; ?> (<?php echo ucfirst($account['account_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="type" class="form-label">Transaction Type</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="">Select type</option>
                                <option value="deposit">Deposit</option>
                                <option value="withdrawal">Withdrawal</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Process Transaction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 