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
    
    // Add created_by column to suppliers table if it doesn't exist
    try {
        $conn->exec("ALTER TABLE suppliers ADD COLUMN created_by INT NULL");
        $conn->exec("ALTER TABLE suppliers ADD CONSTRAINT fk_suppliers_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
    } catch (PDOException $e) {
        // Column might already exist, check if it's a duplicate column error
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            throw $e; // Re-throw if it's not a duplicate column error
        }
    }
    
    // Add created_by column to products table if it doesn't exist
    try {
        $conn->exec("ALTER TABLE products ADD COLUMN created_by INT NULL");
        $conn->exec("ALTER TABLE products ADD CONSTRAINT fk_products_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
    } catch (PDOException $e) {
        // Column might already exist, check if it's a duplicate column error
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            throw $e; // Re-throw if it's not a duplicate column error
        }
    }
    
    // Add created_by column to customers table if it doesn't exist
    try {
        $conn->exec("ALTER TABLE customers ADD COLUMN created_by INT NULL");
        $conn->exec("ALTER TABLE customers ADD CONSTRAINT fk_customers_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
    } catch (PDOException $e) {
        // Column might already exist, check if it's a duplicate column error
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            throw $e; // Re-throw if it's not a duplicate column error
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Database schema updated successfully']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>