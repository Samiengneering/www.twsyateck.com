<?php
// src/actions/process_add_user.php
session_start(); // Start session for messages

// Correct paths for includes relative to the actions directory
require_once '../includes/check_admin.php'; // Secure - Ensure admin is logged in
require_once '../config/database.php';      // $pdo is available
require_once '../includes/upload_helper.php'; // Include the upload helper

// Define upload constants (using $_SERVER['DOCUMENT_ROOT'])
$projectSubdir = '/twsyatech_pos'; // Adjust if your project is not directly in htdocs/twsyatech_pos
define('PROFILE_UPLOAD_DIR', rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $projectSubdir . '/public/images/profiles/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2 MB limit
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']); // Allowed image types


// --- Form Data Validation ---
// Check method and presence/validity of required fields
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['username']) && !empty(trim($_POST['username'])) &&      // Username required
    isset($_POST['full_name']) && !empty(trim($_POST['full_name'])) &&    // Full Name required
    isset($_POST['role']) && in_array($_POST['role'], ['cashier', 'manager']) && // Role required and valid
    isset($_POST['password']) && !empty(trim($_POST['password']))         // Password required for NEW user
   )
{
    // --- Get and Sanitize Input Data ---
    $username = trim($_POST['username']);
    $fullName = trim($_POST['full_name']);
    $role = $_POST['role'];
    $plainPassword = trim($_POST['password']); // Get plain password
    $profileImageUrl = null; // Default profile image to null
    $uploadMessage = '';     // For feedback about upload status
    $uploadWasAttempted = false; // Flag if user selected a file

    // --- Handle File Upload ---
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadWasAttempted = true; // User tried to upload

        // Check if PROFILE_UPLOAD_DIR was defined correctly
        if (!defined('PROFILE_UPLOAD_DIR')) {
             $_SESSION['message'] = "Server configuration error: Upload directory path not set.";
             $_SESSION['message_type'] = 'error';
             header("Location: ../../public/admin_edit_user.php?action=add");
             exit;
        }

        $uploadResult = handleFileUpload($_FILES['profile_image'], PROFILE_UPLOAD_DIR, ALLOWED_MIME_TYPES, MAX_UPLOAD_SIZE);

        if ($uploadResult['success'] && $uploadResult['filename']) {
            $profileImageUrl = $uploadResult['filename']; // Store the new unique filename
        } elseif (!$uploadResult['success']) {
            // Upload failed, set error message and redirect back immediately
            $_SESSION['message'] = "User data not saved. Profile image upload failed: " . $uploadResult['message'];
            $_SESSION['message_type'] = 'error';
            header("Location: ../../public/admin_edit_user.php?action=add");
            exit;
        }
        // If success is true but filename is null, it means UPLOAD_ERR_NO_FILE, which is handled (no new image)
    }
    // --- End Handle File Upload ---


    // --- WARNING: Using Plain Text Password (INSECURE as requested) ---
    $dbPassword = $plainPassword;
    // --- SECURE METHOD (Commented out) ---
    // $dbPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
    // if ($dbPassword === false) { /* Handle hash failure */ }
    // --- End Secure Method ---


    // --- Database Interaction ---
    try {
        // 1. Check if username already exists
        $checkSql = "SELECT id FROM users WHERE username = :username";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':username' => $username]); // Use array execute

        if ($checkStmt->fetch()) {
            // Username exists, set error and redirect back
            $_SESSION['message'] = "Error: Username '" . htmlspecialchars($username) . "' already exists.";
            $_SESSION['message_type'] = 'error';
            header("Location: ../../public/admin_edit_user.php?action=add");
            exit;
        } else {
            // 2. Username is unique, proceed with insert (including profile image url)
            $sql = "INSERT INTO users (username, password, full_name, role, profile_image_url)
                    VALUES (:username, :password, :full_name, :role, :profile_image_url)"; // <<< Added profile_image_url
            $stmt = $pdo->prepare($sql);

            // Bind parameters using execute array
            $stmt->execute([
                ':username' => $username,
                ':password' => $dbPassword, // Plain or Hashed
                ':full_name' => $fullName,
                ':role' => $role,
                ':profile_image_url' => ($profileImageUrl === null) ? null : $profileImageUrl // Bind filename or NULL
            ]);

            // Set success message (append upload status)
            $finalMessage = "User '" . htmlspecialchars($username) . "' added successfully!";
            if ($uploadWasAttempted && !$profileImageUrl) { // Upload attempted but failed/no file
                $finalMessage .= $uploadMessage ?: " (Profile image processing issue.)";
                 $_SESSION['message_type'] = 'warning'; // Set as warning if user added but image failed
                 $_SESSION['message'] = $finalMessage;
            } else if (!$uploadWasAttempted) {
                 $finalMessage .= " (No profile image uploaded.)";
                 $_SESSION['message_type'] = 'success';
                 $_SESSION['message'] = $finalMessage;
            } else { // Upload succeeded
                $_SESSION['message_type'] = 'success';
                $_SESSION['message'] = $finalMessage;
            }

            header("Location: ../../public/admin_users.php"); // Redirect to list on success
            exit;
        }

    } catch (PDOException $e) {
        // --- Handle Database Errors ---
        error_log("Admin Add User PDOException: " . $e->getMessage());
        $_SESSION['message'] = "A database error occurred while adding the user. Please try again.";
        $_SESSION['message_type'] = 'error';
        // If an image was potentially uploaded but DB insert failed, delete the orphaned file
        if ($uploadWasAttempted && $profileImageUrl && defined('PROFILE_UPLOAD_DIR')) {
            $uploadedFilePath = PROFILE_UPLOAD_DIR . $profileImageUrl;
            if (file_exists($uploadedFilePath) && is_file($uploadedFilePath)) {
                @unlink($uploadedFilePath); // Attempt to delete, suppress errors if it fails
                error_log("Deleted orphaned upload file due to DB error: " . $uploadedFilePath);
            }
        }
        header("Location: ../../public/admin_edit_user.php?action=add"); // Redirect back to form on DB error
        exit;
    }
} else {
    // --- Handle Invalid Input / Failed Initial Validation ---
    $_SESSION['message'] = "Invalid input provided. Please check all required fields (Username, Full Name, Role, Password).";
    $_SESSION['message_type'] = 'error';
    header("Location: ../../public/admin_edit_user.php?action=add"); // Redirect back to add form
    exit;
}
?>