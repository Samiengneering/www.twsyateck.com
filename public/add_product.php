<?php
// This page serves as the form for ADDING new products.
// Edit functionality is handled by admin_edit_product.php

session_start(); // Start session for messages
require_once '../src/config/database.php'; // Need $pdo to fetch categories
// We assume adding products is an admin function, check permissions
require_once '../src/includes/check_admin.php'; // Secure the page

// Fetch categories for the dropdown
$categories = [];
$fetchError = null;
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Category Fetch Error (Add Product): " . $e->getMessage());
    $fetchError = 'Error loading categories.'; // Store error to display later
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product - Admin</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include '../src/includes/admin_header.php'; // Include the admin header ?>
    <h1>Add New Product</h1>

    <?php
    // Display feedback messages if redirected back here after submission attempt
    if (isset($_SESSION['message'])):
    ?>
        <p class="feedback-message message-<?php echo $_SESSION['message_type'] ?? 'info'; ?>">
            <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </p>
    <?php
    endif;
    // Display category fetch error if it occurred
    if ($fetchError):
    ?>
         <p class="message-error"><?php echo htmlspecialchars($fetchError); ?></p>
    <?php endif; ?>


    <form action="../src/actions/process_add_product.php" method="POST">
       <!-- Note: No enctype needed unless you implement actual file uploads -->

        <div>
            <label for="name">Product Name: <span class="required">*</span></label>
            <input type="text" id="name" name="name" value="" required> <!-- Value is empty for add mode -->
        </div>
        <div>
            <label for="barcode">Barcode (Unique):</label>
            <input type="text" id="barcode" name="barcode" value=""> <!-- Value is empty for add mode -->
        </div>
         <div>
            <label for="price">Selling Price: <span class="required">*</span></label>
            <input type="number" id="price" name="price" step="0.01" min="0" value="0.00" required> <!-- Default value -->
        </div>
         <!-- ======================================= -->
         <!-- >>> THIS IS THE REQUIRED COST PRICE FIELD <<< -->
         <div>
             <label for="cost_price">Cost Price (Unit Cost): <span class="required">*</span></label>
             <input type="number" id="cost_price" name="cost_price" step="0.01" min="0" value="0.00" required> <!-- Ensure name="cost_price" and required -->
              <small>The price you paid for one unit of this item.</small>
         </div>
         <!-- ======================================= -->
        <div>
            <label for="stock">Stock Quantity: <span class="required">*</span></label>
            <input type="number" id="stock" name="stock_quantity" step="1" min="0" value="0" required> <!-- Default value -->
        </div>
         <div>
            <label for="category_id">Category:</label>
            <select id="category_id" name="category_id">
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category['id']); ?>">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endforeach; ?>
                <?php if(empty($categories) && !$fetchError): ?>
                    <option value="" disabled>No categories found</option>
                <?php endif; ?>
            </select>
        </div>
         <div>
             <label for="image_url">Image Filename (e.g., apple.jpg):</label>
             <input type="text" id="image_url" name="image_url" placeholder="Place file in public/images/products/">
         </div>
          <div>
            <label>
                <input type="checkbox" id="is_quick_key" name="is_quick_key" value="1"> <!-- Value is 1 when checked -->
                Show as Quick Key Button?
            </label>
        </div>
        <div>
            <button type="submit">Add Product</button>
            <a href="admin_products.php" style="margin-left: 15px;">Cancel</a> <!-- Link back to product list -->
        </div>
    </form>

</body>
</html>