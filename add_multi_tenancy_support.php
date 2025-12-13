<?php
// Script to add multi-tenancy support to the existing database

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        echo "Database connection failed\n";
        exit;
    }
    
    // Create organizations table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS organizations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            slug VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    echo "Organizations table created successfully\n";
    
    // Add organization_id to all relevant tables
    $tablesToAddOrgId = [
        'users',
        'categories',
        'products',
        'customers',
        'suppliers',
        'sales',
        'sale_items',
        'credit_sales',
        'credit_payments',
        'purchases',
        'purchase_items',
        'settings',
        'departments'
    ];
    
    foreach ($tablesToAddOrgId as $table) {
        // Check if organization_id column already exists
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE 'organization_id'");
        $stmt->execute();
        $columnExists = $stmt->fetch();
        
        if (!$columnExists) {
            $conn->exec("ALTER TABLE `$table` ADD COLUMN organization_id INT NULL");
            echo "Added organization_id column to $table table\n";
        } else {
            echo "organization_id column already exists in $table table\n";
        }
        
        // Add foreign key constraint if it doesn't exist
        try {
            $conn->exec("ALTER TABLE `$table` ADD CONSTRAINT fk_{$table}_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL");
            echo "Added foreign key constraint to $table table\n";
        } catch (Exception $e) {
            // Constraint might already exist
            echo "Foreign key constraint for $table table: " . $e->getMessage() . "\n";
        }
    }
    
    // Add indexes for better performance
    $conn->exec("ALTER TABLE `users` ADD INDEX idx_users_org (organization_id)");
    $conn->exec("ALTER TABLE `categories` ADD INDEX idx_categories_org (organization_id)");
    $conn->exec("ALTER TABLE `products` ADD INDEX idx_products_org (organization_id)");
    $conn->exec("ALTER TABLE `customers` ADD INDEX idx_customers_org (organization_id)");
    $conn->exec("ALTER TABLE `suppliers` ADD INDEX idx_suppliers_org (organization_id)");
    $conn->exec("ALTER TABLE `sales` ADD INDEX idx_sales_org (organization_id)");
    $conn->exec("ALTER TABLE `purchases` ADD INDEX idx_purchases_org (organization_id)");
    $conn->exec("ALTER TABLE `settings` ADD INDEX idx_settings_org (organization_id)");
    $conn->exec("ALTER TABLE `departments` ADD INDEX idx_departments_org (organization_id)");
    
    echo "Indexes created successfully\n";
    
    // Create a default organization if none exists
    $orgCheck = $conn->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
    if ($orgCheck == 0) {
        $conn->exec("
            INSERT INTO organizations (name, slug, description) VALUES 
            ('Default Organization', 'default-org', 'Default organization for the system')
        ");
        echo "Default organization created\n";
        
        // Assign all existing data to the default organization
        $defaultOrgId = $conn->lastInsertId();
        
        foreach ($tablesToAddOrgId as $table) {
            $conn->exec("UPDATE `$table` SET organization_id = $defaultOrgId WHERE organization_id IS NULL");
            echo "Assigned existing $table records to default organization\n";
        }
    }
    
    echo "Multi-tenancy schema updates completed successfully\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>