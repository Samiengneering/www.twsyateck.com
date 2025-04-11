<?php
// src/actions/search_product_manual.php
require_once '../config/database.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request', 'products' => []];

if (isset($_GET['term']) && !empty(trim($_GET['term']))) {
    $searchTerm = trim($_GET['term']);
    $searchTermWild = "%" . $searchTerm . "%"; // Wildcards for LIKE

    try {
        // Search name OR barcode using LIKE, fetch necessary fields
        // Important: Ensure you have indexes on 'name' and 'barcode' for performance!
        $sql = "SELECT id, name, barcode, price, stock_quantity
                FROM products
                WHERE name LIKE :term OR barcode LIKE :term
                ORDER BY name ASC LIMIT 10"; // Limit results for performance/UI clarity

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':term', $searchTermWild, PDO::PARAM_STR);
        $stmt->execute();
        $products = $stmt->fetchAll(); // Uses default fetch mode (ASSOC)

        if ($products) {
            // Filter out zero stock items server-side
             $availableProducts = array_filter($products, function($product) {
                 return isset($product['stock_quantity']) && $product['stock_quantity'] > 0;
             });

             if (!empty($availableProducts)) {
                 $response = [
                    'success' => true,
                    'products' => array_values($availableProducts) // Re-index
                 ];
             } else {
                  $response['message'] = 'Matches found, but all are out of stock.';
             }
        } else {
            $response['message'] = 'No products found matching "' . htmlspecialchars($searchTerm) . '".';
        }
    } catch (PDOException $e) {
        error_log("Manual Search Error: " . $e->getMessage());
        // --- DEBUGGING (Optional) ---
        // $response['message'] = 'DEBUG: DB Error: ' . $e->getMessage();
        // --- PRODUCTION ---
        $response['message'] = 'Database query error during search.';
    }
} else {
    $response['message'] = 'Search term parameter missing or empty.';
}

echo json_encode($response);
exit();
?>