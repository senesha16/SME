<?php
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; form-action 'self'; upgrade-insecure-requests; base-uri 'self';");
date_default_timezone_set('Asia/Manila');
include("nav.php");
include("../connections.php");

// === CONFIGURATION ===
// Change this to the correct user ID that owns the inventory
$user_id = 50; // <-- UPDATE THIS IF NEEDED (same as other files)

// Determine view mode and expiry filter
$view_mode = isset($_GET["mode"]) ? $_GET["mode"] : "stock";
$expiry_filter = isset($_GET["expiry_filter"]) ? $_GET["expiry_filter"] : "expiring_soon";
$search = isset($_GET["search"]) ? trim($_GET["search"]) : "";
$category_filter = isset($_GET["category"]) ? trim($_GET["category"]) : "";
$items = [];

if ($view_mode == "selling") {
    $query = "SELECT * FROM tbl_item WHERE id_user=? AND quantity_item > 0 AND (
        unit_item IN ('piece', 'sachet') OR 
        (unit_item IN ('pack', 'box') AND sell_as_pack = 1)
    ) AND (expiration_date_item IS NULL OR expiration_date_item > CURDATE())";
    $params = [$user_id];
    $types = "i";
    if (!empty($search)) {
        $search = "%$search%";
        $query .= " AND (name_item LIKE ? OR category_item LIKE ? OR brand_item LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= "sss";
    }
    if (!empty($category_filter)) {
        $query .= " AND category_item = ?";
        $params[] = $category_filter;
        $types .= "s";
    }
    $query .= " ORDER BY name_item ASC";
    $stmt = $connections->prepare($query);
    if ($stmt === false) {
        error_log("view_stock.php: Prepare failed for selling query: " . mysqli_error($connections));
        $items = [];
    } else {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
    }
} elseif ($view_mode == "expiry") {
    if ($expiry_filter == "expired") {
        $query = "SELECT * FROM tbl_item WHERE id_user=? AND expiration_date_item IS NOT NULL 
                  AND expiration_date_item <= CURDATE()";
    } else {
        $query = "SELECT * FROM tbl_item WHERE id_user=? AND expiration_date_item IS NOT NULL 
                  AND expiration_date_item > CURDATE() 
                  AND expiration_date_item <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    }
    $params = [$user_id];
    $types = "i";
    if (!empty($search)) {
        $search = "%$search%";
        $query .= " AND (name_item LIKE ? OR category_item LIKE ? OR brand_item LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= "sss";
    }
    if (!empty($category_filter)) {
        $query .= " AND category_item = ?";
        $params[] = $category_filter;
        $types .= "s";
    }
    $query .= $expiry_filter == "expired" ? " ORDER BY expiration_date_item DESC" : " ORDER BY expiration_date_item ASC";
    $stmt = $connections->prepare($query);
    if ($stmt === false) {
        error_log("view_stock.php: Prepare failed for expiry query ($expiry_filter): " . mysqli_error($connections));
        $items = [];
    } else {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
    }
} elseif ($view_mode == "out_of_stock") {
    $query = "SELECT * FROM tbl_item WHERE id_user = ? AND quantity_item = 0";
    $params = [$user_id];
    $types = "i";
    if (!empty($search)) {
        $search = "%$search%";
        $query .= " AND (name_item LIKE ? OR category_item LIKE ? OR brand_item LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= "sss";
    }
    if (!empty($category_filter)) {
        $query .= " AND category_item = ?";
        $params[] = $category_filter;
        $types .= "s";
    }
    $query .= " ORDER BY name_item ASC";
    $stmt = $connections->prepare($query);
    if ($stmt === false) {
        error_log("view_stock.php: Prepare failed for out_of_stock query: " . mysqli_error($connections));
        $items = [];
    } else {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
    }
} else {
    // Default: All Stock mode
    $items = [];

    // Available items
    $query_available = "SELECT * FROM tbl_item WHERE id_user=? AND quantity_item > 0 
                        AND (expiration_date_item IS NULL OR expiration_date_item > CURDATE())";
    $params = [$user_id];
    $types = "i";
    if (!empty($search)) {
        $search = "%$search%";
        $query_available .= " AND (name_item LIKE ? OR category_item LIKE ? OR brand_item LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= "sss";
    }
    if (!empty($category_filter)) {
        $query_available .= " AND category_item = ?";
        $params[] = $category_filter;
        $types .= "s";
    }
    $query_available .= " ORDER BY name_item ASC";
    $stmt = $connections->prepare($query_available);
    if ($stmt !== false) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
    }

    // Separator for Out of Stock
    if (!empty($items)) {
        $items[] = ['is_separator' => true, 'label' => 'Out of Stock'];
    }

    // Out of stock items
    $query_out_of_stock = "SELECT * FROM tbl_item WHERE id_user=? AND quantity_item = 0";
    $params = [$user_id];
    $types = "i";
    if (!empty($search)) {
        $search = "%$search%";
        $query_out_of_stock .= " AND (name_item LIKE ? OR category_item LIKE ? OR brand_item LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= "sss";
    }
    if (!empty($category_filter)) {
        $query_out_of_stock .= " AND category_item = ?";
        $params[] = $category_filter;
        $types .= "s";
    }
    $query_out_of_stock .= " ORDER BY name_item ASC";
    $stmt = $connections->prepare($query_out_of_stock);
    if ($stmt !== false) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
    }

    // Separator for Expired
    if (!empty($items)) {
        $items[] = ['is_separator' => true, 'label' => 'Expired'];
    }

    // Expired items (in stock)
    $query_expired = "SELECT * FROM tbl_item WHERE id_user=? AND quantity_item > 0 
                      AND expiration_date_item IS NOT NULL AND expiration_date_item <= CURDATE()";
    $params = [$user_id];
    $types = "i";
    if (!empty($search)) {
        $search = "%$search%";
        $query_expired .= " AND (name_item LIKE ? OR category_item LIKE ? OR brand_item LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= "sss";
    }
    if (!empty($category_filter)) {
        $query_expired .= " AND category_item = ?";
        $params[] = $category_filter;
        $types .= "s";
    }
    $query_expired .= " ORDER BY name_item ASC";
    $stmt = $connections->prepare($query_expired);
    if ($stmt !== false) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
    }
}

// Fetch unique categories for dropdown
$categories = [];
$category_query = mysqli_query($connections, "SELECT DISTINCT category_item FROM tbl_item WHERE id_user='$user_id' AND category_item IS NOT NULL ORDER BY category_item ASC");
while ($cat_row = mysqli_fetch_assoc($category_query)) {
    $categories[] = $cat_row['category_item'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Stock - SME Dashboard</title>
    <link rel="stylesheet" href="user-stock.css">
    <link rel="stylesheet" href="user-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .page-header {
            background: linear-gradient(135deg, #E62727 0%, #c62d2d 100%);
            color: white;
            padding: 28px 22px;
            margin-bottom: 30px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(198,45,45,0.12);
        }
        .header-content {
            display:flex;
            justify-content:space-between;
            align-items:center;
            max-width:1200px;
            margin:0 auto;
            gap:12px;
            width:100%;
        }
        .header-text { text-align:left; }
        .header-text h2 {
            margin:0;
            font-size:2.2rem;
            display:flex;
            align-items:center;
            gap:12px;
            color: #fff;
        }
        .header-text p {
            margin:0;
            color: rgba(255,255,255,0.92);
            font-size:1.05rem;
        }
        .header-actions { margin-left:auto; display:flex; align-items:center; gap:12px; }
        .header-actions .btn-primary {
            background: #fff;
            color: #E62727;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .header-actions .btn-primary:hover { background:#f7f7f7; }

        .notification {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
            margin-bottom: 20px;
        }
        .filter-tabs {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 10px 20px;
            text-decoration: none;
            color: black;
            background-color: #ddd;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-btn.active {
            background-color: #bbb;
        }
        .controls-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 20px;
        }
        .search-container .search-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-container input[type="text"] {
            padding: 8px;
            width: 200px;
            border: 2px solid #f0f0f0;
            border-radius: 5px;
        }
        .search-container select {
            padding: 8px;
            border: 2px solid #f0f0f0;
            border-radius: 5px;
            background-color: white;
        }
        .search-container .search-btn {
            padding: 8px 15px;
            background: #E62727;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .action-buttons .btn-primary {
            padding: 10px 20px;
            background: #E62727;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stock-container {
            margin-top: 20px;
        }
        .table-responsive {
            overflow-x: auto;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 6px 18px rgba(16,24,40,0.04);
            border: 1px solid #eef4ff;
        }
        .stock-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
            background: #fff;
        }
        .stock-table thead th:first-child { border-top-left-radius: 12px; }
        .stock-table thead th:last-child  { border-top-right-radius: 12px; }
        .stock-table tbody tr:last-child td:first-child { border-bottom-left-radius: 12px; }
        .stock-table tbody tr:last-child td:last-child  { border-bottom-right-radius: 12px; }

        .stock-table th, .stock-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .stock-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .stock-table td {
            vertical-align: middle;
        }
        .item-name strong {
            color: #2c3e50;
        }
        .quantity-cell .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-left: 5px;
        }
        .out-of-stock {
            color: red;
            background: rgba(255, 0, 0, 0.1);
        }
        .low-stock {
            color: orange;
            background: rgba(255, 165, 0, 0.1);
        }
        .expired {
            color: red;
            font-weight: bold;
        }
        .warning {
            color: orange;
            font-weight: bold;
        }
        .expiry-cell i {
            margin-right: 5px;
        }
        .actions .action-btn {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 0 5px;
        }
        .edit-btn {
            background: #3498db;
        }
        .delete-btn {
            background: #e74c3c;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #7f8c8d;
        }
        .expiry-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .expiry-btn {
            padding: 10px 20px;
            text-decoration: none;
            color: white;
            background-color: #E62727;
            border-radius: 5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .expiry-btn.active {
            background-color: #c62d2d;
        }
        .separator-row {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            border-top: 2px solid #ccc;
        }
        .separator-row th {
            padding: 10px;
            color: #2c3e50;
            font-size: 1.1rem;
        }
        @media (max-width: 768px) {
            .filter-tabs, .controls-section {
                flex-direction: column;
                gap: 15px;
            }
            .search-container input[type="text"], .search-container select {
                width: 100%;
            }
            .stock-table th, .stock-table td {
                padding: 8px;
                font-size: 0.9rem;
            }
            .main-content { margin-left: 0; padding: 15px; }
            .expiry-buttons {
                flex-direction: column;
                align-items: stretch;
            }
        }
        .main-content {
            margin-left: calc(var(--sidebar-width, 250px) + 24px);
            padding: 30px;
            flex-grow: 1;
        }
    </style>
</head>
<body>
    <main class="main-content">
        <div class="page-header">
            <div class="header-content">
                <div class="header-text">
                    <h2><i class="fas fa-boxes"></i> Inventory Management</h2>
                    <p>View and manage your stock inventory</p>
                </div>
                <div class="header-actions">
                    <a href="add_item.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Add New Item
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($_GET["notify"])): ?>
            <div class="notification">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_GET["notify"]); ?></span>
            </div>
        <?php endif; ?>

        <div class="filter-tabs">
            <a href="?mode=stock" class="tab-btn <?php echo $view_mode == 'stock' ? 'active' : ''; ?>">
                <i class="fas fa-warehouse"></i> All Stock
            </a>
            <a href="?mode=selling" class="tab-btn <?php echo $view_mode == 'selling' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i> Selling Items
            </a>
            <a href="?mode=expiry" class="tab-btn <?php echo $view_mode == 'expiry' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-times"></i> Expiry Status
            </a>
            <a href="?mode=out_of_stock" class="tab-btn <?php echo $view_mode == 'out_of_stock' ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-triangle"></i> Out of Stock
            </a>
        </div>

        <?php if ($view_mode == "expiry"): ?>
            <div class="expiry-buttons">
                <a href="?mode=expiry&expiry_filter=expiring_soon" class="expiry-btn <?php echo $expiry_filter == 'expiring_soon' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-circle"></i> Expiring Soon
                </a>
                <a href="?mode=expiry&expiry_filter=expired" class="expiry-btn <?php echo $expiry_filter == 'expired' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-triangle"></i> Expired
                </a>
            </div>
        <?php endif; ?>

        <div class="controls-section">
            <div class="search-container">
                <form method="GET" action="" class="search-form">
                    <input type="hidden" name="mode" value="<?php echo $view_mode; ?>">
                    <?php if ($view_mode == "expiry"): ?>
                        <input type="hidden" name="expiry_filter" value="<?php echo $expiry_filter; ?>">
                    <?php endif; ?>
                    <div class="search-input-group">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, category, or brand...">
                        <select name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter == $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="stock-container">
            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No items found</h3>
                    <p>Try adjusting your search criteria or add new items to your inventory.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="stock-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-tag"></i> Name</th>
                                <th><i class="fas fa-list"></i> Category</th>
                                <th><i class="fas fa-copyright"></i> Brand</th>
                                <th><i class="fas fa-expand-arrows-alt"></i> Size</th>
                                <th><i class="fas fa-sort-numeric-up"></i> Quantity</th>
                                <th><i class="fas fa-ruler"></i> Unit</th>
                                <th><i class="fas fa-receipt"></i> Cost Price</th>
                                <th><i class="fas fa-peso-sign"></i> Selling Price</th>
                                <th><i class="fas fa-calendar-times"></i> Expiration</th>
                                <th><i class="fas fa-cubes"></i> Items</th>
                                <th><i class="fas fa-cogs"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): 
                                if (isset($item['is_separator']) && $item['is_separator']): ?>
                                    <tr class="separator-row">
                                        <th colspan="11"><?php echo htmlspecialchars($item['label']); ?></th>
                                    </tr>
                                <?php else:
                                    $selling_price = "";
                                    if ($item["unit_item"] == "piece" || $item["unit_item"] == "sachet") {
                                        $selling_price = "₱" . number_format($item["selling_price_individual"], 2);
                                    } elseif ($item["unit_item"] == "pack" || $item["unit_item"] == "box") {
                                        if ($view_mode == "selling") {
                                            if ($item["sell_as_pack"] == 1) {
                                                $selling_price = "₱" . number_format($item["selling_price_item"], 2);
                                            }
                                        } else {
                                            if ($item["sell_as_pack"] == 1 && $item["sell_as_sachet"] == 1) {
                                                $selling_price = "Pack: ₱" . number_format($item["selling_price_item"], 2) . " / Individual: ₱" . number_format($item["selling_price_individual"], 2);
                                            } elseif ($item["sell_as_pack"] == 1) {
                                                $selling_price = "₱" . number_format($item["selling_price_item"], 2);
                                            } elseif ($item["sell_as_sachet"] == 1) {
                                                $selling_price = "₱" . number_format($item["selling_price_individual"], 2);
                                            }
                                        }
                                    } else {
                                        $selling_price = "₱" . number_format($item["selling_price_item"], 2);
                                    }

                                    $expiry_class = "";
                                    $expiry_icon = "fas fa-calendar-check";
                                    if ($view_mode == "expiry" || ($item["expiration_date_item"] && $item["expiration_date_item"] <= date('Y-m-d'))) {
                                        $today = date('Y-m-d');
                                        $one_week_later = date('Y-m-d', strtotime('+7 days'));
                                        if ($item["expiration_date_item"] <= $today) {
                                            $expiry_class = "expired";
                                            $expiry_icon = "fas fa-exclamation-triangle";
                                        } elseif ($view_mode == "expiry" && $item["expiration_date_item"] <= $one_week_later) {
                                            $expiry_class = "warning";
                                            $expiry_icon = "fas fa-exclamation-circle";
                                        }
                                    }

                                    $stock_class = "";
                                    if ($item["quantity_item"] == 0) {
                                        $stock_class = "out-of-stock";
                                    } elseif ($item["quantity_item"] <= 5) {
                                        $stock_class = "low-stock";
                                    }
                                ?>
                                <tr class="<?php echo $stock_class; ?>">
                                    <td class="item-name"><strong><?php echo htmlspecialchars($item["name_item"]); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item["category_item"]); ?></td>
                                    <td><?php echo htmlspecialchars($item["brand_item"]); ?></td>
                                    <td><?php echo htmlspecialchars($item["size_item"]); ?></td>
                                    <td class="quantity-cell">
                                        <span class="quantity"><?php echo htmlspecialchars($item["quantity_item"]); ?></span>
                                        <?php if ($item["quantity_item"] == 0): ?>
                                            <span class="status-badge out-of-stock">Out of Stock</span>
                                        <?php elseif ($item["quantity_item"] <= 5): ?>
                                            <span class="status-badge low-stock">Low Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item["unit_item"]); ?></td>
                                    <td class="price">₱<?php echo number_format($item["cost_price_item"], 2); ?></td>
                                    <td class="price"><?php echo $selling_price; ?></td>
                                    <td class="expiry-cell <?php echo $expiry_class; ?>">
                                        <i class="<?php echo $expiry_icon; ?>"></i>
                                        <?php echo htmlspecialchars($item["expiration_date_item"] ?: 'N/A'); ?>
                                        <?php if ($item["expiration_date_item"] && $item["expiration_date_item"] <= date('Y-m-d')): ?>
                                            <span class="status-badge expired">Expired</span>
                                        <?php elseif ($view_mode == "expiry" && $item["expiration_date_item"]): ?>
                                            <?php if ($item["expiration_date_item"] <= $today): ?>
                                                <span class="status-badge expired">Expired</span>
                                            <?php elseif ($item["expiration_date_item"] <= $one_week_later): ?>
                                                <span class="status-badge warning">Expires Soon</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item["items_per_unit"]); ?></td>
                                    <td class="actions">
                                        <?php if ($view_mode != "selling"): ?>
                                        <a href="update_item.php?edit=<?php echo htmlspecialchars($item["id_item"]); ?>" class="action-btn edit-btn" title="Edit Item">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="confirm_delete_item.php?delete=<?php echo htmlspecialchars($item["id_item"]); ?>" class="action-btn delete-btn" 
                                           onclick="return confirm('Are you sure you want to delete this item?');" title="Delete Item">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET["notify"])): ?>
            <div class="notification success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_GET["notify"]); ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        (function(){
            function updateSidebarWidth(){
                try {
                    var sidebar = document.querySelector('.sidebar-nav');
                    if (!sidebar) return;
                    var style = window.getComputedStyle(sidebar);
                    var rect = sidebar.getBoundingClientRect();
                    var width = Math.round(rect.width) || 0;
                    if (style.display === 'none' || width < 40) width = 0;
                    document.documentElement.style.setProperty('--sidebar-width', width + 'px');
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
</body>
</html>