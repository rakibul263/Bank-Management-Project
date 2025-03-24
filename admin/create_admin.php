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

// Define available admin roles and their descriptions
$admin_roles = [
    'super_admin' => 'Full access to all system features',
    'user_manager' => 'Manage users, approve registrations, and handle user accounts',
    'account_manager' => 'Manage bank accounts and withdrawal requests',
    'loan_manager' => 'Manage loan applications and loan approvals',
    'transaction_manager' => 'View and manage transactions and transfers'
];

$error = '';
$success = '';

// Handle admin creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize_input($_POST['role']);
    
    if (empty($username) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!validate_password($password)) {
        $error = 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number';
    } elseif (!array_key_exists($role, $admin_roles)) {
        $error = 'Invalid role selected';
    } else {
        try {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already exists';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
                
                // Check if the role column exists in the admins table
                $role_column_exists = false;
                try {
                    $stmt = $conn->prepare("DESCRIBE admins");
                    $stmt->execute();
                    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $role_column_exists = in_array('role', $columns);
                } catch (PDOException $e) {
                    // Error checking table structure
                }
                
                // Add role column if it doesn't exist
                if (!$role_column_exists) {
                    try {
                        $conn->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(50) DEFAULT 'user_manager'");
                        $role_column_exists = true;
                    } catch (PDOException $e) {
                        $error = 'Failed to update database structure. Please contact the system administrator.';
                    }
                }
                
                if ($role_column_exists) {
                    $stmt = $conn->prepare("INSERT INTO admins (username, password, role) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $role]);
                    
                    $success = 'Admin account created successfully!';
                } else {
                    $stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                    $stmt->execute([$username, $hashed_password]);
                    
                    $success = 'Admin account created successfully (without role assignment due to database limitation)!';
                }
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

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $admin_id = (int)$_POST['admin_id'];
    $new_role = sanitize_input($_POST['role']);
    
    if (empty($new_role) || !array_key_exists($new_role, $admin_roles)) {
        $error = 'Invalid role selected';
    } else {
        try {
            // Check if admin exists and is not superadmin
            $stmt = $conn->prepare("SELECT username FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $admin_to_update = $stmt->fetch();
            
            if (!$admin_to_update) {
                $error = 'Admin account not found.';
            } elseif ($admin_to_update['username'] === 'admin') {
                $error = 'Cannot change role for superadmin account.';
            } else {
                $stmt = $conn->prepare("UPDATE admins SET role = ? WHERE id = ? AND username != 'admin'");
                $stmt->execute([$new_role, $admin_id]);
                
                if ($stmt->rowCount() > 0) {
                    $success = 'Admin role updated successfully!';
                    
                    // Refresh admin list after update
                    $stmt = $conn->prepare("SELECT * FROM admins WHERE username != 'admin' ORDER BY created_at DESC");
                    $stmt->execute();
                    $admins = $stmt->fetchAll();
                } else {
                    $error = 'Failed to update admin role.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Failed to update admin role. Please try again.';
        }
    }
}

// Get all admins except superadmin
$stmt = $conn->prepare("SELECT * FROM admins WHERE username != 'admin' ORDER BY created_at DESC");
$stmt->execute();
$admins = $stmt->fetchAll();

// Check if role column exists
$role_column_exists = false;
try {
    $stmt = $conn->prepare("DESCRIBE admins");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $role_column_exists = in_array('role', $columns);
} catch (PDOException $e) {
    // Error checking table structure
}

// If role column doesn't exist, try to add it
if (!$role_column_exists) {
    try {
        $conn->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(50) DEFAULT 'user_manager'");
        $role_column_exists = true;
        
        // Set default role for existing admins
        $conn->exec("UPDATE admins SET role = 'super_admin' WHERE username = 'admin'");
        $conn->exec("UPDATE admins SET role = 'user_manager' WHERE username != 'admin'");
        
        $success = 'Database updated with role management. All existing admins have been assigned default roles.';
        
        // Refresh admins list
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username != 'admin' ORDER BY created_at DESC");
        $stmt->execute();
        $admins = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Failed to update database structure
    }
}
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
    <!-- Include the navbar -->
    <?php include 'navbar.php'; ?>

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
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">Admin Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="" selected disabled>Select a role</option>
                                    <?php foreach ($admin_roles as $role_id => $role_desc): ?>
                                        <?php if ($role_id !== 'super_admin'): ?>
                                            <option value="<?php echo $role_id; ?>"><?php echo ucwords(str_replace('_', ' ', $role_id)); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    <i class="bi bi-info-circle"></i> The selected role determines what actions this admin can perform.
                                </div>
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
                <div class="card mb-4">
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
                                            <?php if ($role_column_exists): ?>
                                                <th>Role</th>
                                            <?php endif; ?>
                                            <th>Created At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($admins as $admin): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                                <?php if ($role_column_exists): ?>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?php echo isset($admin['role']) ? ucwords(str_replace('_', ' ', $admin['role'])) : 'User Manager'; ?>
                                                        </span>
                                                    </td>
                                                <?php endif; ?>
                                                <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($role_column_exists): ?>
                                                        <button type="button" class="btn btn-primary btn-sm"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editRoleModal<?php echo $admin['id']; ?>">
                                                            <i class="bi bi-pencil"></i> Edit Role
                                                        </button>
                                                    <?php endif; ?>
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
                                                    
                                                    <?php if ($role_column_exists): ?>
                                                    <!-- Edit Role Modal -->
                                                    <div class="modal fade" id="editRoleModal<?php echo $admin['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title text-dark">Edit Admin Role</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST" action="">
                                                                    <div class="modal-body text-dark">
                                                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                                        
                                                                        <p>Update role for admin: <strong><?php echo htmlspecialchars($admin['username']); ?></strong></p>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label for="edit_role_<?php echo $admin['id']; ?>" class="form-label">Select Role</label>
                                                                            <select class="form-select" id="edit_role_<?php echo $admin['id']; ?>" name="role">
                                                                                <?php foreach ($admin_roles as $role_id => $role_desc): ?>
                                                                                    <?php if ($role_id !== 'super_admin'): ?>
                                                                                        <option value="<?php echo $role_id; ?>" <?php echo (isset($admin['role']) && $admin['role'] === $role_id) ? 'selected' : ''; ?>>
                                                                                            <?php echo ucwords(str_replace('_', ' ', $role_id)); ?>
                                                                                        </option>
                                                                                    <?php endif; ?>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="alert alert-info">
                                                                            <h6 class="alert-heading mb-2"><i class="bi bi-info-circle"></i> Role Details</h6>
                                                                            <div id="role_details_<?php echo $admin['id']; ?>">
                                                                                <!-- Role details will be populated by JavaScript -->
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" name="update_role" class="btn btn-primary">
                                                                            <i class="bi bi-save"></i> Update Role
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($role_column_exists): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-shield-lock"></i> Admin Role Permissions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Role</th>
                                        <th>Description</th>
                                        <th>Permissions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admin_roles as $role_id => $role_desc): ?>
                                        <?php if ($role_id !== 'super_admin' || $current_admin['username'] === 'admin'): ?>
                                            <tr>
                                                <td><strong><?php echo ucwords(str_replace('_', ' ', $role_id)); ?></strong></td>
                                                <td><?php echo $role_desc; ?></td>
                                                <td>
                                                    <?php if ($role_id === 'super_admin'): ?>
                                                        <span class="badge bg-danger">All Permissions</span>
                                                    <?php elseif ($role_id === 'user_manager'): ?>
                                                        <span class="badge bg-primary">Manage Users</span>
                                                        <span class="badge bg-primary">Approve Registrations</span>
                                                    <?php elseif ($role_id === 'account_manager'): ?>
                                                        <span class="badge bg-primary">Manage Accounts</span>
                                                        <span class="badge bg-primary">Approve Withdrawals</span>
                                                    <?php elseif ($role_id === 'loan_manager'): ?>
                                                        <span class="badge bg-primary">Manage Loans</span>
                                                        <span class="badge bg-primary">Approve/Reject Loans</span>
                                                    <?php elseif ($role_id === 'transaction_manager'): ?>
                                                        <span class="badge bg-primary">View Transactions</span>
                                                        <span class="badge bg-primary">Manage Transfers</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
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
    <?php if ($role_column_exists): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Role description data
            const roleDescriptions = {
                'user_manager': 'Manage users, approve registrations, and handle user accounts',
                'account_manager': 'Manage bank accounts and withdrawal requests',
                'loan_manager': 'Manage loan applications and loan approvals',
                'transaction_manager': 'View and manage transactions and transfers'
            };
            
            // Update role details in edit modals
            <?php foreach ($admins as $admin): ?>
            const roleSelect_<?php echo $admin['id']; ?> = document.getElementById('edit_role_<?php echo $admin['id']; ?>');
            const roleDetails_<?php echo $admin['id']; ?> = document.getElementById('role_details_<?php echo $admin['id']; ?>');
            
            if (roleSelect_<?php echo $admin['id']; ?> && roleDetails_<?php echo $admin['id']; ?>) {
                // Set initial role description
                roleDetails_<?php echo $admin['id']; ?>.textContent = roleDescriptions[roleSelect_<?php echo $admin['id']; ?>.value] || '';
                
                // Update on change
                roleSelect_<?php echo $admin['id']; ?>.addEventListener('change', function() {
                    roleDetails_<?php echo $admin['id']; ?>.textContent = roleDescriptions[this.value] || '';
                });
            }
            <?php endforeach; ?>
            
            // Also for new admin creation
            const roleSelect = document.getElementById('role');
            if (roleSelect) {
                roleSelect.addEventListener('change', function() {
                    const roleInfo = document.querySelector('.form-text');
                    if (roleInfo) {
                        roleInfo.innerHTML = '<i class="bi bi-info-circle"></i> ' + 
                            (roleDescriptions[this.value] || 'The selected role determines what actions this admin can perform.');
                    }
                });
            }
        });
    </script>
    <?php endif; ?>
</body>
</html> 