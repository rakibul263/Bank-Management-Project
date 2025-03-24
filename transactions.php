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
$sort_order = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'DESC'; // Default to DESC

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

// Add sorting
$query .= " ORDER BY t.created_at " . ($sort_order === 'ASC' ? 'ASC' : 'DESC');

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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3a7bd5;
            --primary-gradient: linear-gradient(to right, #3a7bd5, #00d2ff);
            --secondary-color: #6c757d;
            --accent-color: #ffc107;
            --light-bg: #f8f9fa;
            --white: #ffffff;
            --card-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            --text-primary: #333;
            --text-secondary: #6c757d;
            --border-radius: 10px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background: var(--white);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .nav-link {
            font-weight: 500;
            color: var(--text-primary);
            margin: 0 10px;
            position: relative;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .nav-link i {
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary-color);
        }

        .nav-link.active::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 3px;
            background: var(--primary-gradient);
            bottom: -7px;
            left: 0;
            border-radius: 3px;
        }

        .main-content {
            flex: 1;
            padding: 30px 0;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: var(--primary-gradient);
            color: var(--white);
            font-weight: 600;
            padding: 15px 20px;
            border: none;
        }

        .card-title {
            margin-bottom: 0;
            font-weight: 600;
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: var(--border-radius);
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            box-shadow: 0 5px 15px rgba(58, 123, 213, 0.4);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--secondary-color);
            border: none;
            border-radius: var(--border-radius);
            padding: 10px 20px;
            font-weight: 500;
        }

        .form-select, .form-control {
            border-radius: var(--border-radius);
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            font-size: 0.95rem;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .list-group-item {
            border: none;
            padding: 15px 20px;
            margin-bottom: 5px;
            background-color: var(--white);
            transition: all 0.3s;
            border-radius: 8px;
        }

        .list-group-item:hover {
            background-color: rgba(58, 123, 213, 0.05);
        }

        .transaction-type {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .transaction-account {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .transaction-amount {
            font-size: 18px;
            font-weight: 600;
        }

        .transaction-amount.deposit {
            color: #28a745;
        }

        .transaction-amount.withdraw {
            color: #dc3545;
        }

        .transaction-date {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .alert {
            border-radius: var(--border-radius);
            padding: 15px 20px;
            margin-bottom: 25px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h2 {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-radius: var(--border-radius);
        }

        .filter-card {
            margin-bottom: 25px;
        }

        .filter-card .card-body {
            padding: 20px;
        }

        .modal-content {
            border-radius: var(--border-radius);
            border: none;
        }

        .modal-header {
            background: var(--primary-gradient);
            color: var(--white);
            border-top-left-radius: var(--border-radius);
            border-top-right-radius: var(--border-radius);
        }

        .modal-title {
            font-weight: 600;
        }

        .btn-close {
            color: var(--white);
            filter: brightness(0) invert(1);
        }

        @media (max-width: 767px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .page-header .btn {
                width: 100%;
            }
            
            .card {
                margin-bottom: 20px;
            }
            
            .navbar-nav {
                margin-top: 15px;
            }
            
            .nav-item {
                width: 100%;
                text-align: center;
            }
        }

        /* Add these new styles after the existing styles */
        .sort-section {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sort-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .sort-label i {
            color: var(--primary-color);
        }

        .sort-buttons {
            display: flex;
            gap: 8px;
        }

        .sort-btn {
            padding: 6px 12px;
            font-size: 0.9rem;
            border-radius: 6px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }

        .sort-btn.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 2px 8px rgba(58, 123, 213, 0.3);
        }

        .sort-btn:not(.active) {
            background: rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
        }

        .sort-btn:not(.active):hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .pagination .page-link:hover {
            background-color: rgba(58, 123, 213, 0.1);
            border-color: rgba(58, 123, 213, 0.2);
        }
        
        /* Footer styles */
        footer {
            background: var(--white);
            padding: 15px 0;
            margin-top: auto;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
            box-shadow: 0 -2px 15px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <div class="container main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h2>Transactions</h2>
            <!-- Commented out New Transaction button
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transactionModal">
                <i class="bi bi-plus-circle"></i> New Transaction
            </button>
            -->
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <!-- Transaction Filters -->
        <div class="card filter-card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-funnel me-2"></i>Filter Transactions</h5>
            </div>
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
                    
                    <div class="col-12 d-flex justify-content-end">
                        <a href="transactions.php" class="btn btn-secondary me-2">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-filter me-1"></i> Apply Filters
                        </button>
                        <a href="statements.php" class="btn btn-outline-primary ms-2">
                            <i class="bi bi-file-earmark-pdf me-1"></i> Download Statement
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Transaction History -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>Transaction History
                    <span class="badge bg-white text-primary ms-2"><?php echo count($transactions); ?></span>
                </h5>
                <div class="sort-section">
                    <div class="sort-label">
                        <i class="bi bi-sort"></i> Sort by Date:
                    </div>
                    <div class="sort-buttons">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'DESC'])); ?>" 
                           class="sort-btn <?php echo $sort_order === 'DESC' ? 'active' : ''; ?>">
                            <i class="bi bi-sort-down"></i> Newest First
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'ASC'])); ?>" 
                           class="sort-btn <?php echo $sort_order === 'ASC' ? 'active' : ''; ?>">
                            <i class="bi bi-sort-up"></i> Oldest First
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($transactions)): ?>
                    <div class="p-4 text-center">
                        <i class="bi bi-inbox text-secondary" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">No transactions found</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush p-3">
                        <?php foreach ($transactions as $transaction): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="d-flex align-items-center">
                                        <div class="me-3 rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 40px; height: 40px; background-color: <?php echo $transaction['transaction_type'] === 'deposit' ? 'rgba(40, 167, 69, 0.1)' : 'rgba(220, 53, 69, 0.1)'; ?>">
                                            <i class="bi <?php echo $transaction['transaction_type'] === 'deposit' ? 'bi-arrow-down-circle' : 'bi-arrow-up-circle'; ?> 
                                                         <?php echo $transaction['transaction_type'] === 'deposit' ? 'text-success' : 'text-danger'; ?>"></i>
                                        </div>
                                        <div>
                                            <h6 class="transaction-type mb-1"><?php echo ucfirst($transaction['transaction_type']); ?></h6>
                                            <small class="transaction-account">
                                                <?php echo $transaction['account_number']; ?> (<?php echo ucfirst($transaction['account_type']); ?>)
                                            </small>
                                            <?php if (!empty($transaction['description'])): ?>
                                                <p class="text-muted mb-0 mt-1 small fst-italic"><?php echo $transaction['description']; ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="transaction-amount <?php echo $transaction['transaction_type'] === 'deposit' ? 'deposit' : 'withdraw'; ?>">
                                        <?php echo $transaction['transaction_type'] === 'deposit' ? '+' : '-'; ?>
                                        $<?php echo format_currency($transaction['amount']); ?>
                                    </span>
                                    <br>
                                    <small class="transaction-date">
                                        <?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Transaction Modal - Commented out
    <div class="modal fade" id="transactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>New Transaction</h5>
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
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="2" placeholder="Enter description..."></textarea>
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
    -->
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 