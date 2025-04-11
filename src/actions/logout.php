<?php
session_start();
session_unset(); // Remove all session variables
session_destroy(); // Destroy the session
header('Location: ../../public/login.php?logged_out=1'); // Redirect to login page
exit;
?>