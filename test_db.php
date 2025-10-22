<?php
// Database connection test
$connections = mysqli_connect("localhost", "root", "", "sme"); 

if (mysqli_connect_errno()){
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
    exit();
} else {
    echo "✅ Database connection successful!<br><br>";
}

// Test query for users
echo "<h3>Available Users:</h3>";
$result = mysqli_query($connections, "SELECT id_user, email, password, account_type FROM tbl_user");

if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Email</th><th>Password</th><th>Account Type</th></tr>";
    
    while($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id_user'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . $row['password'] . "</td>";
        echo "<td>" . $row['account_type'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Error querying users: " . mysqli_error($connections);
}

mysqli_close($connections);
?>

<style>
table { border-collapse: collapse; margin: 20px 0; }
th, td { padding: 10px; text-align: left; }
th { background-color: #f2f2f2; }
</style>