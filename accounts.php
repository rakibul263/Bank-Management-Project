<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Require login
require_login();

$error = '';
$success = '';

// Handle account creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_account'])) {
    $account_type = sanitize_input($_POST['account_type']);
    
    if (!in_array($account_type, ['savings', 'current'])) {
        $error = 'Invalid account type';
    } else {
        try {
            $account_number = generate_account_number();
            
            $stmt = $conn->prepare("INSERT INTO accounts (user_id, account_number, account_type) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $account_number, $account_type]);
            
            $success = 'Account created successfully!';
        } catch (PDOException $e) {
            $error = 'Failed to create account. Please try again.';
        }
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $account_id = (int)$_POST['account_id'];
    
    try {
        // Check if account belongs to user
        $stmt = $conn->prepare("SELECT balance FROM accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$account_id, $_SESSION['user_id']]);
        $account = $stmt->fetch();
        
        if ($account) {
            if ($account['balance'] > 0) {
                $error = 'Cannot delete account with remaining balance';
            } else {
                $stmt = $conn->prepare("DELETE FROM accounts WHERE id = ? AND user_id = ?");
                $stmt->execute([$account_id, $_SESSION['user_id']]);
                $success = 'Account deleted successfully!';
            }
        } else {
            $error = 'Invalid account';
        }
    } catch (PDOException $e) {
        $error = 'Failed to delete account. Please try again.';
    }
}

// Get user's accounts
$stmt = $conn->prepare("SELECT * FROM accounts WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$accounts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts - <?php echo SITE_NAME; ?></title>
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
        .account-balance {
            font-size: 24px;
            font-weight: bold;
            color: #0d6efd;
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
                        <a class="nav-link active" href="accounts.php">
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
                    <h2>My Accounts</h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAccountModal">
                        <i class="bi bi-plus-circle"></i> Create New Account
                    </button>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <?php foreach ($accounts as $account): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title"><?php echo ucfirst($account['account_type']); ?> Account</h5>
                                        <p class="card-text text-muted"><?php echo $account['account_number']; ?></p>
                                        <div class="account-balance">
                                            $<?php echo format_currency($account['balance']); ?>
                                        </div>
                                        <div class="mt-2">
                                            <?php echo get_status_badge($account['status']); ?>
                                        </div>
                                    </div>
                                    <?php if ($account['balance'] == 0): ?>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this account?');">
                                        <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                        <button type="submit" name="delete_account" class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-3">
                                    <a href="transactions.php?account=<?php echo $account['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        View Transactions
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
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
                            <label for="account_type" class="form-label">Account Type</label>
                            <select class="form-select" id="account_type" name="account_type" required>
                                <option value="">Select account type</option>
                                <option value="savings">Savings Account</option>
                                <option value="current">Current Account</option>
                            </select>
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