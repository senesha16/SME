<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION["email"])) {
    error_log("access_control.php: No user logged in, redirecting to login.php");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

include("../connections.php");
include("check_subscription.php");

$email = mysqli_real_escape_string($connections, $_SESSION["email"]);
$user_query = mysqli_query($connections, "SELECT subscription_approved, subscription_plan, trial_start_date, subscription_proof, requested_plan FROM tbl_user WHERE email='$email'");
if (!$user_query) {
    error_log("access_control.php: Database error: " . mysqli_error($connections));
    echo "<script>alert('Database error. Please try again later.'); window.location.href='login.php';</script>";
    exit;
}
$user_row = mysqli_fetch_assoc($user_query);
if (!$user_row) {
    error_log("access_control.php: User not found for email: $email");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

// Access control
$allowed_pages = [
    'MyAccount.php' => ['A', 'B', 'C'],
    'view_stock.php' => ['A', 'B', 'C'],
    'add_item.php' => ['A', 'B', 'C'],
    'adjust_stock.php' => ['A', 'B', 'C'],
    'confirm_delete_item.php' => ['A', 'B', 'C'],
    'planner.php' => ['B', 'C'],
    'transaction.php' => ['C']
];
$current_page = basename($_SERVER['PHP_SELF']);
$is_trial_active = false;

if ($user_row['trial_start_date']) {
    try {
        $trial_start = new DateTime($user_row['trial_start_date']);
        $now = new DateTime();
        $trial_duration = 60; // 1 minute for testing
        $seconds_left = $trial_duration - ($now->getTimestamp() - $trial_start->getTimestamp());
        $is_trial_active = $seconds_left > 0 && !$user_row['subscription_approved'];
    } catch (Exception $e) {
        error_log("access_control.php: DateTime error for $email: " . $e->getMessage());
    }
}

// Determine effective plan
$effective_plan = $is_trial_active ? 'C' : $user_row['subscription_plan']; // Default to current plan
if ($user_row['subscription_proof'] && !$user_row['subscription_approved'] && $user_row['requested_plan']) {
    $effective_plan = $user_row['subscription_plan']; // Use current plan during pending approval
} elseif ($user_row['subscription_approved']) {
    $effective_plan = $user_row['subscription_plan']; // Use approved plan
}
$_SESSION['effective_plan'] = $effective_plan;

if (!$effective_plan && !in_array($current_page, array_keys($allowed_pages))) {
    error_log("access_control.php: Access denied for $email to $current_page - no effective plan");
    echo "<script>alert('Access denied. Please subscribe to access this feature.'); window.location.href='subscribe.php';</script>";
    exit;
}

// Check if the current page is allowed for the effective plan
if (isset($allowed_pages[$current_page]) && !in_array($effective_plan, $allowed_pages[$current_page])) {
    error_log("access_control.php: Access denied for $email to $current_page - insufficient plan");
    echo "<script>alert('Access denied. Please upgrade your subscription to access this feature.'); window.location.href='subscribe.php';</script>";
    exit;
}
?>