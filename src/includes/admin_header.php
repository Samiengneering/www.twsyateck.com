<?php
// src/includes/admin_header.php
// Assumes check_admin.php was included before this

// Get current script name to highlight active link (optional)
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
    .admin-main-header { background-color: #343a40; padding: 10px 20px; margin-bottom: 20px; color: #fff; }
    .admin-main-header a { color: #f8f9fa; text-decoration: none; margin-right: 15px; }
    .admin-main-header a:hover, .admin-main-header a.active { color: #007bff; text-decoration: underline; }
    .admin-main-header .user-info { float: right; }
    .admin-main-header .user-info a { color: #ffc107; } /* Logout link color */
</style>
<header class="admin-main-header">
    <nav>
        <a href="admin.php" <?php echo ($currentPage == 'admin.php' ? 'class="active"' : ''); ?>>Dashboard</a> |
        <a href="admin_products.php" <?php echo (strpos($currentPage, 'product') !== false ? 'class="active"' : ''); ?>>Products</a> |
        <a href="admin_users.php" <?php echo (strpos($currentPage, 'user') !== false ? 'class="active"' : ''); ?>>Users</a> |
        <a href="admin_categories.php" <?php echo (strpos($currentPage, 'categor') !== false ? 'class="active"' : ''); ?>>Categories</a> |
        <a href="reports.php" <?php echo ($currentPage == 'reports.php' ? 'class="active"' : ''); ?>>Reports</a> |
        <a href="index.php">POS</a>
    </nav>
    <div class="user-info">
        Logged in as: <?php echo htmlspecialchars($adminFullName); ?>
        <a href="../src/actions/logout.php">(Logout)</a>
    </div>
    <div style="clear: both;"></div> <!-- Clear float -->
</header>