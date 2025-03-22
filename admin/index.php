<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Get statistics
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_accounts' => $conn->query("SELECT COUNT(*) FROM accounts")->fetchColumn(),
    'pending_loans' => $conn->query("SELECT COUNT(*) FROM loans WHERE status = 'pending'")->fetchColumn(),
    'total_balance' => $conn->query("SELECT SUM(balance) FROM accounts")->fetchColumn()
];

// Get recent users
$stmt = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recent_users = $stmt->fetchAll();

// Get pending loans
$stmt = $conn->query("
    SELECT l.*, u.full_name, a.account_number 
    FROM loans l
    JOIN users u ON l.user_id = u.id
    JOIN accounts a ON l.account_id = a.id
    WHERE l.status = 'pending'
    ORDER BY l.created_at DESC
    LIMIT 5
");
$pending_loans = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
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
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,.8);
            padding: 1rem 1.5rem;
            font-size: 0.95rem;
            border-radius: 0.35rem;
            margin: 0.2rem 1rem;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255,255,255,.1);
        }
        
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,.15);
        }
        
        .sidebar .nav-link i {
            margin-right: 0.5rem;
            width: 1.5rem;
            text-align: center;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 1.5rem;
            transition: all 0.3s;
        }
        
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            transition: all 0.3s;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.15);
        }
        
        .stat-card {
            overflow: hidden;
            position: relative;
        }
        
        .stat-card.users { background: linear-gradient(45deg, #4e73df, #6f42c1); }
        .stat-card.accounts { background: linear-gradient(45deg, #1cc88a, #20c997); }
        .stat-card.loans { background: linear-gradient(45deg, #36b9cc, #0dcaf0); }
        .stat-card.balance { background: linear-gradient(45deg, #f6c23e, #fd7e14); }
        
        .stat-card .card-body {
            padding: 1.75rem;
            color: white;
            z-index: 1;
            position: relative;
        }
        
        .stat-card .stat-icon {
            font-size: 3rem;
            opacity: 0.3;
            position: absolute;
            right: 1rem;
            bottom: 1rem;
        }
        
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .stat-label {
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 1.25rem 1.5rem;
        }
        
        .card-header h5 {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            border-top: none;
            border-bottom-width: 1px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            color: var(--secondary-color);
            letter-spacing: 0.05em;
        }
        
        .table td {
            vertical-align: middle;
            color: #5a5c69;
            font-size: 0.9rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.35rem;
        }
        
        .welcome-section {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }
        
        .welcome-section .text-muted {
            color: var(--secondary-color) !important;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-auto">
                <div class="sidebar">
                    <div class="p-4">
                        <h4 class="mb-0">Admin Panel</h4>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
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
                            <a class="nav-link" href="transactions.php">
                                <i class="bi bi-cash"></i> Transactions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="bi bi-person-circle"></i> My Profile
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col">
                <div class="main-content">
                    <div class="welcome-section d-flex justify-content-between align-items-center">
                        <h2 class="mb-0">Dashboard</h2>
                        <div>
                            <span class="text-muted">
                                Welcome back, <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong>
                                <small class="ms-2 text-secondary">(ID: <?php echo $_SESSION['admin_id']; ?>)</small>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Statistics -->
                    <div class="row">
                        <div class="col-xl-3 col-md-6">
                            <div class="card stat-card users">
                                <div class="card-body">
                                    <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                                    <div class="stat-label">Total Users</div>
                                    <div class="stat-icon">
                                        <i class="bi bi-people"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6">
                            <div class="card stat-card accounts">
                                <div class="card-body">
                                    <div class="stat-value"><?php echo number_format($stats['total_accounts']); ?></div>
                                    <div class="stat-label">Total Accounts</div>
                                    <div class="stat-icon">
                                        <i class="bi bi-wallet2"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6">
                            <div class="card stat-card loans">
                                <div class="card-body">
                                    <div class="stat-value"><?php echo number_format($stats['pending_loans']); ?></div>
                                    <div class="stat-label">Pending Loans</div>
                                    <div class="stat-icon">
                                        <i class="bi bi-bank"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6">
                            <div class="card stat-card balance">
                                <div class="card-body">
                                    <div class="stat-value">$<?php echo number_format($stats['total_balance']); ?></div>
                                    <div class="stat-label">Total Balance</div>
                                    <div class="stat-icon">
                                        <i class="bi bi-cash"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Recent Users -->
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Users</h5>
                                    <a href="users.php" class="btn btn-sm btn-primary">View All</a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Joined</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_users as $user): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar bg-light rounded-circle p-2 me-2">
                                                                <i class="bi bi-person text-primary"></i>
                                                            </div>
                                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                    <td>
                                                        <a href="users.php?view=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-eye"></i> View
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pending Loans -->
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Pending Loans</h5>
                                    <a href="loans.php" class="btn btn-sm btn-primary">View All</a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Amount</th>
                                                    <th>Account</th>
                                                    <th>Applied</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_loans as $loan): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar bg-light rounded-circle p-2 me-2">
                                                                <i class="bi bi-person text-primary"></i>
                                                            </div>
                                                            <?php echo htmlspecialchars($loan['full_name']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="fw-bold text-success">$<?php echo number_format($loan['amount']); ?></td>
                                                    <td><span class="badge bg-light text-dark"><?php echo $loan['account_number']; ?></span></td>
                                                    <td><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></td>
                                                    <td>
                                                        <a href="loans.php?view=<?php echo $loan['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-eye"></i> Review
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
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