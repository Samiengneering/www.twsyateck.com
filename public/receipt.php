<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TwsyaTech POS - Receipt</title>
    <style>
        /* Basic Receipt Styling - Focus on Print */
        body {
            font-family: 'Courier New', Courier, monospace; /* Monospaced font */
            font-size: 10pt; /* Small font size typical for receipts */
            margin: 0;
            padding: 10px;
            width: 300px; /* Approximate width of thermal receipt paper */
            background-color: #fff; /* Ensure white background for printing */
            color: #000;
        }
        .receipt-container {
            width: 100%;
            margin: 0 auto;
        }
        h1, h2, h3, p, div {
            margin: 0 0 5px 0;
            padding: 0;
        }
        h1 {
            text-align: center;
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        .header-info, .footer-info {
            text-align: center;
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        hr {
            border: none;
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95em;
        }
        th, td {
            padding: 2px 0;
            text-align: left;
            vertical-align: top;
        }
        th {
             border-bottom: 1px dashed #000;
             font-weight: bold;
        }
        .col-qty { width: 15%; text-align: right; padding-right: 5px;}
        .col-price { width: 25%; text-align: right; padding-right: 5px;}
        .col-total { width: 30%; text-align: right; }
        .line-item td:first-child { /* Product name */
             word-break: break-word; /* Allow long names to wrap */
        }
        .totals-section {
            margin-top: 10px;
        }
        .totals-section div {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            padding: 2px 0;
        }
        .print-button-container {
            text-align: center;
            margin-top: 20px;
        }
        /* Hide elements only for printing */
        @media print {
            body { margin: 0; padding: 0;} /* Remove body margin/padding for print */
            .print-button-container { display: none; }
            a { text-decoration: none; color: #000; } /* Make links look like text */
            @page {
                 margin: 0.5cm; /* Adjust print margins */
                 size: 80mm auto; /* Approximate thermal printer roll width */
            }
        }
    </style>
    <!-- No misplaced code here -->
</head>
<body>
    <div class="receipt-container" id="receipt-content">
        <!-- Content will be loaded here by JavaScript -->
        <p style="text-align: center;">Loading receipt...</p>
    </div>

    <div class="print-button-container">
        <button onclick="window.print();">Print Again</button>
        <button onclick="window.close();">Close Window</button>
         <p style="font-size: 0.8em; margin-top: 10px;"><a href="checkout.php" target="_blank">New Sale</a></p>
    </div>

    <script>
        function formatPrice(price) {
            // Ensure price is a number and format to 2 decimal places
            const numPrice = parseFloat(price);
            return isNaN(numPrice) ? '0.00' : numPrice.toFixed(2);
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            try {
                const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
                const date = new Date(dateString);
                return date.toLocaleString(undefined, options); // Use browser's locale settings
            } catch (e) {
                console.error("Error formatting date:", dateString, e); // Log error
                return dateString; // Return original if parsing fails
            }
        }

        async function loadReceiptData() {
            const receiptContentDiv = document.getElementById('receipt-content');
            const urlParams = new URLSearchParams(window.location.search);
            const saleId = urlParams.get('sale_id');

            if (!saleId) {
                receiptContentDiv.innerHTML = '<p style="color: red; text-align: center;">Error: No Sale ID provided.</p>';
                return;
            }

            try {
                const response = await fetch(`../src/actions/fetch_receipt_data.php?sale_id=${saleId}`);
                if (!response.ok) {
                    let errorMsg = `Network response was not ok (status: ${response.status})`;
                    try {
                        const errorData = await response.json();
                        if (errorData && errorData.message) { errorMsg += `: ${errorData.message}`; }
                    } catch (parseError) { /* Ignore */ }
                    throw new Error(errorMsg);
                }
                const data = await response.json();

                if (data.success && data.sale_details && data.sale_items) {
                    const details = data.sale_details;
                    const items = data.sale_items;

                    let itemsHtml = '';
                    items.forEach(item => {
                        const lineTotal = parseFloat(item.quantity || 0) * parseFloat(item.price_at_sale || 0);
                        itemsHtml += `
                            <tr class="line-item">
                                <td>${escapeHTML(item.product_name || 'N/A')}</td>
                                <td class="col-qty">${parseInt(item.quantity || 0)}</td>
                                <td class="col-price">$${formatPrice(item.price_at_sale)}</td>
                                <td class="col-total">$${formatPrice(lineTotal)}</td>
                            </tr>
                        `;
                    });

                    const receiptHtml = `
                        <h1>TwsyaTech POS</h1>
                        <div class="header-info">
                            <div>Store Address Line 1</div>
                            <div>Phone: (123) 456-7890</div>
                            <hr style="margin: 5px 0;">
                            <div>Sale ID: ${escapeHTML(saleId)}</div>
                            <div>Date: ${formatDate(details.sale_time)}</div>
                            <div>Cashier: ${escapeHTML(details.cashier_name || 'N/A')}</div>
                        </div>
                        <hr>
                        <table>
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="col-qty">Qty</th>
                                    <th class="col-price">Price</th>
                                    <th class="col-total">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHtml}
                            </tbody>
                        </table>
                        <hr>
                        <div class="totals-section">
                            <div>
                                <span>TOTAL:</span>
                                <span>$${formatPrice(details.total_amount)}</span>
                            </div>
                            <!-- Add Tax, Discount lines here if needed -->
                        </div>
                        <hr>
                        <div class="footer-info">
                            Thank you for shopping!
                            <div>www.twsyatech.com</div>
                        </div>
                    `;

                    receiptContentDiv.innerHTML = receiptHtml;

                    setTimeout(() => { window.print(); }, 100);

                } else {
                    receiptContentDiv.innerHTML = `<p style="color: red; text-align: center;">Error: ${data.message || 'Could not load receipt data.'}</p>`;
                }

            } catch (error) {
                console.error('Failed to fetch receipt data:', error);
                receiptContentDiv.innerHTML = `<p style="color: red; text-align: center;">Error loading receipt: ${error.message}. Please check console.</p>`;
            }
        }

         function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(String(str)));
            return div.innerHTML;
         }

        window.onload = loadReceiptData;

    </script>
</body>
</html>
<!-- DUPLICATE JAVASCRIPT FRAGMENT REMOVED FROM HERE -->