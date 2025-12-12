<?php
header('Content-Type: application/json');

try {
    require_once 'database.php';
    
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Create tables
    $conn->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            category_id INTEGER,
            price DECIMAL(10, 2) NOT NULL,
            stock_quantity INTEGER NOT NULL DEFAULT 0,
            sku VARCHAR(100),
            status VARCHAR(20) DEFAULT 'active',
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
            department_id INT NULL,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
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

    $conn->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50),
            entity_id INT,
            old_values JSON,
            new_values JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (user_id), INDEX (created_at), INDEX (entity_type)
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(255) NOT NULL UNIQUE,
            ip_address VARCHAR(45),
            user_agent TEXT,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (user_id), INDEX (session_token), INDEX (expires_at)
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            table_name VARCHAR(100),
            record_id INT,
            changes JSON,
            status VARCHAR(20) DEFAULT 'success',
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX (user_id), INDEX (created_at), INDEX (table_name)
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS user_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role VARCHAR(50) NOT NULL,
            permission VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_role_permission (role, permission)
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS individual_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission VARCHAR(100) NOT NULL,
            granted BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_permission (user_id, permission)
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS real_time_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            event_type VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50),
            entity_id INT,
            event_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX (created_at), INDEX (event_type)
        )
    ");

    // Add default data if not exists
    $categoriesCheck = $conn->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($categoriesCheck == 0) {
        $conn->exec("
            INSERT INTO categories (name, description) VALUES 
            ('Electronics', 'Electronic devices and accessories'),
            ('Clothing', 'Apparel and fashion items'),
            ('Home & Garden', 'Home improvement and garden supplies'),
            ('Books', 'Books and educational materials'),
            ('Sports', 'Sports equipment and accessories')
        ");
    }

    $productsCheck = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
    if ($productsCheck == 0) {
        $conn->exec("
            INSERT INTO products (name, description, category_id, price, stock_quantity, sku) VALUES
            ('iPhone 13 Pro', 'Latest Apple smartphone with advanced camera system', 1, 999.99, 25, 'IPH13PRO'),
            ('Samsung Galaxy S22', 'Android flagship smartphone with excellent display', 1, 899.99, 30, 'SGS22'),
            ('MacBook Pro 16\"', 'Professional laptop for creators and developers', 1, 2399.99, 15, 'MBP16'),
            ('Nike Air Max 270', 'Comfortable running shoes with air cushioning', 2, 129.99, 50, 'NAM270'),
            ('Levi''s 501 Jeans', 'Classic straight fit denim jeans', 2, 59.99, 100, 'LV501'),
            ('Garden Hose 50ft', 'Durable rubber garden hose for watering plants', 3, 24.99, 75, 'GH50'),
            ('Harry Potter Collection', 'Complete set of Harry Potter books', 4, 89.99, 40, 'HPSET'),
            ('Yoga Mat', 'Non-slip yoga mat for exercise and meditation', 5, 29.99, 60, 'YMAT')
        ");
    }

    $customersCheck = $conn->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    if ($customersCheck == 0) {
        $conn->exec("
            INSERT INTO customers (name, email, phone, address, credit_limit) VALUES
            ('John Smith', 'john.smith@email.com', '(555) 123-4567', '123 Main St, Anytown, USA', 500.00),
            ('Sarah Johnson', 'sarah.johnson@email.com', '(555) 987-6543', '456 Oak Ave, Somewhere, USA', 1000.00),
            ('Mike Davis', 'mike.davis@email.com', '(555) 456-7890', '789 Pine Rd, Elsewhere, USA', 250.00)
        ");
    }

    $suppliersCheck = $conn->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
    if ($suppliersCheck == 0) {
        $conn->exec("
            INSERT INTO suppliers (name, contact_person, email, phone, address) VALUES
            ('Tech Distributors Inc', 'Robert Brown', 'robert@techdist.com', '(555) 111-2222', '123 Tech Blvd, Electronics City, USA'),
            ('Fashion Wholesale Co', 'Jennifer Lee', 'jennifer@fashionwholesale.com', '(555) 333-4444', '456 Fashion St, Apparel Town, USA'),
            ('Garden Supply Co', 'Michael Green', 'michael@gardensupply.com', '(555) 555-6666', '789 Garden Ln, Green Valley, USA')
        ");
    }

    $settingsCheck = $conn->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($settingsCheck == 0) {
        $conn->exec("
            INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
            ('company_name', 'InventoryPro Demo', 'text'),
            ('currency_symbol', '$', 'text'),
            ('tax_rate', '8.5', 'number'),
            ('low_stock_threshold', '10', 'number')
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

    $permissionsCheck = $conn->query("SELECT COUNT(*) FROM user_permissions")->fetchColumn();
    if ($permissionsCheck == 0) {
        $permissions = [
            // Admin permissions
            ['role' => 'admin', 'permission' => 'view_dashboard'],
            ['role' => 'admin', 'permission' => 'manage_users'],
            ['role' => 'admin', 'permission' => 'manage_products'],
            ['role' => 'admin', 'permission' => 'manage_categories'],
            ['role' => 'admin', 'permission' => 'manage_customers'],
            ['role' => 'admin', 'permission' => 'manage_suppliers'],
            ['role' => 'admin', 'permission' => 'process_sales'],
            ['role' => 'admin', 'permission' => 'manage_credit_sales'],
            ['role' => 'admin', 'permission' => 'view_reports'],
            ['role' => 'admin', 'permission' => 'manage_settings'],
            ['role' => 'admin', 'permission' => 'view_audit_logs'],
            ['role' => 'admin', 'permission' => 'manage_inventory'],
            ['role' => 'admin', 'permission' => 'export_data'],
            ['role' => 'admin', 'permission' => 'import_data'],
            ['role' => 'admin', 'permission' => 'manage_departments'],
            ['role' => 'admin', 'permission' => 'view_all_data'],
            ['role' => 'admin', 'permission' => 'delete_records'],

            // Manager permissions
            ['role' => 'manager', 'permission' => 'view_dashboard'],
            ['role' => 'manager', 'permission' => 'manage_products'],
            ['role' => 'manager', 'permission' => 'manage_categories'],
            ['role' => 'manager', 'permission' => 'manage_customers'],
            ['role' => 'manager', 'permission' => 'manage_suppliers'],
            ['role' => 'manager', 'permission' => 'process_sales'],
            ['role' => 'manager', 'permission' => 'manage_credit_sales'],
            ['role' => 'manager', 'permission' => 'view_reports'],
            ['role' => 'manager', 'permission' => 'manage_inventory'],
            ['role' => 'manager', 'permission' => 'export_data'],
            ['role' => 'manager', 'permission' => 'view_department_data'],

            // Cashier permissions
            ['role' => 'cashier', 'permission' => 'view_dashboard'],
            ['role' => 'cashier', 'permission' => 'view_products'],
            ['role' => 'cashier', 'permission' => 'process_sales'],
            ['role' => 'cashier', 'permission' => 'view_customers'],
            ['role' => 'cashier', 'permission' => 'view_own_data'],
        ];

        foreach ($permissions as $perm) {
            $conn->exec(sprintf(
                "INSERT INTO user_permissions (role, permission) VALUES ('%s', '%s')",
                $perm['role'],
                $perm['permission']
            ));
        }
    }

    // Check if departments exist, if not create default ones
    $departmentsCheck = $conn->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    if ($departmentsCheck == 0) {
        $conn->exec("
            INSERT INTO departments (name, description) VALUES 
            ('Sales', 'Sales department'),
            ('Inventory', 'Inventory management department'),
            ('Administration', 'Administrative department')
        ");
    }

    echo json_encode(['success' => true, 'message' => 'Database initialized successfully']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>