<?php
// Line 1
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION["email"]) || empty($_SESSION["email"])) {
    error_log("index.php: No user logged in, redirecting to login.php");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

include("../connections.php");
include("access_control.php");

$email = mysqli_real_escape_string($connections, $_SESSION["email"]);
$query_info = mysqli_query($connections, "SELECT id_user, first_name, last_name, subscription_approved, subscription_plan, trial_start_date, account_type FROM tbl_user WHERE email='$email'");
if ($query_info === false) {
    error_log("index.php: Query failed: " . mysqli_error($connections));
    echo "<script>alert('Database error. Please try again later.'); window.location.href='login.php';</script>";
    exit;
}
$my_info = mysqli_fetch_assoc($query_info);
if (!$my_info) {
    error_log("index.php: User not found for email: $email");
    echo "<script>alert('User not found.'); window.location.href='login.php';</script>";
    exit;
}
$user_id = $my_info["id_user"];
$user_name = $my_info["first_name"] . ' ' . $my_info["last_name"];
$subscription_approved = $my_info["subscription_approved"];
$subscription_plan = $my_info["subscription_plan"];
$account_type = $my_info["account_type"];
$is_trial_active = false;

if ($my_info["trial_start_date"]) {
    try {
        $trial_start = new DateTime($my_info["trial_start_date"]);
        $current_date = new DateTime();
        $trial_duration = 60; // 1 minute for testing
        $seconds_since_trial = $trial_start->diff($current_date)->s + ($trial_start->diff($current_date)->i * 60);
        $is_trial_active = $seconds_since_trial <= $trial_duration;
    } catch (Exception $e) {
        error_log("index.php: DateTime error for $email: " . $e->getMessage());
    }
}

// Get business information
$business_query = mysqli_query($connections, "SELECT * FROM tbl_business WHERE id_user='$user_id'");
$business_data = mysqli_fetch_assoc($business_query);
$business_name = $business_data ? htmlspecialchars($business_data['establishment_name']) : 'SME';

// Get item count
$item_query = mysqli_query($connections, "SELECT COUNT(*) as item_count FROM tbl_item WHERE id_user='$user_id'");
if ($item_query === false) {
    error_log("index.php: Item count query failed for user_id $user_id: " . mysqli_error($connections));
    $item_count = 0;
} else {
    $item_count = mysqli_fetch_assoc($item_query)['item_count'];
}

// Get transaction count
$transaction_query = mysqli_query($connections, "SELECT COUNT(*) as transaction_count FROM tbl_purchase WHERE id_user='$user_id'");
if ($transaction_query === false) {
    error_log("index.php: Transaction count query failed for user_id $user_id: " . mysqli_error($connections));
    $transaction_count = 0;
} else {
    $transaction_count = mysqli_fetch_assoc($transaction_query)['transaction_count'];
}

// Get almost expired items count (within 7 days, aligned with view_stock.php)
$stmt = $connections->prepare("SELECT COUNT(*) as almost_expired_count FROM tbl_item WHERE id_user = ? AND expiration_date_item IS NOT NULL AND expiration_date_item > CURDATE() AND expiration_date_item <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
if ($stmt === false) {
    error_log("index.php: Prepare failed for almost expired query for user_id $user_id: " . mysqli_error($connections));
    $almost_expired_count = 0;
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $almost_expired_count = $result->fetch_assoc()['almost_expired_count'];
    $stmt->close();
}

// Get out of stock items count (based on view_stock.php logic)
$out_of_stock_query = mysqli_query($connections, "SELECT COUNT(*) as out_of_stock_count FROM tbl_item WHERE id_user='$user_id' AND quantity_item = 0");
if ($out_of_stock_query === false) {
    error_log("index.php: Out of stock query failed for user_id $user_id: " . mysqli_error($connections));
    $out_of_stock_count = 0;
} else {
    $out_of_stock_count = mysqli_fetch_assoc($out_of_stock_query)['out_of_stock_count'];
}

// Get pending deliveries count (to be delivered)
$pending_delivery_query = mysqli_query($connections, "SELECT COUNT(*) as pending_delivery_count FROM tbl_delivery WHERE id_user='$user_id' AND status='pending'");
if ($pending_delivery_query === false) {
    error_log("index.php: Pending delivery query failed for user_id $user_id: " . mysqli_error($connections));
    $pending_delivery_count = 0;
} else {
    $pending_delivery_count = mysqli_fetch_assoc($pending_delivery_query)['pending_delivery_count'];
}

// Get pending pickups count (assumed to be same as pending deliveries; adjust if separate logic exists)
$pending_pickup_query = mysqli_query($connections, "SELECT COUNT(*) as pending_pickup_count FROM tbl_delivery WHERE id_user='$user_id' AND status='pending'");
if ($pending_pickup_query === false) {
    error_log("index.php: Pending pickup query failed for user_id $user_id: " . mysqli_error($connections));
    $pending_pickup_count = 0;
} else {
    $pending_pickup_count = mysqli_fetch_assoc($pending_pickup_query)['pending_pickup_count'];
}

// Get recent transactions
$recent_query = mysqli_query($connections, "SELECT p.id_purchase, p.date_time FROM tbl_purchase p WHERE p.id_user='$user_id' ORDER BY p.date_time DESC LIMIT 5");
if ($recent_query === false) {
    error_log("index.php: Recent query failed for user_id $user_id: " . mysqli_error($connections));
} else {
    error_log("index.php: Recent query executed for user_id $user_id, rows: " . mysqli_num_rows($recent_query));
}

// Current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $business_name; ?> Dashboard - <?php echo htmlspecialchars($user_name); ?></title>
    <link rel="stylesheet" href="user-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
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
                    <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="dashboard-container">
        <!-- Sidebar -->
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
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Welcome Section -->
            <section class="welcome-section">
                <div class="welcome-card">
                    <div class="welcome-content">
                        <h1>Welcome back, <?php echo htmlspecialchars($my_info['first_name']); ?>! 👋</h1>
                        <p class="welcome-text">Here's what's happening with your business today</p>
                        <?php if($business_data): ?>
                            <div class="business-info">
                                <i class="fas fa-building"></i>
                                <span><?php echo $business_name; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="welcome-image">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </section>

            <!-- Stats Grid -->
            <section class="stats-section">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $item_count; ?></h3>
                            <p>Total Items</p>
                        </div>
                        <div class="stat-trend positive">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $transaction_count; ?></h3>
                            <p>Transactions</p>
                        </div>
                        <div class="stat-trend positive">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                    </div>

                    <a href="view_stock.php?mode=expiry&expiry_filter=expiring_soon" class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $almost_expired_count; ?></h3>
                            <p>Almost Expired</p>
                        </div>
                        <div class="stat-trend warning">
                            <i class="fas fa-exclamation"></i>
                        </div>
                    </a>

                    <a href="view_stock.php?mode=out_of_stock" class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $out_of_stock_count; ?></h3>
                            <p>Out of Stock</p>
                        </div>
                        <div class="stat-trend warning">
                            <i class="fas fa-exclamation"></i>
                        </div>
                    </a>

                    <a href="planner.php" class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $pending_delivery_count; ?></h3>
                            <p>Pending Deliveries</p>
                        </div>
                        <div class="stat-trend warning">
                            <i class="fas fa-exclamation"></i>
                        </div>
                    </a>

                    <?php if($business_data): ?>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-content">
                            <h3>₱<?php echo number_format($business_data['capital']); ?></h3>
                            <p>Capital</p>
                        </div>
                        <div class="stat-trend neutral">
                            <i class="fas fa-minus"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo date('Y', strtotime($business_data['date_of_establishment'])); ?></h3>
                            <p>Established</p>
                        </div>
                        <div class="stat-trend neutral">
                            <i class="fas fa-minus"></i>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Quick Actions -->
            <section class="actions-section">
                <h2><i class="fas fa-lightning-bolt"></i> Quick Actions</h2>
                <div class="actions-grid">
                    <?php if ($is_trial_active || ($subscription_approved && in_array($subscription_plan, ['A', 'B', 'C']))): ?>
                        <a href="add_item.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <div class="action-content">
                                <h3>Add New Item</h3>
                                <p>Add products to your inventory</p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>

                        <a href="view_stock.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="action-content">
                                <h3>View Stock</h3>
                                <p>Check your current inventory</p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    <?php endif; ?>
                    <?php if ($is_trial_active || ($subscription_approved && $subscription_plan == 'C')): ?>
                        <a href="transaction.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <div class="action-content">
                                <h3>New Transaction</h3>
                                <p>Record a new sale or purchase</p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    <?php endif; ?>
                    <?php if ($is_trial_active || ($subscription_approved && in_array($subscription_plan, ['B', 'C']))): ?>
                        <a href="planner.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <div class="action-content">
                                <h3>Schedule Event</h3>
                                <p>Plan your business activities</p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Recent Activity -->
            <section class="recent-section">
                <div class="section-header">
                    <h2><i class="fas fa-clock"></i> Recent Activity</h2>
                    <?php if ($is_trial_active || ($subscription_approved && $subscription_plan == 'C')): ?>
                        <a href="transaction.php?mode=history" class="view-all-btn">View All</a>
                         <a href="weekly_summary.php?mode=history" class="view-all-btn">summary report</a>
                    <?php endif; ?>
                </div>
                <div class="activity-card">
                    <div class="activity-list">
                        <?php
                        if ($recent_query && mysqli_num_rows($recent_query) > 0):
                            $activity_number = $transaction_count; // Start from total transactions
                            mysqli_data_seek($recent_query, 0); // Reset query pointer
                            while ($activity = mysqli_fetch_assoc($recent_query)):
                                if ($activity && isset($activity['id_purchase'], $activity['date_time'])):
                                    $stmt = $connections->prepare("SELECT name_item FROM tbl_item WHERE id_item = (SELECT id_item FROM tbl_purchase WHERE id_purchase = ? LIMIT 1)");
                                    if ($stmt === false) {
                                        error_log("index.php: Prepare failed for item name query for id_purchase {$activity['id_purchase']}: " . mysqli_error($connections));
                                        $item_name = 'Unknown Item';
                                    } else {
                                        $stmt->bind_param("i", $activity['id_purchase']);
                                        $stmt->execute();
                                        $item_result = $stmt->get_result();
                                        $item_name = $item_result->num_rows > 0 && ($item_row = $item_result->fetch_assoc()) ? $item_row['name_item'] : 'Unknown Item';
                                        $stmt->close();
                                    }
                                    $activity_date = !empty($activity['date_time']) && strtotime($activity['date_time']) !== false
                                        ? date('Y-m-d', strtotime($activity['date_time']))
                                        : date('Y-m-d');
                                    $activity_time_display = !empty($activity['date_time']) && strtotime($activity['date_time']) !== false
                                        ? date('M d, Y - g:i A', strtotime($activity['date_time']))
                                        : 'Unknown Date';
                        ?>
                        <a href="transaction.php?mode=history&history_date=<?php echo htmlspecialchars($activity_date); ?>&history_search=<?php echo htmlspecialchars($item_name); ?>" class="activity-item" style="text-decoration: none; color: inherit; display: block;">
                            <div class="activity-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>Transaction #<?php echo $activity_number; ?></strong> - <?php echo htmlspecialchars($item_name); ?></p>
                                <span class="activity-time"><?php echo htmlspecialchars($activity_time_display); ?></span>
                            </div>
                        </a>
                        <?php
                                endif;
                                $activity_number--;
                            endwhile;
                        else:
                        ?>
                        <div class="no-activity">
                            <i class="fas fa-inbox"></i>
                            <p>No recent activity</p>
                            <small>Start by adding items or making transactions</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('animate-in');
            });

            const actionCards = document.querySelectorAll('.action-card');
            actionCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>