<?php
require_once '../src/includes/check_admin.php'; // Secure
require_once '../src/config/database.php'; // $pdo

// Determine mode (add or edit)
$mode = $_GET['action'] ?? 'add'; // Default to 'add'
$categoryId = null;
$category = ['name' => '', 'display_order' => 0]; // Default values for add mode
$pageTitle = "Add New Category";
$formAction = "../src/actions/process_add_category.php";
$submitButtonText = "Add Category";

if ($mode === 'edit') {
    $categoryId = $_GET['id'] ?? null;
    if (!$categoryId || !filter_var($categoryId, FILTER_VALIDATE_INT)) {
        $_SESSION['message'] = "Invalid Category ID for editing.";
        $_SESSION['message_type'] = 'error';
        header('Location: admin_categories.php');
        exit;
    }
    $categoryId = (int)$categoryId;

    try {
        $stmt = $pdo->prepare("SELECT id, name, display_order FROM categories WHERE id = :id");
        $stmt->bindParam(':id', $categoryId, PDO::PARAM_INT);
        $stmt->execute();
        $categoryData = $stmt->fetch();

        if (!$categoryData) {
            $_SESSION['message'] = "Category not found.";
            $_SESSION['message_type'] = 'error';
            header('Location: admin_categories.php');
            exit;
        }
        $category = $categoryData; // Overwrite defaults with fetched data
        $pageTitle = "Edit Category: " . htmlspecialchars($category['name']);
        $formAction = "../src/actions/process_edit_category.php";
        $submitButtonText = "Update Category";

    } catch (PDOException $e) {
        error_log("Admin Edit Category Fetch Error: " . $e->getMessage());
        $_SESSION['message'] = "Could not load category data for editing.";
        $_SESSION['message_type'] = 'error';
        header('Location: admin_categories.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include '../src/includes/admin_header.php'; ?>
    <h1><?php echo $pageTitle; ?></h1>

    <?php if (isset($_SESSION['message'])): // Display feedback messages ?>
        <p class="feedback-message message-<?php echo $_SESSION['message_type'] ?? 'info'; ?>">
            <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </p>
    <?php endif; ?>

    <form action="<?php echo $formAction; ?>" method="POST">
        <?php if ($mode === 'edit'): ?>
            <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($categoryId); ?>">
        <?php endif; ?>

        <div>
            <label for="name">Category Name: <span class="required">*</span></label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($category['name']); ?>" required>
            <small>Name must be unique.</small>
        </div>

        <div>
            <label for="display_order">Display Order:</label>
            <input type="number" id="display_order" name="display_order" value="<?php echo htmlspecialchars($category['display_order']); ?>" step="1">
            <small>Lower numbers appear first in lists.</small>
        </div>

        <div>
            <button type="submit"><?php echo $submitButtonText; ?></button>
            <a href="admin_categories.php" style="margin-left: 15px;">Cancel</a>
        </div>
    </form>

</body>
</html>