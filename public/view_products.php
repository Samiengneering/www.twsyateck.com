<?php require_once '../src/config/database.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Products - TwsyaTech POS</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Product List</h1>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Category</th>
                <th>Barcode</th>
                <th>Price ($)</th>
                <th>Stock</th>
                <th>Quick Key?</th>
                <th>Image File</th>
                <th>Actions</th> <!-- Added for Edit/Delete later -->
            </tr>
        </thead>
        <tbody>
            <?php
            try {
                // Join with categories to display name
                $sql = "SELECT p.*, c.name as category_name
                        FROM products p
                        LEFT JOIN categories c ON p.category_id = c.id
                        ORDER BY p.name ASC";
                $stmt = $pdo->query($sql);

                if ($stmt->rowCount() > 0) {
                    while ($row = $stmt->fetch()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['category_name'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($row['barcode'] ?? 'N/A') . "</td>";
                        echo "<td style='text-align: right;'>" . number_format($row['price'], 2) . "</td>";
                        echo "<td style='text-align: center;'>" . htmlspecialchars($row['stock_quantity']) . "</td>";
                        echo "<td style='text-align: center;'>" . ($row['is_quick_key'] ? 'Yes' : 'No') . "</td>";
                        echo "<td>" . htmlspecialchars($row['image_url'] ?? 'N/A') . "</td>";
                         echo "<td>";
                         // Add Edit/Delete links here later, e.g.:
                         // echo "<a href='edit_product.php?id=" . $row['id'] . "'>Edit</a> ";
                         // echo "<a href='../src/actions/delete_product.php?id=" . $row['id'] . "' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
                         echo "N/A";
                         echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='9' style='text-align: center;'>No products found. <a href='add_product.php'>Add one?</a></td></tr>";
                }
            } catch (PDOException $e) {
                error_log("View Products Error: " . $e->getMessage());
                echo "<tr><td colspan='9' class='message-error' style='text-align: center;'>Error fetching products. Please try again later.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <p><a href="index.php">Back to Main Menu</a></p>

</body>
</html>