<?php
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; form-action 'self'; upgrade-insecure-requests; base-uri 'self';");
date_default_timezone_set('Asia/Manila');
include("nav.php");
include("csrf_helper.php");

if (!file_exists("../connections.php")) {
    die("<h3>Error: Database connection file not found.</h3>");
}
include("../connections.php");

// === CONFIGURATION ===
// Change this to the correct user ID that owns the inventory
$user_id = 50; // <-- UPDATE THIS IF NEEDED (same as other files)

// Fetch user first name for greeting
$query_info = mysqli_query($connections, "SELECT first_name FROM tbl_user WHERE id_user='$user_id'");
$my_info = $query_info && mysqli_num_rows($query_info) > 0 ? mysqli_fetch_assoc($query_info) : ['first_name' => 'User'];
$full_name = $my_info["first_name"];

// Cart storage file (replaces session)
$cart_file = "cart.json";
$cart = [];
if (file_exists($cart_file)) {
    $cart_data = file_get_contents($cart_file);
    $cart = json_decode($cart_data, true) ?: [];
} else {
    file_put_contents($cart_file, json_encode([]));
}

$total = 0.00;
$quantityErr = "";
$view_mode = $_GET["mode"] ?? "transaction";

// Recalculate total
foreach ($cart as $item) {
    $total += floatval($item['subtotal']);
}

// Remove from cart
if (isset($_POST["btnRemoveFromCart"])) {
    validate_csrf_token();
    $id_item = intval($_POST["id_item"]);
    if (isset($cart[$id_item])) {
        unset($cart[$id_item]);
        file_put_contents($cart_file, json_encode($cart));
        echo "<script>alert('Item removed!');</script>";
    }
}

if ($view_mode == "transaction") {
    if (isset($_POST["btnAddToCart"])) {
        validate_csrf_token();
        $id_item = intval($_POST["id_item"]);
        $quantity = intval($_POST["quantity"]);

        if ($id_item > 0 && $quantity > 0) {
            $stmt = $connections->prepare("SELECT name_item, quantity_item, selling_price_item, selling_price_individual, unit_item FROM tbl_item WHERE id_item=? AND id_user=? AND quantity_item >= ? AND (expiration_date_item IS NULL OR expiration_date_item > CURDATE())");
            $stmt->bind_param("iii", $id_item, $user_id, $quantity);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $item = $result->fetch_assoc();
                $price = $item["unit_item"] == 'piece' ? ($item["selling_price_individual"] ?? $item["selling_price_item"]) : $item["selling_price_item"];
                $subtotal = $quantity * $price;

                $cart[$id_item] = [
                    'name' => $item["name_item"],
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal
                ];
                file_put_contents($cart_file, json_encode($cart));
                echo "<script>alert('Item added to cart!');</script>";
            } else {
                echo "<script>alert('Not enough stock or item expired!');</script>";
            }
            $stmt->close();
        } else {
            $quantityErr = "Enter a valid quantity.";
        }
    }

    // Complete transaction
    if (isset($_POST["btnComplete"])) {
        validate_csrf_token();
        if (empty($cart)) {
            echo "<script>alert('Cart is empty!');</script>";
        } else {
            mysqli_begin_transaction($connections);
            $low_stock = [];

            try {
                foreach ($cart as $id_item => $item) {
                    $qty = (int)$item['quantity'];
                    $subtotal = (float)$item['subtotal'];

                    // Lock row for update
                    $lock = $connections->prepare("SELECT quantity_item, name_item FROM tbl_item WHERE id_item=? AND id_user=? FOR UPDATE");
                    $lock->bind_param("ii", $id_item, $user_id);
                    $lock->execute();
                    $res = $lock->get_result();
                    $row = $res->fetch_assoc();
                    $lock->close();

                    if (!$row || $row['quantity_item'] < $qty) {
                        throw new Exception("Insufficient stock for {$row['name_item']}");
                    }

                    // Update stock
                    $new_qty = $row['quantity_item'] - $qty;
                    $upd = $connections->prepare("UPDATE tbl_item SET quantity_item=? WHERE id_item=?");
                    $upd->bind_param("ii", $new_qty, $id_item);
                    $upd->execute();
                    $upd->close();

                    if ($new_qty <= 5) {
                        $low_stock[] = $row['name_item'];
                    }

                    // Record purchase
                    $ins = $connections->prepare("INSERT INTO tbl_purchase (id_user, id_item, quantity, total_cost, payment_method, status, date_time) VALUES (?, ?, ?, ?, 'cash', 'paid', NOW())");
                    $ins->bind_param("iiid", $user_id, $id_item, $qty, $subtotal);
                    $ins->execute();
                    $ins->close();
                }

                mysqli_commit($connections);
                unlink($cart_file); // Clear cart
                touch($cart_file);  // Recreate empty file

                $msg = "Transaction completed successfully! Total: ₱" . number_format($total, 2);
                if (!empty($low_stock)) {
                    $msg .= "\\n\\nLow Stock Alert: " . implode(", ", $low_stock);
                }
                echo "<script>alert('$msg'); window.location='transaction.php';</script>";
            } catch (Exception $e) {
                mysqli_rollback($connections);
                echo "<script>alert('Transaction failed: " . addslashes($e->getMessage()) . "');</script>";
            }
        }
    }
}

// Earnings
$earnings_period = $_GET["period"] ?? "day";
$q = "SELECT COALESCE(SUM(total_cost), 0) AS earnings FROM tbl_purchase WHERE id_user=? AND status='paid'";
if ($earnings_period == "day") $q .= " AND DATE(date_time)=CURDATE()";
elseif ($earnings_period == "week") $q .= " AND YEARWEEK(date_time)=YEARWEEK(CURDATE())";
elseif ($earnings_period == "month") $q .= " AND MONTH(date_time)=MONTH(CURDATE()) AND YEAR(date_time)=YEAR(CURDATE())";
elseif ($earnings_period == "year") $q .= " AND YEAR(date_time)=YEAR(CURDATE())";
$stmt = $connections->prepare($q);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$earnings_total = $row["earnings"] ?? 0.00;
$stmt->close();

$period_text = [
    'day' => 'Today',
    'week' => 'This Week',
    'month' => 'This Month',
    'year' => 'This Year',
    'all' => 'All Transactions'
];

// History
$history_period = $_GET["history_period"] ?? "all";
$transactions = [];
if ($view_mode == "history") {
    $q = "SELECT p.*, i.name_item FROM tbl_purchase p JOIN tbl_item i ON p.id_item=i.id_item WHERE p.id_user=?";
    if ($history_period == "day") $q .= " AND DATE(p.date_time)=CURDATE()";
    elseif ($history_period == "week") $q .= " AND YEARWEEK(p.date_time)=YEARWEEK(CURDATE())";
    elseif ($history_period == "month") $q .= " AND MONTH(p.date_time)=MONTH(CURDATE()) AND YEAR(p.date_time)=YEAR(CURDATE())";
    elseif ($history_period == "year") $q .= " AND YEAR(p.date_time)=YEAR(CURDATE())";
    $q .= " ORDER BY p.date_time DESC";
    $stmt = $connections->prepare($q);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $transactions[] = $row;
    $stmt->close();
}

// Analytics
$analytics_period = $_GET["analytics_period"] ?? "month";
$analytics_data = ['total_sales' => 0.00, 'transaction_count' => 0, 'top_items' => [], 'expired_loss' => 0.00];
if ($view_mode == "analytics") {
    // Total sales & count
    $q = "SELECT COALESCE(SUM(total_cost),0) AS total_sales, COUNT(*) AS transaction_count FROM tbl_purchase WHERE id_user=? AND status='paid'";
    if ($analytics_period == "day") $q .= " AND DATE(date_time)=CURDATE()";
    elseif ($analytics_period == "week") $q .= " AND YEARWEEK(date_time)=YEARWEEK(CURDATE())";
    elseif ($analytics_period == "month") $q .= " AND MONTH(date_time)=MONTH(CURDATE()) AND YEAR(date_time)=YEAR(CURDATE())";
    elseif ($analytics_period == "year") $q .= " AND YEAR(date_time)=YEAR(CURDATE())";
    $stmt = $connections->prepare($q);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $analytics_data['total_sales'] = $row['total_sales'];
    $analytics_data['transaction_count'] = $row['transaction_count'];
    $stmt->close();

    // Top items
    $q = "SELECT i.name_item, SUM(p.quantity) AS total_quantity, SUM(p.total_cost) AS total_revenue 
          FROM tbl_purchase p JOIN tbl_item i ON p.id_item=i.id_item 
          WHERE p.id_user=? AND p.status='paid'";
    if ($analytics_period == "day") $q .= " AND DATE(p.date_time)=CURDATE()";
    elseif ($analytics_period == "week") $q .= " AND YEARWEEK(p.date_time)=YEARWEEK(CURDATE())";
    elseif ($analytics_period == "month") $q .= " AND MONTH(p.date_time)=MONTH(CURDATE()) AND YEAR(p.date_time)=YEAR(CURDATE())";
    elseif ($analytics_period == "year") $q .= " AND YEAR(p.date_time)=YEAR(CURDATE())";
    $q .= " GROUP BY p.id_item ORDER BY total_quantity DESC LIMIT 5";
    $stmt = $connections->prepare($q);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $analytics_data['top_items'][] = $row;
    $stmt->close();

    // Expired loss
    $q = "SELECT quantity_item, selling_price_item, selling_price_individual, unit_item 
          FROM tbl_item WHERE id_user=? AND expiration_date_item IS NOT NULL AND expiration_date_item <= CURDATE() AND quantity_item > 0";
    $stmt = $connections->prepare($q);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $price = $row['unit_item'] == 'piece' ? ($row['selling_price_individual'] ?? $row['selling_price_item']) : $row['selling_price_item'];
        $analytics_data['expired_loss'] += $row['quantity_item'] * $price;
    }
    $stmt->close();
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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

        <!-- Tabs -->
        <div class="filter-tabs">
            <a href="?mode=transaction" class="tab-btn <?php echo $view_mode == 'transaction' ? 'active' : ''; ?>">
                <i class="fas fa-cash-register"></i> New Transaction
            </a>
            <a href="?mode=history" class="tab-btn <?php echo $view_mode == 'history' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Transaction History
            </a>
            <a href="?mode=analytics" class="tab-btn <?php echo $view_mode == 'analytics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Analytics
            </a>
        </div>

        <?php if ($view_mode == "transaction"): ?>
        <div class="transaction-container">
            <!-- Add to Cart -->
            <div class="transaction-section">
                <div class="section-title"><i class="fas fa-shopping-cart"></i> Add Items to Cart</div>
                <form method="POST" class="add-item-form">
                    <?php echo csrf_token_field(); ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Select Item</label>
                            <select name="id_item" id="id_item" class="form-control select2" required>
                                <option value="">Choose an item...</option>
                                <?php
                                $items = mysqli_query($connections, "SELECT id_item, name_item, unit_item, sell_as_pack, sell_as_sachet, quantity_item, selling_price_item, selling_price_individual FROM tbl_item WHERE id_user='$user_id' AND quantity_item > 0 AND (expiration_date_item IS NULL OR expiration_date_item > CURDATE())");
                                while ($i = mysqli_fetch_assoc($items)) {
                                    $show = false; $price = "";
                                    if ($i["unit_item"] == 'piece' && $i["sell_as_sachet"] == 1) {
                                        $show = true; $price = "₱" . number_format($i["selling_price_individual"], 2);
                                    } elseif (in_array($i["unit_item"], ['pack', 'box']) && $i["sell_as_pack"] == 1) {
                                        $show = true; $price = "₱" . number_format($i["selling_price_item"], 2);
                                    }
                                    if ($show) {
                                        echo "<option value='{$i['id_item']}'>{$i['name_item']} ({$i['unit_item']} - Stock: {$i['quantity_item']}) - {$price}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-sort-numeric-up"></i> Quantity</label>
                            <input type="text" name="quantity" class="form-control" placeholder="Enter quantity" onkeypress="return /[0-9]/.test(String.fromCharCode(event.which)) || event.which == 8" required>
                            <?php if ($quantityErr): ?><div class="error-message"><i class="fas fa-exclamation-triangle"></i> <?php echo $quantityErr; ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="btnAddToCart" class="btn-primary"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                    </div>
                </form>
            </div>

            <!-- Cart -->
            <div class="transaction-section">
                <div class="section-title"><i class="fas fa-shopping-basket"></i> Shopping Cart</div>
                <?php if (!empty($cart)): ?>
                <div class="cart-container">
                    <div class="cart-items">
                        <?php foreach ($cart as $id => $item): ?>
                        <div class="cart-item-card">
                            <div class="item-info">
                                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                <div class="item-details">
                                    <span class="quantity">Qty: <?php echo $item['quantity']; ?></span>
                                    <span class="subtotal">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                </div>
                            </div>
                            <form method="POST" class="remove-form">
                                <?php echo csrf_token_field(); ?>
                                <input type="hidden" name="id_item" value="<?php echo $id; ?>">
                                <button type="submit" name="btnRemoveFromCart" class="remove-btn" title="Remove"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="cart-summary">
                        <div class="total-amount">
                            <span class="total-label">Total:</span>
                            <span class="total-value">₱<?php echo number_format($total, 2); ?></span>
                        </div>
                        <form method="POST">
                            <?php echo csrf_token_field(); ?>
                            <button type="submit" name="btnComplete" class="btn-complete"><i class="fas fa-check-circle"></i> Complete Transaction</button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-cart"><i class="fas fa-shopping-cart"></i><h3>Cart is Empty</h3><p>Add items to start</p></div>
                <?php endif; ?>
            </div>

            <!-- Earnings -->
            <div class="transaction-section">
                <div class="section-title"><i class="fas fa-chart-line"></i> Earnings Overview</div>
                <div class="earnings-container">
                    <div class="earnings-filter">
                        <label><i class="fas fa-filter"></i> Filter:</label>
                        <select class="form-control" onchange="window.location='?mode=transaction&period='+this.value">
                            <option value="day" <?php echo $earnings_period=='day'?'selected':'' ?>>Today</option>
                            <option value="week" <?php echo $earnings_period=='week'?'selected':'' ?>>This Week</option>
                            <option value="month" <?php echo $earnings_period=='month'?'selected':'' ?>>This Month</option>
                            <option value="year" <?php echo $earnings_period=='year'?'selected':'' ?>>This Year</option>
                        </select>
                    </div>
                    <div class="earnings-display-card">
                        <div class="earnings-amount-large">₱<?php echo number_format($earnings_total, 2); ?></div>
                        <div class="earnings-period"><?php echo $period_text[$earnings_period]; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($view_mode == "history"): ?>
        <!-- HISTORY SECTION -->
        <div class="transaction-container">
            <div class="transaction-section">
                <div class="section-title"><i class="fas fa-history"></i> Transaction History</div>
                <div class="controls-section">
                    <form method="GET" class="history-filter-form">
                        <input type="hidden" name="mode" value="history">
                        <div class="form-group">
                            <label><i class="fas fa-filter"></i> Filter:</label>
                            <select name="history_period" class="form-control" onchange="this.form.submit()">
                                <option value="all" <?php echo $history_period=='all'?'selected':'' ?>>All</option>
                                <option value="day" <?php echo $history_period=='day'?'selected':'' ?>>Today</option>
                                <option value="week" <?php echo $history_period=='week'?'selected':'' ?>>This Week</option>
                                <option value="month" <?php echo $history_period=='month'?'selected':'' ?>>This Month</option>
                                <option value="year" <?php echo $history_period=='year'?'selected':'' ?>>This Year</option>
                            </select>
                        </div>
                    </form>
                </div>
                <?php if (empty($transactions)): ?>
                <div class="empty-state"><i class="fas fa-receipt"></i><h3>No Transactions</h3><p>No data for <?php echo strtolower($period_text[$history_period]); ?></p></div>
                <?php else: $hist_total = 0; foreach ($transactions as $t) $hist_total += $t['total_cost']; ?>
                <div class="table-responsive">
                    <table class="stock-table">
                        <thead>
                            <tr>
                                <th>Item</th><th>Qty</th><th>Cost</th><th>Payment</th><th>Status</th><th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($t['name_item']); ?></strong></td>
                                <td><?php echo $t['quantity']; ?></td>
                                <td class="price">₱<?php echo number_format($t['total_cost'], 2); ?></td>
                                <td><span class="payment-method <?php echo $t['payment_method']; ?>"><i class="fas fa-money-bill"></i> Cash</span></td>
                                <td><span class="status-badge paid"><i class="fas fa-check"></i> Paid</span></td>
                                <td><?php echo date('M d, Y - g:i A', strtotime($t['date_time'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="2"><strong>Total (<?php echo $period_text[$history_period]; ?>)</strong></td>
                                <td class="price"><strong>₱<?php echo number_format($hist_total, 2); ?></strong></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php elseif ($view_mode == "analytics"): ?>
        <!-- ANALYTICS SECTION -->
        <div class="transaction-container">
            <div class="transaction-section">
                <div class="section-title"><i class="fas fa-chart-bar"></i> Sales Analytics</div>
                <div class="controls-section">
                    <form method="GET" class="history-filter-form">
                        <input type="hidden" name="mode" value="analytics">
                        <div class="form-group">
                            <label><i class="fas fa-filter"></i> Period:</label>
                            <select name="analytics_period" class="form-control" onchange="this.form.submit()">
                                <option value="day" <?php echo $analytics_period=='day'?'selected':'' ?>>Today</option>
                                <option value="week" <?php echo $analytics_period=='week'?'selected':'' ?>>This Week</option>
                                <option value="month" <?php echo $analytics_period=='month'?'selected':'' ?>>This Month</option>
                                <option value="year" <?php echo $analytics_period=='year'?'selected':'' ?>>This Year</option>
                            </select>
                        </div>
                    </form>
                </div>

                <div class="analytics-summary">
                    <div class="summary-card">
                        <h4>Total Sales</h4>
                        <div class="value">₱<?php echo number_format($analytics_data['total_sales'], 2); ?></div>
                        <div class="period"><?php echo $period_text[$analytics_period]; ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Transactions</h4>
                        <div class="value"><?php echo $analytics_data['transaction_count']; ?></div>
                        <div class="period"><?php echo $period_text[$analytics_period]; ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Avg. Value</h4>
                        <div class="value">₱<?php echo $analytics_data['transaction_count'] > 0 ? number_format($analytics_data['total_sales'] / $analytics_data['transaction_count'], 2) : '0.00'; ?></div>
                        <div class="period"><?php echo $period_text[$analytics_period]; ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Expired Loss</h4>
                        <div class="value">₱<?php echo number_format($analytics_data['expired_loss'], 2); ?></div>
                        <div class="period"><?php echo $period_text[$analytics_period]; ?></div>
                    </div>
                </div>

                <div class="transaction-section">
                    <div class="section-title"><i class="fas fa-star"></i> Top Selling Items</div>
                    <?php if (empty($analytics_data['top_items'])): ?>
                    <div class="empty-state"><i class="fas fa-chart-bar"></i><h3>No Sales</h3></div>
                    <?php else: ?>
                    <div class="charts-container">
                        <div class="chart-card">
                            <h4>Quantity Sold</h4>
                            <div class="chart-wrapper"><canvas id="barChart"></canvas></div>
                        </div>
                        <div class="chart-card">
                            <h4>Revenue Share</h4>
                            <div class="chart-wrapper"><canvas id="pieChart"></canvas></div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="stock-table">
                            <thead><tr><th>Item</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
                            <tbody>
                                <?php foreach ($analytics_data['top_items'] as $item): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['name_item']); ?></strong></td>
                                    <td><?php echo $item['total_quantity']; ?></td>
                                    <td class="price">₱<?php echo number_format($item['total_revenue'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <script>
                        const items = <?php echo json_encode($analytics_data['top_items']); ?>;
                        const labels = items.map(i => i.name_item);
                        const qty = items.map(i => i.total_quantity);
                        const rev = items.map(i => i.total_revenue);
                        const totalRev = rev.reduce((a,b) => a+b, 0);
                        const percent = rev.map(r => ((r/totalRev)*100).toFixed(1));

                        new Chart(document.getElementById('barChart'), {
                            type: 'bar', data: { labels, datasets: [{ label: 'Qty Sold', data: qty, backgroundColor: '#E62727' }] },
                            options: { responsive: true, maintainAspectRatio: false }
                        });
                        new Chart(document.getElementById('pieChart'), {
                            type: 'pie', data: { labels, datasets: [{ data: percent, backgroundColor: ['#E62727','#2ecc71','#3498db','#e67e22','#9b59b6'] }] },
                            options: { responsive: true, maintainAspectRatio: false }
                        });
                    </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script>
        $(document).ready(function() {
            $('#id_item').select2({ placeholder: "Choose an item...", width: '100%' });
        });
    </script>

    <style>
        .page-header{background:linear-gradient(135deg,#E62727,#c62d2d);color:#fff;padding:30px 25px;margin-bottom:30px;border-radius:12px;box-shadow:0 10px 30px rgba(198,45,45,.12)}
        .header-content{display:flex;justify-content:flex-start;align-items:center;gap:12px;max-width:1200px;margin:auto}
        .header-text h1{margin:0 0 5px;font-size:28px;font-weight:600;display:flex;align-items:center;gap:10px}
        .header-text p{margin:0;opacity:.9;font-size:16px}
        .earnings-display{margin-left:auto;background:rgba(255,255,255,.1);padding:8px 12px;border-radius:10px;font-weight:600}
        .filter-tabs{display:flex;justify-content:center;gap:10px;margin-bottom:20px}
        .tab-btn{padding:10px 20px;background:#ddd;color:#2c3e50;border-radius:25px;font-weight:600;transition:.3s;display:flex;align-items:center;gap:5px}
        .tab-btn.active,.tab-btn:hover{background:#bbb;color:#fff}
        .transaction-section{background:#fff;border-radius:15px;padding:30px;margin:25px auto;box-shadow:0 5px 20px rgba(0,0,0,.1)}
        .section-title{font-size:20px;font-weight:600;color:#2c3e50;margin-bottom:20px;padding-bottom:10px;border-bottom:2px solid #e9ecef;display:flex;align-items:center;gap:10px}
        .form-row{display:flex;gap:15px;flex-wrap:wrap}
        .form-group{flex:1;min-width:200px}
        .form-control{width:100%;padding:12px 15px;border:2px solid #e9ecef;border-radius:8px;font-size:14px;background:#fff}
        .form-control:focus{border-color:#E62727;outline:none;box-shadow:0 0 0 3px rgba(230,39,39,.1)}
        .select2-container--default .select2-selection--single{border:2px solid #e9ecef;border-radius:8px;padding:10px 15px;height:44px}
        .btn-primary{background:#2ecc71;color:#fff;padding:12px 25px;border:none;border-radius:25px;font-weight:600;cursor:pointer}
        .btn-primary:hover{background:#27ae60}
        .remove-btn{background:#ff4444;color:#fff;border:none;padding:8px;border-radius:50%;cursor:pointer}
        .remove-btn:hover{background:#cc0000}
        .cart-item-card{display:flex;justify-content:space-between;align-items:center;padding:15px;margin-bottom:10px;background:#f9f9f9;border-radius:8px}
        .cart-summary{padding:15px;background:#f9f9f9;border-radius:8px;margin-top:15px}
        .total-amount{font-size:18px;font-weight:600;margin-bottom:10px}
        .btn-complete{background:#3498db;color:#fff;padding:12px 25px;border:none;border-radius:25px;font-weight:600;cursor:pointer}
        .btn-complete:hover{background:#2980b9}
        .empty-cart,.empty-state{text-align:center;padding:20px;color:#7f8c8d}
        .empty-cart i,.empty-state i{font-size:40px;margin-bottom:10px}
        .earnings-container{display:flex;justify-content:space-between;align-items:center;gap:20px}
        .earnings-display-card{background:#f9f9f9;padding:20px;border-radius:8px;text-align:center}
        .earnings-amount-large{font-size:24px;font-weight:600;color:#2ecc71}
        .stock-table{width:100%;border-collapse:collapse;margin-top:20px}
        .stock-table th,.stock-table td{padding:12px;text-align:left;border-bottom:1px solid #ddd}
        .stock-table th{background:#f4f4f4;font-weight:600}
        .price{color:#2ecc71;font-weight:600}
        .total-row{background:#f9f9f9;font-weight:600}
        .analytics-summary{display:flex;flex-wrap:wrap;gap:20px;margin-bottom:30px}
        .summary-card{flex:1;min-width:250px;background:#f9f9f9;padding:20px;border-radius:8px;text-align:center;box-shadow:0 2px 5px rgba(0,0,0,.1)}
        .summary-card .value{font-size:24px;font-weight:600;color:#2ecc71}
        .charts-container{display:flex;flex-wrap:wrap;gap:20px;margin-bottom:30px}
        .chart-card{flex:1;min-width:300px;background:#f9f9f9;padding:20px;border-radius:8px}
        .chart-wrapper{height:300px}
        @media (max-width:768px){.main-content{margin-left:0;padding:15px}.transaction-section{padding:20px;margin:15px}.form-group{min-width:100%}}
    </style>
    
</body>
</html>