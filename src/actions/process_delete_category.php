<?php
// src/actions/process_delete_category.php
session_start();
require_once '../includes/check_admin.php'; // Secure
require_once '../config/database.php'; // $pdo

$categoryId = $_GET['id'] ?? null;

// Validate ID
if (!$categoryId || !filter_var($categoryId, FILTER_VALIDATE_INT)) {
     $_SESSION['message'] = "Invalid Category ID for deletion.";
     $_SESSION['message_type'] = 'error';
     header("Location: ../../public/admin_categories.php");
     exit;
}
$categoryId = (int)$categoryId;

try {
    // IMPORTANT SAFETY CHECK: Check if any products are using this category
    // We will SET product.category_id to NULL because the FOREIGN KEY is ON DELETE SET NULL
    // If it was ON DELETE RESTRICT, we would check first and prevent deletion.

    // Optional: Add a specific check if you want to warn even with SET NULL
    // $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
    // $checkStmt->execute([':id' => $categoryId]);
    // $productCount = $checkStmt->fetchColumn();
    // if ($productCount > 0) {
    //     $_SESSION['message'] = "Warning: Deleting category used by $productCount product(s). Their category will be unset.";
    //     $_SESSION['message_type'] = 'info'; // Use info for warning
    // }

    // Proceed with deletion
    $sql = "DELETE FROM categories WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $categoryId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // Combine potential warning with success message
        $successMsg = "Category deleted successfully.";
        $_SESSION['message'] = isset($_SESSION['message']) ? $_SESSION['message'] . " " . $successMsg : $successMsg;
        $_SESSION['message_type'] = isset($_SESSION['message_type']) && $_SESSION['message_type'] == 'info' ? 'info' : 'success';
    } else {
         $_SESSION['message'] = "Category not found or already deleted.";
         $_SESSION['message_type'] = 'info'; // Use info if not found
    }

} catch (PDOException $e) {
     error_log("Admin Delete Category Error: " . $e->getMessage());
     $_SESSION['message'] = "A database error occurred while deleting the category.";
     $_SESSION['message_type'] = 'error';
}

header("Location: ../../public/admin_categories.php");
exit();
?>