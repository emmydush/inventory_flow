const API_BASE = '/api';

const state = {
    products: [],
    categories: [],
    currentPage: 'dashboard'
};

document.addEventListener('DOMContentLoaded', () => {
    initApp();
});

async function initApp() {
    await initDatabase();
    await loadCategories();
    await loadDashboard();
    setupEventListeners();
}

async function initDatabase() {
    try {
        await fetch('/config/init_db.php');
    } catch (error) {
        console.error('Error initializing database:', error);
    }
}

function setupEventListeners() {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', () => {
            navigateTo(item.dataset.page);
        });
    });

    document.getElementById('menuToggle').addEventListener('click', toggleSidebar);

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

    document.getElementById('categoryFilter').addEventListener('change', loadProducts);
    document.getElementById('productSearch').addEventListener('input', debounce(loadProducts, 300));
    document.getElementById('globalSearch').addEventListener('input', debounce(handleGlobalSearch, 300));

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.classList.remove('active');
            }
        });
    });
}

function navigateTo(page) {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.toggle('active', item.dataset.page === page);
    });

    document.querySelectorAll('.page').forEach(p => {
        p.classList.toggle('active', p.id === page + 'Page');
    });

    const titles = {
        dashboard: 'Dashboard',
        products: 'Products',
        categories: 'Categories',
        transactions: 'Stock History'
    };
    document.getElementById('pageTitle').textContent = titles[page] || 'Dashboard';

    state.currentPage = page;

    if (page === 'dashboard') loadDashboard();
    else if (page === 'products') loadProducts();
    else if (page === 'categories') loadCategories();
    else if (page === 'transactions') loadTransactions();

    if (window.innerWidth <= 1024) {
        toggleSidebar();
    }
}

function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
}

async function loadDashboard() {
    try {
        const response = await fetch(`${API_BASE}/dashboard.php`);
        const result = await response.json();

        if (result.success) {
            const data = result.data;

            document.getElementById('totalProducts').textContent = data.total_products;
            document.getElementById('lowStockCount').textContent = data.low_stock_count;
            document.getElementById('outOfStockCount').textContent = data.out_of_stock_count;
            document.getElementById('totalValue').textContent = '$' + formatNumber(data.total_value);
            document.getElementById('lowStockBadge').textContent = data.low_stock_count;

            renderLowStockList(data.low_stock_items);
            renderRecentActivity(data.recent_transactions);
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showToast('Error loading dashboard', 'error');
    }
}

function renderLowStockList(items) {
    const container = document.getElementById('lowStockList');

    if (!items || items.length === 0) {
        container.innerHTML = '<p class="empty-state">No low stock items</p>';
        return;
    }

    container.innerHTML = items.map(item => `
        <div class="alert-item ${item.quantity === 0 ? 'critical' : ''}">
            <div class="alert-item-info">
                <h4>${escapeHtml(item.name)}</h4>
                <span>${item.category_name || 'Uncategorized'} | SKU: ${escapeHtml(item.sku)}</span>
            </div>
            <div class="alert-item-stock">
                <div class="stock-value">${item.quantity}</div>
                <div class="stock-label">In Stock</div>
            </div>
        </div>
    `).join('');
}

function renderRecentActivity(transactions) {
    const container = document.getElementById('recentActivity');

    if (!transactions || transactions.length === 0) {
        container.innerHTML = '<p class="empty-state">No recent activity</p>';
        return;
    }

    container.innerHTML = transactions.map(t => {
        const icon = getActivityIcon(t.type);
        return `
            <div class="activity-item">
                <div class="activity-icon ${t.type}">
                    ${icon}
                </div>
                <div class="activity-content">
                    <h4>${getActivityTitle(t.type)} - ${escapeHtml(t.product_name)}</h4>
                    <p>${t.quantity} units ${t.notes ? '| ' + escapeHtml(t.notes) : ''}</p>
                    <time>${formatDate(t.created_at)}</time>
                </div>
            </div>
        `;
    }).join('');
}

function getActivityIcon(type) {
    const icons = {
        add: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        remove: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        initial: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>'
    };
    return icons[type] || icons.initial;
}

function getActivityTitle(type) {
    const titles = {
        add: 'Stock Added',
        remove: 'Stock Removed',
        initial: 'Initial Stock'
    };
    return titles[type] || 'Stock Update';
}

async function loadCategories() {
    try {
        const response = await fetch(`${API_BASE}/categories.php`);
        const result = await response.json();

        if (result.success) {
            state.categories = result.data;
            updateCategorySelects();

            if (state.currentPage === 'categories') {
                renderCategories();
            }
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

function updateCategorySelects() {
    const options = '<option value="">Select Category</option>' +
        state.categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');

    document.getElementById('productCategory').innerHTML = options;
    document.getElementById('categoryFilter').innerHTML = '<option value="">All Categories</option>' +
        state.categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
}

function renderCategories() {
    const container = document.getElementById('categoriesGrid');

    if (state.categories.length === 0) {
        container.innerHTML = '<p class="empty-state">No categories found</p>';
        return;
    }

    container.innerHTML = state.categories.map(category => `
        <div class="category-card">
            <div class="category-header">
                <h3>${escapeHtml(category.name)}</h3>
                <button class="btn btn-icon btn-secondary" onclick="deleteCategory(${category.id})" title="Delete">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                </button>
            </div>
            <p class="category-description">${escapeHtml(category.description || 'No description')}</p>
            <div class="category-count">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                </svg>
                ${category.product_count} products
            </div>
        </div>
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

        if (result.success) {
            state.products = result.data;
            renderProducts();
        }
    } catch (error) {
        console.error('Error loading products:', error);
        showToast('Error loading products', 'error');
    }
}

function renderProducts() {
    const container = document.getElementById('productsGrid');

    if (state.products.length === 0) {
        container.innerHTML = '<p class="empty-state">No products found. Add your first product!</p>';
        return;
    }

    container.innerHTML = state.products.map(product => {
        const stockStatus = getStockStatus(product.quantity, product.min_stock);
        return `
            <div class="product-card">
                <div class="product-header">
                    <div class="product-title">
                        <h3>${escapeHtml(product.name)}</h3>
                        <span class="sku">SKU: ${escapeHtml(product.sku)}</span>
                    </div>
                    <span class="product-category">${product.category_name || 'Uncategorized'}</span>
                </div>
                <div class="product-details">
                    <div class="product-detail">
                        <label>Price</label>
                        <span>$${formatNumber(product.price)}</span>
                    </div>
                    <div class="product-detail">
                        <label>Quantity</label>
                        <div class="stock-status">
                            <span class="stock-indicator ${stockStatus.class}"></span>
                            <span>${product.quantity}</span>
                        </div>
                    </div>
                </div>
                <div class="product-actions">
                    <button class="btn btn-secondary btn-sm" onclick="openStockModal(${product.id}, '${escapeHtml(product.name)}', ${product.quantity})">
                        Adjust Stock
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="editProduct(${product.id})">
                        Edit
                    </button>
                    <button class="btn btn-icon btn-secondary btn-sm" onclick="deleteProduct(${product.id})" title="Delete">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

function getStockStatus(quantity, minStock) {
    if (quantity === 0) return { class: 'out-of-stock', text: 'Out of Stock' };
    if (quantity <= minStock) return { class: 'low-stock', text: 'Low Stock' };
    return { class: 'in-stock', text: 'In Stock' };
}

async function loadTransactions() {
    try {
        const response = await fetch(`${API_BASE}/stock.php`);
        const result = await response.json();

        if (result.success) {
            renderTransactions(result.data);
        }
    } catch (error) {
        console.error('Error loading transactions:', error);
    }
}

function renderTransactions(transactions) {
    const tbody = document.getElementById('transactionsBody');

    if (!transactions || transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No transactions found</td></tr>';
        return;
    }

    tbody.innerHTML = transactions.map(t => `
        <tr>
            <td>${formatDate(t.created_at)}</td>
            <td>${escapeHtml(t.product_name)}</td>
            <td>${escapeHtml(t.sku)}</td>
            <td><span class="transaction-type ${t.type}">${getActivityTitle(t.type)}</span></td>
            <td>${t.type === 'remove' ? '-' : '+'}${t.quantity}</td>
            <td>${escapeHtml(t.notes || '-')}</td>
        </tr>
    `).join('');
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
        document.getElementById('productQuantity').value = product.quantity;
        document.getElementById('productMinStock').value = product.min_stock;
    } else {
        title.textContent = 'Add Product';
        document.getElementById('productId').value = '';
    }

    modal.classList.add('active');
}

function closeProductModal() {
    document.getElementById('productModal').classList.remove('active');
}

async function editProduct(id) {
    try {
        const response = await fetch(`${API_BASE}/products.php?id=${id}`);
        const result = await response.json();

        if (result.success) {
            openProductModal(result.data);
        }
    } catch (error) {
        console.error('Error loading product:', error);
        showToast('Error loading product', 'error');
    }
}

async function handleProductSubmit(e) {
    e.preventDefault();

    const id = document.getElementById('productId').value;
    const data = {
        sku: document.getElementById('productSku').value,
        name: document.getElementById('productName').value,
        description: document.getElementById('productDescription').value,
        category_id: document.getElementById('productCategory').value,
        price: parseFloat(document.getElementById('productPrice').value) || 0,
        quantity: parseInt(document.getElementById('productQuantity').value) || 0,
        min_stock: parseInt(document.getElementById('productMinStock').value) || 10
    };

    if (id) {
        data.id = id;
    }

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
        } else {
            showToast(result.error || 'Error saving product', 'error');
        }
    } catch (error) {
        console.error('Error saving product:', error);
        showToast('Error saving product', 'error');
    }
}

async function deleteProduct(id) {
    if (!confirm('Are you sure you want to delete this product?')) return;

    try {
        const response = await fetch(`${API_BASE}/products.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });

        const result = await response.json();

        if (result.success) {
            showToast('Product deleted successfully', 'success');
            loadProducts();
            if (state.currentPage === 'dashboard') loadDashboard();
        } else {
            showToast(result.error || 'Error deleting product', 'error');
        }
    } catch (error) {
        console.error('Error deleting product:', error);
        showToast('Error deleting product', 'error');
    }
}

function openCategoryModal() {
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryModal').classList.add('active');
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.remove('active');
}

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
        } else {
            showToast(result.error || 'Error creating category', 'error');
        }
    } catch (error) {
        console.error('Error creating category:', error);
        showToast('Error creating category', 'error');
    }
}

async function deleteCategory(id) {
    if (!confirm('Are you sure you want to delete this category? Products in this category will become uncategorized.')) return;

    try {
        const response = await fetch(`${API_BASE}/categories.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });

        const result = await response.json();

        if (result.success) {
            showToast('Category deleted successfully', 'success');
            loadCategories();
        } else {
            showToast(result.error || 'Error deleting category', 'error');
        }
    } catch (error) {
        console.error('Error deleting category:', error);
        showToast('Error deleting category', 'error');
    }
}

function openStockModal(productId, productName, currentStock) {
    document.getElementById('stockForm').reset();
    document.getElementById('stockProductId').value = productId;
    document.getElementById('stockProductName').textContent = productName;
    document.getElementById('stockCurrent').textContent = currentStock;
    document.getElementById('stockModal').classList.add('active');
}

function closeStockModal() {
    document.getElementById('stockModal').classList.remove('active');
}

async function handleStockSubmit(e) {
    e.preventDefault();

    const data = {
        product_id: document.getElementById('stockProductId').value,
        type: document.getElementById('stockType').value,
        quantity: parseInt(document.getElementById('stockQuantity').value),
        notes: document.getElementById('stockNotes').value
    };

    try {
        const response = await fetch(`${API_BASE}/stock.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Stock adjusted successfully', 'success');
            closeStockModal();
            loadProducts();
            if (state.currentPage === 'dashboard') loadDashboard();
            if (state.currentPage === 'transactions') loadTransactions();
        } else {
            showToast(result.error || 'Error adjusting stock', 'error');
        }
    } catch (error) {
        console.error('Error adjusting stock:', error);
        showToast('Error adjusting stock', 'error');
    }
}

function handleGlobalSearch(e) {
    const query = e.target.value.trim();
    if (query.length >= 2) {
        navigateTo('products');
        document.getElementById('productSearch').value = query;
        loadProducts();
    }
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toastMessage');

    toast.className = 'toast ' + type;
    toastMessage.textContent = message;
    toast.classList.add('active');

    setTimeout(() => {
        toast.classList.remove('active');
    }, 3000);
}

function formatNumber(num) {
    return parseFloat(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
