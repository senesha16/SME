<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION["email"])) {
    error_log("subscribe.php: No user logged in, redirecting to login.php");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

include("../connections.php");
include("check_subscription.php");

$email = mysqli_real_escape_string($connections, $_SESSION["email"]);
$notify = isset($_GET["notify"]) ? $_GET["notify"] : "";
$uploadErr = "";

$user_query = mysqli_query($connections, "SELECT trial_start_date, subscription_proof, subscription_approved, subscription_plan, requested_plan FROM tbl_user WHERE email='$email'");
if (!$user_query) {
    error_log("subscribe.php: Database error: " . mysqli_error($connections));
    $uploadErr = "Database error: " . mysqli_error($connections);
}
$user_row = mysqli_fetch_assoc($user_query);
if (!$user_row) {
    error_log("subscribe.php: User not found for email: $email");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

if ($user_row['subscription_approved'] == 1 && !$user_row['subscription_proof']) {
    error_log("subscribe.php: User $email is subscribed, redirecting to index.php");
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

// Check trial status
$status_message = "";
if (is_null($user_row['trial_start_date']) || empty($user_row['trial_start_date'])) {
    $status_message = "Your trial has not started yet. Log in to begin your 1-minute free trial.";
    error_log("subscribe.php: No trial started for $email");
} else {
    try {
        $trial_start = new DateTime($user_row['trial_start_date']);
        $now = new DateTime();
        $trial_duration = 60; // 1 minute for testing
        $seconds_left = $trial_duration - ($now->getTimestamp() - $trial_start->getTimestamp());
        $is_trial_active = $seconds_left > 0;

        error_log("subscribe.php: Trial check for $email - trial_start: " . $trial_start->format('Y-m-d H:i:s') . ", now: " . $now->format('Y-m-d H:i:s') . ", seconds_left: $seconds_left, is_trial_active: " . ($is_trial_active ? 'true' : 'false'));

        if ($is_trial_active) {
            $status_message = "You are on a 1-minute free trial. $seconds_left seconds remaining.";
            error_log("subscribe.php: Trial active for $email, redirecting to index.php");
            echo "<script>window.location.href='index.php';</script>";
            exit;
        } else {
            $status_message = "Your 1-minute trial has expired. Please subscribe.";
            unset($_SESSION['effective_plan']);
            error_log("subscribe.php: Trial expired for $email");
        }
    } catch (Exception $e) {
        error_log("subscribe.php: DateTime error for $email: " . $e->getMessage());
        $status_message = "Error processing trial date. Please subscribe.";
    }
}

$target_dir = "subscription_proofs/";
$full_dir = $_SERVER['DOCUMENT_ROOT'] . '/sme/' . $target_dir;
if (!file_exists($full_dir)) {
    if (!mkdir($full_dir, 0755, true)) {
        error_log("subscribe.php: Failed to create directory: $full_dir");
    }
}
if (!is_writable($full_dir)) {
    error_log("subscribe.php: Directory not writable: $full_dir");
}

// Handle subscription upload
if (isset($_POST["btnSubscribe"])) {
    $subscription_plan = $_POST["subscription_plan"] ?? '';
    if (empty($subscription_plan) || !in_array($subscription_plan, ['A', 'B', 'C'])) {
        $uploadErr = "Please select a valid subscription plan.";
        error_log("subscribe.php: Invalid or missing subscription plan for $email");
    } elseif (isset($_FILES["proof_file"]) && $_FILES["proof_file"]["error"] == 0) {
        $target_file = $target_dir . basename($_FILES["proof_file"]["name"]);
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $full_path = $_SERVER['DOCUMENT_ROOT'] . '/sme/' . $target_file;

        if (file_exists($full_path)) {
            $target_file = $target_dir . rand(1000,9999) . "_" . basename($_FILES["proof_file"]["name"]);
            $full_path = $_SERVER['DOCUMENT_ROOT'] . '/sme/' . $target_file;
        }

        if ($_FILES["proof_file"]["size"] > 5000000) {
            $uploadErr = "File too large (max 5MB).";
            $uploadOk = 0;
            error_log("subscribe.php: File too large for $email");
        }

        if (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'pdf'])) {
            $uploadErr = "Only JPG, JPEG, PNG, PDF allowed.";
            $uploadOk = 0;
            error_log("subscribe.php: Invalid file type for $email: $imageFileType");
        }

        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["proof_file"]["tmp_name"], $full_path)) {
                $file_type = mime_content_type($full_path);
                error_log("subscribe.php: Uploaded file for $email: $full_path, FileType=$file_type");
                $target_file = mysqli_real_escape_string($connections, $target_file);
                $query = mysqli_query($connections, "UPDATE tbl_user SET subscription_proof='$target_file', requested_plan='$subscription_plan', subscription_approved=0 WHERE email='$email'");
                if ($query) {
                    $notify = "Proof uploaded! Waiting for admin approval. You can still use your current plan (Plan " . htmlspecialchars($user_row['subscription_plan']) . ").";
                    error_log("subscribe.php: Proof uploaded for $email, plan: $subscription_plan");
                    echo "<script>window.location.href='subscribe.php?notify=" . urlencode($notify) . "';</script>";
                } else {
                    $uploadErr = "Database update failed: " . mysqli_error($connections);
                    error_log("subscribe.php: Database update failed for $email: " . mysqli_error($connections));
                }
            } else {
                $uploadErr = "Error uploading file to $full_path. PHP Error: " . $_FILES["proof_file"]["error"];
                error_log("subscribe.php: Upload failed for $email: Target = $full_path, PHP Error = " . $_FILES["proof_file"]["error"]);
            }
        }
    } else {
        $uploadErr = "No file selected or upload error: " . ($_FILES["proof_file"]["error"] ?? 'Unknown');
        error_log("subscribe.php: Upload error for $email: " . ($_FILES["proof_file"]["error"] ?? 'Unknown'));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe - <?php echo $business_name; ?> Dashboard</title>
    <link rel="stylesheet" href="user-dashboard.css">
    <link rel="stylesheet" href="user-bar.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .page-header {
            background: linear-gradient(135deg, #E62727 0%, #c62d2d 100%);
            color: white;
            padding: 30px 25px;
            margin-bottom: 30px;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
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
        .transaction-section {
            max-width: 1200px;
            margin: 0 auto 25px;
            background: white;
            border-radius: 15px;
            padding: 30px;
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
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #E62727;
            box-shadow: 0 0 0 3px rgba(230, 39, 39, 0.1);
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
        .error {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .notification {
            color: #2ecc71;
            font-size: 14px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        @media (max-width: 768px) {
            .page-header, .transaction-section {
                padding: 15px;
                margin: 0 15px 25px;
            }
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include("nav.php"); ?>
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-text">
                <h1><i class="fas fa-lock"></i> Subscription</h1>
               
            </div>
        </div>
    </div>

    <!-- Subscription Section -->
    <div class="transaction-section">
        <div class="section-title">
            <i class="fas fa-credit-card"></i>
            Subscription Details
        </div>
        <p><?php echo htmlspecialchars($status_message); ?></p>
        <p>Make a one-time payment via GCash to 0906 082 0723, then upload proof below to subscribe. Prices are as follows:</p>
        <ul style="list-style-type: none; padding-left: 0;">
            <li><strong>Plan A (Basic Inventory):</strong> ₱250</li>
            <li><strong>Plan B (Inventory + Calendar):</strong> ₱300</li>
            <li><strong>Plan C (Inventory + Calendar + Transactions):</strong> ₱350</li>
        </ul>

        <?php if ($notify != "") echo "<p class='notification'><i class='fas fa-check-circle'></i>" . htmlspecialchars($notify) . "</p>"; ?>
        <?php if ($uploadErr != "") echo "<p class='error'><i class='fas fa-exclamation-triangle'></i>" . htmlspecialchars($uploadErr) . "</p>"; ?>

        <?php if ($user_row['subscription_proof'] != ''): ?>
            <p>Your subscription is pending approval for Plan <?php echo htmlspecialchars($user_row['requested_plan']); ?>. You can still use your current plan (Plan <?php echo htmlspecialchars($user_row['subscription_plan']); ?>). Press <a href="../logout.php" class="btn-primary">Here</a> to go back.</p>
            <p>Please wait for admin to review. You can log out and check back later.</p>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data" class="mt-4">
                <div class="form-group">
                    <label for="subscription_plan"><i class="fas fa-list"></i> Select Plan</label>
                    <select name="subscription_plan" id="subscription_plan" class="form-control" required>
                        <option value="">Select Plan</option>
                        <option value="A">Plan A (Basic Inventory) - ₱250</option>
                        <option value="B">Plan B (Inventory + Calendar) - ₱300</option>
                        <option value="C">Plan C (Inventory + Calendar + Transactions) - ₱350</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="proof_file"><i class="fas fa-upload"></i> Upload Proof</label>
                    <input type="file" name="proof_file" id="proof_file" accept=".jpg,.jpeg,.png,.pdf" class="form-control" required>
                </div>
                <input type="submit" name="btnSubscribe" value="Upload Proof" class="btn-primary">
            </form>
        <?php endif; ?>
    </div>
</body>
</html>