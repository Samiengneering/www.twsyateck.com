<?php
session_start();
// If already logged in, redirect to main menu or checkout
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']); // Clear error after displaying
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TwsyaTech POS</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #e9ecef; }
        .login-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .login-container h1 { text-align: center; margin-bottom: 25px; color: #0056b3; border-bottom: none;}
        .login-container button { width: 100%; background-color: #007bff; }
        .login-container button:hover { background-color: #0056b3; }
        .error-message { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center;}
    </style>
</head>
<body>
    <div class="login-container">
        <h1>TwsyaTech POS Login</h1>
        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form action="../src/actions/process_login.php" method="POST">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <button type="submit">Login</button>
            </div>
        </form>
    </div>
</body>
</html>