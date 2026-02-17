<?php
ob_start();
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; form-action 'self'; upgrade-insecure-requests; base-uri 'self';");
include("../connections.php");

// === CONFIGURATION ===
// Change this to the correct user ID that owns the inventory
$user_id = 50; // <-- UPDATE THIS IF NEEDED (same as in index.php, MyAccount.php, etc.)

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$term = mysqli_real_escape_string($connections, $term);

$query = "SELECT id_item, name_item, unit_item, quantity_item, selling_price_item, selling_price_individual, sell_as_pack, sell_as_sachet 
          FROM tbl_item 
          WHERE id_user = ? 
            AND quantity_item > 0 
            AND (expiration_date_item IS NULL OR expiration_date_item > CURDATE()) 
            AND name_item LIKE ?";

$stmt = $connections->prepare($query);
if ($stmt === false) {
    error_log("Prepare failed for search items: " . $connections->error);
    http_response_code(500);
    echo json_encode([]);
    exit;
}

$search_term = "%$term%";
$stmt->bind_param("is", $user_id, $search_term);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($item = $result->fetch_assoc()) {
    $show_item = false;
    $price = 0.00;

    if ($item["unit_item"] == 'piece' && $item["sell_as_sachet"] == 1) {
        $show_item = true;
        $price = $item["selling_price_individual"] ?? $item["selling_price_item"];
    } elseif (($item["unit_item"] == 'pack' || $item["unit_item"] == 'box') && $item["sell_as_pack"] == 1) {
        $show_item = true;
        $price = $item["selling_price_item"];
    }

    if ($show_item) {
        $items[] = [
            'id_item'       => $item['id_item'],
            'name_item'     => $item['name_item'],
            'unit_item'     => $item['unit_item'],
            'quantity_item' => $item['quantity_item'],
            'price'         => $price
        ];
    }
}

$stmt->close();

header('Content-Type: application/json');
echo json_encode($items);
?>