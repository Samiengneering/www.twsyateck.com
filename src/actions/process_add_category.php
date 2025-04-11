<?php
// src/actions/process_add_category.php
session_start();
require_once '../includes/check_admin.php'; // Secure
require_once '../config/database.php'; // $pdo

// Validate POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['name']) && !empty(trim($_POST['name']))
   )
{
    $name = trim($_POST['name']);
    // Set display order, default to 0 if not provided or invalid
    $displayOrder = (isset($_POST['display_order']) && filter_var($_POST['display_order'], FILTER_VALIDATE_INT) !== false)
                    ? (int)$_POST['display_order'] : 0;

    try {
        // Check if category name already exists (case-insensitive check might be better depending on DB collation)
        $checkSql = "SELECT id FROM categories WHERE name = :name";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindParam(':name', $name, PDO::PARAM_STR);
        $checkStmt->execute();

        if ($checkStmt->fetch()) {
            $_SESSION['message'] = "Error: Category name '" . htmlspecialchars($name) . "' already exists.";
            $_SESSION['message_type'] = 'error';
            header("Location: ../../public/admin_edit_category.php?action=add"); // Redirect back to add form
            exit;
        } else {
            // Name is unique, proceed with insert
            $sql = "INSERT INTO categories (name, display_order) VALUES (:name, :display_order)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':display_order', $displayOrder, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['message'] = "Category '" . htmlspecialchars($name) . "' added successfully!";
            $_SESSION['message_type'] = 'success';
            header("Location: ../../public/admin_categories.php"); // Redirect to list on success
            exit;
        }

    } catch (PDOException $e) {
        error_log("Admin Add Category Error: " . $e->getMessage());
        $_SESSION['message'] = "A database error occurred while adding the category.";
        $_SESSION['message_type'] = 'error';
        header("Location: ../../public/admin_edit_category.php?action=add"); // Redirect back on DB error
        exit;
    }
} else {
    $_SESSION['message'] = "Invalid input provided. Category name is required.";
    $_SESSION['message_type'] = 'error';
    header("Location: ../../public/admin_edit_category.php?action=add"); // Redirect back on validation failure
    exit;
}
?>