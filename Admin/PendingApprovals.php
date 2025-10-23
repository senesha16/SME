<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["email"])) {
    echo "<script>window.location.href='../';</script>";
}

include("../connections.php");

// use the user's nav/sidebar so layout matches other admin pages
include("../User/nav.php");
// load user dashboard styles (for navbar/sidebar) and the shared admin-new stylesheet
echo '<link rel="stylesheet" href="../User/user-dashboard.css">';
echo '<link rel="stylesheet" href="admin-pendings.css">';                 // shared pending/subscription styles
echo '<link rel="stylesheet" href="admin-backup.css">';                  // page-scoped full-bleed overrides (pending approvals only)
// Font Awesome (icons)
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';

// replace sidebar with admin menu entries
echo '<script>
document.addEventListener("DOMContentLoaded", function(){
    var sidebar = document.querySelector(".sidebar-nav");
    if (!sidebar) return;
    sidebar.innerHTML = `
        <a href="index.php" class="nav-item">
            <i class="fas fa-table"></i>
            <span>View Records</span>
        </a>
        <a href="PendingApprovals.php" class="nav-item">
            <i class="fas fa-briefcase"></i>
            <span>Pending Business</span>
        </a>
        <a href="PendingSubscriptions.php" class="nav-item">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Pending Subscription</span>
        </a>
		<a href="retriever.php" class="nav-item">
            <i class="fas fa-users"></i>
            <span>View Users</span>
        </a>
        <a href="analytics.php" class="nav-item">
            <i class="fas fa-chart-line"></i>
            <span>Analytics</span>
        </a>
    `;
    // Normalize current path (lowercase, strip query/hash)
    var current = window.location.pathname.split("/").pop().split(/[?#]/)[0].toLowerCase();
    sidebar.querySelectorAll(".nav-item").forEach(function(a){
        var href = (a.getAttribute("href") || "").split(/[?#]/)[0].toLowerCase();
        if (href === current || (href === "index.php" && (current === "" || current === "index.php"))) {
            a.classList.add("active");
        } else {
            a.classList.remove("active");
        }
    });

    // mark page to use index-like theme and measure sidebar width to avoid overlap
    document.documentElement.classList.add("index-theme");
    // mark as pending-approvals so admin-backup.css applies only here
    document.documentElement.classList.add("pending-approvals");

    function updateSidebarWidth() {
        try {
            var rect = sidebar.getBoundingClientRect();
            var width = Math.round(rect.width) || 0;
            if (window.getComputedStyle(sidebar).display === "none" || width < 40) width = 0;
            document.documentElement.style.setProperty("--sidebar-width", width + "px");
        } catch (e) {
            console.warn("Could not compute sidebar width", e);
        }
    }
    updateSidebarWidth();
    var resizeTimer;
    window.addEventListener("resize", function(){
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(updateSidebarWidth, 100);
    });
    var mo = new MutationObserver(function(){ updateSidebarWidth(); });
    mo.observe(sidebar, { attributes: true, childList: true, subtree: false });
});
</script>';

require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// compute project root and base URL dynamically so the code works on Hostinger or local
$PROJECT_ROOT = realpath(__DIR__ . '/../'); // points to .../SME
$SITE_ROOT_PATH = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/') . '/'; // e.g. /SME/
$SITE_BASE_URL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $SITE_ROOT_PATH;

$email = $_SESSION["email"];
$query_account_type = mysqli_query($connections, "SELECT account_type FROM tbl_user WHERE email='$email'");
$user_row = mysqli_fetch_assoc($query_account_type);
if ($user_row['account_type'] != '1') {
    echo "<script>window.location.href='../';</script>";
}

if (isset($_GET['approve']) && isset($_GET['id_pending'])) {
    $id_pending = mysqli_real_escape_string($connections, $_GET['id_pending']);
    $pending_query = mysqli_query($connections, "SELECT * FROM tbl_pending_users WHERE id_pending='$id_pending'");
    if ($pending_row = mysqli_fetch_assoc($pending_query)) {
        $first_name = mysqli_real_escape_string($connections, $pending_row['first_name']);
        $middle_name = mysqli_real_escape_string($connections, $pending_row['middle_name']);
        $last_name = mysqli_real_escape_string($connections, $pending_row['last_name']);
        $birthday = $pending_row['birthday'];
        $birth_place = mysqli_real_escape_string($connections, $pending_row['birth_place']);
        $city = mysqli_real_escape_string($connections, $pending_row['city']);
        $barangay = mysqli_real_escape_string($connections, $pending_row['barangay']);
        $lot_street = mysqli_real_escape_string($connections, $pending_row['lot_street']);
        $prefix = $pending_row['prefix'];
        $seven_digit = $pending_row['seven_digit'];
        $email = mysqli_real_escape_string($connections, $pending_row['email']);
        $password = mysqli_real_escape_string($connections, $pending_row['password']);
        $img = $pending_row['img'];
        $establishment_name = mysqli_real_escape_string($connections, $pending_row['establishment_name']);
        $capital = $pending_row['capital'];
        $date_of_establishment = $pending_row['date_of_establishment'];
        $business_type = mysqli_real_escape_string($connections, $pending_row['business_type']);
        $nature_of_business = mysqli_real_escape_string($connections, $pending_row['nature_of_business']);
        $sabang_location = mysqli_real_escape_string($connections, $pending_row['sabang_location']);
        $lot_street_business = mysqli_real_escape_string($connections, $pending_row['lot_street_business']);
        $temp_DTI = str_replace("\\", "/", $pending_row['DTI']);
        $temp_business_permit = str_replace("\\", "/", $pending_row['business_permit']);
        $enterprise_type = $pending_row['enterprise_type'];

        // Move DTI file
        $DTI = '';
	$dti_full_path = $PROJECT_ROOT . '/' . ltrim(str_replace('\\','/',$temp_DTI), '/');
        if (!empty($temp_DTI) && file_exists($dti_full_path)) {
            $DTI_target_file = "dti/" . basename($temp_DTI);
			if (!is_dir($PROJECT_ROOT . '/dti/')) {
				mkdir($PROJECT_ROOT . '/dti/', 0755, true);
			}
			$dti_target_full_path = $PROJECT_ROOT . '/' . $DTI_target_file;
			if (file_exists($dti_target_full_path)) {
				$DTI_target_file = "dti/" . rand(1000,9999) . "_" . basename($temp_DTI);
				$dti_target_full_path = $PROJECT_ROOT . '/' . $DTI_target_file;
			}
            if (rename($dti_full_path, $dti_target_full_path)) {
                $DTI = $DTI_target_file;
            }
        }

        // Move business permit file
        $business_permit = '';
	$permit_full_path = $PROJECT_ROOT . '/' . ltrim(str_replace('\\','/',$temp_business_permit), '/');
        if (!empty($temp_business_permit) && file_exists($permit_full_path)) {
            $business_permit_target_file = "business_permit/" . basename($temp_business_permit);
			if (!is_dir($PROJECT_ROOT . '/business_permit/')) {
				mkdir($PROJECT_ROOT . '/business_permit/', 0755, true);
			}
			$permit_target_full_path = $PROJECT_ROOT . '/' . $business_permit_target_file;
			if (file_exists($permit_target_full_path)) {
				$business_permit_target_file = "business_permit/" . rand(1000,9999) . "_" . basename($temp_business_permit);
				$permit_target_full_path = $PROJECT_ROOT . '/' . $business_permit_target_file;
			}
            if (rename($permit_full_path, $permit_target_full_path)) {
                $business_permit = $business_permit_target_file;
            }
        }

        // Insert into tbl_user only if DTI and business permit are valid
		if (!empty($DTI) && !empty($business_permit)) {
            $user_query = "INSERT INTO tbl_user (first_name, middle_name, last_name, birthday, birth_place, city, barangay, lot_street, prefix, seven_digit, email, password, attempt, log_time, account_type, img, trial_start_date)
                           VALUES ('$first_name', '$middle_name', '$last_name', '$birthday', '$birth_place', '$city', '$barangay', '$lot_street', '$prefix', '$seven_digit', '$email', '$password', '', '', '2', '$img', NULL)";
            if (mysqli_query($connections, $user_query)) {
                $new_user_id = mysqli_insert_id($connections);

                // Insert into tbl_business
				$business_query = "INSERT INTO tbl_business (id_user, establishment_name, capital, date_of_establishment, business_type, nature_of_business, sabang_location, lot_street_business, DTI, business_permit, enterprise_type)
								  VALUES ('$new_user_id', '$establishment_name', '$capital', '$date_of_establishment', '$business_type', '$nature_of_business', '$sabang_location', '$lot_street_business', '$DTI', '$business_permit', '$enterprise_type')";
				$business_res = mysqli_query($connections, $business_query);
				if (!$business_res) {
					// rollback user insert to avoid orphaned user
					$dbErr = mysqli_error($connections);
					mysqli_query($connections, "DELETE FROM tbl_user WHERE id_user='$new_user_id'");
					echo "<script>window.location.href='PendingApprovals.php?notify=User%20approval%20failed:%20Business%20insert%20error';</script>";
					exit;
				}

                // Send approval email
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
                    $mail->Subject = 'Registration Approval';
					// use the detected base URL so the link is not localhost
					$mail->Body = "Dear $first_name $last_name,<br><br>Your registration has been approved. Your account password is: <font color='red'><b>$password</b></font><br><br>Please use this password to log in to your account at <a href='{$SITE_BASE_URL}'>SME System</a>.<br><br>Best regards,<br>SME System Team";
					$emailSent = false;
					$mailError = '';
					try {
						$emailSent = $mail->send();
					} catch (Exception $e) {
						$mailError = $mail->ErrorInfo ?: $e->getMessage();
						$emailSent = false;
					}
					// remove pending record regardless of email success so approval completes
					mysqli_query($connections, "DELETE FROM tbl_pending_users WHERE id_pending='$id_pending'");
					if ($emailSent) {
						echo "<script>window.location.href='PendingApprovals.php?notify=User%20approved%20successfully%20and%20email%20sent';</script>";
					} else {
						$encoded = urlencode('User approved but email failed: ' . $mailError);
						echo "<script>window.location.href='PendingApprovals.php?notify={$encoded}';</script>";
					}
				} catch (Exception $e) {
					// catch from PHPMailer setup (shouldn't normally reach here because we handle send above)
					$mailError = $mail->ErrorInfo ?: $e->getMessage();
					mysqli_query($connections, "DELETE FROM tbl_pending_users WHERE id_pending='$id_pending'");
					$encoded = urlencode('User approved but email error: ' . $mailError);
					echo "<script>window.location.href='PendingApprovals.php?notify={$encoded}';</script>";
				}
            } else {
				$dberr = mysqli_error($connections);
				$enc = urlencode('User approval failed: Database error - ' . $dberr);
				echo "<script>window.location.href='PendingApprovals.php?notify={$enc}';</script>";
            }
        } else {
            echo "<script>window.location.href='PendingApprovals.php?notify=User%20approval%20failed:%20Missing%20DTI%20or%20business%20permit';</script>";
        }
    } else {
        echo "<script>window.location.href='PendingApprovals.php?notify=Invalid%20pending%20user%20ID';</script>";
    }
}

if (isset($_GET['reject']) && isset($_GET['id_pending'])) {
    $id_pending = mysqli_real_escape_string($connections, $_GET['id_pending']);
    $query = mysqli_query($connections, "SELECT DTI, business_permit, first_name, last_name, email FROM tbl_pending_users WHERE id_pending='$id_pending'");
    if ($row = mysqli_fetch_assoc($query)) {
        $first_name = $row['first_name'];
        $last_name = $row['last_name'];
        $email = $row['email'];
        $dti_full_path = $_SERVER['DOCUMENT_ROOT'] . '/SME/' . str_replace("\\", "/", $row['DTI']);
        $permit_full_path = $_SERVER['DOCUMENT_ROOT'] . '/SME/' . str_replace("\\", "/", $row['business_permit']);
        if (!empty($row['DTI']) && file_exists($dti_full_path)) {
            unlink($dti_full_path);
        }
        if (!empty($row['business_permit']) && file_exists($permit_full_path)) {
            unlink($permit_full_path);
        }

        // Send rejection email
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
            $mail->Subject = 'Registration Rejection';
            $mail->Body = "Dear $first_name $last_name,<br><br>We regret to inform you that your registration request has been rejected. Please contact our support team at <a href='mailto:support@smesystem.com'>support@smesystem.com</a> for more details.<br><br>Best regards,<br>SME System Team";
            $mail->send();
            mysqli_query($connections, "DELETE FROM tbl_pending_users WHERE id_pending='$id_pending'");
            echo "<script>window.location.href='PendingApprovals.php?notify=User%20rejected%20and%20email%20sent';</script>";
        } catch (Exception $e) {
            mysqli_query($connections, "DELETE FROM tbl_pending_users WHERE id_pending='$id_pending'");
            echo "<script>window.location.href='PendingApprovals.php?notify=User%20rejected%20but%20email%20failed:%20" . urlencode($mail->ErrorInfo) . "';</script>";
        }
    } else {
        echo "<script>window.location.href='PendingApprovals.php?notify=Invalid%20pending%20user%20ID';</script>";
    }
}

$notify = isset($_GET["notify"]) ? $_GET["notify"] : "";

// --- new metric queries (inserted before HTML output) ---
$pending_count_row = mysqli_fetch_assoc(mysqli_query($connections, "SELECT COUNT(*) AS cnt FROM tbl_pending_users WHERE account_type='pending'"));
$pending_count = intval($pending_count_row['cnt'] ?? 0);

$pending_subs_row = mysqli_fetch_assoc(mysqli_query($connections, "SELECT COUNT(*) AS cnt FROM tbl_user WHERE subscription_proof IS NOT NULL AND subscription_approved = 0"));
$pending_subs_count = intval($pending_subs_row['cnt'] ?? 0);

// --- helper functions for icon selection ---
function get_business_icon_class($business_type) {
	$bt = strtolower(trim((string)$business_type));
	if (strpos($bt, 'food') !== false) return 'fas fa-utensils';
	if (strpos($bt, 'store') !== false || strpos($bt, 'retail') !== false) return 'fas fa-store';
	if (strpos($bt, 'service') !== false) return 'fas fa-concierge-bell';
	if (strpos($bt, 'manufact') !== false) return 'fas fa-industry';
	return 'fas fa-briefcase';
}
function file_is_image($path) {
	$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
	return in_array($ext, ['jpg','jpeg','png','gif','bmp','webp','svg']);
}
function get_file_icon_class($path) {
	$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
	if ($ext === 'pdf') return 'fas fa-file-pdf';
	if (in_array($ext, ['doc','docx'])) return 'fas fa-file-word';
	if (in_array($ext, ['xls','xlsx','csv'])) return 'fas fa-file-excel';
	if (in_array($ext, ['ppt','pptx'])) return 'fas fa-file-powerpoint';
	if (in_array($ext, ['zip','rar','7z'])) return 'fas fa-file-archive';
	return 'fas fa-file';
}
?>

<!-- Replace the old table with a dashboard-like layout -->
<main class="main-content pending-container">
	<!-- Welcome / Summary Header -->
	<div class="welcome-card card">
		<div class="welcome-content">
			<h1><i class="fas fa-briefcase"></i> Pending Approvals</h1>
			<p class="welcome-text">Review submitted business registrations and their supporting documents.</p>
		</div>
		<div class="welcome-stats">
			<div class="stat-card">
				<div class="stat-icon"><i class="fas fa-user-clock"></i></div>
				<div class="stat-content">
					<h3><?php echo $pending_count; ?></h3>
					<p>Pending Registrations</p>
				</div>
			</div>
			<!-- removed Pending Subscriptions stat for approvals page -->
		</div>
	</div>

	<!-- Dashboard header: search, filter, view toggle (new, matches admin index style) -->
	<div class="dashboard-header card">
		<div class="dashboard-title">
			<h2 style="margin:0; font-size:1rem;"><i class="fas fa-clipboard-list"></i> Pending Approvals</h2>
			<div class="small text-muted" style="margin-top:6px;">Manage and review registration requests</div>
		</div>
		<div class="controls">
			<input id="pending-search" class="search-input" type="search" placeholder="Search name, establishment, address or type…" aria-label="Search pending requests">
			<select id="pending-filter" class="filter-select" aria-label="Filter by business type">
				<option value="">All Business Types</option>
				<option value="food">Food</option>
				<option value="store">Store / Retail</option>
				<option value="service">Service</option>
				<option value="manufact">Manufacturing</option>
			</select>

			<!-- view-toggle removed -->
		</div>
	</div>

	<!-- Pending Cards Grid -->
	<div class="card">
		<div class="section-title"><i class="fas fa-list"></i> Pending Requests</div>

		<div class="pending-grid">
			<?php
			// retrieve pending list (unchanged query)
			$retrieve_query = mysqli_query($connections, "SELECT id_pending, first_name, middle_name, last_name, prefix, seven_digit, img, establishment_name, sabang_location, lot_street_business, business_type, nature_of_business, DTI, business_permit 
													 FROM tbl_pending_users 
													 WHERE account_type='pending' ORDER BY id_pending DESC");
			if (!$retrieve_query || mysqli_num_rows($retrieve_query) == 0) {
				echo '<div class="empty-state card">No pending requests at the moment.</div>';
			} else {
				while ($row = mysqli_fetch_assoc($retrieve_query)) {
					$id_pending = intval($row["id_pending"]);
					$first = htmlspecialchars($row['first_name'] ?? '');
					$middle = htmlspecialchars($row['middle_name'] ?? '');
					$last = htmlspecialchars($row['last_name'] ?? '');
					$full_name = trim(ucfirst($first) . ($middle ? ' ' . strtoupper($middle[0]) . '. ' : ' ') . ucfirst($last));
					$establishment = htmlspecialchars($row['establishment_name'] ?: 'N/A');
					$location = htmlspecialchars(trim(($row['sabang_location'] ?? '') . ' ' . ($row['lot_street_business'] ?? '')));
					$business_type = htmlspecialchars($row['business_type'] ?? 'N/A');
					$nature = htmlspecialchars($row['nature_of_business'] ?? 'N/A');
					$contact = htmlspecialchars(($row['prefix'] ?? '') . ($row['seven_digit'] ?? ''));

					// image / document URLs
					$img_path = $row['img'] ? str_replace("\\","/",$row['img']) : '';
					$img_exists = $img_path && file_exists($_SERVER['DOCUMENT_ROOT'] . '/SME/' . $img_path);
					$img_url = $img_exists ? '/SME/' . $img_path : '';

					$dti_path = $row['DTI'] ? str_replace("\\","/",$row['DTI']) : '';
					$dti_exists = $dti_path && file_exists($_SERVER['DOCUMENT_ROOT'] . '/SME/' . $dti_path);
					$dti_url = $dti_exists ? '/SME/' . $dti_path : '';

					$permit_path = $row['business_permit'] ? str_replace("\\","/",$row['business_permit']) : '';
					$permit_exists = $permit_path && file_exists($_SERVER['DOCUMENT_ROOT'] . '/SME/' . $permit_path);
					$permit_url = $permit_exists ? '/SME/' . $permit_path : '';

					$approve_url = "PendingApprovals.php?id_pending={$id_pending}&approve=1";
					$reject_url  = "PendingApprovals.php?id_pending={$id_pending}&reject=1";

					// pick an icon for the business
					$biz_icon_class = get_business_icon_class($row['business_type'] ?? '');

					// normalized business type for client-side filtering
					$biz_type_norm = strtolower(trim((string)$row['business_type']));
					?>
					<!-- include data-business-type so JS can filter -->
					<div class="pending-card card" data-business-type="<?php echo htmlspecialchars($biz_type_norm); ?>">
						<div class="pending-left">
							<div class="pending-thumb">
								<?php if ($dti_exists || $permit_exists): ?>
									<div class="file-preview-thumb">
										<?php if ($dti_exists): ?>
											<?php if (file_is_image($_SERVER['DOCUMENT_ROOT'] . '/SME/' . $dti_path)): ?>
												<!-- image: open full-screen in new tab -->
												<a href="<?php echo $dti_url; ?>" class="file-link" target="_blank" rel="noopener noreferrer" aria-label="Open DTI in new tab">
													<img src="<?php echo $dti_url; ?>" alt="DTI">
													<span class="file-label">DTI</span>
												</a>
											<?php else: ?>
												<!-- non-image: keep modal trigger -->
												<a href="#" class="detail-trigger"
												   data-url="<?php echo htmlspecialchars($dti_url); ?>"
												   data-file="<?php echo htmlspecialchars($dti_path); ?>"
												   data-type="DTI"
												   data-name="<?php echo htmlspecialchars($full_name); ?>"
												   data-est="<?php echo htmlspecialchars($establishment); ?>"
												   data-address="<?php echo htmlspecialchars($location); ?>"
												   data-btype="<?php echo htmlspecialchars($business_type); ?>"
												   data-nature="<?php echo htmlspecialchars($nature); ?>"
												   aria-label="View DTI for <?php echo htmlspecialchars($full_name); ?>">
													<div class="file-icon"><i class="<?php echo get_file_icon_class($dti_path); ?>"></i><span>DTI</span></div>
												</a>
											<?php endif; ?>
										<?php else: ?>
											<div class="file-empty">No DTI</div>
										<?php endif; ?>
									</div>
									<div class="file-preview-thumb">
										<?php if ($permit_exists): ?>
											<?php if (file_is_image($_SERVER['DOCUMENT_ROOT'] . '/SME/' . $permit_path)): ?>
												<!-- image: open full-screen in new tab -->
												<a href="<?php echo $permit_url; ?>" class="file-link" target="_blank" rel="noopener noreferrer" aria-label="Open Permit in new tab">
													<img src="<?php echo $permit_url; ?>" alt="Permit">
													<span class="file-label">Permit</span>
												</a>
											<?php else: ?>
												<!-- non-image: keep modal trigger -->
												<a href="#" class="detail-trigger"
												   data-url="<?php echo htmlspecialchars($permit_url); ?>"
												   data-file="<?php echo htmlspecialchars($permit_path); ?>"
												   data-type="Permit"
												   data-name="<?php echo htmlspecialchars($full_name); ?>"
												   data-est="<?php echo htmlspecialchars($establishment); ?>"
												   data-address="<?php echo htmlspecialchars($location); ?>"
												   data-btype="<?php echo htmlspecialchars($business_type); ?>"
												   data-nature="<?php echo htmlspecialchars($nature); ?>"
												   aria-label="View Permit for <?php echo htmlspecialchars($full_name); ?>">
													<div class="file-icon"><i class="<?php echo get_file_icon_class($permit_path); ?>"></i><span>Permit</span></div>
												</a>
											<?php endif; ?>
										<?php else: ?>
											<div class="file-empty">No Permit</div>
										<?php endif; ?>
									</div>
								<?php else: ?>
									<!-- fallback: show entity/user icon when no files -->
									<?php if ($img_exists): ?>
										<a href="<?php echo $img_url; ?>" target="_blank" rel="noopener noreferrer" class="thumb-img-link"><img src="<?php echo $img_url; ?>" alt="User Image"></a>
										<div class="thumb-overlay-icon"><i class="<?php echo $biz_icon_class; ?>"></i></div>
									<?php else: ?>
										<div class="entity-icon"><i class="<?php echo $biz_icon_class; ?>"></i></div>
									<?php endif; ?>
								<?php endif; ?>
 							</div>
						</div>

						<div class="pending-body">
							<div class="pending-title">
								<h3><?php echo $full_name; ?></h3>
								<div class="small text-muted"><?php echo $establishment; ?></div>
							</div>

							<div class="pending-meta">
								<div><strong>Address:</strong> <?php echo $location ?: 'N/A'; ?></div>
								<div><strong>Business Type:</strong> <?php echo $business_type; ?></div>
								<div><strong>Nature:</strong> <?php echo $nature; ?></div>
								<div><strong>Contact:</strong> <?php echo $contact ?: 'N/A'; ?></div>
							</div>

							<!-- moved approve/reject actions here so they sit below 'Nature' / requested plan -->
							<div class="subscription-actions" role="group" aria-label="Approve or reject actions">
								<!-- data-name used by JS to show in modal; no inline confirm -->
								<a href="<?php echo htmlspecialchars($approve_url); ?>"
								   class="action-btn action-approve"
								   data-label="Approve"
								   data-name="<?php echo htmlspecialchars($full_name); ?>"
								   aria-label="Approve <?php echo htmlspecialchars($full_name); ?>">
									<i class="fas fa-check"></i>
								</a>
								<a href="<?php echo htmlspecialchars($reject_url); ?>"
								   class="action-btn action-reject"
								   data-label="Reject"
								   data-name="<?php echo htmlspecialchars($full_name); ?>"
								   aria-label="Reject <?php echo htmlspecialchars($full_name); ?>">
									<i class="fas fa-times"></i>
								</a>
							</div>
						</div>
					</div>
				<?php
				} // end while
			} // end else
			?>
		</div>

	</div>

	<!-- Confirmation modal (shared for approve/reject) -->
	<div id="confirmModal" class="confirm-modal" aria-hidden="true" role="dialog" aria-modal="true" style="display:none;">
		<div class="confirm-backdrop" data-close style="position:fixed;inset:0;background:rgba(0,0,0,0.5);"></div>
		<div class="confirm-panel" role="document" style="position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);background:#fff;border-radius:10px;box-shadow:0 20px 50px rgba(0,0,0,0.4);max-width:420px;width:90%;z-index:2000;overflow:hidden;">
			<div style="padding:18px 20px;">
				<h3 id="confirmTitle" style="margin:0 0 8px;font-size:18px;color:#0f172a;"></h3>
				<p id="confirmMessage" style="margin:0 0 14px;color:#4b5563;">Are you sure you want to continue?</p>
				<div style="display:flex;gap:10px;justify-content:flex-end;">
					<button id="confirmCancel" style="padding:8px 12px;border-radius:8px;border:1px solid #d1d5db;background:#fff;cursor:pointer;">Cancel</button>
					<button id="confirmOk" style="padding:8px 12px;border-radius:8px;border:0;background:#E62727;color:#fff;cursor:pointer;">Confirm</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Pending Detail Modal (shows DTI / Permit when clicked) -->
	<div id="pendingDetailModal" class="proof-modal" aria-hidden="true" style="display:none;">
		<div class="proof-modal-backdrop" data-close></div>
		<div class="proof-modal-panel" role="dialog" aria-modal="true" style="max-width:1000px;">
			<button class="proof-modal-close" aria-label="Close details">&times;</button>
			<div class="proof-modal-body">
				<div class="proof-modal-media" id="pendingDetailMedia">
					<!-- image or embed injected here -->
				</div>
				<div class="proof-modal-info" id="pendingDetailInfo">
					<div class="proof-modal-name" id="pdName"></div>
					<div class="proof-modal-meta" id="pdEst"></div>
					<div class="proof-modal-meta" id="pdAddress"></div>
					<div class="proof-modal-meta" id="pdBtype"></div>
					<div class="proof-modal-meta" id="pdNature"></div>
				</div>
			</div>
		</div>
	</div>
	
	<script>
		// shared confirmation modal logic for PendingApprovals (replaces inline confirm)
		(function(){
			var modal = document.getElementById('confirmModal');
			var titleEl = document.getElementById('confirmTitle');
			var msgEl = document.getElementById('confirmMessage');
			var okBtn = document.getElementById('confirmOk');
			var cancelBtn = document.getElementById('confirmCancel');
			var backdrop = modal && modal.querySelector('[data-close]');
			var pendingHref = null;

			function openConfirm(action, name, href){
				pendingHref = href || null;
				titleEl.textContent = (action === 'approve') ? 'Approve registration' : 'Reject registration';
				msgEl.textContent = (action === 'approve')
					? 'Approve registration for ' + name + '?'
					: 'Reject registration for ' + name + '?';
				if (action === 'approve') {
					okBtn.style.background = '#22c55e'; // Green for approve
					okBtn.textContent = 'Approve';
				} else {
					okBtn.style.background = '#ef4444'; // Red for reject
					okBtn.textContent = 'Reject';
				}
				modal.style.display = 'block';
				modal.setAttribute('aria-hidden','false');
				okBtn.focus();
			}
			function closeConfirm(){
				modal.style.display = 'none';
				modal.setAttribute('aria-hidden','true');
				pendingHref = null;
			}

			// attach to approve/reject action buttons
			document.querySelectorAll('.action-btn.action-approve, .action-btn.action-reject').forEach(function(a){
				a.addEventListener('click', function(e){
					e.preventDefault();
					var name = a.getAttribute('data-name') || '';
					var href = a.getAttribute('href');
					var action = a.classList.contains('action-approve') ? 'approve' : 'reject';
					openConfirm(action, name, href);
				});
			});

			// navigate to original href on confirm
			okBtn.addEventListener('click', function(){
				if (pendingHref) {
					window.location.href = pendingHref;
				} else {
					closeConfirm();
				}
			});
			cancelBtn.addEventListener('click', closeConfirm);
			if (backdrop) backdrop.addEventListener('click', closeConfirm);
			document.addEventListener('keydown', function(e){
				if (e.key === 'Escape' && modal.style.display === 'block') closeConfirm();
			});
		})();

		// open detail modal when clicking DTI/Permit triggers
		(function(){
			function isImageFile(path){
				if(!path) return false;
				var ext = path.split('.').pop().toLowerCase();
				return ['jpg','jpeg','png','gif','bmp','webp','svg'].indexOf(ext) !== -1;
			}
			var modal = document.getElementById('pendingDetailModal');
			var media = document.getElementById('pendingDetailMedia');
			var infoName = document.getElementById('pdName');
			var infoEst = document.getElementById('pdEst');
			var infoAddress = document.getElementById('pdAddress');
			var infoBtype = document.getElementById('pdBtype');
			var infoNature = document.getElementById('pdNature');

			function openModal(details){
				// clear previous
				media.innerHTML = '';
				infoName.textContent = details.name || '';
				infoEst.textContent = details.establishment ? ('Establishment: ' + details.establishment) : '';
				infoAddress.textContent = details.address ? ('Address: ' + details.address) : '';
				infoBtype.textContent = details.btype ? ('Business Type: ' + details.btype) : '';
				infoNature.textContent = details.nature ? ('Nature: ' + details.nature) : '';

				if (isImageFile(details.file || details.url)){
					var img = document.createElement('img');
					img.className = 'proof-modal-img';
					img.src = details.url;
					img.alt = details.type || 'Document';
					media.appendChild(img);
				} else {
					var iframe = document.createElement('iframe');
					iframe.className = 'proof-modal-embed';
					iframe.src = details.url;
					iframe.title = details.type || 'Document';
					media.appendChild(iframe);
				}

				modal.style.display = 'flex';
				modal.classList.add('open');
				modal.setAttribute('aria-hidden','false');
			}
			function closeModal(){
				modal.style.display = 'none';
				modal.classList.remove('open');
				modal.setAttribute('aria-hidden','true');
				media.innerHTML = '';
			}

			document.addEventListener('click', function(e){
				var t = e.target.closest('.detail-trigger');
				if(t){
					e.preventDefault();
					openModal({
						url: t.getAttribute('data-url'),
						file: t.getAttribute('data-file'),
						type: t.getAttribute('data-type'),
						name: t.getAttribute('data-name'),
						establishment: t.getAttribute('data-est'),
						address: t.getAttribute('data-address'),
						btype: t.getAttribute('data-btype'),
						nature: t.getAttribute('data-nature')
					});
				}
			});

			// close handlers
			var closeBtn = modal && modal.querySelector('.proof-modal-close');
			var backdrop = modal && modal.querySelector('.proof-modal-backdrop');
			if(closeBtn) closeBtn.addEventListener('click', closeModal);
			if(backdrop) backdrop.addEventListener('click', closeModal);
			document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeModal(); });
		})();
	</script>
</main>