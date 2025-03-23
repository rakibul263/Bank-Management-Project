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

// Get statistics
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_accounts' => $conn->query("SELECT COUNT(*) FROM accounts")->fetchColumn(),
    'pending_loans' => $conn->query("SELECT COUNT(*) FROM loans WHERE status = 'pending'")->fetchColumn(),
    'total_balance' => $conn->query("SELECT COALESCE(SUM(CASE WHEN balance IS NULL THEN 0 ELSE balance END), 0) as total FROM accounts")->fetchColumn(),
    'total_loans' => $conn->query("SELECT COALESCE(SUM(amount), 0) FROM loans WHERE status = 'approved'")->fetchColumn()
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

        /* Add these styles to your existing CSS */
        .refresh-btn {
            padding: 10px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            background: linear-gradient(45deg, var(--primary-color), #6f42c1);
            border: none;
            font-weight: 500;
        }

        .refresh-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(78,115,223,0.3);
        }

        .refresh-btn i {
            font-size: 1.1rem;
            transition: transform 0.5s ease;
        }

        .refresh-btn:active i {
            transform: rotate(180deg);
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .refresh-btn.loading i {
            animation: spin 1s linear infinite;
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
                        <a class="nav-link active" href="index.php">
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
                        <a class="nav-link" href="loans.php">
                            <i class="bi bi-credit-card"></i>
                            Loans
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
            <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
            <button onclick="window.location.reload()" class="btn btn-primary refresh-btn">
                <i class="bi bi-arrow-clockwise"></i>
                Refresh Dashboard
            </button>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row">
            <!-- Total Users Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_users']); ?></div>
                            </div>
                            <div class="text-primary"><i class="bi bi-people fa-2x"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Accounts Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Accounts</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_accounts']); ?></div>
                            </div>
                            <div class="text-success"><i class="bi bi-bank fa-2x"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Balance Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Balance</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['total_balance'], 2); ?></div>
                            </div>
                            <div class="text-info"><i class="bi bi-cash-stack fa-2x"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Loans Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Loans</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['total_loans'], 2); ?></div>
                                <div class="text-xs text-muted"><?php echo $stats['pending_loans']; ?> pending</div>
                            </div>
                            <div class="text-warning"><i class="bi bi-credit-card fa-2x"></i></div>
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
                        <h5 class="card-title mb-0"><i class="bi bi-people"></i> Recent Users</h5>
                        <a href="users.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
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
                        <h5 class="card-title mb-0"><i class="bi bi-credit-card"></i> Pending Loans</h5>
                        <a href="loans.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Add this to your existing JavaScript
    document.querySelector('.refresh-btn').addEventListener('click', function() {
        this.classList.add('loading');
    });
    </script>
</body>
</html> 