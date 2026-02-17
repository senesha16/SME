<?php
// csrf_helper.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate or reuse CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

function validate_csrf_token() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed. Please try again.");
        // In production you might want to log this and redirect instead of die()
    }
    // Optional: regenerate token after successful validation (one-time-use)
    // $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>