<?php
// src/actions/fetch_receipt_data.php
require_once '../config/database.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid Sale ID provided.'];

if (isset($_GET['sale_id']) && filter_var($_GET['sale_id'], FILTER_VALIDATE_INT)) {
    $saleId = (int)$_GET['sale_id'];

    try {
        // --- Fetch Sale Details including cashier name ---
        // >>> VERIFY THIS SQL JOIN AND SELECT <<<
        $sqlSale = "SELECT s.sale_time, s.total_amount, u.full_name as cashier_name
                    FROM sales s
                    LEFT JOIN users u ON s.cashier_id = u.id  -- Join with users table
                    WHERE s.id = :sale_id";
        $stmtSale = $pdo->prepare($sqlSale);
        $stmtSale->bindParam(':sale_id', $saleId, PDO::PARAM_INT);
        $stmtSale->execute();
        $saleDetails = $stmtSale->fetch();
        // >>> END VERIFICATION <<<

        if (!$saleDetails) {
            $response['message'] = 'Sale record not found.';
        } else {
            // --- Fetch Sale Items (remains the same) ---
            $sqlItems = "SELECT si.quantity, si.price_at_sale, p.name as product_name
                         FROM sale_items si
                         JOIN products p ON si.product_id = p.id
                         WHERE si.sale_id = :sale_id";
            $stmtItems = $pdo->prepare($sqlItems);
            $stmtItems->bindParam(':sale_id', $saleId, PDO::PARAM_INT);
            $stmtItems->execute();
            $saleItems = $stmtItems->fetchAll();

            // Combine data and set success
            $response = [
                'success' => true,
                'sale_details' => $saleDetails, // Includes cashier_name now
                'sale_items' => $saleItems
            ];
        }

    } catch (PDOException $e) {
        error_log("Fetch Receipt Data Error (Sale ID: $saleId): " . $e->getMessage());
        $response['message'] = 'Database error occurred while fetching receipt data.';
    }

} else { /* Invalid Sale ID message */ }

echo json_encode($response);
exit();
?>