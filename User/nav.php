<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("../connections.php");

if (!isset($_SESSION["email"]) || empty($_SESSION["email"])) {
    error_log("nav.php: No user logged in, redirecting to login.php");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

// Get user information
$email = mysqli_real_escape_string($connections, $_SESSION["email"]);
$query_info = mysqli_query($connections, "SELECT id_user, first_name, last_name, subscription_approved, subscription_plan, trial_start_date, account_type FROM tbl_user WHERE email='$email'");

if ($query_info === false) {
    error_log("nav.php: Query failed: " . mysqli_error($connections));
    echo "<script>alert('Database error. Please try again later.'); window.location.href='login.php';</script>";
    exit;
}

$my_info = mysqli_fetch_assoc($query_info);
if (!$my_info) {
    echo "<script>alert('User not found.'); window.location.href='login.php';</script>";
    exit;
}

$user_id = $my_info["id_user"];
$user_name = htmlspecialchars($my_info["first_name"] . ' ' . $my_info["last_name"]);
$subscription_approved = $my_info["subscription_approved"];
$subscription_plan = $my_info["subscription_plan"];
$account_type = $my_info["account_type"];
$is_trial_active = false;

if ($my_info["trial_start_date"]) {
    $trial_start = new DateTime($my_info["trial_start_date"]);
    $current_date = new DateTime();
    $trial_duration = 60; // 1 minute for testing
    $seconds_since_trial = $trial_start->diff($current_date)->s + ($trial_start->diff($current_date)->i * 60);
    $is_trial_active = $seconds_since_trial <= $trial_duration;
}

// Get business information
$business_query = mysqli_query($connections, "SELECT establishment_name FROM tbl_business WHERE id_user='$user_id'");
$business_data = mysqli_fetch_assoc($business_query);
$business_name = $business_data ? htmlspecialchars($business_data['establishment_name']) : 'SME';

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Navigation -->
<nav class="navbar">
    <div class="nav-container">
        <div class="nav-brand">
            <img src="../sabang_logo.png" alt="<?php echo $business_name; ?> Logo" class="nav-logo">
            <span class="nav-title"><?php echo $business_name; ?> Dashboard</span>
        </div>
        <div class="nav-user">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span class="user-name"><?php echo $user_name; ?></span>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>
</nav>

<!-- Sidebar -->
<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-tachometer-alt"></i> Menu</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="MyAccount.php" class="nav-item <?php echo ($current_page == 'MyAccount.php') ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>My Account</span>
            </a>
            <?php if ($is_trial_active || ($subscription_approved && in_array($subscription_plan, ['A', 'B', 'C']))): ?>
                <a href="view_stock.php" class="nav-item <?php echo ($current_page == 'view_stock.php') ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>View Stock</span>
                </a>
                <a href="adjust_stock.php" class="nav-item <?php echo ($current_page == 'adjust_stock.php') ? 'active' : ''; ?>">
                    <i class="fas fa-edit"></i>
                    <span>Adjust Stock</span>
                </a>
                <a href="add_item.php" class="nav-item <?php echo ($current_page == 'add_item.php') ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Item</span>
                </a>
            <?php endif; ?>
            <?php if ($is_trial_active || ($subscription_approved && in_array($subscription_plan, ['B', 'C']))): ?>
                <a href="planner.php" class="nav-item <?php echo ($current_page == 'planner.php') ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Calendar</span>
                </a>
            <?php endif; ?>
            <?php if ($is_trial_active || ($subscription_approved && $subscription_plan == 'C')): ?>
                <a href="transaction.php" class="nav-item <?php echo ($current_page == 'transaction.php') ? 'active' : ''; ?>">
                    <i class="fas fa-receipt"></i>
                    <span>Transactions</span>
                </a>
            <?php endif; ?>
            <?php if ($account_type == '1'): ?>
                <a href="check_subscription.php" class="nav-item <?php echo ($current_page == 'check_subscription.php') ? 'active' : ''; ?>">
                    <i class="fas fa-cogs"></i>
                    <span>Manage Subscriptions</span>
                </a>
            <?php endif; ?>
            <?php if (!$is_trial_active && !$subscription_approved): ?>
                <a href="subscribe.php" class="nav-item <?php echo ($current_page == 'subscribe.php') ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i>
                    <span>Choose Subscription Plan</span>
                </a>
            <?php endif; ?>
        </nav>
    </aside>