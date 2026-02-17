<?php
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; form-action 'self'; upgrade-insecure-requests; base-uri 'self';");

include("nav.php");
include("csrf_helper.php");
include("../connections.php");

// === FULLY STANDALONE MODE ===
// No login required
// No subscription checks
// No account_type restrictions
// All items are shared in the system (single inventory)

// Fixed display name for header (change if you want a custom store name)
$full_name = "Cashier";

// Initialize variables
$item_name = "";
$id_item = "";

// Check if an item ID is provided via GET
if (!isset($_GET["delete"]) || empty($_GET["delete"])) {
    echo "<script>alert('No item selected for deletion.'); window.location.href='view_stock.php';</script>";
    exit;
}

// Sanitize and fetch item name
$id_item = mysqli_real_escape_string($connections, $_GET["delete"]);
$query = mysqli_query($connections, "SELECT name_item FROM tbl_item WHERE id_item='$id_item'");
if ($query === false) {
    echo "<script>alert('Database error. Please try again later.'); window.location.href='view_stock.php';</script>";
    exit;
}

if (mysqli_num_rows($query) > 0) {
    $item = mysqli_fetch_assoc($query);
    $item_name = $item["name_item"];
} else {
    echo "<script>alert('Item not found.'); window.location.href='view_stock.php';</script>";
    exit;
}

// Handle confirmed deletion
if (isset($_POST["btnConfirmDelete"])) {
    validate_csrf_token();
    $id_item = mysqli_real_escape_string($connections, $_POST["id_item"]);
    
    // Delete the item (no id_user restriction — works on shared standalone inventory)
    $delete_query = mysqli_query($connections, "DELETE FROM tbl_item WHERE id_item='$id_item'");
    
    if ($delete_query) {
        echo "<script>alert('Item deleted successfully!'); window.location.href='view_stock.php?notify=Item deleted successfully!';</script>";
    } else {
        echo "<script>alert('Error deleting item: " . addslashes(mysqli_error($connections)) . "'); window.location.href='view_stock.php';</script>";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Delete - SME Dashboard</title>
    <link rel="stylesheet" href="user-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        .main-content {
            margin-left: 250px;
            padding: 30px;
            flex-grow: 1;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .container h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: 600;
        }
        .container h4 {
            color: #333;
            margin-bottom: 25px;
            font-size: 18px;
        }
        .action-btn {
            padding: 12px 25px;
            margin: 0 10px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .delete-btn {
            background: #e74c3c; /* Red for delete, matching your hints */
            color: white;
        }
        .delete-btn:hover {
            background: #c0392b;
        }
        .cancel-btn {
            background: #4CAF50; /* Green for cancel, lighter feel */
            color: white;
        }
        .cancel-btn:hover {
            background: #45a049;
        }
        @media (max-width: 768px) {
            .container {
                margin: 15px;
                padding: 20px;
            }
            .action-btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <main class="main-content">
        <div class="container">
            <h3>Confirm Delete - Welcome, <?php echo htmlspecialchars($full_name); ?></h3>
            <h4>Are you sure you want to delete the item: <?php echo htmlspecialchars($item_name); ?>?</h4>
            <form method="POST">
                <?php echo csrf_token_field(); ?>
                <input type="hidden" name="id_item" value="<?php echo htmlspecialchars($id_item); ?>">
                <input type="submit" name="btnConfirmDelete" value="Delete" class="action-btn delete-btn">
                <a href="view_stock.php" class="action-btn cancel-btn">Cancel</a>
            </form>
        </div>
    </main>
</body>
</html>