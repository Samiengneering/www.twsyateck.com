<?php
// src/actions/process_delete_product.php
session_start(); // Start session for messages

// --- >>> CORRECTED PATHS <<< ---
require_once '../includes/check_admin.php'; // Go up one level, then into includes
require_once '../config/database.php';      // Go up one level, then into config
// --- >>> END CORRECTION <<< ---

$productId = $_GET['id'] ?? null;

// Validate ID
if (!$productId || !filter_var($productId, FILTER_VALIDATE_INT)) {
     $_SESSION['message'] = "Invalid Product ID for deletion.";
     $_SESSION['message_type'] = 'error';
     header("Location: ../../public/admin_products.php");
     exit;
}
$productId = (int)$productId;

try {
    // The FOREIGN KEY constraint on `sale_items` is ON DELETE RESTRICT.
    // This means the DELETE below WILL FAIL if any sale_items record points to this product.
    // The catch block handles this specific error.

    $sql = "DELETE FROM products WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $_SESSION['message'] = "Product deleted successfully.";
        $_SESSION['message_type'] = 'success';
    } else {
         $_SESSION['message'] = "Product not found or already deleted.";
         $_SESSION['message_type'] = 'info';
    }

} catch (PDOException $e) {
     error_log("Admin Delete Product Error (ID: $productId): " . $e->getMessage());
     // Specifically check for foreign key constraint violation error (code 1451 for MySQL)
     if ($e->errorInfo[1] == 1451 || strpos(strtolower($e->getMessage()), 'foreign key constraint') !== false) {
          $_SESSION['message'] = "Cannot delete product: It is linked to existing sales records. You might need to delete related sales first (or change database constraints - not recommended).";
     } else {
          $_SESSION['message'] = "A database error occurred while deleting the product.";
     }
     $_SESSION['message_type'] = 'error';
}

// Redirect back to the product list page regardless of outcome
header("Location: ../../public/admin_products.php");
exit();
?>