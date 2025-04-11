<?php
require_once '../src/includes/check_admin.php'; // Secure
require_once '../src/config/database.php'; // $pdo

$products = [];
$fetchError = null;
try {
    // Fetch products with category names
    $sql = "SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            ORDER BY p.name ASC";
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Admin Fetch Products Error: " . $e->getMessage());
    $fetchError = "Could not load products.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <style> .action-links a { margin-right: 10px; } </style>
</head>
<body>
    <?php include '../src/includes/admin_header.php'; // Optional: Reuse header ?>
    <h1>Manage Products</h1>

    <p><a href="add_product.php" class="button-like">Add New Product</a></p> <!-- Link to existing add page -->

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
                    <th>Name</th>
                    <th>Category</th>
                    <th>Barcode</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Quick Key?</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="8" class="no-data-message">No products found.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['id']); ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($product['barcode'] ?? 'N/A'); ?></td>
                        <td style="text-align: right;">$<?php echo number_format($product['price'], 2); ?></td>
                        <td style="text-align: center;"><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
                        <td style="text-align: center;"><?php echo $product['is_quick_key'] ? 'Yes' : 'No'; ?></td>
                        <td class="action-links">
                            <a href="admin_edit_product.php?id=<?php echo $product['id']; ?>">Edit</a>
                            <a href="../src/actions/process_delete_product.php?id=<?php echo $product['id']; ?>"
                               onclick="return confirm('Are you sure you want to delete this product? This cannot be undone.');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p><a href="admin.php">Back to Admin Dashboard</a></p>

</body>
</html>