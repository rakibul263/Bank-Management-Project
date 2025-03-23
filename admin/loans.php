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

// Handle loan approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_loan'])) {
    $loan_id = isset($_POST['loan_id']) ? (int)$_POST['loan_id'] : 0;
    $action = isset($_POST['process_loan']) ? $_POST['process_loan'] : '';
    $remarks = isset($_POST['remarks']) ? sanitize_input($_POST['remarks']) : '';
    
    if ($loan_id <= 0) {
        $error = 'Invalid loan ID.';
    } elseif (empty($remarks)) {
        $error = 'Remarks are required.';
    } else {
        try {
            $conn->beginTransaction(); // Start transaction for consistency
            
            // Get loan details
            $stmt = $conn->prepare("
                SELECT l.*, a.balance, a.id as account_id 
                FROM loans l
                JOIN accounts a ON l.account_id = a.id
                WHERE l.id = ? AND l.status = 'pending'
            ");
            $stmt->execute([$loan_id]);
            $loan = $stmt->fetch();
            
            if (!$loan) {
                $conn->rollBack();
                $error = 'Loan not found or already processed';
            } else {
                if ($action === 'approve') {
                    // Calculate monthly payment
                    $monthly_payment = calculate_monthly_payment($loan['amount'], $loan['interest_rate'], $loan['term_months']);
                    
                    // Update loan status and monthly payment
                    $stmt = $conn->prepare("UPDATE loans SET status = 'approved', remarks = ?, processed_at = NOW(), monthly_payment = ? WHERE id = ?");
                    if (!$stmt->execute([$remarks, $monthly_payment, $loan_id])) {
                        throw new Exception("Failed to update loan status");
                    }
                    
                    // Update account balance
                    $stmt = $conn->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
                    if (!$stmt->execute([$loan['amount'], $loan['account_id']])) {
                        throw new Exception("Failed to update account balance");
                    }
                    
                    // Create transaction record
                    $new_balance = $loan['balance'] + $loan['amount'];
                    $stmt = $conn->prepare("INSERT INTO transactions (account_id, transaction_type, amount, balance_after, description) 
                                          VALUES (?, ?, ?, ?, ?)");
                    if (!$stmt->execute([$loan['account_id'], 'loan', $loan['amount'], $new_balance, 'Loan approved: ' . $remarks])) {
                        throw new Exception("Failed to create transaction record");
                    }
                    
                    $conn->commit();
                    $success = 'Loan approved successfully!';
                } else if ($action === 'reject') {
                    // Update loan status
                    $stmt = $conn->prepare("UPDATE loans SET status = 'rejected', remarks = ?, processed_at = NOW() WHERE id = ?");
                    if (!$stmt->execute([$remarks, $loan_id])) {
                        throw new Exception("Failed to update loan status");
                    }
                    
                    $conn->commit();
                    $success = 'Loan rejected successfully!';
                } else {
                    $conn->rollBack();
                    $error = 'Invalid action specified.';
                }
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Failed to process loan: ' . $e->getMessage();
        }
    }
}

// Get loan details if viewing specific loan
$loan_details = null;
if (isset($_GET['view'])) {
    $loan_id = (int)$_GET['view'];
    $stmt = $conn->prepare("
        SELECT l.*, u.full_name, u.email, a.account_number, a.balance as account_balance
        FROM loans l
        JOIN users u ON l.user_id = u.id
        JOIN accounts a ON l.account_id = a.id
        WHERE l.id = ?
    ");
    $stmt->execute([$loan_id]);
    $loan_details = $stmt->fetch();
}

// Get all loans for the list
$stmt = $conn->query("
    SELECT l.*, u.full_name, a.account_number
    FROM loans l
    JOIN users u ON l.user_id = u.id
    JOIN accounts a ON l.account_id = a.id
    ORDER BY l.created_at DESC
");
$loans = $stmt->fetchAll();

// Get pending loan count for notification
$stmt = $conn->query("SELECT COUNT(*) FROM loans WHERE status = 'pending'");
$pending_loan_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loans Management - <?php echo SITE_NAME; ?></title>
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
        
        /* Notification Badge Styles */
        .nav-link {
            position: relative;
        }
        
        .badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35em 0.65em;
            transition: all 0.3s ease;
        }
        
        .badge.bg-danger {
            background: var(--danger-color) !important;
            box-shadow: 0 4px 10px rgba(231, 74, 59, 0.3);
        }
        
        .nav-link:hover .badge {
            transform: scale(1.1);
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
        
        /* Table Styles */
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: rgba(78,115,223,0.05);
            border-bottom: 2px solid rgba(78,115,223,0.1);
            color: var(--primary-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
            padding: 1rem;
        }
        
        .table td {
            vertical-align: middle;
            color: #5a5c69;
            font-size: 0.9rem;
            padding: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .table tr:hover {
            background: rgba(78,115,223,0.02);
        }
        
        /* Pending Loan Row Styles */
        .table tr.pending-loan {
            background: rgba(231, 74, 59, 0.02);
            animation: highlight 2s infinite;
        }
        
        .table tr.pending-loan:hover {
            background: rgba(231, 74, 59, 0.05);
        }
        
        @keyframes highlight {
            0% { background: rgba(231, 74, 59, 0.02); }
            50% { background: rgba(231, 74, 59, 0.05); }
            100% { background: rgba(231, 74, 59, 0.02); }
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
        
        /* Form Styles */
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 2px solid #e1e1e1;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78,115,223,0.25);
        }
        
        /* Status Badge Styles */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-badge i {
            font-size: 1rem;
        }
        
        .status-badge.approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.rejected {
            background: #f8d7da;
            color: #721c24;
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
        
        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            background: linear-gradient(45deg, var(--primary-color), #6f42c1);
            color: white;
            border-radius: 1rem 1rem 0 0;
            padding: 1.25rem 1.5rem;
        }
        
        .modal-header .btn-close {
            color: white;
            filter: brightness(0) invert(1);
        }
        
        /* Avatar Styles */
        .avatar {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(78,115,223,0.1);
            color: var(--primary-color);
            font-weight: 600;
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
        }
    </style>
</head>
<body>
    <!-- Modern Navbar -->
    <nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-bank"></i>
                Admin Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="bi bi-list"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-speedometer2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="bi bi-people"></i>
                            Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="accounts.php">
                            <i class="bi bi-bank"></i>
                            Accounts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">
                            <i class="bi bi-cash"></i>
                            Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="loans.php">
                            <i class="bi bi-credit-card"></i>
                            Loans
                            <?php if ($pending_loan_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $pending_loan_count; ?>
                                <span class="visually-hidden">pending loans</span>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php if ($current_admin['username'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="create_admin.php">
                            <i class="bi bi-person-plus"></i>
                            Create Admin
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person"></i>
                            Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
            
            <!-- Main Content -->
    <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-credit-card"></i> Loans Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLoanModal">
                <i class="bi bi-plus-lg"></i>
                Add New Loan
            </button>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($loan_details): ?>
                    <!-- Loan Details -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Loan Details</h5>
                            <a href="loans.php" class="btn btn-secondary btn-sm">
                                <i class="bi bi-arrow-left"></i> Back to List
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Loan Information</h6>
                                    <table class="table">
                                        <tr>
                                            <th>Amount:</th>
                                            <td>$<?php echo format_currency($loan_details['amount']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Interest Rate:</th>
                                            <td><?php echo $loan_details['interest_rate']; ?>%</td>
                                        </tr>
                                        <tr>
                                            <th>Term:</th>
                                            <td><?php echo $loan_details['term_months']; ?> months</td>
                                        </tr>
                                        <tr>
                                            <th>Monthly Payment:</th>
                                            <td>$<?php echo format_currency(isset($loan_details['monthly_payment']) ? $loan_details['monthly_payment'] : 0); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Status:</th>
                                            <td><?php echo get_status_badge($loan_details['status']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Applied:</th>
                                            <td><?php echo date('M d, Y', strtotime($loan_details['created_at'])); ?></td>
                                        </tr>
                                        <?php if (isset($loan_details['processed_at']) && $loan_details['processed_at']): ?>
                                        <tr>
                                            <th>Processed:</th>
                                            <td><?php echo date('M d, Y', strtotime($loan_details['processed_at'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6>Applicant Information</h6>
                                    <table class="table">
                                        <tr>
                                            <th>Name:</th>
                                            <td><?php echo htmlspecialchars($loan_details['full_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email:</th>
                                            <td><?php echo htmlspecialchars($loan_details['email']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Account Number:</th>
                                            <td><?php echo $loan_details['account_number']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Account Balance:</th>
                                            <td>$<?php echo format_currency($loan_details['account_balance']); ?></td>
                                        </tr>
                                    </table>
                                    
                                    <?php if ($loan_details['status'] === 'pending'): ?>
                                    <!-- Loan Processing Form -->
                                    <form method="POST" action="" class="mt-4">
                                        <input type="hidden" name="loan_id" value="<?php echo $loan_details['id']; ?>">
                                        <div class="mb-3">
                                            <label for="remarks" class="form-label">Remarks</label>
                                            <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="process_loan" value="approve" class="btn btn-success">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                            <button type="submit" name="process_loan" value="reject" class="btn btn-danger">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </button>
                                        </div>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Loans List -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Applicant</th>
                                            <th>Amount</th>
                                            <th>Interest Rate</th>
                                            <th>Term</th>
                                            <th>Status</th>
                                            <th>Applied</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loans as $loan): ?>
                                <tr class="<?php echo $loan['status'] === 'pending' ? 'pending-loan' : ''; ?>">
                                            <td><?php echo htmlspecialchars($loan['full_name']); ?></td>
                                            <td>$<?php echo format_currency($loan['amount']); ?></td>
                                            <td><?php echo $loan['interest_rate']; ?>%</td>
                                            <td><?php echo $loan['term_months']; ?> months</td>
                                            <td><?php echo get_status_badge($loan['status']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></td>
                                            <td>
                                        <a href="?view=<?php echo $loan['id']; ?>" class="btn btn-sm <?php echo $loan['status'] === 'pending' ? 'btn-warning' : 'btn-primary'; ?>">
                                                    <i class="bi bi-eye"></i>
                                            <?php echo $loan['status'] === 'pending' ? 'Review' : 'View'; ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 