<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION["email"])) {
    echo "<script>window.location.href='../';</script>";
    exit;
}

include("../connections.php");

// Verify admin privileges
$email = mysqli_real_escape_string($connections, $_SESSION["email"]);
$query_account_type = mysqli_query($connections, "SELECT account_type FROM tbl_user WHERE email='$email'");
$user_row = mysqli_fetch_assoc($query_account_type);
if (!$user_row || $user_row['account_type'] != '1') {
    echo "<script>window.location.href='../';</script>";
    exit;
}

// Fetch analytics data for approved businesses
$locations = [];
$query = mysqli_query($connections, "SELECT sabang_location, enterprise_type, COUNT(*) as count 
                                    FROM tbl_business 
                                    WHERE enterprise_type IN ('Small Enterprise', 'Medium Enterprise') 
                                    GROUP BY sabang_location, enterprise_type 
                                    ORDER BY sabang_location");
while ($row = mysqli_fetch_assoc($query)) {
    $location = $row['sabang_location'] ?? 'Unknown';
    $type = $row['enterprise_type'] ?? 'Unknown';
    $locations[$location][$type] = $row['count'];
}

// Fetch pending users analytics
$pending_locations = [];
$pending_query = mysqli_query($connections, "SELECT sabang_location, enterprise_type, COUNT(*) as count 
                                            FROM tbl_pending_users 
                                            WHERE enterprise_type IN ('Small Enterprise', 'Medium Enterprise') 
                                            GROUP BY sabang_location, enterprise_type 
                                            ORDER BY sabang_location");
while ($row = mysqli_fetch_assoc($pending_query)) {
    $location = $row['sabang_location'] ?? 'Unknown';
    $type = $row['enterprise_type'] ?? 'Unknown';
    $pending_locations[$location][$type] = $row['count'];
}

// Combine approved and pending data
$all_locations = [];
foreach ($locations as $location => $types) {
    $all_locations[$location]['Small Enterprise'] = ($types['Small Enterprise'] ?? 0);
    $all_locations[$location]['Medium Enterprise'] = ($types['Medium Enterprise'] ?? 0);
}
foreach ($pending_locations as $location => $types) {
    $all_locations[$location]['Small Enterprise'] = ($all_locations[$location]['Small Enterprise'] ?? 0) + ($types['Small Enterprise'] ?? 0);
    $all_locations[$location]['Medium Enterprise'] = ($all_locations[$location]['Medium Enterprise'] ?? 0) + ($types['Medium Enterprise'] ?? 0);
}

// Prepare data for charts
$small_counts = [];
$medium_counts = [];
$total_types = ['Small Enterprise' => 0, 'Medium Enterprise' => 0];
foreach ($all_locations as $location => $types) {
    $small = $types['Small Enterprise'] ?? 0;
    $medium = $types['Medium Enterprise'] ?? 0;
    $small_counts[$location] = $small;
    $medium_counts[$location] = $medium;
    $total_types['Small Enterprise'] += $small;
    $total_types['Medium Enterprise'] += $medium;
}

// Find locations with most small and medium establishments
arsort($small_counts);
arsort($medium_counts);
$most_small_location = key($small_counts) ?: 'None';
$most_medium_location = key($medium_counts) ?: 'None';

// Prepare data for bar chart
$chart_labels = array_keys($all_locations);
$small_data = [];
$medium_data = [];
foreach ($chart_labels as $location) {
    $small_data[] = $all_locations[$location]['Small Enterprise'] ?? 0;
    $medium_data[] = $all_locations[$location]['Medium Enterprise'] ?? 0;
}

// --- add: include nav, styles and admin sidebar injection here (before output) ---
include("../User/nav.php");
// load user dashboard styles and admin tweaks
// Font Awesome (icons)
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
echo '<link rel="stylesheet" href="../User/user-dashboard.css">';
echo '<link rel="stylesheet" href="admin-index.css">';
echo '<link rel="stylesheet" href="admin-report.css">';

// replace sidebar with admin menu entries (case-insensitive active detection)
echo '<script>
document.addEventListener("DOMContentLoaded", function(){
    var sidebar = document.querySelector(".sidebar-nav");
    if (!sidebar) return;
    sidebar.innerHTML = `
        <a href="index.php" class="nav-item"><i class="fas fa-table"></i><span>View Records</span></a>
        <a href="PendingApprovals.php" class="nav-item"><i class="fas fa-briefcase"></i><span>Pending Business</span></a>
        <a href="PendingSubscriptions.php" class="nav-item"><i class="fas fa-file-invoice-dollar"></i><span>Pending Subscription</span></a>
        <a href="retriever.php" class="nav-item">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>View Users</span>
        </a>
        <a href="analytics.php" class="nav-item"><i class="fas fa-chart-line"></i><span>Analytics</span></a>
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

    // Measure sidebar width and expose it as a CSS variable so .main-content can avoid overlap.
    function updateSidebarWidth() {
        try {
            var rect = sidebar.getBoundingClientRect();
            var width = Math.round(rect.width) || 0;
            // if sidebar is visually hidden on small screens, set width to 0
            if (window.getComputedStyle(sidebar).display === "none" || width < 40) {
                width = 0;
            }
            // add a small gap (24px) for breathing room
            document.documentElement.style.setProperty("--sidebar-width", width + "px");
        } catch (e) {
            // fallback: leave CSS default --sidebar-width
            console.warn("Could not compute sidebar width", e);
        }
    }

    // Update immediately and on resize (debounced)
    updateSidebarWidth();
    var resizeTimer;
    window.addEventListener("resize", function(){
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(updateSidebarWidth, 120);
    });

    // If layout may change (sidebar toggle buttons), observe mutations and update
    var mo = new MutationObserver(function(){ updateSidebarWidth(); });
    mo.observe(sidebar, { attributes: true, childList: true, subtree: false });
});
</script>';

?>

<!-- Dashboard-style analytics UI -->
<main class="main-content">
    <div class="page-header">
        <div class="page-title">
            <h1><i class="fas fa-chart-line"></i> Analytics</h1>
            <p class="small text-muted">Brgy. Sabang's SME Summary.</Summary></p>
        </div>
        <div class="header-stats">
            <!-- explicit modifier classes added to match index conventions -->
            <div class="stat-card card stat--red">
                <div class="stat-icon"><i class="fas fa-building"></i></div>
                <div class="stat-content">
                    <h3><?php echo array_sum($small_data) + array_sum($medium_data); ?></h3>
                    <p>Total Establishments</p>
                </div>
            </div>
            <div class="stat-card card stat--blue">
                <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-content">
                    <h3><?php echo count($all_locations); ?></h3>
                    <p>Locations</p>
                </div>
            </div>
            <div class="stat-card card stat--yellow">
                <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                <div class="stat-content">
                    <h3><?php echo htmlspecialchars($most_small_location); ?></h3>
                    <p>Top Small Location</p>
                </div>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="card chart-card">
            <div class="card-title"><i class="fas fa-pie-chart"></i> Overall Establishment Types</div>
            <canvas id="totalPieChart"></canvas>
        </div>

        <div class="card chart-card chart-full">
            <div class="card-title"><i class="fas fa-map"></i> Establishments by Location</div>
            <canvas id="locationBarChart"></canvas>
        </div>
    </div>

    <div class="card" style="margin-top:18px;">
        <div class="section-title"><i class="fas fa-table"></i> Data Table</div>
        <div style="overflow:auto;">
            <table class="stock-table" style="width:100%; margin-top:0;">
                <thead>
                    <tr>
                        <th>Location</th>
                        <th>Small Enterprises</th>
                        <th>Medium Enterprises</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($all_locations as $location => $types): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($location); ?></td>
                        <td><?php echo $types['Small Enterprise'] ?? 0; ?></td>
                        <td><?php echo $types['Medium Enterprise'] ?? 0; ?></td>
                        <td><?php echo ($types['Small Enterprise'] ?? 0) + ($types['Medium Enterprise'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Pie Chart for Overall Establishment Types
    const totalCtx = document.getElementById('totalPieChart').getContext('2d');
    new Chart(totalCtx, {
        type: 'pie',
        data: {
            labels: ['Small Enterprises', 'Medium Enterprises'],
            datasets: [{
                data: [<?php echo (int)$total_types['Small Enterprise']; ?>, <?php echo (int)$total_types['Medium Enterprise']; ?>],
                backgroundColor: ['#F08787', '#B8B2A6']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'right' } // moved to right
            }
        }
    });

    // Bar Chart for Establishments by Location
    const locationCtx = document.getElementById('locationBarChart').getContext('2d');
    new Chart(locationCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                { label: 'Small Enterprises', data: <?php echo json_encode($small_data); ?>, backgroundColor: '#F08787' },
                { label: 'Medium Enterprises', data: <?php echo json_encode($medium_data); ?>, backgroundColor: '#B8B2A6' }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Number of Establishments' } },
                x: { title: { display: true, text: 'Location' } }
            },
            plugins: {
                legend: { position: 'right' } // moved to right
            }
        }
    });
</script>