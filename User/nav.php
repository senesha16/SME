<?php
include("../connections.php");

// === CONFIGURATION ===
// Change this to the correct user ID that owns the business/account
$user_id = 15; // <-- UPDATE THIS IF YOUR MAIN USER HAS A DIFFERENT ID

// Get user information
$query_info = mysqli_query($connections, "SELECT first_name, last_name FROM tbl_user WHERE id_user='$user_id'");
if ($query_info && mysqli_num_rows($query_info) > 0) {
    $my_info = mysqli_fetch_assoc($query_info);
    $user_name = htmlspecialchars($my_info["first_name"] . ' ' . $my_info["last_name"]);
} else {
    $user_name = "User";
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
            <!-- Logout button removed - system is now standalone and fully free -->
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
            
            <!-- ALL FEATURES NOW PERMANENTLY AVAILABLE - FULLY FREE SYSTEM -->
            <a href="view_stock.php" class="nav-item <?php echo ($current_page == 'view_stock.php') ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i>
                <span>View Stock</span>
            </a>
            <a href="adjust_stocks.php" class="nav-item <?php echo ($current_page == 'adjust_stocks.php') ? 'active' : ''; ?>">
                <i class="fas fa-edit"></i>
                <span>Adjust Stock</span>
            </a>
            <a href="add_item.php" class="nav-item <?php echo ($current_page == 'add_item.php') ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Add Item</span>
            </a>
            <a href="planner.php" class="nav-item <?php echo ($current_page == 'planner.php') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Calendar</span>
            </a>
            <a href="transaction.php" class="nav-item <?php echo ($current_page == 'transaction.php') ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i>
                <span>Transactions</span>
            </a>
        </nav>
    </aside>