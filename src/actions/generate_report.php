<?php
// src/actions/generate_report.php
session_start();
require_once '../config/database.php'; // $pdo is available

// --- Auth & Role Check ---
if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['format']) && $_GET['format'] === 'csv') { header("HTTP/1.1 401 Unauthorized"); die("Authentication Required."); }
    else { header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => 'Authentication required.']); exit; }
}
// Optional Role check: Uncomment and adjust if needed
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
//     if (isset($_GET['format']) && $_GET['format'] === 'csv') { header("HTTP/1.1 403 Forbidden"); die("Access Denied."); }
//     else { header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => 'Access Denied.']); exit; }
// }

// --- Parameter Handling & Validation ---
$reportType = $_GET['report_type'] ?? 'daily';
$format = $_GET['format'] ?? 'json';
$selectedCashierId = isset($_GET['cashier_id']) && filter_var($_GET['cashier_id'], FILTER_VALIDATE_INT) ? (int)$_GET['cashier_id'] : null;

$params = [];
$whereClauses = ["1=1"]; // Base clause
$reportTitle = "Sales Report";
$cashierNameForTitle = "All Cashiers";
$groupBy = null;
// Default fields for detailed reports (includes calculated fields)
$selectFields = "s.id as sale_id, s.sale_time, p.id as product_id, p.name as product_name, p.barcode, si.quantity, si.price_at_sale, si.cost_price_at_sale, (si.price_at_sale - si.cost_price_at_sale) AS unit_profit, (si.quantity * (si.price_at_sale - si.cost_price_at_sale)) AS item_total_profit, (si.quantity * si.price_at_sale) AS item_total_revenue, u.full_name as cashier_name";
$fromJoin = "FROM sales s JOIN sale_items si ON s.id = si.sale_id JOIN products p ON si.product_id = p.id LEFT JOIN users u ON s.cashier_id = u.id";
$orderBy = "ORDER BY s.sale_time DESC, s.id DESC, p.name ASC";
$validPeriod = false; // Flag to track date validity

// --- Add Cashier Filter (if selected) ---
if ($selectedCashierId !== null) {
    $whereClauses[] = "s.cashier_id = :cashier_id";
    $params[':cashier_id'] = $selectedCashierId;
    try { // Fetch name for title
        $stmtName = $pdo->prepare("SELECT full_name FROM users WHERE id = :id");
        $stmtName->execute([':id' => $selectedCashierId]);
        $cashierInfo = $stmtName->fetch();
        $cashierNameForTitle = $cashierInfo ? htmlspecialchars($cashierInfo['full_name']) : "Cashier ID: " . $selectedCashierId;
    } catch (PDOException $e) { error_log("Error fetching cashier name: " . $e->getMessage()); $cashierNameForTitle = "Cashier ID: " . $selectedCashierId; }
}

// --- Determine Date WHERE clauses, Title, and potential Aggregation ---
switch ($reportType) {
    case 'daily':
        $date = $_GET['date'] ?? date('Y-m-d');
        if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
            $whereClauses[] = "DATE(s.sale_time) = :report_date"; $params[':report_date'] = $date;
            $reportTitle = "Daily Sales (" . date('M d, Y', strtotime($date)) . ") - " . $cashierNameForTitle; $validPeriod = true;
        }
        break;
    case 'weekly':
         $date = $_GET['date'] ?? date('Y-m-d');
         if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
             $yearWeek = date('oW', strtotime($date)); // ISO year/week
             $whereClauses[] = "DATE_FORMAT(s.sale_time, '%x%v') = :year_week"; $params[':year_week'] = $yearWeek;
             $weekStartDate = date('M d, Y', strtotime('monday this week', strtotime($date)));
             $reportTitle = "Weekly Sales (Week of $weekStartDate) - " . $cashierNameForTitle; $validPeriod = true;
         }
        break;
    case 'monthly':
        $month = $_GET['month'] ?? date('Y-m');
        if (preg_match("/^\d{4}-\d{2}$/", $month)) {
            $year = substr($month, 0, 4); $mon = substr($month, 5, 2);
            $whereClauses[] = "YEAR(s.sale_time) = :year AND MONTH(s.sale_time) = :month";
            $params[':year'] = $year; $params[':month'] = $mon;
            $reportTitle = "Monthly Sales (" . date('F Y', strtotime($month . "-01")) . ") - " . $cashierNameForTitle; $validPeriod = true;
        }
        break;
    case 'yearly': // Yearly summary by product
        $year = $_GET['year'] ?? date('Y');
        if (filter_var($year, FILTER_VALIDATE_INT) && $year > 1990 && $year < 2100) {
            $whereClauses[] = "YEAR(s.sale_time) = :year"; $params[':year'] = $year;
            $reportTitle = "Yearly Product Summary ($year) - " . $cashierNameForTitle;
            // Aggregate by product for yearly summary
            $selectFields = "p.id as product_id, p.name as product_name, p.barcode, SUM(si.quantity) as total_quantity_sold, AVG(si.price_at_sale) as avg_sell_price, AVG(si.cost_price_at_sale) as avg_cost_price, SUM(si.quantity * si.price_at_sale) as total_revenue, SUM(si.quantity * si.cost_price_at_sale) as total_cost, SUM(si.quantity * (si.price_at_sale - si.cost_price_at_sale)) as total_profit";
            // Need to adjust FROM/JOIN as 'u' alias is not used in yearly product summary SELECT
             $fromJoin = "FROM sales s JOIN sale_items si ON s.id = si.sale_id JOIN products p ON si.product_id = p.id";
            $groupBy = "GROUP BY p.id, p.name, p.barcode";
            $orderBy = "ORDER BY total_profit DESC";
            $validPeriod = true;
        }
        break;
    case 'custom':
        $startDate = $_GET['start_date'] ?? null; $endDate = $_GET['end_date'] ?? null;
        if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $startDate) && preg_match("/^\d{4}-\d{2}-\d{2}$/", $endDate) && $startDate <= $endDate) {
            $whereClauses[] = "s.sale_time >= :start_date AND s.sale_time < DATE_ADD(:end_date, INTERVAL 1 DAY)"; // Include entire end date
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate; // DATE_ADD handles the time part
            $reportTitle = "Custom Range Sales (" . date('M d, Y', strtotime($startDate)) . " - " . date('M d, Y', strtotime($endDate)) . ") - " . $cashierNameForTitle;
            $validPeriod = true;
        }
        break;
    default:
         $errorMessage = 'Invalid report type specified.';
         if ($format == 'json') { echo json_encode(['success' => false, 'message' => $errorMessage]); exit; }
         else { die($errorMessage); }
}

// --- Check if a valid period was set ---
if (!$validPeriod) {
    $errorMessage = "Invalid or missing date/period parameters for the selected report type.";
    if ($format == 'json') { echo json_encode(['success' => false, 'message' => $errorMessage]); exit; }
    else { die($errorMessage); }
}

// --- Execute Query & Calculate Summaries ---
$results = [];
$summaryData = ['total_revenue' => 0.0, 'total_cost' => 0.0, 'total_profit' => 0.0, 'total_items_sold' => 0, 'total_sales_count' => 0]; // Initialize with floats
$dbError = null;

try {
    $sql = "SELECT $selectFields $fromJoin WHERE " . implode(" AND ", $whereClauses);
    if ($groupBy) { $sql .= " " . $groupBy; }
    $sql .= " " . $orderBy;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    // Calculate summary totals based on fetched results
    if (!empty($results)) {
        if (!empty($groupBy)) { // Summarize data already aggregated by SQL (e.g., yearly)
             $summaryData['total_revenue'] = (float) array_sum(array_column($results, 'total_revenue'));
             $summaryData['total_cost'] = (float) array_sum(array_column($results, 'total_cost'));
             $summaryData['total_profit'] = (float) array_sum(array_column($results, 'total_profit'));
             $summaryData['total_items_sold'] = (int) array_sum(array_column($results, 'total_quantity_sold'));
             $summaryData['total_sales_count'] = count($results); // Count unique groups (products)
        } else { // Summarize detailed item data
            $uniqueSaleIds = [];
            foreach ($results as $row) {
                $revenue = (float) ($row['item_total_revenue'] ?? 0);
                $profit = (float) ($row['item_total_profit'] ?? 0); // Use pre-calculated profit per item
                $quantity = (int) ($row['quantity'] ?? 0);
                $costAtSale = (float) ($row['cost_price_at_sale'] ?? 0);

                $summaryData['total_revenue'] += $revenue;
                $summaryData['total_profit'] += $profit;
                $summaryData['total_cost'] += ($quantity * $costAtSale); // Calculate total cost by summing item costs
                $summaryData['total_items_sold'] += $quantity;
                if (isset($row['sale_id'])) { $uniqueSaleIds[$row['sale_id']] = true; }
            }
            $summaryData['total_sales_count'] = count($uniqueSaleIds);
            // Optional: Recalculate profit from totals for consistency check
            // $summaryData['total_profit'] = $summaryData['total_revenue'] - $summaryData['total_cost'];
        }
    }

} catch (PDOException $e) {
    error_log("Report Generation PDOException ($reportType): " . $e->getMessage());
    $dbError = "Database error generating report. Check logs.";
}


// --- Output Formatting ---

// --- CSV Export ---
if ($format === 'csv') {
    if ($dbError) { header("HTTP/1.1 500 Internal Server Error"); die($dbError); }

    $filenameBase = "sales_report_" . strtolower(str_replace([' ', '(', ')', ',', ':', '/'], '_', preg_replace('/-+/', '_', $reportTitle))) ;
    $filename = $filenameBase . "_" . date('Ymd') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');

    // Title & Summary
    fputcsv($output, [$reportTitle]);
    fputcsv($output, []);
    fputcsv($output, ["Summary for selected period/filter:"]);
    fputcsv($output, ["Total Sales Count:", $summaryData['total_sales_count']]);
    fputcsv($output, ["Total Items Sold:", $summaryData['total_items_sold']]);
    fputcsv($output, ["Total Revenue:", '$' . number_format($summaryData['total_revenue'], 2)]);
    fputcsv($output, ["Total Cost:", '$' . number_format($summaryData['total_cost'], 2)]);
    fputcsv($output, ["Total Gross Profit:", '$' . number_format($summaryData['total_profit'], 2)]);
    fputcsv($output, []); // Empty line separator

    // Data Headers & Rows
    if (!empty($results)) {
        $headers = array_keys($results[0]);
        $formattedHeaders = array_map(function($hdr){ return ucwords(str_replace('_', ' ', $hdr)); }, $headers);
        fputcsv($output, $formattedHeaders);
        foreach ($results as $row) { fputcsv($output, $row); } // Output raw data for CSV
    } else {
        fputcsv($output, ["No sales data found for this period/filter."]);
    }
    fclose($output);
    exit;
}

// --- JSON Output for Web Display (SINGLE CORRECT BLOCK) ---
else {
    header('Content-Type: application/json');
    if ($dbError) {
        // Send summary data even on DB error if it was calculated before error
        echo json_encode(['success' => false, 'message' => $dbError, 'summary_data' => $summaryData, 'raw_results' => []]);
        exit;
    }

    // Build HTML Table
    $htmlTable = '';
    if (empty($results)) {
        $htmlTable = '<p class="no-sales-message">No sales data found for this period/filter.</p>';
    } else {
        $htmlTable .= '<table id="report-data-table">';
        $htmlTable .= '<thead><tr>';
        $headers = array_keys($results[0]);
        foreach ($headers as $header) { $htmlTable .= '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $header))) . '</th>'; }
        $htmlTable .= '</tr></thead><tbody>';
        foreach ($results as $row) {
            $htmlTable .= '<tr>';
            foreach ($row as $key => $value) {
                $displayValue = ($value === null || $value === '') ? '<span style="color:#999;">N/A</span>' : htmlspecialchars($value);
                $textAlign = '';
                 if (strpos($key, 'time') !== false && $value) { try { $d = new DateTime($value); $displayValue = $d->format('Y-m-d H:i:s'); } catch(Exception $ex) {$displayValue = htmlspecialchars($value);} }
                 elseif (is_numeric($value) && (strpos($key, 'total') !== false || strpos($key, 'amount') !== false || strpos($key, 'profit') !== false || strpos($key, 'price') !== false || strpos($key, 'cost') !== false)) { $displayValue = '$' . number_format(floatval($value), 2); $textAlign = ' style="text-align: right;"';}
                 elseif (strpos($key, 'quantity') !== false || strpos($key, 'count') !== false || strpos($key, '_id') !== false) { $textAlign = ' style="text-align: center;"'; $displayValue = is_numeric($value) ? intval($value) : htmlspecialchars($value ?? 'N/A'); }
                 elseif (strpos($key, 'unit_profit') !== false && is_numeric($value)) { $displayValue = '$' . number_format(floatval($value), 2); $textAlign = ' style="text-align: right;"';}
                $htmlTable .= "<td{$textAlign}>" . $displayValue . '</td>';
            }
            $htmlTable .= '</tr>';
        }
        $htmlTable .= '</tbody></table>';
    }

     // Prepare summary string
     $summary = sprintf("Sales Count: %d | Items Sold: %d | Revenue: $%s | Cost: $%s | Gross Profit: $%s",
         $summaryData['total_sales_count'],
         $summaryData['total_items_sold'],
         number_format($summaryData['total_revenue'], 2),
         number_format($summaryData['total_cost'], 2),
         number_format($summaryData['total_profit'], 2)
     );

    // Send all relevant data back to JavaScript
    echo json_encode([
        'success' => true,
        'report_title' => $reportTitle,        // For display
        'html_table' => $htmlTable,            // For display
        'summary' => $summary,                 // For display
        'has_data' => !empty($results),        // For enabling buttons
        'raw_results' => $results,             // For charting
        'summary_data' => $summaryData,        // For charting & recommendations
        'report_type' => $reportType          // For chart logic
    ]);
    exit;
}
// --- NO CODE SHOULD BE BELOW THIS LINE ---
?>

