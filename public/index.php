<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$loggedInUserName = $_SESSION['full_name'] ?? 'N/A';
$isManager = (isset($_SESSION['role']) && $_SESSION['role'] === 'manager');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TwsyaTech POS - Main Menu</title>
    <link rel="stylesheet" href="css/style.css">
     <style> /* Basic styles for centering */
        body { display: flex; flex-direction: column; align-items: center; padding-top: 50px; }
        nav ul { list-style: none; padding: 0; display: flex; gap: 20px; }
        nav ul li a { display: block; padding: 15px 30px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; font-size: 1.1em; }
        nav ul li a:hover { background-color: #0056b3; }
        .admin-link a { background-color: #28a745; } /* Different color for admin */
        .admin-link a:hover { background-color: #218838; }
        .welcome-logout { margin-top: 30px; color: #555; }
        .welcome-logout a { color: #dc3545; font-weight: bold; margin-left: 15px;}
     </style>
</head>
<body>
    <h1>Welcome to TwsyaTech POS</h1>
    <nav>
        <ul>
            <li><a href="checkout.php">Start New Sale</a></li>
            <?php if ($isManager): ?>
                <li class="admin-link"><a href="admin.php">Admin Panel</a></li>
            <?php endif; ?>
        </ul>
    </nav>
     <div class="welcome-logout">
        Logged in as: <?php echo htmlspecialchars($loggedInUserName); ?>
        <a href="../src/actions/logout.php">Logout</a>
    </div>
</body>
</html>