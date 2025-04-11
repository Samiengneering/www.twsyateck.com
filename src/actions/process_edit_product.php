<?php
// src/actions/process_edit_product.php
session_start(); // Start session for messages

// Correct paths for includes relative to the actions directory
require_once '../includes/check_admin.php'; // Secure the script
require_once '../config/database.php'; // $pdo is available

// --- Form Data Validation ---
// Check method and ensure all required fields (including cost_price) are present and valid
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['product_id']) && filter_var($_POST['product_id'], FILTER_VALIDATE_INT) &&
    isset($_POST['name']) && !empty(trim($_POST['name'])) &&
    isset($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] >= 0 &&
    isset($_POST['cost_price']) && is_numeric($_POST['cost_price']) && $_POST['cost_price'] >= 0 && // <<< Validate cost_price
    isset($_POST['stock_quantity']) && filter_var($_POST['stock_quantity'], FILTER_VALIDATE_INT) !== false && $_POST['stock_quantity'] >= 0
   )
{
    // --- Get and Sanitize Input Data ---
    $productId = (int)$_POST['product_id'];
    $name = trim($_POST['name']);
    $barcode = (isset($_POST['barcode']) && trim($_POST['barcode']) !== '') ? trim($_POST['barcode']) : null;
    $price = (float)$_POST['price'];
    $cost_price = (float)$_POST['cost_price']; // <<< Get cost price
    $stock_quantity = (int)$_POST['stock_quantity'];
    $category_id = (isset($_POST['category_id']) && !empty($_POST['category_id'])) ? (int)$_POST['category_id'] : null;
    $image_url = (isset($_POST['image_url']) && trim($_POST['image_url']) !== '') ? trim($_POST['image_url']) : null;
    $is_quick_key = isset($_POST['is_quick_key']) ? 1 : 0;

    // --- Database Interaction ---
    try {
        // Prepare the UPDATE statement including cost_price
        $sql = "UPDATE products SET
                    name = :name,
                    barcode = :barcode,
                    price = :price,
                    cost_price = :cost_price, -- <<< Include cost_price
                    stock_quantity = :stock_quantity,
                    category_id = :category_id,
                    image_url = :image_url,
                    is_quick_key = :is_quick_key
                WHERE id = :product_id";
        $stmt = $pdo->prepare($sql);

        // Bind all parameters
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':barcode', $barcode, ($barcode === null) ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':cost_price', $cost_price); // <<< Bind cost_price
        $stmt->bindParam(':stock_quantity', $stock_quantity, PDO::PARAM_INT);
        $stmt->bindParam(':category_id', $category_id, ($category_id === null) ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':image_url', $image_url, ($image_url === null) ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':is_quick_key', $is_quick_key, PDO::PARAM_INT);
        $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT); // Bind the product ID for the WHERE clause

        // Execute the update
        $stmt->execute();

        // --- Set Success Feedback ---
        $_SESSION['message'] = "Product '" . htmlspecialchars($name) . "' updated successfully!";
        $_SESSION['message_type'] = 'success';

    } catch (PDOException $e) {
        // --- Handle Database Errors ---
         if ($e->errorInfo[1] == 1062) { // Duplicate barcode error
             $_SESSION['message'] = "Error: Barcode '" . htmlspecialchars($barcode) . "' is already used by another product.";
        } else {
            error_log("Admin Edit Product Error: " . $e->getMessage());
            $_SESSION['message'] = "A database error occurred while updating the product.";
        }
        $_SESSION['message_type'] = 'error';
        // Redirect back to the edit form on database error so user can see what happened
        header("Location: ../../public/admin_edit_product.php?id=" . $productId);
        exit;
    }
} else {
    // --- Handle Invalid Input / Failed Validation ---
    $_SESSION['message'] = "Invalid input provided for update. Please check all required fields.";
    $_SESSION['message_type'] = 'error';
     // Redirect back to the edit form if possible, otherwise to list
     $productId = $_POST['product_id'] ?? null;
     if ($productId && filter_var($productId, FILTER_VALIDATE_INT)) {
        header("Location: ../../public/admin_edit_product.php?id=" . $productId);
     } else {
        header("Location: ../../public/admin_products.php");
     }
     exit;
}

// Redirect to the product list page after successful update
header("Location: ../../public/admin_products.php");
exit();
?>