<?php
// src/actions/get_recent_sales_ajax.php
session_start(); // Access session if needed later (e.g., filtering by cashier)
require_once '../config/database.php';
header('Content-Type: application/json'); // Send JSON back

$response = ['success' => false, 'html' => '<p class="sales-fetch-error">Could not fetch sales.</p>']; // Default error response

try {
    $sql = "SELECT id, sale_time, total_amount FROM sales ORDER BY sale_time DESC LIMIT 10";
    $stmt = $pdo->query($sql);
    $recentSales = $stmt->fetchAll();

    $html = ''; // Build HTML table rows
    if (empty($recentSales)) {
        $html = '<tr><td colspan="3" class="no-sales-message">No recent sales found.</td></tr>';
    } else {
        foreach ($recentSales as $sale) {
            $formattedTime = htmlspecialchars($sale['sale_time']); // Fallback
            try {
                $date = new DateTime($sale['sale_time']);
                $formattedTime = htmlspecialchars($date->format('M d, Y h:i A'));
            } catch (Exception $e) { /* Use fallback */ }

            $html .= '<tr>';
            $html .= '<td class="sale-id">' . htmlspecialchars($sale['id']) . '</td>';
            $html .= '<td class="sale-time">' . $formattedTime . '</td>';
            $html .= '<td class="sale-total">$' . number_format($sale['total_amount'], 2) . '</td>';
            $html .= '</tr>';
        }
    }
    $response = ['success' => true, 'html' => $html]; // Send success and HTML

} catch (PDOException $e) {
    error_log("AJAX Fetch Recent Sales Error: " . $e->getMessage());
    // Keep default error response message for security
}

echo json_encode($response);
exit();
?>