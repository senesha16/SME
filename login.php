<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include("connections.php");

// Check database connection
if (!$connections) {
    die("Database connection failed: " . mysqli_connect_error());
}

if (isset($_SESSION["email"])) {
    $email = $_SESSION["email"];
    $query_account_type = mysqli_query($connections, "SELECT * FROM tbl_user WHERE email='$email'");
    $get_account_type = mysqli_fetch_assoc($query_account_type);
    $account_type = $get_account_type["account_type"];

    if ($account_type == 1) {
        echo "<script>window.location.href='Admin/index.php';</script>";
    } else {
        echo "<script>window.location.href='User';</script>";
    }
}

date_default_timezone_set("Asia/Manila");
$date_now = date("m/d/Y");
$time_now = date("h:i a");
$notify = $attempt = $log_time = "";

$end_time = date("h:i A", strtotime("+15 minutes", strtotime($time_now)));

$email = $password = "";
$emailErr = $passwordErr = "";

if (isset($_POST["btnLogin"])) {
    if (empty($_POST["email"])) {
        $emailErr = "Email is required";
    } else {
        $email = $_POST["email"];
    }

    if (empty($_POST["password"])) {
        $passwordErr = "Password is required!";
    } else {
        $password = $_POST["password"];
    }

    if ($email && $password) {
        $check_email = mysqli_query($connections, "SELECT * FROM tbl_user WHERE email='$email'");
        $check_row = mysqli_num_rows($check_email);
        if ($check_row > 0) {
            $fetch = mysqli_fetch_assoc($check_email);
            $db_password = $fetch["password"];
            $db_attempt = $fetch["attempt"];
            $db_log_time = strtotime($fetch["log_time"]);
            $my_log_time = $fetch["log_time"];
            $new_time = strtotime($time_now);
            $account_type = $fetch["account_type"];

            if ($account_type == "1") {
                if ($db_password == $password) {
                    $_SESSION["email"] = $email;
                    echo "<script>window.location.href='Admin';</script>";
                } else {
                    $passwordErr = "Hi Admin! Your Password is incorrect!";
                }
            } else {
                if ($db_log_time <= $new_time || empty($my_log_time)) {
                    if ($db_password == $password) {
                        $_SESSION["email"] = $email;
                        mysqli_query($connections, "UPDATE tbl_user SET attempt = '', log_time = '' WHERE email = '$email'");

                        $user_query = mysqli_query($connections, "SELECT trial_start_date, subscription_approved, subscription_proof FROM tbl_user WHERE email='$email'");
                        $user_row = mysqli_fetch_assoc($user_query);
                        
                        if (is_null($user_row['trial_start_date'])) {
                            mysqli_query($connections, "UPDATE tbl_user SET trial_start_date = NOW() WHERE email='$email'");
                        }

                        $trial_end = date('Y-m-d H:i:s', strtotime($user_row['trial_start_date'] . ' +1 minute'));
                        $now = date('Y-m-d H:i:s');

                        if ($user_row['subscription_approved'] == 1) {
                            echo "<script>window.location.href='User';</script>";
                        } elseif ($now < $trial_end) {
                            echo "<script>window.location.href='User';</script>";
                        } else {
                            $notify = ($user_row['subscription_proof'] != '' && $user_row['subscription_approved'] == 0) ? "Your subscription is pending admin approval. Please check back later." : "Your trial has expired. Please subscribe to continue.";
                            echo "<script>window.location.href='User/Subscribe.php?notify=$notify';</script>";
                        }
                    } else {
                        $attempt = (int)$db_attempt + 1;
                        if ($attempt >= 3) {
                            $attempt = 3;
                            mysqli_query($connections, "UPDATE tbl_user SET attempt='$attempt', log_time='$end_time' WHERE email='$email'");
                            $notify = "You already reached the three (3) times attempt to login. Please Login after 15 minutes: <b>$end_time</b>";
                        } else {
                            mysqli_query($connections, "UPDATE tbl_user SET attempt='$attempt' WHERE email='$email'");
                            $passwordErr = "Password is incorrect!";
                            $notify = "Login Attempt: <b>$attempt</b>";
                        }
                    }
                } else {
                    $notify = "I'm Sorry, You have to wait until: <b>$my_log_time</b> before login";
                }
            }
        } else {
            $emailErr = "Email is not registered";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SME Login</title>
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <div class="blur-orb blur-orb-1"></div>
    <div class="blur-orb blur-orb-2"></div>
    <div class="blur-orb blur-orb-3"></div>
    
    <div class="auth-container">
        <div class="auth-header">
            <div class="logo">
                <img src="sabang_logo.png" alt="SME Logo">
            </div>
            <h2>Welcome Back!</h2>
            <p>Sign in to your SME account</p>
        </div>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" 
                       name="email" 
                       class="form-input" 
                       placeholder="Enter your email address" 
                       value="<?php echo htmlspecialchars($email); ?>" 
                       required>
                <span class="error-message"><?php echo $emailErr; ?></span>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" 
                       name="password" 
                       class="form-input" 
                       placeholder="Enter your password" 
                       required>
                <span class="error-message"><?php echo $passwordErr; ?></span>
            </div>

            <button type="submit" name="btnLogin" class="auth-btn">
                Sign In
            </button>

            <?php if (!empty($notify)): ?>
                <div class="notification">
                    <?php echo $notify; ?>
                </div>
            <?php endif; ?>
        </form>

        <div class="auth-footer">
            <a href="index.php">Home</a>
            <span class="divider">|</span>
            <a href="register.php">Create Account</a>
        </div>
    </div>

    <script>
        const notification = document.querySelector('.notification');
        if (notification) {
            setTimeout(function() {
                notification.style.opacity = '0';
                setTimeout(function() {
                    notification.style.display = 'none';
                }, 300);
            }, 10000);
        }
    </script>
</body>
</html>