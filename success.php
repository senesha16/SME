<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');
include("connections.php");

if (isset($_SESSION["email"]) && !empty($_SESSION["email"])) {
    echo "<script>window.location.href='user/dashboard.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Success - SME Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .success-container {
            background: white;
            border-radius: 15px;
            padding: 50px 40px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #E62727 0%, #c62d2d 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: white;
            font-size: 36px;
            box-shadow: 0 10px 30px rgba(230, 39, 39, 0.3);
        }
        .success-container h3 {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        .success-container p {
            color: #7f8c8d;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .btn-delete {
            background: #E62727;
            color: white;
            text-decoration: none;
            padding: 14px 30px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(230, 39, 39, 0.3);
        }
        .btn-delete:hover {
            background: #c62d2d;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(230, 39, 39, 0.4);
        }
        @media (max-width: 480px) {
            .success-container {
                margin: 15px;
                padding: 40px 25px;
            }
            .success-container h3 {
                font-size: 20px;
            }
            .btn-delete {
                padding: 12px 25px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-clock"></i>
        </div>
        <h3>Your Registration is Pending Admin Approval</h3>
        <p>Our team will review your business details within <strong>24 hours</strong>. You'll receive an email once approved!</p>
        <a href="index.php" class="btn-delete">
            <i class="fas fa-arrow-left"></i>
            Back to Login
        </a>
    </div>
</body>
</html>