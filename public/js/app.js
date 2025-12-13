const API_BASE = '/api';

const state = {
    products: [],
    categories: [],
    customers: [],
    suppliers: [],
    cart: [],
    settings: {},
    currentPage: 'dashboard',
    paymentMethod: 'cash'
};

document.addEventListener('DOMContentLoaded', () => { initApp(); });

async function initApp() {
    await initDatabase();
    await loadSettings();
    await loadCategories();
    await loadCustomers();
    await loadSuppliers();
    await loadDashboard();
    setupEventListeners();
}

async function initDatabase() {
    try { await fetch('/config/init_db.php'); } catch (e) { console.error('DB init error:', e); }
}

async function loadSettings() {
    try {
        const response = await fetch(`${API_BASE}/settings.php`);
        const result = await response.json();
        if (result.success) {
            state.settings = result.data;
            Object.keys(result.data).forEach(key => {
                const el = document.getElementById('setting_' + key);
                if (el) {
                    if (el.type === 'checkbox') {
                        el.checked = result.data[key].value === '1' || result.data[key].value === true || result.data[key].value === 'true';
                    } else {
                        el.value = result.data[key].value || '';
                    }
                }
            });
        }
    } catch (e) { console.error('Settings error:', e); }
}

function setupEventListeners() {
    document.querySelectorAll('.nav-item').forEach(item => { item.addEventListener('click', () => navigateTo(item.dataset.page)); });
    document.getElementById('menuToggle').addEventListener('click', toggleSidebar);
    
    // Add logout event listener
    document.getElementById('logoutBtn').addEventListener('click', handleLogout);
    
    // User dropdown functionality
    const userDropdown = document.getElementById('userDropdown');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    const logoutBtnHeader = document.getElementById('logoutBtnHeader');
    
    if (userDropdown) {
        userDropdown.addEventListener('click', (e) => {
            e.stopPropagation();
            // Only toggle if the click target is the dropdown itself, not its children
            if (e.target === userDropdown || userDropdown.contains(e.target)) {
                userDropdownMenu.classList.toggle('show');
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (userDropdownMenu.classList.contains('show') && !userDropdown.contains(e.target)) {
                userDropdownMenu.classList.remove('show');
            }
        });
        
        // View profile button
        const viewProfileBtn = document.getElementById('viewProfileBtn');
        if (viewProfileBtn) {
            viewProfileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdownMenu.classList.remove('show');
                // Navigate to dashboard to show user profile
                navigateTo('dashboard');
            });
        }
        
        // Session management button
        const sessionManagementBtn = document.getElementById('sessionManagementBtn');
        if (sessionManagementBtn) {
            sessionManagementBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdownMenu.classList.remove('show');
                // TODO: Implement session management
                alert('Session management feature coming soon!');
            });
        }
        
        // Logout button in header
        if (logoutBtnHeader) {
            logoutBtnHeader.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdownMenu.classList.remove('show');
                handleLogout();
            });
        }
    }

    document.getElementById('addProductBtn').addEventListener('click', () => openProductModal());
    document.getElementById('closeProductModal').addEventListener('click', closeProductModal);
    document.getElementById('cancelProductBtn').addEventListener('click', closeProductModal);
    document.getElementById('productForm').addEventListener('submit', handleProductSubmit);

    document.getElementById('addCategoryBtn').addEventListener('click', () => openCategoryModal());
    document.getElementById('closeCategoryModal').addEventListener('click', closeCategoryModal);
    document.getElementById('cancelCategoryBtn').addEventListener('click', closeCategoryModal);
    document.getElementById('categoryForm').addEventListener('submit', handleCategorySubmit);

    document.getElementById('closeStockModal').addEventListener('click', closeStockModal);
    document.getElementById('cancelStockBtn').addEventListener('click', closeStockModal);
    document.getElementById('stockForm').addEventListener('submit', handleStockSubmit);

    document.getElementById('addCustomerBtn').addEventListener('click', () => openCustomerModal());
    document.getElementById('closeCustomerModal').addEventListener('click', closeCustomerModal);
    document.getElementById('cancelCustomerBtn').addEventListener('click', closeCustomerModal);
    document.getElementById('customerForm').addEventListener('submit', handleCustomerSubmit);

    document.getElementById('addSupplierBtn').addEventListener('click', () => openSupplierModal());
    document.getElementById('closeSupplierModal').addEventListener('click', closeSupplierModal);
    document.getElementById('cancelSupplierBtn').addEventListener('click', closeSupplierModal);
    document.getElementById('supplierForm').addEventListener('submit', handleSupplierSubmit);

    document.getElementById('addUserBtn').addEventListener('click', () => openUserModal());
    document.getElementById('closeUserModal').addEventListener('click', closeUserModal);
    document.getElementById('cancelUserBtn').addEventListener('click', closeUserModal);
    document.getElementById('userForm').addEventListener('submit', handleUserSubmit);
    document.getElementById('userSearch').addEventListener('input', debounce(loadUsers, 300));

    document.getElementById('closePaymentModal').addEventListener('click', closePaymentModal);
    document.getElementById('cancelPaymentBtn').addEventListener('click', closePaymentModal);
    document.getElementById('paymentForm').addEventListener('submit', handlePaymentSubmit);

    document.getElementById('closeSaleModal').addEventListener('click', () => document.getElementById('saleModal').classList.remove('active'));

    document.getElementById('categoryFilter').addEventListener('change', loadProducts);
    document.getElementById('productSearch').addEventListener('input', debounce(loadProducts, 300));
    document.getElementById('customerSearch').addEventListener('input', debounce(loadCustomers, 300));
    document.getElementById('supplierSearch').addEventListener('input', debounce(loadSuppliers, 300));

    document.getElementById('posProductSearch').addEventListener('input', debounce(loadPosProducts, 300));
    document.getElementById('posCategoryFilter').addEventListener('change', loadPosProducts);
    document.getElementById('clearCartBtn').addEventListener('click', clearCart);
    document.getElementById('completeSaleBtn').addEventListener('click', completeSale);
    
    // Barcode scanner functionality
    document.getElementById('toggleBarcodeScanner').addEventListener('click', toggleBarcodeScanner);
    document.getElementById('barcodeScannerInput').addEventListener('keypress', handleBarcodeInput);
    document.querySelectorAll('.payment-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.payment-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            state.paymentMethod = btn.dataset.method;
        });
    });

    document.getElementById('filterSalesBtn').addEventListener('click', loadSales);
    document.getElementById('creditStatusFilter').addEventListener('change', loadCreditSales);
    
    // Purchases page event listeners
    document.getElementById('addPurchaseBtn').addEventListener('click', () => openPurchaseModal());
    document.getElementById('filterPurchasesBtn').addEventListener('click', loadPurchases);
    document.getElementById('purchaseSupplierFilter').addEventListener('change', loadPurchases);

    document.querySelectorAll('.report-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.report-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            loadReport(tab.dataset.report);
        });
    });

    document.getElementById('settingsForm').addEventListener('submit', handleSettingsSubmit);
    document.getElementById('advancedSettingsForm').addEventListener('submit', handleAdvancedSettingsSubmit);

    document.querySelectorAll('.settings-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const tabName = tab.dataset.tab;
            document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.settings-tab-content').forEach(c => c.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.classList.remove('active'); });
    });
}

function navigateTo(page) {
    document.querySelectorAll('.nav-item').forEach(item => { item.classList.toggle('active', item.dataset.page === page); });
    document.querySelectorAll('.page').forEach(p => { p.classList.toggle('active', p.id === page + 'Page'); });
    const titles = { dashboard: 'Dashboard', pos: 'Point of Sale', products: 'Products', categories: 'Categories', customers: 'Customers', suppliers: 'Suppliers', purchases: 'Purchases', sales: 'Sales History', credit: 'Credit Sales', reports: 'Reports', transactions: 'Stock History', users: 'Users', settings: 'Settings' };
    document.getElementById('pageTitle').textContent = titles[page] || 'Dashboard';
    state.currentPage = page;
    if (page === 'dashboard') loadDashboard();
    else if (page === 'pos') { loadPosProducts(); updateCartDisplay(); initializeBarcodeScanner(); }
    else if (page === 'products') loadProducts();
    else if (page === 'categories') loadCategories();
    else if (page === 'customers') loadCustomers();
    else if (page === 'suppliers') loadSuppliers();
    else if (page === 'purchases') loadPurchases();
    else if (page === 'sales') loadSales();
    else if (page === 'credit') loadCreditSales();
    else if (page === 'reports') loadReport('summary');
    else if (page === 'transactions') loadTransactions();
    else if (page === 'users') loadUsers();
    else if (page === 'settings') loadSettings();
    if (window.innerWidth <= 1024) toggleSidebar();
}

function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('active'); }

async function loadDashboard() {
    try {
        // Get current user data from session
        const userResponse = await fetch(`${API_BASE}/auth/check-session.php`, {
            credentials: 'include'  // Ensure credentials are sent with the request
        });
        const userResult = await userResponse.json();
        
        if (userResult.success && userResult.authenticated) {
            // Update user profile information in sidebar (if elements exist)
            const profileUsername = document.getElementById('profileUsername');
            const profileRole = document.getElementById('profileRole');
            if (profileUsername) profileUsername.textContent = userResult.user.username;
            if (profileRole) profileRole.textContent = userResult.user.role;
            
            // Update welcome message with user's name
            const welcomeUserName = document.getElementById('welcomeUserName');
            if (welcomeUserName) {
                // Use full name if available, otherwise fallback to username
                const displayName = userResult.user.full_name || userResult.user.username;
                welcomeUserName.textContent = displayName;
            }
            
            // Update user avatar with initials
            const userAvatarInitials = document.getElementById('userAvatarInitials');
            if (userAvatarInitials) {
                // Use full name if available, otherwise fallback to username
                const displayName = userResult.user.full_name || userResult.user.username;
                const initials = displayName.charAt(0).toUpperCase();
                userAvatarInitials.textContent = initials;
            }
            
            // Update header username display
            const topBarUserName = document.getElementById('topBarUserName');
            if (topBarUserName) {
                // Use full name if available, otherwise fallback to username
                const displayName = userResult.user.full_name || userResult.user.username;
                topBarUserName.textContent = displayName;
            }
        }
        
        const response = await fetch(`${API_BASE}/reports.php?type=summary`, {
            credentials: 'include'  // Ensure credentials are sent with the request
        });
        const result = await response.json();
        if (result.success) {
            const d = result.data;
            document.getElementById('todaySales').textContent = '$' + formatNumber(d.today_sales.total);
            document.getElementById('monthSales').textContent = '$' + formatNumber(d.month_sales.total);
            document.getElementById('totalProducts').textContent = d.total_products;
            document.getElementById('lowStockCount').textContent = d.low_stock_count;
            document.getElementById('lowStockBadge').textContent = d.low_stock_count;
            document.getElementById('totalCustomers').textContent = d.total_customers;
            document.getElementById('pendingCredits').textContent = '$' + formatNumber(d.pending_credits.total);
        }
        
        const dashResponse = await fetch(`${API_BASE}/dashboard.php`, {
            credentials: 'include'  // Ensure credentials are sent with the request
        });
        const dashResult = await dashResponse.json();
        if (dashResult.success) {
            renderLowStockList(dashResult.data.low_stock_items);
            renderRecentActivity(dashResult.data.recent_transactions);
        }
        
        // Load chart data
        await loadChartData();
    } catch (e) { 
        console.error('Dashboard error:', e); 
        showToast('Error loading dashboard', 'error'); 
    }
}

function renderLowStockList(items) {
    const container = document.getElementById('lowStockList');
    if (!items || items.length === 0) { container.innerHTML = '<p class="empty-state">No low stock items</p>'; return; }
    container.innerHTML = items.map(item => `<div class="alert-item ${item.quantity === 0 ? 'critical' : ''}"><div class="alert-item-info"><h4>${escapeHtml(item.name)}</h4><span>${item.category_name || 'Uncategorized'} | SKU: ${escapeHtml(item.sku)}</span></div><div class="alert-item-stock"><div class="stock-value">${item.quantity}</div><div class="stock-label">In Stock</div></div></div>`).join('');
}

function renderRecentActivity(transactions) {
    const container = document.getElementById('recentActivity');
    if (!transactions || transactions.length === 0) { container.innerHTML = '<p class="empty-state">No recent activity</p>'; return; }
    container.innerHTML = transactions.map(t => `<div class="activity-item"><div class="activity-icon ${t.type}">${getActivityIcon(t.type)}</div><div class="activity-content"><h4>${getActivityTitle(t.type)} - ${escapeHtml(t.product_name)}</h4><p>${t.quantity} units ${t.notes ? '| ' + escapeHtml(t.notes) : ''}</p><time>${formatDate(t.created_at)}</time></div></div>`).join('');
}

function getActivityIcon(type) {
    const icons = { add: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>', remove: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>', initial: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>' };
    return icons[type] || icons.initial;
}

function getActivityTitle(type) { return { add: 'Stock Added', remove: 'Stock Removed', initial: 'Initial Stock' }[type] || 'Stock Update'; }

async function loadCategories() {
    try {
        const response = await fetch(`${API_BASE}/categories.php`);
        const result = await response.json();
        if (result.success) {
            state.categories = result.data;
            updateCategorySelects();
            if (state.currentPage === 'categories') renderCategories();
        }
    } catch (e) { console.error('Categories error:', e); }
}

function updateCategorySelects() {
    const options = '<option value="">Select Category</option>' + state.categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    ['productCategory', 'categoryFilter', 'posCategoryFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = id === 'productCategory' ? options : '<option value="">All Categories</option>' + state.categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    });
}

function renderCategories() {
    const tbody = document.getElementById('categoriesBody');
    if (state.categories.length === 0) { 
        tbody.innerHTML = '<tr><td colspan="4" class="empty-state">No categories found</td></tr>'; 
        return; 
    }
    
    tbody.innerHTML = state.categories.map(cat => `
        <tr>
            <td>${escapeHtml(cat.name)}</td>
            <td>${escapeHtml(cat.description || 'No description')}</td>
            <td>${cat.product_count} products</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-icon btn-secondary" onclick="deleteCategory(${cat.id})" title="Delete">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function loadProducts() {
    try {
        const category = document.getElementById('categoryFilter').value;
        const search = document.getElementById('productSearch').value;
        let url = `${API_BASE}/products.php?`;
        if (category) url += `category=${category}&`;
        if (search) url += `search=${encodeURIComponent(search)}`;
        const response = await fetch(url);
        const result = await response.json();
        
        // Check if user is authenticated
        if (response.status === 401) {
            showToast('Session expired. Please log in again.', 'error');
            // Redirect to landing page
            window.location.href = '/index.php';
            return;
        }
        
        if (result.success) { 
            state.products = result.data; 
            renderProducts(); 
        } else {
            // Show specific error message from server
            showToast(result.error || 'Error loading products', 'error');
        }
    } catch (e) { 
        console.error('Products error:', e); 
        showToast('Error loading products: ' + e.message, 'error'); 
    }
}

function renderProducts() {
    const tbody = document.getElementById('productsBody');
    if (state.products.length === 0) { 
        tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No products found. Add your first product!</td></tr>'; 
        return; 
    }
    
    tbody.innerHTML = state.products.map(product => {
        const stockStatus = getStockStatus(product.quantity, product.min_stock);
        const stockText = product.quantity <= 0 ? 'Out of Stock' : 
                         product.quantity <= product.min_stock ? 'Low Stock' : 'In Stock';
        
        return `
            <tr>
                <td>
                    <div class="product-info">
                        <div class="product-name">${escapeHtml(product.name)}</div>
                    </div>
                </td>
                <td>${escapeHtml(product.sku)}</td>
                <td>${product.category_name || 'Uncategorized'}</td>
                <td>$${formatNumber(product.price)}</td>
                <td>${product.quantity}</td>
                <td>
                    <span class="status-badge ${stockStatus.class}">
                        ${stockText}
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-secondary btn-sm" onclick="openStockModal(${product.id}, '${escapeHtml(product.name)}', ${product.quantity})">Adjust</button>
                        <button class="btn btn-secondary btn-sm" onclick="editProduct(${product.id})">Edit</button>
                        <button class="btn btn-icon btn-secondary btn-sm" onclick="deleteProduct(${product.id})" title="Delete">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function getStockStatus(quantity, minStock) {
    if (quantity === 0) return { class: 'out-of-stock', text: 'Out of Stock' };
    if (quantity <= minStock) return { class: 'low-stock', text: 'Low Stock' };
    return { class: 'in-stock', text: 'In Stock' };
}

async function loadCustomers() {
    try {
        const search = document.getElementById('customerSearch')?.value || '';
        const response = await fetch(`${API_BASE}/customers.php${search ? '?search=' + encodeURIComponent(search) : ''}`);
        const result = await response.json();
        
        // Check if user is authenticated
        if (response.status === 401) {
            showToast('Session expired. Please log in again.', 'error');
            window.location.href = '/index.php'
            return;
        }
        
        if (result.success) {
            state.customers = result.data;
            updateCustomerSelects();
            if (state.currentPage === 'customers') renderCustomers();
        } else {
            showToast(result.error || 'Error loading customers', 'error');
        }
    } catch (e) { console.error('Customers error:', e); showToast('Error loading customers: ' + e.message, 'error'); }
}

function updateCustomerSelects() {
    const options = '<option value="">Walk-in Customer</option>' + state.customers.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    document.getElementById('posCustomer').innerHTML = options;
}

function renderCustomers() {
    const tbody = document.getElementById('customersBody');
    if (state.customers.length === 0) { tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No customers found</td></tr>'; return; }
    tbody.innerHTML = state.customers.map(c => `
        <tr>
            <td>${escapeHtml(c.name)}</td>
            <td>${escapeHtml(c.email || '-')}</td>
            <td>${escapeHtml(c.phone || '-')}</td>
            <td>$${formatNumber(c.credit_limit)}</td>
            <td>$${formatNumber(c.credit_balance)}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-secondary btn-sm" onclick="editCustomer(${c.id})">Edit</button>
                    <button class="btn btn-icon btn-secondary btn-sm" onclick="deleteCustomer(${c.id})" title="Delete">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function loadSuppliers() {
    try {
        const search = document.getElementById('supplierSearch')?.value || '';
        const response = await fetch(`${API_BASE}/suppliers.php${search ? '?search=' + encodeURIComponent(search) : ''}`);
        const result = await response.json();
        
        // Check if user is authenticated
        if (response.status === 401) {
            showToast('Session expired. Please log in again.', 'error');
            window.location.href = '/index.php'
            return;
        }
        
        if (result.success && state.currentPage === 'suppliers') renderSuppliers(result.data);
        else if (!result.success) {
            showToast(result.error || 'Error loading suppliers', 'error');
        }
    } catch (e) { console.error('Suppliers error:', e); showToast('Error loading suppliers: ' + e.message, 'error'); }
}

function renderSuppliers(suppliers) {
    const tbody = document.getElementById('suppliersBody');
    if (!suppliers || suppliers.length === 0) { tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No suppliers found</td></tr>'; return; }
    tbody.innerHTML = suppliers.map(s => `
        <tr>
            <td>${escapeHtml(s.name)}</td>
            <td>${escapeHtml(s.contact_person || '-')}</td>
            <td>${escapeHtml(s.phone || '-')}</td>
            <td>${escapeHtml(s.email || '-')}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-secondary btn-sm" onclick="editSupplier(${s.id})">Edit</button>
                    <button class="btn btn-icon btn-secondary btn-sm" onclick="deleteSupplier(${s.id})" title="Delete">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function loadPosProducts() {
    try {
        const category = document.getElementById('posCategoryFilter').value;
        const search = document.getElementById('posProductSearch').value;
        let url = `${API_BASE}/products.php?`;
        if (category) url += `category=${category}&`;
        if (search) url += `search=${encodeURIComponent(search)}`;
        const response = await fetch(url);
        const result = await response.json();
        
        // Check if user is authenticated
        if (response.status === 401) {
            showToast('Session expired. Please log in again.', 'error');
            window.location.href = '/index.php'
            return;
        }
        
        if (result.success) renderPosProducts(result.data);
        else {
            showToast(result.error || 'Error loading products', 'error');
        }
    } catch (e) { console.error('POS products error:', e); showToast('Error loading products: ' + e.message, 'error'); }
}

function renderPosProducts(products) {
    const container = document.getElementById('posProductsGrid');
    if (!products || products.length === 0) { container.innerHTML = '<p class="empty-state">No products found</p>'; return; }
    container.innerHTML = products.map(p => `<div class="pos-product-card ${p.quantity === 0 ? 'out-of-stock' : ''}" onclick="addToCart(${p.id}, '${escapeHtml(p.name)}', ${p.price}, ${p.quantity})"><h4>${escapeHtml(p.name)}</h4><div class="price">$${formatNumber(p.price)}</div><div class="stock">${p.quantity} in stock</div></div>`).join('');
}

function addToCart(productId, name, price, maxQty) {
    if (maxQty === 0) return;
    const existing = state.cart.find(item => item.product_id === productId);
    if (existing) {
        if (existing.quantity < maxQty) existing.quantity++;
    } else {
        state.cart.push({ product_id: productId, product_name: name, unit_price: price, quantity: 1, max_qty: maxQty });
    }
    updateCartDisplay();
}

function updateCartDisplay() {
    const container = document.getElementById('cartItems');
    if (state.cart.length === 0) { container.innerHTML = '<p class="empty-state">No items in cart</p>'; updateCartTotals(); return; }
    container.innerHTML = state.cart.map((item, idx) => `<div class="cart-item"><div class="cart-item-info"><h4>${escapeHtml(item.product_name)}</h4><span>$${formatNumber(item.unit_price)} each</span></div><div class="cart-item-qty"><button onclick="updateCartQty(${idx}, -1)">-</button><span>${item.quantity}</span><button onclick="updateCartQty(${idx}, 1)">+</button></div><div class="cart-item-total">$${formatNumber(item.unit_price * item.quantity)}</div><button class="cart-item-remove" onclick="removeFromCart(${idx})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>`).join('');
    updateCartTotals();
}

function updateCartQty(index, change) {
    const item = state.cart[index];
    item.quantity += change;
    if (item.quantity <= 0) state.cart.splice(index, 1);
    else if (item.quantity > item.max_qty) item.quantity = item.max_qty;
    updateCartDisplay();
}

function removeFromCart(index) { state.cart.splice(index, 1); updateCartDisplay(); }
function clearCart() { state.cart = []; updateCartDisplay(); }

// Barcode scanner functions
let barcodeScannerMode = false;

function toggleBarcodeScanner() {
    barcodeScannerMode = !barcodeScannerMode;
    const searchInput = document.getElementById('posProductSearch');
    const barcodeInput = document.getElementById('barcodeScannerInput');
    const toggleButton = document.getElementById('toggleBarcodeScanner');
    const scannerIndicator = document.getElementById('scannerIndicator');
    
    if (barcodeScannerMode) {
        searchInput.style.display = 'none';
        barcodeInput.style.display = 'block';
        scannerIndicator.style.display = 'block';
        barcodeInput.focus();
        toggleButton.textContent = 'Disable Barcode Scanner';
        toggleButton.classList.add('active');
    } else {
        searchInput.style.display = 'block';
        barcodeInput.style.display = 'none';
        scannerIndicator.style.display = 'none';
        searchInput.focus();
        toggleButton.textContent = 'Enable Barcode Scanner';
        toggleButton.classList.remove('active');
    }
}

async function handleBarcodeInput(event) {
    // Enter key triggers the scan
    if (event.key === 'Enter') {
        const barcode = event.target.value.trim();
        if (barcode) {
            try {
                // Search for product by SKU (barcode)
                const response = await fetch(`${API_BASE}/products.php?search=${encodeURIComponent(barcode)}&exact=true`);
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    // Find exact match by SKU
                    const product = result.data.find(p => p.sku === barcode);
                    
                    if (product) {
                        addToCart(product.id, product.name, product.price, product.quantity);
                        showToast(`Added ${product.name} to cart`, 'success');
                    } else {
                        showToast('Product not found', 'error');
                    }
                } else {
                    showToast('Product not found', 'error');
                }
            } catch (e) {
                console.error('Barcode scan error:', e);
                showToast('Error scanning product', 'error');
            }
        }
        // Clear the input
        event.target.value = '';
    }
}

function initializeBarcodeScanner() {
    // Reset barcode scanner mode when switching to POS page
    barcodeScannerMode = false;
    const searchInput = document.getElementById('posProductSearch');
    const barcodeInput = document.getElementById('barcodeScannerInput');
    const toggleButton = document.getElementById('toggleBarcodeScanner');
    const scannerIndicator = document.getElementById('scannerIndicator');
    
    searchInput.style.display = 'block';
    barcodeInput.style.display = 'none';
    scannerIndicator.style.display = 'none';
    toggleButton.textContent = 'Enable Barcode Scanner';
    toggleButton.classList.remove('active');
}

function updateCartTotals() {
    const subtotal = state.cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
    const total = subtotal;
    document.getElementById('cartSubtotal').textContent = '$' + formatNumber(subtotal);
    document.getElementById('cartTotal').textContent = '$' + formatNumber(total);
}

async function completeSale() {
    if (state.cart.length === 0) { 
        showToast('Cart is empty', 'warning'); 
        return; 
    }
    
    // Check if all products in cart still exist
    try {
        const productChecks = state.cart.map(async (item) => {
            const response = await fetch(`${API_BASE}/products.php?id=${item.product_id}`);
            const result = await response.json();
            return result.success && result.data;
        });
        
        const productResults = await Promise.all(productChecks);
        const invalidProducts = productResults.filter(result => !result);
        
        if (invalidProducts.length > 0) {
            showToast('Some products in your cart are no longer available. Please refresh your cart.', 'error');
            loadPosProducts(); // Refresh product list
            return;
        }
    } catch (e) {
        console.error('Product validation error:', e);
        showToast('Error validating products in cart', 'error');
        return;
    }
    
    const subtotal = state.cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
    const total = subtotal;
    const customerId = document.getElementById('posCustomer').value;
    
    if (state.paymentMethod === 'credit' && !customerId) { 
        showToast('Please select a customer for credit sales', 'warning'); 
        return; 
    }
    
    const saleData = {
        customer_id: customerId || null,
        subtotal: subtotal,
        total: total,
        payment_method: state.paymentMethod,
        items: state.cart.map(item => ({ 
            product_id: item.product_id, 
            product_name: item.product_name, 
            quantity: item.quantity, 
            unit_price: item.unit_price, 
            total: item.unit_price * item.quantity 
        }))
    };
    
    try {
        const response = await fetch(`${API_BASE}/sales.php`, { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify(saleData) 
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`Sale completed! Invoice: ${result.invoice_number}`, 'success');
            clearCart();
            loadPosProducts();
        } else {
            showToast(result.error || 'Error completing sale', 'error');
        }
    } catch (e) { 
        console.error('Sale error:', e); 
        showToast('Error completing sale', 'error'); 
    }
}

async function loadSales() {
    try {
        const dateFrom = document.getElementById('salesDateFrom').value;
        const dateTo = document.getElementById('salesDateTo').value;
        const status = document.getElementById('salesStatusFilter').value;
        let url = `${API_BASE}/sales.php?`;
        if (dateFrom) url += `date_from=${dateFrom}&`;
        if (dateTo) url += `date_to=${dateTo}&`;
        if (status) url += `payment_status=${status}`;
        const response = await fetch(url);
        const result = await response.json();
        if (result.success) renderSales(result.data);
    } catch (e) { console.error('Sales error:', e); }
}

function renderSales(sales) {
    const tbody = document.getElementById('salesBody');
    if (!sales || sales.length === 0) { tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No sales found</td></tr>'; return; }
    tbody.innerHTML = sales.map(s => `<tr><td>${escapeHtml(s.invoice_number)}</td><td>${escapeHtml(s.customer_name || 'Walk-in')}</td><td>$${formatNumber(s.total)}</td><td>${s.payment_method}</td><td><span class="status-badge ${s.payment_status}">${s.payment_status}</span></td><td>${formatDate(s.created_at)}</td><td><button class="btn btn-sm btn-secondary" onclick="viewSale(${s.id})">View</button></td></tr>`).join('');
}

async function viewSale(id) {
    try {
        const response = await fetch(`${API_BASE}/sales.php?id=${id}`);
        const result = await response.json();
        if (result.success) {
            const s = result.data;
            document.getElementById('saleDetails').innerHTML = `<div class="sale-info"><p><strong>Invoice:</strong> ${s.invoice_number}</p><p><strong>Customer:</strong> ${s.customer_name || 'Walk-in'}</p><p><strong>Date:</strong> ${formatDate(s.created_at)}</p><p><strong>Payment:</strong> ${s.payment_method} (${s.payment_status})</p></div><table class="data-table"><thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead><tbody>${s.items.map(i => `<tr><td>${escapeHtml(i.product_name)}</td><td>${i.quantity}</td><td>$${formatNumber(i.unit_price)}</td><td>$${formatNumber(i.total)}</td></tr>`).join('')}</tbody></table><div class="sale-totals" style="margin-top:1rem"><p><strong>Subtotal:</strong> $${formatNumber(s.subtotal)}</p><p><strong>Total:</strong> $${formatNumber(s.total)}</p></div>`;
            document.getElementById('saleModal').classList.add('active');
        }
    } catch (e) { console.error('View sale error:', e); }
}

async function loadCreditSales() {
    try {
        const status = document.getElementById('creditStatusFilter').value;
        const response = await fetch(`${API_BASE}/credit.php${status ? '?status=' + status : ''}`);
        const result = await response.json();
        if (result.success) renderCreditSales(result.data);
    } catch (e) { console.error('Credit sales error:', e); }
}

function renderCreditSales(credits) {
    const tbody = document.getElementById('creditBody');
    if (!credits || credits.length === 0) { tbody.innerHTML = '<tr><td colspan="8" class="empty-state">No credit sales found</td></tr>'; return; }
    tbody.innerHTML = credits.map(c => `<tr><td>${escapeHtml(c.invoice_number)}</td><td>${escapeHtml(c.customer_name)}</td><td>$${formatNumber(c.amount)}</td><td>$${formatNumber(c.amount_paid)}</td><td>$${formatNumber(c.balance)}</td><td>${c.due_date || '-'}</td><td><span class="status-badge ${c.status}">${c.status}</span></td><td>${c.status !== 'paid' ? `<button class="btn btn-sm btn-success" onclick="openPaymentModal(${c.id}, ${c.balance})">Pay</button>` : '-'}</td></tr>`).join('');
}

function openPaymentModal(creditId, balance) {
    document.getElementById('paymentForm').reset();
    document.getElementById('paymentCreditId').value = creditId;
    document.getElementById('paymentInfo').textContent = `Balance: $${formatNumber(balance)}`;
    document.getElementById('paymentAmount').max = balance;
    document.getElementById('paymentModal').classList.add('active');
}

function closePaymentModal() { document.getElementById('paymentModal').classList.remove('active'); }

async function handlePaymentSubmit(e) {
    e.preventDefault();
    const data = { credit_sale_id: document.getElementById('paymentCreditId').value, amount: parseFloat(document.getElementById('paymentAmount').value), payment_method: document.getElementById('paymentMethod').value, notes: document.getElementById('paymentNotes').value };
    try {
        const response = await fetch(`${API_BASE}/credit.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        const result = await response.json();
        if (result.success) { showToast('Payment recorded successfully', 'success'); closePaymentModal(); loadCreditSales(); }
        else showToast(result.error || 'Error recording payment', 'error');
    } catch (e) { console.error('Payment error:', e); showToast('Error recording payment', 'error'); }
}

async function loadReport(type) {
    console.log('Loading report type:', type); // Debug log
    const container = document.getElementById('reportContent');
    container.innerHTML = '<p class="empty-state">Loading...</p>';
    try {
        const response = await fetch(`${API_BASE}/reports.php?type=${type}`);
        console.log('Report API response status:', response.status); // Debug log
        const result = await response.json();
        console.log('Report API result:', result); // Debug log
        if (result.success) {
            if (type === 'summary') renderSummaryReport(result.data);
            else if (type === 'sales') renderSalesReport(result.data);
            else if (type === 'inventory') renderInventoryReport(result.data);
            else if (type === 'credit') renderCreditReport(result.data);
        } else {
            console.error('Report API error:', result.error);
            container.innerHTML = '<p class="empty-state">Error loading report: ' + (result.error || 'Unknown error') + '</p>';
        }
    } catch (e) { 
        console.error('Report error:', e); 
        container.innerHTML = '<p class="empty-state">Error loading report: ' + e.message + '</p>'; 
    }
}

function renderSummaryReport(data) {
    document.getElementById('reportContent').innerHTML = `<div class="report-stats"><div class="report-stat"><div class="report-stat-label">Today's Sales</div><div class="report-stat-value">$${formatNumber(data.today_sales.total)}</div></div><div class="report-stat"><div class="report-stat-label">This Month</div><div class="report-stat-value">$${formatNumber(data.month_sales.total)}</div></div><div class="report-stat"><div class="report-stat-label">Total Products</div><div class="report-stat-value">${data.total_products}</div></div><div class="report-stat"><div class="report-stat-label">Low Stock Items</div><div class="report-stat-value">${data.low_stock_count}</div></div><div class="report-stat"><div class="report-stat-label">Total Customers</div><div class="report-stat-value">${data.total_customers}</div></div><div class="report-stat"><div class="report-stat-label">Pending Credits</div><div class="report-stat-value">$${formatNumber(data.pending_credits.total)}</div></div><div class="report-stat"><div class="report-stat-label">Inventory Value</div><div class="report-stat-value">$${formatNumber(data.inventory_value)}</div></div><div class="report-stat"><div class="report-stat-label">Total Suppliers</div><div class="report-stat-value">${data.total_suppliers}</div></div></div>`;
}

function renderSalesReport(data) {
    document.getElementById('reportContent').innerHTML = `<div class="report-stats"><div class="report-stat"><div class="report-stat-label">Total Transactions</div><div class="report-stat-value">${data.summary.transactions}</div></div><div class="report-stat"><div class="report-stat-label">Total Sales</div><div class="report-stat-value">$${formatNumber(data.summary.total_sales)}</div></div><div class="report-stat"><div class="report-stat-label">Paid Sales</div><div class="report-stat-value">$${formatNumber(data.summary.paid_sales)}</div></div><div class="report-stat"><div class="report-stat-label">Credit Sales</div><div class="report-stat-value">$${formatNumber(data.summary.credit_sales)}</div></div></div><table class="data-table"><thead><tr><th>Date</th><th>Transactions</th><th>Total</th><th>Paid</th><th>Credit</th></tr></thead><tbody>${data.daily.map(d => `<tr><td>${d.date}</td><td>${d.transactions}</td><td>$${formatNumber(d.total_sales)}</td><td>$${formatNumber(d.paid_sales)}</td><td>$${formatNumber(d.credit_sales)}</td></tr>`).join('') || '<tr><td colspan="5" class="empty-state">No data</td></tr>'}</tbody></table>`;
}

function renderInventoryReport(data) {
    document.getElementById('reportContent').innerHTML = `<h4 style="margin-bottom:1rem">By Category</h4><table class="data-table"><thead><tr><th>Category</th><th>Products</th><th>Total Qty</th><th>Value</th></tr></thead><tbody>${data.by_category.map(c => `<tr><td>${escapeHtml(c.name)}</td><td>${c.product_count}</td><td>${c.total_quantity}</td><td>$${formatNumber(c.total_value)}</td></tr>`).join('')}</tbody></table><h4 style="margin:1.5rem 0 1rem">All Products</h4><table class="data-table"><thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Price</th><th>Value</th></tr></thead><tbody>${data.products.map(p => `<tr><td>${escapeHtml(p.name)}</td><td>${escapeHtml(p.sku)}</td><td>${p.quantity}</td><td>$${formatNumber(p.price)}</td><td>$${formatNumber(p.stock_value)}</td></tr>`).join('')}</tbody></table>`;
}

function renderCreditReport(data) {
    document.getElementById('reportContent').innerHTML = `<h4 style="margin-bottom:1rem">Customer Credits</h4><table class="data-table"><thead><tr><th>Customer</th><th>Credit Limit</th><th>Balance</th><th>Pending</th></tr></thead><tbody>${data.customer_credits.map(c => `<tr><td>${escapeHtml(c.customer_name)}</td><td>$${formatNumber(c.credit_limit)}</td><td>$${formatNumber(c.credit_balance)}</td><td>$${formatNumber(c.pending_amount)}</td></tr>`).join('') || '<tr><td colspan="4" class="empty-state">No data</td></tr>'}</tbody></table>${data.overdue.length > 0 ? `<h4 style="margin:1.5rem 0 1rem;color:var(--danger)">Overdue Payments</h4><table class="data-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Amount</th><th>Balance</th><th>Due Date</th></tr></thead><tbody>${data.overdue.map(o => `<tr><td>${escapeHtml(o.invoice_number)}</td><td>${escapeHtml(o.customer_name)}</td><td>$${formatNumber(o.amount)}</td><td>$${formatNumber(o.balance)}</td><td>${o.due_date}</td></tr>`).join('')}</tbody></table>` : ''}`;
}

async function loadTransactions() {
    try {
        const response = await fetch(`${API_BASE}/stock.php`);
        const result = await response.json();
        if (result.success) renderTransactions(result.data);
    } catch (e) { console.error('Transactions error:', e); }
}

function renderTransactions(transactions) {
    const tbody = document.getElementById('transactionsBody');
    if (!transactions || transactions.length === 0) { tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No transactions found</td></tr>'; return; }
    tbody.innerHTML = transactions.map(t => `<tr><td>${formatDate(t.created_at)}</td><td>${escapeHtml(t.product_name)}</td><td>${escapeHtml(t.sku)}</td><td><span class="transaction-type ${t.type}">${getActivityTitle(t.type)}</span></td><td>${t.type === 'remove' ? '-' : '+'}${t.quantity}</td><td>${escapeHtml(t.notes || '-')}</td></tr>`).join('');
}

function openProductModal(product = null) {
    const modal = document.getElementById('productModal');
    const title = document.getElementById('productModalTitle');
    const form = document.getElementById('productForm');
    form.reset();
    if (product) {
        title.textContent = 'Edit Product';
        document.getElementById('productId').value = product.id;
        document.getElementById('productSku').value = product.sku;
        document.getElementById('productName').value = product.name;
        document.getElementById('productDescription').value = product.description || '';
        document.getElementById('productCategory').value = product.category_id || '';
        document.getElementById('productPrice').value = product.price;
        document.getElementById('productCostPrice').value = product.cost_price || 0;
        document.getElementById('productQuantity').value = product.quantity;
        document.getElementById('productMinStock').value = product.min_stock;
    } else { title.textContent = 'Add Product'; document.getElementById('productId').value = ''; }
    modal.classList.add('active');
}

function closeProductModal() { document.getElementById('productModal').classList.remove('active'); }

async function editProduct(id) {
    try {
        const response = await fetch(`${API_BASE}/products.php?id=${id}`);
        const result = await response.json();
        if (result.success) openProductModal(result.data);
    } catch (e) { console.error('Edit product error:', e); showToast('Error loading product', 'error'); }
}

async function handleProductSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('productId').value;
    const data = { 
        sku: document.getElementById('productSku').value, 
        name: document.getElementById('productName').value, 
        description: document.getElementById('productDescription').value, 
        category_id: document.getElementById('productCategory').value || null,
        price: parseFloat(document.getElementById('productPrice').value) || 0, 
        cost_price: parseFloat(document.getElementById('productCostPrice').value) || 0, 
        quantity: parseInt(document.getElementById('productQuantity').value) || 0, 
        min_stock: parseInt(document.getElementById('productMinStock').value) || 10 
    };
    if (id) data.id = id;
    try {
        const response = await fetch(`${API_BASE}/products.php`, { 
            method: id ? 'PUT' : 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify(data) 
        });
        const result = await response.json();
        if (result.success) { 
            showToast(result.message, 'success'); 
            closeProductModal(); 
            loadProducts(); 
            if (state.currentPage === 'dashboard') loadDashboard(); 
        }
        else showToast(result.error || 'Error saving product', 'error');
    } catch (e) { 
        console.error('Save product error:', e); 
        showToast('Error saving product', 'error'); 
    }
}

async function deleteProduct(id) {
    if (!confirm('Are you sure you want to delete this product?')) return;
    try {
        const response = await fetch(`${API_BASE}/products.php`, { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
        const result = await response.json();
        if (result.success) { showToast('Product deleted successfully', 'success'); loadProducts(); if (state.currentPage === 'dashboard') loadDashboard(); }
        else showToast(result.error || 'Error deleting product', 'error');
    } catch (e) { console.error('Delete product error:', e); showToast('Error deleting product', 'error'); }
}

function openCategoryModal() { document.getElementById('categoryForm').reset(); document.getElementById('categoryModal').classList.add('active'); }
function closeCategoryModal() { document.getElementById('categoryModal').classList.remove('active'); }

async function handleCategorySubmit(e) {
    e.preventDefault();
    const data = { 
        name: document.getElementById('categoryName').value, 
        description: document.getElementById('categoryDescription').value 
    };
    try {
        const response = await fetch(`${API_BASE}/categories.php`, { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify(data) 
        });
        const result = await response.json();
        if (result.success) { 
            showToast('Category created successfully', 'success'); 
            closeCategoryModal(); 
            loadCategories(); 
            // Refresh products page if needed
            if (state.currentPage === 'products') {
                loadProducts();
            }
        }
        else showToast(result.error || 'Error creating category', 'error');
    } catch (e) { 
        console.error('Create category error:', e); 
        showToast('Error creating category', 'error'); 
    }
}

async function deleteCategory(id) {
    if (!confirm('Are you sure you want to delete this category?')) return;
    try {
        const response = await fetch(`${API_BASE}/categories.php`, { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
        const result = await response.json();
        if (result.success) { showToast('Category deleted successfully', 'success'); loadCategories(); }
        else showToast(result.error || 'Error deleting category', 'error');
    } catch (e) { console.error('Delete category error:', e); showToast('Error deleting category', 'error'); }
}

function openStockModal(productId, productName, currentStock) {
    document.getElementById('stockForm').reset();
    document.getElementById('stockProductId').value = productId;
    document.getElementById('stockProductName').textContent = productName;
    document.getElementById('stockCurrent').textContent = currentStock;
    document.getElementById('stockModal').classList.add('active');
}

function closeStockModal() { document.getElementById('stockModal').classList.remove('active'); }

async function handleStockSubmit(e) {
    e.preventDefault();
    const data = { product_id: document.getElementById('stockProductId').value, type: document.getElementById('stockType').value, quantity: parseInt(document.getElementById('stockQuantity').value), notes: document.getElementById('stockNotes').value };
    try {
        const response = await fetch(`${API_BASE}/stock.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        const result = await response.json();
        if (result.success) { showToast('Stock adjusted successfully', 'success'); closeStockModal(); loadProducts(); if (state.currentPage === 'dashboard') loadDashboard(); }
        else showToast(result.error || 'Error adjusting stock', 'error');
    } catch (e) { console.error('Stock adjustment error:', e); showToast('Error adjusting stock', 'error'); }
}

function openCustomerModal(customer = null) {
    const modal = document.getElementById('customerModal');
    const title = document.getElementById('customerModalTitle');
    const form = document.getElementById('customerForm');
    form.reset();
    if (customer) {
        title.textContent = 'Edit Customer';
        document.getElementById('customerId').value = customer.id;
        document.getElementById('customerName').value = customer.name;
        document.getElementById('customerEmail').value = customer.email || '';
        document.getElementById('customerPhone').value = customer.phone || '';
        document.getElementById('customerAddress').value = customer.address || '';
        document.getElementById('customerCreditLimit').value = customer.credit_limit;
    } else { title.textContent = 'Add Customer'; document.getElementById('customerId').value = ''; }
    modal.classList.add('active');
}

function closeCustomerModal() { document.getElementById('customerModal').classList.remove('active'); }

async function editCustomer(id) {
    try {
        const response = await fetch(`${API_BASE}/customers.php?id=${id}`);
        const result = await response.json();
        if (result.success) openCustomerModal(result.data);
    } catch (e) { console.error('Edit customer error:', e); showToast('Error loading customer', 'error'); }
}

async function handleCustomerSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('customerId').value;
    const data = { 
        name: document.getElementById('customerName').value, 
        email: document.getElementById('customerEmail').value, 
        phone: document.getElementById('customerPhone').value, 
        address: document.getElementById('customerAddress').value, 
        credit_limit: parseFloat(document.getElementById('customerCreditLimit').value) || 0 
    };
    if (id) data.id = id;
    try {
        const response = await fetch(`${API_BASE}/customers.php`, { 
            method: id ? 'PUT' : 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify(data) 
        });
        const result = await response.json();
        if (result.success) { 
            showToast(result.message, 'success'); 
            closeCustomerModal(); 
            loadCustomers(); 
        }
        else showToast(result.error || 'Error saving customer', 'error');
    } catch (e) { 
        console.error('Save customer error:', e); 
        showToast('Error saving customer', 'error'); 
    }
}

async function deleteCustomer(id) {
    if (!confirm('Are you sure you want to delete this customer?')) return;
    try {
        const response = await fetch(`${API_BASE}/customers.php`, { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
        const result = await response.json();
        if (result.success) { showToast('Customer deleted successfully', 'success'); loadCustomers(); }
        else showToast(result.error || 'Error deleting customer', 'error');
    } catch (e) { console.error('Delete customer error:', e); showToast('Error deleting customer', 'error'); }
}

function openSupplierModal(supplier = null) {
    const modal = document.getElementById('supplierModal');
    const title = document.getElementById('supplierModalTitle');
    const form = document.getElementById('supplierForm');
    form.reset();
    if (supplier) {
        title.textContent = 'Edit Supplier';
        document.getElementById('supplierId').value = supplier.id;
        document.getElementById('supplierName').value = supplier.name;
        document.getElementById('supplierContact').value = supplier.contact_person || '';
        document.getElementById('supplierPhone').value = supplier.phone || '';
        document.getElementById('supplierEmail').value = supplier.email || '';
        document.getElementById('supplierAddress').value = supplier.address || '';
        document.getElementById('supplierNotes').value = supplier.notes || '';
    } else { title.textContent = 'Add Supplier'; document.getElementById('supplierId').value = ''; }
    modal.classList.add('active');
}

function closeSupplierModal() { document.getElementById('supplierModal').classList.remove('active'); }

async function editSupplier(id) {
    try {
        const response = await fetch(`${API_BASE}/suppliers.php?id=${id}`);
        const result = await response.json();
        if (result.success) openSupplierModal(result.data);
    } catch (e) { console.error('Edit supplier error:', e); showToast('Error loading supplier', 'error'); }
}

async function handleSupplierSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('supplierId').value;
    const data = { 
        name: document.getElementById('supplierName').value, 
        contact_person: document.getElementById('supplierContact').value, 
        phone: document.getElementById('supplierPhone').value, 
        email: document.getElementById('supplierEmail').value, 
        address: document.getElementById('supplierAddress').value, 
        notes: document.getElementById('supplierNotes').value 
    };
    if (id) data.id = id;
    try {
        const response = await fetch(`${API_BASE}/suppliers.php`, { 
            method: id ? 'PUT' : 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify(data) 
        });
        const result = await response.json();
        if (result.success) { 
            showToast(result.message, 'success'); 
            closeSupplierModal(); 
            loadSuppliers(); 
        }
        else showToast(result.error || 'Error saving supplier', 'error');
    } catch (e) { 
        console.error('Save supplier error:', e); 
        showToast('Error saving supplier', 'error'); 
    }
}

async function deleteSupplier(id) {
    if (!confirm('Are you sure you want to delete this supplier?')) return;
    try {
        const response = await fetch(`${API_BASE}/suppliers.php`, { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
        const result = await response.json();
        if (result.success) { showToast('Supplier deleted successfully', 'success'); loadSuppliers(); }
        else showToast(result.error || 'Error deleting supplier', 'error');
    } catch (e) { console.error('Delete supplier error:', e); showToast('Error deleting supplier', 'error'); }
}

async function handleSettingsSubmit(e) {
    e.preventDefault();
    const data = {};
    ['company_name', 'company_address', 'company_phone', 'company_email', 'currency_symbol', 'low_stock_threshold', 'invoice_prefix'].forEach(key => {
        const el = document.getElementById('setting_' + key);
        if (el) data[key] = el.value;
    });
    try {
        const response = await fetch(`${API_BASE}/settings.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        const result = await response.json();
        if (result.success) { showToast('Settings saved successfully', 'success'); loadSettings(); }
        else showToast(result.error || 'Error saving settings', 'error');
    } catch (e) { console.error('Save settings error:', e); showToast('Error saving settings', 'error'); }
}

async function handleAdvancedSettingsSubmit(e) {
    e.preventDefault();
    const data = {};
    const advancedKeys = ['min_order_value', 'discount_type', 'max_discount', 'enable_credit_sales', 'stock_alert_email', 'enable_stock_alerts', 'decimal_places', 'number_format', 'session_timeout', 'enable_auto_backup', 'enable_receipt_printing', 'theme_preference', 'items_per_page', 'date_format'];

    advancedKeys.forEach(key => {
        const el = document.getElementById('setting_' + key);
        if (el) {
            if (el.type === 'checkbox') {
                data[key] = el.checked ? '1' : '0';
            } else {
                data[key] = el.value;
            }
        }
    });

    try {
        const response = await fetch(`${API_BASE}/settings.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        const result = await response.json();
        if (result.success) { showToast('Advanced settings saved successfully', 'success'); loadSettings(); }
        else showToast(result.error || 'Error saving settings', 'error');
    } catch (e) { console.error('Save advanced settings error:', e); showToast('Error saving settings', 'error'); }
}

async function handleLogout() {
    try {
        const response = await fetch('/api/auth/logout.php', { method: 'POST' });
        const result = await response.json();
        if (result.success) {
            showToast('Logout successful', 'success');
            setTimeout(() => {
                window.location.href = '/index.php';
            }, 1000);
        }
    } catch (error) {
        console.error('Logout error:', error);
        showToast('Error logging out', 'error');
    }
}


function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toastMessage');
    toast.className = 'toast ' + type;
    toastMessage.textContent = message;
    toast.classList.add('active');
    setTimeout(() => { toast.classList.remove('active'); }, 3000);
}

function formatNumber(num) { return parseFloat(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function formatDate(dateString) { const date = new Date(dateString); return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
function debounce(func, wait) { let timeout; return function executedFunction(...args) { const later = () => { clearTimeout(timeout); func(...args); }; clearTimeout(timeout); timeout = setTimeout(later, wait); }; }

async function loadChartData() {
    try {
        // Load sales trend data
        const salesResponse = await fetch(`${API_BASE}/reports.php?type=sales&date_from=${getLastNDays(7)}&date_to=${getCurrentDate()}`);
        const salesResult = await salesResponse.json();
        
        if (salesResult.success) {
            renderSalesChart(salesResult.data.daily);
        }
        
        // Load top products data
        const topProductsResponse = await fetch(`${API_BASE}/reports.php?type=top_products&limit=5`);
        const topProductsResult = await topProductsResponse.json();
        
        if (topProductsResult.success) {
            renderTopProductsChart(topProductsResult.data);
        }
        
        // Load inventory by category data
        const inventoryResponse = await fetch(`${API_BASE}/reports.php?type=inventory`);
        const inventoryResult = await inventoryResponse.json();
        
        if (inventoryResult.success) {
            renderCategoryChart(inventoryResult.data.by_category);
        }
    } catch (e) {
        console.error('Chart data error:', e);
    }
}

function renderSalesChart(data) {
    if (!data || data.length === 0) {
        document.getElementById('salesPoints').innerHTML = '<p class="empty-state">No sales data available</p>';
        return;
    }
    
    // Sort data by date
    data.sort((a, b) => new Date(a.date) - new Date(b.date));
    
    // Get last 7 days
    const last7Days = data.slice(-7);
    
    // Find max value for scaling
    const maxValue = Math.max(...last7Days.map(d => d.total_sales));
    
    // Render points
    const pointsContainer = document.getElementById('salesPoints');
    const pathElement = document.getElementById('salesPath');
    
    pointsContainer.innerHTML = '';
    
    const points = [];
    const pointElements = [];
    
    last7Days.forEach((day, index) => {
        const x = (index / (last7Days.length - 1)) * 100;
        const y = 100 - (day.total_sales / maxValue) * 80; // Leave some margin at top
        
        const point = document.createElement('div');
        point.className = 'line-point';
        point.style.left = `${x}%`;
        point.style.top = `${y}%`;
        point.title = `${day.date}: $${formatNumber(day.total_sales)}`;
        
        pointsContainer.appendChild(point);
        points.push({x: x, y: y});
        pointElements.push(point);
    });
    
    // Draw line path
    if (points.length > 1) {
        let pathData = `M ${points[0].x} ${points[0].y}`;
        for (let i = 1; i < points.length; i++) {
            pathData += ` L ${points[i].x} ${points[i].y}`;
        }
        pathElement.setAttribute('viewBox', '0 0 100 100');
        pathElement.setAttribute('preserveAspectRatio', 'none');
        pathElement.innerHTML = `<path d="${pathData}" vector-effect="non-scaling-stroke"></path>`;
    }
}

function renderTopProductsChart(data) {
    const container = document.getElementById('topProductsChart');
    
    if (!data || data.length === 0) {
        container.innerHTML = '<p class="empty-state">No product data available</p>';
        return;
    }
    
    // Sort by total sold and take top 5
    const topProducts = data.slice(0, 5);
    
    // Find max value for scaling
    const maxValue = Math.max(...topProducts.map(p => p.total_sold));
    
    container.innerHTML = '';
    
    topProducts.forEach(product => {
        const heightPercent = (product.total_sold / maxValue) * 80 + 10; // Minimum 10% height
        
        const bar = document.createElement('div');
        bar.className = 'bar';
        bar.style.height = `${heightPercent}%`;
        bar.title = `${product.name}: ${product.total_sold} units sold`;
        
        const value = document.createElement('div');
        value.className = 'bar-value';
        value.textContent = product.total_sold;
        
        const label = document.createElement('div');
        label.className = 'bar-label';
        label.textContent = product.name.length > 10 ? product.name.substring(0, 10) + '...' : product.name;
        
        bar.appendChild(value);
        bar.appendChild(label);
        container.appendChild(bar);
    });
}

function renderCategoryChart(data) {
    const pieContainer = document.getElementById('categoryChart');
    const legendContainer = document.getElementById('categoryLegend');
    const totalElement = document.getElementById('categoryTotal');
    
    if (!data || data.length === 0) {
        pieContainer.innerHTML = '<p class="empty-state">No category data available</p>';
        legendContainer.innerHTML = '';
        totalElement.textContent = '0 Items';
        return;
    }
    
    // Calculate total items
    const totalItems = data.reduce((sum, category) => sum + parseInt(category.total_quantity), 0);
    totalElement.textContent = `${totalItems} Items`;
    
    // Filter out categories with 0 items and sort by value
    const categoriesWithData = data.filter(c => parseInt(c.total_quantity) > 0)
                                  .sort((a, b) => parseInt(b.total_quantity) - parseInt(a.total_quantity));
    
    if (categoriesWithData.length === 0) {
        pieContainer.innerHTML = '<p class="empty-state">No inventory data available</p>';
        legendContainer.innerHTML = '';
        return;
    }
    
    // Clear containers
    pieContainer.innerHTML = '';
    legendContainer.innerHTML = '';
    pieContainer.appendChild(totalElement);
    
    // Define colors
    const colors = ['#6366f1', '#818cf8', '#4f46e5', '#a5b4fc', '#c7d2fe'];
    
    // Calculate angles for pie segments
    let currentAngle = 0;
    
    categoriesWithData.forEach((category, index) => {
        const percentage = (parseInt(category.total_quantity) / totalItems) * 100;
        const angle = (percentage / 100) * 360;
        
        // Create pie segment
        const segment = document.createElement('div');
        segment.className = 'pie-segment';
        segment.style.backgroundColor = colors[index % colors.length];
        segment.style.transform = `rotate(${currentAngle}deg)`;
        segment.style.clipPath = `polygon(50% 50%, 50% 0%, ${100 - 50 * Math.cos((angle * Math.PI) / 180)}% ${50 - 50 * Math.sin((angle * Math.PI) / 180)}%)`;
        segment.title = `${category.name}: ${category.total_quantity} items (${percentage.toFixed(1)}%)`;
        
        pieContainer.appendChild(segment);
        
        // Create legend item
        const legendItem = document.createElement('div');
        legendItem.className = 'legend-item';
        
        const legendColor = document.createElement('div');
        legendColor.className = 'legend-color';
        legendColor.style.backgroundColor = colors[index % colors.length];
        
        const legendText = document.createElement('span');
        legendText.textContent = `${category.name}: ${category.total_quantity} (${percentage.toFixed(1)}%)`;
        
        legendItem.appendChild(legendColor);
        legendItem.appendChild(legendText);
        legendContainer.appendChild(legendItem);
        
        currentAngle += angle;
    });
}

// Helper functions
function getLastNDays(n) {
    const date = new Date();
    date.setDate(date.getDate() - n);
    return date.toISOString().split('T')[0];
}

function getCurrentDate() {
    return new Date().toISOString().split('T')[0];
}

// Purchases functions
async function loadPurchases() {
    try {
        const dateFrom = document.getElementById('purchaseDateFrom').value;
        const dateTo = document.getElementById('purchaseDateTo').value;
        const supplierId = document.getElementById('purchaseSupplierFilter').value;
        
        let url = `${API_BASE}/purchases.php?`;
        if (dateFrom) url += `date_from=${dateFrom}&`;
        if (dateTo) url += `date_to=${dateTo}&`;
        if (supplierId) url += `supplier_id=${supplierId}&`;
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            renderPurchases(result.data);
            // Update supplier filter options
            updatePurchaseSupplierSelects();
        }
    } catch (e) { 
        console.error('Purchases error:', e); 
        showToast('Error loading purchases', 'error'); 
    }
}

function renderPurchases(purchases) {
    const tbody = document.getElementById('purchasesBody');
    
    if (!purchases || purchases.length === 0) { 
        tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No purchases found</td></tr>';
        return;
    }
    
    tbody.innerHTML = purchases.map(purchase => `
        <tr>
            <td>${escapeHtml(purchase.invoice_number)}</td>
            <td>${escapeHtml(purchase.supplier_name || 'Unknown Supplier')}</td>
            <td>$${formatNumber(purchase.total)}</td>
            <td>
                <span class="status-badge ${purchase.payment_status}">
                    ${purchase.payment_status.charAt(0).toUpperCase() + purchase.payment_status.slice(1)}
                </span>
            </td>
            <td>${formatDate(purchase.created_at)}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-secondary btn-sm" onclick="viewPurchase(${purchase.id})">View</button>
                    <button class="btn btn-icon btn-secondary btn-sm" onclick="deletePurchase(${purchase.id})" title="Delete">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function updatePurchaseSupplierSelects() {
    // This will be called to update the supplier filter dropdown
    const options = '<option value="">All Suppliers</option>' + state.suppliers.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
    const el = document.getElementById('purchaseSupplierFilter');
    if (el) el.innerHTML = options;
}

function openPurchaseModal() {
    // TODO: Implement purchase modal
    alert('Purchase functionality coming soon!');
}

function viewPurchase(id) {
    // TODO: Implement view purchase details
    alert('View purchase details functionality coming soon!');
}

function deletePurchase(id) {
    if (!confirm('Are you sure you want to delete this purchase?')) return;
    
    // TODO: Implement delete purchase
    alert('Delete purchase functionality coming soon!');
}
