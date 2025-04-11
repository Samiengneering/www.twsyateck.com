<?php
// src/actions/process_sale.php
session_start(); // Start session to access logged-in user info
require_once '../config/database.php'; // Include DB connection ($pdo is available)
header('Content-Type: application/json'); // Set response header for JSON

// --- Authentication Check ---
// Ensure a user is logged in before allowing a sale to be processed
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please login.']);
    exit; // Stop execution if not logged in
}
$loggedInUserId = $_SESSION['user_id']; // Get the ID of the currently logged-in cashier
// --- End Auth Check ---

// Default error response
$response = ['success' => false, 'message' => 'Invalid sale data received.'];

// Get the raw JSON data sent from the JavaScript fetch request
$jsonInput = file_get_contents('php://input');
// Decode the JSON string into a PHP associative array
$saleData = json_decode($jsonInput, true);

// --- Validate Input Structure ---
// Check if the decoded data is an array and contains a non-empty 'items' array
if (is_array($saleData) && isset($saleData['items']) && is_array($saleData['items']) && !empty($saleData['items'])) {

    $items = $saleData['items']; // The array of items in the cart {id: productId, quantity: qty}
    $totalSaleAmount = 0;        // Initialize total amount calculated server-side
    $itemsToInsert = [];         // Array to hold data for inserting into sale_items table
    $stockUpdates = [];          // Array to hold data for updating product stock
    $validationError = null;     // Variable to store the first validation error message

    // --- Database Transaction ---
    // Use a transaction to ensure all database operations succeed or fail together
    try {
        $pdo->beginTransaction();

        // 1. Server-Side Validation Loop (Crucial for Security & Accuracy)
        foreach ($items as $item) {
            // Basic validation of item structure received from client
            if (!isset($item['id']) || !isset($item['quantity']) ||
                !filter_var($item['id'], FILTER_VALIDATE_INT) ||
                !filter_var($item['quantity'], FILTER_VALIDATE_INT) || $item['quantity'] <= 0)
            {
                $validationError = "Invalid item data received (ID or Quantity missing/invalid).";
                break; // Stop processing on the first invalid item
            }

            $productId = (int)$item['id'];
            $requestedQuantity = (int)$item['quantity'];

            // Fetch current product details FROM DATABASE WITH LOCK to prevent race conditions
            // Includes selling price, cost price, and current stock
            $stmt = $pdo->prepare("SELECT name, price, cost_price, stock_quantity FROM products WHERE id = :id FOR UPDATE");
            $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            $product = $stmt->fetch(); // Use default FETCH_ASSOC mode

            // Check if product exists
            if (!$product) {
                $validationError = "Product with ID " . $productId . " not found in database.";
                break;
            }

            // Get current values from the database (don't trust client values other than ID and Qty)
            $currentPrice = (float)$product['price'];
            $currentCost = (float)($product['cost_price'] ?? 0.00); // Use cost from DB, default to 0 if NULL
            $currentStock = (int)$product['stock_quantity'];
            $productName = $product['name']; // For user-friendly error messages

            // CRITICAL: Check stock availability again on the server
            if ($currentStock < $requestedQuantity) {
                $validationError = "Insufficient stock for '" . htmlspecialchars($productName) . "' (ID: " . $productId . "). Available: " . $currentStock . ", Requested: " . $requestedQuantity;
                break;
            }

            // Calculate total amount based on SERVER-SIDE price
            $totalSaleAmount += ($currentPrice * $requestedQuantity);

            // Prepare data for batch inserting into sale_items table later
            $itemsToInsert[] = [
                'product_id' => $productId,
                'quantity' => $requestedQuantity,
                'price_at_sale' => $currentPrice,         // Record the price at this exact moment
                'cost_price_at_sale' => $currentCost     // Record the cost at this exact moment
            ];
            // Prepare data for batch updating product stock later
            $stockUpdates[] = [
                'id' => $productId,
                'new_stock' => $currentStock - $requestedQuantity
            ];
        } // End of validation loop foreach ($items as $item)

        // --- Process after loop: Check for validation errors ---
        if ($validationError !== null) {
            $pdo->rollBack(); // Abort the transaction if any validation failed
            $response['message'] = $validationError; // Send the specific error message back
        } else {
            // --- Validation Passed: Perform Database Writes ---

            // 2. Insert the main sale record into the 'sales' table
            $sqlSale = "INSERT INTO sales (total_amount, cashier_id) VALUES (:total, :cashier_id)";
            $stmtSale = $pdo->prepare($sqlSale);
            $stmtSale->bindValue(':total', $totalSaleAmount); // Use server-calculated total
            $stmtSale->bindParam(':cashier_id', $loggedInUserId, PDO::PARAM_INT); // Bind the logged-in cashier's ID
            $stmtSale->execute();
            $saleId = $pdo->lastInsertId(); // Get the ID of the sale record just created

            // Safety check in case insertion failed unexpectedly
            if (!$saleId) {
                 throw new Exception("Failed to create sale record after validation.");
            }

            // 3. Insert each item from the cart into the 'sale_items' table
            $sqlItem = "INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, cost_price_at_sale)
                        VALUES (:sid, :pid, :qty, :pas, :cas)";
            $stmtItem = $pdo->prepare($sqlItem);
            foreach ($itemsToInsert as $itd) {
                $stmtItem->execute([ // Using named placeholders in an associative array for execute()
                    'sid' => $saleId,
                    'pid' => $itd['product_id'],
                    'qty' => $itd['quantity'],
                    'pas' => $itd['price_at_sale'],
                    'cas' => $itd['cost_price_at_sale'] // Include cost price at sale
                ]);
            }

            // 4. Update the stock quantity for each product sold in the 'products' table
            $sqlStock = "UPDATE products SET stock_quantity = :ns WHERE id = :id";
            $stmtStock = $pdo->prepare($sqlStock);
            foreach ($stockUpdates as $upd) {
                 $stmtStock->execute(['ns' => $upd['new_stock'], 'id' => $upd['id']]);
            }

            // 5. Commit the transaction: All database changes are now made permanent
            $pdo->commit();
            // Prepare successful response
            $response = ['success' => true, 'sale_id' => $saleId, 'message' => 'Sale processed successfully.'];
        }

    } catch (Exception $e) { // Catch PDOException or general Exception
        // If any error occurred during the transaction, roll back all changes
        if ($pdo->inTransaction()) {
             $pdo->rollBack();
        }
        // Log the detailed error for the administrator/developer
        error_log("Process Sale Exception: " . $e->getMessage());
        // Set the response message (use validation error first, otherwise generic message)
        $response['message'] = $validationError ?? "An internal error occurred during sale processing. Please contact support.";
        // Ensure success is false on error
        $response['success'] = false;
    }
    // --- End Transaction Handling ---

} else {
    // Handle cases where the initial JSON data structure was invalid or empty
    $response['message'] = 'Invalid or empty sale data structure received.';
}

// --- Output Final JSON Response ---
// Encode the final $response array (either success or error details) into JSON
echo json_encode($response);
exit(); // Terminate script execution
?>