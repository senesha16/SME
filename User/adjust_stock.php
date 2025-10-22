<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');
include("nav.php"); // Adjust to "../nav.php" if in parent directory
include("check_subscription.php");
include("access_control.php");

include("../connections.php");

if (!isset($_SESSION["email"]) || empty($_SESSION["email"])) {
    error_log("adjust_stock.php: No user logged in, redirecting to login.php");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$email = mysqli_real_escape_string($connections, $_SESSION["email"]);
$query_info = mysqli_query($connections, "SELECT id_user, first_name, subscription_approved, subscription_plan FROM tbl_user WHERE email='$email'");
if (!$query_info) {
    error_log("adjust_stock.php: Database error: " . mysqli_error($connections));
    echo "<script>alert('Database error: " . mysqli_error($connections) . "'); window.location.href='login.php';</script>";
    exit;
}
$my_info = mysqli_fetch_assoc($query_info);
if (!$my_info) {
    error_log("adjust_stock.php: User not found for email: $email");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}
$user_id = $my_info["id_user"];
$full_name = $my_info["first_name"];
$subscription_approved = $my_info["subscription_approved"];
$subscription_plan = isset($_SESSION['effective_plan']) ? $_SESSION['effective_plan'] : $my_info["subscription_plan"];

// Restrict access based on feature and plan
$required_plan = ['A', 'B', 'C'];
if (!$subscription_approved && !isset($_SESSION['effective_plan'])) {
    error_log("adjust_stock.php: Access denied for $email, trial expired or no subscription");
    echo "<script>alert('Access denied. Your trial has expired. Please subscribe.'); window.location.href='Subscribe.php';</script>";
    exit;
} elseif ($subscription_approved && !in_array($subscription_plan, $required_plan)) {
    error_log("adjust_stock.php: Access denied for $email, plan $subscription_plan not sufficient");
    echo "<script>alert('Access denied. Please upgrade to Plan " . implode(' or ', $required_plan) . ".'); window.location.href='MyAccount.php';</script>";
    exit;
}

// Handle adjustment submission
$actionErr = $quantityErr = "";
$selected_quantity = 0;
if (isset($_POST['btnAdjust'])) {
    $pack_item_id = mysqli_real_escape_string($connections, $_POST['pack_item_id']);
    $action = $_POST['action'] ?? '';
    $new_quantity = isset($_POST['new_quantity']) ? trim($_POST['new_quantity']) : '';

    $pack_query = mysqli_query($connections, "SELECT quantity_item, items_per_unit, name_item, category_item, brand_item, size_item, cost_price_item, selling_price_item, selling_price_individual, expiration_date_item, sell_as_pack, sell_as_sachet FROM tbl_item WHERE id_item='$pack_item_id' AND id_user='$user_id' AND (unit_item = 'pack' OR unit_item = 'box')");
    if ($pack_query === false) {
        $actionErr = "Database error: " . mysqli_error($connections);
    } else {
        $pack = mysqli_fetch_assoc($pack_query);
        if (!$pack) {
            $actionErr = "Invalid pack item.";
        } else {
            $current_quantity = $pack['quantity_item'];
            $selected_quantity = $current_quantity;
            $items_per_unit = $pack['items_per_unit'];
            if (!preg_match("/^\d+$/", $new_quantity) || $new_quantity <= 0) {
                $quantityErr = "Enter a valid positive number.";
            } else {
                if ($action == 'add') {
                    $new_qty = $current_quantity + $new_quantity;
                } elseif ($action == 'subtract') {
                    if ($new_quantity > $current_quantity) {
                        $quantityErr = "Subtract value cannot exceed current quantity ($current_quantity).";
                    } else {
                        $new_qty = $current_quantity - $new_quantity;
                    }
                } elseif ($action == 'unpack') {
                    $units_to_unpack = $new_quantity;
                    if ($units_to_unpack > $current_quantity) {
                        $quantityErr = "Unpack value cannot exceed current quantity ($current_quantity).";
                    } else {
                        $new_qty = $current_quantity - $units_to_unpack;
                        $add_qty = $units_to_unpack * $items_per_unit;
                        $individual_name = $pack['name_item'] . " (Individual)";
                        $selling_price_individual = $pack['selling_price_individual'] ?? ($pack['selling_price_item'] / $items_per_unit);

                        $individual_query = mysqli_query($connections, "SELECT id_item, quantity_item FROM tbl_item WHERE name_item = '$individual_name' AND id_user='$user_id' AND unit_item='piece' LIMIT 1");
                        if ($individual_query === false) {
                            $quantityErr = "Database error checking individual item: " . mysqli_error($connections);
                        } else {
                            $individual = mysqli_fetch_assoc($individual_query);
                            if ($individual) {
                                $new_individual_qty = $individual['quantity_item'] + $add_qty;
                                $update_ind_query = mysqli_query($connections, "UPDATE tbl_item SET quantity_item = '$new_individual_qty' WHERE id_item='{$individual['id_item']}'");
                                if ($update_ind_query === false) {
                                    $quantityErr = "Error updating individual item: " . mysqli_error($connections);
                                }
                            } else {
                                $insert_query = "INSERT INTO tbl_item (id_user, name_item, category_item, brand_item, size_item, quantity_item, unit_item, cost_price_item, selling_price_item, selling_price_individual, availability_status_item, expiration_date_item, items_per_unit, sell_as_pack, sell_as_sachet) 
                                                VALUES ('$user_id', '$individual_name', '{$pack['category_item']}', '{$pack['brand_item']}', '{$pack['size_item']}', '$add_qty', 'piece', '{$pack['cost_price_item']}', '$selling_price_individual', '$selling_price_individual', 'available', '{$pack['expiration_date_item']}', '1', '0', '1')";
                                if (!mysqli_query($connections, $insert_query)) {
                                    $quantityErr = "Error creating individual item: " . mysqli_error($connections);
                                }
                            }
                        }
                    }
                }
                if (!$quantityErr) {
                    $update_query = mysqli_query($connections, "UPDATE tbl_item SET quantity_item = '$new_qty' WHERE id_item='$pack_item_id'");
                    if ($update_query === false) {
                        $quantityErr = "Error updating pack quantity: " . mysqli_error($connections);
                    } else {
                        echo "<script>alert('Stock adjusted successfully!'); window.location.href='adjust_stock.php';</script>";
                    }
                }
            }
        }
    }
}

// Fetch items for table
$items = [];
$item_query = mysqli_query($connections, "SELECT id_item, name_item, quantity_item, unit_item, items_per_unit FROM tbl_item WHERE id_user='$user_id' AND (unit_item = 'pack' OR unit_item = 'box') AND (expiration_date_item IS NULL OR expiration_date_item > CURDATE())");
while ($row = mysqli_fetch_assoc($item_query)) {
    $items[$row['id_item']] = $row;
}
$selected_item_id = isset($_POST['pack_item_id']) ? $_POST['pack_item_id'] : '';
if ($selected_item_id && isset($items[$selected_item_id])) {
    $selected_quantity = $items[$selected_item_id]['quantity_item'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adjust Stock - SME Dashboard</title>
    <link rel="stylesheet" href="user-stock.css">
    <link rel="stylesheet" href="user-dashboard.css"> <!-- Added to match nav integration -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <script>
        function allowNumbersOnly(event) {
            var char = String.fromCharCode(event.which);
            if (!(/[0-9]/.test(char) || event.which == 8)) {
                event.preventDefault();
                return false;
            }
            return true;
        }
        function toggleFields() {
            var action = document.getElementById("action").value;
            var qtyField = document.getElementById("new_quantity_row");
            var unpackNote = document.getElementById("unpack_note");
            if (action == "unpack") {
                qtyField.style.display = "block";
                unpackNote.style.display = "block";
            } else {
                qtyField.style.display = "block";
                unpackNote.style.display = "none";
            }
        }
        function validateForm() {
            var action = document.getElementById("action").value;
            var newQty = document.getElementsByName("new_quantity")[0].value;
            var currentQty = <?php echo $selected_quantity; ?>;
            console.log("Validating: action=" + action + ", newQty=" + newQty + ", currentQty=" + currentQty);
            if (!newQty || !/^\d+$/.test(newQty) || parseInt(newQty) <= 0) {
                alert("Please enter a valid positive number.");
                return false;
            }
            if (action == "subtract" && parseInt(newQty) > currentQty) {
                alert("Subtract value cannot exceed current quantity (" + currentQty + ").");
                return false;
            }
            if (action == "unpack" && parseInt(newQty) > currentQty) {
                alert("Unpack value cannot exceed current quantity (" + currentQty + ").");
                return false;
            }
            return true;
        }
        function submitOnRadioSelect() {
            var radios = document.getElementsByName("pack_item_id");
            for (var i = 0; i < radios.length; i++) {
                if (radios[i].checked) {
                    document.getElementById("item-selection-form").submit();
                    break;
                }
            }
        }
    </script>

    <style>
        /* Minimal inline styles (move to user-stock.css later) */
        .page-header {
            background: linear-gradient(135deg, #E62727 0%, #c62d2d 100%);
            color: white;
            padding: 30px 25px;
            margin-bottom: 30px;
            border-radius: 12px; /* rounded corners */
            overflow: hidden;    /* ensure inner content respects radius */
            box-shadow: 0 8px 24px rgba(198,45,45,0.12); /* subtle lift to match rounded look */
        }
        .header-content {
            display: flex;
            justify-content: space-between; /* title left, actions right */
            align-items: center;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            gap: 12px;
        }
        .header-text { text-align: left; }
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
        .header-actions { margin-left: auto; display:flex; align-items:center; gap:12px; }
        .btn-secondary {
            background: #ffffff;           /* white background */
            color: #E62727;               /* red text */
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(230,39,39,0.12); /* subtle red border */
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(230,39,39,0.08);
        }
        .adjustment-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin: 25px auto;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
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
        .items-selection-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .items-selection-table thead {
            background: #f8f9fa;
        }
        .items-selection-table th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
        }
        .items-selection-table tbody tr {
            border-bottom: 1px solid #f1f2f6;
            transition: all 0.2s ease;
        }
        .items-selection-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .items-selection-table td {
            padding: 15px 12px;
            vertical-align: middle;
        }
        .radio-cell {
            text-align: center;
            width: 60px;
        }
        .radio-cell input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .item-details-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .current-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .detail-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .detail-card .label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .detail-card .value {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        .adjustment-form {
            background: white;
            border-radius: 12px;
            padding: 25px;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
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
        .unpack-note {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            font-size: 14px;
            color: #0c5460;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .submit-section {
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            text-align: center;
        }
        .btn-adjust {
            background: #E62727;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .empty-state h3 {
            margin: 0 0 10px 0;
            font-size: 24px;
            color: #2c3e50;
        }
        .out-of-stock {
            color: #e74c3c;
        }
        .low-stock {
            color: #f39c12;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-left: 5px;
        }
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            .adjustment-container {
                margin: 15px;
                padding: 20px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .current-details {
                grid-template-columns: 1fr;
            }
            .main-content { margin-left: 0; padding: 15px; }
        }
        /* Ensure main content is offset for sidebar */
        .main-content {
            margin-left: calc(var(--sidebar-width, 250px) + 24px);
            padding: 30px;
            flex-grow: 1;
        }
    </style>
</head>
<body>
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-text">
                    <h1><i class="fas fa-boxes"></i> Adjust Stock</h1>
                    <p>Manage inventory levels for your items</p>
                </div>
                <div class="header-actions">
                    <a href="view_stock.php" class="btn-secondary">
                        <i class="fas fa-eye"></i>
                        View Stock
                    </a>
                </div>
            </div>
        </div>

        <!-- Adjustment Container -->
        <div class="adjustment-container">
            <!-- Item Selection Section -->
            <div class="section-title">
                <i class="fas fa-hand-pointer"></i>
                Select Item to Adjust
            </div>
            
            <form method="POST" id="item-selection-form">
                <?php if (empty($items)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Items Available</h3>
                        <p>You don't have any pack or box items to adjust. Add some items first.</p>
                        <a href="add_item.php" class="btn-primary">
                            <i class="fas fa-plus"></i>
                            Add New Item
                        </a>
                    </div>
                <?php else: ?>
                    <table class="items-selection-table">
                        <thead>
                            <tr>
                                <th class="radio-cell">Select</th>
                                <th><i class="fas fa-tag"></i> Item Name</th>
                                <th><i class="fas fa-sort-numeric-up"></i> Quantity</th>
                                <th><i class="fas fa-ruler"></i> Unit</th>
                                <th><i class="fas fa-cubes"></i> Items per Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item_id => $item): ?>
                                <tr>
                                    <td class="radio-cell">
                                        <input type="radio" name="pack_item_id" value="<?php echo $item_id; ?>" 
                                               <?php echo $selected_item_id == $item_id ? 'checked' : ''; ?> 
                                               onchange="submitOnRadioSelect()">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item["name_item"]); ?></strong>
                                    </td>
                                    <td class="<?php echo $item["quantity_item"] == 0 ? 'out-of-stock' : ($item["quantity_item"] <= 5 ? 'low-stock' : ''); ?>">
                                        <?php if ($item["quantity_item"] == 0): ?>
                                            <i class="fas fa-times-circle" style="color: #e74c3c;"></i>
                                        <?php elseif ($item["quantity_item"] <= 5): ?>
                                            <i class="fas fa-exclamation-circle" style="color: #f39c12;"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($item["quantity_item"]); ?>
                                        <?php if ($item["quantity_item"] == 0): ?>
                                            <span class="status-badge out-of-stock">Out of Stock</span>
                                        <?php elseif ($item["quantity_item"] <= 5): ?>
                                            <span class="status-badge low-stock">Low Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item["unit_item"]); ?></td>
                                    <td><?php echo htmlspecialchars($item["items_per_unit"]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <?php if ($actionErr): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo $actionErr; ?>
                    </div>
                <?php endif; ?>
            </form>

            <!-- Adjustment Form Section -->
            <?php if ($selected_item_id && isset($items[$selected_item_id])): 
                $selected_item = $items[$selected_item_id];
                $current_quantity = $selected_item['quantity_item'];
                $items_per_unit = $selected_item['items_per_unit'];
                $selected_quantity = $current_quantity;
            ?>
                <div class="section-title" style="margin-top: 40px;">
                    <i class="fas fa-cogs"></i>
                    Adjust Stock for: <?php echo htmlspecialchars($selected_item['name_item']); ?>
                </div>

                <div class="item-details-section">
                    <div class="current-details">
                        <div class="detail-card">
                            <div class="label">Item Name</div>
                            <div class="value"><?php echo htmlspecialchars($selected_item['name_item']); ?></div>
                        </div>
                        <div class="detail-card">
                            <div class="label">Current Quantity</div>
                            <div class="value">
                                <?php echo htmlspecialchars($current_quantity); ?> 
                                <?php echo htmlspecialchars($selected_item['unit_item']); ?>
                            </div>
                        </div>
                        <div class="detail-card">
                            <div class="label">Items per Unit</div>
                            <div class="value"><?php echo htmlspecialchars($items_per_unit); ?> pieces</div>
                        </div>
                    </div>
                </div>

                <div class="adjustment-form">
                    <form method="POST" onsubmit="return validateForm()">
                        <input type="hidden" name="pack_item_id" value="<?php echo htmlspecialchars($selected_item_id); ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="action">
                                    <i class="fas fa-tasks"></i>
                                    Select Action
                                </label>
                                <select name="action" id="action" class="form-control" onchange="toggleFields()">
                                    <option value="add">Add Stock</option>
                                    <option value="subtract">Subtract Stock</option>
                                    <option value="unpack">Unpack to Individual</option>
                                </select>
                            </div>

                            <div id="new_quantity_row" class="form-group" style="display: block;">
                                <label for="new_quantity">
                                    <i class="fas fa-sort-numeric-up"></i>
                                    Quantity
                                </label>
                                <input type="text" name="new_quantity" id="new_quantity" class="form-control" 
                                       placeholder="Enter quantity (e.g., 5)" onkeypress="return allowNumbersOnly(event);">
                                <?php if ($quantityErr): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?php echo $quantityErr; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div id="unpack_note" class="unpack-note" style="display: none;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Unpack Information:</strong> This will convert the entered quantity from 
                            <?php echo htmlspecialchars($selected_item['unit_item']); ?> to individual pieces. 
                            Each <?php echo htmlspecialchars($selected_item['unit_item']); ?> contains 
                            <?php echo htmlspecialchars($items_per_unit); ?> pieces.
                        </div>

                        <div class="submit-section">
                            <button type="submit" name="btnAdjust" class="btn-adjust">
                                <i class="fas fa-save"></i>
                                Adjust Stock
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Sidebar-width measurement script: sets --sidebar-width so .main-content won't overlap -->
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