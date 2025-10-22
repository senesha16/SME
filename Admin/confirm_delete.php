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
// Intentionally NOT including nav.php so this page shows as a standalone popup/modal

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
        // Redirect to retriever.php as requested
        echo "<script>window.location.href='retriever.php?notify=$notify';</script>";
    } else {
        echo "<script>alert('Error deleting user: " . mysqli_error($connections) . "'); window.location.href='retriever.php';</script>";
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Confirm Delete</title>
    <style>
        /* Fullscreen backdrop */
        body{margin:0;font-family:Arial,Helvetica,sans-serif;background:transparent}
        .modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,0.6);display:flex;align-items:center;justify-content:center;z-index:9999}
        .modal{width:92%;max-width:680px;background:#fff;border-radius:10px;padding:22px;border:1px solid #e6eef7;box-shadow:0 12px 40px rgba(2,6,23,0.2)}
        .modal h2{margin:0 0 6px 0;font-size:20px}
        .modal p{color:#4b5563;margin:6px 0 14px}
        .user-row{display:flex;gap:12px;align-items:center;padding:12px;border-radius:8px;background:#f8fafc;border:1px solid #eef2f7}
        .avatar{width:48px;height:48px;border-radius:8px;background:#e6eef8;display:flex;align-items:center;justify-content:center;font-weight:700}
        .user-info .name{font-weight:700}
        .actions{display:flex;gap:10px;justify-content:flex-end;margin-top:16px}
        .btn{padding:10px 16px;border-radius:8px;border:0;cursor:pointer;font-weight:700}
        .btn-cancel{background:#f3f4f6;color:#111;border:1px solid #e5e7eb;text-decoration:none}
        .btn-delete{background:#dc2626;color:#fff}
        @media (max-width:480px){.modal{padding:16px}}
    </style>
</head>
<body>
    <div class="modal-backdrop" role="dialog" aria-modal="true">
        <div class="modal">
            <h2>Confirm Delete User</h2>
            <p>This will permanently remove the user and related data. This action cannot be undone.</p>

            <div class="user-row">
                <div class="avatar"><?php echo strtoupper(substr($db_first_name,0,1)); ?></div>
                <div class="user-info">
                    <div class="name"><?php echo htmlspecialchars($full_name); ?></div>
                    <div class="meta">User ID: <?php echo htmlspecialchars($id_user); ?> &middot; Permanent delete</div>
                </div>
            </div>

            <form method="POST">
                <div class="actions">
                    <a href="retriever.php" class="btn btn-cancel">Cancel</a>
                    <button type="submit" name="btnDelete" class="btn btn-delete">Delete user</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>