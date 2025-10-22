<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');
include("nav.php");
include("check_subscription.php");
include("access_control.php");
include("../connections.php");

if (!isset($_SESSION["email"]) || empty($_SESSION["email"])) {
    error_log("planner.php: No user logged in, redirecting to login.php");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$email = mysqli_real_escape_string($connections, $_SESSION["email"]);
$query_info = mysqli_query($connections, "SELECT id_user, first_name, subscription_approved, subscription_plan FROM tbl_user WHERE email='$email'");
if (!$query_info) {
    error_log("planner.php: Database error: " . mysqli_error($connections));
    echo "<script>alert('Database error: " . mysqli_error($connections) . "'); window.location.href='login.php';</script>";
    exit;
}
$my_info = mysqli_fetch_assoc($query_info);
if (!$my_info) {
    error_log("planner.php: User not found for email: $email");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}
$user_id = $my_info["id_user"];
$full_name = $my_info["first_name"];
$subscription_approved = $my_info["subscription_approved"];
$subscription_plan = isset($_SESSION['effective_plan']) ? $_SESSION['effective_plan'] : $my_info["subscription_plan"];

// Restrict access based on feature and plan
$required_plan = ['B', 'C'];
if (!$subscription_approved && !isset($_SESSION['effective_plan'])) {
    error_log("planner.php: Access denied for $email, trial expired or no subscription");
    echo "<script>alert('Access denied. Your trial has expired. Please subscribe.'); window.location.href='Subscribe.php';</script>";
    exit;
} elseif ($subscription_approved && !in_array($subscription_plan, $required_plan)) {
    error_log("planner.php: Access denied for $email, plan $subscription_plan not sufficient");
    echo "<script>alert('Access denied. Please upgrade to Plan " . implode(' or ', $required_plan) . ".'); window.location.href='MyAccount.php';</script>";
    exit;
}

// Handle adding delivery
if (isset($_POST["btnAddDelivery"])) {
    error_log("POST data: " . print_r($_POST, true));

    $supplier_name = isset($_POST["supplier_name"]) ? mysqli_real_escape_string($connections, trim($_POST["supplier_name"])) : '';
    $contact_info = isset($_POST["contact_info"]) ? mysqli_real_escape_string($connections, trim($_POST["contact_info"])) : '';
    $description = isset($_POST["description"]) ? mysqli_real_escape_string($connections, trim($_POST["description"])) : '';
    $expected_date = isset($_POST["expected_date"]) ? mysqli_real_escape_string($connections, $_POST["expected_date"]) : '';

    if (!empty($supplier_name) && !empty($expected_date)) {
        $stmt_supplier = $connections->prepare("SELECT id_supplier FROM tbl_supplier WHERE id_user=? AND name=?");
        if ($stmt_supplier === false) {
            error_log("Prepare failed for supplier select: " . $connections->error);
            echo "<script>alert('Error preparing supplier query: " . addslashes($connections->error) . "');</script>";
        } else {
            $stmt_supplier->bind_param("is", $user_id, $supplier_name);
            $stmt_supplier->execute();
            $result = $stmt_supplier->get_result();
            if ($result->num_rows > 0) {
                $supplier = $result->fetch_assoc();
                $supplier_id = $supplier['id_supplier'];
                if (!empty($contact_info)) {
                    $stmt_update = $connections->prepare("UPDATE tbl_supplier SET contact_info=? WHERE id_supplier=?");
                    if ($stmt_update === false) {
                        error_log("Prepare failed for supplier update: " . $connections->error);
                    } else {
                        $stmt_update->bind_param("si", $contact_info, $supplier_id);
                        $stmt_update->execute();
                        $stmt_update->close();
                    }
                }
            } else {
                $stmt_insert = $connections->prepare("INSERT INTO tbl_supplier (id_user, name, contact_info) VALUES (?, ?, ?)");
                if ($stmt_insert === false) {
                    error_log("Prepare failed for supplier insert: " . $connections->error);
                    echo "<script>alert('Error preparing supplier insert: " . addslashes($connections->error) . "');</script>";
                } else {
                    $stmt_insert->bind_param("iss", $user_id, $supplier_name, $contact_info);
                    if ($stmt_insert->execute()) {
                        $supplier_id = $connections->insert_id;
                    } else {
                        echo "<script>alert('Error adding supplier: " . addslashes($stmt_insert->error) . "');</script>";
                        $supplier_id = null;
                    }
                    $stmt_insert->close();
                }
            }
            $stmt_supplier->close();

            if ($supplier_id) {
                $stmt_delivery = $connections->prepare("INSERT INTO tbl_delivery (id_supplier, id_user, expected_date, description, status) VALUES (?, ?, ?, ?, 'pending')");
                if ($stmt_delivery === false) {
                    error_log("Prepare failed for delivery insert: " . $connections->error);
                    echo "<script>alert('Error preparing delivery query: " . addslashes($connections->error) . "');</script>";
                } else {
                    $stmt_delivery->bind_param("iiss", $supplier_id, $user_id, $expected_date, $description);
                    if ($stmt_delivery->execute()) {
                        echo "<script>alert('Delivery added! Check stock after delivery.'); window.location.href='planner.php';</script>";
                    } else {
                        echo "<script>alert('Error adding delivery: " . addslashes($stmt_delivery->error) . "');</script>";
                    }
                    $stmt_delivery->close();
                }
            }
        }
    } else {
        echo "<script>alert('Please enter a supplier name and select a date!');</script>";
    }
}

// Handle claim delivery
if (isset($_POST["btnClaimDelivery"])) {
    $delivery_id = mysqli_real_escape_string($connections, $_POST["delivery_id"]);
    $stmt_claim = $connections->prepare("UPDATE tbl_delivery SET status='delivered' WHERE id_delivery=? AND id_user=? AND status='pending'");
    if ($stmt_claim === false) {
        error_log("Prepare failed for claim update: " . $connections->error);
    } else {
        $stmt_claim->bind_param("ii", $delivery_id, $user_id);
        if ($stmt_claim->execute()) {
            echo "<script>alert('Delivery marked as delivered!'); window.location.href='planner.php?month=$month&year=$year';</script>";
        } else {
            echo "<script>alert('Error updating delivery: " . addslashes($stmt_claim->error) . "');</script>";
        }
        $stmt_claim->close();
    }
}

// Handle cancel delivery
if (isset($_POST["btnCancelDelivery"])) {
    $delivery_id = mysqli_real_escape_string($connections, $_POST["delivery_id"]);
    $stmt_cancel = $connections->prepare("UPDATE tbl_delivery SET status='canceled' WHERE id_delivery=? AND id_user=? AND status='pending'");
    if ($stmt_cancel === false) {
        error_log("Prepare failed for cancel update: " . $connections->error);
    } else {
        $stmt_cancel->bind_param("ii", $delivery_id, $user_id);
        if ($stmt_cancel->execute()) {
            echo "<script>alert('Delivery canceled!'); window.location.href='planner.php?month=$month&year=$year';</script>";
        } else {
            echo "<script>alert('Error canceling delivery: " . addslashes($stmt_cancel->error) . "');</script>";
        }
        $stmt_cancel->close();
    }
}

// Fetch expiration dates
$expirations = [];
$exp_query = mysqli_query($connections, "SELECT name_item, expiration_date_item FROM tbl_item WHERE id_user='$user_id' AND expiration_date_item >= CURDATE()");
if (!$exp_query) {
    error_log("planner.php: Expiration query failed: " . mysqli_error($connections));
} else {
    while ($exp = mysqli_fetch_assoc($exp_query)) {
        $date = $exp["expiration_date_item"];
        if (!isset($expirations[$date])) {
            $expirations[$date] = [];
        }
        $expirations[$date][] = $exp["name_item"];
    }
    error_log("planner.php: Raw Expirations data: " . print_r($expirations, true)); // Debug raw data
}
$json_expirations = json_encode($expirations);
error_log("planner.php: JSON Expirations: " . $json_expirations); // Debug JSON output

// Fetch delivery dates with supplier names, contact info, and description
$deliveries = [];
$del_query = mysqli_query($connections, "SELECT d.id_delivery, d.expected_date, d.description, d.status, s.name AS supplier_name, s.contact_info FROM tbl_delivery d JOIN tbl_supplier s ON d.id_supplier = s.id_supplier WHERE d.id_user='$user_id'");
if (!$del_query) {
    error_log("planner.php: Delivery query failed: " . mysqli_error($connections));
} else {
    while ($del = mysqli_fetch_assoc($del_query)) {
        $date = $del["expected_date"];
        if (!isset($deliveries[$date])) {
            $deliveries[$date] = [];
        }
        $deliveries[$date][] = [
            'id_delivery' => $del["id_delivery"],
            'status' => $del["status"],
            'supplier_name' => $del["supplier_name"],
            'contact_info' => $del["contact_info"],
            'description' => $del["description"]
        ];
    }
    error_log("planner.php: Raw Deliveries data: " . print_r($deliveries, true)); // Debug raw data
}
$json_deliveries = json_encode($deliveries);
error_log("planner.php: JSON Deliveries: " . $json_deliveries); // Debug JSON output

// Handle month navigation
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
if ($month < 1 || $month > 12) $month = intval(date('m'));
if ($year < 1970 || $year > 9999) $year = intval(date('Y'));
$prev_month = $month == 1 ? 12 : $month - 1;
$prev_year = $month == 1 ? $year - 1 : $year;
$next_month = $month == 12 ? 1 : $month + 1;
$next_year = $month == 12 ? $year + 1 : $year;
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_day = mktime(0, 0, 0, $month, 1, $year);
$day_of_week = date('w', $first_day);
$current_date = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planner - SME Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="calendar.css">
    <link rel="stylesheet" href="user-bar.css">
</head>
<body class="calendar-page">
    <main class="main-content">
        <!-- Page Header -->


        <div class="container">
            <div class="section">
                <div class="calendar-nav">
                    <button onclick="navigateMonth('prev')" class="nav-btn">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <div class="month-year-controls">
                        <select id="monthSelect" onchange="jumpToMonth()">
                            <?php
                            $months = [
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                            ];
                            foreach ($months as $m => $name) {
                                $selected = $m == $month ? 'selected' : '';
                                echo "<option value='$m' $selected>$name</option>";
                            }
                            ?>
                        </select>
                        <select id="yearSelect" onchange="jumpToMonth()">
                            <?php
                            $start_year = date('Y') - 5;
                            $end_year = date('Y') + 5;
                            for ($y = $start_year; $y <= $end_year; $y++) {
                                $selected = $y == $year ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button onclick="navigateMonth('next')" class="nav-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <h3><i class="fas fa-calendar"></i> Calendar - <?php echo $months[$month] . ' ' . $year; ?></h3>
                <table class="calendar">
                    <tr>
                        <th>Sun</th>
                        <th>Mon</th>
                        <th>Tue</th>
                        <th>Wed</th>
                        <th>Thu</th>
                        <th>Fri</th>
                        <th>Sat</th>
                    </tr>
                    <tr>
                        <?php
                        for ($i = 0; $i < $day_of_week; $i++) {
                            echo "<td></td>";
                        }
                        $day = 1;
                        while ($day <= $days_in_month) {
                            $current_date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                            $class = date('Y-m-d') === $current_date ? 'today' : '';
                            echo "<td class='$class' onclick='showDetails(\"$current_date\")'>";
                            echo "<div class='day-number'>$day</div>";
                            if (isset($expirations[$current_date])) {
                                echo "<div class='event'><strong>Expirations:</strong><br>";
                                foreach ($expirations[$current_date] as $item) {
                                    echo "• " . htmlspecialchars($item) . "<br>";
                                }
                                echo "</div>";
                            }
                            if (isset($deliveries[$current_date])) {
                                foreach ($deliveries[$current_date] as $delivery) {
                                    $status_class = $delivery['status'];
                                    echo "<div class='delivery $status_class'>";
                                    echo "<strong>" . htmlspecialchars($delivery['supplier_name']) . "</strong>";
                                    if ($delivery['contact_info']) {
                                        echo "<br><small>" . htmlspecialchars($delivery['contact_info']) . "</small>";
                                    }
                                    if ($delivery['description']) {
                                        echo "<br><small>" . htmlspecialchars($delivery['description']) . "</small>";
                                    }
                                    if ($delivery['status'] === 'pending') {
                                        echo "<br>";
                                        echo "<form method='POST' action='planner.php?month=$month&year=$year' style='display:inline;'>
                                                <input type='hidden' name='delivery_id' value='{$delivery['id_delivery']}'>
                                                <input type='submit' name='btnClaimDelivery' value='Mark as Delivered' class='btn-claim'>
                                              </form>";
                                        echo "<form method='POST' action='planner.php?month=$month&year=$year' style='display:inline; margin-left:5px;'>
                                                <input type='hidden' name='delivery_id' value='{$delivery['id_delivery']}'>
                                                <input type='submit' name='btnCancelDelivery' value='Cancel Delivery' class='btn-cancel'>
                                              </form>";
                                    }
                                    echo "</div>";
                                }
                            }
                            echo "</td>";
                            $day++;
                            if (($day + $day_of_week - 1) % 7 == 0) {
                                echo "</tr><tr>";
                            }
                        }
                        while (($day + $day_of_week - 1) % 7 != 0) {
                            echo "<td></td>";
                            $day++;
                        }
                        ?>
                    </tr>
                </table>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-truck"></i> Add New Delivery</h3>
                <form method="POST">
                    <table>
                        <tr>
                            <td>
                                <label for="supplier_name"><i class="fas fa-building"></i> Supplier Name:</label>
                                <input type="text" id="supplier_name" name="supplier_name" placeholder="Enter supplier name" value="<?php echo isset($_POST['supplier_name']) ? htmlspecialchars($_POST['supplier_name']) : ''; ?>">
                                <span class="error"><?php echo !empty($supplier_name) || !isset($_POST['btnAddDelivery']) ? '' : 'Supplier name required!'; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="contact_info"><i class="fas fa-phone"></i> Contact Info (optional):</label>
                                <input type="text" id="contact_info" name="contact_info" placeholder="Phone number or email" value="<?php echo isset($_POST['contact_info']) ? htmlspecialchars($_POST['contact_info']) : ''; ?>">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="description"><i class="fas fa-clipboard"></i> Description (optional):</label>
                                <textarea id="description" name="description" placeholder="Enter delivery details, items, notes..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="expected_date"><i class="fas fa-calendar-day"></i> Expected Delivery Date:</label>
                                <input type="date" id="expected_date" name="expected_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo isset($_POST['expected_date']) ? htmlspecialchars($_POST['expected_date']) : ''; ?>">
                                <span class="error"><?php echo !empty($expected_date) || !isset($_POST['btnAddDelivery']) ? '' : 'Date required!'; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <hr>
                                <input type="submit" name="btnAddDelivery" value="Add Delivery" class="btn">
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
        </div>
    </main>

    <div id="detailsModal" role="dialog" aria-hidden="true">
        <div class="modal-content">
            <span class="close" onclick="closeDetailsModal()">&times;</span>
            <div id="detailsContent"></div>
        </div>
    </div>

    <script>
        const EXPIRATIONS = <?php echo $json_expirations; ?>;
        const DELIVERIES = <?php echo $json_deliveries; ?>;
        console.log('EXPIRATIONS:', EXPIRATIONS); // Debug log with correct variable
        console.log('DELIVERIES:', DELIVERIES);   // Debug log with correct variable
        const currentMonth = <?php echo $month; ?>;
        const currentYear = <?php echo $year; ?>;

        function showDetails(date) {
            const expirations = EXPIRATIONS || {}; // Use const variable directly
            const deliveries = DELIVERIES || {};   // Use const variable directly
            const modal = document.getElementById('detailsModal');
            const modalContent = modal.querySelector('.modal-content');
            const content = document.getElementById('detailsContent');

            console.log('Clicked date:', date); // Debug clicked date
            console.log('Expirations for date:', expirations[date]); // Debug specific date data
            console.log('Deliveries for date:', deliveries[date]);   // Debug specific date data

            let formattedDate = date; // Ensure exact match with PHP date format

            let html = `<h4 class="modal-date">${escapeHtml(formattedDate)}</h4>`;

            if (expirations && expirations[date] && expirations[date].length) {
                html += `<div class="modal-section"><strong>Expirations:</strong><ul>`;
                expirations[date].forEach(item => {
                    html += `<li>${escapeHtml(item)}</li>`;
                });
                html += `</ul></div>`;
            } else {
                html += `<div class="modal-section"><strong>Expirations:</strong><p><em>None</em></p></div>`;
            }

            if (deliveries && deliveries[date] && deliveries[date].length) {
                html += `<div class="modal-section"><strong>Deliveries:</strong>`;
                deliveries[date].forEach(d => {
                    html += `<div class="delivery-row ${d.status}"><strong>${escapeHtml(d.supplier_name)}</strong> <span class="status">(${escapeHtml(d.status)})</span>`;
                    if (d.contact_info) html += `<div class="small">Contact: ${escapeHtml(d.contact_info)}</div>`;
                    if (d.description) html += `<div class="small">Description: ${escapeHtml(d.description)}</div>`;
                    if (d.status === 'pending') {
                        html += `<div class="actions">
                                    <form method="POST" action="planner.php?month=${currentMonth}&year=${currentYear}" style="display:inline;">
                                        <input type="hidden" name="delivery_id" value="${d.id_delivery}">
                                        <input type="submit" name="btnClaimDelivery" value="Mark as Delivered" class="btn-claim">
                                    </form>
                                    <form method="POST" action="planner.php?month=${currentMonth}&year=${currentYear}" style="display:inline; margin-left:5px;">
                                        <input type="hidden" name="delivery_id" value="${d.id_delivery}">
                                        <input type="submit" name="btnCancelDelivery" value="Cancel Delivery" class="btn-cancel">
                                    </form>
                                  </div>`;
                    }
                    html += `</div>`;
                });
                html += `</div>`;
            } else {
                html += `<div class="modal-section"><strong>Deliveries:</strong><p><em>None</em></p></div>`;
            }

            content.innerHTML = html;
            modal.style.display = 'block';
        }

        function escapeHtml(text) {
            if (!text && text !== 0) return '';
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function closeDetailsModal() {
            const modal = document.getElementById('detailsModal');
            if (modal) modal.style.display = 'none';
        }

        window.addEventListener('click', function(e) {
            const modal = document.getElementById('detailsModal');
            if (modal && e.target === modal) closeDetailsModal();
        });

        function navigateMonth(action) {
            let currentMonth = <?php echo $month; ?>;
            let currentYear = <?php echo $year; ?>;
            if (action === 'prev') {
                currentMonth = currentMonth === 1 ? 12 : currentMonth - 1;
                currentYear = currentMonth === 12 ? currentYear - 1 : currentYear;
            } else if (action === 'next') {
                currentMonth = currentMonth === 12 ? 1 : currentMonth + 1;
                currentYear = currentMonth === 1 ? currentYear + 1 : currentYear;
            }
            window.location.href = `planner.php?month=${currentMonth}&year=${currentYear}`;
        }

        function jumpToMonth() {
            const month = document.getElementById('monthSelect').value;
            const year = document.getElementById('yearSelect').value;
            window.location.href = `planner.php?month=${month}&year=${year}`;
        }
    </script>
</body>
</html>