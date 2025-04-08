<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Require login
require_login();

$error = '';
$success = '';

// Check for error or success messages
if (isset($_GET['error'])) {
    $error_code = sanitize_input($_GET['error']);
    if ($error_code === 'missing_parameters') {
        $error = 'Missing account or period selection.';
    } elseif ($error_code === 'invalid_account') {
        $error = 'Invalid account selected.';
    } else {
        $error = 'An error occurred. Please try again.';
    }
}

if (isset($_GET['success'])) {
    $success = 'Statement generated successfully.';
}

// Get user's accounts
$stmt = $conn->prepare("SELECT * FROM accounts WHERE user_id = ? AND status = 'active'");
$stmt->execute([$_SESSION['user_id']]);
$accounts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Statements - <?php echo SITE_NAME; ?></title>
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
            box-shadow: 0 5px 15px rgba(58, 123, 213, 0.4);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--secondary-color);
            border: none;
            border-radius: var(--border-radius);
            padding: 10px 20px;
            font-weight: 500;
        }

        .form-select, .form-control {
            border-radius: var(--border-radius);
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            font-size: 0.95rem;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .alert {
            border-radius: var(--border-radius);
            padding: 15px 20px;
            margin-bottom: 25px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h2 {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0;
        }

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

        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-radius: var(--border-radius);
        }
        
        .statement-option {
            border: 1px solid #eee;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            cursor: pointer;
            background-color: var(--white);
        }
        
        .statement-option:hover {
            border-color: var(--primary-color);
            background-color: rgba(58, 123, 213, 0.05);
        }
        
        .statement-option.active {
            border-color: var(--primary-color);
            background-color: rgba(58, 123, 213, 0.1);
        }
        
        .statement-option h5 {
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .statement-option p {
            color: var(--text-secondary);
            margin-bottom: 0;
        }
        
        .custom-date-section {
            display: none;
            padding-top: 15px;
        }

        @media (max-width: 767px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .page-header .btn {
                width: 100%;
            }
            
            .card {
                margin-bottom: 20px;
            }
            
            .navbar-nav {
                margin-top: 15px;
            }
            
            .nav-item {
                width: 100%;
                text-align: center;
            }
        }

        .daterange {
            cursor: pointer;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            border: 1px solid #dee2e6;
        }
        
        /* Footer styles */
        footer {
            background: var(--white);
            padding: 15px 0;
            margin-top: auto;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
            box-shadow: 0 -2px 15px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <div class="container main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-file-earmark-text me-2"></i>Account Statements</h2>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-download me-2"></i>Download Account Statement</h5>
                    </div>
                    <div class="card-body">
                        <form id="statementForm" action="generate_statement.php" method="GET" target="_blank">
                            <div class="mb-4">
                                <label for="account_id" class="form-label">Select Account</label>
                                <select class="form-select" id="account_id" name="account_id" required>
                                    <option value="">Choose an account</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo $account['id']; ?>">
                                            <?php echo $account['account_number']; ?> (<?php echo ucfirst($account['account_type']); ?>) - <?php echo format_currency($account['balance']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Statement Period</label>
                                <div class="statement-options">
                                    <div class="statement-option active" data-period="last_month">
                                        <h5><i class="bi bi-calendar-month me-2"></i>Last Month</h5>
                                        <p>Statement for <?php echo date('F Y', strtotime('last month')); ?></p>
                                    </div>
                                    
                                    <div class="statement-option" data-period="last_3_months">
                                        <h5><i class="bi bi-calendar3 me-2"></i>Last 3 Months</h5>
                                        <p>Statement from <?php echo date('M d', strtotime('-3 months')); ?> to <?php echo date('M d, Y'); ?></p>
                                    </div>
                                    
                                    <div class="statement-option" data-period="last_6_months">
                                        <h5><i class="bi bi-calendar3 me-2"></i>Last 6 Months</h5>
                                        <p>Statement from <?php echo date('M d', strtotime('-6 months')); ?> to <?php echo date('M d, Y'); ?></p>
                                    </div>
                                    
                                    <div class="statement-option" data-period="year_to_date">
                                        <h5><i class="bi bi-calendar-event me-2"></i>Year to Date</h5>
                                        <p>Statement for <?php echo date('Y'); ?> (Jan 1 to <?php echo date('M d'); ?>)</p>
                                    </div>
                                    
                                    <div class="statement-option" data-period="last_year">
                                        <h5><i class="bi bi-calendar-event me-2"></i>Last Year</h5>
                                        <p>Annual statement for <?php echo date('Y', strtotime('-1 year')); ?></p>
                                    </div>
                                    
                                    <div class="statement-option" data-period="custom">
                                        <h5><i class="bi bi-calendar4-range me-2"></i>Custom Period</h5>
                                        <p>Select a custom date range for your statement</p>
                                        
                                        <div class="custom-date-section">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="start_date" class="form-label">Start Date</label>
                                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                                           value="<?php echo date('Y-m-d', strtotime('-1 month')); ?>" max="<?php echo date('Y-m-d'); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="end_date" class="form-label">End Date</label>
                                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                                           value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="period" id="period" value="last_month">
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-file-earmark-pdf me-2"></i>Generate PDF Statement
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-info-circle me-2"></i>About Statements</h5>
                    </div>
                    <div class="card-body">
                        <p>Your account statements provide a summary of all transactions during a specific period, including:</p>
                        
                        <ul class="mb-4">
                            <li>Opening and closing balances</li>
                            <li>Deposits and withdrawals</li>
                            <li>Transaction details</li>
                            <li>Account information</li>
                        </ul>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-lightbulb me-2"></i>Statements are generated as PDF files that you can download, print, or save for your records.
                        </div>
                        
                        <hr>
                        
                        <h6 class="fw-bold">Need Help?</h6>
                        <p class="mb-0">If you have questions about your account statement or need assistance, please contact our customer service team.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Additional scripts for daterangepicker -->
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle statement option selection
            const options = document.querySelectorAll('.statement-option');
            const periodInput = document.getElementById('period');
            const customDateSection = document.querySelector('.custom-date-section');
            
            options.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove active class from all options
                    options.forEach(opt => opt.classList.remove('active'));
                    
                    // Add active class to clicked option
                    this.classList.add('active');
                    
                    // Set period value
                    const period = this.dataset.period;
                    periodInput.value = period;
                    
                    // Show/hide custom date section
                    if (period === 'custom') {
                        customDateSection.style.display = 'block';
                    } else {
                        customDateSection.style.display = 'none';
                    }
                });
            });
            
            // Form validation
            document.getElementById('statementForm').addEventListener('submit', function(e) {
                const accountId = document.getElementById('account_id').value;
                if (!accountId) {
                    e.preventDefault();
                    alert('Please select an account');
                }
                
                if (periodInput.value === 'custom') {
                    const startDate = document.getElementById('start_date').value;
                    const endDate = document.getElementById('end_date').value;
                    
                    if (!startDate || !endDate) {
                        e.preventDefault();
                        alert('Please select both start and end dates');
                    } else if (new Date(startDate) > new Date(endDate)) {
                        e.preventDefault();
                        alert('Start date cannot be after end date');
                    }
                }
            });
        });
    </script>
</body>
</html> 