<?php
session_start(); // Start session FIRST
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; } // Check login

// Include database configuration AFTER session start
require_once '../src/config/database.php';

// Get user details from session
$loggedInUserId = $_SESSION['user_id'];
$loggedInUserName = $_SESSION['full_name'] ?? 'N/A';
$loggedInUserProfileImage = null; // Default

// Fetch user's profile image filename from DB
try {
    $stmtUser = $pdo->prepare("SELECT profile_image_url FROM users WHERE id = :id");
    $stmtUser->bindParam(':id', $loggedInUserId, PDO::PARAM_INT);
    $stmtUser->execute();
    $userData = $stmtUser->fetch();
    if ($userData && !empty($userData['profile_image_url'])) { // Check if not empty
        $loggedInUserProfileImage = $userData['profile_image_url'];
    }
} catch (PDOException $e) {
    error_log("Fetch user profile image error (Checkout): " . $e->getMessage());
    // Non-critical, proceed without image
}

// --- Fetch Initial Recent Sales Data ---
$recentSales = [];
$fetchError = null;
try {
    $sql = "SELECT id, sale_time, total_amount FROM sales ORDER BY sale_time DESC LIMIT 10";
    $stmt = $pdo->query($sql);
    $recentSales = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch Recent Sales Error (Checkout Page): " . $e->getMessage());
    $fetchError = "Could not load recent sales data.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - TwsyaTech POS</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* --- Styles for Page Header --- */
        .page-header { display: flex; justify-content: space-between; align-items: center; padding: 5px 20px; background-color: #f0f0f0; border-bottom: 1px solid #ccc; margin-bottom: 20px; }
        .header-info { display: flex; align-items: center; gap: 10px; }
        .header-info .profile-pic { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid #ccc; }
        .header-info span { font-weight: 500; }
        .header-info #current-time { margin-left: 15px; font-weight: normal; color: #555; }
        .logout-link { display: flex; align-items: center; gap: 15px;} /* Added gap */
        .logout-link a { text-decoration: none; font-weight: bold;}
        .logout-link a:hover { text-decoration: underline;}
        .logout-link a[href="index.php"] { color:#007bff; } /* Main Menu link color */
        .logout-link a[href*="logout.php"] { color: #dc3545; } /* Logout link color */

        /* --- Checkout Layout Styles --- */
        .checkout-main-area { display: flex; gap: 20px; flex-wrap: wrap; }
        .checkout-left-column { flex: 1; min-width: 300px; display: flex; flex-direction: column; gap: 15px;}
        .checkout-right-column { flex: 1.5; min-width: 400px; }

        /* --- Scan Area & Live Search Styles --- */
        .scan-area { position: relative; background-color: #fff; padding: 15px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);}
        #product-lookup-result { position: absolute; background-color: white; border: 1px solid #ced4da; border-top: none; border-radius: 0 0 4px 4px; width: 100%; box-sizing: border-box; max-height: 250px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-top: -1px; }
        #product-lookup-result:empty { display: none; }
        .search-result-item { display: block; padding: 8px 12px; margin: 0; background-color: transparent; border: none; border-bottom: 1px solid #eee; border-radius: 0; cursor: pointer; text-align: left; width: 100%; box-sizing: border-box; font-size: 0.95em; transition: background-color 0.15s ease-in-out; }
        .search-result-item:last-child { border-bottom: none; }
        .search-result-item:hover, .search-result-item:focus { background-color: #e9ecef; outline: none; }
        .search-result-item .item-name { font-weight: bold; }
        .search-result-item .item-details { font-size: 0.85em; color: #6c757d; display: block; margin-top: 2px;}

        /* --- Quick Keys / Available Products Styles --- */
        .quick-keys-container { background-color: #fff; padding: 15px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-height: 450px; overflow-y: auto; }
        .quick-keys-container h2 { margin-top: 0; font-size: 1.2em; color: #495057; }
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 10px; margin-top: 10px; }
        .quick-key-item { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 8px; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; background-color: #f8f9fa; transition: background-color 0.2s, box-shadow 0.2s; min-height: 90px; text-align: center; }
        .quick-key-item:hover { background-color: #e9ecef; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .quick-key-item img { max-width: 45px; max-height: 45px; margin-bottom: 5px; object-fit: contain; }
        .quick-key-item span { font-size: 0.8em; font-weight: 500; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        /* --- Cart Area Styles --- */
        .cart-area { background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; flex-direction: column; height: 100%; }
        .cart-area h2 { margin-top: 0; }
        #cart-items-container { flex-grow: 1; overflow-y: auto; max-height: 400px; border: 1px solid #dee2e6; border-radius: 4px; margin-bottom: 15px;}
        #cart-items { width: 100%; border-collapse: collapse; }
        #cart-items thead { position: sticky; top: 0; background-color: #e9ecef; z-index: 10; }
        #cart-items th, #cart-items td { padding: 10px; border-bottom: 1px solid #dee2e6; }
        #cart-items tbody tr:last-child td { border-bottom: none; }
        .cart-summary { margin-top: auto; padding-top: 15px; border-top: 2px solid #dee2e6; font-size: 1.3em; font-weight: bold; text-align: right; }
        #complete-sale-btn { margin-top: 15px; width: 100%; padding: 12px; background-color: #fd7e14; font-weight: bold; }
        #complete-sale-btn:hover:not(:disabled) { background-color: #e66804; }
        #complete-sale-btn:disabled { background-color: #6c757d; }

        /* --- Cart Item Stock Cell Styles --- */
         #cart-items td.stock-cell { font-weight: 500; font-size: 0.9em; text-align: center;}
         #cart-items td.stock-low { color: orange; font-weight: bold; }
         #cart-items td.stock-out { color: red; font-weight: bold; text-decoration: line-through; }
         #cart-items td.stock-ok { color: #28a745; }

        /* --- Recent Sales Section Styles --- */
        .recent-sales-container { margin-top: 30px; padding: 20px; background-color: #f1f3f5; border-radius: 5px; border: 1px solid #dee2e6; }
        .recent-sales-container h3 { margin-top: 0; margin-bottom: 15px; color: #495057; border-bottom: 1px solid #ced4da; padding-bottom: 8px; }
        #recent-sales-table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        #recent-sales-table th, #recent-sales-table td { border: 1px solid #dee2e6; padding: 8px 10px; text-align: left; }
        #recent-sales-table th { background-color: #e9ecef; font-weight: 600; }
        #recent-sales-table td.sale-id { text-align: center; width: 10%; }
        #recent-sales-table td.sale-time { width: 50%; }
        #recent-sales-table td.sale-total { text-align: right; width: 40%; }
        .no-sales-message { text-align: center; color: #6c757d; padding: 15px; font-style: italic; }
        .sales-fetch-error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px 15px; border-radius: 4px; text-align: center; font-weight: bold; }

        /* Status messages defined in style.css are assumed */

    </style>
</head>
<body>

    <!-- Page Header with Profile Picture -->
    <div class="page-header">
        <div class="header-info">
            <img src="images/<?php echo $loggedInUserProfileImage ? 'profiles/' . htmlspecialchars($loggedInUserProfileImage) : 'placeholder.png'; // Use profile pic or default ?>"
                 alt="Profile"
                 class="profile-pic"
                 onerror="this.src='images/placeholder.png'; this.onerror=null;"> <!-- Fallback if image fails -->
            <span>Cashier: <?php echo htmlspecialchars($loggedInUserName); ?></span>
            <span id="current-time">Loading time...</span>
        </div>
        <div class="logout-link">
             <a href="index.php">Main Menu</a>
             <!-- Optionally add Admin link here if user is manager -->
             <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'): ?>
                <a href="admin.php" style="color:#28a745;">Admin Panel</a>
             <?php endif; ?>
            <a href="../src/actions/logout.php">Logout</a>
        </div>
    </div>

    <h1>Checkout</h1>

    <!-- Main Content Area -->
    <div class="checkout-main-area">

        <!-- LEFT COLUMN (Scan / Available Products) -->
        <div class="checkout-left-column">
           <!-- Scan Area -->
           <div class="scan-area">
                <label for="barcode-input">Scan Barcode / Enter Name or Code:</label>
                <input type="text" id="barcode-input" placeholder="Start typing or scan..." autocomplete="off">
                 <div id="lookup-status" class="status-message"></div>
                 <div id="product-lookup-result"></div> <!-- Live search results -->
            </div>
            <!-- Available Products (Quick Keys) Area -->
            <div class="quick-keys-container">
                <h2>Available Products</h2>
                <div id="quick-keys-grid" class="product-grid">
                    <p>Loading products...</p>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN (Cart) -->
        <div class="checkout-right-column">
            <div class="cart-area">
                <h2>Current Sale</h2>
                <div id="cart-items-container">
                     <table id="cart-items">
                         <thead>
                             <tr>
                                 <th>Product</th>
                                 <th style="text-align: right;">Price</th>
                                 <th style="text-align: center;">Qty</th>
                                 <th style="text-align: right;">Total</th>
                                 <th style="text-align: center;">Stock Left</th>
                                 <th style="text-align: center;">Action</th>
                             </tr>
                         </thead>
                         <tbody>
                             <tr><td colspan="6" style="text-align: center; padding: 20px;">Cart is empty</td></tr>
                         </tbody>
                     </table>
                 </div>
                <div class="cart-summary">
                    <strong>Total: $<span id="cart-total">0.00</span></strong>
                </div>
                <button id="complete-sale-btn" type="button" disabled>Complete Sale</button> <!-- Type added -->
                 <div id="sale-status" class="status-message"></div>
            </div>
        </div> <!-- End Right Column -->

    </div><!-- End checkout-main-area -->

    <!-- Recent Sales Section -->
    <div class="recent-sales-container">
        <h3>Recent Sales</h3>
        <?php if ($fetchError): ?>
            <p class="sales-fetch-error"><?php echo htmlspecialchars($fetchError); ?></p>
        <?php else: ?>
            <table id="recent-sales-table">
                <thead>
                    <tr>
                        <th>Sale ID</th>
                        <th>Date & Time</th>
                        <th style="text-align: right;">Total Amount</th>
                    </tr>
                </thead>
                <tbody id="recent-sales-tbody">
                    <?php if (empty($recentSales)): ?>
                        <tr><td colspan="3" class="no-sales-message">No recent sales found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td class="sale-id"><?php echo htmlspecialchars($sale['id']); ?></td>
                                <td class="sale-time">
                                    <?php
                                    try { $date = new DateTime($sale['sale_time']); echo htmlspecialchars($date->format('M d, Y h:i A')); }
                                    catch (Exception $e) { echo htmlspecialchars($sale['sale_time']); }
                                    ?>
                                </td>
                                <td class="sale-total">$<?php echo number_format($sale['total_amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <!-- End Recent Sales Section -->

    <!-- Include JavaScript at the end -->
    <script src="js/checkout.js"></script>
</body>
</html>