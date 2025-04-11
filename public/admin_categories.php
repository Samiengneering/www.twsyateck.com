<?php
require_once '../src/includes/check_admin.php'; // Secure the page
require_once '../src/config/database.php'; // $pdo

$categories = [];
$fetchError = null;
try {
    // Fetch all categories, order by display_order, then name
    $sql = "SELECT id, name, display_order FROM categories ORDER BY display_order ASC, name ASC";
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Admin Fetch Categories Error: " . $e->getMessage());
    $fetchError = "Could not load category data.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .action-links a { margin-right: 10px; font-size: 0.9em; }
        .add-button { margin-bottom: 15px; display: inline-block; }
        th.display-order, td.display-order { text-align: center; width: 15%;}
    </style>
</head>
<body>
    <?php include '../src/includes/admin_header.php'; // Include the admin header ?>
    <h1>Manage Categories</h1>

    <p><a href="admin_edit_category.php?action=add" class="button-like add-button">Add New Category</a></p>

    <?php if (isset($_SESSION['message'])): // Display feedback messages ?>
        <p class="feedback-message message-<?php echo $_SESSION['message_type'] ?? 'info'; ?>">
            <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </p>
    <?php endif; ?>

    <?php if ($fetchError): ?>
        <p class="message-error"><?php echo htmlspecialchars($fetchError); ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th class="display-order">Display Order</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="4" class="no-data-message">No categories found.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($category['id']); ?></td>
                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                        <td class="display-order"><?php echo htmlspecialchars($category['display_order']); ?></td>
                        <td class="action-links">
                            <a href="admin_edit_category.php?action=edit&id=<?php echo $category['id']; ?>">Edit</a>
                            <a href="../src/actions/process_delete_category.php?id=<?php echo $category['id']; ?>"
                               class="delete-link"
                               onclick="return confirm('Are you sure you want to delete category \'<?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?>\'? Products using it will become uncategorized.');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p style="margin-top: 20px;"><a href="admin.php">Back to Admin Dashboard</a></p>

</body>
</html>