<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Get current admin info
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$current_admin = $stmt->fetch();

$error = '';
$success = '';

// Get admin information
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

// Get accounts created by this admin with status counts
$stmt = $conn->prepare("SELECT 
                        COUNT(*) as total_accounts,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_accounts,
                        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_accounts 
                        FROM accounts");
$stmt->execute();
$accounts_stat = $stmt->fetch();
$total_accounts_created = $accounts_stat['total_accounts'] ?? 0;
$active_accounts = $accounts_stat['active_accounts'] ?? 0;
$inactive_accounts = $accounts_stat['inactive_accounts'] ?? 0;

// 2. Get loans approved/rejected by this admin
$stmt = $conn->prepare("SELECT 
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_loans,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_loans
                        FROM loans");
$stmt->execute();
$loans_stat = $stmt->fetch();
$approved_loans = $loans_stat['approved_loans'] ?? 0;
$rejected_loans = $loans_stat['rejected_loans'] ?? 0;

// 3. Get withdrawals approved/rejected by this admin
$stmt = $conn->prepare("SELECT 
                        COUNT(*) as total_withdrawals,
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_withdrawals,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_withdrawals
                        FROM withdrawal_requests 
                        WHERE admin_id = ? AND (status = 'approved' OR status = 'rejected')");
$stmt->execute([$_SESSION['admin_id']]);
$withdrawals_stat = $stmt->fetch();
$total_withdrawals = $withdrawals_stat['total_withdrawals'] ?? 0;
$approved_withdrawals = $withdrawals_stat['approved_withdrawals'] ?? 0;
$rejected_withdrawals = $withdrawals_stat['rejected_withdrawals'] ?? 0;

// Get recent activity
// Get 5 most recent withdrawals processed by admin
$stmt = $conn->prepare("SELECT wr.*, u.full_name as user_name, a.account_number 
                        FROM withdrawal_requests wr
                        JOIN accounts a ON wr.account_id = a.id
                        JOIN users u ON a.user_id = u.id
                        WHERE wr.admin_id = ? AND (wr.status = 'approved' OR wr.status = 'rejected')
                        ORDER BY wr.processed_at DESC
                        LIMIT 5");
$stmt->execute([$_SESSION['admin_id']]);
$recent_withdrawals = $stmt->fetchAll();

// Get 5 most recent loans processed by admin
$stmt = $conn->prepare("SELECT l.*, u.full_name as user_name 
                        FROM loans l
                        JOIN users u ON l.user_id = u.id
                        WHERE l.status = 'approved' OR l.status = 'rejected'
                        ORDER BY l.processed_at DESC
                        LIMIT 5");
$stmt->execute();
$recent_loans = $stmt->fetchAll();

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif (!password_verify($current_password, $admin['password'])) {
        $error = 'Current password is incorrect';
    } elseif (!validate_password($new_password)) {
        $error = 'New password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
            
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['admin_id']]);
            
            $success = 'Password changed successfully!';
            
            // Get updated admin information
            $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $admin = $stmt->fetch();
        } catch (PDOException $e) {
            $error = 'Failed to change password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #2c3e50;
            --light-color: #f8f9fc;
        }
        
        body {
            background-color: var(--light-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Modern Navbar Styles */
        .navbar {
            background: linear-gradient(135deg, var(--dark-color) 0%, #1a252f 100%);
            padding: 1rem 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .navbar-brand {
            color: white !important;
            font-weight: 600;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .navbar-brand i {
            font-size: 1.8rem;
            color: var(--primary-color);
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            padding: 0.5rem 1rem;
            margin: 0 0.2rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-link:hover {
            color: white !important;
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }
        
        .nav-link.active {
            color: white !important;
            background: var(--primary-color);
            box-shadow: 0 4px 15px rgba(78,115,223,0.3);
        }
        
        .nav-link i {
            font-size: 1.2rem;
        }
        
        .navbar-toggler {
            border: none;
            padding: 0.5rem;
            color: white;
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
        }
        
        .main-content {
            margin-top: 80px;
            padding: 2rem;
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            transition: all 0.3s;
            background: white;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background: linear-gradient(45deg, var(--primary-color), #6f42c1);
            color: white;
            padding: 1.25rem 1.5rem;
            border: none;
        }
        
        .card-header h5 {
            color: white;
            font-weight: 600;
            margin: 0;
        }
        
        /* Form Styles */
        .form-control {
            border-radius: 0.5rem;
            border: 2px solid #e1e1e1;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78,115,223,0.25);
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 0.5rem;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        .alert-danger {
            background: #fff5f5;
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }
        
        .alert-success {
            background: #f0fff4;
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }
        
        .alert-info {
            background: #e8f4f8;
            color: var(--info-color);
            border-left: 4px solid var(--info-color);
        }
        
        /* Button Styles */
        .btn {
            border-radius: 0.5rem;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), #6f42c1);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(45deg, #6f42c1, var(--primary-color));
        }
        
        /* Profile Header Styles */
        .profile-header {
            background: linear-gradient(45deg, var(--primary-color), #6f42c1);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.15);
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
            color: var(--primary-color);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .profile-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .profile-role {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        /* Table Styles */
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            font-weight: 600;
            color: var(--dark-color);
            width: 40%;
            padding: 1rem;
        }
        
        .table td {
            color: var(--secondary-color);
            padding: 1rem;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .form-text {
            color: var(--secondary-color);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 1rem;
            }
            
            .main-content {
                margin-top: 60px;
                padding: 1rem;
            }
            
            .nav-link {
                padding: 0.75rem 1rem;
            }
            
            .profile-header {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include the navbar -->
            <?php include 'navbar.php'; ?>
            
            <!-- Main Content -->
            <div class="col-md-12 main-content">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($admin['username']); ?></div>
                    <div class="profile-role">Administrator</div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Left Column - Admin Information and Activity Stats -->
                    <div class="col-lg-4">
                        <!-- Admin Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-info-circle"></i> Account Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table">
                                    <tr>
                                        <th>Username:</th>
                                        <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Admin ID:</th>
                                        <td><?php echo $admin['id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Account Created:</th>
                                        <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Admin Activity Statistics -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-graph-up"></i> Activity Statistics</h5>
                            </div>
                            <div class="card-body">
                                <!-- Accounts Section -->
                                <div class="mb-4">
                                    <h6 class="fw-bold text-primary"><i class="bi bi-wallet2"></i> Accounts</h6>
                                    <div class="row g-3 mb-3">
                                        <div class="col-12">
                                            <div class="p-3 rounded-3 bg-light">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>Total Accounts Created</span>
                                                    <span class="badge bg-primary rounded-pill"><?php echo $total_accounts_created; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-3 rounded-3 bg-success bg-opacity-10">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>Active</span>
                                                    <span class="badge bg-success rounded-pill"><?php echo $active_accounts; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-3 rounded-3 bg-secondary bg-opacity-10">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>Inactive</span>
                                                    <span class="badge bg-secondary rounded-pill"><?php echo $inactive_accounts; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Loans Section -->
                                <div class="mb-4">
                                    <h6 class="fw-bold text-primary"><i class="bi bi-cash-coin"></i> Loans</h6>
                                    <div class="row g-3 mb-3">
                                        <div class="col-6">
                                            <div class="p-3 rounded-3 bg-success bg-opacity-10">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>Approved</span>
                                                    <span class="badge bg-success rounded-pill"><?php echo $approved_loans; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-3 rounded-3 bg-danger bg-opacity-10">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>Rejected</span>
                                                    <span class="badge bg-danger rounded-pill"><?php echo $rejected_loans; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Withdrawals Section -->
                                <div>
                                    <h6 class="fw-bold text-primary"><i class="bi bi-cash-stack"></i> Withdrawals</h6>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <div class="p-3 rounded-3 bg-success bg-opacity-10">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>Approved</span>
                                                    <span class="badge bg-success rounded-pill"><?php echo $approved_withdrawals; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-3 rounded-3 bg-danger bg-opacity-10">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>Rejected</span>
                                                    <span class="badge bg-danger rounded-pill"><?php echo $rejected_withdrawals; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 mt-2">
                                            <div class="p-3 rounded-3 bg-light">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>Total Withdrawals Processed</span>
                                                    <span class="badge bg-primary rounded-pill"><?php echo $total_withdrawals; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Middle Column - Change Password -->
                    <div class="col-lg-4">
                        <!-- Change Password Form -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-key"></i> Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <h6 class="alert-heading"><i class="bi bi-info-circle"></i> Password Requirements</h6>
                                        <ul class="mb-0">
                                            <li>At least 8 characters long</li>
                                            <li>Contains at least one uppercase letter</li>
                                            <li>Contains at least one lowercase letter</li>
                                            <li>Contains at least one number</li>
                                        </ul>
                                    </div>
                                    
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="bi bi-key"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column - Recent Activity -->
                    <div class="col-lg-4">
                        <!-- Recent Activity -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-activity"></i> Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <!-- Recent Withdrawal Activity -->
                                <div class="mb-4">
                                    <h6 class="fw-bold text-primary"><i class="bi bi-cash-stack"></i> Recent Withdrawals</h6>
                                    <?php if (empty($recent_withdrawals)): ?>
                                        <p class="text-muted fst-italic">No recent withdrawal activity</p>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach ($recent_withdrawals as $withdrawal): ?>
                                                <div class="list-group-item list-group-item-action p-3">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($withdrawal['user_name']); ?></h6>
                                                        <small class="text-muted">
                                                            <?php 
                                                                $processed_date = isset($withdrawal['processed_at']) ? 
                                                                    date('M d, Y', strtotime($withdrawal['processed_at'])) : 
                                                                    date('M d, Y', strtotime($withdrawal['created_at']));
                                                                echo $processed_date;
                                                            ?>
                                                        </small>
                                                    </div>
                                                    <p class="mb-1">
                                                        Amount: <?php echo format_currency($withdrawal['amount']); ?> - 
                                                        <?php 
                                                            $status_class = $withdrawal['status'] === 'approved' ? 'success' : 'danger';
                                                            echo '<span class="badge bg-' . $status_class . '">' . ucfirst($withdrawal['status']) . '</span>';
                                                        ?>
                                                    </p>
                                                    <small class="text-muted">
                                                        Account: <?php echo htmlspecialchars($withdrawal['account_number']); ?>
                                                    </small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Recent Loan Activity -->
                                <div>
                                    <h6 class="fw-bold text-primary"><i class="bi bi-cash-coin"></i> Recent Loans</h6>
                                    <?php if (empty($recent_loans)): ?>
                                        <p class="text-muted fst-italic">No recent loan activity</p>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach ($recent_loans as $loan): ?>
                                                <div class="list-group-item list-group-item-action p-3">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($loan['user_name']); ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo date('M d, Y', strtotime($loan['processed_at'] ?? $loan['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <p class="mb-1">
                                                        Amount: <?php echo format_currency($loan['amount']); ?> - 
                                                        <?php 
                                                            $status_class = $loan['status'] === 'approved' ? 'success' : 'danger';
                                                            echo '<span class="badge bg-' . $status_class . '">' . ucfirst($loan['status']) . '</span>';
                                                        ?>
                                                    </p>
                                                    <small class="text-muted">
                                                        Term: <?php echo $loan['term_months']; ?> months
                                                        <?php if (isset($loan['interest_rate'])): ?>
                                                            - Interest Rate: <?php echo $loan['interest_rate']; ?>%
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
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