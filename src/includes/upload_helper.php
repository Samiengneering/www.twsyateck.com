<?php
// src/includes/upload_helper.php

/**
 * Handles file upload validation and moving.
 * (Includes enhanced debugging and path checks)
 * ... (function description as before) ...
 */
function handleFileUpload(array $fileInput, string $uploadDir, array $allowedTypes, int $maxSize): array
{
    // --- Basic Upload Error Check ---
    if (!isset($fileInput['error']) || is_array($fileInput['error'])) {
        error_log("handleFileUpload Error: Invalid parameters passed to function.");
        return ['success' => false, 'message' => 'Invalid upload parameters.', 'filename' => null];
    }

    switch ($fileInput['error']) {
        case UPLOAD_ERR_OK: break; // OK
        case UPLOAD_ERR_NO_FILE: return ['success' => true, 'message' => 'No file submitted.', 'filename' => null];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
             error_log("handleFileUpload Error: Upload error code " . $fileInput['error'] . " - File too large (max set in php.ini/form).");
            return ['success' => false, 'message' => 'File is too large (max ' . round($maxSize / 1024 / 1024, 1) . 'MB).', 'filename' => null];
        default:
             error_log("handleFileUpload Error: Unknown upload error code: " . $fileInput['error']);
            return ['success' => false, 'message' => 'Unknown file upload error (Code: '.$fileInput['error'].').', 'filename' => null];
    }

    // --- File Size Check ---
    if ($fileInput['size'] > $maxSize) {
         error_log("handleFileUpload Error: File size (" . $fileInput['size'] . ") exceeds max size (" . $maxSize . ").");
        return ['success' => false, 'message' => 'File is too large (max ' . round($maxSize / 1024 / 1024, 1) . 'MB).', 'filename' => null];
    }

    // --- MIME Type Check ---
    // Check tmp_name exists and is readable before using finfo
    if (!is_uploaded_file($fileInput['tmp_name']) || !is_readable($fileInput['tmp_name'])) {
         error_log("handleFileUpload Error: Uploaded file temp name is invalid or not readable: " . ($fileInput['tmp_name'] ?? 'N/A'));
        return ['success' => false, 'message' => 'Server error processing uploaded file.', 'filename' => null];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($fileInput['tmp_name']);
    if (false === array_search($mimeType, $allowedTypes, true)) {
         error_log("handleFileUpload Error: Invalid MIME type '" . $mimeType . "'. Allowed: " . implode(', ', $allowedTypes));
        return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP', 'filename' => null];
    }

    // --- Target Directory and Filename ---
    // Ensure directory path ends with a separator
    $uploadDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR;

    // Generate unique filename
    $originalName = pathinfo($fileInput['name'], PATHINFO_FILENAME);
    $extension = pathinfo($fileInput['name'], PATHINFO_EXTENSION);
    $safeOriginalName = preg_replace("/[^A-Za-z0-9_\-.]/", '_', $originalName); // Allow dots in safe name now
    $safeExtension = strtolower($extension); // Ensure lowercase extension
    // Check if extension is actually allowed (secondary check)
     if (!in_array('image/' . $safeExtension, $allowedTypes) && $safeExtension !== 'jpg') { // Allow jpg as alias for jpeg
          // Adjust this check if your ALLOWED_MIME_TYPES array is different
          error_log("handleFileUpload Error: File extension '.".$safeExtension."' not explicitly allowed based on MIME types.");
          return ['success' => false, 'message' => 'Invalid file extension detected.', 'filename' => null];
     }
    $newFilename = 'user_profile_' . uniqid() . '_' . substr($safeOriginalName, 0, 50) . '.' . $safeExtension; // Limit original name part length
    $targetPath = $uploadDir . $newFilename;

    // --- >>> Enhanced Directory/Permission Check <<< ---
    if (!is_dir($uploadDir)) {
        error_log("handleFileUpload Error: Target directory does NOT exist: " . $uploadDir);
        return ['success' => false, 'message' => 'Server configuration error (Target directory missing).', 'filename' => null];
    }
    if (!is_writable($uploadDir)) {
        error_log("handleFileUpload Error: Target directory is NOT writable: " . $uploadDir . " | Current User: " . get_current_user());
        // Try to get the Apache user (may not work on all systems, especially Windows/XAMPP)
         if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
              $processUser = posix_getpwuid(posix_geteuid());
              error_log("Web server process likely running as: " . ($processUser['name'] ?? 'N/A'));
         }
        return ['success' => false, 'message' => 'Server configuration error (Directory permissions).', 'filename' => null];
    }
    // --- >>> END Enhanced Check <<< ---


    // --- Move Uploaded File ---
    if (!move_uploaded_file($fileInput['tmp_name'], $targetPath)) {
         error_log("handleFileUpload Error: move_uploaded_file failed. Source: " . $fileInput['tmp_name'] . " | Target: " . $targetPath);
         // Check for more specific errors if possible (e.g., partial upload, write error)
         $lastError = error_get_last();
         if ($lastError) { error_log("PHP Last Error: " . print_r($lastError, true)); }
        return ['success' => false, 'message' => 'Failed to save uploaded file.', 'filename' => null];
    }

    // --- Success ---
    return ['success' => true, 'message' => 'File uploaded successfully.', 'filename' => $newFilename];
}
?>