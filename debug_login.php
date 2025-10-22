<?php
session_start();
include("connections.php");

// Debug information
echo "<h2>🔍 Login Debug Information</h2>";
echo "<hr>";

// Check database connection
if (mysqli_connect_errno()) {
    echo "❌ <strong>Database Connection Failed:</strong> " . mysqli_connect_error() . "<br>";
} else {
    echo "✅ <strong>Database Connected Successfully</strong><br>";
}

// Check if users exist
$users_query = mysqli_query($connections, "SELECT email, password, account_type FROM tbl_user");
if ($users_query) {
    $user_count = mysqli_num_rows($users_query);
    echo "✅ <strong>Found {$user_count} users in database</strong><br>";
    
    echo "<h3>📋 Available Test Accounts:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th style='padding: 8px;'>Email</th><th style='padding: 8px;'>Password</th><th style='padding: 8px;'>Type</th></tr>";
    
    while($user = mysqli_fetch_assoc($users_query)) {
        $type = ($user['account_type'] == '1') ? 'Admin' : 'User';
        echo "<tr>";
        echo "<td style='padding: 8px;'>{$user['email']}</td>";
        echo "<td style='padding: 8px;'>{$user['password']}</td>";
        echo "<td style='padding: 8px;'>{$type}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ <strong>Error querying users:</strong> " . mysqli_error($connections) . "<br>";
}

// Check if form was submitted
if(isset($_POST["btnLogin"])){
    echo "<h3>🔍 Form Submission Debug:</h3>";
    echo "📧 <strong>Email submitted:</strong> " . htmlspecialchars($_POST["email"]) . "<br>";
    echo "🔑 <strong>Password submitted:</strong> " . htmlspecialchars($_POST["password"]) . "<br>";
    
    $email = $_POST["email"];
    $password = $_POST["password"];
    
    // Check if user exists
    $check_email = mysqli_query($connections, "SELECT * FROM tbl_user WHERE email='$email'");
    $check_row = mysqli_num_rows($check_email);
    
    if($check_row > 0) {
        echo "✅ <strong>Email found in database</strong><br>";
        $fetch = mysqli_fetch_assoc($check_email);
        echo "🔑 <strong>Database password:</strong> " . $fetch["password"] . "<br>";
        echo "🔑 <strong>Submitted password:</strong> " . $password . "<br>";
        echo "👤 <strong>Account type:</strong> " . $fetch["account_type"] . "<br>";
        
        if($fetch["password"] == $password) {
            echo "✅ <strong>Password matches!</strong><br>";
            
            // Set session and redirect
            $_SESSION["email"] = $email;
            
            if($fetch["account_type"] == "1") {
                echo "🔄 <strong>Redirecting to Admin panel...</strong><br>";
                echo "<script>setTimeout(function(){ window.location.href='Admin/index.php'; }, 2000);</script>";
            } else {
                echo "🔄 <strong>Redirecting to User panel...</strong><br>";
                echo "<script>setTimeout(function(){ window.location.href='User/index.php'; }, 2000);</script>";
            }
        } else {
            echo "❌ <strong>Password does not match!</strong><br>";
        }
    } else {
        echo "❌ <strong>Email not found in database</strong><br>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .login-form { background: #f9f9f9; padding: 20px; border-radius: 5px; max-width: 400px; margin: 20px 0; }
        input[type="email"], input[type="password"] { width: 100%; padding: 8px; margin: 5px 0; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #005a8b; }
    </style>
</head>
<body>

<div class="login-form">
    <h3>🚀 Test Login Form</h3>
    <form method="POST">
        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>
        
        <label>Password:</label><br>
        <input type="password" name="password" required><br><br>
        
        <button type="submit" name="btnLogin">Test Login</button>
    </form>
    
    <p><a href="login.php">← Back to Regular Login</a></p>
</div>

</body>
</html>