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

// Define transaction limits
define('MAX_TRANSACTION_AMOUNT', 1000000.00); // 10 Lakh Taka limit per transaction

// Function to validate transaction
function validate_transaction($account_id, $amount, $type) {
    global $conn;
    
    // Get current balance
    $stmt = $conn->prepare("SELECT balance FROM accounts WHERE id = ?");
    $stmt->execute([$account_id]);
    $current_balance = $stmt->fetchColumn();
    
    // Check transaction amount limit
    if ($amount > MAX_TRANSACTION_AMOUNT) {
        return "Transaction amount exceeds the maximum limit of " . number_format(MAX_TRANSACTION_AMOUNT) . " Taka";
    }
    
    // For withdrawals, check if sufficient balance
    if ($type == 'withdrawal' && $current_balance < $amount) {
        return "Insufficient balance for withdrawal";
    }
    
    return true;
}

$error = '';
$success = '';

// Get filter parameters
$account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : null;
$transaction_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : null;
$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : null;
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : null;
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : null;

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

if ($search) {
    $query .= " AND (u.full_name LIKE ? OR a.account_number LIKE ? OR t.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
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
    <title>Transactions Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
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
        
        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Transaction Type Styles */
        .transaction-type {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            text-transform: capitalize;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .transaction-type i {
            font-size: 1rem;
        }
        
        .transaction-type.deposit {
            background: #d4edda;
            color: #155724;
        }
        
        .transaction-type.withdrawal {
            background: #f8d7da;
            color: #721c24;
        }
        
        .transaction-type.transfer {
            background: #cce5ff;
            color: #004085;
        }
        
        .transaction-type.loan {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Transaction Amount Styles */
        .transaction-amount {
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .transaction-amount i {
            font-size: 1rem;
        }
        
        .transaction-amount.positive {
            background: #d4edda;
            color: #155724;
        }
        
        .transaction-amount.negative {
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
        
        /* Search Bar Styles */
        .input-group {
            position: relative;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 0.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .input-group:focus-within {
            box-shadow: 0 4px 15px rgba(78,115,223,0.2);
            transform: translateY(-2px);
        }
        
        .input-group-text {
            border: none;
            background: linear-gradient(45deg, var(--primary-color), #6f42c1);
            color: white;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .input-group-text:hover {
            background: linear-gradient(45deg, #6f42c1, var(--primary-color));
        }
        
        .form-control {
            border: none;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            box-shadow: none;
            border-color: transparent;
        }
        
        /* Search Results Highlight */
        .highlight {
            background-color: rgba(78,115,223,0.1);
            padding: 0.2rem 0.4rem;
            border-radius: 0.25rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Include the navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-cash"></i> Transactions Management</h2>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-funnel"></i> Filter Transactions</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-12 mb-3">
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Search by account holder, account number, or description..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </div>
                    </div>
                    
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
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-list"></i> Transactions List</h5>
            </div>
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
                                    <td class="transaction-date">
                                        <?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['full_name']); ?></td>
                                    <td><?php echo $transaction['account_number']; ?></td>
                                    <td>
                                        <span class="transaction-type <?php echo $transaction['transaction_type']; ?>">
                                            <?php echo ucfirst($transaction['transaction_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="transaction-amount <?php echo $transaction['transaction_type'] === 'deposit' ? 'positive' : 'negative'; ?>">
                                            <?php echo $transaction['transaction_type'] === 'deposit' ? '+' : '-'; ?>
                                            <?php echo format_currency($transaction['amount']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_currency($transaction['balance_after']); ?></td>
                                    <td class="transaction-details">
                                        <?php echo htmlspecialchars($transaction['description']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search');
        const searchForm = document.querySelector('form');
        
        // Add debounce to search
        let debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                searchForm.submit();
            }, 500);
        });
        
        // Highlight search results
        const searchTerm = '<?php echo $search ?? ''; ?>';
        if (searchTerm) {
            const rows = document.querySelectorAll('.transaction-details');
            rows.forEach(row => {
                const text = row.textContent;
                const regex = new RegExp(searchTerm, 'gi');
                row.innerHTML = text.replace(regex, match => `<span class="highlight">${match}</span>`);
            });
        }
    });
    </script>
</body>
</html> 