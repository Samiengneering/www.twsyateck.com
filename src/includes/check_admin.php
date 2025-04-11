<?php
// src/includes/check_admin.php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Start session if not already started
}

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../public/login.php'); // Redirect to login if not logged in
    exit;
}

// 2. Check if user has the required role (e.g., 'manager')
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    // Optionally redirect to a 'permission denied' page or back to index
    // For simplicity, we'll just stop execution with a message.
    die("Access Denied: You do not have permission to view this page.");
}

// If checks pass, the script including this file can continue.
// We can also make user info available
$adminUserId = $_SESSION['user_id'];
$adminFullName = $_SESSION['full_name'] ?? 'Admin';
?>