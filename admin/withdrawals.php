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

// Get notification for new withdrawal requests for this admin
$stmt = $conn->prepare("
    SELECT COUNT(*) as count, MAX(created_at) as latest 
    FROM withdrawal_requests 
    WHERE admin_id = ? AND status = 'pending'
");
$stmt->execute([$_SESSION['admin_id']]);
$notification = $stmt->fetch();
$pending_requests_count = $notification['count'];
$latest_request_time = $notification['latest'] ? strtotime($notification['latest']) : 0;
$has_new_requests = $pending_requests_count > 0 && (time() - $latest_request_time < 86400); // 86400 seconds = 24 hours

// Make the pending withdrawal count available to the navbar
$pending_withdrawal_count = $pending_requests_count;

// Handle withdrawal request approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = (int)$_POST['request_id'];
    $action = sanitize_input($_POST['action']);
    
    if ($action === 'approve' || $action === 'reject') {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        
        try {
            if (process_withdrawal_request($request_id, $status, $_SESSION['admin_id'])) {
                $_SESSION['success'] = "Withdrawal request has been " . $status . " successfully!";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error = "Failed to " . $action . " the withdrawal request.";
            }
        } catch (Exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}

// Get success message from session if exists
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : null;
$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : null;
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : null;
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : null;

// Build the query for withdrawal requests
$query = "
    SELECT wr.*, u.full_name as user_name, a.account_number, adm.username as admin_username
    FROM withdrawal_requests wr
    JOIN accounts a ON wr.account_id = a.id
    JOIN users u ON a.user_id = u.id
    JOIN admins adm ON wr.admin_id = adm.id
    WHERE 1=1
";
$params = [];

// Add a filter for requests assigned to current admin
if (isset($_GET['my_requests']) && $_GET['my_requests'] === 'true') {
    $query .= " AND wr.admin_id = ?";
    $params[] = $_SESSION['admin_id'];
}

// Add status filter
if ($status_filter) {
    $query .= " AND wr.status = ?";
    $params[] = $status_filter;
}

// Add date filters
if ($start_date) {
    $query .= " AND DATE(wr.created_at) >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $query .= " AND DATE(wr.created_at) <= ?";
    $params[] = $end_date;
}

// Add search filter
if ($search) {
    $query .= " AND (u.full_name LIKE ? OR a.account_number LIKE ? OR wr.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add order by
$query .= " ORDER BY wr.created_at DESC";

// Execute query
$stmt = $conn->prepare($query);
$stmt->execute($params);
$withdrawal_requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Requests - Admin Panel</title>
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
            transition: all 0.2s;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white !important;
            background-color: rgba(255,255,255,0.1);
        }
        
        .nav-link i {
            font-size: 1.1rem;
        }
        
        /* Content Styles */
        .content-wrapper {
            margin-top: 80px;
            margin-left: 0;
            padding: 2rem;
            transition: all 0.3s;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1.25rem;
        }
        
        .card-title {
            font-weight: 600;
            margin-bottom: 0;
            color: var(--dark-color);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            margin-bottom: 1rem;
            background-color: transparent;
        }
        
        .table th {
            background-color: #f8f9fc;
            border-bottom: 2px solid #e3e6f0;
            color: #4e73df;
            font-weight: 600;
            padding: 1rem;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #e3e6f0;
            transition: all 0.3s ease;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fc;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-success {
            background: linear-gradient(45deg, #1cc88a, #17a673);
            border: none;
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #e74a3b, #be2617);
            border: none;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .filters-card {
            background: linear-gradient(135deg, #f8f9fc 0%, #e3e6f0 100%);
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #4e73df;
            margin-bottom: 0.5rem;
        }
        
        .form-select, .form-control {
            border-radius: 0.5rem;
            border: 1px solid #e3e6f0;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78,115,223,0.25);
            transform: translateY(-2px);
        }
        
        .search-container {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .search-input {
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border-radius: 0.5rem;
            border: 1px solid #e3e6f0;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .search-input:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78,115,223,0.25);
            transform: translateY(-2px);
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            transition: all 0.3s ease;
        }
        
        .search-input:focus + .search-icon {
            color: #4e73df;
            transform: translateY(-50%) scale(1.1);
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
            font-weight: 500;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .status-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .alert {
            border: none;
            border-radius: 0.5rem;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .alert-info {
            background: linear-gradient(45deg, #36b9cc, #2c9faf);
            color: white;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        tr[data-status="pending"] {
            animation: pulse 2s infinite;
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                border-radius: 0.5rem;
                box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .search-container {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    
    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">Withdrawal Requests</h1>
                <!-- Dashboard link commented out
                <a href="index.php" class="btn btn-sm btn-secondary"><i class="bi bi-house-door"></i> Dashboard</a>
                -->
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filters Card -->
            <div class="card filters-card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="my_requests" name="my_requests" value="true" <?php echo isset($_GET['my_requests']) && $_GET['my_requests'] === 'true' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="my_requests">
                                    Show only my requests
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="search-container">
                                <i class="bi bi-search search-icon"></i>
                                <input type="text" class="form-control search-input" name="search" placeholder="Search by name, account number, or description..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-filter"></i> Apply Filters</button>
                            <a href="withdrawals.php" class="btn btn-outline-secondary ms-2"><i class="bi bi-x-circle"></i> Clear</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Withdrawal Requests Table -->
            <div class="card" id="pending-withdrawals">
                <div class="card-header">
                    <h5 class="card-title"><i class="bi bi-cash me-2"></i>Withdrawal Requests</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($withdrawal_requests)): ?>
                        <div class="alert alert-info" role="alert">
                            No withdrawal requests found matching the criteria.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>User</th>
                                        <th>Account</th>
                                        <th>Amount</th>
                                        <th>Assigned Admin</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($withdrawal_requests as $request): ?>
                                        <tr data-status="<?php echo $request['status']; ?>" data-admin-id="<?php echo $request['admin_id']; ?>">
                                            <td>#<?php echo $request['id']; ?></td>
                                            <td><?php echo date('d M Y H:i', strtotime($request['created_at'])); ?></td>
                                            <td>
                                                <?php 
                                                if ($search) {
                                                    $user_name = $request['user_name'] ?? '';
                                                    echo preg_replace("/($search)/i", '<span class="highlight">$1</span>', htmlspecialchars($user_name));
                                                } else {
                                                    echo htmlspecialchars($request['user_name'] ?? '');
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($search) {
                                                    $account_number = $request['account_number'] ?? '';
                                                    echo preg_replace("/($search)/i", '<span class="highlight">$1</span>', htmlspecialchars($account_number));
                                                } else {
                                                    echo htmlspecialchars($request['account_number'] ?? '');
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo format_currency($request['amount']); ?></td>
                                            <td><?php echo htmlspecialchars($request['admin_username']); ?>
                                                <?php if ($request['admin_id'] == $_SESSION['admin_id']): ?>
                                                    <span class="badge bg-info">You</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo get_status_badge($request['status']); ?></td>
                                            <td>
                                                <?php if ($request['status'] === 'pending' && $request['admin_id'] == $_SESSION['admin_id']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to approve this withdrawal?')">
                                                            <i class="bi bi-check-circle"></i> Approve
                                                        </button>
                                                        <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger ms-1" onclick="return confirm('Are you sure you want to reject this withdrawal?')">
                                                            <i class="bi bi-x-circle"></i> Reject
                                                        </button>
                                                    </form>
                                                <?php elseif ($request['status'] !== 'pending'): ?>
                                                    <span class="text-muted">
                                                        <?php echo ($request['processed_at']) ? 'Processed on ' . date('d M Y H:i', strtotime($request['processed_at'])) : 'No action needed'; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Awaiting approval by assigned admin</span>
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
        </div>
    </div>
    
    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Notification Sound and JavaScript -->
    <?php if ($has_new_requests): ?>
    <script>
        // Check if this is the first time we're showing the notification (using sessionStorage)
        if (!sessionStorage.getItem('withdrawal_notification_shown_<?php echo date("Y-m-d"); ?>')) {
            // Play notification sound - create a subtle notification sound
            const notificationSound = new Audio('data:audio/wav;base64,UklGRiQDAABXQVZFZm10IBAAAAABAAEAESsAABErAAABAAgAZGF0YQADAACBhYqFbF1fZHB3EhgICRAX2dTj6e4FBwwQEAwKCggIBQcIBgUICgYFFw4HoZBuRDo0NT1Wg8aZdXJ6f5KUnJ6Vl56hi2piVEg+Pj1APDh+cVEzHxYaJEF9lmNKR05aZWlbV1pmdINfUVJUU05LSEFAQG1PIxsRDhckVY9qQDk9SVZSTUFBUmZyW1RYXmBdVk1JSkxRTDkpHxwkM1d9aTgzPlRfa2VaXGVwdGlna3F1b2NVTkxEODIzSWxZPDAnKDhLeJ2Be2UzIhseQWp/ZUw9QFBea1M8Oj1BWl1MRkpYX1pQQ0ZFTEU9ODrQvoVZTkhDPz9EZI2HbVtPTFBaY2phUkVAPEBGSEE3RlRXTD84Njs8NjY5bVwwHRQQGCtQbWJXU1deZGZbRzs5QE1cXlJMTlZZUEQ5Njg6NC80Tos1DwkHDBxCU0dBPz9JV2tVPTc4RE5dXFNMUltgVkIzMDA0L0hlVDceFhYeKDY2MjEzOUVWXVJEPT5GUFxhU0pOW2JcSz0xMDIxaFMlCwQECRY1TUU5MzU9TFtmWEc/QEhWYGBTRElWYF1RQDUyMjNAPDMwe2E6IRcWGiU2UldXU1ZebXaRkHNeXXCEf2xobnuKhHBmZ25pX2NmaAkHBwwRGzlPV1peZnWRp7qbVEAxNUBIRj46PkJGTVBXUEIvGwVLcmlSSDo6RFFQTFRqgIuOf3dvT0xGNkkZF0ZALURcbHuEdnl/gHprYl5ZOSsqtK6TeX6JoaSflZmTcGiZkZZyWF1JMSwpJzxHGRM5HSI+aYc8FiMcBCc/Mjk8R15xRiwrO3B7TDg5LnJaJDAyKR00UlhZNjM1Sk0jEBEePWdeZ1slLVDh3aVaTExHRlJcd4lpPCgzYX0xFSNFNB0VIy0xVV1JRnFdLiUoIiUcGzE8bTA=');
            notificationSound.volume = 0.3; // Set volume to 30%
            notificationSound.play().catch(e => console.log('Audio play failed:', e));
            
            // Mark as shown
            sessionStorage.setItem('withdrawal_notification_shown_<?php echo date("Y-m-d"); ?>', 'true');
            
            // Animate the navbar notification badge
            const navWithdrawalBadge = document.querySelector('.nav-link[href="withdrawals.php"] .badge-notification');
            if (navWithdrawalBadge) {
                navWithdrawalBadge.classList.add('pulse-animation');
            }
        }
        
        // Highlight pending withdrawals that need attention
        document.addEventListener('DOMContentLoaded', () => {
            const pendingRows = document.querySelectorAll('tr[data-status="pending"][data-admin-id="<?php echo $_SESSION['admin_id']; ?>"]');
            pendingRows.forEach(row => {
                row.classList.add('table-warning');
                row.classList.add('animate__animated');
                row.classList.add('animate__pulse');
            });
            
            // Add scroll into view for pending requests if coming from navbar
            if (window.location.hash === '#pending-withdrawals') {
                document.getElementById('pending-withdrawals').scrollIntoView();
            }
        });
    </script>
    
    <!-- Add styles for navbar notification badge animation -->
    <style>
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .pulse-animation {
            animation: pulse 1s infinite;
            box-shadow: 0 0 10px rgba(255, 165, 0, 0.7);
        }
    </style>
    
    <!-- Add animate.css for subtle animation effects -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <?php endif; ?>
    
    <script>
        // Enhanced real-time search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');
            const form = document.querySelector('form');
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    form.submit();
                }, 500);
            });
            
            // Add smooth scrolling to table
            const table = document.querySelector('.table');
            if (table) {
                table.addEventListener('scroll', function() {
                    const scrollTop = table.scrollTop;
                    const scrollHeight = table.scrollHeight;
                    const clientHeight = table.clientHeight;
                    
                    if (scrollTop + clientHeight >= scrollHeight - 20) {
                        // Add loading animation or fetch more data here
                    }
                });
            }
            
            // Add hover effect to table rows
            const tableRows = document.querySelectorAll('.table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.05)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            });
        });
    </script>
</body>
</html> 