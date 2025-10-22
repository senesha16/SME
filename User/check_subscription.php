<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila'); // Adjust to your timezone
if (!isset($_SESSION["email"])) {
    error_log("check_subscription.php: No user logged in, redirecting to login.php");
    echo "<script>window.location.href='../';</script>";
    exit;
}

include("../connections.php");
$email = mysqli_real_escape_string($connections, $_SESSION["email"]);

// Fetch user data
$user_query = mysqli_query($connections, "SELECT trial_start_date, subscription_approved, subscription_proof, subscription_plan FROM tbl_user WHERE email='$email'");
if (!$user_query) {
    error_log("check_subscription.php: Database error: " . mysqli_error($connections));
    echo "<script>alert('Database error, please try again.'); window.location.href='Subscribe.php?notify=Database%20error,%20please%20try%20again.';</script>";
    exit;
}
$user_row = mysqli_fetch_assoc($user_query);
if (!$user_row) {
    error_log("check_subscription.php: User not found for email: $email");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

// Default to no access
unset($_SESSION['effective_plan']);
error_log("check_subscription.php: Unset effective_plan for $email");

if ($user_row['subscription_approved'] == 1) {
    // User is subscribed, set their actual plan
    $_SESSION['effective_plan'] = $user_row['subscription_plan'];
    error_log("check_subscription.php: User $email is subscribed, plan: " . $user_row['subscription_plan']);
} else {
    if (is_null($user_row['trial_start_date']) || empty($user_row['trial_start_date'])) {
        // No trial started, redirect to Subscribe.php
        error_log("check_subscription.php: No trial started for $email, redirecting to Subscribe.php");
        echo "<script>window.location.href='Subscribe.php?notify=Start%20your%20trial%20or%20subscribe.';</script>";
        exit;
    }

    // Calculate trial expiration
    try {
        $trial_start = new DateTime($user_row['trial_start_date']);
        $now = new DateTime();
        $trial_duration = 60; // 1 minute for testing (in seconds)
        $seconds_since_trial = $now->getTimestamp() - $trial_start->getTimestamp();
        $is_trial_active = $seconds_since_trial < $trial_duration;

        error_log("check_subscription.php: Trial check for $email - trial_start: " . $trial_start->format('Y-m-d H:i:s') . ", now: " . $now->format('Y-m-d H:i:s') . ", seconds_since_trial: $seconds_since_trial, is_trial_active: " . ($is_trial_active ? 'true' : 'false'));

        if (!$is_trial_active) {
            // Trial expired, redirect to Subscribe.php
            $notify = ($user_row['subscription_proof'] != '' && $user_row['subscription_approved'] == 0) ? "Your subscription is pending approval." : "Your trial has expired. Please subscribe.";
            $current_page = basename($_SERVER['PHP_SELF']);
            error_log("check_subscription.php: Trial expired for $email, redirecting from $current_page to Subscribe.php");
            if ($current_page != 'Subscribe.php') {
                echo "<script>window.location.href='Subscribe.php?notify=" . urlencode($notify) . "';</script>";
                exit;
            }
        } else {
            // Trial is active, grant full access (Plan C)
            $_SESSION['effective_plan'] = 'C';
            error_log("check_subscription.php: Trial active for $email, granting Plan C access");
        }
    } catch (Exception $e) {
        error_log("check_subscription.php: DateTime error for $email: " . $e->getMessage());
        echo "<script>alert('Error processing trial date.'); window.location.href='Subscribe.php?notify=Error%20processing%20trial%20date.';</script>";
        exit;
    }
}
?>