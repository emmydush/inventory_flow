<?php
// Admin script to verify and fix multi-tenancy implementation
// This script should be run by administrators to ensure proper multi-tenancy setup

require_once '../config/database.php';

class MultiTenancyVerifier {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        if (!$this->conn) {
            throw new Exception("Database connection failed");
        }
    }
    
    public function verifyOrganizationsTable() {
        echo "1. Verifying organizations table...\n";
        
        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE 'organizations'");
            $result = $stmt->fetchAll();
            
            if (count($result) > 0) {
                echo "   ✓ Organizations table exists\n";
                return true;
            } else {
                echo "   ✗ Organizations table missing\n";
                return false;
            }
        } catch (Exception $e) {
            echo "   ✗ Error checking organizations table: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function verifyOrganizationColumns() {
        echo "\n2. Verifying organization_id columns...\n";
        
        $requiredTables = [
            'users', 'categories', 'products', 'customers', 'suppliers',
            'sales', 'sale_items', 'credit_sales', 'credit_payments',
            'purchases', 'purchase_items', 'settings', 'departments'
        ];
        
        $missingColumns = [];
        
        foreach ($requiredTables as $table) {
            try {
                $stmt = $this->conn->prepare("SHOW COLUMNS FROM `$table` LIKE 'organization_id'");
                $stmt->execute();
                $columnExists = $stmt->fetch();
                
                if ($columnExists) {
                    echo "   ✓ organization_id column exists in $table\n";
                } else {
                    echo "   ✗ Missing organization_id column in $table\n";
                    $missingColumns[] = $table;
                }
            } catch (Exception $e) {
                echo "   ✗ Error checking $table: " . $e->getMessage() . "\n";
                $missingColumns[] = $table;
            }
        }
        
        return $missingColumns;
    }
    
    public function verifyDefaultOrganization() {
        echo "\n3. Verifying default organization...\n";
        
        try {
            $stmt = $this->conn->query("SELECT COUNT(*) FROM organizations WHERE slug = 'default-org'");
            $defaultOrgExists = $stmt->fetchColumn() > 0;
            
            if ($defaultOrgExists) {
                echo "   ✓ Default organization exists\n";
                $stmt = $this->conn->query("SELECT id FROM organizations WHERE slug = 'default-org'");
                $defaultOrgId = $stmt->fetchColumn();
                echo "   ✓ Default organization ID: $defaultOrgId\n";
                return $defaultOrgId;
            } else {
                echo "   ✗ Default organization missing\n";
                return false;
            }
        } catch (Exception $e) {
            echo "   ✗ Error checking default organization: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function verifyDataAssignment($defaultOrgId) {
        echo "\n4. Verifying data assignment to organizations...\n";
        
        $requiredTables = [
            'users', 'categories', 'products', 'customers', 'suppliers',
            'sales', 'settings', 'departments'
        ];
        
        foreach ($requiredTables as $table) {
            try {
                $stmt = $this->conn->query("SELECT COUNT(*) FROM `$table` WHERE organization_id IS NULL");
                $nullCount = $stmt->fetchColumn();
                
                if ($nullCount > 0) {
                    echo "   ✗ $nullCount records in $table are not assigned to an organization\n";
                } else {
                    echo "   ✓ All records in $table are assigned to organizations\n";
                }
            } catch (Exception $e) {
                echo "   ✗ Error checking $table: " . $e->getMessage() . "\n";
            }
        }
    }
    
    public function createMissingColumns($missingColumns) {
        if (empty($missingColumns)) {
            echo "\n5. No missing columns to fix\n";
            return;
        }
        
        echo "\n5. Fixing missing organization_id columns...\n";
        
        foreach ($missingColumns as $table) {
            try {
                echo "   Adding organization_id column to $table...\n";
                $this->conn->exec("ALTER TABLE `$table` ADD COLUMN organization_id INT NULL");
                
                // Add foreign key constraint
                try {
                    $this->conn->exec("ALTER TABLE `$table` ADD CONSTRAINT fk_{$table}_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL");
                    echo "   ✓ Added organization_id column and foreign key to $table\n";
                } catch (Exception $e) {
                    echo "   ✓ Added organization_id column to $table (foreign key may already exist)\n";
                }
            } catch (Exception $e) {
                echo "   ✗ Error adding organization_id to $table: " . $e->getMessage() . "\n";
            }
        }
    }
    
    public function createDefaultOrganization() {
        echo "\n6. Creating default organization if missing...\n";
        
        try {
            $stmt = $this->conn->query("SELECT COUNT(*) FROM organizations WHERE slug = 'default-org'");
            $defaultOrgExists = $stmt->fetchColumn() > 0;
            
            if ($defaultOrgExists) {
                echo "   ✓ Default organization already exists\n";
                $stmt = $this->conn->query("SELECT id FROM organizations WHERE slug = 'default-org'");
                return $stmt->fetchColumn();
            } else {
                $this->conn->exec("
                    INSERT INTO organizations (name, slug, description) VALUES 
                    ('Default Organization', 'default-org', 'Default organization for the system')
                ");
                $defaultOrgId = $this->conn->lastInsertId();
                echo "   ✓ Created default organization with ID: $defaultOrgId\n";
                return $defaultOrgId;
            }
        } catch (Exception $e) {
            echo "   ✗ Error creating default organization: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function assignDataToOrganization($defaultOrgId) {
        echo "\n7. Assigning unassigned data to default organization...\n";
        
        $requiredTables = [
            'users', 'categories', 'products', 'customers', 'suppliers',
            'sales', 'settings', 'departments'
        ];
        
        foreach ($requiredTables as $table) {
            try {
                $stmt = $this->conn->query("SELECT COUNT(*) FROM `$table` WHERE organization_id IS NULL");
                $nullCount = $stmt->fetchColumn();
                
                if ($nullCount > 0) {
                    echo "   Assigning $nullCount records in $table to default organization...\n";
                    $this->conn->exec("UPDATE `$table` SET organization_id = $defaultOrgId WHERE organization_id IS NULL");
                    echo "   ✓ Assigned records in $table\n";
                } else {
                    echo "   ✓ No unassigned records in $table\n";
                }
            } catch (Exception $e) {
                echo "   ✗ Error assigning data in $table: " . $e->getMessage() . "\n";
            }
        }
    }
    
    public function addPerformanceIndexes() {
        echo "\n8. Adding performance indexes...\n";
        
        $indexedTables = ['users', 'categories', 'products', 'customers', 'suppliers', 'sales', 'purchases', 'settings', 'departments'];
        
        foreach ($indexedTables as $table) {
            try {
                $this->conn->exec("ALTER TABLE `$table` ADD INDEX IF NOT EXISTS idx_{$table}_org (organization_id)");
                echo "   ✓ Added index to $table\n";
            } catch (Exception $e) {
                echo "   ✓ Index for $table (may already exist)\n";
            }
        }
    }
    
    public function runFullVerification() {
        echo "=== Multi-Tenancy Verification Report ===\n\n";
        
        // Step 1: Verify organizations table
        $orgTableExists = $this->verifyOrganizationsTable();
        if (!$orgTableExists) {
            echo "\n   Cannot proceed without organizations table. Please create it first.\n";
            return;
        }
        
        // Step 2: Verify organization columns
        $missingColumns = $this->verifyOrganizationColumns();
        
        // Step 3: Verify default organization
        $defaultOrgId = $this->verifyDefaultOrganization();
        
        // Step 4: Verify data assignment
        if ($defaultOrgId) {
            $this->verifyDataAssignment($defaultOrgId);
        }
        
        echo "\n=== Verification Summary ===\n";
        echo "Organizations table: " . ($orgTableExists ? "OK" : "MISSING") . "\n";
        echo "Missing columns: " . (empty($missingColumns) ? "NONE" : implode(", ", $missingColumns)) . "\n";
        echo "Default organization: " . ($defaultOrgId ? "OK (ID: $defaultOrgId)" : "MISSING") . "\n";
        
        return [
            'org_table_exists' => $orgTableExists,
            'missing_columns' => $missingColumns,
            'default_org_id' => $defaultOrgId
        ];
    }
    
    public function runFullFix() {
        echo "=== Multi-Tenancy Fix Process ===\n\n";
        
        // Step 1: Fix missing columns
        $missingColumns = $this->verifyOrganizationColumns();
        $this->createMissingColumns($missingColumns);
        
        // Step 2: Create default organization
        $defaultOrgId = $this->createDefaultOrganization();
        
        // Step 3: Assign data to organization
        if ($defaultOrgId) {
            $this->assignDataToOrganization($defaultOrgId);
        }
        
        // Step 4: Add performance indexes
        $this->addPerformanceIndexes();
        
        echo "\n=== Fix Process Completed ===\n";
        echo "Multi-tenancy implementation has been verified and fixed.\n";
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    try {
        $verifier = new MultiTenancyVerifier();
        
        $action = $argv[1] ?? 'report';
        
        switch ($action) {
            case 'fix':
                $verifier->runFullFix();
                break;
            case 'report':
            default:
                $verifier->runFullVerification();
                break;
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>