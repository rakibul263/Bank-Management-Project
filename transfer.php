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

// Handle transfer - now a single step without OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_transfer'])) {
    $from_account_id = (int)$_POST['from_account_id'];
    $to_account_number = sanitize_input($_POST['to_account_number']);
    $amount = (float)$_POST['amount'];
    $description = sanitize_input($_POST['description']);
    
    // Validate source account
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$from_account_id, $_SESSION['user_id']]);
    $from_account = $stmt->fetch();
    
    if (!$from_account) {
        $error = 'Invalid source account';
    } elseif ($amount <= 0) {
        $error = 'Amount must be greater than 0';
    } elseif ($amount > $from_account['balance']) {
        $error = 'Insufficient funds';
    } else {
        // Validate destination account
        $stmt = $conn->prepare("SELECT * FROM accounts WHERE account_number = ?");
        $stmt->execute([$to_account_number]);
        $to_account = $stmt->fetch();
        
        if (!$to_account) {
            $error = 'Invalid destination account number';
        } else {
            try {
                $conn->beginTransaction();
                
                // Update source account
                $stmt = $conn->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$amount, $from_account_id]);
                
                // Update destination account
                $stmt = $conn->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$amount, $to_account['id']]);
                
                // Create transfer record
                $stmt = $conn->prepare("INSERT INTO transfers (from_account_id, to_account_id, amount, status) VALUES (?, ?, ?, 'completed')");
                $stmt->execute([$from_account_id, $to_account['id'], $amount]);
                
                $transfer_id = $conn->lastInsertId();
                
                // Create transaction records
                $stmt = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description) VALUES (?, 'transfer', ?, (SELECT balance FROM accounts WHERE id = ?), ?)");
                $stmt->execute([$from_account_id, $amount, $from_account_id, 'Transfer to ' . $to_account_number . ($description ? ': ' . $description : '')]);
                $stmt->execute([$to_account['id'], $amount, $to_account['id'], 'Transfer from ' . $from_account['account_number'] . ($description ? ': ' . $description : '')]);
                
                $conn->commit();
                $success = 'Transfer completed successfully!';
            } catch (Exception $e) {
                $conn->rollBack();
                $error = 'Transfer failed. Please try again.';
            }
        }
    }
}

// Get transfer history
$stmt = $conn->prepare("
    SELECT t.*, 
           fa.account_number as from_account_number,
           ta.account_number as to_account_number
    FROM transfers t
    JOIN accounts fa ON t.from_account_id = fa.id
    JOIN accounts ta ON t.to_account_id = ta.id
    WHERE fa.user_id = ? OR ta.user_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$transfers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Funds - <?php echo SITE_NAME; ?></title>
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

        .transfer-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .transfer-item:last-child {
            border-bottom: none;
        }
        
        .transfer-item:hover {
            background-color: rgba(58, 123, 213, 0.05);
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

        .alert {
            border-radius: var(--border-radius);
            padding: 15px 20px;
            margin-bottom: 25px;
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
        
        .transfer-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            margin-right: 15px;
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
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><?php echo SITE_NAME; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
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
                        <a class="nav-link active" href="transfer.php">
                            <i class="bi bi-arrow-left-right"></i> Transfer
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="loans.php">
                            <i class="bi bi-bank"></i> Loans
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="statements.php">
                            <i class="bi bi-file-earmark-text"></i> Statements
                        </a>
                    </li>
                </ul>
                <div class="dropdown">
                    <div class="d-flex align-items-center" role="button" data-bs-toggle="dropdown">
                        <div class="avatar">
                            <?php echo substr($_SESSION['user_name'] ?? 'U', 0, 1); ?>
                        </div>
                        <i class="bi bi-chevron-down ms-1"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container main-content">
        <div class="page-header">
            <h2><i class="bi bi-arrow-left-right me-2"></i>Transfer Funds</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transferModal">
                <i class="bi bi-plus-circle me-2"></i> New Transfer
            </button>
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
        
        <!-- Transfer History -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Transfer History</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($transfers)): ?>
                    <div class="p-4 text-center">
                        <i class="bi bi-inbox text-secondary" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">No transfers found</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($transfers as $transfer): ?>
                        <div class="transfer-item">
                            <div class="d-flex align-items-center">
                                <div class="transfer-icon">
                                    <i class="bi bi-arrow-left-right"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Transfer</h6>
                                    <small class="text-muted">
                                        From: <?php echo $transfer['from_account_number']; ?><br>
                                        To: <?php echo $transfer['to_account_number']; ?>
                                    </small>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="text-danger">
                                    -$<?php echo format_currency($transfer['amount']); ?>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('M d, Y H:i', strtotime($transfer['created_at'])); ?>
                                </small>
                                <div class="mt-1">
                                    <?php echo get_status_badge($transfer['status']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Transfer Modal -->
    <div class="modal fade" id="transferModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>New Transfer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="from_account_id" class="form-label">From Account</label>
                            <select class="form-select" id="from_account_id" name="from_account_id" required>
                                <option value="">Select account</option>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?php echo $account['id']; ?>">
                                        <?php echo $account['account_number']; ?> (<?php echo ucfirst($account['account_type']); ?>) - $<?php echo format_currency($account['balance']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="to_account_number" class="form-label">To Account Number</label>
                            <input type="text" class="form-control" id="to_account_number" name="to_account_number" required>
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
                        <button type="submit" name="process_transfer" class="btn btn-primary">Complete Transfer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>