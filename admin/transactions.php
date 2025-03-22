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

// Get filter parameters
$account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : null;
$transaction_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : null;
$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : null;
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : null;

// Build query for transactions
$query = "
    SELECT t.*, u.full_name, a.account_number
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($account_id) {
    $query .= " AND t.account_id = ?";
    $params[] = $account_id;
}

if ($transaction_type) {
    $query .= " AND t.transaction_type = ?";
    $params[] = $transaction_type;
}

if ($start_date) {
    $query .= " AND DATE(t.created_at) >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $query .= " AND DATE(t.created_at) <= ?";
    $params[] = $end_date;
}

$query .= " ORDER BY t.created_at DESC";

// Get transactions
$stmt = $conn->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get all accounts for filter
$stmt = $conn->query("
    SELECT a.id, a.account_number, u.full_name 
    FROM accounts a
    JOIN users u ON a.user_id = u.id
    ORDER BY u.full_name
");
$accounts = $stmt->fetchAll();

// Get transaction types for filter
$transaction_types = ['deposit', 'withdrawal', 'transfer', 'loan'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions Management - <?php echo SITE_NAME; ?></title>
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
                        <a class="nav-link" href="loans.php">
                            <i class="bi bi-bank"></i> Loans
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_admin.php">
                            <i class="bi bi-person-plus"></i> Create Admin
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="transactions.php">
                            <i class="bi bi-cash"></i> Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person-circle"></i> My Profile
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
                    <h2>Transactions Management</h2>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="account_id" class="form-label">Account</label>
                                <select class="form-select" id="account_id" name="account_id">
                                    <option value="">All Accounts</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo $account['id']; ?>" <?php echo $account_id == $account['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($account['full_name']); ?> - <?php echo $account['account_number']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="type" class="form-label">Transaction Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Types</option>
                                    <?php foreach ($transaction_types as $type): ?>
                                        <option value="<?php echo $type; ?>" <?php echo $transaction_type === $type ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($type); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel"></i> Apply Filters
                                </button>
                                <a href="transactions.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Transactions List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Account Holder</th>
                                        <th>Account Number</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Balance After</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transactions)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No transactions found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['full_name']); ?></td>
                                            <td><?php echo $transaction['account_number']; ?></td>
                                            <td><?php echo ucfirst($transaction['transaction_type']); ?></td>
                                            <td class="<?php echo $transaction['transaction_type'] === 'deposit' ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $transaction['transaction_type'] === 'deposit' ? '+' : '-'; ?>
                                                $<?php echo format_currency($transaction['amount']); ?>
                                            </td>
                                            <td>$<?php echo format_currency($transaction['balance_after']); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 