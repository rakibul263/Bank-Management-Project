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

// Only allow superadmin (username: admin) to access this page
if ($current_admin['username'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Handle admin creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!validate_password($password)) {
        $error = 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number';
    } else {
        try {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already exists';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
                
                $stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                $stmt->execute([$username, $hashed_password]);
                
                $success = 'Admin account created successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Failed to create admin account. Please try again.';
        }
    }
}

// Handle admin deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
    $admin_id = (int)$_POST['admin_id'];
    
    try {
        // Get admin info to check if it exists and is not superadmin
        $stmt = $conn->prepare("SELECT username FROM admins WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin_to_delete = $stmt->fetch();
        
        if (!$admin_to_delete) {
            $error = 'Admin account not found.';
        } elseif ($admin_to_delete['username'] === 'admin') {
            $error = 'Cannot delete superadmin account.';
        } else {
            $stmt = $conn->prepare("DELETE FROM admins WHERE id = ? AND username != 'admin'");
            $stmt->execute([$admin_id]);
            
            if ($stmt->rowCount() > 0) {
                $success = 'Admin account deleted successfully!';
            } else {
                $error = 'Failed to delete admin account.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Failed to delete admin account. Please try again.';
    }
}

// Get all admins except superadmin
$stmt = $conn->prepare("SELECT * FROM admins WHERE username != 'admin' ORDER BY created_at DESC");
$stmt->execute();
$admins = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin - <?php echo SITE_NAME; ?></title>
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
        
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            padding: 0.5rem 1rem;
            margin: 0 0.2rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .main-content {
            margin-top: 80px;
            padding: 2rem;
        }
        
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: linear-gradient(45deg, var(--primary-color), #6f42c1);
            color: white;
            padding: 1rem 1.5rem;
            border: none;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: rgba(78,115,223,0.05);
            font-weight: 600;
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
                        <a class="nav-link" href="loans.php">
                            <i class="bi bi-credit-card"></i>
                            Loans
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="create_admin.php">
                            <i class="bi bi-person-plus"></i>
                            Create Admin
                        </a>
                    </li>
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
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-person-plus"></i> Create New Admin</h5>
                    </div>
                    <div class="card-body">
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

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
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
                            
                            <button type="submit" name="create_admin" class="btn btn-primary">
                                <i class="bi bi-person-plus"></i> Create Admin
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-people"></i> Existing Admins</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($admins)): ?>
                            <p class="text-muted">No other admin accounts found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Created At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($admins as $admin): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteModal<?php echo $admin['id']; ?>">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                    
                                                    <!-- Delete Confirmation Modal -->
                                                    <div class="modal fade" id="deleteModal<?php echo $admin['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title text-dark">Confirm Delete</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body text-dark">
                                                                    Are you sure you want to delete admin account: <strong><?php echo htmlspecialchars($admin['username']); ?></strong>?
                                                                    <br><br>
                                                                    <div class="alert alert-warning">
                                                                        <i class="bi bi-exclamation-triangle"></i> This action cannot be undone!
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <form method="POST" action="">
                                                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" name="delete_admin" class="btn btn-danger">
                                                                            <i class="bi bi-trash"></i> Delete Admin
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 