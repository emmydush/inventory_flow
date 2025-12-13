<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

if ($conn) {
    try {
        // Check if department_id column exists
        $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'department_id'");
        $columnExists = $stmt->fetch();
        
        if (!$columnExists) {
            // Add department_id column
            $conn->exec("ALTER TABLE users ADD COLUMN department_id INT NULL");
            echo "Added department_id column to users table\n";
            
            // Add foreign key constraint
            $conn->exec("ALTER TABLE users ADD CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL");
            echo "Added foreign key constraint for department_id\n";
        } else {
            echo "department_id column already exists in users table\n";
        }
        
        // Check if last_login column exists
        $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
        $columnExists = $stmt->fetch();
        
        if (!$columnExists) {
            // Add last_login column
            $conn->exec("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL");
            echo "Added last_login column to users table\n";
        } else {
            echo "last_login column already exists in users table\n";
        }
        
    } catch (Exception $e) {
        echo "Error updating schema: " . $e->getMessage() . "\n";
    }
} else {
    echo "Could not connect to database\n";
}
?>