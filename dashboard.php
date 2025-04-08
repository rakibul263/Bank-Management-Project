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

        .account-balance {
            font-size: 26px;
            font-weight: bold;
            color: var(--primary-color);
        }

        .transaction-item {
            padding: 14px 0;
            border-bottom: 1px solid #ddd;
            transition: background 0.3s ease-in-out;
        }

        .transaction-item:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .welcome-banner {
            background: var(--primary-gradient);
            color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
        }

        .welcome-banner h2 {
            font-size: 1.8rem;
            font-weight: 600;
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

        .btn-outline-primary {
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: var(--border-radius);
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-outline-primary:hover {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
        }

        @media (max-width: 767px) {
            .welcome-banner {
                text-align: center;
            }
            
            .welcome-banner .d-flex {
                flex-direction: column;
                align-items: center;
            }
            
            .welcome-banner .text-end {
                text-align: center !important;
                margin-top: 10px;
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

        .refresh-btn {
            padding: 8px 16px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .refresh-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-2px);
        }

        .refresh-btn i {
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }

        .refresh-btn:active i {
            transform: rotate(180deg);
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
        <div class="welcome-banner">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
                    <p class="mb-0">Here's your financial overview</p>
                </div>
                <div class="text-end d-flex align-items-center">
                    <button onclick="window.location.reload()" class="btn btn-light refresh-btn me-3">
                        <i class="bi bi-arrow-clockwise"></i>
                        Refresh
                    </button>
                    <div>
                        <small>Last login: <?php echo date('M d, Y H:i'); ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Accounts Overview -->
        <h4 class="mb-4"><i class="bi bi-wallet2 me-2"></i>Your Accounts</h4>
        <div class="row">
            <?php foreach ($accounts as $account): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0"><?php echo ucfirst($account['account_type']); ?> Account</h5>
                            <div>
                                <?php echo get_status_badge($account['status']); ?>
                            </div>
                        </div>
                        <p class="card-text text-muted"><?php echo $account['account_number']; ?></p>
                        <div class="account-balance mb-3">
                            <?php echo format_currency($account['balance']); ?>
                        </div>
                        <a href="transactions.php?account=<?php echo $account['id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-list-ul me-1"></i> View Transactions
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Recent Transactions and Loans -->
        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Recent Transactions</h5>
                        <a href="transactions.php" class="btn btn-sm btn-light">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_transactions)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No recent transactions</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_transactions as $transaction): ?>
                            <div class="transaction-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3 rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 36px; height: 36px; background-color: <?php echo $transaction['transaction_type'] === 'deposit' ? 'rgba(40, 167, 69, 0.1)' : 'rgba(220, 53, 69, 0.1)'; ?>">
                                            <i class="bi <?php echo $transaction['transaction_type'] === 'deposit' ? 'bi-arrow-down-circle' : 'bi-arrow-up-circle'; ?> 
                                                        <?php echo $transaction['transaction_type'] === 'deposit' ? 'text-success' : 'text-danger'; ?>"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo ucfirst($transaction['transaction_type']); ?></h6>
                                            <small class="text-muted"><?php echo $transaction['account_number']; ?></small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold <?php echo $transaction['transaction_type'] === 'deposit' ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $transaction['transaction_type'] === 'deposit' ? '+' : '-'; ?>
                                            <?php echo format_currency($transaction['amount']); ?>
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
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="bi bi-bank me-2"></i>Active Loans</h5>
                        <a href="loans.php" class="btn btn-sm btn-light">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($active_loans)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-piggy-bank fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No active loans</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($active_loans as $loan): ?>
                            <div class="mb-3 p-3 border rounded">
                                <h6><?php echo format_currency($loan['amount']); ?></h6>
                                <div class="row mt-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Interest Rate</small>
                                        <span class="fw-semibold"><?php echo $loan['interest_rate']; ?>%</span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Term</small>
                                        <span class="fw-semibold"><?php echo $loan['term_months']; ?> months</span>
                                    </div>
                                </div>
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
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 