<?php
// src/actions/process_edit_category.php
session_start();
require_once '../includes/check_admin.php'; // Secure
require_once '../config/database.php'; // $pdo

// Validate POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['category_id']) && filter_var($_POST['category_id'], FILTER_VALIDATE_INT) &&
    isset($_POST['name']) && !empty(trim($_POST['name']))
   )
{
    $categoryId = (int)$_POST['category_id'];
    $name = trim($_POST['name']);
    $displayOrder = (isset($_POST['display_order']) && filter_var($_POST['display_order'], FILTER_VALIDATE_INT) !== false)
                    ? (int)$_POST['display_order'] : 0;

    try {
        // Check if the new name already exists for a *different* category
        $checkSql = "SELECT id FROM categories WHERE name = :name AND id != :current_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindParam(':name', $name, PDO::PARAM_STR);
        $checkStmt->bindParam(':current_id', $categoryId, PDO::PARAM_INT);
        $checkStmt->execute();

        if ($checkStmt->fetch()) {
            $_SESSION['message'] = "Error: Category name '" . htmlspecialchars($name) . "' is already used by another category.";
            $_SESSION['message_type'] = 'error';
            header("Location: ../../public/admin_edit_category.php?action=edit&id=" . $categoryId); // Redirect back to edit form
            exit;
        } else {
            // Name is unique (or unchanged), proceed with update
            $sql = "UPDATE categories SET name = :name, display_order = :display_order WHERE id = :category_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':display_order', $displayOrder, PDO::PARAM_INT);
            $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['message'] = "Category '" . htmlspecialchars($name) . "' updated successfully!";
            $_SESSION['message_type'] = 'success';
            header("Location: ../../public/admin_categories.php"); // Redirect to list on success
            exit;
        }

    } catch (PDOException $e) {
        error_log("Admin Edit Category Error: " . $e->getMessage());
        $_SESSION['message'] = "A database error occurred while updating the category.";
        $_SESSION['message_type'] = 'error';
        header("Location: ../../public/admin_edit_category.php?action=edit&id=" . $categoryId); // Redirect back on DB error
        exit;
    }
} else {
    $_SESSION['message'] = "Invalid input provided for update.";
    $_SESSION['message_type'] = 'error';
    // Redirect back to list if ID missing, else back to edit form
    $categoryId = $_POST['category_id'] ?? null;
    if ($categoryId && filter_var($categoryId, FILTER_VALIDATE_INT)) {
        header("Location: ../../public/admin_edit_category.php?action=edit&id=" . $categoryId);
    } else {
        header("Location: ../../public/admin_categories.php");
    }
    exit;
}
?>