<?php
// This block MUST be at the very top
session_start(); // Start session FIRST
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; } // Check login
// Optional: Role check for manager access
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') { die("Access Denied."); }

// Define variable needed by the header include
$adminFullName = $_SESSION['full_name'] ?? 'Admin'; // Define variable expected by header

// --- Fetch Cashier List for Filtering ---
require_once '../src/config/database.php'; // $pdo is available
$cashiers = [];
$cashierFetchError = null;
try {
    $stmt = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name ASC");
    $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC); // Explicit FETCH_ASSOC
} catch (PDOException $e) {
    error_log("Fetch Cashiers Error (Reports Page): " . $e->getMessage());
    $cashierFetchError = "Could not load cashier list.";
}
// --- END: Fetch Cashier List ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports & Analysis - TwsyaTech POS</title>
    <link rel="stylesheet" href="css/style.css"> <!-- Link single stylesheet -->
    <!-- Include Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <!-- Optional: Date adapter for better time scales (if needed later) -->
    <!-- <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script> -->
    <style>
        /* Report specific styles - Move to style.css if preferred */
        .report-options { margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;}
        .report-options div { margin-bottom: 10px; }
        .report-options label { margin-bottom: 0; margin-right: 5px;}
        .report-options select, .report-options input[type="date"], .report-options input[type="month"], .report-options input[type="number"] { width: auto; min-width: 150px; padding: 8px; vertical-align: bottom;}
        .report-results { margin-top: 20px; }
        #report-table-container { max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; margin-top: 10px;}
        .report-summary-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; flex-wrap: wrap; gap: 10px; padding: 10px 0; border-top: 1px solid #eee;}
        #report-summary { font-weight: bold; text-align: left; font-size: 1.1em; flex-grow: 1; margin: 0; }
        #analyze-download-btn, #download-pdf-btn { display: none; white-space: nowrap; margin-left: 10px; /* Add margin between buttons */ } /* Hide initially */
        #analyze-download-btn { background-color: #17a2b8; }
        #analyze-download-btn:hover { background-color: #138496; }
        #download-pdf-btn { background-color: #dc3545; } /* Example: Red PDF button */
        #download-pdf-btn:hover:not(:disabled) { background-color: #c82333; }
        #download-area { display: none; text-align: right; }

        /* Chart Styles */
        .charts-grid { display: none; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); /* Adjusted minmax */ gap: 20px; margin-top: 30px; }
        .chart-container { padding: 20px; background-color: #fff; border: 1px solid #dee2e6; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .chart-container h4 { margin-top: 0; margin-bottom: 15px; text-align: center; color: #495057; }
        .chart-canvas-container { position: relative; height: 300px; width: 100%; }
        .chart-download-link { display: none; margin-top: 10px; text-align: right; font-size: 0.85em; }
        .chart-download-link a { padding: 3px 8px; background-color: #17a2b8; color: white; border-radius: 3px; text-decoration: none; }
        .chart-download-link a:hover { background-color: #138496; }
        .chart-placeholder-message { text-align: center; color: #6c757d; padding: 20px; font-style: italic; }

         /* Recommendations Styles */
        .recommendations { display: none; margin-top: 30px; padding: 15px; background-color: #e9ecef; border: 1px solid #ced4da; border-radius: 5px; }
        .recommendations h4 { margin-top: 0; margin-bottom: 10px; color: #495057;}
        .recommendations ul { padding-left: 20px; margin-bottom: 0;}
        .recommendations li { margin-bottom: 5px; font-size: 0.95em;}

        /* Add container styling */
        .container { max-width: 1300px; /* Slightly wider for more charts */ margin: 20px auto; padding: 0 15px; }

    </style>
</head>
<body>
    <?php include '../src/includes/admin_header.php'; // Include the common admin header ?>

    <div class="container"> <!-- Added container for centering and padding -->

        <div style="display: flex; justify-content: space-between; align-items: center;">
             <h1>Sales Reports & Analysis</h1>
             <!-- Optional: Add Company Name/Logo -->
        </div>

        <!-- Report Filtering Options Form -->
        <div class="report-options">
             <div>
                <label for="report_type">Report Type:</label>
                <select id="report_type" name="report_type">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="yearly">Yearly Summary</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            <!-- Date Selectors -->
            <div id="date-selector-daily">
                 <label for="report_date">Date:</label>
                 <input type="date" id="report_date" name="report_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div id="date-selector-weekly" style="display: none;">
                 <label for="report_week">Week Starting (Mon):</label>
                 <input type="date" id="report_week" name="report_week" value="<?php echo date('Y-m-d', strtotime('last monday')); ?>">
            </div>
            <div id="date-selector-monthly" style="display: none;">
                <label for="report_month">Month:</label>
                <input type="month" id="report_month" name="report_month" value="<?php echo date('Y-m'); ?>">
            </div>
             <div id="date-selector-yearly" style="display: none;">
                 <label for="report_year">Year:</label>
                 <input type="number" id="report_year" name="report_year" min="2000" max="<?php echo date('Y'); ?>" value="<?php echo date('Y'); ?>">
             </div>
            <div id="date-selector-custom" style="display: none;">
                <label for="start_date">Start:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo date('Y-m-01'); ?>">
                <label for="end_date" style="margin-left: 10px;">End:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <!-- Cashier Filter Dropdown -->
            <div>
                <label for="cashier_filter">Cashier:</label>
                <select id="cashier_filter" name="cashier_id">
                    <option value="">-- All Cashiers --</option>
                    <?php if ($cashierFetchError): ?>
                        <option value="" disabled><?php echo htmlspecialchars($cashierFetchError); ?></option>
                    <?php elseif (empty($cashiers)): ?>
                         <option value="" disabled>No users found</option>
                    <?php else: ?>
                        <?php foreach ($cashiers as $cashier): ?>
                            <option value="<?php echo htmlspecialchars($cashier['id']); ?>">
                                <?php echo htmlspecialchars($cashier['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <!-- Generate Button -->
            <div>
                 <button type="button" id="generate-report-btn">Generate Report</button>
            </div>
        </div>

        <!-- Area to Display Report Results -->
        <div class="report-results">
            <h3 id="report-title-display">Report Results</h3>
            <div id="report-status" class="status-message" style="display: none;"></div>
            <!-- Summary and Action Buttons Area -->
            <div class="report-summary-actions">
                <div id="report-summary">Select options and generate report to see summary.</div>
                <!-- Container for the download buttons -->
                <div id="download-area">
                     <button type="button" id="analyze-download-btn">Analyze Data (Download CSV)</button>
                     <button type="button" id="download-pdf-btn" disabled title="Generate a report first">Download PDF</button>
                </div>
            </div>
            <!-- Table Container -->
            <div id="report-table-container">
                <p class="no-sales-message">Select report options and click Generate.</p>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="charts-grid" id="charts-section">
            <!-- Chart 1: Revenue/Profit Summary -->
            <div class="chart-container" id="revenue-profit-chart-container">
                <h4>Revenue vs Profit Summary</h4>
                <div class="chart-canvas-container"><canvas id="chartRevenueProfit"></canvas></div>
                <div class="chart-download-link" id="dl-chartRevenueProfit"><a href="#" download="chart_revenue_profit.png">Download Chart</a></div>
            </div>
            <!-- Chart 2: Top Products by Quantity -->
            <div class="chart-container" id="product-qty-chart-container">
                <h4>Top Products by Quantity</h4>
                <div class="chart-canvas-container"><canvas id="chartProductQty"></canvas></div>
                 <p class="chart-placeholder-message" style="display:none;">Not applicable for Yearly Summary.</p>
                <div class="chart-download-link" id="dl-chartProductQty"><a href="#" download="chart_product_qty.png">Download Chart</a></div>
            </div>
            <!-- Chart 3: Top Products by Revenue -->
            <div class="chart-container" id="product-revenue-chart-container">
                <h4>Top Products by Revenue</h4>
                <div class="chart-canvas-container"><canvas id="chartProductRevenue"></canvas></div>
                 <p class="chart-placeholder-message" style="display:none;">Not applicable for Yearly Summary.</p>
                <div class="chart-download-link" id="dl-chartProductRevenue"><a href="#" download="chart_product_revenue.png">Download Chart</a></div>
            </div>
             <!-- Chart 4: Revenue Trend (Line Chart) -->
             <div class="chart-container" id="revenue-trend-chart-container">
                <h4>Revenue Trend Over Period</h4>
                <div class="chart-canvas-container"><canvas id="chartRevenueTrend"></canvas></div>
                <p class="chart-placeholder-message" style="display:none;">Trend data requires a Weekly, Monthly, Yearly, or Custom range report.</p>
                <div class="chart-download-link" id="dl-chartRevenueTrend"><a href="#" download="chart_revenue_trend.png">Download Chart</a></div>
            </div>
            <!-- Chart 5: Sales by Cashier -->
            <div class="chart-container" id="cashier-chart-container">
                <h4>Sales by Cashier</h4>
                <div class="chart-canvas-container"><canvas id="chartCashierSales"></canvas></div>
                 <p class="chart-placeholder-message" style="display:none;">Not applicable for Yearly Summary or when a specific cashier is selected.</p>
                <div class="chart-download-link" id="dl-chartCashierSales"><a href="#" download="chart_cashier_sales.png">Download Chart</a></div>
            </div>
            <!-- Add more chart containers here if needed -->
        </div> <!-- End charts-grid -->

        <!-- Recommendations Section -->
        <div class="recommendations" id="recommendations-section" style="display: none;">
             <h4>Recommendations Based on Report</h4>
             <ul id="recommendation-list">
                 <li>Generate a report to see recommendations.</li>
             </ul>
         </div>

        <p style="margin-top: 20px;"><a href="admin.php">Back to Admin Dashboard</a></p>

    </div> <!-- End Container -->

    <!-- >>> SINGLE SCRIPT BLOCK AT THE END <<< -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- Element References ---
            const reportTypeSelect = document.getElementById('report_type');
            const dateSelectors = { /* ... same as before ... */
                daily: document.getElementById('date-selector-daily'),
                weekly: document.getElementById('date-selector-weekly'),
                monthly: document.getElementById('date-selector-monthly'),
                yearly: document.getElementById('date-selector-yearly'),
                custom: document.getElementById('date-selector-custom'),
            };
            const generateBtn = document.getElementById('generate-report-btn');
            const reportStatusDiv = document.getElementById('report-status');
            const reportTableContainer = document.getElementById('report-table-container');
            const reportSummaryDiv = document.getElementById('report-summary');
            const downloadArea = document.getElementById('download-area');
            const analyzeDownloadBtn = document.getElementById('analyze-download-btn');
            const downloadPdfBtn = document.getElementById('download-pdf-btn');
            const reportTitleDisplay = document.getElementById('report-title-display');
            const cashierFilterSelect = document.getElementById('cashier_filter');
            // Chart/Rec Refs
            const chartsGrid = document.getElementById('charts-section');
            const recommendationsSection = document.getElementById('recommendations-section');
            const recommendationList = document.getElementById('recommendation-list');
            // Chart Canvases
            const chartCanvasRevenueProfit = document.getElementById('chartRevenueProfit');
            const chartCanvasProductQty = document.getElementById('chartProductQty');
            const chartCanvasProductRevenue = document.getElementById('chartProductRevenue');
            const chartCanvasRevenueTrend = document.getElementById('chartRevenueTrend'); // <-- New Canvas
            const chartCanvasCashierSales = document.getElementById('chartCashierSales');
            // Chart Containers (for showing/hiding placeholders)
            const revenueProfitChartContainer = document.getElementById('revenue-profit-chart-container');
            const productQtyChartContainer = document.getElementById('product-qty-chart-container');
            const productRevenueChartContainer = document.getElementById('product-revenue-chart-container');
            const revenueTrendChartContainer = document.getElementById('revenue-trend-chart-container'); // <-- New Container
            const cashierChartContainer = document.getElementById('cashier-chart-container');

            const chartDownloadLinks = document.querySelectorAll('.chart-download-link a');
            const chartPlaceholders = document.querySelectorAll('.chart-placeholder-message');

            // --- State Variables ---
            let currentReportParams = null;
            let chartInstances = {};
            let currentRawResults = [];
            let currentSummaryData = {};
            let currentReportType = '';

            // --- Function to Show/Hide Date Inputs ---
            function toggleDateSelectors() {
                const selectedType = reportTypeSelect.value;
                Object.values(dateSelectors).forEach(el => { if (el) el.style.display = 'none'; });
                if (dateSelectors[selectedType]) { dateSelectors[selectedType].style.display = 'block'; }
            }

            // --- Function to Reset UI Elements Before Report Generation ---
            function resetUIBeforeGeneration() {
                reportStatusDiv.textContent = 'Generating report...';
                reportStatusDiv.className = 'status-message status-info';
                reportStatusDiv.style.display = 'block';
                reportTableContainer.innerHTML = '<p class="no-sales-message">Loading report data...</p>';
                reportSummaryDiv.textContent = '';
                reportTitleDisplay.textContent = 'Generating Report...';
                downloadArea.style.display = 'none';
                if(analyzeDownloadBtn) analyzeDownloadBtn.style.display = 'none';
                if(downloadPdfBtn) {
                     downloadPdfBtn.style.display = 'none';
                     downloadPdfBtn.disabled = true; // Ensure it's disabled
                }
                chartsGrid.style.display = 'none'; // Hide the whole grid initially
                chartPlaceholders.forEach(p => p.style.display = 'none'); // Hide all placeholders
                recommendationsSection.style.display = 'none';
                destroyAllCharts(); // Destroy existing charts
                generateBtn.disabled = true;
                currentReportParams = null; currentRawResults = []; currentSummaryData = {}; currentReportType = '';
            }

            // --- Function to Destroy ALL Existing Charts ---
            function destroyAllCharts() {
                Object.values(chartInstances).forEach(chart => { if (chart) chart.destroy(); });
                chartInstances = {};
                chartDownloadLinks.forEach(link => { if(link.parentElement) link.parentElement.style.display = 'none'; link.href = '#'; link.onclick = (e) => e.preventDefault(); });
            }

            // --- Function to Generate Report via AJAX ---
            async function generateReport() {
                // 1. Set Loading State & Reset UI
                resetUIBeforeGeneration();

                // 2. Gather Parameters
                const reportType = reportTypeSelect.value;
                const selectedCashierId = cashierFilterSelect.value;
                const params = { report_type: reportType };
                let periodValid = true;
                switch (reportType) {
                    case 'daily':   params.date = dateSelectors.daily.querySelector('input').value; if (!params.date) periodValid = false; break;
                    case 'weekly':  params.date = dateSelectors.weekly.querySelector('input').value; if (!params.date) periodValid = false; break;
                    case 'monthly': params.month = dateSelectors.monthly.querySelector('input').value; if (!params.month) periodValid = false; break;
                    case 'yearly':  params.year = dateSelectors.yearly.querySelector('input').value; if (!params.year || !/^\d{4}$/.test(params.year)) periodValid = false; break;
                    case 'custom':
                        params.start_date = dateSelectors.custom.querySelector('#start_date').value;
                        params.end_date = dateSelectors.custom.querySelector('#end_date').value;
                        if (!params.start_date || !params.end_date || params.start_date > params.end_date) periodValid = false; break;
                }
                if (selectedCashierId) params.cashier_id = selectedCashierId;

                if (!periodValid) {
                     reportStatusDiv.textContent = 'Please select a valid date/range/year.';
                     reportStatusDiv.className = 'status-message status-error';
                     reportTableContainer.innerHTML = '<p class="no-sales-message">Invalid period selected.</p>';
                     generateBtn.disabled = false; return;
                }
                currentReportParams = {...params};
                currentReportType = reportType; // Store report type for chart logic

                // 3. Fetch Data
                try {
                    const queryParams = new URLSearchParams(params).toString();
                    const response = await fetch(`../src/actions/generate_report.php?${queryParams}`);
                    if (!response.ok) { let ed = `Network error: ${response.status}`; try { const d = await response.json(); ed += ` - ${d.message||'?'}`; } catch(e){} throw new Error(ed); }
                    const data = await response.json();

                    // 4. Process Response
                    if (data.success) {
                        reportStatusDiv.style.display = 'none';
                        reportTableContainer.innerHTML = data.html_table || '<p class="no-sales-message">No sales data found for this period/filter.</p>';
                        reportSummaryDiv.textContent = data.summary || '';
                        reportTitleDisplay.textContent = data.report_title || 'Report Results';
                        currentRawResults = data.raw_results || [];
                        currentSummaryData = data.summary_data || {};
                        // currentReportType = data.report_type || ''; // Backend should also return this ideally

                        if (data.has_data && currentRawResults.length > 0) {
                            downloadArea.style.display = 'flex';
                             if(analyzeDownloadBtn) analyzeDownloadBtn.style.display = 'inline-block';
                             if(downloadPdfBtn) downloadPdfBtn.disabled = false;
                             if(downloadPdfBtn) downloadPdfBtn.style.display = 'inline-block';

                            chartsGrid.style.display = 'grid'; // Show the grid now
                            recommendationsSection.style.display = 'block';
                            generateRecommendations(); // Consider moving after charts if it depends on them

                            // Create Charts Conditionally
                            createRevenueProfitChart();
                            createProductQtyChart();
                            createProductRevenueChart();
                            createRevenueTrendChart(); // <-- Call the new chart function
                            createCashierSalesChart();

                        } else if (data.has_data && currentRawResults.length === 0 && currentReportType === 'yearly') {
                            // Handle Yearly Summary case (might have summary but no raw item data)
                             downloadArea.style.display = 'flex';
                             if(analyzeDownloadBtn) analyzeDownloadBtn.style.display = 'inline-block'; // Allow CSV of summary?
                             if(downloadPdfBtn) downloadPdfBtn.disabled = false;
                             if(downloadPdfBtn) downloadPdfBtn.style.display = 'inline-block'; // Allow PDF

                             chartsGrid.style.display = 'grid'; // Show grid
                             recommendationsSection.style.display = 'block';
                             generateRecommendations();

                             createRevenueProfitChart(); // Only this one might work
                             // Show placeholders for others
                             showPlaceholder(productQtyChartContainer, true);
                             showPlaceholder(productRevenueChartContainer, true);
                             showPlaceholder(revenueTrendChartContainer, false); // Trend is applicable for yearly
                             showPlaceholder(cashierChartContainer, true); // Assuming no cashier data for yearly

                             // Attempt yearly trend chart if applicable
                             createRevenueTrendChart();


                        } else {
                            // No data found message already in table container
                            reportSummaryDiv.textContent = 'No sales data found for this period/filter.';
                        }
                    } else {
                         reportStatusDiv.textContent = data.message || 'Failed to generate report.';
                         reportStatusDiv.className = 'status-message status-error';
                         reportTitleDisplay.textContent = 'Report Generation Failed';
                         reportTableContainer.innerHTML = `<p class="message-error" style="text-align:center;">${data.message || 'Failed to generate report.'}</p>`;
                    }
                } catch (error) {
                    console.error("Report Generation Fetch/JSON Error:", error);
                    reportStatusDiv.textContent = `Error generating report: ${error.message}. Check console.`;
                    reportStatusDiv.className = 'status-message status-error';
                    reportTitleDisplay.textContent = 'Report Generation Error';
                    reportTableContainer.innerHTML = `<p class="message-error" style="text-align:center;">An error occurred. Please check the console.</p>`;
                } finally {
                     generateBtn.disabled = false;
                }
            }

            // --- Function to Trigger CSV Download ---
             function downloadCSV() { /* ... same as before ... */
                 if (!currentReportParams) { alert("Please generate a report first."); return; }
                 const downloadParams = { ...currentReportParams, format: 'csv' };
                 const queryParams = new URLSearchParams(downloadParams).toString();
                 window.location.href = `../src/actions/generate_report.php?${queryParams}`;
             }

            // --- Function to Trigger PDF Download ---
             function downloadPDF() { /* ... same as before ... */
                 if (!currentReportParams) { alert("Please generate a report first."); return; }
                 const downloadParams = { ...currentReportParams, format: 'pdf' };
                 const queryParams = new URLSearchParams(downloadParams).toString();
                 window.open(`../src/actions/generate_report.php?${queryParams}`, '_blank');
             }

             // --- Helper Function to Show/Hide Chart Placeholder Message ---
             function showPlaceholder(containerElement, isYearlyIssue = false) {
                if (!containerElement) return;
                const placeholder = containerElement.querySelector('.chart-placeholder-message');
                const canvasContainer = containerElement.querySelector('.chart-canvas-container');
                const downloadLink = containerElement.querySelector('.chart-download-link');

                if (placeholder) placeholder.style.display = 'block';
                if (canvasContainer) canvasContainer.style.display = 'none'; // Hide canvas area
                if (downloadLink) downloadLink.style.display = 'none'; // Hide download link

                // Customize message slightly
                if (placeholder && isYearlyIssue) {
                    placeholder.textContent = "Not applicable for Yearly Summary view.";
                } else if (placeholder) {
                    // Default messages set in HTML are used
                }
             }

             // --- Helper Function to Show Chart Area (Hide Placeholder) ---
              function showChartArea(containerElement) {
                 if (!containerElement) return;
                 const placeholder = containerElement.querySelector('.chart-placeholder-message');
                 const canvasContainer = containerElement.querySelector('.chart-canvas-container');
                 // Download link visibility is handled by setupChart

                 if (placeholder) placeholder.style.display = 'none';
                 if (canvasContainer) canvasContainer.style.display = 'block';
              }


            // --- Chart Generation Functions ---
            function getRandomColor() { const r=Math.floor(Math.random()*200), g=Math.floor(Math.random()*200), b=Math.floor(Math.random()*200); return `rgb(${r},${g},${b})`; }

            function setupChart(canvasId, chartConfig) {
                const containerElement = document.getElementById(canvasId)?.closest('.chart-container');
                if (chartInstances[canvasId]) { chartInstances[canvasId].destroy(); }
                const canvas = document.getElementById(canvasId);
                const downloadLinkContainer = document.getElementById('dl-' + canvasId);

                if (!canvas || !chartConfig || !containerElement) {
                    console.error("Canvas, config, or container missing for", canvasId);
                    if(downloadLinkContainer) downloadLinkContainer.style.display = 'none';
                    showPlaceholder(containerElement); // Show placeholder if setup fails early
                    return null;
                }

                try {
                    showChartArea(containerElement); // Ensure canvas area is visible
                    const ctx = canvas.getContext('2d');
                    const newChart = new Chart(ctx, chartConfig);
                    chartInstances[canvasId] = newChart;

                    // Setup download link
                    if (downloadLinkContainer) {
                        const link = downloadLinkContainer.querySelector('a');
                        if (link) {
                            link.onclick = (e) => {
                                e.preventDefault();
                                try {
                                     if (chartInstances[canvasId]) {
                                        link.href = chartInstances[canvasId].toBase64Image();
                                        const title = chartConfig.options?.plugins?.title?.text || canvasId.replace('chart', '');
                                        link.download = `chart_${title.toLowerCase().replace(/[^a-z0-9]+/g, '_')}.png`;
                                        link.click();
                                     } else { alert('Chart instance not found.'); }
                                } catch (imgError) { console.error("Error generating chart image:", imgError); alert("Could not generate chart image."); }
                            };
                            downloadLinkContainer.style.display = 'block'; // Show download link
                        } else {
                             downloadLinkContainer.style.display = 'none';
                        }
                    } else {
                        console.warn("Download link container not found for", canvasId);
                    }
                    return newChart;
                } catch (chartError) {
                     console.error("Error creating chart:", canvasId, chartError);
                     if(downloadLinkContainer) downloadLinkContainer.style.display = 'none';
                     const canvasContainer = canvas.parentElement;
                     if(canvasContainer) canvasContainer.innerHTML = '<p style="color:red; text-align:center; padding: 20px;">Could not render chart.</p>';
                     showPlaceholder(containerElement); // Show placeholder on error
                     return null;
                }
            }

            // --- Specific Chart Creation Functions ---
            function createRevenueProfitChart(canvasId='chartRevenueProfit') {
                const container = revenueProfitChartContainer; // Use specific container ref
                if (!currentSummaryData || typeof currentSummaryData.total_revenue !== 'number') {
                    console.warn("Rev/Profit chart: Summary data missing.");
                    showPlaceholder(container); return;
                }
                const config = { type: 'bar', data: { labels: ['Metrics'], datasets: [ { label: 'Revenue', data: [currentSummaryData.total_revenue || 0], backgroundColor: 'rgba(75, 192, 192, 0.6)' }, { label: 'Cost', data: [currentSummaryData.total_cost || 0], backgroundColor: 'rgba(255, 159, 64, 0.6)' }, { label: 'Profit', data: [currentSummaryData.total_profit || 0], backgroundColor: 'rgba(153, 102, 255, 0.6)' } ] }, options: { responsive: true, maintainAspectRatio: false, plugins: { title: { display: true, text: 'Revenue vs Cost vs Profit' }}, scales: { y: { beginAtZero: true, ticks: { callback: v => '$' + v.toFixed(2) } } } } };
                setupChart(canvasId, config);
            }
            function createProductQtyChart(canvasId='chartProductQty') {
                 const container = productQtyChartContainer;
                 if (currentReportType === 'yearly') { showPlaceholder(container, true); return; } // Show yearly message
                 if (!currentRawResults || currentRawResults.length === 0) { showPlaceholder(container); return; }

                 const productQty = {}; currentRawResults.forEach(i => { productQty[i.product_name||'?'] = (productQty[i.product_name||'?']||0) + (parseInt(i.quantity)||0); });
                 const sorted = Object.entries(productQty).sort(([,a],[,b]) => b-a).slice(0, 10);
                 if (sorted.length === 0) { showPlaceholder(container); return; }

                 const config = { type: 'bar', data: { labels: sorted.map(([n,])=>n), datasets: [{ label: 'Quantity Sold', data: sorted.map(([,q])=>q), backgroundColor: sorted.map(() => getRandomColor()) }] }, options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { title: { display: true, text: 'Top 10 Products by Quantity' } }, scales: { x: { beginAtZero: true } } } };
                 setupChart(canvasId, config);
            }
             function createProductRevenueChart(canvasId='chartProductRevenue') {
                 const container = productRevenueChartContainer;
                 if (currentReportType === 'yearly') { showPlaceholder(container, true); return; }
                 if (!currentRawResults || currentRawResults.length === 0) { showPlaceholder(container); return; }

                 const productRevenue = {}; currentRawResults.forEach(i => { productRevenue[i.product_name||'?'] = (productRevenue[i.product_name||'?']||0) + (parseFloat(i.item_total_revenue)||0); });
                 const sorted = Object.entries(productRevenue).sort(([,a],[,b]) => b-a).slice(0, 10);
                 if (sorted.length === 0) { showPlaceholder(container); return; }

                 const config = { type: 'pie', data: { labels: sorted.map(([n,])=>n), datasets: [{ label: 'Total Revenue', data: sorted.map(([,r])=>r.toFixed(2)), backgroundColor: sorted.map(() => getRandomColor()), hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { title: { display: true, text: 'Top 10 Products by Revenue' }, tooltip: { callbacks: { label: ctx => `${ctx.label}: $${ctx.parsed.toFixed(2)}` } } } } };
                 setupChart(canvasId, config);
            }

             // --- NEW: Aggregate Data for Trend Chart ---
             function aggregateDataForTrend() {
                if (!currentRawResults || currentRawResults.length === 0) return []; // Need raw data for this client-side aggregation

                const aggregated = {};
                let timeUnit = 'day'; // Default: daily aggregation for weekly, monthly, custom

                if (currentReportType === 'yearly') {
                    timeUnit = 'month';
                } else if (currentReportType === 'daily') {
                     return []; // Not enough data points for a trend line on a single day
                }

                currentRawResults.forEach(item => {
                     // Ensure sale_date exists and item_total_revenue is a number
                    if (!item.sale_date || isNaN(parseFloat(item.item_total_revenue))) {
                        console.warn("Skipping item due to missing date or invalid revenue:", item);
                        return;
                    }

                    const saleDate = new Date(item.sale_date + 'T00:00:00'); // Ensure date is parsed correctly, add time to avoid timezone issues if only date is present
                    if (isNaN(saleDate.getTime())) { // Check if date parsing failed
                         console.warn("Skipping item due to invalid date format:", item.sale_date);
                         return;
                    }

                    let key;
                    if (timeUnit === 'month') {
                         // Format as YYYY-MM
                         key = saleDate.getFullYear() + '-' + ('0' + (saleDate.getMonth() + 1)).slice(-2);
                    } else { // timeUnit === 'day'
                         // Format as YYYY-MM-DD
                         key = saleDate.toISOString().split('T')[0];
                     }

                     aggregated[key] = (aggregated[key] || 0) + parseFloat(item.item_total_revenue);
                 });

                 // Convert to array and sort
                 const sortedData = Object.entries(aggregated)
                     .map(([period, revenue]) => ({ period, revenue }))
                     .sort((a, b) => a.period.localeCompare(b.period)); // Sort chronologically

                 return sortedData;
             }


             // --- NEW: Revenue Trend Line Chart ---
             function createRevenueTrendChart(canvasId='chartRevenueTrend') {
                const container = revenueTrendChartContainer;
                // Trend chart isn't useful for a single day
                if (currentReportType === 'daily') {
                     showPlaceholder(container);
                     // Customize placeholder text specifically for daily
                     const placeholder = container.querySelector('.chart-placeholder-message');
                     if(placeholder) placeholder.textContent = "Revenue trend requires a report range (Weekly, Monthly, etc.).";
                     return;
                }

                const timeSeriesData = aggregateDataForTrend();

                // Need at least two points for a line
                if (!timeSeriesData || timeSeriesData.length < 2) {
                     console.warn("Revenue Trend Chart: Not enough data points (< 2).");
                     showPlaceholder(container);
                      // Customize placeholder text
                     const placeholder = container.querySelector('.chart-placeholder-message');
                     if(placeholder) placeholder.textContent = "Not enough data points to draw a trend line for this period.";
                     return;
                }

                const labels = timeSeriesData.map(d => d.period);
                const dataPoints = timeSeriesData.map(d => d.revenue.toFixed(2));

                const config = {
                     type: 'line',
                     data: {
                         labels: labels,
                         datasets: [{
                             label: 'Total Revenue',
                             data: dataPoints,
                             borderColor: 'rgb(54, 162, 235)', // Blue line
                             backgroundColor: 'rgba(54, 162, 235, 0.1)', // Optional fill
                             fill: true, // Fill area under the line
                             tension: 0.1 // Slight curve
                         }]
                     },
                     options: {
                         responsive: true,
                         maintainAspectRatio: false,
                         plugins: {
                             title: {
                                 display: true,
                                 text: 'Revenue Trend Over Period' + (currentReportType === 'yearly' ? ' (Monthly)' : ' (Daily)')
                             },
                             tooltip: {
                                callbacks: {
                                    label: ctx => `${ctx.dataset.label}: $${parseFloat(ctx.raw).toFixed(2)}`
                                }
                             }
                         },
                         scales: {
                             y: {
                                 beginAtZero: true,
                                 ticks: {
                                     callback: value => '$' + value.toFixed(2)
                                 }
                             },
                             x: {
                                 title: {
                                     display: true,
                                     text: currentReportType === 'yearly' ? 'Month' : 'Date'
                                 }
                                 // Note: For many dates, labels might overlap.
                                 // Consider using Chart.js time scale & adapter if needed.
                                 // e.g., type: 'time', time: { unit: 'day' or 'month' }
                             }
                         }
                     }
                 };
                 setupChart(canvasId, config);
            }


             function createCashierSalesChart(canvasId='chartCashierSales') {
                 const container = cashierChartContainer;
                 if (currentReportType === 'yearly' || currentReportParams?.cashier_id) {
                     showPlaceholder(container, currentReportType === 'yearly'); // Show yearly or cashier specific message
                      // Customize placeholder text if specific cashier
                     if (currentReportParams?.cashier_id) {
                          const placeholder = container.querySelector('.chart-placeholder-message');
                          if(placeholder) placeholder.textContent = "Cashier chart not shown when a specific cashier is selected.";
                     }
                     return;
                 }
                 if (!currentRawResults || currentRawResults.length === 0) { showPlaceholder(container); return; }

                 const cashierSales = {}; currentRawResults.forEach(i => { cashierSales[i.cashier_name||'?'] = (cashierSales[i.cashier_name||'?']||0) + (parseFloat(i.item_total_revenue)||0); });
                 const sorted = Object.entries(cashierSales).sort(([,a],[,b]) => b-a);
                 if (sorted.length === 0) { showPlaceholder(container); return; }

                 const config = { type: 'bar', data: { labels: sorted.map(([n,])=>n), datasets: [{ label: 'Total Sales Value', data: sorted.map(([,v])=>v.toFixed(2)), backgroundColor: sorted.map(() => getRandomColor()) }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { title: { display: true, text: 'Total Sales Value by Cashier' } }, scales: { y: { beginAtZero: true, ticks: { callback: v => '$' + v.toFixed(2) } } } } };
                 setupChart(canvasId, config);
            }

            // --- Generate Basic Recommendations ---
            function generateRecommendations() {
                if (!currentRawResults || currentRawResults.length === 0 || !currentSummaryData) {
                    recommendationList.innerHTML = '<li>No data available to generate recommendations.</li>';
                    return;
                }

                let recs = [];

                // Recommendation based on Profit Margin
                if (typeof currentSummaryData.total_revenue === 'number' && currentSummaryData.total_revenue > 0 && typeof currentSummaryData.total_profit === 'number') {
                     const profitMargin = (currentSummaryData.total_profit / currentSummaryData.total_revenue) * 100;
                     if (profitMargin < 10) { // Example threshold
                         recs.push("Profit margin is low (<10%). Review product costs and pricing strategies.");
                     } else if (profitMargin > 50) { // Example threshold
                         recs.push("Profit margin is high (>50%). Ensure pricing is competitive.");
                     }
                }

                // Recommendation based on Top Products (if not yearly)
                 if (currentReportType !== 'yearly') {
                     const productRevenue = {};
                     currentRawResults.forEach(i => { productRevenue[i.product_name||'?'] = (productRevenue[i.product_name||'?']||0) + (parseFloat(i.item_total_revenue)||0); });
                     const sortedRevenue = Object.entries(productRevenue).sort(([,a],[,b]) => b-a);

                     if (sortedRevenue.length > 1) {
                        const topProductRevenue = sortedRevenue[0][1];
                        const totalRevenue = currentSummaryData.total_revenue || 1; // Avoid division by zero
                        if ((topProductRevenue / totalRevenue) > 0.5) { // If top product is > 50% revenue
                            recs.push(`"${sortedRevenue[0][0]}" drives a significant portion (>50%) of revenue. Consider diversifying or promoting other products.`);
                        }
                     }
                     if (sortedRevenue.length > 5) {
                          recs.push("Analyze the performance of lower-selling products to see if they should be discontinued or promoted differently.");
                     }
                 }

                 // Recommendation based on Trend (if available)
                 const trendData = aggregateDataForTrend();
                 if (trendData.length >= 2) {
                     const firstVal = trendData[0].revenue;
                     const lastVal = trendData[trendData.length - 1].revenue;
                     if (lastVal < firstVal) {
                         recs.push("Overall revenue trend shows a decrease over the period. Investigate potential causes (e.g., seasonality, competition, marketing).");
                     } else if (lastVal > firstVal * 1.2) { // Example: > 20% increase
                         recs.push("Revenue trend shows strong growth. Identify factors contributing to success and reinforce them.");
                     }
                 }


                if (recs.length === 0) {
                     recs.push("Sales data looks stable. Continue monitoring key metrics.");
                }

                recommendationList.innerHTML = recs.map(r => `<li>${r}</li>`).join('');
             }

            // --- Attach Event Listeners ---
            if (reportTypeSelect) reportTypeSelect.addEventListener('change', toggleDateSelectors);
            if (generateBtn) generateBtn.addEventListener('click', generateReport);
            if (analyzeDownloadBtn) analyzeDownloadBtn.addEventListener('click', downloadCSV);
            if (downloadPdfBtn) downloadPdfBtn.addEventListener('click', downloadPDF);

            // --- Initial Setup ---
            toggleDateSelectors();
             chartPlaceholders.forEach(p => p.style.display = 'none'); // Ensure placeholders are hidden initially

        }); // End DOMContentLoaded
    </script>

</body>
</html>