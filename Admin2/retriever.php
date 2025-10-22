<?php
include("../connections.php");
// include site nav/sidebar so layout matches index
include("../User/nav.php");
// load dashboard styles + shared pending/retriever styles and Font Awesome
echo '<link rel="stylesheet" href="../User/user-dashboard.css">';
echo '<link rel="stylesheet" href="admin-report.css">';
echo '<link rel="stylesheet" href="admin-new.css">'; // shared styles (contains retriever styles)
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';

/* add: fetch summary counts for welcome card */
$total_users = intval(mysqli_fetch_assoc(mysqli_query($connections, "SELECT COUNT(*) AS cnt FROM tbl_user"))['cnt'] ?? 0);
$total_business = intval(mysqli_fetch_assoc(mysqli_query($connections, "SELECT COUNT(*) AS cnt FROM tbl_business"))['cnt'] ?? 0);
$pending_subs_count = intval(mysqli_fetch_assoc(mysqli_query($connections, "SELECT COUNT(*) AS cnt FROM tbl_user WHERE subscription_proof IS NOT NULL AND subscription_approved = 0"))['cnt'] ?? 0);

?>

<!-- Inject admin sidebar items (match index / pending pages) -->
<script>
document.addEventListener("DOMContentLoaded", function(){
    var sidebar = document.querySelector(".sidebar-nav");
    if (!sidebar) return;
    sidebar.innerHTML = `
        <a href="index.php" class="nav-item">
            <i class="fas fa-table"></i>
            <span>View Records</span>
        </a>
        <a href="pendingapprovals.php" class="nav-item">
            <i class="fas fa-briefcase"></i>
            <span>Pending Business</span>
        </a>
        <a href="pendingsubscriptions.php" class="nav-item">
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
    // Normalize current path and set active state
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
</script>
<br>
<br>
<br>
<br>

<!-- wrap the old centered table in admin-style main/content -->
<main class="main-content retriever-container">

	<!-- added: welcome card (uses admin-new.css welcome styles) -->
	<div class="welcome-card card" role="region" aria-label="Users summary">
		<div class="welcome-content">
			<h1><i class="fas fa-users"></i> Users Overview</h1>
			<div class="welcome-text">Quick summary of registered users and business records.</div>
		</div>
		<div class="welcome-stats" role="list">
			<div class="stat-card" role="listitem" aria-label="Total users">
				<div class="stat-icon"><i class="fas fa-users"></i></div>
				<div class="stat-content">
					<h3><?php echo $total_users; ?></h3>
					<p>Total Users</p>
				</div>
			</div>
			<div class="stat-card" role="listitem" aria-label="Total businesses">
				<div class="stat-icon"><i class="fas fa-store"></i></div>
				<div class="stat-content">
					<h3><?php echo $total_business; ?></h3>
					<p>Total Businesses</p>
				</div>
			</div>
			<div class="stat-card" role="listitem" aria-label="Pending subscriptions">
				<div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
				<div class="stat-content">
					<h3><?php echo $pending_subs_count; ?></h3>
					<p>Pending Subscriptions</p>
				</div>
			</div>
		</div>
	</div>

	<div class="retriever-card card">
		<div class="card-header">
			<!-- <div class="card-title"><i class="fas fa-table"></i> Records</div> -->
			<div class="card-actions">
				<!-- optional: add global actions or search here -->
			</div>
		</div>

		<table class="retriever-table">
			<thead>
				<tr>
					<th>No.</th>
					<th>Full Name</th>
					<th>Establishment</th>
					<th>Address</th>
					<th>Business Type</th>
					<th>Business Nature</th>
					<th>Contact</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$retrieve_query = mysqli_query($connections, "SELECT u.id_user, u.first_name, u.middle_name, u.last_name, u.prefix, u.seven_digit, u.img, b.establishment_name, b.sabang_location, b.lot_street_business, b.business_type, b.nature_of_business 
                                             FROM tbl_user u 
                                             LEFT JOIN tbl_business b ON u.id_user = b.id_user 
                                             ORDER BY u.id_user ASC");

				/* added: initialize row counter */
				$row_num = 0;

				while($row = mysqli_fetch_assoc($retrieve_query)) {
				    /* increment counter for this row */
				    $row_num++;

					$id_user = $row["id_user"];
					$db_first_name = $row["first_name"];
					$db_middle_name = $row["middle_name"];
					$db_last_name = $row["last_name"];
					$db_establishment_name = $row["establishment_name"] ?: "N/A";
					$db_sabang_location = $row["sabang_location"] ?: "N/A";
					$db_lot_street_business = $row["lot_street_business"] ?: "N/A";
					$db_business_type = $row["business_type"] ?: "N/A";
					$db_nature_of_business = $row["nature_of_business"] ?: "N/A";
					$db_prefix = $row["prefix"];
					$db_seven_digit = $row["seven_digit"];
					$db_img = $row["img"] ?: "N/A";

					$full_name = ucfirst($db_first_name) . " " . ucfirst(isset($db_middle_name[0]) ? $db_middle_name[0] . "." : "") . " " . ucfirst($db_last_name);
					$contact = $db_prefix . $db_seven_digit;
					$full_address = ucfirst($db_sabang_location) . " " . ucfirst($db_lot_street_business);

					$id_user_esc = mysqli_real_escape_string($connections, $id_user);

					echo "
					<tr>
						<td data-label='No.'>{$row_num}</td>
						<td data-label='Full Name'>$full_name</td>
						<td data-label='Establishment'>$db_establishment_name</td>
						<td data-label='Address'>$full_address</td>
						<td data-label='Business Type'>$db_business_type</td>
						<td data-label='Business Nature'>$db_nature_of_business</td>
						<td data-label='Contact'>$contact</td>
						<td data-label='Action'><a href='confirm_delete.php?id_user=$id_user_esc' class='btn-delete'><i class='fas fa-trash'></i></a></td>
					</tr>
					";
				}
				?>
			</tbody>
		</table>
	</div>
</main>