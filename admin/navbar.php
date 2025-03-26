<?php
// Get current page
$current_page = basename($_SERVER['SCRIPT_NAME']);

// Get current admin info if not already set
if (!isset($current_admin) && isset($_SESSION['admin_id'])) {
    $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $current_admin = $stmt->fetch();
}

// Check if role column exists in admins table
$role_column_exists = false;
try {
    $stmt = $conn->prepare("SHOW COLUMNS FROM admins LIKE 'role'");
    $stmt->execute();
    $role_column_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    // Column doesn't exist or there was an error
}

// Define admin role permissions
$admin_permissions = [
    'super_admin' => ['all'],
    'user_manager' => ['users.php', 'pending_users.php'],
    'account_manager' => ['accounts.php', 'withdrawals.php'],
    'loan_manager' => ['loans.php'],
    'transaction_manager' => ['transactions.php']
];

// Get admin role
$admin_role = 'user_manager'; // Default role
if ($role_column_exists && isset($current_admin['role'])) {
    $admin_role = $current_admin['role'];
} elseif (isset($current_admin) && $current_admin['username'] === 'admin') {
    $admin_role = 'super_admin';
}

// Function to check if admin has permission to access a page
function has_permission($page, $role, $permissions) {
    if ($role === 'super_admin' || in_array('all', $permissions[$role])) {
        return true;
    }
    return in_array($page, $permissions[$role]);
}

// Check if is_approved column exists in users table
$column_exists = false;
try {
    $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'is_approved'");
    $stmt->execute();
    $column_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    // Column doesn't exist or there was an error
}

// Get pending users count if the column exists
if (!isset($pending_user_count)) {
    if ($column_exists) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE is_approved = 'pending'");
        $stmt->execute();
        $pending_user_count = $stmt->fetchColumn();
    } else {
        $pending_user_count = 0;
    }
}
?>
<!-- Sleek Modern Navbar -->
<nav class="admin-navbar navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-bank"></i>
            <span>Admin Panel</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <i class="bi bi-list"></i>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="index.php">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <?php if (has_permission('users.php', $admin_role, $admin_permissions)): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" href="users.php">
                        <i class="bi bi-people-fill"></i>
                        <span>Users</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if ($column_exists && file_exists(__DIR__ . '/pending_users.php') && has_permission('pending_users.php', $admin_role, $admin_permissions)): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'pending_users.php') ? 'active' : ''; ?>" href="pending_users.php">
                        <i class="bi bi-person-check-fill"></i>
                        <span>Pending Users</span>
                        <?php if (isset($pending_user_count) && $pending_user_count > 0): ?>
                        <span class="badge-notification">
                            <?php echo $pending_user_count; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (has_permission('accounts.php', $admin_role, $admin_permissions)): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'accounts.php') ? 'active' : ''; ?>" href="accounts.php">
                        <i class="bi bi-safe2-fill"></i>
                        <span>Accounts</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (has_permission('transactions.php', $admin_role, $admin_permissions)): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'transactions.php') ? 'active' : ''; ?>" href="transactions.php">
                        <i class="bi bi-arrow-left-right"></i>
                        <span>Transactions</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (has_permission('loans.php', $admin_role, $admin_permissions)): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'loans.php') ? 'active' : ''; ?>" href="loans.php">
                        <i class="bi bi-cash-coin"></i>
                        <span>Loans</span>
                        <?php if (isset($pending_loan_count) && $pending_loan_count > 0): ?>
                        <span class="badge-notification">
                            <?php echo $pending_loan_count; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (has_permission('withdrawals.php', $admin_role, $admin_permissions)): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'withdrawals.php') ? 'active' : ''; ?>" href="withdrawals.php<?php echo (isset($pending_withdrawal_count) && $pending_withdrawal_count > 0) ? '#pending-withdrawals' : ''; ?>">
                        <i class="bi bi-cash-stack"></i>
                        <span>Withdrawals</span>
                        <span class="badge-notification" id="withdrawal-badge" style="display: none;" data-bs-toggle="tooltip" data-bs-placement="right" title="Pending withdrawals awaiting your approval">
                            0
                        </span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if ($admin_role === 'super_admin' || (isset($current_admin) && $current_admin['username'] === 'admin')): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'create_admin.php') ? 'active' : ''; ?>" href="create_admin.php">
                        <i class="bi bi-person-plus-fill"></i>
                        <span>Create Admin</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" href="profile.php">
                        <i class="bi bi-person-circle"></i>
                        <span>Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link logout" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Add the CSS for our sleek navbar -->
<style>
    .admin-navbar {
        background: linear-gradient(135deg, #1E2A38 0%, #2C3E50 100%);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.05);
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        padding: 0.8rem 1.5rem;
        transition: all 0.3s ease;
    }

    .admin-navbar .navbar-brand {
        color: #E0E6ED;
        font-weight: 600;
        font-size: 1.4rem;
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0;
        transition: all 0.3s ease;
    }

    .admin-navbar .navbar-brand:hover {
        transform: translateY(-2px);
        filter: drop-shadow(0 0 8px rgba(74, 110, 245, 0.5));
    }

    .admin-navbar .navbar-brand i {
        font-size: 1.6rem;
        color: #7A92FF;
        transition: all 0.3s ease;
    }

    .admin-navbar .navbar-brand:hover i {
        transform: rotate(-10deg);
        color: #4A6EF5;
    }

    .admin-navbar .navbar-toggler {
        border: none;
        color: #E0E6ED;
        padding: 0.4rem;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 0.5rem;
        backdrop-filter: blur(10px);
        transition: all 0.3s ease;
    }

    .admin-navbar .navbar-toggler:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: scale(1.05);
    }

    .admin-navbar .navbar-toggler:focus {
        box-shadow: none;
    }

    .admin-navbar .nav-item {
        margin: 0 0.25rem;
    }

    .admin-navbar .nav-link {
        color: #E0E6ED !important;
        padding: 0.7rem 1.1rem;
        border-radius: 0.5rem;
        transition: all 0.3s ease;
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        font-weight: 500;
        font-size: 0.95rem;
        letter-spacing: 0.3px;
        background: transparent;
        border: 1px solid transparent;
    }

    .admin-navbar .nav-link:hover {
        background: rgba(255, 255, 255, 0.08);
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .admin-navbar .nav-link.active {
        background: linear-gradient(135deg, #4A6EF5 0%, #7A92FF 100%);
        color: white !important;
        box-shadow: 0 4px 18px rgba(74, 110, 245, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
        font-weight: 600;
    }

    .admin-navbar .nav-link i {
        font-size: 1.2rem;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .admin-navbar .nav-link:hover i {
        transform: scale(1.15);
        color: #7A92FF;
    }
    
    .admin-navbar .nav-link.active:hover i {
        color: white;
    }

    .admin-navbar .nav-link.logout {
        color: #E0E6ED !important;
    }

    .admin-navbar .nav-link.logout:hover {
        background: rgba(255, 76, 76, 0.15);
        color: #FF4C4C !important;
    }
    
    .admin-navbar .nav-link.logout:hover i {
        color: #FF4C4C;
    }

    .admin-navbar .badge-notification {
        position: absolute;
        top: 0.3rem;
        right: 0.3rem;
        font-size: 0.7rem;
        font-weight: 600;
        background: #FF4C4C;
        color: white;
        padding: 0.2rem 0.5rem;
        border-radius: 0.375rem;
        min-width: 1.5rem;
        text-align: center;
        box-shadow: 0 2px 8px rgba(255, 76, 76, 0.4);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(255, 76, 76, 0.6); }
        70% { box-shadow: 0 0 0 6px rgba(255, 76, 76, 0); }
        100% { box-shadow: 0 0 0 0 rgba(255, 76, 76, 0); }
    }

    /* Responsive adjustments */
    @media (max-width: 991.98px) {
        .admin-navbar {
            padding: 0.7rem 1rem;
        }

        .admin-navbar .navbar-collapse {
            background: linear-gradient(135deg, #1E2A38 0%, #2C3E50 100%);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            margin-top: 0.5rem;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .admin-navbar .nav-link {
            padding: 0.8rem 1rem;
            margin: 0.2rem 0;
        }
    }

    @media (max-width: 767.98px) {
        .admin-navbar .navbar-brand span {
            font-size: 1.2rem;
        }
    }

    /* Main content positioning to work with fixed navbar */
    .main-content {
        margin-top: 70px; /* Allows space for the fixed navbar */
        padding: 2rem;
        transition: all 0.3s ease;
    }

    @media (max-width: 767.98px) {
        .main-content {
            margin-top: 65px;
            padding: 1.2rem;
        }
    }

    /* Enhanced Badge Notification Style */
    .badge-notification {
        position: absolute;
        top: 10px;
        right: 5px;
        background-color: #FF5757;
        color: white;
        border-radius: 50%;
        min-width: 18px;
        height: 18px;
        font-size: 0.7rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        padding: 0 5px;
        border: 2px solid white;
        box-shadow: 0 0 5px rgba(0, 0, 0, 0.3);
        transition: all 0.3s ease;
    }
    
    .nav-link:hover .badge-notification {
        transform: scale(1.2);
        background-color: #ff3535;
    }
    
    /* Tooltip styling */
    .tooltip {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-size: 0.85rem;
    }
    
    .tooltip-inner {
        background-color: #1a2942;
        box-shadow: 0 3px 15px rgba(0, 0, 0, 0.2);
        border-radius: 4px;
        padding: 8px 12px;
    }
    
    .bs-tooltip-end .tooltip-arrow::before {
        border-right-color: #1a2942;
    }
</style>

<!-- Initialize tooltips with JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Function to check for new withdrawal requests
        function checkWithdrawals() {
            fetch('check_withdrawals.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('withdrawal-badge');
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'flex';
                        
                        // If there are new requests and the badge was previously hidden, play notification sound
                        if (data.has_new && badge.style.display === 'none') {
                            const notificationSound = new Audio('data:audio/wav;base64,UklGRiQDAABXQVZFZm10IBAAAAABAAEAESsAABErAAABAAgAZGF0YQADAACBhYqFbF1fZHB3EhgICRAX2dTj6e4FBwwQEAwKCggIBQcIBgUICgYFFw4HoZBuRDo0NT1Wg8aZdXJ6f5KUnJ6Vl56hi2piVEg+Pj1APDh+cVEzHxYaJEF9lmNKR05aZWlbV1pmdINfUVJUU05LSEFAQG1PIxsRDhckVY9qQDk9SVZSTUFBUmZyW1RYXmBdVk1JSkxRTDkpHxwkM1d9aTgzPlRfa2VaXGVwdGlna3F1b2NVTkxEODIzSWxZPDAnKDhLeJ2Be2UzIhseQWp/ZUw9QFBea1M8Oj1BWl1MRkpYX1pQQ0ZFTEU9ODrQvoVZTkhDPz9EZI2HbVtPTFBaY2phUkVAPEBGSEE3RlRXTD84Njs8NjY5bVwwHRQQGCtQbWJXU1deZGZbRzs5QE1cXlJMTlZZUEQ5Njg6NC80Tos1DwkHDBxCU0dBPz9JV2tVPTc4RE5dXFNMUltgVkIzMDA0L0hlVDceFhYeKDY2MjEzOUVWXVJEPT5GUFxhU0pOW2JcSz0xMDIxaFMlCwQECRY1TUU5MzU9TFtmWEc/QEhWYGBTRElWYF1RQDUyMjNAPDMwe2E6IRcWGiU2UldXU1ZebXaRkHNeXXCEf2xobnuKhHBmZ25pX2NmaAkHBwwRGzlPV1peZnWRp7qbVEAxNUBIRj46PkJGTVBXUEIvGwVLcmlSSDo6RFFQTFRqgIuOf3dvT0xGNkkZF0ZALURcbHuEdnl/gHprYl5ZOSsqtK6TeX6JoaSflZmTcGiZkZZyWF1JMSwpJzxHGRM5HSI+aYc8FiMcBCc/Mjk8R15xRiwrO3B7TDg5LnJaJDAyKR00UlhZNjM1Sk0jEBEePWdeZ1slLVDh3aVaTExHRlJcd4lpPCgzYX0xFSNFNB0VIy0xVV1JRnFdLiUoIiUcGzE8bTA=');
                            notificationSound.volume = 0.3;
                            notificationSound.play().catch(e => console.log('Audio play failed:', e));
                        }
                    } else {
                        badge.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error checking withdrawals:', error));
        }

        // Check immediately when page loads
        checkWithdrawals();

        // Check every 30 seconds
        setInterval(checkWithdrawals, 30000);
    });
</script> 