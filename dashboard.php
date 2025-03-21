<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Require login
require_login();

// Get user information
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user's accounts
$stmt = $conn->prepare("SELECT * FROM accounts WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$accounts = $stmt->fetchAll();

// Get recent transactions
$stmt = $conn->prepare("
    SELECT t.*, a.account_number 
    FROM transactions t 
    JOIN accounts a ON t.account_id = a.id 
    WHERE a.user_id = ? 
    ORDER BY t.created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_transactions = $stmt->fetchAll();

// Get active loans
$stmt = $conn->prepare("
    SELECT * FROM loans 
    WHERE user_id = ? AND status IN ('pending', 'approved')
");
$stmt->execute([$_SESSION['user_id']]);
$active_loans = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
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
        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
        }
        .account-balance {
            font-size: 24px;
            font-weight: bold;
            color: #0d6efd;
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
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="accounts.php">
                            <i class="bi bi-wallet2"></i> Accounts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">
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
                    <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
                    <div class="text-end">
                        <small class="text-muted">Last login: <?php echo date('M d, Y H:i'); ?></small>
                    </div>
                </div>
                
                <!-- Accounts Overview -->
                <div class="row">
                    <?php foreach ($accounts as $account): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo ucfirst($account['account_type']); ?> Account</h5>
                                <p class="card-text text-muted"><?php echo $account['account_number']; ?></p>
                                <div class="account-balance">
                                    $<?php echo format_currency($account['balance']); ?>
                                </div>
                                <div class="mt-2">
                                    <?php echo get_status_badge($account['status']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Recent Transactions -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Transactions</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_transactions)): ?>
                                    <p class="text-muted">No recent transactions</p>
                                <?php else: ?>
                                    <?php foreach ($recent_transactions as $transaction): ?>
                                    <div class="transaction-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0"><?php echo ucfirst($transaction['transaction_type']); ?></h6>
                                                <small class="text-muted"><?php echo $transaction['account_number']; ?></small>
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
                    
                    <!-- Active Loans -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Active Loans</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($active_loans)): ?>
                                    <p class="text-muted">No active loans</p>
                                <?php else: ?>
                                    <?php foreach ($active_loans as $loan): ?>
                                    <div class="mb-3">
                                        <h6>$<?php echo format_currency($loan['amount']); ?></h6>
                                        <small class="text-muted">
                                            Interest Rate: <?php echo $loan['interest_rate']; ?>%<br>
                                            Term: <?php echo $loan['term_months']; ?> months
                                        </small>
                                        <div class="mt-2">
                                            <?php echo get_status_badge($loan['status']); ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 