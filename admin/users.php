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

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $full_name = sanitize_input($_POST['full_name']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($phone)) {
        $error = 'Please fill in all required fields';
    } elseif (!validate_email($email)) {
        $error = 'Invalid email format';
    } elseif (!validate_password($password)) {
        $error = 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number';
    } else {
        try {
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or email already exists';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
                
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, address) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $address]);
                
                $success = 'User created successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Failed to create user. Please try again.';
        }
    }
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    try {
        // Check if user has any accounts with balance
        $stmt = $conn->prepare("SELECT COUNT(*) FROM accounts WHERE user_id = ? AND balance > 0");
        $stmt->execute([$user_id]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Cannot delete user with active accounts containing balance';
        } else {
            // Delete user's accounts first
            $stmt = $conn->prepare("DELETE FROM accounts WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Delete user's loans
            $stmt = $conn->prepare("DELETE FROM loans WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $success = 'User deleted successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Failed to delete user. Please try again.';
    }
}

// Get user details if viewing specific user
$user_details = null;
if (isset($_GET['view'])) {
    $user_id = (int)$_GET['view'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_details = $stmt->fetch();
    
    if ($user_details) {
        // Get user's accounts
        $stmt = $conn->prepare("SELECT * FROM accounts WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_accounts = $stmt->fetchAll();
        
        // Get user's loans
        $stmt = $conn->prepare("SELECT * FROM loans WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_loans = $stmt->fetchAll();
    }
}

// Get all users for the list
$stmt = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Admin Panel</title>
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
        
        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.suspended {
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
                        <a class="nav-link active" href="users.php">
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
            <h2><i class="bi bi-people"></i> Users Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-plus-lg"></i>
                Add New User
            </button>
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
        
        <?php if ($user_details): ?>
            <!-- User Details -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-person-circle"></i> User Details</h5>
                    <a href="users.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3"><i class="bi bi-person"></i> Personal Information</h6>
                            <table class="table">
                                <tr>
                                    <th>Username:</th>
                                    <td><?php echo htmlspecialchars($user_details['username']); ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($user_details['email']); ?></td>
                                </tr>
                                <tr>
                                    <th>Full Name:</th>
                                    <td><?php echo htmlspecialchars($user_details['full_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td><?php echo htmlspecialchars($user_details['phone']); ?></td>
                                </tr>
                                <tr>
                                    <th>Address:</th>
                                    <td><?php echo htmlspecialchars($user_details['address']); ?></td>
                                </tr>
                                <tr>
                                    <th>Joined:</th>
                                    <td><?php echo date('M d, Y', strtotime($user_details['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="mb-3"><i class="bi bi-bank"></i> Accounts</h6>
                            <?php if (empty($user_accounts)): ?>
                                <p class="text-muted">No accounts found</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Account Number</th>
                                                <th>Type</th>
                                                <th>Balance</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($user_accounts as $account): ?>
                                            <tr>
                                                <td><?php echo $account['account_number']; ?></td>
                                                <td><?php echo ucfirst($account['account_type']); ?></td>
                                                <td>$<?php echo format_currency($account['balance']); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $account['status']; ?>">
                                                        <?php echo ucfirst($account['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6 class="mb-3"><i class="bi bi-credit-card"></i> Loan History</h6>
                        <?php if (empty($user_loans)): ?>
                            <p class="text-muted">No loans found</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Amount</th>
                                            <th>Interest Rate</th>
                                            <th>Term</th>
                                            <th>Status</th>
                                            <th>Applied</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($user_loans as $loan): ?>
                                        <tr>
                                            <td>$<?php echo format_currency($loan['amount']); ?></td>
                                            <td><?php echo $loan['interest_rate']; ?>%</td>
                                            <td><?php echo $loan['term_months']; ?> months</td>
                                            <td>
                                                <span class="status-badge <?php echo $loan['status']; ?>">
                                                    <?php echo ucfirst($loan['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Users List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-list"></i> Users List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <a href="?view=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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
    
    <!-- Create User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"></textarea>
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_user" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i> Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 