<?php
// src/actions/get_product_details.php
require_once '../config/database.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request'];

if (isset($_GET['barcode']) && !empty(trim($_GET['barcode']))) {
    $barcode = trim($_GET['barcode']);

    try {
        // Fetch product by EXACT barcode match
        $sql = "SELECT id, name, price, stock_quantity FROM products WHERE barcode = :barcode LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':barcode', $barcode, PDO::PARAM_STR);
        $stmt->execute();
        $product = $stmt->fetch(); // Uses default fetch mode (ASSOC)

        if ($product) {
            if ($product['stock_quantity'] > 0) {
                $response = ['success' => true, 'product' => $product];
            } else {
                $response['message'] = 'Product "' . htmlspecialchars($product['name']) . '" is out of stock.';
            }
        } else {
            $response['message'] = 'Product not found with barcode: ' . htmlspecialchars($barcode);
        }
    } catch (PDOException $e) {
        error_log("Get Product Details Error: " . $e->getMessage());
        $response['message'] = 'Database query error.';
    }
} else {
     $response['message'] = 'Barcode parameter missing or empty.';
}

echo json_encode($response);
exit();
?>