<?php
// Get current page
$current_page = basename($_SERVER['SCRIPT_NAME']);

// If user info not set, get it
if (!isset($user) && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}
?>
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><?php echo SITE_NAME; ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'accounts.php') ? 'active' : ''; ?>" href="accounts.php">
                        <i class="bi bi-wallet2"></i> Accounts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'transactions.php') ? 'active' : ''; ?>" href="transactions.php">
                        <i class="bi bi-cash"></i> Transactions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'withdraw.php') ? 'active' : ''; ?>" href="withdraw.php">
                        <i class="bi bi-cash-stack"></i> Withdraw
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'transfer.php') ? 'active' : ''; ?>" href="transfer.php">
                        <i class="bi bi-arrow-left-right"></i> Transfer
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'loans.php') ? 'active' : ''; ?>" href="loans.php">
                        <i class="bi bi-bank"></i> Loans
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'statements.php') ? 'active' : ''; ?>" href="statements.php">
                        <i class="bi bi-file-earmark-text"></i> Statements
                    </a>
                </li>
            </ul>
            <div class="dropdown">
                <div class="d-flex align-items-center" role="button" data-bs-toggle="dropdown">
                    <div class="avatar">
                        <?php echo substr($user['full_name'] ?? 'U', 0, 1); ?>
                    </div>
                    <i class="bi bi-chevron-down ms-1"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav> 