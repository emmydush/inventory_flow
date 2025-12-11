<?php
require_once __DIR__ . '/database.php';

$database = new Database();
$conn = $database->getConnection();

try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id SERIAL PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS products (
            id SERIAL PRIMARY KEY,
            sku VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
            quantity INTEGER DEFAULT 0,
            price DECIMAL(10, 2) DEFAULT 0.00,
            cost_price DECIMAL(10, 2) DEFAULT 0.00,
            min_stock INTEGER DEFAULT 10,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS stock_transactions (
            id SERIAL PRIMARY KEY,
            product_id INTEGER REFERENCES products(id) ON DELETE CASCADE,
            type VARCHAR(20) NOT NULL,
            quantity INTEGER NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS customers (
            id SERIAL PRIMARY KEY,
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
            id SERIAL PRIMARY KEY,
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
            id SERIAL PRIMARY KEY,
            invoice_number VARCHAR(50) NOT NULL UNIQUE,
            customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
            subtotal DECIMAL(10, 2) DEFAULT 0.00,
            tax DECIMAL(10, 2) DEFAULT 0.00,
            discount DECIMAL(10, 2) DEFAULT 0.00,
            total DECIMAL(10, 2) DEFAULT 0.00,
            payment_method VARCHAR(50) DEFAULT 'cash',
            payment_status VARCHAR(20) DEFAULT 'paid',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS sale_items (
            id SERIAL PRIMARY KEY,
            sale_id INTEGER REFERENCES sales(id) ON DELETE CASCADE,
            product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,
            product_name VARCHAR(255),
            quantity INTEGER NOT NULL,
            unit_price DECIMAL(10, 2) NOT NULL,
            total DECIMAL(10, 2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS credit_sales (
            id SERIAL PRIMARY KEY,
            sale_id INTEGER REFERENCES sales(id) ON DELETE CASCADE,
            customer_id INTEGER REFERENCES customers(id) ON DELETE CASCADE,
            amount DECIMAL(10, 2) NOT NULL,
            amount_paid DECIMAL(10, 2) DEFAULT 0.00,
            balance DECIMAL(10, 2) NOT NULL,
            due_date DATE,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS credit_payments (
            id SERIAL PRIMARY KEY,
            credit_sale_id INTEGER REFERENCES credit_sales(id) ON DELETE CASCADE,
            amount DECIMAL(10, 2) NOT NULL,
            payment_method VARCHAR(50) DEFAULT 'cash',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id SERIAL PRIMARY KEY,
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
            ('currency_symbol', '\$', 'text'),
            ('low_stock_threshold', '10', 'number'),
            ('invoice_prefix', 'INV-', 'text')
        ");
    }

    echo json_encode(['success' => true, 'message' => 'Database initialized successfully']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
