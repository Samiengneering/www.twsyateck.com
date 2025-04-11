<?php
// src/config/database.php

// --- Database Credentials ---
// Best practice: Store these outside the web root or in environment variables in production
$dbHost = 'localhost';        // Usually correct for local XAMPP/WAMP/MAMP
$dbName = 'twsyatech_market'; // The name of your database
$dbUser = 'root';             // Default username for XAMPP/WAMP/MAMP
$dbPass = '';                 // Default password for XAMPP/WAMP/MAMP (often empty)
                              // *** CHANGE THIS for production or if you set a password ***

// --- PDO Connection Options ---
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on SQL errors (good for try/catch)
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Fetch results as associative arrays by default
    PDO::ATTR_EMULATE_PREPARES   => false,                // Use native prepared statements (more secure)
];

// --- Establish Connection ---
try {
    // Data Source Name (DSN) specifying driver, host, dbname, and charset
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

    // Create a new PDO instance (database connection object)
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);

} catch (PDOException $e) {
    // --- Error Handling ---
    // Log the detailed error to the server's error log (don't show details to users)
    error_log("Database Connection Error: " . $e->getMessage() . " | Code: " . $e->getCode());

    // Display a generic, user-friendly error message and stop script execution
    // In a real application, you might show a nicer error page.
    die("Database connection failed. Unable to proceed. Please contact support if the problem persists.");
}

// If the script reaches here without dying, the $pdo object is successfully created
// and available for use in any script that includes this file via require_once.
?>