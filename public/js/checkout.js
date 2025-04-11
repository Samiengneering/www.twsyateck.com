// public/js/checkout.js

document.addEventListener('DOMContentLoaded', () => {
    // --- DOM Element References ---
    const barcodeInput = document.getElementById('barcode-input');
    const cartItemsTableBody = document.querySelector('#cart-items tbody');
    const cartTotalSpan = document.getElementById('cart-total');
    const completeSaleBtn = document.getElementById('complete-sale-btn');
    const productLookupResultDiv = document.getElementById('product-lookup-result');
    const lookupStatusDiv = document.getElementById('lookup-status');
    const saleStatusDiv = document.getElementById('sale-status');
    const quickKeysContainer = document.querySelector('.quick-keys-container'); // Ref for hiding/showing
    const quickKeysGrid = document.getElementById('quick-keys-grid');
    const currentTimeSpan = document.getElementById('current-time');
    const recentSalesTbody = document.getElementById('recent-sales-tbody');

    // --- State Variables ---
    let currentCart = {}; // { productId: { id, name, price, quantity, stock } }
    let lastSearchResults = [];
    let quickKeyProductsData = {};
    let isProcessingLookup = false;
    let isProcessingSale = false;
    let debounceTimer;

    // --- Helper Functions ---
    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    function setLookupStatus(message, type = 'info') {
        if (!lookupStatusDiv) return;
        lookupStatusDiv.textContent = message;
        lookupStatusDiv.className = `status-message status-${type}`;
    }

    function setSaleStatus(message, type = 'info') {
         if (!saleStatusDiv) return;
        saleStatusDiv.textContent = message;
        saleStatusDiv.className = `status-message status-${type}`;
    }

    // Clears search results AND shows Quick Keys
    function clearLookupUI() {
        if (productLookupResultDiv) productLookupResultDiv.innerHTML = '';
        if (lookupStatusDiv) {
            lookupStatusDiv.textContent = '';
            lookupStatusDiv.className = 'status-message';
        }
        lastSearchResults = [];
        // Show Quick Keys when clearing UI
        if (quickKeysContainer) {
            quickKeysContainer.style.display = ''; // Reset display (makes it visible)
        }
    }

    function debounce(func, delay = 300) {
        return function(...args) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => { func.apply(this, args); }, delay);
        };
    }

    // --- Live Clock Function ---
    function updateClock() {
        if (!currentTimeSpan) return;
        const now = new Date();
        const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
        currentTimeSpan.textContent = now.toLocaleString(undefined, options);
    }

    // --- Function to Refresh Recent Sales List via AJAX ---
    async function refreshRecentSales() {
        if (!recentSalesTbody) return;
        try {
            const response = await fetch('../src/actions/get_recent_sales_ajax.php');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            if (data.success && typeof data.html !== 'undefined') {
                recentSalesTbody.innerHTML = data.html;
            } else {
                recentSalesTbody.innerHTML = `<tr><td colspan="3" class="sales-fetch-error">${data.message || 'Error updating sales.'}</td></tr>`;
            }
        } catch (error) {
            console.error('Error refreshing recent sales:', error);
             recentSalesTbody.innerHTML = `<tr><td colspan="3" class="sales-fetch-error">Could not refresh sales list. Check console.</td></tr>`;
        }
    }

    // --- Live Search Logic (Hides Quick Keys) ---
    async function performLiveSearch(term) {
         if (quickKeysContainer) quickKeysContainer.style.display = 'none'; // Hide QK

        if (isProcessingLookup || term.length < 2) {
            if (term.length < 2) clearLookupUI(); // Show QK if term too short
            return;
         }
        isProcessingLookup = true;
        setLookupStatus('Searching...', 'info');
        lastSearchResults = [];

        try {
            const response = await fetch(`../src/actions/search_product_manual.php?term=${encodeURIComponent(term)}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();

            if (productLookupResultDiv) productLookupResultDiv.innerHTML = '';

            if (data.success && data.products.length > 0) {
                lastSearchResults = data.products;
                displayLiveSearchResults(data.products);
                setLookupStatus('', 'info'); // Clear status, keep QK hidden
            } else {
                setLookupStatus(data.message || `No matches found.`, 'info'); // Keep QK hidden
            }
        } catch (error) {
            console.error('Live Search Error:', error);
            clearLookupUI(); // Show QK on error
            setLookupStatus('Error during search.', 'error');
        } finally {
            isProcessingLookup = false;
        }
    }

    // --- Display Live Search Results ---
    function displayLiveSearchResults(products) {
        if (!productLookupResultDiv) return;
        // Clear previous before adding new
        productLookupResultDiv.innerHTML = '';
        products.forEach((product, index) => {
            const button = document.createElement('button');
            button.classList.add('search-result-item');
            button.innerHTML = `
                <span class="item-name">${escapeHTML(product.name)}</span>
                <span class="item-details">(${escapeHTML(product.barcode || 'No Barcode')}) - $${parseFloat(product.price).toFixed(2)} | Stock: ${product.stock_quantity}</span>
            `;
            button.dataset.index = index;
            button.addEventListener('click', handleLiveSelection);
            productLookupResultDiv.appendChild(button);
        });
    }

    // --- Handle Live Selection (Clears UI, Showing Quick Keys) ---
    function handleLiveSelection(event) {
        const selectedIndex = event.currentTarget.dataset.index;
        const selectedProduct = lastSearchResults[selectedIndex];
        if (selectedProduct) {
            addProductToCart(selectedProduct);
            clearLookupUI(); // Show QK
            if (barcodeInput) {
                 barcodeInput.value = '';
                 barcodeInput.focus();
            }
        } else {
            console.error("Could not find selected product.");
            setLookupStatus('Error selecting product.', 'error');
        }
    }

    // --- Exact Barcode Lookup (Hides/Shows Quick Keys) ---
    async function findProductByExactBarcode(barcode) {
          if (quickKeysContainer) quickKeysContainer.style.display = 'none'; // Hide QK

        if (isProcessingLookup) return;
        isProcessingLookup = true;
        if (productLookupResultDiv) productLookupResultDiv.innerHTML = ''; // Clear results
        lastSearchResults = [];
        setLookupStatus('Checking barcode...', 'info');

        try {
            const response = await fetch(`../src/actions/get_product_details.php?barcode=${encodeURIComponent(barcode)}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();

            if (data.success && data.product) {
                addProductToCart(data.product);
                setLookupStatus(`Added: ${escapeHTML(data.product.name)}`, 'success');
                 if (barcodeInput) barcodeInput.value = ''; // Input cleared
            } else {
                setLookupStatus(data.message || `Exact barcode not found.`, 'info');
                 if (barcodeInput) barcodeInput.select(); // Keep QK hidden
            }
        } catch (error) {
            console.error('Exact Barcode Fetch Error:', error);
            setLookupStatus('Error checking barcode.', 'error'); // Keep QK hidden
        } finally {
            isProcessingLookup = false;
            // Show Quick Keys ONLY if input is now empty (item added successfully)
            if (barcodeInput && barcodeInput.value.trim() === '') {
                 clearLookupUI(); // Show QK
            }
            if (barcodeInput) barcodeInput.focus(); // Ensure focus is returned
        }
    }

    // --- Quick Keys Logic ---
    async function loadQuickKeys() {
         if (!quickKeysGrid) return;
        quickKeysGrid.innerHTML = '<p>Loading quick keys...</p>';
        try {
            const response = await fetch('../src/actions/get_quick_keys.php');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            quickKeysGrid.innerHTML = '';
            if (data.success && data.products.length > 0) {
                quickKeyProductsData = {};
                data.products.forEach(product => {
                    quickKeyProductsData[product.id] = product;
                    const button = document.createElement('button');
                    button.classList.add('quick-key-item');
                    button.dataset.productId = product.id;
                    const imageFilename = product.image_url ? escapeHTML(product.image_url) : 'placeholder.png';
                    const imageUrl = `images/products/${imageFilename}`;
                    button.innerHTML = `<img src="${imageUrl}" alt="${escapeHTML(product.name)}" loading="lazy" onerror="this.src='images/placeholder.png'; this.onerror=null;"><span>${escapeHTML(product.name)}</span>`;
                    button.addEventListener('click', handleQuickKeyClick);
                    quickKeysGrid.appendChild(button);
                });
            } else {
                quickKeysGrid.innerHTML = '<p>No quick keys available.</p>';
            }
        } catch (error) {
            console.error('Error Loading Quick Keys:', error);
            quickKeysGrid.innerHTML = '<p>Error loading quick keys.</p>';
        }
    }

    function handleQuickKeyClick(event) {
        const productId = event.currentTarget.dataset.productId;
        const productData = quickKeyProductsData[productId];
        if (productData) {
            const productForCart = { id: productData.id, name: productData.name, price: productData.price, stock_quantity: productData.stock_quantity };
            addProductToCart(productForCart);
            event.currentTarget.style.outline = '2px solid green'; // Visual feedback
            setTimeout(() => { if(event.currentTarget) event.currentTarget.style.outline = 'none'; }, 200);
        } else {
            console.error('Quick key product data not found for ID:', productId);
            alert('Error adding item via quick key.');
        }
    }

    // --- Cart Management Functions ---
    function addProductToCart(product) {
        if (!cartItemsTableBody) return;
        const productId = product.id;
        const price = parseFloat(product.price);
        // Get stock from the *product data passed in*, which should be fresh from lookup/quick key load
        const stock = parseInt(product.stock_quantity);

        if (isNaN(price) || isNaN(stock)) { // Check if stock is a valid number here
            setLookupStatus(`Cannot add "${escapeHTML(product.name)}": Invalid product data (Price/Stock).`, 'error');
            return;
        }
         if (stock <= 0 && !currentCart[productId]) { // Prevent adding NEW item if stock is 0
              setLookupStatus(`Cannot add "${escapeHTML(product.name)}": Out of stock.`, 'error');
              return;
          }


        if (currentCart[productId]) {
            // Check available stock (original stock stored in cart) BEFORE incrementing
            const potentialNextQty = currentCart[productId].quantity + 1;
            if (potentialNextQty <= currentCart[productId].stock) {
                currentCart[productId].quantity++;
            } else {
                alert(`Max stock (${currentCart[productId].stock}) for ${escapeHTML(product.name)} reached in cart.`);
                setLookupStatus(`Max stock reached for ${escapeHTML(product.name)}.`, 'info');
            }
        } else {
            // Add new product to cart - store the stock level known at this time
            currentCart[productId] = {
                id: product.id, name: product.name, price: price, quantity: 1, stock: stock
            };
        }
        renderCart(); // Render cart including stock info
    }

    function removeCartItem(productId) {
        if (!cartItemsTableBody) return;
        if (currentCart[productId]) { delete currentCart[productId]; renderCart(); }
    }

    function decreaseCartItemQuantity(productId) {
        if (!cartItemsTableBody) return;
        if (currentCart[productId]) {
            currentCart[productId].quantity--;
            if (currentCart[productId].quantity <= 0) { removeCartItem(productId); }
            else { renderCart(); }
        }
    }

    function renderCart() {
        if (!cartItemsTableBody || !cartTotalSpan || !completeSaleBtn) { console.error("Cart rendering elements missing!"); return; }
        cartItemsTableBody.innerHTML = '';
        let total = 0, itemCount = 0;
        for (const productId in currentCart) {
            itemCount++;
            const item = currentCart[productId];
            const itemTotal = item.price * item.quantity;
            total += itemTotal;
            const remainingStock = item.stock - item.quantity;
            const stockClass = remainingStock <= 5 ? 'stock-low' : (remainingStock <= 0 ? 'stock-out' : 'stock-ok');
            const displayStock = Math.max(0, remainingStock);
            const row = cartItemsTableBody.insertRow();
            row.innerHTML = `
                <td>${escapeHTML(item.name)}</td>
                <td style="text-align: right;">$${item.price.toFixed(2)}</td>
                <td style="text-align: center;">
                    <button class="qty-decrease" data-id="${productId}" title="Decrease quantity">-</button>
                    <span class="qty-value">${item.quantity}</span>
                    <button class="qty-increase" data-id="${productId}" title="Increase quantity">+</button>
                </td>
                <td style="text-align: right;">$${itemTotal.toFixed(2)}</td>
                <td class="stock-cell ${stockClass}" style="text-align: center;">${displayStock}</td>
                <td style="text-align: center;"><button class="remove-item" data-id="${productId}" title="Remove item">X</button></td>
            `;
        }
        if (itemCount === 0) { cartItemsTableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">Cart is empty</td></tr>'; }
        cartTotalSpan.textContent = total.toFixed(2);
        completeSaleBtn.disabled = itemCount === 0;
    }

    // --- Sale Completion Logic ---
    async function completeSale() {
        if (isProcessingSale || Object.keys(currentCart).length === 0) {
            if (Object.keys(currentCart).length === 0) alert("Cart is empty!"); return;
        }
        isProcessingSale = true;
        if (completeSaleBtn) { completeSaleBtn.disabled = true; completeSaleBtn.textContent = 'Processing...'; }
        setSaleStatus('Processing sale...', 'info');
        clearLookupUI(); // Show QK

        const saleData = { items: Object.values(currentCart).map(item => ({ id: item.id, quantity: item.quantity })) };

        try {
            const response = await fetch('../src/actions/process_sale.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(saleData) });
            if (!response.ok) throw new Error(`Sale failed. Server status: ${response.status}`);
            const result = await response.json();

            if (result.success) {
                setSaleStatus(`Sale successful! Sale ID: ${result.sale_id}.`, 'success');
                if (result.sale_id) {
                    const receiptUrl = `receipt.php?sale_id=${result.sale_id}`;
                    window.open(receiptUrl, '_blank', 'width=450,height=700,scrollbars=yes,noopener,noreferrer');
                } else {
                    console.warn("Sale successful but no Sale ID received for receipt.");
                    setSaleStatus(`Sale successful! (No ID for receipt)`, 'success');
                }
                currentCart = {}; // Clear cart state
                renderCart();     // Update UI
                if (barcodeInput) barcodeInput.focus();
                refreshRecentSales(); // Update recent sales list
            } else {
                setSaleStatus(`Sale failed: ${result.message || 'Unknown error.'}`, 'error');
                if (completeSaleBtn) completeSaleBtn.disabled = false; // Re-enable button on logical failure
            }
        } catch (error) {
            console.error('Sale Processing Error:', error);
            setSaleStatus(`Error processing sale: ${error.message}.`, 'error');
             if (completeSaleBtn) completeSaleBtn.disabled = false; // Re-enable button on network/fetch error
        } finally {
            isProcessingSale = false;
            if (completeSaleBtn) completeSaleBtn.textContent = 'Complete Sale'; // Reset button text
        }
    }

    // --- EVENT LISTENERS ---
    if (barcodeInput) {
        const debouncedLiveSearch = debounce(performLiveSearch, 300);
        barcodeInput.addEventListener('input', () => {
            const searchTerm = barcodeInput.value.trim();
            if (searchTerm) {
                 if (quickKeysContainer) quickKeysContainer.style.display = 'none'; // Hide QK
                 debouncedLiveSearch(searchTerm);
            } else {
                clearLookupUI(); // Show QK when input empty
            }
        });
        barcodeInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                 e.preventDefault();
                 const barcodeValue = barcodeInput.value.trim();
                 if (barcodeValue && !isProcessingLookup) {
                     clearTimeout(debounceTimer);
                     findProductByExactBarcode(barcodeValue);
                 }
            }
        });
    }

    if (cartItemsTableBody) {
        cartItemsTableBody.addEventListener('click', (event) => { // Event Delegation
            const target = event.target; const productId = target.dataset.id; if (!productId) return;
            if (target.classList.contains('remove-item')) { removeCartItem(productId); }
            else if (target.classList.contains('qty-decrease')) { decreaseCartItemQuantity(productId); }
            else if (target.classList.contains('qty-increase')) {
                // Check stock *before* incrementing
                const potentialNextQty = (currentCart[productId]?.quantity || 0) + 1;
                if (currentCart[productId] && potentialNextQty <= currentCart[productId].stock) {
                     currentCart[productId].quantity++;
                     renderCart();
                } else if (currentCart[productId]) {
                    alert(`Max stock (${currentCart[productId].stock}) for ${escapeHTML(currentCart[productId].name)} reached.`);
                }
            }
        });
    }

    if (completeSaleBtn) { completeSaleBtn.addEventListener('click', completeSale); }

    // --- Initial Page Load Actions ---
    renderCart();
    if (barcodeInput) barcodeInput.focus();
    loadQuickKeys();
    updateClock();
    setInterval(updateClock, 1000);

}); // End DOMContentLoaded