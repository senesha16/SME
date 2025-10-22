<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');
include("nav.php");
include("check_subscription.php");
include("access_control.php");
include("../connections.php");

if (!isset($_SESSION["email"]) || empty($_SESSION["email"])) {
    error_log("transaction.php: No user logged in, redirecting to login.php");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$email = mysqli_real_escape_string($connections, $_SESSION["email"]);
$query_info = mysqli_query($connections, "SELECT id_user, first_name, subscription_approved, subscription_plan FROM tbl_user WHERE email='$email'");
if (!$query_info) {
    error_log("transaction.php: Database error: " . mysqli_error($connections));
    echo "<script>alert('Database error: " . mysqli_error($connections) . "'); window.location.href='login.php';</script>";
    exit;
}
$my_info = mysqli_fetch_assoc($query_info);
if (!$my_info) {
    error_log("transaction.php: User not found for email: $email");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}
$user_id = $my_info["id_user"];
$full_name = $my_info["first_name"];
$subscription_approved = $my_info["subscription_approved"];
$subscription_plan = isset($_SESSION['effective_plan']) ? $_SESSION['effective_plan'] : $my_info["subscription_plan"];

// Define period text array for use in earnings and analytics
$period_text = [
    'day' => 'Today',
    'week' => 'This Week',
    'month' => 'This Month',
    'year' => 'This Year',
    'all' => 'All Transactions'
];

// Restrict access based on feature and plan
$required_plan = ['C'];
if (!$subscription_approved && !isset($_SESSION['effective_plan'])) {
    error_log("transaction.php: Access denied for $email, trial expired or no subscription");
    echo "<script>alert('Access denied. Your trial has expired. Please subscribe.'); window.location.href='Subscribe.php';</script>";
    exit;
} elseif ($subscription_approved && !in_array($subscription_plan, $required_plan)) {
    error_log("transaction.php: Access denied for $email, plan $subscription_plan not sufficient");
    echo "<script>alert('Access denied. Please upgrade to Plan " . implode(' or ', $required_plan) . ".'); window.location.href='MyAccount.php';</script>";
    exit;
}

// Initialize cart and variables
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart = &$_SESSION['cart'];
$total = 0.00;
$quantityErr = "";
$view_mode = isset($_GET["mode"]) ? $_GET["mode"] : "transaction";

// Recalculate total whenever the page loads or form is submitted
foreach ($cart as $cart_item) {
    $total += floatval($cart_item['subtotal']);
}

// Handle remove item from cart
if (isset($_POST["btnRemoveFromCart"])) {
    $id_item = $_POST["id_item"];
    if (isset($cart[$id_item])) {
        unset($cart[$id_item]);
        // Recalculate total
        $total = 0.00;
        foreach ($cart as $cart_item) {
            $total += floatval($cart_item['subtotal']);
        }
        echo "<script>alert('Item removed from cart!');</script>";
    }
}

if ($view_mode == "transaction") {
    if (isset($_POST["btnAddToCart"])) {
        $id_item = $_POST["id_item"];
        $quantity = $_POST["quantity"];
        if ($id_item && $quantity && preg_match("/^\d+$/", $quantity)) {
            $stmt_item = $connections->prepare("SELECT name_item, quantity_item, selling_price_item, selling_price_individual, unit_item, items_per_unit FROM tbl_item WHERE id_item=? AND id_user=? AND quantity_item > 0 AND (expiration_date_item IS NULL OR expiration_date_item > CURDATE())");
            if ($stmt_item === false) {
                error_log("Prepare failed for item select: " . $connections->error);
            } else {
                $stmt_item->bind_param("ii", $id_item, $user_id);
                $stmt_item->execute();
                $result = $stmt_item->get_result();
                $item = $result->fetch_assoc();
                if ($item) {
                    $available_qty = $item["quantity_item"];
                    $selling_price = $item["selling_price_item"];
                    if ($item["unit_item"] == 'piece') {
                        $selling_price = $item["selling_price_individual"] ?? $selling_price;
                    }
                    if ($quantity <= $available_qty) {
                        $subtotal = $quantity * $selling_price;
                        $cart[$id_item] = ['name' => $item["name_item"], 'quantity' => $quantity, 'price' => $selling_price, 'subtotal' => $subtotal];
                        // Recalculate total
                        $total = 0.00;
                        foreach ($cart as $cart_item) {
                            $total += floatval($cart_item['subtotal']);
                        }
                    } else {
                        echo "<script>alert('Kulang ang stock o hindi valid ang item!');</script>";
                    }
                } else {
                    echo "<script>alert('Item not found or out of stock!');</script>";
                }
                $stmt_item->close();
            }
        } else {
            $quantityErr = "Enter a valid quantity.";
        }
    }

    if (isset($_POST["btnComplete"])) {
        // Recalculate total before completing
        $total = 0.00;
        foreach ($cart as $cart_item) {
            $total += floatval($cart_item['subtotal']);
        }

        if (!empty($cart)) {
            $low_stock_items = [];
            foreach ($cart as $id_item => $item) {
                $quantity = $item['quantity'];
                $subtotal = $item['subtotal'];
                $stmt_item = $connections->prepare("SELECT quantity_item, unit_item FROM tbl_item WHERE id_item=? AND id_user=?");
                if ($stmt_item === false) {
                    error_log("Prepare failed for item update select: " . $connections->error);
                } else {
                    $stmt_item->bind_param("ii", $id_item, $user_id);
                    $stmt_item->execute();
                    $result = $stmt_item->get_result();
                    $db_item = $result->fetch_assoc();
                    if ($db_item) {
                        $current_qty = $db_item["quantity_item"];
                        $new_qty = $current_qty - $quantity;
                        if ($new_qty < 0) $new_qty = 0;
                        $stmt_update = $connections->prepare("UPDATE tbl_item SET quantity_item = ? WHERE id_item = ?");
                        if ($stmt_update === false) {
                            error_log("Prepare failed for item update: " . $connections->error);
                        } else {
                            $stmt_update->bind_param("ii", $new_qty, $id_item);
                            $stmt_update->execute();
                            $stmt_update->close();
                        }
                        // Check if stock is low (5 or below)
                        if ($new_qty <= 5) {
                            $low_stock_items[] = $item['name'];
                        }
                        $stmt_insert = $connections->prepare("INSERT INTO tbl_purchase (id_user, id_item, quantity, total_cost, payment_method, status) VALUES (?, ?, ?, ?, 'cash', 'paid')");
                        if ($stmt_insert === false) {
                            error_log("Prepare failed for purchase insert: " . $connections->error);
                        } else {
                            $stmt_insert->bind_param("iiid", $user_id, $id_item, $quantity, $subtotal);
                            $stmt_insert->execute();
                            $stmt_insert->close();
                        }
                    }
                    $stmt_item->close();
                }
            }
            $_SESSION['cart'] = [];
            $notification = "Transaction completed! Total: ₱" . number_format($total, 2);
            if (!empty($low_stock_items)) {
                $notification .= "\\nLow Stock Alert: Please unpack or add more stock for: " . implode(", ", $low_stock_items) . ".";
            }
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    const notification = document.createElement('div');
                    notification.className = 'notification success slide-in';
                    notification.innerHTML = '<i class=\"fas fa-check-circle\"></i> $notification';
                    document.querySelector('.main-content').appendChild(notification);
                    setTimeout(() => notification.classList.add('visible'), 100);
                    setTimeout(() => notification.classList.remove('visible', 'slide-in'), 5000);
                    setTimeout(() => notification.remove(), 5100);
                });
                window.location.href='transaction.php';
            </script>";
        } else {
            echo "<script>alert('Cart is empty!');</script>";
        }
    }
}

// Handle earnings filter (for transaction mode)
$earnings_period = isset($_GET["period"]) ? $_GET["period"] : "day";
$earnings_total = 0.00;
$earnings_query = "SELECT SUM(total_cost) AS earnings FROM tbl_purchase WHERE id_user=? AND status='paid'";
if ($earnings_period == "day") {
    $earnings_query .= " AND DATE(date_time) = CURDATE()";
} elseif ($earnings_period == "week") {
    $earnings_query .= " AND YEARWEEK(date_time) = YEARWEEK(CURDATE())";
} elseif ($earnings_period == "month") {
    $earnings_query .= " AND MONTH(date_time) = MONTH(CURDATE()) AND YEAR(date_time) = YEAR(CURDATE())";
} elseif ($earnings_period == "year") {
    $earnings_query .= " AND YEAR(date_time) = YEAR(CURDATE())";
}
$stmt_earnings = $connections->prepare($earnings_query);
if ($stmt_earnings === false) {
    error_log("Prepare failed for earnings query: " . $connections->error);
} else {
    $stmt_earnings->bind_param("i", $user_id);
    $stmt_earnings->execute();
    $result = $stmt_earnings->get_result();
    if ($result) {
        $earnings = $result->fetch_assoc();
        $earnings_total = $earnings["earnings"] ?? 0.00;
    }
    $stmt_earnings->close();
}

// Handle transaction history
$history_period = isset($_GET["history_period"]) ? $_GET["history_period"] : "all";
$transactions = [];
if ($view_mode == "history") {
    $history_query = "SELECT p.*, i.name_item FROM tbl_purchase p JOIN tbl_item i ON p.id_item = i.id_item WHERE p.id_user=?";
    if ($history_period == "day") {
        $history_query .= " AND DATE(p.date_time) = CURDATE()";
    } elseif ($history_period == "week") {
        $history_query .= " AND YEARWEEK(p.date_time) = YEARWEEK(CURDATE())";
    } elseif ($history_period == "month") {
        $history_query .= " AND MONTH(p.date_time) = MONTH(CURDATE()) AND YEAR(p.date_time) = YEAR(CURDATE())";
    } elseif ($history_period == "year") {
        $history_query .= " AND YEAR(p.date_time) = YEAR(CURDATE())";
    }
    $history_query .= " ORDER BY p.date_time DESC";
    $stmt_history = $connections->prepare($history_query);
    if ($stmt_history === false) {
        error_log("Prepare failed for history query: " . $connections->error);
    } else {
        $stmt_history->bind_param("i", $user_id);
        $stmt_history->execute();
        $result = $stmt_history->get_result();
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        $stmt_history->close();
    }
}

// Handle analytics data
$analytics_period = isset($_GET["analytics_period"]) ? $_GET["analytics_period"] : "month";
$analytics_data = ['total_sales' => 0.00, 'transaction_count' => 0, 'top_items' => [], 'expired_loss' => 0.00];
if ($view_mode == "analytics") {
    // Sales summary query
    $analytics_query = "SELECT SUM(total_cost) AS total_sales, COUNT(*) AS transaction_count FROM tbl_purchase WHERE id_user=? AND status='paid'";
    if ($analytics_period == "day") {
        $analytics_query .= " AND DATE(date_time) = CURDATE()";
    } elseif ($analytics_period == "week") {
        $analytics_query .= " AND YEARWEEK(date_time) = YEARWEEK(CURDATE())";
    } elseif ($analytics_period == "month") {
        $analytics_query .= " AND MONTH(date_time) = MONTH(CURDATE()) AND YEAR(date_time) = YEAR(CURDATE())";
    } elseif ($analytics_period == "year") {
        $analytics_query .= " AND YEAR(date_time) = YEAR(CURDATE())";
    }
    $stmt_analytics = $connections->prepare($analytics_query);
    if ($stmt_analytics === false) {
        error_log("Prepare failed for analytics query: " . $connections->error);
    } else {
        $stmt_analytics->bind_param("i", $user_id);
        $stmt_analytics->execute();
        $result = $stmt_analytics->get_result();
        if ($result) {
            $analytics_data = array_merge($analytics_data, $result->fetch_assoc());
            $analytics_data['total_sales'] = $analytics_data['total_sales'] ?? 0.00;
            $analytics_data['transaction_count'] = $analytics_data['transaction_count'] ?? 0;
        }
        $stmt_analytics->close();
    }

    // Top sold items query
    $top_items_query = "SELECT i.name_item, SUM(p.quantity) AS total_quantity, SUM(p.total_cost) AS total_revenue 
                        FROM tbl_purchase p 
                        JOIN tbl_item i ON p.id_item = i.id_item 
                        WHERE p.id_user=? AND p.status='paid'";
    if ($analytics_period == "day") {
        $top_items_query .= " AND DATE(p.date_time) = CURDATE()";
    } elseif ($analytics_period == "week") {
        $top_items_query .= " AND YEARWEEK(p.date_time) = YEARWEEK(CURDATE())";
    } elseif ($analytics_period == "month") {
        $top_items_query .= " AND MONTH(p.date_time) = MONTH(CURDATE()) AND YEAR(p.date_time) = YEAR(CURDATE())";
    } elseif ($analytics_period == "year") {
        $top_items_query .= " AND YEAR(p.date_time) = YEAR(CURDATE())";
    }
    $top_items_query .= " GROUP BY p.id_item, i.name_item ORDER BY total_quantity DESC LIMIT 5";
    $stmt_top_items = $connections->prepare($top_items_query);
    if ($stmt_top_items === false) {
        error_log("Prepare failed for top items query: " . $connections->error);
    } else {
        $stmt_top_items->bind_param("i", $user_id);
        $stmt_top_items->execute();
        $result = $stmt_top_items->get_result();
        while ($row = $result->fetch_assoc()) {
            $analytics_data['top_items'][] = $row;
        }
        $stmt_top_items->close();
    }

    // Expired items loss query
    $expired_query = "SELECT i.name_item, i.quantity_item, i.selling_price_item, i.selling_price_individual, i.unit_item 
                      FROM tbl_item i 
                      WHERE i.id_user=? AND i.expiration_date_item IS NOT NULL 
                      AND i.expiration_date_item <= CURDATE() 
                      AND i.quantity_item > 0";
    if ($analytics_period == "day") {
        $expired_query .= " AND DATE(i.expiration_date_item) = CURDATE()";
    } elseif ($analytics_period == "week") {
        $expired_query .= " AND YEARWEEK(i.expiration_date_item) = YEARWEEK(CURDATE())";
    } elseif ($analytics_period == "month") {
        $expired_query .= " AND MONTH(i.expiration_date_item) = MONTH(CURDATE()) AND YEAR(i.expiration_date_item) = YEAR(CURDATE())";
    } elseif ($analytics_period == "year") {
        $expired_query .= " AND YEAR(i.expiration_date_item) = YEAR(CURDATE())";
    }
    $stmt_expired = $connections->prepare($expired_query);
    if ($stmt_expired === false) {
        error_log("Prepare failed for expired items query: " . $connections->error);
    } else {
        $stmt_expired->bind_param("i", $user_id);
        $stmt_expired->execute();
        $result = $stmt_expired->get_result();
        while ($row = $result->fetch_assoc()) {
            $selling_price = $row["unit_item"] == 'piece' ? ($row["selling_price_individual"] ?? $row["selling_price_item"]) : $row["selling_price_item"];
            $analytics_data['expired_loss'] += $row["quantity_item"] * $selling_price;
        }
        $stmt_expired->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction - SME Dashboard</title>
    <link rel="stylesheet" href="user-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jQuery and Select2 for searchable dropdown -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" integrity="sha256-8c1Z1oJHo+1LC6rYQ3fQ8b0Z1r2iW0+1r6qZ2zD3qM=" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" integrity="sha256-6i12b4u76V71vZJ+0v2y3oq0mH36z0vOOn2l0E6W1jM=" crossorigin="anonymous"></script>
</head>
<body>
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-text">
                    <h1><i class="fas fa-cash-register"></i> Transaction Management</h1>
                    <p>Welcome, <?php echo htmlspecialchars($full_name); ?> - Process sales and view transaction history</p>
                </div>
                <div class="earnings-display">
                    Today's Earnings: ₱<?php echo number_format($earnings_total, 2); ?>
                </div>
            </div>
        </div>

        <!-- Transaction Tabs -->
        <div class="filter-tabs">
            <a href="?mode=transaction" class="tab-btn <?php echo $view_mode == 'transaction' ? 'active' : ''; ?>">
                <i class="fas fa-cash-register"></i>
                New Transaction
            </a>
            <a href="?mode=history" class="tab-btn <?php echo $view_mode == 'history' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                Transaction History
            </a>
            <a href="?mode=analytics" class="tab-btn <?php echo $view_mode == 'analytics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                Analytics
            </a>
        </div>

        <?php if ($view_mode == "transaction") { ?>
        <div class="transaction-container">
            <!-- Add to Cart Section -->
            <div class="transaction-section">
                <div class="section-title">
                    <i class="fas fa-shopping-cart"></i>
                    Add Items to Cart
                </div>
                
                <form method="POST" class="add-item-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_item">
                                <i class="fas fa-tag"></i>
                                Select Item
                            </label>
                            <select name="id_item" id="id_item" class="form-control select2" required>
                                <option value="">Choose an item...</option>
                                <?php
                                $items_query = mysqli_query($connections, "SELECT id_item, name_item, unit_item, sell_as_pack, sell_as_sachet, quantity_item, selling_price_item, selling_price_individual, expiration_date_item FROM tbl_item WHERE id_user='$user_id' AND quantity_item > 0 AND (expiration_date_item IS NULL OR expiration_date_item > CURDATE())");
                                while ($item = mysqli_fetch_assoc($items_query)) {
                                    $show_item = false;
                                    $price_display = "";
                                    if ($item["unit_item"] == 'piece' && $item["sell_as_sachet"] == 1) {
                                        $show_item = true;
                                        $price_display = "₱" . number_format($item["selling_price_individual"], 2);
                                    } elseif (($item["unit_item"] == 'pack' || $item["unit_item"] == 'box') && $item["sell_as_pack"] == 1) {
                                        $show_item = true;
                                        $price_display = "₱" . number_format($item["selling_price_item"], 2);
                                    }
                                    if ($show_item) {
                                        echo "<option value='{$item['id_item']}'>{$item['name_item']} ({$item['unit_item']} - Stock: {$item['quantity_item']}) - {$price_display}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">
                                <i class="fas fa-sort-numeric-up"></i>
                                Quantity
                            </label>
                            <input type="text" name="quantity" id="quantity" class="form-control" 
                                   placeholder="Enter quantity" onkeypress="return allowNumbersOnly(event);" required>
                            <?php if ($quantityErr): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?php echo $quantityErr; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="btnAddToCart" class="btn-primary">
                            <i class="fas fa-cart-plus"></i>
                            Add to Cart
                        </button>
                    </div>
                </form>
            </div>

            <!-- Cart Section -->
            <div class="transaction-section">
                <div class="section-title">
                    <i class="fas fa-shopping-basket"></i>
                    Shopping Cart
                </div>
                
                <?php if (!empty($cart)): ?>
                <div class="cart-container">
                    <div class="cart-items">
                        <?php foreach ($cart as $id_item => $item): ?>
                        <div class="cart-item-card">
                            <div class="item-info">
                                <h4 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h4>
                                <div class="item-details">
                                    <span class="quantity">Qty: <?php echo $item['quantity']; ?></span>
                                    <span class="subtotal">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                </div>
                            </div>
                            <form method="POST" class="remove-form">
                                <input type="hidden" name="id_item" value="<?php echo $id_item; ?>">
                                <button type="submit" name="btnRemoveFromCart" class="remove-btn" title="Remove Item">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="cart-summary">
                        <div class="total-amount">
                            <span class="total-label">Total Amount:</span>
                            <span class="total-value">₱<?php echo number_format($total, 2); ?></span>
                        </div>
                        <form method="POST">
                            <button type="submit" name="btnComplete" class="btn-complete">
                                <i class="fas fa-check-circle"></i>
                                Complete Transaction
                            </button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Cart is Empty</h3>
                    <p>Add items to your cart to start a transaction</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Earnings Section -->
            <div class="transaction-section">
                <div class="section-title">
                    <i class="fas fa-chart-line"></i>
                    Earnings Overview
                </div>
                
                <div class="earnings-container">
                    <div class="earnings-filter">
                        <label for="earningsSelect">
                            <i class="fas fa-filter"></i>
                            Filter Period:
                        </label>
                        <select id="earningsSelect" class="form-control" onchange="updateEarnings()">
                            <option value="day" <?php if ($earnings_period == "day") echo "selected"; ?>>Today</option>
                            <option value="week" <?php if ($earnings_period == "week") echo "selected"; ?>>This Week</option>
                            <option value="month" <?php if ($earnings_period == "month") echo "selected"; ?>>This Month</option>
                            <option value="year" <?php if ($earnings_period == "year") echo "selected"; ?>>This Year</option>
                        </select>
                    </div>
                    
                    <div class="earnings-display-card">
                        <div class="earnings-amount-large">
                            ₱<?php echo number_format($earnings_total, 2); ?>
                        </div>
                        <div class="earnings-period">
                            <?php echo $period_text[$earnings_period]; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php } elseif ($view_mode == "history") { ?>
        <div class="transaction-container">
            <div class="transaction-section">
                <div class="section-title">
                    <i class="fas fa-history"></i>
                    Transaction History
                </div>
                
                <!-- Period Filter Controls -->
                <div class="controls-section">
                    <form method="GET" action="" class="history-filter-form">
                        <input type="hidden" name="mode" value="history">
                        <div class="form-group">
                            <label for="history_period">
                                <i class="fas fa-filter"></i>
                                Filter Period:
                            </label>
                            <select id="history_period" name="history_period" class="form-control" onchange="this.form.submit()">
                                <option value="all" <?php if ($history_period == "all") echo "selected"; ?>>All Transactions</option>
                                <option value="day" <?php if ($history_period == "day") echo "selected"; ?>>Today</option>
                                <option value="week" <?php if ($history_period == "week") echo "selected"; ?>>This Week</option>
                                <option value="month" <?php if ($history_period == "month") echo "selected"; ?>>This Month</option>
                                <option value="year" <?php if ($history_period == "year") echo "selected"; ?>>This Year</option>
                            </select>
                        </div>
                    </form>
                </div>
                
                <!-- Transaction History Table -->
                <?php if (empty($transactions)): ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <h3>No Transactions Found</h3>
                    <p>No transactions found for <?php echo strtolower($period_text[$history_period]); ?>.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="stock-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-tag"></i> Item Name</th>
                                <th><i class="fas fa-sort-numeric-up"></i> Quantity</th>
                                <th><i class="fas fa-peso-sign"></i> Total Cost</th>
                                <th><i class="fas fa-credit-card"></i> Payment</th>
                                <th><i class="fas fa-check-circle"></i> Status</th>
                                <th><i class="fas fa-clock"></i> Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total = 0;
                            foreach ($transactions as $transaction): 
                                $total += $transaction["total_cost"];
                            ?>
                            <tr>
                                <td class="item-name">
                                    <strong><?php echo htmlspecialchars($transaction["name_item"]); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($transaction["quantity"]); ?></td>
                                <td class="price">₱<?php echo number_format($transaction["total_cost"], 2); ?></td>
                                <td>
                                    <span class="payment-method <?php echo $transaction["payment_method"]; ?>">
                                        <i class="fas fa-<?php echo $transaction["payment_method"] == 'cash' ? 'money-bill' : 'credit-card'; ?>"></i>
                                        <?php echo ucfirst(htmlspecialchars($transaction["payment_method"])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $transaction["status"]; ?>">
                                        <i class="fas fa-<?php echo $transaction["status"] == 'paid' ? 'check' : 'clock'; ?>"></i>
                                        <?php echo ucfirst(htmlspecialchars($transaction["status"])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y - g:i A', strtotime($transaction["date_time"])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="2"><strong>Total (<?php echo $period_text[$history_period]; ?>)</strong></td>
                                <td class="price"><strong>₱<?php echo number_format($total, 2); ?></strong></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php } else { ?>
        <div class="transaction-container">
            <div class="transaction-section">
                <div class="section-title">
                    <i class="fas fa-chart-bar"></i>
                    Sales Analytics
                </div>
                
                <!-- Analytics Filter -->
                <div class="controls-section">
                    <form method="GET" action="" class="history-filter-form">
                        <input type="hidden" name="mode" value="analytics">
                        <div class="form-group">
                            <label for="analyticsSelect">
                                <i class="fas fa-filter"></i>
                                Filter Period (All Analytics):
                            </label>
                            <select id="analyticsSelect" class="form-control" onchange="updateAnalytics()">
                                <option value="day" <?php if ($analytics_period == "day") echo "selected"; ?>>Today</option>
                                <option value="week" <?php if ($analytics_period == "week") echo "selected"; ?>>This Week</option>
                                <option value="month" <?php if ($analytics_period == "month") echo "selected"; ?>>This Month</option>
                                <option value="year" <?php if ($analytics_period == "year") echo "selected"; ?>>This Year</option>
                            </select>
                        </div>
                    </form>
                </div>
                
                <!-- Analytics Summary -->
                <div class="analytics-summary">
                    <div class="summary-card">
                        <h4>Total Sales</h4>
                        <div class="value">₱<?php echo number_format($analytics_data['total_sales'], 2); ?></div>
                        <div class="period"><?php echo $period_text[$analytics_period]; ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Total Transactions</h4>
                        <div class="value"><?php echo $analytics_data['transaction_count']; ?></div>
                        <div class="period"><?php echo $period_text[$analytics_period]; ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Avg. Transaction Value</h4>
                        <div class="value">₱<?php echo $analytics_data['transaction_count'] > 0 ? number_format($analytics_data['total_sales'] / $analytics_data['transaction_count'], 2) : '0.00'; ?></div>
                        <div class="period"><?php echo $period_text[$analytics_period]; ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Loss from Expired Items</h4>
                        <div class="value">₱<?php echo number_format($analytics_data['expired_loss'], 2); ?></div>
                        <div class="period"><?php echo $period_text[$analytics_period]; ?></div>
                    </div>
                </div>
                
                <!-- Top Sold Items -->
                <div class="transaction-section">
                    <div class="section-title">
                        <i class="fas fa-star"></i>
                        Top Selling Items (<?php echo $period_text[$analytics_period]; ?>)
                    </div>
                    <?php if (empty($analytics_data['top_items'])): ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <h3>No Sales Data</h3>
                        <p>No sales recorded for <?php echo strtolower($period_text[$analytics_period]); ?>. Try a different period or complete some transactions.</p>
                    </div>
                    <?php else: ?>
                    <div class="charts-container">
                        <div class="chart-card">
                            <h4>Quantity Sold (Bar Chart)</h4>
                            <div class="chart-wrapper">
                                <canvas id="topItemsBarChart"></canvas>
                            </div>
                        </div>
                        <div class="chart-card">
                            <h4>Revenue Share (Pie Chart)</h4>
                            <div class="chart-wrapper">
                                <canvas id="topItemsPieChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="stock-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-tag"></i> Item Name</th>
                                    <th><i class="fas fa-sort-numeric-up"></i> Total Quantity Sold</th>
                                    <th><i class="fas fa-peso-sign"></i> Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analytics_data['top_items'] as $item): ?>
                                <tr>
                                    <td class="item-name">
                                        <strong><?php echo htmlspecialchars($item['name_item']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['total_quantity']); ?></td>
                                    <td class="price">₱<?php echo number_format($item['total_revenue'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const barCtx = document.getElementById('topItemsBarChart').getContext('2d');
                            const pieCtx = document.getElementById('topItemsPieChart').getContext('2d');
                            <?php if (!empty($analytics_data['top_items'])): ?>
                            const labels = [<?php echo implode(',', array_map(function($item) { return "'" . addslashes($item['name_item']) . "'"; }, $analytics_data['top_items'])); ?>];
                            const quantities = [<?php echo implode(',', array_map(function($item) { return $item['total_quantity']; }, $analytics_data['top_items'])); ?>];
                            const revenues = [<?php echo implode(',', array_map(function($item) { return $item['total_revenue']; }, $analytics_data['top_items'])); ?>];
                            const totalRevenue = <?php echo array_sum(array_column($analytics_data['top_items'], 'total_revenue')); ?>;
                            const revenuePercents = revenues.map(rev => (rev / totalRevenue * 100).toFixed(2));

                            new Chart(barCtx, {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: 'Total Quantity Sold',
                                        data: quantities,
                                        backgroundColor: '#E62727',
                                        borderColor: '#c62d2d',
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            title: {
                                                display: true,
                                                text: 'Quantity Sold',
                                                color: '#2c3e50',
                                                font: { size: 14 }
                                            },
                                            ticks: { stepSize: 1 }
                                        },
                                        x: {
                                            title: {
                                                display: true,
                                                text: 'Items',
                                                color: '#2c3e50',
                                                font: { size: 14 }
                                            }
                                        }
                                    },
                                    plugins: {
                                        legend: { display: false },
                                        title: {
                                            display: true,
                                            text: 'Top Selling Items (<?php echo $period_text[$analytics_period]; ?>)',
                                            color: '#2c3e50',
                                            font: { size: 16 }
                                        }
                                    },
                                    responsive: true,
                                    maintainAspectRatio: false
                                }
                            });

                            new Chart(pieCtx, {
                                type: 'pie',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        data: revenuePercents,
                                        backgroundColor: ['#E62727', '#2ecc71', '#3498db', '#e67e22', '#9b59b6'],
                                        borderColor: ['#c62d2d', '#27ae60', '#2980b9', '#d35400', '#8e44ad'],
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    plugins: {
                                        legend: {
                                            position: 'bottom',
                                            labels: { color: '#2c3e50', font: { size: 12 } }
                                        },
                                        title: {
                                            display: true,
                                            text: 'Revenue Share (<?php echo $period_text[$analytics_period]; ?>)',
                                            color: '#2c3e50',
                                            font: { size: 16 }
                                        }
                                    },
                                    responsive: true,
                                    maintainAspectRatio: false
                                }
                            });
                            <?php endif; ?>
                        });
                    </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php } ?>

        <!-- Notification -->
        <?php if (isset($_GET["notify"])): ?>
        <div class="notification success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_GET["notify"]); ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- JavaScript for Select2 and other functionality -->
    <script>
        function allowNumbersOnly(event) {
            var char = String.fromCharCode(event.which);
            if (!(/[0-9,.]/.test(char) || event.which == 8 || event.which == 46)) {
                event.preventDefault();
                return false;
            }
            return true;
        }

        function updateEarnings() {
            const period = document.getElementById('earningsSelect')?.value;
            if (period) {
                window.location.href = `transaction.php?mode=transaction&period=${period}`;
            }
        }

        function updateAnalytics() {
            const period = document.getElementById('analyticsSelect')?.value;
            if (period) {
                window.location.href = `transaction.php?mode=analytics&analytics_period=${period}`;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            jQuery.noConflict();
            jQuery(document).ready(function($) {
                console.log('Attempting to initialize Select2 for #id_item');
                try {
                    $('#id_item').select2({
                        placeholder: "Choose an item...",
                        allowClear: true,
                        width: '100%',
                        minimumInputLength: 0,
                        dropdownAutoWidth: true,
                        matcher: function(params, data) {
                            if ($.trim(params.term) === '') {
                                return data;
                            }
                            if (typeof data.text === 'undefined') {
                                return null;
                            }
                            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                                return $.extend({}, data, true);
                            }
                            return null;
                        }
                    }).on('select2:open', function() {
                        console.log('Select2 opened successfully');
                    });
                    console.log('Select2 initialized successfully');
                } catch (e) {
                    console.error('Select2 initialization failed:', e);
                }
            });
        });

        // Sidebar-width measurement script
        (function(){
            function updateSidebarWidth(){
                try {
                    var sidebar = document.querySelector('.sidebar-nav');
                    if (!sidebar) return;
                    var style = window.getComputedStyle(sidebar);
                    var rect = sidebar.getBoundingClientRect();
                    var width = Math.round(rect.width) || 0;
                    if (style.display === 'none' || width < 40) width = 0;
                    if (width > 0) {
                        document.documentElement.style.setProperty('--sidebar-width', width + 'px');
                    } else {
                        document.documentElement.style.setProperty('--sidebar-width', '0px');
                    }
                } catch (e) {
                    console.warn('Sidebar width measurement failed', e);
                }
            }
            document.addEventListener('DOMContentLoaded', function(){
                updateSidebarWidth();
                var resizeTimer;
                window.addEventListener('resize', function(){
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(updateSidebarWidth, 120);
                });
                var sb = document.querySelector('.sidebar-nav');
                if (sb) {
                    var mo = new MutationObserver(function(){ updateSidebarWidth(); });
                    mo.observe(sb, { childList: true, attributes: true, subtree: true });
                }
            });
        })();
    </script>

    <style>
        .page-header {
            background: linear-gradient(135deg, #E62727 0%, #c62d2d 100%);
            color: white;
            padding: 30px 25px;
            margin-bottom: 30px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(198,45,45,0.12);
        }
        .header-content {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            gap: 12px;
        }
        .header-text h1 {
            margin: 0 0 5px 0;
            font-size: 28px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-text p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .earnings-display {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            font-weight: 600;
            background: rgba(255,255,255,0.102);
            padding: 8px 12px;
            border-radius: 10px;
            color: #ffffff;
            box-shadow: 0 6px 18px rgba(0,0,0,0.06);
        }
        @media (max-width: 520px) {
            .header-content { flex-direction: column; align-items: flex-start; gap: 10px; }
            .earnings-display { margin-left: 0; align-self: flex-end; }
        }
        .transaction-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .transaction-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin: 25px auto;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-tabs {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab-btn {
            padding: 10px 20px;
            text-decoration: none;
            color: #2c3e50;
            background-color: #ddd;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .tab-btn.active {
            background-color: #bbb;
            color: #fff;
        }
        .tab-btn:hover {
            background-color: #ccc;
        }
        .add-item-form, .history-filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #E62727;
            box-shadow: 0 0 0 3px rgba(230, 39, 39, 0.1);
        }
        /* Style Select2 to match form-control */
        .select2-container--default .select2-selection--single {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
            background: white;
            height: 44px;
        }
        .select2-container--default .select2-selection--single:focus {
            outline: none;
            border-color: #E62727;
            box-shadow: 0 0 0 3px rgba(230, 39, 39, 0.1);
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 20px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 44px;
        }
        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .btn-primary {
            background: #2ecc71;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: #27ae60;
        }
        .remove-btn {
            background: #ff4444;
            color: white;
            border: none;
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .remove-btn:hover {
            background: #cc0000;
        }
        .cart-item-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .item-info h4 {
            margin: 0;
            font-size: 16px;
        }
        .item-details {
            font-size: 14px;
            color: #7f8c8d;
        }
        .cart-summary {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            margin-top: 15px;
        }
        .total-amount {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .btn-complete {
            background: #3498db;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-complete:hover {
            background: #2980b9;
        }
        .empty-cart {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
        }
        .empty-cart i {
            font-size: 40px;
            margin-bottom: 10px;
        }
        .earnings-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }
        .earnings-filter select {
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }
        .earnings-display-card {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .earnings-amount-large {
            font-size: 24px;
            font-weight: 600;
            color: #2ecc71;
        }
        .history-filter-form {
            margin-bottom: 20px;
        }
        .stock-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .stock-table th, .stock-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .stock-table th {
            background-color: #f4f4f4;
            font-weight: 600;
        }
        .price {
            color: #2ecc71;
            font-weight: 600;
        }
        .total-row {
            background: #f9f9f9;
            font-weight: 600;
        }
        .empty-state {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
        }
        .empty-state i {
            font-size: 40px;
            margin-bottom: 10px;
        }
        .notification {
            position: fixed;
            top: 20px;
            right: -300px; /* Start off-screen */
            background: #2ecc71;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: right 0.5s ease-in-out;
            max-width: 300px;
            word-wrap: break-word;
        }
        .notification.success {
            background: #2ecc71;
        }
        .notification.slide-in.visible {
            right: 20px; /* Slide in to visible position */
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .main-content {
            margin-left: calc(var(--sidebar-width, 250px) + 24px);
            padding: 30px;
            flex-grow: 1;
        }
        .analytics-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            flex: 1;
            min-width: 250px;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .summary-card h4 {
            margin: 0 0 10px 0;
            font-size: 18px;
            color: #2c3e50;
        }
        .summary-card .value {
            font-size: 24px;
            font-weight: 600;
            color: #2ecc71;
            margin-bottom: 5px;
        }
        .charts-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        .chart-card {
            flex: 1;
            min-width: 300px;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .chart-wrapper {
            height: 300px;
        }
        .analytics-card {
            flex: 1;
            min-width: 300px;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .chart-container {
            max-width: 600px;
            margin: 20px auto;
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
            .transaction-section { margin: 15px; padding: 20px; }
            .form-group { min-width: 100%; }
            .earnings-container, .analytics-summary, .charts-container { flex-direction: column; text-align: center; }
            .chart-wrapper { height: 250px; }
            .notification { right: -250px; max-width: 250px; }
            .notification.slide-in.visible { right: 10px; }
        }
    </style>
</body>
</html>