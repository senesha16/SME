```php
<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session data and destroy the session
session_unset();
session_destroy();

echo "Logging out ... Please wait ...";
echo "<script>window.location.href='login.php';</script>";
exit();
?>
```