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
            
            $_SESSION['success'] = 'Loan application submitted successfully!';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $error = 'Failed to submit loan application. Please try again.';
        }
    }
}

// Get success message from session if exists
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
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

        .alert {
            border-radius: var(--border-radius);
            padding: 15px 20px;
            margin-bottom: 25px;
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
        
        .loan-card {
            border: 1px solid #eee;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            transition: all 0.3s ease;
        }
        
        .loan-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px);
        }
        
        .loan-card .amount {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .loan-card .info-row {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .loan-card .info-item {
            text-align: center;
        }
        
        .loan-card .info-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .loan-card .info-value {
            font-weight: 600;
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
            
            .loan-card .info-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .loan-card .info-item {
                text-align: left;
                display: flex;
                justify-content: space-between;
            }
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

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-bank me-2"></i>Loans</h2>
                <?php if (can_apply_loan($_SESSION['user_id'])): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loanModal">
                    <i class="bi bi-plus-circle me-2"></i>Apply for Loan
                </button>
                <?php else: ?>
                <button type="button" class="btn btn-secondary" disabled>
                    <i class="bi bi-exclamation-circle me-2"></i>Maximum Loans Reached
                </button>
                <?php endif; ?>
            </div>
            
            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success) && $success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <?php if (isset($loans) && !empty($loans)): ?>
                    <?php foreach ($loans as $loan): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="loan-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Loan #<?php echo $loan['id']; ?></h5>
                                    <?php echo get_status_badge($loan['status']); ?>
                                </div>
                                <div class="amount mb-3"><?php echo format_currency($loan['amount']); ?></div>
                                <div class="info-row">
                                    <div class="info-item">
                                        <div class="info-label">Interest Rate</div>
                                        <div class="info-value"><?php echo $loan['interest_rate']; ?>%</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Term</div>
                                        <div class="info-value"><?php echo $loan['term_months']; ?> months</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Applied On</div>
                                        <div class="info-value"><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></div>
                                    </div>
                                </div>
                                <?php if ($loan['status'] === 'approved'): ?>
                                    <div class="mt-3 pt-3 border-top">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="info-label">Monthly Payment</div>
                                                <div class="info-value text-primary"><?php echo format_currency($loan['monthly_payment']); ?></div>
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary">View Details</button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center p-5">
                                <i class="bi bi-bank text-muted" style="font-size: 3rem;"></i>
                                <p class="mt-3 mb-0 text-muted">You don't have any loans yet.</p>
                                <p class="text-muted">Apply for a loan to get started.</p>
                                <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#loanModal">
                                    <i class="bi bi-plus-circle me-2"></i> Apply for Loan
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Loan Application Modal -->
    <div class="modal fade" id="loanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-bank me-2"></i>Apply for Loan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="account_id" class="form-label">Account</label>
                            <select class="form-select" id="account_id" name="account_id" required>
                                <option value="">Select account</option>
                                <?php if (isset($accounts)): foreach ($accounts as $account): ?>
                                    <option value="<?php echo $account['id']; ?>">
                                        <?php echo $account['account_number']; ?> (<?php echo ucfirst($account['account_type']); ?>)
                                    </option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Loan Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="1000" max="50000" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="term_months" class="form-label">Loan Term (Months)</label>
                            <select class="form-select" id="term_months" name="term_months" required>
                                <option value="">Select term</option>
                                <option value="12">12 months (1 year)</option>
                                <option value="24">24 months (2 years)</option>
                                <option value="36">36 months (3 years)</option>
                                <option value="48">48 months (4 years)</option>
                                <option value="60">60 months (5 years)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="purpose" class="form-label">Loan Purpose</label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="3" placeholder="Please describe the purpose of this loan..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="apply_loan" class="btn btn-primary">Submit Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 