<?php require_once '../src/includes/check_admin.php'; // Secure the page ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TwsyaTech POS</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .admin-dashboard { max-width: 900px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .admin-dashboard h1 { text-align: center; color: #0056b3; margin-bottom: 30px; }
        .admin-nav ul { list-style: none; padding: 0; display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; }
        .admin-nav ul li a { display: block; padding: 15px 25px; background-color: #007bff; color: white; border-radius: 5px; text-decoration: none; font-weight: 500; transition: background-color 0.2s; text-align: center; }
        .admin-nav ul li a:hover { background-color: #0056b3; }
        .admin-welcome { text-align: right; margin-bottom: 10px; color: #555; }
        .admin-welcome a { color: #dc3545; margin-left: 10px; }
    </style>
</head>
<body>
    <div class="admin-dashboard">
        <div class="admin-welcome">
            Welcome, <?php echo htmlspecialchars($adminFullName); ?>!
            <a href="../src/actions/logout.php">Logout</a>
        </div>
        <h1>Admin Control Panel</h1>

        <nav class="admin-nav">
            <ul>
                <li><a href="admin_products.php">Manage Products</a></li>
                <li><a href="admin_users.php">Manage Users</a></li>
                <li><a href="admin_categories.php">Manage Categories</a></li>
                <li><a href="reports.php">View Sales Reports</a></li>
                <li><a href="index.php">Main POS Menu</a></li>
                <!-- Add more links as needed (e.g., Settings) -->
            </ul>
        </nav>

        <!-- Optional: Add dashboard widgets/stats here later -->

    </div>
</body>
</html>