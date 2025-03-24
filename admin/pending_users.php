<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Get pending users count for badge
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE is_approved = 'pending'");
$stmt->execute();
$pending_user_count = $stmt->fetchColumn();

// Process approval or rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve' || $action === 'reject') {
        try {
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            
            // Start transaction
            $conn->beginTransaction();
            
            if ($action === 'approve') {
                // Update user status to approved
                $stmt = $conn->prepare("UPDATE users SET is_approved = 'approved' WHERE id = ?");
                $stmt->execute([$user_id]);
                
                // Get user info for notification
                $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                // Add admin activity log
                $message = "User account for {$user['full_name']} ({$user['email']}) has been approved";
                $stmt = $conn->prepare("INSERT INTO admin_notifications (type, message, user_id, is_read) VALUES (?, ?, ?, 1)");
                $stmt->execute(["approve_user", $message, $user_id]);
                
                $success = "User {$user['full_name']} has been successfully approved";
            } else {
                // Get user info for log before deleting
                $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                // Delete related data first (to avoid foreign key constraints)
                
                // Check if admin_notifications table exists and has user_id column
                $admin_notifications_exists = false;
                try {
                    $stmt = $conn->prepare("SHOW TABLES LIKE 'admin_notifications'");
                    $stmt->execute();
                    if ($stmt->rowCount() > 0) {
                        $stmt = $conn->prepare("DESCRIBE admin_notifications");
                        $stmt->execute();
                        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        $admin_notifications_exists = in_array('user_id', $columns);
                    }
                } catch (PDOException $e) {
                    // Table doesn't exist or there was an error
                }
                
                // Delete any notifications related to the user if the table exists
                if ($admin_notifications_exists) {
                    $stmt = $conn->prepare("DELETE FROM admin_notifications WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                }
                
                // Delete any withdrawal requests (though likely none for new user)
                $stmt = $conn->prepare("DELETE FROM withdrawal_requests WHERE account_id IN (SELECT id FROM accounts WHERE user_id = ?)");
                $stmt->execute([$user_id]);
                
                // Delete transactions (though likely none for new user)
                $stmt = $conn->prepare("DELETE FROM transactions WHERE account_id IN (SELECT id FROM accounts WHERE user_id = ?)");
                $stmt->execute([$user_id]);
                
                // Delete transfers (though likely none for new user)
                $stmt = $conn->prepare("DELETE FROM transfers WHERE from_account_id IN (SELECT id FROM accounts WHERE user_id = ?) OR to_account_id IN (SELECT id FROM accounts WHERE user_id = ?)");
                $stmt->execute([$user_id, $user_id]);
                
                // Delete loans (though likely none for new user)
                $stmt = $conn->prepare("DELETE FROM loans WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Delete accounts (though likely none for new user)
                $stmt = $conn->prepare("DELETE FROM accounts WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Finally, delete the user
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                // Add admin activity log (without user_id since user is deleted)
                $message = "User registration for {$user['full_name']} ({$user['email']}) has been rejected and removed from the system";
                $stmt = $conn->prepare("INSERT INTO admin_notifications (type, message, is_read) VALUES (?, ?, 1)");
                $stmt->execute(["reject_user", $message]);
                
                $success = "User {$user['full_name']} has been rejected and removed from the system";
            }
            
            // Commit transaction
            $conn->commit();
            
        } catch (PDOException $e) {
            // Rollback on error
            $conn->rollBack();
            $error = "Error processing request: " . $e->getMessage();
        }
    }
}

// Get all pending users
$stmt = $conn->prepare("
    SELECT id, username, email, full_name, phone, created_at 
    FROM users 
    WHERE is_approved = 'pending'
    ORDER BY created_at DESC
");
$stmt->execute();
$pending_users = $stmt->fetchAll();

// Get admin info
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$current_admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Registrations - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4A6EF5;
            --primary-light: #7A92FF;
            --primary-dark: #3A5AD9;
            --danger-color: #FF4C4C;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --secondary-color: #6c757d;
            --dark-bg: #1E2A38;
            --card-bg: #fff;
            --card-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 90px;
            color: #495057;
        }
        
        .content-wrapper {
            padding: 30px;
        }
        
        .page-header {
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding-bottom: 15px;
        }
        
        .page-header h2 {
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #2C3E50;
        }
        
        .page-header h2 i {
            font-size: 1.8rem;
            color: var(--primary-color);
        }
        
        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            border: none;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-left: 4px solid var(--success-color);
            color: #146c43;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-left: 4px solid var(--danger-color);
            color: #b02a37;
        }
        
        .user-card {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }
        
        .user-card .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .user-card .card-header h5 {
            margin: 0;
            font-weight: 600;
            color: #343a40;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-card .card-header small {
            color: var(--secondary-color);
            font-weight: normal;
        }
        
        .user-card-body {
            padding: 20px 25px;
        }
        
        .user-info {
            margin-bottom: 15px;
        }
        
        .user-info p {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-info p i {
            color: var(--primary-color);
            font-size: 1.1rem;
            width: 22px;
        }
        
        .user-info .timestamp {
            font-size: 0.9rem;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-approval {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            flex: 1;
            transition: all 0.3s ease;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-approval:hover {
            transform: translateY(-2px);
        }
        
        .btn-approve {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .btn-approve:hover {
            background-color: var(--success-color);
            color: white;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .btn-reject {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .btn-reject:hover {
            background-color: var(--danger-color);
            color: white;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        
        .no-pending-users {
            text-align: center;
            padding: 60px 0;
            color: var(--secondary-color);
        }
        
        .no-pending-users i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 15px;
        }
        
        .no-pending-users h4 {
            margin-bottom: 10px;
            font-weight: 600;
            color: #343a40;
        }
        
        .modal-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-title i {
            color: var(--primary-color);
        }
        
        /* Badge styles */
        .badge-pending {
            background-color: var(--warning-color);
            color: #212529;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <!-- Include Admin Navbar -->
    <?php include 'navbar.php'; ?>
    
    <div class="container content-wrapper">
        <div class="page-header d-flex justify-content-between align-items-center">
            <h2>
                <i class="bi bi-person-check-fill"></i>
                Pending Registrations
                <?php if($pending_user_count > 0): ?>
                <span class="badge-pending">
                    <?php echo $pending_user_count; ?> Pending
                </span>
                <?php endif; ?>
            </h2>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if(count($pending_users) > 0): ?>
            <div class="row">
                <?php foreach($pending_users as $user): ?>
                    <div class="col-lg-6">
                        <div class="user-card">
                            <div class="card-header">
                                <h5>
                                    <i class="bi bi-person-fill"></i>
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                    <small class="ms-auto">#<?php echo $user['id']; ?></small>
                                </h5>
                            </div>
                            <div class="user-card-body">
                                <div class="user-info">
                                    <p>
                                        <i class="bi bi-person-badge"></i>
                                        <strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?>
                                    </p>
                                    <p>
                                        <i class="bi bi-envelope"></i>
                                        <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
                                    </p>
                                    <p>
                                        <i class="bi bi-telephone"></i>
                                        <strong>Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?>
                                    </p>
                                    <p class="timestamp">
                                        <i class="bi bi-clock"></i>
                                        <span>Registered on <?php echo date('M d, Y \a\t h:i A', strtotime($user['created_at'])); ?></span>
                                    </p>
                                </div>
                                <div class="user-actions">
                                    <form method="post" class="d-inline flex-grow-1">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-approval btn-approve">
                                            <i class="bi bi-check-circle"></i> Approve
                                        </button>
                                    </form>
                                    <button type="button" class="btn-approval btn-reject" data-bs-toggle="modal" data-bs-target="#rejectUserModal" data-userid="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['full_name']); ?>">
                                        <i class="bi bi-x-circle"></i> Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-pending-users">
                <i class="bi bi-check2-all"></i>
                <h4>No Pending Registrations</h4>
                <p>All user registrations have been processed.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Reject User Confirmation Modal -->
    <div class="modal fade" id="rejectUserModal" tabindex="-1" aria-labelledby="rejectUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="rejectUserModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Reject User Registration
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to reject the registration for <strong id="rejectUserName"></strong>?</p>
                    
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Warning:</strong> This action will permanently delete this user from the database.
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong>Note:</strong> All user data will be permanently removed, including:
                        <ul class="mb-0 mt-2">
                            <li>User account information</li>
                            <li>Any banking accounts (if created)</li>
                            <li>Any transactions (if any)</li>
                            <li>Any loan applications (if any)</li>
                            <li>Any other related records</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="">
                        <input type="hidden" name="user_id" id="rejectUserId" value="">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash-fill me-2"></i>Reject and Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Setup for Reject User Modal
        document.addEventListener('DOMContentLoaded', function() {
            const rejectUserModal = document.getElementById('rejectUserModal');
            if (rejectUserModal) {
                rejectUserModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const userId = button.getAttribute('data-userid');
                    const userName = button.getAttribute('data-username');
                    
                    document.getElementById('rejectUserId').value = userId;
                    document.getElementById('rejectUserName').textContent = userName;
                });
            }
        });
    </script>
</body>
</html> 