<?php
require_once '../src/includes/check_admin.php';
require_once '../src/config/database.php';

// Determine mode (add or edit)
$mode = $_GET['action'] ?? 'add';
$userId = null;
$user = ['id' => null, 'username' => '', 'full_name' => '', 'role' => 'cashier', 'profile_image_url' => null];
$pageTitle = "Add New User";
$formAction = "../src/actions/process_add_user.php";
$submitButtonText = "Add User";
$currentImageUrl = null; // Variable to hold path to current image

if ($mode === 'edit') {
    $userId = $_GET['id'] ?? null;
    if (!$userId || !filter_var($userId, FILTER_VALIDATE_INT)) { /* ... redirect ... */ }
    $userId = (int)$userId;
    try {
        $stmt = $pdo->prepare("SELECT id, username, full_name, role, profile_image_url FROM users WHERE id = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $userData = $stmt->fetch();
        if (!$userData) { /* ... redirect user not found ... */ }
        $user = $userData; // Overwrite defaults
        $pageTitle = "Edit User: " . htmlspecialchars($user['username']);
        $formAction = "../src/actions/process_edit_user.php";
        $submitButtonText = "Update User";
        // Construct path to current image if it exists
        if (!empty($user['profile_image_url'])) {
             $currentImageUrl = "../images/profiles/" . htmlspecialchars($user['profile_image_url']); // Relative path from public folder
        }

    } catch (PDOException $e) { /* ... redirect on DB error ... */ }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .current-profile-pic { max-width: 100px; max-height: 100px; display: block; margin-bottom: 10px; border-radius: 50%; border: 1px solid #ccc;}
        label[for="profile_image"] { margin-top: 10px;} /* Add space above file input */
    </style>
</head>
<body>
    <?php include '../src/includes/admin_header.php'; ?>
    <h1><?php echo $pageTitle; ?></h1>

    <?php if (isset($_SESSION['message'])): /* ... Display messages ... */ endif; ?>

    <!-- >>> ADDED enctype FOR FILE UPLOADS <<< -->
    <form action="<?php echo $formAction; ?>" method="POST" enctype="multipart/form-data">
        <?php if ($mode === 'edit'): ?>
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($userId); ?>">
            <!-- Keep track of the old image filename for potential deletion -->
            <input type="hidden" name="old_image_url" value="<?php echo htmlspecialchars($user['profile_image_url'] ?? ''); ?>">
        <?php endif; ?>

        <div> <!-- Username -->
            <label for="username">Username: <span class="required">*</span></label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required <?php echo ($mode === 'edit' ? 'readonly title="Username cannot be changed"' : ''); ?>>
            <?php if ($mode === 'edit'): ?> <small>Username cannot be changed.</small> <?php endif; ?>
        </div>
        <div> <!-- Full Name -->
            <label for="full_name">Full Name: <span class="required">*</span></label>
            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
        </div>
        <div> <!-- Role -->
             <label for="role">Role: <span class="required">*</span></label>
             <select id="role" name="role" required <?php echo ($mode === 'edit' && $user['id'] === $adminUserId) ? 'disabled title="Cannot change your own role"' : ''; ?>>
                 <option value="cashier" <?php echo (($user['role']) === 'cashier' ? 'selected' : ''); ?>>Cashier</option>
                 <option value="manager" <?php echo (($user['role']) === 'manager' ? 'selected' : ''); ?>>Manager (Admin)</option>
             </select>
             <?php if ($mode === 'edit' && $user['id'] === $adminUserId): ?> <input type="hidden" name="role" value="manager" /><small>Cannot change your own admin role.</small> <?php endif; ?>
        </div>
        <div> <!-- Password -->
            <label for="password">Password: <?php echo ($mode === 'add' ? '<span class="required">*</span>' : ''); ?></label>
            <input type="password" id="password" name="password" placeholder="<?php echo ($mode === 'edit' ? 'Leave blank to keep current password' : ''); ?>" <?php echo ($mode === 'add' ? 'required' : ''); ?>>
            <?php if ($mode === 'edit'): ?><small>Only enter a new password if you want to change it.</small><?php endif; ?>
        </div>

        <!-- >>> MODIFIED: Profile Image Upload <<< -->
        <div>
            <label>Current Profile Image:</label>
            <?php if ($currentImageUrl): ?>
                 <img src="<?php echo $currentImageUrl; ?>" alt="Current Profile Picture" class="current-profile-pic" onerror="this.src='../images/placeholder.png'; this.style.display='none'; this.nextElementSibling.style.display='block';">
                 <p style="display:none; font-style: italic; color: #666;">No current image.</p> <!-- Fallback text if image fails -->
                 <small>Current Image: <?php echo htmlspecialchars($user['profile_image_url']); ?></small>
                 <br> <!-- Line break -->
            <?php else: ?>
                <p style="font-style: italic; color: #666;">No current image set.</p>
            <?php endif; ?>

            <label for="profile_image">Upload New Profile Image:</label>
            <input type="file" id="profile_image" name="profile_image" accept="image/jpeg, image/png, image/gif, image/webp"> <!-- Accept common image types -->
             <small>Optional. Leave blank to keep existing image (if any) or use default. Max 2MB. Recommended: Square dimensions.</small>
             <!-- Add input to signal deletion? More complex, skipping for now. -->
             <!-- <label><input type="checkbox" name="delete_image" value="1"> Delete current image</label> -->
        </div>
        <!-- >>> END: Profile Image Upload <<< -->

        <div>
            <button type="submit"><?php echo $submitButtonText; ?></button>
            <a href="admin_users.php" style="margin-left: 15px;">Cancel</a>
        </div>
    </form>

</body>
</html>