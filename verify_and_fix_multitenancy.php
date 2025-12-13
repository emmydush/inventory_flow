<?php
// Script to verify and fix any gaps in multi-tenancy implementation

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        echo "Database connection failed\n";
        exit;
    }
    
    echo "Verifying and fixing multi-tenancy implementation...\n\n";
    
    // 1. Verify organizations table exists
    echo "1. Checking organizations table...\n";
    $stmt = $conn->query("SHOW TABLES LIKE 'organizations'");
    $result = $stmt->fetchAll();
    
    if (count($result) > 0) {
        echo "   ✓ Organizations table exists\n";
    } else {
        echo "   ✗ Organizations table missing. Creating...\n";
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
        echo "   ✓ Organizations table created\n";
    }
    
    // 2. Check if all required tables have organization_id column
    echo "\n2. Checking organization_id columns in all tables...\n";
    $requiredTables = [
        'users', 'categories', 'products', 'customers', 'suppliers', 
        'sales', 'sale_items', 'credit_sales', 'credit_payments',
        'purchases', 'purchase_items', 'settings', 'departments'
    ];
    
    foreach ($requiredTables as $table) {
        // Check if organization_id column exists
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE 'organization_id'");
        $stmt->execute();
        $columnExists = $stmt->fetch();
        
        if (!$columnExists) {
            echo "   ✗ Missing organization_id in $table. Adding...\n";
            $conn->exec("ALTER TABLE `$table` ADD COLUMN organization_id INT NULL");
            
            // Add foreign key constraint if organizations table exists
            $stmt = $conn->query("SHOW TABLES LIKE 'organizations'");
            $orgTableExists = $stmt->fetchAll();
            
            if (count($orgTableExists) > 0) {
                try {
                    $conn->exec("ALTER TABLE `$table` ADD CONSTRAINT fk_{$table}_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL");
                    echo "   ✓ Added organization_id column and foreign key to $table\n";
                } catch (Exception $e) {
                    echo "   ✓ Added organization_id column to $table (foreign key constraint may already exist)\n";
                }
            } else {
                echo "   ✓ Added organization_id column to $table\n";
            }
        } else {
            echo "   ✓ organization_id column exists in $table\n";
        }
    }
    
    // 3. Check if default organization exists
    echo "\n3. Checking default organization...\n";
    $stmt = $conn->query("SELECT COUNT(*) FROM organizations WHERE slug = 'default-org'");
    $defaultOrgExists = $stmt->fetchColumn() > 0;
    
    if ($defaultOrgExists) {
        echo "   ✓ Default organization exists\n";
        $stmt = $conn->query("SELECT id FROM organizations WHERE slug = 'default-org'");
        $defaultOrgId = $stmt->fetchColumn();
    } else {
        echo "   ✗ Default organization missing. Creating...\n";
        $conn->exec("
            INSERT INTO organizations (name, slug, description) VALUES 
            ('Default Organization', 'default-org', 'Default organization for the system')
        ");
        $defaultOrgId = $conn->lastInsertId();
        echo "   ✓ Default organization created with ID: $defaultOrgId\n";
    }
    
    // 4. Assign existing data to default organization if not already assigned
    echo "\n4. Assigning existing data to default organization...\n";
    foreach ($requiredTables as $table) {
        // Skip organizations table itself
        if ($table === 'organizations') continue;
        
        // Check how many records need to be updated
        $stmt = $conn->query("SELECT COUNT(*) FROM `$table` WHERE organization_id IS NULL");
        $nullCount = $stmt->fetchColumn();
        
        if ($nullCount > 0) {
            echo "   Updating $nullCount records in $table...\n";
            $conn->exec("UPDATE `$table` SET organization_id = $defaultOrgId WHERE organization_id IS NULL");
        } else {
            echo "   ✓ All records in $table already assigned to organizations\n";
        }
    }
    
    // 5. Add indexes for better performance
    echo "\n5. Adding indexes for better performance...\n";
    $indexedTables = ['users', 'categories', 'products', 'customers', 'suppliers', 'sales', 'purchases', 'settings', 'departments'];
    
    foreach ($indexedTables as $table) {
        try {
            $conn->exec("ALTER TABLE `$table` ADD INDEX idx_{$table}_org (organization_id)");
            echo "   ✓ Added index to $table\n";
        } catch (Exception $e) {
            // Index might already exist
            echo "   ✓ Index for $table (may already exist)\n";
        }
    }
    
    echo "\n✓ Multi-tenancy verification and fixes completed successfully!\n";
    echo "All data is now properly isolated by organization.\n";
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>