<?php
// src/actions/process_add_product.php
session_start(); // Required for feedback messages

// --- Security Check: Ensure only admins can access this ---
// Note: We require check_admin *after* config to ensure PDO is available if needed by check_admin
require_once '../config/database.php'; // $pdo is available
require_once '../includes/check_admin.php'; // Secure the script (redirects/dies if not admin)

// --- Form Data Validation ---
// Check if the request method is POST and all required fields are set and valid
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['name']) && !empty(trim($_POST['name'])) &&              // Name required
    isset($_POST['price']) && is_numeric($_POST['price']) && $_POST['price'] >= 0 && // Price required, numeric, non-negative
    isset($_POST['cost_price']) && is_numeric($_POST['cost_price']) && $_POST['cost_price'] >= 0 && // Cost Price required, numeric, non-negative
    isset($_POST['stock_quantity']) && filter_var($_POST['stock_quantity'], FILTER_VALIDATE_INT) !== false && $_POST['stock_quantity'] >= 0 // Stock required, integer, non-negative
   )
{
    // --- Get and Sanitize Input Data ---
    $name = trim($_POST['name']);
    // Barcode is optional, store null if empty/whitespace
    $barcode = (isset($_POST['barcode']) && trim($_POST['barcode']) !== '') ? trim($_POST['barcode']) : null;
    $price = (float)$_POST['price'];
    $cost_price = (float)$_POST['cost_price']; // Get cost price
    $stock_quantity = (int)$_POST['stock_quantity'];
    // Optional fields: Category, Image URL, Quick Key flag
    $category_id = (isset($_POST['category_id']) && !empty($_POST['category_id'])) ? (int)$_POST['category_id'] : null;
    $image_url = (isset($_POST['image_url']) && trim($_POST['image_url']) !== '') ? trim($_POST['image_url']) : null;
    $is_quick_key = isset($_POST['is_quick_key']) ? 1 : 0; // 1 if checkbox is checked, 0 otherwise

    // --- Database Interaction ---
    try {
        // Prepare the INSERT statement including the cost_price field
        $sql = "INSERT INTO products (name, barcode, price, cost_price, stock_quantity, category_id, image_url, is_quick_key)
                VALUES (:name, :barcode, :price, :cost_price, :stock_quantity, :category_id, :image_url, :is_quick_key)";
        $stmt = $pdo->prepare($sql);

        // Bind all parameters
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':barcode', $barcode, ($barcode === null) ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':cost_price', $cost_price); // Bind cost price
        $stmt->bindParam(':stock_quantity', $stock_quantity, PDO::PARAM_INT);
        $stmt->bindParam(':category_id', $category_id, ($category_id === null) ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':image_url', $image_url, ($image_url === null) ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':is_quick_key', $is_quick_key, PDO::PARAM_INT);

        // Execute the prepared statement
        $stmt->execute();

        // --- Set Success Feedback ---
        $_SESSION['message'] = "Product '" . htmlspecialchars($name) . "' added successfully!";
        $_SESSION['message_type'] = 'success';
        // Redirect to the product list page after successful addition
        header("Location: ../../public/admin_products.php");
        exit;

    } catch (PDOException $e) {
        // --- Handle Database Errors ---
        if ($e->errorInfo[1] == 1062) { // Check for duplicate entry error (MySQL code 1062)
             // Be specific about what might be duplicate (usually barcode)
             $_SESSION['message'] = "Error: Barcode '" . htmlspecialchars($barcode) . "' already exists. Please use a unique barcode.";
        } else {
             // Log the detailed error for developers
             error_log("Admin Add Product PDOException: " . $e->getMessage());
             // Provide a generic error message to the user
             $_SESSION['message'] = "A database error occurred while adding the product. Please try again.";
        }
         $_SESSION['message_type'] = 'error';
         // Redirect back to the add form on error so user can correct input
         header("Location: ../../public/add_product.php"); // Or admin_edit_product.php?action=add
         exit;
    }

} else {
    // --- Handle Invalid Input / Failed Validation ---
    $_SESSION['message'] = "Invalid input provided. Please check all required fields (Name, Price, Cost Price, Stock).";
    $_SESSION['message_type'] = 'error';
    // Redirect back to the add form
    header("Location: ../../public/add_product.php"); // Or admin_edit_product.php?action=add
    exit;
}

// Fallback redirect (should not be reached if logic is correct)
header("Location: ../../public/admin_products.php");
exit();
?>