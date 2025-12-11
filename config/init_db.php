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

    echo json_encode(['success' => true, 'message' => 'Database initialized successfully']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
