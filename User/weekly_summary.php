<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');

include("../connections.php");

require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Log start of script
$notify = "Starting manual weekly summary send at " . date('F d, Y H:i:s') . " Manila time";
error_log("send_weekly_summary.php: $notify");

// Fetch all registered users
$users_query = mysqli_query($connections, "SELECT id_user, email, first_name FROM tbl_user");
if (!$users_query) {
    $notify = "Database error fetching users: " . mysqli_error($connections);
    error_log("send_weekly_summary.php: $notify");
    echo "<script>alert('$notify'); window.location.href='index.php';</script>";
    exit;
}

$email_count = 0;
$failed_emails = 0;

while ($user = mysqli_fetch_assoc($users_query)) {
    $user_id = $user['id_user'];
    $email = $user['email'];
    $first_name = $user['first_name'] ?: 'User';

    // Fetch expiring items for this user (next 14 days) using prepared statement
    $exp_query = mysqli_prepare($connections, "
        SELECT name_item, expiration_date_item
        FROM tbl_item
        WHERE id_user = ?
        AND expiration_date_item BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
        ORDER BY expiration_date_item ASC
    ");
    if (!$exp_query) {
        $notify = "Database error preparing expiring items query for $email: " . mysqli_error($connections);
        error_log("send_weekly_summary.php: $notify");
        continue;
    }
    mysqli_stmt_bind_param($exp_query, 'i', $user_id);
    mysqli_stmt_execute($exp_query);
    $result = mysqli_stmt_get_result($exp_query);

    $expiring_items = [];
    while ($exp = mysqli_fetch_assoc($result)) {
        $expiring_items[] = $exp['name_item'] . ' - ' . date('F d, Y', strtotime($exp['expiration_date_item']));
    }
    $item_count = count($expiring_items);
    mysqli_stmt_close($exp_query);

    // Fetch sales for this week (Monday to Sunday) using prepared statement
    $sales_query = mysqli_prepare($connections, "
        SELECT i.name_item, p.quantity, p.total_cost
        FROM tbl_purchase p
        JOIN tbl_item i ON p.id_item = i.id_item
        WHERE p.id_user = ?
        AND p.status = 'paid'
        AND YEARWEEK(p.date_time, 1) = YEARWEEK(CURDATE(), 1)
        ORDER BY p.date_time ASC
    ");
    if (!$sales_query) {
        $notify = "Database error preparing sales query for $email: " . mysqli_error($connections);
        error_log("send_weekly_summary.php: $notify");
        continue;
    }
    mysqli_stmt_bind_param($sales_query, 'i', $user_id);
    mysqli_stmt_execute($sales_query);
    $sales_result = mysqli_stmt_get_result($sales_query);

    $sales_items = [];
    $total_sales = 0.0;
    while ($sale = mysqli_fetch_assoc($sales_result)) {
        $sales_items[] = [
            'name' => $sale['name_item'],
            'quantity' => $sale['quantity'],
            'total_cost' => $sale['total_cost']
        ];
        $total_sales += $sale['total_cost'];
    }
    $sales_count = count($sales_items);
    mysqli_stmt_close($sales_query);

    // Prepare email body
    $body = "Dear $first_name,<br><br>";
    $body .= "This is your weekly summary for " . date('F d, Y H:i:s') . " Manila time:<br><br>";

    // Expiring Items Section
    $body .= "<strong>Items Expiring in the Next 14 Days:</strong><br>";
    if ($item_count > 0) {
        $body .= "<ul>";
        foreach ($expiring_items as $item) {
            $body .= "<li>$item</li>";
        }
        $body .= "</ul>";
        $body .= "Total: $item_count items<br><br>";
    } else {
        $body .= "No items expiring within the next 14 days.<br><br>";
    }

    // Sales This Week Section
    $body .= "<strong>Sales This Week (Monday to Sunday):</strong><br>";
    if ($sales_count > 0) {
        $body .= "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        $body .= "<tr><th>Item Name</th><th>Quantity Sold</th><th>Total Revenue</th></tr>";
        foreach ($sales_items as $sale) {
            $body .= "<tr>";
            $body .= "<td>" . htmlspecialchars($sale['name']) . "</td>";
            $body .= "<td>" . $sale['quantity'] . "</td>";
            $body .= "<td>₱" . number_format($sale['total_cost'], 2) . "</td>";
            $body .= "</tr>";
        }
        $body .= "<tr><td><strong>Total</strong></td><td><strong>$sales_count items</strong></td><td><strong>₱" . number_format($total_sales, 2) . "</strong></td></tr>";
        $body .= "</table><br>";
    } else {
        $body .= "No sales recorded this week.<br><br>";
    }

    $body .= "Please log in at <a href='http://localhost/SME/'>SME System</a> to manage your inventory.<br>";
    $body .= "For assistance, contact <a href='mailto:support@smesystem.com'>support@smesystem.com</a>.<br><br>";
    $body .= "Best regards,<br>SME System Team";

    // Send email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'pepitacurtiz@gmail.com';
        $mail->Password = 'dpyz pbgg jlau lkom';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->setFrom('pepitacurtiz@gmail.com', 'SME System Team');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Weekly Stock and Sales Summary - ' . date('F d, Y H:i:s');
        $mail->Body = $body;
        $mail->send();
        $email_count++;
        $notify = "Email sent successfully to $email at " . date('F d, Y H:i:s') . " Manila time";
        error_log("send_weekly_summary.php: $notify");
    } catch (Exception $e) {
        $failed_emails++;
        $notify = "Email failed for $email: " . $mail->ErrorInfo . " at " . date('F d, Y H:i:s') . " Manila time";
        error_log("send_weekly_summary.php: $notify");
    }
}

$notify = "Manual weekly summary completed. Emails sent: $email_count, Failed: $failed_emails at " . date('F d, Y H:i:s') . " Manila time";
error_log("send_weekly_summary.php: $notify");
echo "<script>alert('$notify'); window.location.href='index.php';</script>";
?>