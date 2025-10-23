<style>
    table {
        border-collapse: collapse;
        margin: 20px auto;
    }
    td, th {
        padding: 10px;
        text-align: center;
        vertical-align: middle;
    }
    img {
        max-width: 100px;
        height: auto;
        display: block;
        margin: 5px auto;
    }
    .btn-approve, .btn-reject {
        padding: 5px 10px;
        text-decoration: none;
        color: white;
        border-radius: 3px;
        margin: 5px;
        display: inline-block;
    }
    .btn-approve {
        background-color: green;
    }
    .btn-reject {
        background-color: red;
    }
</style>

<center>
<table border="0" width="85%">
<tr>
    <td width="10%">Full Name</td>
    <td width="10%">Establishment</td>
    <td width="12%">Address</td>
    <td width="10%">Business Type</td>
    <td width="10%">Business Nature</td>
    <td width="10%">Contact</td>
    <td width="14%">Image</td>
    <td width="14%">DTI</td>
    <td width="14%">Business Permit</td>
    <td width="10%">Action</td>
</tr>
<tr>
    <td colspan='10'><hr></td>
</tr>

<?php
include("../connections.php");
include ("nav.php");
$PROJECT_ROOT = realpath(__DIR__ . '/../');
$SITE_ROOT_PATH = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/') . '/';
$SITE_BASE_URL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $SITE_ROOT_PATH;
$retrieve_query = mysqli_query($connections, "SELECT id_pending, first_name, middle_name, last_name, prefix, seven_digit, img, establishment_name, sabang_location, lot_street_business, business_type, nature_of_business, DTI, business_permit 
                                             FROM tbl_pending_users 
                                             WHERE account_type='pending'");
while($row = mysqli_fetch_assoc($retrieve_query)) {
    $id_pending = $row["id_pending"];
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
    $db_DTI = $row["DTI"] ?: "N/A";
    $db_business_permit = $row["business_permit"] ?: "N/A";

    $full_name = ucfirst($db_first_name) . " " . ucfirst(isset($db_middle_name[0]) ? $db_middle_name[0] . "." : "") . " " . ucfirst($db_last_name);
    $contact = $db_prefix . $db_seven_digit;
    $full_address = ucfirst($db_sabang_location) . " " . ucfirst($db_lot_street_business);
    $image_path = $db_img && $db_img !== '0' ? str_replace('\\','/',$db_img) : '';
    $image_full = $image_path ? $PROJECT_ROOT . '/' . ltrim($image_path, '/') : '';
    $image_url = $image_path ? $SITE_BASE_URL . ltrim($image_path, '/') : '';
    $image_display = ($image_full && file_exists($image_full)) ? "<a href='$image_url' target='_blank'><img src='$image_url' alt='User Image'></a>" : "No image";

    $dti_path = $db_DTI && $db_DTI !== 'N/A' ? str_replace('\\','/',$db_DTI) : '';
    $dti_full_path = $dti_path ? $PROJECT_ROOT . '/' . ltrim($dti_path, '/') : '';
    $dti_url = $dti_path ? $SITE_BASE_URL . ltrim($dti_path, '/') : '';

    $permit_path = $db_business_permit && $db_business_permit !== 'N/A' ? str_replace('\\','/',$db_business_permit) : '';
    $permit_full_path = $permit_path ? $PROJECT_ROOT . '/' . ltrim($permit_path, '/') : '';
    $permit_url = $permit_path ? $SITE_BASE_URL . ltrim($permit_path, '/') : '';
    $dti_display = ($dti_path && $dti_path !== "N/A" && file_exists($dti_full_path)) ? "<a href='$dti_url' target='_blank'><img src='$dti_url' alt='DTI'></a>" : "No image";
    $permit_display = ($permit_path && $permit_path !== "N/A" && file_exists($permit_full_path)) ? "<a href='$permit_url' target='_blank'><img src='$permit_url' alt='Business Permit'></a>" : "No image";

    $jScript = md5(rand(1,9));
    $newScript = md5(rand(1,9));
    $getApprove = md5(rand(1,9));
    $getReject = md5(rand(1,9));

    echo "
    <tr>
        <td>$full_name</td>
        <td>$db_establishment_name</td>
        <td>$full_address</td>
        <td>$db_business_type</td>
        <td>$db_nature_of_business</td>
        <td>$contact</td>
        <td>$image_display</td>
        <td>$dti_display</td>
        <td>$permit_display</td>
        <td>
            <center>
            <a href='PendingApprovals.php?jScript=$jScript&newScript=$newScript&approve=$getApprove&id_pending=$id_pending' class='btn-approve'>Approve</a>
            &nbsp;
            <a href='PendingApprovals.php?jScript=$jScript&newScript=$newScript&reject=$getReject&id_pending=$id_pending' class='btn-reject'>Reject</a>
            </center>
        </td>
    </tr>
    <tr>
        <td colspan='10'><hr></td>
    </tr>
    ";
}
?>
</table>
</center>