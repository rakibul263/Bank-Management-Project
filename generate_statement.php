<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Require login
require_login();

// Check if account_id and period are provided
if (!isset($_GET['account_id']) || !isset($_GET['period'])) {
    header('Location: statements.php?error=missing_parameters');
    exit;
}

$account_id = (int)$_GET['account_id'];
$period = sanitize_input($_GET['period']);

// Validate account ownership
$stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
$stmt->execute([$account_id, $_SESSION['user_id']]);
$account = $stmt->fetch();

if (!$account) {
    header('Location: statements.php?error=invalid_account');
    exit;
}

// Determine date range based on period
$end_date = date('Y-m-d');
$start_date = '';

switch ($period) {
    case 'last_month':
        $start_date = date('Y-m-d', strtotime('first day of last month'));
        $end_date = date('Y-m-d', strtotime('last day of last month'));
        $period_text = 'Monthly Statement: ' . date('F Y', strtotime('last month'));
        break;
    case 'last_3_months':
        $start_date = date('Y-m-d', strtotime('-3 months'));
        $period_text = 'Quarterly Statement: ' . date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date));
        break;
    case 'last_6_months':
        $start_date = date('Y-m-d', strtotime('-6 months'));
        $period_text = 'Bi-Annual Statement: ' . date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date));
        break;
    case 'year_to_date':
        $start_date = date('Y-01-01');
        $period_text = 'Year-to-Date Statement: ' . date('Y');
        break;
    case 'last_year':
        $start_date = date('Y-01-01', strtotime('-1 year'));
        $end_date = date('Y-12-31', strtotime('-1 year'));
        $period_text = 'Annual Statement: ' . date('Y', strtotime('-1 year'));
        break;
    case 'custom':
        $start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : date('Y-m-d', strtotime('-1 month'));
        $end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : date('Y-m-d');
        $period_text = 'Custom Statement: ' . date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date));
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-1 month'));
        $period_text = 'Monthly Statement: ' . date('F Y');
}

// Get transactions for the period
$stmt = $conn->prepare("
    SELECT t.*, a.account_number, a.account_type 
    FROM transactions t 
    JOIN accounts a ON t.account_id = a.id 
    WHERE t.account_id = ? 
    ORDER BY t.created_at ASC
");
$stmt->execute([$account_id]);
$transactions = $stmt->fetchAll();

// Get user information
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Calculate opening balance (balance at start_date - transactions after start_date)
function calculate_opening_balance($account, $transactions) {
    $current_balance = $account['balance'];
    // Reverse the transactions to go backward in time
    $reversed_transactions = array_reverse($transactions);
    
    foreach ($reversed_transactions as $transaction) {
        if ($transaction['transaction_type'] === 'deposit') {
            $current_balance -= $transaction['amount'];
        } else {
            $current_balance += $transaction['amount'];
        }
    }
    
    return $current_balance;
}

$opening_balance = calculate_opening_balance($account, $transactions);
$closing_balance = $account['balance'];

// Calculate transaction summary
$deposits = 0;
$withdrawals = 0;
foreach ($transactions as $transaction) {
    if ($transaction['transaction_type'] === 'deposit') {
        $deposits += $transaction['amount'];
    } else {
        $withdrawals += $transaction['amount'];
    }
}

// Begin building HTML for statement
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Statement - <?php echo SITE_NAME; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .statement-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #3a7bd5;
            padding-bottom: 20px;
        }
        .bank-name {
            font-size: 24px;
            font-weight: bold;
            color: #3a7bd5;
        }
        .statement-title {
            font-size: 20px;
            margin: 10px 0;
        }
        .period {
            font-size: 16px;
            color: #555;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        .info-label {
            width: 150px;
            font-weight: bold;
            color: #555;
        }
        .info-value {
            flex: 1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .amount-positive {
            color: green;
        }
        .amount-negative {
            color: red;
        }
        .summary-box {
            background-color: #f7f7f7;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .print-button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background: linear-gradient(to right, #3a7bd5, #00d2ff);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
        @media print {
            body {
                background-color: white;
                padding: 0;
            }
            .statement-container {
                box-shadow: none;
                padding: 0;
            }
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="statement-container">
        <div class="header">
            <div class="bank-name"><?php echo SITE_NAME; ?></div>
            <h1 class="statement-title">Account Statement</h1>
            <div class="period"><?php echo $period_text; ?></div>
        </div>
        
        <div class="section">
            <h2 class="section-title">Client Information</h2>
            <div class="info-row">
                <div class="info-label">Name:</div>
                <div class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Address:</div>
                <div class="info-value"><?php echo htmlspecialchars($user['address'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Phone:</div>
                <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">Account Details</h2>
            <div class="info-row">
                <div class="info-label">Account Number:</div>
                <div class="info-value"><?php echo htmlspecialchars($account['account_number']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Account Type:</div>
                <div class="info-value"><?php echo ucfirst(htmlspecialchars($account['account_type'])); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Opening Balance:</div>
                <div class="info-value">$<?php echo format_currency($opening_balance); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Closing Balance:</div>
                <div class="info-value">$<?php echo format_currency($closing_balance); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Statement Period:</div>
                <div class="info-value"><?php echo date('M d, Y', strtotime($start_date)).' to '.date('M d, Y', strtotime($end_date)); ?></div>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">Transaction Summary</h2>
            <div class="summary-box">
                <div class="info-row">
                    <div class="info-label">Total Deposits:</div>
                    <div class="info-value amount-positive">$<?php echo format_currency($deposits); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Total Withdrawals:</div>
                    <div class="info-value amount-negative">$<?php echo format_currency($withdrawals); ?></div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">Transaction Details</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($start_date)); ?></td>
                        <td>Opening Balance</td>
                        <td></td>
                        <td></td>
                        <td>$<?php echo format_currency($opening_balance); ?></td>
                    </tr>
                    
                    <?php 
                    $current_balance = $opening_balance;
                    foreach ($transactions as $transaction): 
                        if ($transaction['transaction_type'] === 'deposit') {
                            $current_balance += $transaction['amount'];
                            $type = 'Deposit';
                            $amount_class = 'amount-positive';
                            $amount_display = '+$'.format_currency($transaction['amount']);
                        } else {
                            $current_balance -= $transaction['amount'];
                            $type = 'Withdrawal';
                            $amount_class = 'amount-negative';
                            $amount_display = '-$'.format_currency($transaction['amount']);
                        }
                    ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($transaction['created_at'])); ?></td>
                        <td><?php echo $transaction['description'] ? htmlspecialchars($transaction['description']) : $type; ?></td>
                        <td><?php echo $type; ?></td>
                        <td class="<?php echo $amount_class; ?>"><?php echo $amount_display; ?></td>
                        <td>$<?php echo format_currency($current_balance); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <button class="print-button" onclick="window.print()">Print Statement</button>
        
        <div class="footer">
            <p>This statement was generated on <?php echo date('F d, Y'); ?> at <?php echo date('h:i A'); ?></p>
            <p>For questions about this statement, please contact customer service.</p>
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
    
    <script>
        // Auto-print when the page loads
        window.onload = function() {
            // Automatically open print dialog after a short delay
            setTimeout(function() {
                window.print();
            }, 1000);
        };
    </script>
</body>
</html> 