<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

// Handle loan approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_loan'])) {
    $loan_id = (int)$_POST['loan_id'];
    $action = sanitize_input($_POST['action']);
    $remarks = sanitize_input($_POST['remarks']);
    
    try {
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
            $error = 'Loan not found or already processed';
        } else {
            if ($action === 'approve') {
                // Update loan status
                $stmt = $conn->prepare("UPDATE loans SET status = 'approved', remarks = ?, processed_at = NOW() WHERE id = ?");
                $stmt->execute([$remarks, $loan_id]);
                
                // Update account balance
                $stmt = $conn->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$loan['amount'], $loan['account_id']]);
                
                // Create transaction record
                create_transaction($loan['account_id'], 'loan', $loan['amount'], 'Loan approved: ' . $remarks);
                
                $success = 'Loan approved successfully!';
            } else {
                // Update loan status
                $stmt = $conn->prepare("UPDATE loans SET status = 'rejected', remarks = ?, processed_at = NOW() WHERE id = ?");
                $stmt->execute([$remarks, $loan_id]);
                
                $success = 'Loan rejected successfully!';
            }
        }
    } catch (PDOException $e) {
        $error = 'Failed to process loan. Please try again.';
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="p-3">
                    <h4>Admin Panel</h4>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="bi bi-people"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="accounts.php">
                            <i class="bi bi-wallet2"></i> Accounts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="loans.php">
                            <i class="bi bi-bank"></i> Loans
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">
                            <i class="bi bi-cash"></i> Transactions
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
                    <h2>Loans Management</h2>
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
                                            <td>$<?php echo format_currency($loan_details['monthly_payment']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Status:</th>
                                            <td><?php echo get_status_badge($loan_details['status']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Applied:</th>
                                            <td><?php echo date('M d, Y', strtotime($loan_details['created_at'])); ?></td>
                                        </tr>
                                        <?php if ($loan_details['processed_at']): ?>
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
                                        <tr>
                                            <td><?php echo htmlspecialchars($loan['full_name']); ?></td>
                                            <td>$<?php echo format_currency($loan['amount']); ?></td>
                                            <td><?php echo $loan['interest_rate']; ?>%</td>
                                            <td><?php echo $loan['term_months']; ?> months</td>
                                            <td><?php echo get_status_badge($loan['status']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></td>
                                            <td>
                                                <a href="?view=<?php echo $loan['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-eye"></i>
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
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 