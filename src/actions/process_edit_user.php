<?php
// src/actions/process_edit_user.php
session_start(); // <<< START SESSION

// --- Correct Include Paths & Add Upload Helper ---
require_once '../includes/check_admin.php'; // Secure the script
require_once '../config/database.php';      // $pdo is available
require_once '../includes/upload_helper.php'; // Include the upload helper
// --- End Includes ---

// --- Define upload constants ---
$projectSubdir = '/twsyatech_pos'; // Adjust if needed, or set to ''
// Ensure PROFILE_UPLOAD_DIR is defined only once if included elsewhere potentially
if (!defined('PROFILE_UPLOAD_DIR')) {
    define('PROFILE_UPLOAD_DIR', rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $projectSubdir . '/public/images/profiles/');
}
if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2 MB limit
}
if (!defined('ALLOWED_MIME_TYPES')) {
    define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
}
// --- End Constants ---


$userId = $_POST['user_id'] ?? null; // Get user ID early for redirects

// --- Form Data Validation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    $userId && filter_var($userId, FILTER_VALIDATE_INT) &&          // Validate user_id
    isset($_POST['username']) && !empty(trim($_POST['username'])) && // Check username (though not changing)
    isset($_POST['full_name']) && !empty(trim($_POST['full_name'])) && // Validate full_name
    isset($_POST['role']) && in_array($_POST['role'], ['cashier', 'manager']) // Validate role
   )
{
    $userId = (int)$userId; // Cast to int
    $username = trim($_POST['username']); // Keep for messages
    $fullName = trim($_POST['full_name']);
    $role = $_POST['role'];
    $plainPassword = $_POST['password'] ?? null; // Optional password
    $oldImageUrl = $_POST['old_image_url'] ?? null; // Get existing image filename
    $profileImageUrl = $oldImageUrl; // Assume keeping old image initially
    $uploadMessage = '';     // Feedback message specifically for upload
    $uploadWasAttempted = false; // Flag if user submitted a file input
    $deleteOldImage = false; // Flag to delete old file later

    // Prevent admin self-role change
    if ($userId === $adminUserId && $role !== 'manager') {
        $_SESSION['message'] = "Error: Cannot change your own role from Manager.";
        $_SESSION['message_type'] = 'error';
        header("Location: ../../public/admin_edit_user.php?action=edit&id=" . $userId);
        exit;
    }

    // --- Handle File Upload ---
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadWasAttempted = true;
        $uploadResult = handleFileUpload($_FILES['profile_image'], PROFILE_UPLOAD_DIR, ALLOWED_MIME_TYPES, MAX_UPLOAD_SIZE);

        if ($uploadResult['success'] && $uploadResult['filename']) {
            $profileImageUrl = $uploadResult['filename']; // Use the new unique filename
            // Mark old image for deletion only if upload succeeded AND there was an old image
            $deleteOldImage = (!empty($oldImageUrl) && $oldImageUrl !== $profileImageUrl);
        } elseif (!$uploadResult['success']) {
            // Upload failed, store error message but continue script to save other changes
            $uploadMessage = " Profile image upload failed: " . $uploadResult['message'];
            $profileImageUrl = $oldImageUrl; // Keep the old image filename if upload fails
            $deleteOldImage = false; // Don't delete old image if new one failed
        }
        // If UPLOAD_ERR_NO_FILE, do nothing, keep $profileImageUrl as $oldImageUrl
    }
    // --- End Handle File Upload ---

    try {
        // Build the SQL update query dynamically
        $sqlSetParts = [];
        $params = [];

        // Add fields that are always updated (if changed)
        $sqlSetParts[] = "full_name = :full_name"; $params[':full_name'] = $fullName;
        if ($userId !== $adminUserId) { $sqlSetParts[] = "role = :role"; $params[':role'] = $role; }

        // Add profile image URL to update list if it changed
        if ($profileImageUrl !== $oldImageUrl || ($profileImageUrl && !$oldImageUrl) || ($profileImageUrl === null && $oldImageUrl)) {
             $sqlSetParts[] = "profile_image_url = :profile_image_url";
             $params[':profile_image_url'] = (empty($profileImageUrl)) ? null : $profileImageUrl; // Bind new filename or NULL
        }

        // Add password to update list only if a new one was provided
        if (!empty(trim($plainPassword))) {
            $sqlSetParts[] = "password = :password";
            $params[':password'] = $plainPassword; // INSECURE plain text storage
            // $params[':password'] = password_hash($plainPassword, PASSWORD_DEFAULT); // SECURE way
        }

        // --- Execute Update only if changes were made ---
        $dbUpdateAttempted = !empty($sqlSetParts);
        if ($dbUpdateAttempted) {
            $params[':user_id'] = $userId; // Add user ID for WHERE clause
            $sql = "UPDATE users SET " . implode(", ", $sqlSetParts) . " WHERE id = :user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params); // Execute the update

            // --- Delete Old Image File (if marked for deletion and AFTER DB update) ---
            if ($deleteOldImage && !empty($oldImageUrl)) {
                $oldFilePath = PROFILE_UPLOAD_DIR . $oldImageUrl;
                if (file_exists($oldFilePath) && is_file($oldFilePath)) {
                    if (!@unlink($oldFilePath)) { // Suppress unlink error, but log it
                         error_log("Could not delete old profile image: " . $oldFilePath);
                         // Optionally add to $uploadMessage as a warning
                         // $uploadMessage .= " (Could not remove old image file)";
                    }
                }
            }
            // --- End Delete Old Image ---

            // Set overall success message, append any upload warning
            $_SESSION['message'] = "User '" . htmlspecialchars($username) . "' updated successfully!" . $uploadMessage;
            $_SESSION['message_type'] = !empty($uploadMessage) ? 'warning' : 'success';

            // Update session name if the logged-in user edits themselves
            if ($userId === $adminUserId) { $_SESSION['full_name'] = $fullName; }

        } else if ($uploadWasAttempted && !empty($uploadMessage)) {
             // No DB changes were needed, but upload failed
             $_SESSION['message'] = $uploadMessage;
             $_SESSION['message_type'] = 'error';
             header("Location: ../../public/admin_edit_user.php?action=edit&id=" . $userId); // Redirect back to edit
             exit;
        } else {
             // No DB changes and no upload attempt/error
             $_SESSION['message'] = "No changes detected for user '" . htmlspecialchars($username) . "'.";
             $_SESSION['message_type'] = 'info';
        }

    } catch (PDOException $e) {
        error_log("Admin Edit User PDOException: " . $e->getMessage());
        $_SESSION['message'] = "A database error occurred while updating the user." . $uploadMessage; // Append upload msg
        $_SESSION['message_type'] = 'error';
        // If upload succeeded but DB failed, delete the newly uploaded file
        if ($uploadWasAttempted && $profileImageUrl !== $oldImageUrl && !empty($profileImageUrl) && defined('PROFILE_UPLOAD_DIR')) {
             $newFilePath = PROFILE_UPLOAD_DIR . $profileImageUrl;
             if (file_exists($newFilePath) && is_file($newFilePath)) {
                 @unlink($newFilePath);
                 error_log("Deleted newly uploaded file due to DB error: " . $newFilePath);
             }
        }
        header("Location: ../../public/admin_edit_user.php?action=edit&id=" . $userId); // Redirect back on DB error
        exit;
    }
} else {
    // --- Handle Initial Invalid Input ---
    $_SESSION['message'] = "Invalid input provided for update. Please check all required fields.";
    $_SESSION['message_type'] = 'error';
     // Redirect back to the edit form if possible, otherwise to list
     if ($userId && filter_var($userId, FILTER_VALIDATE_INT)) {
        header("Location: ../../public/admin_edit_user.php?action=edit&id=" . $userId);
     } else {
        header("Location: ../../public/admin_users.php");
     }
     exit;
}

// Redirect to the user list page after successful update or if no changes were detected
header("Location: ../../public/admin_users.php");
exit();
?>