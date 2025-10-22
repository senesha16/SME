<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION["email"])) {
    echo "<script>window.location.href='../';</script>";
    exit;
}

include("../connections.php");
include("nav.php");

// Verify admin privileges
$email = mysqli_real_escape_string($connections, $_SESSION["email"]);
$query_account_type = mysqli_query($connections, "SELECT account_type FROM tbl_user WHERE email='$email'");
$user_row = mysqli_fetch_assoc($query_account_type);
if (!$user_row || $user_row['account_type'] != '1') {
    echo "<script>window.location.href='../';</script>";
    exit;
}

// Get and sanitize id_user
$id_user = isset($_GET["id_user"]) ? mysqli_real_escape_string($connections, $_GET["id_user"]) : null;
if (!$id_user) {
    echo "<script>alert('Invalid user ID.'); window.location.href='ViewRecord.php';</script>";
    exit;
}

// Fetch user details
$query_name = mysqli_query($connections, "SELECT first_name, middle_name, last_name FROM tbl_user WHERE id_user='$id_user'");
if (!$query_name) {
    echo "<script>alert('Error fetching user: " . mysqli_error($connections) . "'); window.location.href='ViewRecord.php';</script>";
    exit;
}
$row_ = mysqli_fetch_assoc($query_name);
if (!$row_) {
    echo "<script>alert('User not found.'); window.location.href='ViewRecord.php';</script>";
    exit;
}

$db_first_name = $row_["first_name"];
$db_middle_name = $row_["middle_name"];
$db_last_name = $row_["last_name"];
$full_name = ucfirst($db_first_name) . " " . ($db_middle_name ? ucfirst($db_middle_name[0]) . ". " : "") . ucfirst($db_last_name);

// Handle deletion
if (isset($_POST["btnDelete"])) {
    $delete_query = mysqli_query($connections, "DELETE FROM tbl_user WHERE id_user='$id_user'");
    if ($delete_query) {
        $notify = urlencode("$full_name has been successfully deleted!");
        echo "<script>window.location.href='ViewRecord.php?notify=$notify';</script>";
    } else {
        echo "<script>alert('Error deleting user: " . mysqli_error($connections) . "'); window.location.href='ViewRecord.php';</script>";
    }
}
?>

<style>
    .btn-primary {
        padding: 5px 10px;
        background: #4CAF50;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-primary:hover {
        background: #45a049;
    }
    .btn-delete {
        padding: 5px 10px;
        background: #e74c3c;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-delete:hover {
        background: #c0392b;
    }
</style>

<br>
<br>
<br>

<center>
    <form method="POST">
        <h4>You are about to delete this user: <font color="red"><?php echo htmlspecialchars($full_name); ?></font></h4>
        <input type="submit" name="btnDelete" value="Confirm" class="btn-primary"> &nbsp; &nbsp; 
        <a href="ViewRecord.php" class="btn-delete">Cancel</a>
    </form>
</center>