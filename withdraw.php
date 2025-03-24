<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Require login
require_login();

$error = '';
$success = '';

// Get user's accounts
$stmt = $conn->prepare("SELECT * FROM accounts WHERE user_id = ? AND status = 'active'");
$stmt->execute([$_SESSION['user_id']]);
$accounts = $stmt->fetchAll();

// Get all admins
$stmt = $conn->prepare("SELECT id, username FROM admins");
$stmt->execute();
$admins = $stmt->fetchAll();

// Handle withdrawal request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_id = (int)$_POST['account_id'];
    $admin_id = (int)$_POST['admin_id'];
    $amount = (float)$_POST['amount'];
    $description = sanitize_input($_POST['description'] ?? '');
    
    // Validate account ownership
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$account_id, $_SESSION['user_id']]);
    $account = $stmt->fetch();
    
    if (!$account) {
        $error = 'Invalid account';
    } elseif ($amount <= 0) {
        $error = 'Amount must be greater than 0';
    } elseif ($amount > $account['balance']) {
        $error = 'Insufficient funds';
    } elseif ($admin_id <= 0) {
        $error = 'Please select an admin';
    } else {
        // Create withdrawal request
        if (create_withdrawal_request($account_id, $admin_id, $amount, $description)) {
            $success = 'Withdrawal request submitted successfully! Awaiting admin approval.';
        } else {
            $error = 'Failed to submit withdrawal request. Please try again.';
        }
    }
}

// Get user's pending withdrawal requests
$stmt = $conn->prepare("
    SELECT wr.*, a.account_number, adm.username as admin_name
    FROM withdrawal_requests wr
    JOIN accounts a ON wr.account_id = a.id
    JOIN admins adm ON wr.admin_id = adm.id
    WHERE a.user_id = ?
    ORDER BY wr.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$withdrawal_requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw Money - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3a7bd5;
            --primary-gradient: linear-gradient(to right, #3a7bd5, #00d2ff);
            --secondary-color: #6c757d;
            --accent-color: #ffc107;
            --light-bg: #f8f9fa;
            --white: #ffffff;
            --card-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            --text-primary: #333;
            --text-secondary: #6c757d;
            --border-radius: 10px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background: var(--white);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .nav-link {
            font-weight: 500;
            color: var(--text-primary);
            margin: 0 10px;
            position: relative;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .nav-link i {
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary-color);
        }

        .nav-link.active::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 3px;
            background: var(--primary-gradient);
            bottom: -7px;
            left: 0;
            border-radius: 3px;
        }

        .main-content {
            flex: 1;
            padding: 30px 0;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: var(--primary-gradient);
            color: var(--white);
            font-weight: 600;
            padding: 15px 20px;
            border: none;
        }

        .card-title {
            margin-bottom: 0;
            font-weight: 600;
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: var(--border-radius);
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(58, 123, 213, 0.3);
        }

        .status-badge {
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Replace the existing avatar styles with dashboard's avatar style */
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .admin-name {
            display: flex;
            align-items: center;
        }
        
        .admin-name .avatar {
            margin-right: 10px;
        }

        footer {
            background: var(--white);
            padding: 20px 0;
            margin-top: auto;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
            box-shadow: 0 -2px 15px rgba(0, 0, 0, 0.05);
        }

        /* Add these styles for the custom select options */
        .admin-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
        }
        
        .admin-option-avatar {
            width: 24px;
            height: 24px;
            background: rgba(58, 123, 213, 0.1);
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        select.form-select {
            padding-top: 10px;
            padding-bottom: 10px;
        }

        /* Custom dropdown styles */
        .custom-dropdown {
            position: relative;
        }
        
        .dropdown-toggle {
            border: 2px solid #dee2e6;
            border-radius: var(--border-radius);
            background: white;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.95rem;
            width: 100%;
        }
        
        .dropdown-toggle:hover {
            border-color: var(--primary-color);
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1000;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            border: 1px solid rgba(0,0,0,0.08);
            margin-top: 5px;
            padding: 8px 0;
            max-height: 250px;
            overflow-y: auto;
            display: none;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-item {
            padding: 10px 15px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .dropdown-item:hover {
            background: rgba(58, 123, 213, 0.05);
        }
        
        .dropdown-item.selected {
            background: rgba(58, 123, 213, 0.1);
            font-weight: 500;
        }
        
        /* Update dropdown-item-avatar to match main avatar style */
        .dropdown-item-avatar {
            width: 32px;
            height: 32px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 1rem;
        }
        
        .placeholder-text {
            color: #6c757d;
        }
        
        @media (max-width: 767.98px) {
            .dropdown-menu {
                max-height: 200px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title"><i class="bi bi-cash-stack me-2"></i>Withdraw Money</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                            <?php endif; ?>
                            
                            <?php if (empty($accounts)): ?>
                                <div class="alert alert-warning">You don't have any active accounts. Please create an account first.</div>
                            <?php else: ?>
                                <form method="POST" class="mt-3">
                                    <div class="mb-3">
                                        <label for="account_id" class="form-label">Select Account</label>
                                        <select name="account_id" id="account_id" class="form-select" required>
                                            <option value="">-- Select Account --</option>
                                            <?php foreach ($accounts as $account): ?>
                                                <option value="<?php echo $account['id']; ?>">
                                                    <?php echo $account['account_number']; ?> (<?php echo $account['account_type']; ?>) - Balance: <?php echo format_currency($account['balance']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="admin_dropdown" class="form-label">Select Admin for Approval</label>
                                        <div class="custom-dropdown">
                                            <div class="dropdown-toggle" id="admin_dropdown">
                                                <div class="d-flex align-items-center">
                                                    <span class="dropdown-avatar d-none me-2">
                                                        <div class="dropdown-item-avatar">
                                                            <i class="bi bi-person"></i>
                                                        </div>
                                                    </span>
                                                    <span class="selected-option placeholder-text">-- Select Admin --</span>
                                                </div>
                                                <i class="bi bi-chevron-down"></i>
                                            </div>
                                            <div class="dropdown-menu">
                                                <?php foreach ($admins as $admin): ?>
                                                    <div class="dropdown-item" data-value="<?php echo $admin['id']; ?>">
                                                        <div class="dropdown-item-avatar">
                                                            <i class="bi bi-person"></i>
                                                        </div>
                                                        <span><?php echo htmlspecialchars($admin['username']); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <input type="hidden" name="admin_id" id="admin_id" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="amount" class="form-label">Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control" required placeholder="0.00">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description (Optional)</label>
                                        <textarea name="description" id="description" class="form-control" rows="3" placeholder="Reason for withdrawal"></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">Submit Withdrawal Request</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title"><i class="bi bi-list-check me-2"></i>Withdrawal Requests</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($withdrawal_requests)): ?>
                                <div class="alert alert-info">You don't have any withdrawal requests.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Account</th>
                                                <th>Amount</th>
                                                <th>Admin</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($withdrawal_requests as $request): ?>
                                                <tr>
                                                    <td><?php echo date('d M Y', strtotime($request['created_at'])); ?></td>
                                                    <td><?php echo $request['account_number']; ?></td>
                                                    <td>$<?php echo format_currency($request['amount']); ?></td>
                                                    <td>
                                                        <div class="admin-name">
                                                            <div class="avatar">
                                                                <i class="bi bi-person"></i>
                                                            </div>
                                                            <?php echo $request['admin_name']; ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo get_status_badge($request['status']); ?></td>
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
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Bootstrap & jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Custom dropdown functionality
            const dropdown = $('.custom-dropdown');
            const dropdownToggle = dropdown.find('.dropdown-toggle');
            const dropdownMenu = dropdown.find('.dropdown-menu');
            const dropdownItems = dropdown.find('.dropdown-item');
            const hiddenInput = $('#admin_id');
            const selectedOption = dropdown.find('.selected-option');
            const dropdownAvatar = dropdown.find('.dropdown-avatar');
            
            // Toggle dropdown menu
            dropdownToggle.on('click', function() {
                dropdownMenu.toggleClass('show');
            });
            
            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!dropdown.is(e.target) && dropdown.has(e.target).length === 0) {
                    dropdownMenu.removeClass('show');
                }
            });
            
            // Select an option
            dropdownItems.on('click', function() {
                const value = $(this).data('value');
                const text = $(this).find('span').text().trim();
                
                // Update hidden input value
                hiddenInput.val(value);
                
                // Update selected text
                selectedOption.text(text).removeClass('placeholder-text');
                
                // Show avatar in dropdown toggle
                dropdownAvatar.removeClass('d-none');
                
                // Highlight selected item
                dropdownItems.removeClass('selected');
                $(this).addClass('selected');
                
                // Close dropdown
                dropdownMenu.removeClass('show');
            });
        });
    </script>
</body>
</html> 