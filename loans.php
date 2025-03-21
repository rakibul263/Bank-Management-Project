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

// Handle loan application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_loan'])) {
    $account_id = (int)$_POST['account_id'];
    $amount = (float)$_POST['amount'];
    $term_months = (int)$_POST['term_months'];
    
    // Validate account ownership
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$account_id, $_SESSION['user_id']]);
    $account = $stmt->fetch();
    
    if (!$account) {
        $error = 'Invalid account';
    } elseif ($amount <= 0) {
        $error = 'Amount must be greater than 0';
    } elseif ($term_months < 1 || $term_months > 60) {
        $error = 'Loan term must be between 1 and 60 months';
    } elseif (!can_apply_loan($_SESSION['user_id'])) {
        $error = 'You have reached the maximum number of active loans (2)';
    } else {
        try {
            // Calculate interest rate based on amount and term
            $interest_rate = 5.0; // Base rate
            if ($amount > 10000) {
                $interest_rate += 1.0;
            }
            if ($term_months > 24) {
                $interest_rate += 0.5;
            }
            
            $stmt = $conn->prepare("INSERT INTO loans (user_id, account_id, amount, interest_rate, term_months) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $account_id, $amount, $interest_rate, $term_months]);
            
            $success = 'Loan application submitted successfully!';
        } catch (PDOException $e) {
            $error = 'Failed to submit loan application. Please try again.';
        }
    }
}

// Get active loans
$stmt = $conn->prepare("
    SELECT l.*, a.account_number, a.account_type 
    FROM loans l
    JOIN accounts a ON l.account_id = a.id
    WHERE l.user_id = ? AND l.status IN ('pending', 'approved')
    ORDER BY l.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$active_loans = $stmt->fetchAll();

// Get loan history
$stmt = $conn->prepare("
    SELECT l.*, a.account_number, a.account_type 
    FROM loans l
    JOIN accounts a ON l.account_id = a.id
    WHERE l.user_id = ? AND l.status NOT IN ('pending', 'approved')
    ORDER BY l.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$loan_history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loans - <?php echo SITE_NAME; ?></title>
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
        .loan-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .loan-item:last-child {
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
                        <a class="nav-link active" href="loans.php">
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
                    <h2>Loans</h2>
                    <?php if (can_apply_loan($_SESSION['user_id'])): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loanModal">
                        <i class="bi bi-plus-circle"></i> Apply for Loan
                    </button>
                    <?php endif; ?>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Active Loans -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Active Loans</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($active_loans)): ?>
                            <p class="text-muted">No active loans</p>
                        <?php else: ?>
                            <?php foreach ($active_loans as $loan): ?>
                            <div class="loan-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">$<?php echo format_currency($loan['amount']); ?></h6>
                                        <small class="text-muted">
                                            Account: <?php echo $loan['account_number']; ?> (<?php echo ucfirst($loan['account_type']); ?>)<br>
                                            Interest Rate: <?php echo $loan['interest_rate']; ?>%<br>
                                            Term: <?php echo $loan['term_months']; ?> months
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="mb-1">
                                            <?php echo get_status_badge($loan['status']); ?>
                                        </div>
                                        <small class="text-muted">
                                            Applied: <?php echo date('M d, Y', strtotime($loan['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Loan History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Loan History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($loan_history)): ?>
                            <p class="text-muted">No loan history</p>
                        <?php else: ?>
                            <?php foreach ($loan_history as $loan): ?>
                            <div class="loan-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">$<?php echo format_currency($loan['amount']); ?></h6>
                                        <small class="text-muted">
                                            Account: <?php echo $loan['account_number']; ?> (<?php echo ucfirst($loan['account_type']); ?>)<br>
                                            Interest Rate: <?php echo $loan['interest_rate']; ?>%<br>
                                            Term: <?php echo $loan['term_months']; ?> months
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="mb-1">
                                            <?php echo get_status_badge($loan['status']); ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($loan['created_at'])); ?>
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
    
    <!-- Loan Application Modal -->
    <div class="modal fade" id="loanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Apply for Loan</h5>
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
                            <label for="amount" class="form-label">Loan Amount</label>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="term_months" class="form-label">Loan Term (months)</label>
                            <input type="number" class="form-control" id="term_months" name="term_months" min="1" max="60" required>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6 class="alert-heading">Loan Terms</h6>
                            <ul class="mb-0">
                                <li>Maximum 2 active loans at a time</li>
                                <li>Loan term: 1-60 months</li>
                                <li>Interest rate varies based on amount and term</li>
                                <li>Loan approval is subject to verification</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="apply_loan" class="btn btn-primary">Apply for Loan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 