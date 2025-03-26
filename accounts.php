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
        // Begin transaction for safety
        $conn->beginTransaction();
        
        // Check if account belongs to user
        $stmt = $conn->prepare("SELECT balance FROM accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$account_id, $_SESSION['user_id']]);
        $account = $stmt->fetch();
        
        if ($account) {
            if ($account['balance'] > 0) {
                $error = 'Cannot delete account with remaining balance';
                $conn->rollBack();
            } else {
                // First delete related records from all tables that reference account_id
                
                // Delete from withdrawal_requests
                $stmt = $conn->prepare("DELETE FROM withdrawal_requests WHERE account_id = ?");
                $stmt->execute([$account_id]);
                
                // Delete from loans
                $stmt = $conn->prepare("DELETE FROM loans WHERE account_id = ?");
                $stmt->execute([$account_id]);
                
                // Delete from transfers (both as sender and receiver)
                $stmt = $conn->prepare("DELETE FROM transfers WHERE from_account_id = ? OR to_account_id = ?");
                $stmt->execute([$account_id, $account_id]);
                
                // Delete related transactions
                $stmt = $conn->prepare("DELETE FROM transactions WHERE account_id = ?");
                $stmt->execute([$account_id]);
                
                // Then delete the account
                $stmt = $conn->prepare("DELETE FROM accounts WHERE id = ? AND user_id = ?");
                $stmt->execute([$account_id, $_SESSION['user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    $success = 'Account deleted successfully!';
                    $conn->commit();
                } else {
                    $error = 'Failed to delete account';
                    $conn->rollBack();
                }
            }
        } else {
            $error = 'Invalid account';
            $conn->rollBack();
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = 'Failed to delete account. Error: ' . $e->getMessage();
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
        
        .btn-danger {
            background: #dc3545;
            border: none;
            border-radius: var(--border-radius);
            transition: all 0.3s;
        }
        
        .btn-danger:hover {
            background: #c82333;
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
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

        /* Account Balance Styling */
        .account-balance {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 10px 0;
        }

        .account-balance:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
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
            padding: 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease-in-out;
        }

        .page-header h2 {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
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
            <div class="d-flex justify-content-between align-items-center page-header mb-4">
                <h2><i class="bi bi-wallet2 me-2"></i>Your Accounts</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAccountModal">
                    <i class="bi bi-plus-circle me-2"></i>New Account
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
                                    <i class="bi bi-list-ul me-1"></i> View Transactions
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Create Account Modal -->
    <div class="modal fade" id="createAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Create New Account</h5>
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
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 