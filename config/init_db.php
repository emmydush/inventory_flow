<?php
require_once __DIR__ . '/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Please ensure MySQL is running and credentials are correct.']);
    exit;
}

try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sku VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            category_id INTEGER,
            quantity INTEGER DEFAULT 0,
            price DECIMAL(10, 2) DEFAULT 0.00,
            cost_price DECIMAL(10, 2) DEFAULT 0.00,
            min_stock INTEGER DEFAULT 10,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS stock_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INTEGER,
            type VARCHAR(20) NOT NULL,
            quantity INTEGER NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            phone VARCHAR(50),
            address TEXT,
            credit_limit DECIMAL(10, 2) DEFAULT 0.00,
            credit_balance DECIMAL(10, 2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            contact_person VARCHAR(255),
            email VARCHAR(255),
            phone VARCHAR(50),
            address TEXT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_number VARCHAR(50) NOT NULL UNIQUE,
            customer_id INTEGER,
            subtotal DECIMAL(10, 2) DEFAULT 0.00,
            tax DECIMAL(10, 2) DEFAULT 0.00,
            discount DECIMAL(10, 2) DEFAULT 0.00,
            total DECIMAL(10, 2) DEFAULT 0.00,
            payment_method VARCHAR(50) DEFAULT 'cash',
            payment_status VARCHAR(20) DEFAULT 'paid',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS sale_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INTEGER,
            product_id INTEGER,
            product_name VARCHAR(255),
            quantity INTEGER NOT NULL,
            unit_price DECIMAL(10, 2) NOT NULL,
            total DECIMAL(10, 2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS credit_sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INTEGER,
            customer_id INTEGER,
            amount DECIMAL(10, 2) NOT NULL,
            amount_paid DECIMAL(10, 2) DEFAULT 0.00,
            balance DECIMAL(10, 2) NOT NULL,
            due_date DATE,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS credit_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            credit_sale_id INTEGER,
            amount DECIMAL(10, 2) NOT NULL,
            payment_method VARCHAR(50) DEFAULT 'cash',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (credit_sale_id) REFERENCES credit_sales(id) ON DELETE CASCADE
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            role VARCHAR(50) DEFAULT 'cashier',
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            setting_type VARCHAR(50) DEFAULT 'text',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $check = $conn->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($check == 0) {
        $conn->exec("
            INSERT INTO categories (name, description) VALUES
            ('Electronics', 'Electronic devices and accessories'),
            ('Clothing', 'Apparel and fashion items'),
            ('Food & Beverages', 'Consumable products'),
            ('Office Supplies', 'Stationery and office equipment'),
            ('Home & Garden', 'Household and gardening items')
        ");
    }

    $settingsCheck = $conn->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($settingsCheck == 0) {
        $conn->exec("
            INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
            ('company_name', 'InventoryPro', 'text'),
            ('company_address', '', 'textarea'),
            ('company_phone', '', 'text'),
            ('company_email', '', 'email'),
            ('tax_rate', '0', 'number'),
            ('currency_symbol', 'FRW', 'text'),
            ('low_stock_threshold', '10', 'number'),
            ('invoice_prefix', 'INV-', 'text'),
            ('min_order_value', '0', 'number'),
            ('discount_type', 'percentage', 'text'),
            ('max_discount', '50', 'number'),
            ('enable_credit_sales', '1', 'boolean'),
            ('stock_alert_email', '', 'email'),
            ('enable_stock_alerts', '1', 'boolean'),
            ('decimal_places', '2', 'number'),
            ('number_format', '1000.00', 'text'),
            ('session_timeout', '30', 'number'),
            ('enable_auto_backup', '0', 'boolean'),
            ('enable_receipt_printing', '1', 'boolean'),
            ('theme_preference', 'light', 'text'),
            ('items_per_page', '20', 'number'),
            ('date_format', 'MM/DD/YYYY', 'text')
        ");
    }

    $usersCheck = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($usersCheck == 0) {
        $defaultPassword = password_hash('admin123', PASSWORD_BCRYPT);
        $conn->exec("
            INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES
            ('admin', 'admin@inventorypro.local', '$defaultPassword', 'Administrator', 'admin', 'active')
        ");
    }

    echo json_encode(['success' => true, 'message' => 'Database initialized successfully']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
