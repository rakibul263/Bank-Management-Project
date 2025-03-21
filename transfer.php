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

// Handle transfer initiation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['initiate_transfer'])) {
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
        } elseif ($to_account['id'] === $from_account['id']) {
            $error = 'Cannot transfer to the same account';
        } else {
            try {
                // Generate OTP
                $otp = generate_otp();
                $otp_expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                
                // Create transfer record
                $stmt = $conn->prepare("INSERT INTO transfers (from_account_id, to_account_id, amount, otp, otp_expiry) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$from_account_id, $to_account['id'], $amount, $otp, $otp_expiry]);
                
                $transfer_id = $conn->lastInsertId();
                
                // Store transfer ID in session for OTP verification
                $_SESSION['pending_transfer_id'] = $transfer_id;
                
                // In a real application, you would send the OTP via email/SMS
                // For demo purposes, we'll show it on screen
                $success = "Transfer initiated! Your OTP is: " . $otp;
            } catch (PDOException $e) {
                $error = 'Failed to initiate transfer. Please try again.';
            }
        }
    }
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $otp = sanitize_input($_POST['otp']);
    $transfer_id = $_SESSION['pending_transfer_id'] ?? 0;
    
    if (!$transfer_id) {
        $error = 'No pending transfer found';
    } else {
        $stmt = $conn->prepare("SELECT * FROM transfers WHERE id = ? AND otp = ? AND otp_expiry > NOW()");
        $stmt->execute([$transfer_id, $otp]);
        $transfer = $stmt->fetch();
        
        if (!$transfer) {
            $error = 'Invalid or expired OTP';
        } else {
            try {
                $conn->beginTransaction();
                
                // Update source account
                $stmt = $conn->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$transfer['amount'], $transfer['from_account_id']]);
                
                // Update destination account
                $stmt = $conn->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$transfer['amount'], $transfer['to_account_id']]);
                
                // Create transaction records
                $stmt = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description) VALUES (?, 'transfer', ?, (SELECT balance FROM accounts WHERE id = ?), ?)");
                $stmt->execute([$transfer['from_account_id'], $transfer['amount'], $transfer['from_account_id'], 'Transfer to ' . $transfer['to_account_id']]);
                $stmt->execute([$transfer['to_account_id'], $transfer['amount'], $transfer['to_account_id'], 'Transfer from ' . $transfer['from_account_id']]);
                
                // Update transfer status
                $stmt = $conn->prepare("UPDATE transfers SET status = 'completed' WHERE id = ?");
                $stmt->execute([$transfer_id]);
                
                $conn->commit();
                $success = 'Transfer completed successfully!';
                
                // Clear pending transfer
                unset($_SESSION['pending_transfer_id']);
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
        .transfer-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .transfer-item:last-child {
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
                    <h2>Transfer Funds</h2>
                    <?php if (!isset($_SESSION['pending_transfer_id'])): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transferModal">
                        <i class="bi bi-plus-circle"></i> New Transfer
                    </button>
                    <?php endif; ?>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['pending_transfer_id'])): ?>
                <!-- OTP Verification Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Verify OTP</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="otp" class="form-label">Enter OTP</label>
                                <input type="text" class="form-control" id="otp" name="otp" required>
                            </div>
                            <button type="submit" name="verify_otp" class="btn btn-primary">Verify OTP</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Transfer History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Transfer History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transfers)): ?>
                            <p class="text-muted">No transfers found</p>
                        <?php else: ?>
                            <?php foreach ($transfers as $transfer): ?>
                            <div class="transfer-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Transfer</h6>
                                        <small class="text-muted">
                                            From: <?php echo $transfer['from_account_number']; ?><br>
                                            To: <?php echo $transfer['to_account_number']; ?>
                                        </small>
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
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transfer Modal -->
    <div class="modal fade" id="transferModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Transfer</h5>
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
                                        <?php echo $account['account_number']; ?> (<?php echo ucfirst($account['account_type']); ?>)
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
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="initiate_transfer" class="btn btn-primary">Initiate Transfer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 