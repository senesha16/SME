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
    error_log("add_item.php: No user logged in, redirecting to login.php");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$email = mysqli_real_escape_string($connections, $_SESSION["email"]);
$query_info = mysqli_query($connections, "SELECT id_user, first_name, subscription_approved, subscription_plan FROM tbl_user WHERE email='$email'");
if (!$query_info) {
    error_log("add_item.php: Database error: " . mysqli_error($connections));
    echo "<script>alert('Database error: " . mysqli_error($connections) . "'); window.location.href='login.php';</script>";
    exit;
}
$my_info = mysqli_fetch_assoc($query_info);
if (!$my_info) {
    error_log("add_item.php: User not found for email: $email");
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
    error_log("add_item.php: Access denied for $email, trial expired or no subscription");
    echo "<script>alert('Access denied. Your trial has expired. Please subscribe.'); window.location.href='Subscribe.php';</script>";
    exit;
} elseif ($subscription_approved && !in_array($subscription_plan, $required_plan)) {
    error_log("add_item.php: Access denied for $email, plan $subscription_plan not sufficient");
    echo "<script>alert('Access denied. Please upgrade to Plan " . implode(' or ', $required_plan) . ".'); window.location.href='MyAccount.php';</script>";
    exit;
}

// Initialize form variables
$name_item = $category_item = $brand_item = $size_item = $quantity_item = $unit_item = $cost_price_item = $selling_price_pack = $selling_price_individual = $expiration_date_item = "";
$name_itemErr = $category_itemErr = $brand_itemErr = $size_itemErr = $quantity_itemErr = $unit_itemErr = $cost_price_itemErr = $selling_price_packErr = $selling_price_individualErr = $expiration_date_itemErr = $items_per_unitErr = "";
$items_per_unit = 1;
$sell_as = "";
$sell_asErr = "";
$id_item = isset($_GET["edit"]) ? mysqli_real_escape_string($connections, $_GET["edit"]) : null;

if ($id_item) {
    $edit_query = mysqli_query($connections, "SELECT * FROM tbl_item WHERE id_item='$id_item' AND id_user='$user_id'");
    if ($edit_query === false) {
        die("Edit query failed: " . mysqli_error($connections));
    }
    $edit_item = mysqli_fetch_assoc($edit_query);
    if ($edit_item) {
        $name_item = $edit_item["name_item"];
        $category_item = $edit_item["category_item"];
        $brand_item = $edit_item["brand_item"];
        $size_item = $edit_item["size_item"];
        $quantity_item = $edit_item["quantity_item"];
        $unit_item = $edit_item["unit_item"];
        $cost_price_item = number_format($edit_item["cost_price_item"], 2);
        $selling_price_pack = number_format($edit_item["selling_price_item"], 2);
        $selling_price_individual = number_format($edit_item["selling_price_individual"] ?? 0, 2);
        $expiration_date_item = $edit_item["expiration_date_item"];
        $items_per_unit = $edit_item["items_per_unit"];
        $sell_as_pack = $edit_item["sell_as_pack"];
        $sell_as_sachet = $edit_item["sell_as_sachet"];
        if ($sell_as_pack == 1 && $sell_as_sachet == 1) {
            $sell_as = "both";
        } elseif ($sell_as_pack == 1) {
            $sell_as = "pack";
        } elseif ($sell_as_sachet == 1) {
            $sell_as = "individual";
        }
    }
}

if (isset($_POST["btnAddItem"])) {
    $name_item = !empty($_POST["name_item"]) ? mysqli_real_escape_string($connections, $_POST["name_item"]) : "";
    $category_item = !empty($_POST["category_item"]) ? mysqli_real_escape_string($connections, $_POST["category_item"]) : "";
    $brand_item = !empty($_POST["brand_item"]) ? mysqli_real_escape_string($connections, $_POST["brand_item"]) : "";
    $size_item = !empty($_POST["size_item"]) ? mysqli_real_escape_string($connections, $_POST["size_item"]) : "";
    $quantity_item = !empty($_POST["quantity_item"]) && preg_match("/^\d+$/", $_POST["quantity_item"]) ? $_POST["quantity_item"] : "";
    $unit_item = !empty($_POST["unit_item"]) ? $_POST["unit_item"] : "";
    $cost_price_item = !empty($_POST["cost_price_item"]) && preg_match("/^\d{1,3}(,\d{3})*(\.\d{0,2})?$/", $_POST["cost_price_item"]) ? str_replace(",", "", $_POST["cost_price_item"]) : "";
    $selling_price_pack = $_POST["selling_price_pack"] ?? '0.00';
    $selling_price_individual = $_POST["selling_price_individual"] ?? '0.00';
    $sell_as = $_POST["sell_as"] ?? '';
    $expiration_date_item = !empty($_POST["expiration_date_item"]) ? $_POST["expiration_date_item"] : "";
    $items_per_unit = !empty($_POST["items_per_unit"]) && preg_match("/^\d+$/", $_POST["items_per_unit"]) ? $_POST["items_per_unit"] : 1;

    // Validate inputs
    if (empty($name_item)) $name_itemErr = "Required!";
    if (empty($category_item)) $category_itemErr = "Required!";
    if (empty($brand_item)) $brand_itemErr = "Required!";
    if (empty($size_item)) $size_itemErr = "Required!";
    if (empty($quantity_item)) $quantity_itemErr = "Required!";
    elseif (!preg_match("/^\d+$/", $quantity_item)) $quantity_itemErr = "Enter only numbers.";
    if (empty($unit_item)) $unit_itemErr = "Required!";
    if (empty($cost_price_item)) $cost_price_itemErr = "Required!";
    elseif (!preg_match("/^\d{1,3}(,\d{3})*(\.\d{0,2})?$/", $_POST["cost_price_item"])) $cost_price_itemErr = "Enter valid number (e.g., 50, 1,000, or 1,000.00).";
    if (empty($expiration_date_item)) $expiration_date_itemErr = "Required!";
    else {
        $today = date('Y-m-d');
        if ($expiration_date_item < $today) $expiration_date_itemErr = "Expiration date cannot be in the past.";
    }
    if ($unit_item == "piece") {
        $sell_as = "individual";
        $selling_price_pack = '0.00';
        if (empty($selling_price_individual) || !preg_match("/^\d{1,3}(,\d{3})*(\.\d{0,2})?$/", $selling_price_individual)) $selling_price_individualErr = "Required for piece unit!";
        else $selling_price_individual = str_replace(",", "", $selling_price_individual);
    } else {
        if (in_array($unit_item, ['pack', 'box']) && ($sell_as == 'pack' || $sell_as == 'both') && (empty($selling_price_pack) || !preg_match("/^\d{1,3}(,\d{3})*(\.\d{0,2})?$/", $selling_price_pack))) $selling_price_packErr = "Required for pack/box when selling as pack or both!";
        else $selling_price_pack = !empty($selling_price_pack) ? str_replace(",", "", $selling_price_pack) : '0.00';
        if (in_array($sell_as, ['individual', 'both']) && (empty($selling_price_individual) || !preg_match("/^\d{1,3}(,\d{3})*(\.\d{0,2})?$/", $selling_price_individual))) $selling_price_individualErr = "Required if selling as individual or both!";
        else $selling_price_individual = !empty($selling_price_individual) ? str_replace(",", "", $selling_price_individual) : '0.00';
    }
    if (in_array($unit_item, ['pack', 'box']) && empty($items_per_unit)) $items_per_unitErr = "Required for pack or box!";
    elseif (!preg_match("/^\d+$/", $items_per_unit)) $items_per_unitErr = "Enter only numbers.";
    if (in_array($unit_item, ['pack', 'box']) && empty($sell_as)) $sell_asErr = "Please select how you want to sell this item.";

    // Process form if no errors
    if (empty($name_itemErr) && empty($category_itemErr) && empty($brand_itemErr) && empty($size_itemErr) && empty($quantity_itemErr) && empty($unit_itemErr) && empty($cost_price_itemErr) && empty($selling_price_packErr) && empty($selling_price_individualErr) && empty($expiration_date_itemErr) && empty($items_per_unitErr) && empty($sell_asErr)) {
        $sell_as_pack = ($sell_as == "pack" || $sell_as == "both") ? 1 : 0;
        $sell_as_sachet = ($sell_as == "individual" || $sell_as == "both") ? 1 : 0;
        $availability_status = ($quantity_item > 0) ? "available" : "unavailable";

        if ($id_item) {
            // Update existing item
            $stmt = $connections->prepare("UPDATE tbl_item SET name_item=?, category_item=?, brand_item=?, size_item=?, quantity_item=?, unit_item=?, cost_price_item=?, selling_price_item=?, selling_price_individual=?, availability_status_item=?, expiration_date_item=?, items_per_unit=?, sell_as_pack=?, sell_as_sachet=? WHERE id_item=? AND id_user=?");
            if ($stmt === false) {
                error_log("Prepare failed for update: " . $connections->error);
                echo "<script>alert('Error preparing update query: " . addslashes($connections->error) . "');</script>";
            } else {
                $u_name = $name_item;
                $u_category = $category_item;
                $u_brand = $brand_item;
                $u_size = $size_item;
                $u_quantity = (int)$quantity_item;
                $u_unit = $unit_item;
                $u_cost = (float)$cost_price_item;
                $u_sellpack = (float)$selling_price_pack;
                $u_sellind = (float)$selling_price_individual;
                $u_avail = $availability_status;
                $u_expiration = $expiration_date_item;
                $u_items_per_unit = (int)$items_per_unit;
                $u_sell_as_pack = (int)$sell_as_pack;
                $u_sell_as_sachet = (int)$sell_as_sachet;
                $u_id_item = (int)$id_item;
                $u_user_id = (int)$user_id;

                $stmt->bind_param("sssssisddsssiiii", $u_name, $u_category, $u_brand, $u_size, $u_quantity, $u_unit, $u_cost, $u_sellpack, $u_sellind, $u_avail, $u_expiration, $u_items_per_unit, $u_sell_as_pack, $u_sell_as_sachet, $u_id_item, $u_user_id);

                if ($stmt->execute()) {
                    echo "<script>window.location.href='view_stock.php?notify=Item updated successfully!';</script>";
                } else {
                    error_log("Update error: " . $stmt->error);
                    echo "<script>alert('Error updating item: " . addslashes($stmt->error) . "');</script>";
                }
                $stmt->close();
            }
        } else {
            // Insert new item
            $stmt = $connections->prepare("INSERT INTO tbl_item (id_user, name_item, category_item, brand_item, size_item, quantity_item, unit_item, cost_price_item, selling_price_item, selling_price_individual, availability_status_item, expiration_date_item, items_per_unit, sell_as_pack, sell_as_sachet) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt === false) {
                error_log("Prepare failed for insert: " . $connections->error);
                echo "<script>alert('Error preparing insert query: " . addslashes($connections->error) . "');</script>";
            } else {
                $i_user_id = (int)$user_id;
                $i_name = $name_item;
                $i_category = $category_item;
                $i_brand = $brand_item;
                $i_size = $size_item;
                $i_quantity = (int)$quantity_item;
                $i_unit = $unit_item;
                $i_cost = (float)$cost_price_item;
                $i_sellpack = (float)$selling_price_pack;
                $i_sellind = (float)$selling_price_individual;
                $i_avail = $availability_status;
                $i_expiration = $expiration_date_item;
                $i_items_per_unit = (int)$items_per_unit;
                $i_sell_as_pack = (int)$sell_as_pack;
                $i_sell_as_sachet = (int)$sell_as_sachet;

                $stmt->bind_param("issssisddsssiii", $i_user_id, $i_name, $i_category, $i_brand, $i_size, $i_quantity, $i_unit, $i_cost, $i_sellpack, $i_sellind, $i_avail, $i_expiration, $i_items_per_unit, $i_sell_as_pack, $i_sell_as_sachet);

                if ($stmt->execute()) {
                    echo "<script>window.location.href='view_stock.php?notify=Item added successfully!';</script>";
                } else {
                    error_log("Insert error: " . $stmt->error);
                    echo "<script>alert('Error: " . addslashes($stmt->error) . "');</script>";
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id_item ? 'Edit Item' : 'Add New Item'; ?> - SME Dashboard</title>
    <link rel="stylesheet" href="user-items.css">
    <link rel="stylesheet" href="user-dashboard.css"> <!-- Added to match nav integration -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <script>
        function allowNumbersOnly(event) {
            var char = String.fromCharCode(event.which);
            if (!(/[0-9,.]/.test(char) || event.which == 8 || event.which == 46)) {
                event.preventDefault();
                return false;
            }
            return true;
        }

        function toggleFields() {
            var unit = document.getElementById("unit_item").value;
            var sellAsRow = document.getElementById("pack_section");
            var sellingPricePackRow = document.getElementById("pack_price_row");
            var sellingPriceIndividualRow = document.getElementById("individual_price_row");
            var itemsPerUnitRow = document.getElementById("items_per_unit_row");
            if (unit == "piece") {
                sellAsRow.classList.add("hidden");
                sellingPricePackRow.classList.add("hidden");
                sellingPriceIndividualRow.classList.remove("hidden");
                itemsPerUnitRow.classList.add("hidden");
                document.getElementById("sell_as").value = "individual";
            } else if (unit == "pack" || unit == "box") {
                sellAsRow.classList.remove("hidden");
                sellingPricePackRow.classList.remove("hidden");
                sellingPriceIndividualRow.classList.remove("hidden");
                itemsPerUnitRow.classList.remove("hidden");
            } else {
                sellAsRow.classList.add("hidden");
                sellingPricePackRow.classList.remove("hidden");
                sellingPriceIndividualRow.classList.add("hidden");
                itemsPerUnitRow.classList.add("hidden");
            }
            updateSellingPriceFields();
        }

        function updateSellingPriceFields() {
            var sellAs = document.getElementById("sell_as").value;
            var sellingPricePackRow = document.getElementById("pack_price_row");
            var sellingPriceIndividualRow = document.getElementById("individual_price_row");
            if (sellAs == "pack") {
                sellingPricePackRow.classList.remove("hidden");
                sellingPriceIndividualRow.classList.add("hidden");
            } else if (sellAs == "individual") {
                sellingPricePackRow.classList.add("hidden");
                sellingPriceIndividualRow.classList.remove("hidden");
            } else if (sellAs == "both") {
                sellingPricePackRow.classList.remove("hidden");
                sellingPriceIndividualRow.classList.remove("hidden");
            } else {
                sellingPricePackRow.classList.remove("hidden");
                sellingPriceIndividualRow.classList.add("hidden");
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            var form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    var dateInput = document.querySelector('input[name="expiration_date_item"]');
                    if (!dateInput || !dateInput.value) {
                        alert('Expiration date is required.');
                        e.preventDefault();
                    } else if (!/^\d{4}-\d{2}-\d{2}$/.test(dateInput.value)) {
                        alert('Please enter a valid date in YYYY-MM-DD format.');
                        e.preventDefault();
                    }
                });
            }
            toggleFields(); // Initialize fields on load
        });
    </script>

    <style>
        .page-header {
            background: linear-gradient(135deg, #E62727 0%, #c62d2d 100%);
            color: white;
            padding: 30px 25px;
            margin-bottom: 30px;
        }
        .header-text h2 {
            margin: 0 0 5px 0;
            font-size: 28px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; /* match Inventory Management header font */
        }
        .header-content p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .section {
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
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .form-input, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #E62727;
            box-shadow: 0 0 0 3px rgba(230, 39, 39, 0.1);
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
        .btn-submit {
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
        .btn-submit:hover {
            background: #c62d2d;
        }
        .notification {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 0 auto 25px;
            max-width: 1200px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .hidden {
            display: none;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .section {
                margin: 15px;
                padding: 20px;
            }
        }
        .main-content {
            margin-left: calc(var(--sidebar-width, 250px) + 24px);
            padding: 30px;
            flex-grow: 1;
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
            .transaction-section { margin: 15px; padding: 20px; }
            .form-group { min-width: 100%; }
            .earnings-container { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h2>
                    <i class="fas <?php echo $id_item ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
                    <?php echo $id_item ? 'Edit Item' : 'Add New Item'; ?>
                </h2>
                <p><?php echo $id_item ? 'Update the details of your existing item' : 'Add a new item to your inventory system'; ?></p>
            </div>
        </div>

        <!-- Notification -->
        <?php if (isset($_GET["notify"])): ?>
            <div class="notification">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_GET["notify"]); ?></span>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST">
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Basic Information
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tag"></i>
                            Name of Item
                        </label>
                        <input type="text" name="name_item" class="form-input" placeholder="e.g., Bread, Rice, Soap" value="<?php echo htmlspecialchars($name_item); ?>">
                        <?php if ($name_itemErr): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $name_itemErr; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-list"></i>
                            Category
                        </label>
                       <select name="category_item" class="form-select">
                        <option value="">Select Category</option>
                       <option value="Wet Goods" <?php echo $category_item == "Wet Goods" ? "selected" : ""; ?>>Wet Goods</option>
                       <option value="Dry Goods" <?php echo $category_item == "Dry Goods" ? "selected" : ""; ?>>Dry Goods</option>
                      <option value="Perishable" <?php echo $category_item == "Perishable" ? "selected" : ""; ?>>Perishable</option>
                       <option value="Non-Perishable" <?php echo $category_item == "Non-Perishable" ? "selected" : ""; ?>>Non-Perishable</option>
                        <option value="Frozen" <?php echo $category_item == "Frozen" ? "selected" : ""; ?>>Frozen</option>
                       <option value="Ambient" <?php echo $category_item == "Ambient" ? "selected" : ""; ?>>Ambient</option>
                       <option value="Bulk" <?php echo $category_item == "Bulk" ? "selected" : ""; ?>>Bulk</option>
                       <option value="Packaged" <?php echo $category_item == "Packaged" ? "selected" : ""; ?>>Packaged</option>
                       <option value="Raw Materials" <?php echo $category_item == "Raw Materials" ? "selected" : ""; ?>>Raw Materials</option>
                        <option value="Others" <?php echo $category_item == "Others" ? "selected" : ""; ?>>Others</option>
                       </select>
                        <?php if ($category_itemErr): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $category_itemErr; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-copyright"></i>
                            Brand
                        </label>
                        <input type="text" name="brand_item" class="form-input" placeholder="e.g., Brand X, Generic, Local" value="<?php echo htmlspecialchars($brand_item); ?>">
                        <?php if ($brand_itemErr): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $brand_itemErr; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">
                    <i class="fas fa-cube"></i>
                    Item Details
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-expand-arrows-alt"></i>
                            Size
                        </label>
                       <select name="size_item" class="form-select">
                      <option value="">Select Size or Volume</option>
                      <option value="standard_size" <?php echo $size_item == "standard_size" ? "selected" : ""; ?>>Standard Size</option>
                     <option value="small" <?php echo $size_item == "small" ? "selected" : ""; ?>>Small</option>
                      <option value="medium" <?php echo $size_item == "medium" ? "selected" : ""; ?>>Medium</option>
                       <option value="large" <?php echo $size_item == "large" ? "selected" : ""; ?>>Large</option>
                       <option value="250ml" <?php echo $size_item == "250ml" ? "selected" : ""; ?>>250 ml</option>
                      <option value="500ml" <?php echo $size_item == "500ml" ? "selected" : ""; ?>>500 ml</option>
                      <option value="1L" <?php echo $size_item == "1L" ? "selected" : ""; ?>>1 Liter</option>
                      <option value="2L" <?php echo $size_item == "2L" ? "selected" : ""; ?>>2 Liters</option>
                    </select>
                        <?php if ($size_itemErr): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $size_itemErr; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-sort-numeric-up"></i>
                            Quantity
                        </label>
                        <input type="text" name="quantity_item" class="form-input" placeholder="e.g., 5, 10, 25" value="<?php echo htmlspecialchars($quantity_item); ?>" onkeypress="return allowNumbersOnly(event);">
                        <?php if ($quantity_itemErr): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $quantity_itemErr; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-ruler"></i>
                            Unit
                        </label>
                        <select name="unit_item" id="unit_item" class="form-select" onchange="toggleFields()">
                            <option value="">Select Unit</option>
                            <option value="piece" <?php echo $unit_item == "piece" ? "selected" : ""; ?>>Piece</option>
                            <option value="pack" <?php echo $unit_item == "pack" ? "selected" : ""; ?>>Pack</option>
                            <option value="box" <?php echo $unit_item == "box" ? "selected" : ""; ?>>Box</option>

                        </select>
                        <?php if ($unit_itemErr): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $unit_itemErr; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="section <?php echo ($unit_item != 'pack' && $unit_item != 'box') ? 'hidden' : ''; ?>" id="pack_section">
                <div class="section-title">
                    <i class="fas fa-boxes"></i>
                    Pack/Box Options
                </div>
                <div class="form-row">
                    <div class="form-group" id="items_per_unit_row">
                        <label class="form-label">
                            <i class="fas fa-cubes"></i>
                            Items per Unit
                        </label>
                        <input type="text" name="items_per_unit" class="form-input" placeholder="e.g., 6, 12, 24" value="<?php echo htmlspecialchars($items_per_unit); ?>" onkeypress="return allowNumbersOnly(event);">
                        <?php if ($items_per_unitErr): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $items_per_unitErr; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-shopping-cart"></i>
                            Sell As
                        </label>
                        <select name="sell_as" id="sell_as" class="form-select" onchange="updateSellingPriceFields()">
                            <option value="">Select Option</option>
                            <option value="pack" <?php echo $sell_as == "pack" ? "selected" : ""; ?>>Pack Only</option>
                            <option value="individual" <?php echo $sell_as == "individual" ? "selected" : ""; ?>>Individual Only</option>
                            <option value="both" <?php echo $sell_as == "both" ? "selected" : ""; ?>>Both</option>
                        </select>
                        <?php if ($sell_asErr): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $sell_asErr; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="section <?php echo ($unit_item == 'piece' || in_array($sell_as, ['individual', 'both'])) ? '' : 'hidden'; ?>" id="individual_price_row">
                <div class="section-title">
                    <i class="fas fa-coins"></i>
                    Individual Item Pricing
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-peso-sign"></i>
                            Price to Sell (Individual)
                        </label>
                        <input type="text" name="selling_price_individual" class="form-input" placeholder="e.g., 10.00, 5.50" value="<?php echo htmlspecialchars($selling_price_individual); ?>" onkeypress="return allowNumbersOnly(event);">
                        <?php if ($selling_price_individualErr): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $selling_price_individualErr; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="section <?php echo in_array($unit_item, ['pack', 'box', 'kg', 'grams']) && in_array($sell_as, ['pack', 'both']) ? '' : 'hidden'; ?>" id="pack_price_row">
                <div class="section-title">
                    <i class="fas fa-money-bill-wave"></i>
                    Pack/Box Pricing
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-peso-sign"></i>
                            Price to Sell (Pack/Box)
                        </label>
                        <input type="text" name="selling_price_pack" class="form-input" placeholder="e.g., 50.00, 120.00" value="<?php echo htmlspecialchars($selling_price_pack); ?>" onkeypress="return allowNumbersOnly(event);">
                        <?php if ($selling_price_packErr): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $selling_price_packErr; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">
                    <i class="fas fa-calculator"></i>
                    Cost & Expiration
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-receipt"></i>
                            Price when Bought (Cost)
                        </label>
                        <input type="text" name="cost_price_item" class="form-input" placeholder="e.g., 45.00, 100.00" value="<?php echo htmlspecialchars($cost_price_item); ?>" onkeypress="return allowNumbersOnly(event);">
                        <?php if ($cost_price_itemErr): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $cost_price_itemErr; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-calendar-times"></i>
                            Expiration Date
                        </label>
                        <input type="date" name="expiration_date_item" class="form-input" value="<?php echo htmlspecialchars($expiration_date_item); ?>" required>
                        <?php if ($expiration_date_itemErr): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $expiration_date_itemErr; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="submit-section">
                <button type="submit" name="btnAddItem" class="btn-submit">
                    <i class="fas <?php echo $id_item ? 'fa-save' : 'fa-plus'; ?>"></i>
                    <?php echo $id_item ? 'Update Item' : 'Add Item'; ?>
                </button>
            </div>
        </form>
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
					// Treat hidden or very small sidebars as 0 (mobile collapse)
					if (style.display === 'none' || width < 40) width = 0;
					// write the CSS variable (fallback preserved in calc())
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
				// Observe sidebar changes (e.g. toggles) and update width
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