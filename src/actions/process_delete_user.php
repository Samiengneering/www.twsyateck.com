<?php
// src/actions/process_delete_user.php
session_start(); // Start session for messages and admin check

// --- >>> CORRECTED PATHS <<< ---
require_once '../includes/check_admin.php'; // Go up one level, then into includes
require_once '../config/database.php';      // Go up one level, then into config
// --- >>> END CORRECTION <<< ---

$userId = $_GET['id'] ?? null;

// Validate ID
if (!$userId || !filter_var($userId, FILTER_VALIDATE_INT)) {
     $_SESSION['message'] = "Invalid User ID for deletion.";
     $_SESSION['message_type'] = 'error';
     header("Location: ../../public/admin_users.php");
     exit;
}
$userId = (int)$userId;

// Prevent admin from deleting themselves (check against ID stored during check_admin include)
if ($userId === $adminUserId) {
    $_SESSION['message'] = "Error: You cannot delete your own administrator account.";
    $_SESSION['message_type'] = 'error';
    header("Location: ../../public/admin_users.php");
    exit;
}

try {
    // The FOREIGN KEY on `sales` table is `ON DELETE SET NULL`.
    // This means deleting a user will set `cashier_id` to NULL in related sales records.
    // This is generally acceptable behavior.

    $sql = "DELETE FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $_SESSION['message'] = "User deleted successfully.";
        $_SESSION['message_type'] = 'success';
    } else {
         $_SESSION['message'] = "User not found or already deleted.";
         $_SESSION['message_type'] = 'info'; // Use info if not found
    }

} catch (PDOException $e) {
     error_log("Admin Delete User Error (ID: $userId): " . $e->getMessage());
     // Check for foreign key constraint errors (though unlikely with ON DELETE SET NULL)
     // if ($e->errorInfo[1] == 1451) { ... }
     $_SESSION['message'] = "A database error occurred while deleting the user.";
     $_SESSION['message_type'] = 'error';
}

// Redirect back to the user list page regardless of outcome
header("Location: ../../public/admin_users.php");
exit();
?>