<?php
require_once '../src/includes/check_admin.php'; // Secure the page
require_once '../src/config/database.php'; // $pdo

$users = [];
$fetchError = null;
try {
    // --- MODIFIED SQL: Added profile_image_url ---
    $sql = "SELECT id, username, full_name, role, profile_image_url
            FROM users
            ORDER BY username ASC";
    // --- END MODIFICATION ---
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Admin Fetch Users Error: " . $e->getMessage());
    $fetchError = "Could not load user data.";
}
?>
<!-- ... rest of the HTML ... -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .action-links a { margin-right: 10px; font-size: 0.9em; }
        .action-links .delete-disabled { color: #999; text-decoration: none; cursor: not-allowed; font-size: 0.9em; margin-right: 10px;}
        .add-button { margin-bottom: 15px; display: inline-block; }
        /* --- Styles for Profile Picture Column --- */
        th.profile-pic-col, td.profile-pic-col {
            width: 80px; /* Adjust width as needed */
            text-align: center;
        }
        td.profile-pic-col img {
            max-width: 50px;
            max-height: 50px;
            border-radius: 50%; /* Circular image */
            object-fit: cover;
            border: 1px solid #ccc;
            vertical-align: middle;
        }
        td.profile-pic-col .no-pic { /* Style for placeholder text */
             font-size: 0.8em;
             color: #999;
        }
    </style>
</head>
<body>
    <?php include '../src/includes/admin_header.php'; ?>
    <h1>Manage Users</h1>

    <p><a href="admin_edit_user.php?action=add" class="button-like add-button">Add New User</a></p>

    <?php if (isset($_SESSION['message'])): /* ... Display messages ... */ endif; ?>

    <?php if ($fetchError): ?>
        <p class="message-error"><?php echo htmlspecialchars($fetchError); ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <!-- >>> NEW: Picture Header <<< -->
                    <th class="profile-pic-col">Picture</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <!-- >>> Updated colspan <<< -->
                    <tr><td colspan="6" class="no-data-message">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                        <!-- >>> NEW: Picture Cell <<< -->
                        <td class="profile-pic-col">
                            <?php
                                // Construct image path, use placeholder if not set or file doesn't exist (more robust check possible but adds overhead)
                                $imgPath = '../images/placeholder.png'; // Default placeholder relative path
                                if (!empty($user['profile_image_url'])) {
                                    $userImg = '../images/profiles/' . htmlspecialchars($user['profile_image_url']);
                                    // Basic check if file might exist (not foolproof) - consider removing if causes issues
                                    // if (file_exists(__DIR__ . '/images/profiles/' . $user['profile_image_url'])) {
                                        $imgPath = $userImg;
                                    // }
                                }
                            ?>
                            <img src="<?php echo $imgPath; ?>" alt="Profile" onerror="this.src='../images/placeholder.png'; this.onerror=null;">
                            <?php /* Alternative text if no image:
                                    if (empty($user['profile_image_url'])) {
                                       echo '<span class="no-pic">No Pic</span>';
                                    } else {
                                        echo '<img src="../images/profiles/' . htmlspecialchars($user['profile_image_url']) . '" alt="Profile" onerror="this.src=\'../images/placeholder.png\'; this.onerror=null;">';
                                    } */
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                        <td class="action-links">
                            <a href="admin_edit_user.php?action=edit&id=<?php echo $user['id']; ?>">Edit</a>
                            <?php if ($user['id'] !== $adminUserId): ?>
                                <a href="../src/actions/process_delete_user.php?id=<?php echo $user['id']; ?>"
                                   class="delete-link"
                                   onclick="return confirm('Are you REALLY sure you want to delete user <?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>? This cannot be undone.');">Delete</a>
                            <?php else: ?>
                                <span class="delete-disabled" title="Cannot delete your own account">Delete</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p style="margin-top: 20px;"><a href="admin.php">Back to Admin Dashboard</a></p>

</body>
</html>