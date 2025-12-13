<?php
try {
    $host = 'localhost';
    $db_name = 'inventory_flow';
    $username = 'root';
    $password = '';
    $port = '3306';
    
    $dsn = "mysql:host=$host;port=$port";
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL successfully!\n";
    
    // Check if database exists
    $stmt = $conn->query("SHOW DATABASES LIKE '$db_name'");
    $result = $stmt->fetchAll();
    
    if (count($result) > 0) {
        echo "Database '$db_name' exists.\n";
        
        // Select the database
        $conn->exec("USE $db_name");
        
        // Show tables
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Tables in database:\n";
        foreach ($tables as $table) {
            echo "- $table\n";
        }
    } else {
        echo "Database '$db_name' does not exist.\n";
    }
    
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>