<?php
// src/actions/process_login.php
// WARNING: CONTAINS HARDCODED ADMIN LOGIN - EXTREMELY INSECURE - FOR TEMPORARY DEBUGGING/SETUP ONLY
// REMOVE OR REPLACE WITH SECURE DATABASE LOGIN IMMEDIATELY AFTER USE!

session_start(); // Start session FIRST
require_once '../config/database.php'; // Include DB config ($pdo)

// --- >>> TEMPORARY HARDCODED ADMIN LOGIN <<< ---
// Define the credentials that will bypass the database check
$hardcodedAdminUsername = 'admin'; // Choose a temporary username
$hardcodedAdminPassword = 'securepassword123'; // Choose a temporary password
// Define the details for this temporary admin session
$hardcodedAdminFullName = 'Temporary Admin';
$hardcodedAdminRole = 'manager'; // Ensure this user has admin rights
$hardcodedAdminId = 9999; // Use an ID unlikely to conflict with real users
// --- >>> END HARDCODED SECTION <<< ---


// Check if the form was submitted via POST and required fields are present
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {

    $submittedUsername = trim($_POST['username']);
    $submittedPassword = $_POST['password']; // Get the password user typed

    // --- 1. Check Hardcoded Admin Credentials FIRST ---
    if ($submittedUsername === $hardcodedAdminUsername && $submittedPassword === $hardcodedAdminPassword) {
        // If credentials match the hardcoded ones:
        session_regenerate_id(true); // Improve session security
        // Set session variables using the hardcoded details
        $_SESSION['user_id'] = $hardcodedAdminId;
        $_SESSION['username'] = $hardcodedAdminUsername;
        $_SESSION['full_name'] = $hardcodedAdminFullName;
        $_SESSION['role'] = $hardcodedAdminRole;
        $_SESSION['login_time'] = time();
        $_SESSION['is_hardcoded_login'] = true; // Optional flag to indicate temporary login

        header('Location: ../../public/index.php'); // Redirect to main menu on success
        exit; // Stop script execution
    }
    // --- End Hardcoded Check ---

    // --- 2. Proceed with Database Check for OTHER users ---
    // If the credentials didn't match the hardcoded admin, try the database.
    // (This section uses PLAIN TEXT comparison based on previous modifications - still insecure for DB users!)

    // Basic validation for DB check
    if (empty($submittedUsername) || empty($submittedPassword)) {
        $_SESSION['login_error'] = 'Username and password are required.';
        header('Location: ../../public/login.php');
        exit;
    }

    try {
        // Fetch user data from DB based on submitted username
        $sql = "SELECT id, password, full_name, role FROM users WHERE username = :username";
        $stmt = $pdo->prepare($sql);
        // Use the submitted username for the query parameter
        $stmt->bindParam(':username', $submittedUsername, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(); // Fetch user data

        // INSECURE: Direct comparison with plain text password from DB
        if ($user && $submittedPassword === $user['password']) {
            // Database user match (plain text)
            session_regenerate_id(true);
            // Set session variables from the database record
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username']; // Use username from DB for consistency
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            unset($_SESSION['is_hardcoded_login']); // Ensure temporary login flag is removed

            header('Location: ../../public/index.php'); // Redirect on success
            exit;
        }
        // SECURE Check (Keep commented out unless you switch DB back to hashes)
        // elseif ($user && password_verify($submittedPassword, $user['password'])) {
        //    // Database user match (hashed password)
        //    session_regenerate_id(true);
        //    $_SESSION['user_id'] = $user['id']; // etc...
        //    header('Location: ../../public/index.php');
        //    exit;
        // }
        else {
            // Database check failed (user not found or plain text password mismatch)
            $_SESSION['login_error'] = 'Invalid username or password.';
            header('Location: ../../public/login.php');
            exit;
        }

    } catch (PDOException $e) {
        // Handle database errors during the lookup
        error_log("Login PDOException: " . $e->getMessage());
        $_SESSION['login_error'] = 'Database error during login. Please try again later.';
        header('Location: ../../public/login.php');
        exit;
    }
    // --- End Database Check ---

} else {
    // Handle cases where the script is accessed directly or without required POST data
    $_SESSION['login_error'] = 'Invalid login request.';
    header('Location: ../../public/login.php');
    exit;
}
?>