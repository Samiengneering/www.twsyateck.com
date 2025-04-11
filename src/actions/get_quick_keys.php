<?php
// src/actions/get_quick_keys.php
require_once '../config/database.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Could not fetch quick keys.', 'products' => []];

try {
    // Select products flagged as quick keys AND in stock
    // Join with categories to potentially group later or just for info
    $sql = "SELECT p.id, p.name, p.barcode, p.price, p.stock_quantity, p.image_url, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.is_quick_key = TRUE AND p.stock_quantity > 0
            ORDER BY c.display_order ASC, p.name ASC"; // Order logically

    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(); // Uses default fetch mode (ASSOC)

    if ($products) {
        $response = ['success' => true, 'products' => $products];
    } else {
        $response['message'] = 'No quick key products are currently configured or available.';
    }

} catch (PDOException $e) {
    error_log("Get Quick Keys Error: " . $e->getMessage());
    // Keep generic error message for client
    $response['message'] = 'Database error while fetching quick keys.';
}

echo json_encode($response);
exit();
?>