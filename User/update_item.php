<?php
session_start();
include("nav.php");
include("check_subscription.php");
include("../connections.php");

// Check if user is logged in
if (!isset($_SESSION["email"]) || empty($_SESSION["email"])) {
    error_log("update_item.php: No user logged in, redirecting to login.php");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

// Fetch user data
$email = mysqli_real_escape_string($connections, $_SESSION["email"]);
$query_info = mysqli_query($connections, "SELECT id_user, first_name, subscription_approved, subscription_plan FROM tbl_user WHERE email='$email'");
if (!$query_info) {
    error_log("update_item.php: Database error: " . mysqli_error($connections));
    echo "<script>alert('Database error: " . mysqli_error($connections) . "'); window.location.href='login.php';</script>";
    exit;
}
$my_info = mysqli_fetch_assoc($query_info);
if (!$my_info) {
    error_log("update_item.php: User not found for email: $email");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}
$user_id = $my_info["id_user"];
$full_name = $my_info["first_name"];
$subscription_approved = $my_info["subscription_approved"];
$subscription_plan = isset($_SESSION['effective_plan']) ? $_SESSION['effective_plan'] : $my_info["subscription_plan"];

// Verify access (trial or Plan A/B/C)
if (!$subscription_approved && !isset($_SESSION['effective_plan'])) {
    error_log("update_item.php: Access denied for $email, trial expired or no subscription");
    echo "<script>alert('Access denied. Your trial has expired. Please subscribe.'); window.location.href='Subscribe.php';</script>";
    exit;
}

echo "<center><h3>Update Item - Welcome, " . htmlspecialchars($full_name) . "</h3></center>";

// Initialize form variables
$name_item = $category_item = $brand_item = $size_item = $quantity_item = $unit_item = $cost_price_item = $selling_price_pack = $selling_price_individual = $expiration_date_item = "";
$name_itemErr = $category_itemErr = $brand_itemErr = $size_itemErr = $quantity_itemErr = $unit_itemErr = $cost_price_itemErr = $selling_price_packErr = $selling_price_individualErr = $expiration_date_itemErr = $items_per_unitErr = "";
$items_per_unit = 1;
$sell_as = "";
$sell_asErr = "";
$id_item = isset($_GET["edit"]) ? mysqli_real_escape_string($connections, $_GET["edit"]) : null;

if (!$id_item) {
    error_log("update_item.php: No item ID provided, redirecting to view_stock.php");
    echo "<script>alert('No item selected for update.'); window.location.href='view_stock.php';</script>";
    exit;
}

// Fetch item details
$edit_query = mysqli_query($connections, "SELECT * FROM tbl_item WHERE id_item='$id_item' AND id_user='$user_id'");
if ($edit_query === false) {
    error_log("update_item.php: Edit query failed: " . mysqli_error($connections));
    die("Edit query failed: " . mysqli_error($connections));
}
$edit_item = mysqli_fetch_assoc($edit_query);
if (!$edit_item) {
    error_log("update_item.php: Item not found for id_item=$id_item, user_id=$user_id");
    echo "<script>alert('Item not found.'); window.location.href='view_stock.php';</script>";
    exit;
}
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

if (isset($_POST["btnUpdateItem"])) {
    // Validate inputs
    if (empty($_POST["name_item"])) {
        $name_itemErr = "Required!";
    } else {
        $name_item = mysqli_real_escape_string($connections, $_POST["name_item"]);
    }

    if (empty($_POST["category_item"])) {
        $category_itemErr = "Required!";
    } else {
        $category_item = mysqli_real_escape_string($connections, $_POST["category_item"]);
    }

    if (empty($_POST["brand_item"])) {
        $brand_itemErr = "Required!";
    } else {
        $brand_item = mysqli_real_escape_string($connections, $_POST["brand_item"]);
    }

    if (empty($_POST["size_item"])) {
        $size_itemErr = "Required!";
    } else {
        $size_item = mysqli_real_escape_string($connections, $_POST["size_item"]);
    }

    if (empty($_POST["quantity_item"])) {
        $quantity_itemErr = "Required!";
    } else if (!preg_match("/^\d+$/", $_POST["quantity_item"])) {
        $quantity_itemErr = "Enter only numbers.";
    } else {
        $quantity_item = $_POST["quantity_item"];
    }

    if (empty($_POST["unit_item"])) {
        $unit_itemErr = "Required!";
    } else {
        $unit_item = $_POST["unit_item"];
    }

    if (empty($_POST["cost_price_item"])) {
        $cost_price_itemErr = "Required!";
    } else if (!preg_match("/^\d{1,3}(,\d{3})*(\.\d{0,2})?$/", $_POST["cost_price_item"])) {
        $cost_price_itemErr = "Enter valid number (e.g., 50, 1,000, or 1,000.00).";
    } else {
        $cost_price_item = str_replace(",", "", $_POST["cost_price_item"]);
    }

    $selling_price_pack = $_POST["selling_price_pack"] ?? '0.00';
    $selling_price_individual = $_POST["selling_price_individual"] ?? '0.00';
    $sell_as = $_POST["sell_as"] ?? '';

    if ($unit_item == "piece") {
        $sell_as = "individual";
        $selling_price_pack = '0.00';
        if (empty($_POST["selling_price_individual"])) {
            $selling_price_individualErr = "Required for piece unit!";
        } else if (!preg_match("/^\d{1,3}(,\d{3})*(\.\d{0,2})?$/", $_POST["selling_price_individual"])) {
            $selling_price_individualErr = "Enter valid number (e.g., 75, 1,500, or 1,500.00).";
        } else {
            $selling_price_individual = str_replace(",", "", $_POST["selling_price_individual"]);
        }
    } else {
        if (in_array($unit_item, ['pack', 'box']) && ($sell_as == 'pack' || $sell_as == 'both') && empty($selling_price_pack)) {
            $selling_price_packErr = "Required for pack/box when selling as pack or both!";
        } else if (!empty($selling_price_pack) && !preg_match("/^\d{1,3}(,\d{3})*(\.\d{0,2})?$/", $selling_price_pack)) {
            $selling_price_packErr = "Enter valid number (e.g., 75, 1,500, or 1,500.00).";
        } else {
            $selling_price_pack = str_replace(",", "", $selling_price_pack);
        }

        if (in_array($sell_as, ['individual', 'both']) && empty($selling_price_individual)) {
            $selling_price_individualErr = "Required if selling as individual or both!";
        } else if (!empty($selling_price_individual) && !preg_match("/^\d{1,3}(,\d{3})*(\.\d{0,2})?$/", $_POST["selling_price_individual"])) {
            $selling_price_individualErr = "Enter valid number (e.g., 75, 1,500, or 1,500.00).";
        } else {
            $selling_price_individual = str_replace(",", "", $_POST["selling_price_individual"]);
        }
    }

    if (empty($_POST["expiration_date_item"])) {
        $expiration_date_itemErr = "Required!";
    } else {
        $expiration_date_item = $_POST["expiration_date_item"];
        $today = date('Y-m-d');
        if ($expiration_date_item < $today) {
            $expiration_date_itemErr = "Expiration date cannot be in the past.";
        }
    }

    if ($unit_item == "piece") {
        $items_per_unit = 1;
    } else if (empty($_POST["items_per_unit"])) {
        $items_per_unitErr = "Required for pack or box!";
    } else if (!preg_match("/^\d+$/", $_POST["items_per_unit"])) {
        $items_per_unitErr = "Enter only numbers.";
    } else {
        $items_per_unit = $_POST["items_per_unit"];
    }

    if (in_array($unit_item, ['pack', 'box']) && empty($sell_as)) {
        $sell_asErr = "Please select how you want to sell this item.";
    }

    if (empty($name_itemErr) && empty($category_itemErr) && empty($brand_itemErr) && empty($size_itemErr) && empty($quantity_itemErr) && empty($unit_itemErr) && empty($cost_price_itemErr) && empty($selling_price_packErr) && empty($selling_price_individualErr) && empty($expiration_date_itemErr) && empty($items_per_unitErr) && empty($sell_asErr)) {
        $sell_as_pack = ($sell_as == "pack" || $sell_as == "both") ? 1 : 0;
        $sell_as_sachet = ($sell_as == "individual" || $sell_as == "both") ? 1 : 0;
        $availability_status = ($quantity_item > 0) ? "available" : "unavailable";

        $query = "UPDATE tbl_item SET 
                  name_item='$name_item', 
                  category_item='$category_item', 
                  brand_item='$brand_item', 
                  size_item='$size_item', 
                  quantity_item='$quantity_item', 
                  unit_item='$unit_item', 
                  cost_price_item='$cost_price_item', 
                  selling_price_item='$selling_price_pack', 
                  selling_price_individual='$selling_price_individual', 
                  availability_status_item='$availability_status', 
                  expiration_date_item='$expiration_date_item', 
                  items_per_unit='$items_per_unit', 
                  sell_as_pack='$sell_as_pack', 
                  sell_as_sachet='$sell_as_sachet' 
                  WHERE id_item='$id_item' AND id_user='$user_id'";
        if (mysqli_query($connections, $query)) {
            echo "<script>alert('Item updated successfully!'); window.location.href='view_stock.php?notify=Item%20updated%20successfully!';</script>";
        } else {
            error_log("update_item.php: Error updating item: " . mysqli_error($connections));
            echo "<script>alert('Error updating item: " . mysqli_error($connections) . "');</script>";
        }
    }
}
?>

<style>
    .container {
        width: 80%;
        margin: 20px auto;
        padding: 20px;
        background: #f9f9f9;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .error {
        color: red;
    }
    .label {
        font-weight: bold;
        font-size: 1.1em;
        margin-bottom: 5px;
    }
    .form-group {
        margin-bottom: 15px;
    }
    input[type="text"], input[type="date"], select {
        padding: 5px;
        width: 200px;
        font-size: 1em;
    }
    .action-btn {
        padding: 5px 10px;
        background: #4CAF50;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    }
    .action-btn:hover {
        background: #45a049;
    }
    .cancel-btn {
        background: #e74c3c;
    }
    .cancel-btn:hover {
        background: #c0392b;
    }
</style>

<script>
function allowNumbersOnly(event, allowComma = false) {
    var char = String.fromCharCode(event.which);
    if (allowComma) {
        if (!(/[0-9,.]/.test(char) || event.which == 8)) {
            event.preventDefault();
            return false;
        }
    } else {
        if (!(/[0-9]/.test(char) || event.which == 8)) {
            event.preventDefault();
            return false;
        }
    }
    return true;
}

function toggleFields() {
    var unit = document.getElementById("unit_item").value;
    var sellAsRow = document.getElementById("sell_as_row");
    var sellingPricePackRow = document.getElementById("selling_price_pack_row");
    var sellingPriceIndividualRow = document.getElementById("selling_price_individual_row");
    var itemsPerUnitRow = document.getElementById("items_per_unit_row");
    if (unit == "piece") {
        sellAsRow.style.display = "none";
        sellingPricePackRow.style.display = "none";
        sellingPriceIndividualRow.style.display = "block";
        itemsPerUnitRow.style.display = "none";
        document.getElementById("sell_as").value = "individual";
    } else if (unit == "pack" || unit == "box") {
        sellAsRow.style.display = "block";
        sellingPricePackRow.style.display = "block";
        sellingPriceIndividualRow.style.display = "block";
        itemsPerUnitRow.style.display = "block";
    } else {
        sellAsRow.style.display = "none";
        sellingPricePackRow.style.display = "block";
        sellingPriceIndividualRow.style.display = "none";
        itemsPerUnitRow.style.display = "none";
    }
    updateSellingPriceFields();
}

function updateSellingPriceFields() {
    var sellAs = document.getElementById("sell_as").value;
    var sellingPricePackRow = document.getElementById("selling_price_pack_row");
    var sellingPriceIndividualRow = document.getElementById("selling_price_individual_row");
    if (sellAs == "pack") {
        sellingPricePackRow.style.display = "block";
        sellingPriceIndividualRow.style.display = "none";
    } else if (sellAs == "individual") {
        sellingPricePackRow.style.display = "none";
        sellingPriceIndividualRow.style.display = "block";
    } else if (sellAs == "both") {
        sellingPricePackRow.style.display = "block";
        sellingPriceIndividualRow.style.display = "block";
    } else {
        sellingPricePackRow.style.display = "block";
        sellingPriceIndividualRow.style.display = "none";
    }
}
</script>

<div class="container">
    <form method="POST">
        <input type="hidden" name="id_item" value="<?php echo htmlspecialchars($id_item); ?>">
        <div class="form-group">
            <span class="label">Item Name</span><br>
            <input type="text" name="name_item" value="<?php echo htmlspecialchars($name_item); ?>">
            <span class="error"><?php echo $name_itemErr; ?></span>
        </div>

        <div class="form-group">
            <span class="label">Category</span><br>
            <select name="category_item" id="category_item">
                <option value="">Select Category</option>
                <option value="Daily Essentials" <?php echo $category_item == "Daily Essentials" ? "selected" : ""; ?>>Daily Essentials</option>
                <option value="Food & Groceries" <?php echo $category_item == "Food & Groceries" ? "selected" : ""; ?>>Food & Groceries</option>
                <option value="Electronics & Gadgets" <?php echo $category_item == "Electronics & Gadgets" ? "selected" : ""; ?>>Electronics & Gadgets</option>
                <option value="Health & Wellness" <?php echo $category_item == "Health & Wellness" ? "selected" : ""; ?>>Health & Wellness</option>
                <option value="Fashion & Accessories" <?php echo $category_item == "Fashion & Accessories" ? "selected" : ""; ?>>Fashion & Accessories</option>
                <option value="Home & Living" <?php echo $category_item == "Home & Living" ? "selected" : ""; ?>>Home & Living</option>
                <option value="Beauty & Personal Care" <?php echo $category_item == "Beauty & Personal Care" ? "selected" : ""; ?>>Beauty & Personal Care</option>
                <option value="Others" <?php echo $category_item == "Others" ? "selected" : ""; ?>>Others</option>
            </select>
            <span class="error"><?php echo $category_itemErr; ?></span>
        </div>

        <div class="form-group">
            <span class="label">Brand</span><br>
            <input type="text" name="brand_item" value="<?php echo htmlspecialchars($brand_item); ?>">
            <span class="error"><?php echo $brand_itemErr; ?></span>
        </div>

        <div class="form-group">
            <span class="label">Size</span><br>
            <input type="text" name="size_item" value="<?php echo htmlspecialchars($size_item); ?>">
            <span class="error"><?php echo $size_itemErr; ?></span>
        </div>

        <div class="form-group">
            <span class="label">Quantity</span><br>
            <input type="text" name="quantity_item" value="<?php echo htmlspecialchars($quantity_item); ?>" onkeypress="return allowNumbersOnly(event);">
            <span class="error"><?php echo $quantity_itemErr; ?></span>
        </div>

        <div class="form-group">
            <span class="label">Unit</span><br>
            <select name="unit_item" id="unit_item" onchange="toggleFields()">
                <option value="">Select Unit</option>
                <option value="piece" <?php echo $unit_item == "piece" ? "selected" : ""; ?>>Piece</option>
                <option value="pack" <?php echo $unit_item == "pack" ? "selected" : ""; ?>>Pack</option>
                <option value="box" <?php echo $unit_item == "box" ? "selected" : ""; ?>>Box</option>
                <option value="kg" <?php echo $unit_item == "kg" ? "selected" : ""; ?>>Kilogram</option>
                <option value="grams" <?php echo $unit_item == "grams" ? "selected" : ""; ?>>Grams</option>
            </select>
            <span class="error"><?php echo $unit_itemErr; ?></span>
        </div>

        <div class="form-group">
            <span class="label">Cost Price</span><br>
            <input type="text" name="cost_price_item" value="<?php echo htmlspecialchars($cost_price_item); ?>" onkeypress="return allowNumbersOnly(event, true);">
            <span class="error"><?php echo $cost_price_itemErr; ?></span>
        </div>

        <div class="form-group" id="sell_as_row" style="display: <?php echo in_array($unit_item, ['pack', 'box']) ? 'block' : 'none'; ?>;">
            <span class="label">Sell As</span><br>
            <select name="sell_as" id="sell_as" onchange="updateSellingPriceFields()">
                <option value="">Select Option</option>
                <option value="pack" <?php echo $sell_as == "pack" ? "selected" : ""; ?>>Pack Only</option>
                <option value="individual" <?php echo $sell_as == "individual" ? "selected" : ""; ?>>Individual Only</option>
                <option value="both" <?php echo $sell_as == "both" ? "selected" : ""; ?>>Both</option>
            </select>
            <span class="error"><?php echo $sell_asErr; ?></span>
        </div>

        <div class="form-group" id="selling_price_pack_row" style="display: <?php echo in_array($unit_item, ['pack', 'box', 'kg', 'grams']) ? 'block' : 'none'; ?>;">
            <span class="label">Selling Price (Pack/Box)</span><br>
            <input type="text" name="selling_price_pack" value="<?php echo htmlspecialchars($selling_price_pack); ?>" onkeypress="return allowNumbersOnly(event, true);">
            <span class="error"><?php echo $selling_price_packErr; ?></span>
        </div>

        <div class="form-group" id="selling_price_individual_row" style="display: <?php echo ($unit_item == 'piece' || in_array($sell_as, ['individual', 'both'])) ? 'block' : 'none'; ?>;">
            <span class="label">Selling Price (Individual)</span><br>
            <input type="text" name="selling_price_individual" value="<?php echo htmlspecialchars($selling_price_individual); ?>" onkeypress="return allowNumbersOnly(event, true);">
            <span class="error"><?php echo $selling_price_individualErr; ?></span>
        </div>

        <div class="form-group" id="items_per_unit_row" style="display: <?php echo in_array($unit_item, ['pack', 'box']) ? 'block' : 'none'; ?>;">
            <span class="label">Items per Unit</span><br>
            <input type="text" name="items_per_unit" value="<?php echo htmlspecialchars($items_per_unit); ?>" onkeypress="return allowNumbersOnly(event);">
            <span class="error"><?php echo $items_per_unitErr; ?></span>
        </div>

        <div class="form-group">
            <span class="label">Expiration Date</span><br>
            <input type="date" name="expiration_date_item" value="<?php echo htmlspecialchars($expiration_date_item); ?>">
            <span class="error"><?php echo $expiration_date_itemErr; ?></span>
        </div>

        <div class="form-group">
            <input type="submit" name="btnUpdateItem" value="Update Item" class="action-btn">
            <a href="view_stock.php" class="action-btn cancel-btn">Cancel</a>
        </div>
    </form>
</div>

<script>
    toggleFields();
</script>