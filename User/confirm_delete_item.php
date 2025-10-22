<?php
session_start();
include("nav.php");
include("access_control.php");
include("../connections.php");

// Check if user is logged in
if (!isset($_SESSION["email"]) || empty($_SESSION["email"])) {
    error_log("confirm_delete_item.php: No user logged in, redirecting to login.php");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

// Check subscription plan
$email = mysqli_real_escape_string($connections, $_SESSION["email"]);
$query_info = mysqli_query($connections, "SELECT id_user, first_name, subscription_approved, subscription_plan, trial_start_date FROM tbl_user WHERE email='$email'");
if ($query_info === false) {
    error_log("confirm_delete_item.php: Database error: " . mysqli_error($connections));
    echo "<script>alert('Database error. Please try again later.'); window.location.href='login.php';</script>";
    exit;
}
$my_info = mysqli_fetch_assoc($query_info);
if (!$my_info) {
    error_log("confirm_delete_item.php: User not found for email: $email");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}
$user_id = $my_info["id_user"];
$full_name = $my_info["first_name"];
$subscription_approved = $my_info["subscription_approved"];
$subscription_plan = $my_info["subscription_plan"];
$is_trial_active = false;

if ($my_info["trial_start_date"]) {
    try {
        $trial_start = new DateTime($my_info["trial_start_date"]);
        $current_date = new DateTime();
        $trial_duration = 30; // 30-day trial period
        $days_since_trial = $trial_start->diff($current_date)->days;
        $is_trial_active = $days_since_trial <= $trial_duration;
    } catch (Exception $e) {
        error_log("confirm_delete_item.php: DateTime error for $email: " . $e->getMessage());
    }
}

$required_plan = ['A', 'B', 'C'];
if (!$is_trial_active && (!$subscription_approved || !in_array($subscription_plan, $required_plan))) {
    error_log("confirm_delete_item.php: Access denied for $email, trial expired or insufficient plan");
    echo "<script>alert('Access denied. Please upgrade to Plan A, B, or C to delete items.'); window.location.href='MyAccount.php';</script>";
    exit;
}

$item_name = "";
if (isset($_GET["delete"])) {
    $id_item = mysqli_real_escape_string($connections, $_GET["delete"]);
    $query = mysqli_query($connections, "SELECT name_item FROM tbl_item WHERE id_item='$id_item' AND id_user='$user_id'");
    if ($query === false) {
        error_log("confirm_delete_item.php: Query failed: " . mysqli_error($connections));
        echo "<script>alert('Database error. Please try again later.'); window.location.href='view_stock.php';</script>";
        exit;
    }
    if (mysqli_num_rows($query) > 0) {
        $item = mysqli_fetch_assoc($query);
        $item_name = $item["name_item"];
    } else {
        error_log("confirm_delete_item.php: Item not found for id_item: $id_item, user_id: $user_id");
        echo "<script>alert('Item not found.'); window.location.href='view_stock.php';</script>";
        exit;
    }
}

if (isset($_POST["btnConfirmDelete"])) {
    $id_item = mysqli_real_escape_string($connections, $_POST["id_item"]);
    $delete_query = mysqli_query($connections, "DELETE FROM tbl_item WHERE id_item='$id_item' AND id_user='$user_id'");
    if ($delete_query) {
        echo "<script>alert('Item deleted successfully!'); window.location.href='view_stock.php?notify=Item deleted successfully!';</script>";
    } else {
        error_log("confirm_delete_item.php: Delete failed: " . mysqli_error($connections));
        echo "<script>alert('Error deleting item: " . addslashes(mysqli_error($connections)) . "'); window.location.href='view_stock.php';</script>";
    }
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
                <input type="hidden" name="id_item" value="<?php echo htmlspecialchars($id_item); ?>">
                <input type="submit" name="btnConfirmDelete" value="Delete" class="action-btn delete-btn">
                <a href="view_stock.php" class="action-btn cancel-btn">Cancel</a>
            </form>
        </div>
    </main>
</body>
</html>