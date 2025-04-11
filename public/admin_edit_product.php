<?php
require_once '../src/includes/check_admin.php'; // Secure
require_once '../src/config/database.php'; // $pdo

$productId = $_GET['id'] ?? null;
$product = null;
$categories = [];
$fetchError = null;

if (!$productId || !filter_var($productId, FILTER_VALIDATE_INT)) {
    // Instead of die, set session message and redirect for better UX
    $_SESSION['message'] = "Invalid Product ID provided.";
    $_SESSION['message_type'] = 'error';
    header('Location: admin_products.php');
    exit;
}

try {
    // Fetch product data
    $stmtProd = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $stmtProd->bindParam(':id', $productId, PDO::PARAM_INT);
    $stmtProd->execute();
    $product = $stmtProd->fetch();

    if (!$product) {
        $_SESSION['message'] = "Product with ID $productId not found.";
        $_SESSION['message_type'] = 'error';
        header('Location: admin_products.php');
        exit;
    }

    // Fetch categories for dropdown
    $stmtCat = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmtCat->fetchAll();

} catch (PDOException $e) {
    error_log("Admin Edit Product Fetch Error: " . $e->getMessage());
    $fetchError = "Could not load product data or categories.";
    // Optionally set session message here too before rendering
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Admin</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include '../src/includes/admin_header.php'; // Include the admin header ?>
    <h1>Edit Product: <?php echo htmlspecialchars($product['name'] ?? 'N/A'); ?></h1>

    <?php if (isset($_SESSION['message'])): // Display feedback messages from redirects ?>
        <p class="feedback-message message-<?php echo $_SESSION['message_type'] ?? 'info'; ?>">
            <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </p>
    <?php endif; ?>

    <?php if ($fetchError): ?>
        <p class="message-error"><?php echo htmlspecialchars($fetchError); ?></p>
    <?php elseif ($product): ?>
        <form action="../src/actions/process_edit_product.php" method="POST">
            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId); ?>">

            <div>
                <label for="name">Product Name: <span class="required">*</span></label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
            </div>
            <div>
                <label for="barcode">Barcode (Unique):</label>
                <input type="text" id="barcode" name="barcode" value="<?php echo htmlspecialchars($product['barcode'] ?? ''); ?>">
            </div>
             <div>
                <label for="price">Selling Price: <span class="required">*</span></label>
                <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($product['price']); ?>" required>
            </div>
            <!-- ======================================= -->
            <!-- >>> MOVED Cost Price Input HERE <<< -->
            <div>
                <label for="cost_price">Cost Price (Unit Cost): <span class="required">*</span></label>
                <input type="number" id="cost_price" name="cost_price" step="0.01" min="0"
                       value="<?php echo htmlspecialchars(number_format($product['cost_price'] ?? 0.00, 2, '.', '')); ?>" required>
                 <small>The price you paid for one unit of this item.</small>
            </div>
            <!-- ======================================= -->
            <div>
                <label for="stock">Stock Quantity: <span class="required">*</span></label>
                <input type="number" id="stock" name="stock_quantity" step="1" min="0" value="<?php echo htmlspecialchars($product['stock_quantity']); ?>" required>
            </div>
             <div>
                <label for="category_id">Category:</label>
                <select id="category_id" name="category_id">
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo ($product['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div>
                 <label for="image_url">Image Filename (e.g., apple.jpg):</label>
                 <input type="text" id="image_url" name="image_url" value="<?php echo htmlspecialchars($product['image_url'] ?? ''); ?>" placeholder="Place file in public/images/products/">
             </div>
              <div>
                <label for="is_quick_key">
                    <input type="checkbox" id="is_quick_key" name="is_quick_key" value="1" <?php echo ($product['is_quick_key'] ? 'checked' : ''); ?>>
                    Show as Quick Key Button?
                </label>
            </div>
            <div>
                <button type="submit">Update Product</button>
                <a href="admin_products.php" style="margin-left: 15px;">Cancel</a>
            </div>
        </form> <!-- Form ends here -->
    <?php endif; ?>

    <!-- The cost price div was incorrectly placed after the form, it has been moved inside -->

</body>
</html>