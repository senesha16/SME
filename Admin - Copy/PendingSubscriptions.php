<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["email"])) {
    echo "<script>window.location.href='../';</script>";
}

include("../connections.php");
// include("nav.php");

// include pending approvals/styles for admin pendings UI
// echo '<link rel="stylesheet" href="admin-pendings.css">';
// use the user's nav/sidebar so layout matches other admin pages
include("../User/nav.php");
// load user dashboard styles (for navbar/sidebar) and the page-specific admin pendings css
echo '<link rel="stylesheet" href="../User/user-dashboard.css">';
echo '<link rel="stylesheet" href="admin-pendings.css">';

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
        <a href="Analytics.php" class="nav-item">
            <i class="fas fa-chart-line"></i>
            <span>Analytics</span>
        </a>
    `;
    var path = window.location.pathname.split("/").pop();
    sidebar.querySelectorAll(".nav-item").forEach(function(a){
        var href = a.getAttribute("href");
        if (href === path || (href === "index.php" && (path === "" || path === "index.php"))) {
            a.classList.add("active");
        } else {
            a.classList.remove("active");
        }
    });
});
</script>';

require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email = $_SESSION["email"];
$query_account_type = mysqli_query($connections, "SELECT account_type FROM tbl_user WHERE email='$email'");
$user_row = mysqli_fetch_assoc($query_account_type);
if ($user_row['account_type'] != '1') {
    echo "<script>window.location.href='../';</script>";
}

if (isset($_GET['approve']) && isset($_GET['id_user'])) {
    $id_user = mysqli_real_escape_string($connections, $_GET['id_user']);
    $query = mysqli_query($connections, "SELECT first_name, last_name, email, subscription_plan, requested_plan FROM tbl_user WHERE id_user='$id_user'");
    if ($row = mysqli_fetch_assoc($query)) {
        $first_name = $row['first_name'];
        $last_name = $row['last_name'];
        $user_email = $row['email'];
        $requested_plan = $row['requested_plan'] ?? $row['subscription_plan']; // Fallback to current plan if requested_plan is null
        mysqli_query($connections, "UPDATE tbl_user SET subscription_approved=1, subscription_plan='$requested_plan', subscription_proof=NULL, requested_plan=NULL WHERE id_user=$id_user");
        
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
            $mail->addAddress($user_email);
            $mail->isHTML(true);
            $mail->Subject = 'Subscription Approval';
            $mail->Body = "Dear $first_name $last_name,<br><br>Your subscription to Plan $requested_plan has been approved. You now can Log in at <a href='http://localhost/SME/'>SME System</a>.<br><br>Best regards,<br>SME System Team";
            $mail->send();
            echo "<script>window.location.href='PendingSubscriptions.php?notify=Subscription%20approved%20and%20email%20sent';</script>";
        } catch (Exception $e) {
            echo "<script>window.location.href='PendingSubscriptions.php?notify=Subscription%20approved%20but%20email%20failed:%20" . urlencode($mail->ErrorInfo) . "';</script>";
        }
    } else {
        echo "<script>window.location.href='PendingSubscriptions.php?notify=Invalid%20user%20ID';</script>";
    }
}

if (isset($_GET['reject']) && isset($_GET['id_user'])) {
    $id_user = mysqli_real_escape_string($connections, $_GET['id_user']);
    $query = mysqli_query($connections, "SELECT first_name, last_name, email, subscription_proof FROM tbl_user WHERE id_user='$id_user'");
    if ($row = mysqli_fetch_assoc($query)) {
        $first_name = $row['first_name'];
        $last_name = $row['last_name'];
        $user_email = $row['email'];
        $proof = $row['subscription_proof'];
        if ($proof) {
            $full_path = $_SERVER['DOCUMENT_ROOT'] . '/SME/' . $proof;
            if (file_exists($full_path)) {
                unlink($full_path);
                error_log("PendingSubscriptions.php: Deleted proof file: $full_path");
            }
        }
        mysqli_query($connections, "UPDATE tbl_user SET subscription_proof=NULL, requested_plan=NULL WHERE id_user=$id_user");
        
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
            $mail->addAddress($user_email);
            $mail->isHTML(true);
            $mail->Subject = 'Subscription Rejection';
            $mail->Body = "Dear $first_name $last_name,<br><br>We regret to inform you that your subscription request has been rejected. Please contact our support team at <a href='mailto:support@smesystem.com'>support@smesystem.com</a> for more details.<br><br>Best regards,<br>SME System Team";
            $mail->send();
            echo "<script>window.location.href='PendingSubscriptions.php?notify=Subscription%20rejected%20and%20email%20sent';</script>";
        } catch (Exception $e) {
            echo "<script>window.location.href='PendingSubscriptions.php?notify=Subscription%20rejected%20but%20email%20failed:%20" . urlencode($mail->ErrorInfo) . "';</script>";
        }
    } else {
        echo "<script>window.location.href='PendingSubscriptions.php?notify=Invalid%20user%20ID';</script>";
    }
}

$notify = isset($_GET["notify"]) ? $_GET["notify"] : "";

// small metrics for header
$pending_subs_count_row = mysqli_fetch_assoc(mysqli_query($connections, "SELECT COUNT(*) AS cnt FROM tbl_user WHERE subscription_proof IS NOT NULL AND subscription_approved = 0"));
$pending_subs_count = intval($pending_subs_count_row['cnt'] ?? 0);
$earnings_row = mysqli_fetch_assoc(mysqli_query($connections, "SELECT IFNULL(SUM(total_cost),0) AS tot FROM tbl_purchase WHERE status='paid'"));
$total_earnings = floatval($earnings_row['tot'] ?? 0.00);
?>

<!-- Pending Subscriptions Dashboard -->
<main class="main-content pending-container">
    <div class="welcome-card card">
        <div class="welcome-content">
            <h1><i class="fas fa-file-invoice-dollar"></i> Pending Subscriptions</h1>
            <p class="welcome-text">Verify uploaded proof of payment and approve subscriptions.</p>
        </div>
        <div class="welcome-stats">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
                <div class="stat-content">
                    <h3><?php echo $pending_subs_count; ?></h3>
                    <p>Pending Subscriptions</p>
                </div>
            </div>
            <!-- removed Total Earnings stat-card -->
        </div>
    </div>

	<!-- Dashboard header: search, plan filter (view toggle removed) -->
	<div class="dashboard-header card">
		<div class="controls">
			<input id="subs-search" class="search-input" type="search" placeholder="Search name, establishment, email…" aria-label="Search pending subscriptions">
			<select id="subs-filter" class="filter-select" aria-label="Filter by plan">
				<option value="">All Plans</option>
				<option value="a">A</option>
				<option value="b">B</option>
				<option value="c">C</option>
			</select>
		</div>
	</div>

    <div class="card">
		<div class="pending-grid">
			<?php
			$query = mysqli_query($connections, "
                SELECT u.id_user, u.first_name, u.middle_name, u.last_name, u.email, u.subscription_proof, u.subscription_plan, u.requested_plan, b.establishment_name
                FROM tbl_user u
                LEFT JOIN tbl_business b ON u.id_user = b.id_user
                WHERE u.subscription_proof IS NOT NULL AND u.subscription_approved = 0 AND u.account_type = 2
                ORDER BY u.id_user DESC
            ");

            if (!$query || mysqli_num_rows($query) == 0) {
                echo '<div class="empty-state card">No pending subscriptions found.</div>';
            } else {
                while ($row = mysqli_fetch_assoc($query)) {
                    $id_user = intval($row['id_user']);
                    $first = htmlspecialchars($row['first_name'] ?? '');
                    $middle = htmlspecialchars($row['middle_name'] ?? '');
                    $last = htmlspecialchars($row['last_name'] ?? '');
                    $full_name = trim(ucfirst($first) . ($middle ? ' ' . strtoupper($middle[0]) . '. ' : ' ') . ucfirst($last));
                    $establishment = htmlspecialchars($row['establishment_name'] ?? 'N/A');
                    $email_user = htmlspecialchars($row['email'] ?? '');
                    $subscription_plan = htmlspecialchars($row['subscription_plan'] ?? 'N/A');
                    $requested_plan = htmlspecialchars($row['requested_plan'] ?? $subscription_plan);

					// map common plan names to compact codes used by the filter (a/b/c)
					$rp_norm = strtolower(trim($requested_plan));
					if (strpos($rp_norm, 'basic') !== false || $rp_norm === 'a') {
						$plan_code = 'a';
					} elseif (strpos($rp_norm, 'pro') !== false || $rp_norm === 'b') {
						$plan_code = 'b';
					} elseif (strpos($rp_norm, 'premium') !== false || $rp_norm === 'c') {
						$plan_code = 'c';
					} else {
						$plan_code = ''; // unknown / no-code
					}

                    $proof = $row['subscription_proof'] ? str_replace("\\","/",$row['subscription_proof']) : '';
                    $proof_full = $proof ? $_SERVER['DOCUMENT_ROOT'] . '/SME/' . $proof : '';
                    $proof_exists = $proof && file_exists($proof_full);
                    $proof_url = $proof_exists ? htmlspecialchars('/SME/' . $proof) : '';
                    $approve_url = "PendingSubscriptions.php?id_user={$id_user}&approve=1";
                    $reject_url  = "PendingSubscriptions.php?id_user={$id_user}&reject=1";
                    ?>
                    <!-- add data-requested-plan for client filtering -->
                    <div class="pending-card card" data-requested-plan="<?php echo htmlspecialchars($plan_code); ?>">
						<div class="pending-left">
							<div class="pending-thumb">
								<?php if ($proof_exists):
									$ext = strtolower(pathinfo($proof, PATHINFO_EXTENSION));
									$is_image = in_array($ext, ['jpg','jpeg','png','gif','bmp','webp','svg']);
									$size_bytes = @filesize($proof_full) ?: 0;
									$size_kb = round($size_bytes / 1024, 1);
									$filename = basename($proof);
                                ?>
                                    <a href="#" class="proof-link" role="button"
                                       data-url="<?php echo $proof_url; ?>"
                                       data-ext="<?php echo $ext; ?>"
                                       data-name="<?php echo htmlspecialchars($filename); ?>"
                                       data-size="<?php echo $size_kb; ?>">
                                        <?php if ($is_image): ?>
                                            <img src="<?php echo $proof_url; ?>" alt="Proof of <?php echo htmlspecialchars($full_name); ?>" loading="lazy">
                                        <?php else: ?>
                                            <div class="file-card">
                                                <i class="fas <?php echo ($ext === 'pdf') ? 'fa-file-pdf' : 'fa-file-alt'; ?> file-icon"></i>
                                                <div class="file-name"><?php echo htmlspecialchars($filename); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                    <div class="proof-meta">
                                        <span class="badge badge-prooftype"><?php echo strtoupper($ext); ?></span>
                                        <small class="proof-size"><?php echo $size_kb; ?> KB</small>
                                    </div>
                                <?php else: ?>
                                    <div class="no-thumb small text-muted">No proof uploaded</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="pending-body">
                            <div class="pending-title">
                                <h3><?php echo $full_name; ?></h3>
                                <div class="small text-muted"><?php echo $establishment; ?></div>
                            </div>
                            <div class="pending-meta">
                                <div><strong>Email:</strong> <?php echo $email_user; ?></div>
                                <div><strong>Current Plan:</strong> <?php echo $subscription_plan; ?></div> 
                                <div><strong>Requested Plan:</strong> <?php echo $requested_plan; ?></div>
                            </div>
                        </div>

                        <div class="pending-actions">
							<div class="subscription-actions">
								<!-- data-name used by JS to show in modal; inline confirms removed -->
								<a href="<?php echo htmlspecialchars($approve_url); ?>" 
								   class="action-btn action-approve" 
								   data-label="Approve"
								   data-name="<?php echo htmlspecialchars($full_name); ?>"
								   aria-label="Approve <?php echo htmlspecialchars($full_name); ?>"
								   title="Approve">
									<i class="fas fa-check"></i>
								</a>
								<a href="<?php echo htmlspecialchars($reject_url); ?>" 
								   class="action-btn action-reject" 
								   data-label="Reject"
								   data-name="<?php echo htmlspecialchars($full_name); ?>"
								   aria-label="Reject <?php echo htmlspecialchars($full_name); ?>"
								   title="Reject">
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

	<script>
		// confirmation modal logic (replaces inline confirm)
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
				titleEl.textContent = (action === 'approve') ? 'Approve subscription' : 'Reject subscription';
				msgEl.textContent = (action === 'approve')
					? 'Approve subscription for ' + name + '?'
					: 'Reject subscription for ' + name + '?';
				modal.style.display = 'block';
				modal.setAttribute('aria-hidden','false');
				okBtn.focus();
			}
			function closeConfirm(){
				modal.style.display = 'none';
				modal.setAttribute('aria-hidden','true');
				pendingHref = null;
			}

			// attach to all action links
			document.querySelectorAll('.action-btn.action-approve, .action-btn.action-reject').forEach(function(a){
				a.addEventListener('click', function(e){
					e.preventDefault();
					var name = a.getAttribute('data-name') || '';
					var href = a.getAttribute('href');
					var action = a.classList.contains('action-approve') ? 'approve' : 'reject';
					openConfirm(action, name, href);
				});
			});

			// confirm proceed navigates to stored href
			okBtn.addEventListener('click', function(){
				if (pendingHref) {
					// navigate to the original link (preserves GET params)
					window.location.href = pendingHref;
				} else {
					closeConfirm();
				}
			});
			cancelBtn.addEventListener('click', closeConfirm);
			if (backdrop) backdrop.addEventListener('click', closeConfirm);
			// keyboard: ESC to close
			document.addEventListener('keydown', function(e){
				if (e.key === 'Escape' && modal.style.display === 'block') closeConfirm();
			});
		})();
	</script>

	<!-- Proof preview modal (single shared modal for all proofs) -->
	<div id="proofModal" class="proof-modal" aria-hidden="true" role="dialog" aria-modal="true">
		<div class="proof-modal-backdrop" data-close></div>
		<div class="proof-modal-panel" role="document">
			<button class="proof-modal-close" aria-label="Close preview" data-close><i class="fas fa-times"></i></button>
			<div class="proof-modal-body">
				<div class="proof-modal-media" id="proofMedia" aria-live="polite"></div>
				<div class="proof-modal-info">
					<div class="proof-modal-name" id="proofName"></div>
					<div class="proof-modal-meta" id="proofMeta"></div>
				</div>
			</div>
		</div>
	</div>

	<script>
		// proof preview lightbox
		(function(){
			function $(sel, ctx){ return (ctx||document).querySelector(sel); }
			function $all(sel, ctx){ return Array.from((ctx||document).querySelectorAll(sel)); }

			var modal = $('#proofModal');
			var media = $('#proofMedia');
			var nameEl = $('#proofName');
			var metaEl = $('#proofMeta');

			function openModal(url, ext, name, size) {
				// clear
				media.innerHTML = '';
				nameEl.textContent = name || '';
				metaEl.textContent = (size ? size + ' KB • ' : '') + ext.toUpperCase();

				// render content
				ext = (ext || '').toLowerCase();
				if (['jpg','jpeg','png','gif','webp','bmp','svg'].indexOf(ext) !== -1) {
					var img = document.createElement('img');
					img.src = url;
					img.alt = name || 'Proof';
					img.loading = 'eager';
					img.className = 'proof-modal-img';
					media.appendChild(img);
				} else if (ext === 'pdf') {
					var iframe = document.createElement('iframe');
					iframe.src = url;
					iframe.className = 'proof-modal-embed';
					iframe.setAttribute('aria-label', name || 'PDF proof');
					media.appendChild(iframe);
				} else {
					var link = document.createElement('a');
					link.href = url;
					link.target = '_blank';
					link.rel = 'noopener noreferrer';
					link.textContent = 'Open file: ' + (name || url);
					media.appendChild(link);
				}

				modal.setAttribute('aria-hidden','false');
				modal.classList.add('open');
				document.body.style.overflow = 'hidden';
			}

			function closeModal() {
				modal.classList.remove('open');
				modal.setAttribute('aria-hidden','true');
				media.innerHTML = '';
				document.body.style.overflow = '';
			}

			// open handlers
			$all('.proof-link').forEach(function(el){
				el.addEventListener('click', function(e){
					e.preventDefault();
					var url = el.getAttribute('data-url');
					var ext = el.getAttribute('data-ext') || '';
					var name = el.getAttribute('data-name') || '';
					var size = el.getAttribute('data-size') || '';
					openModal(url, ext, name, size);
				});
			});

			// close handlers
			$all('[data-close]').forEach(function(el){ el.addEventListener('click', closeModal); });
			modal.addEventListener('keydown', function(e){
				if (e.key === 'Escape') closeModal();
			});
		})();
	</script>

    <?php if ($notify): ?>
        <div class="notification success" style="max-width: 1200px; margin: 20px auto;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($notify); ?>
        </div>
    <?php endif; ?>
</main>

<!-- ensure icons load -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">