<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    $totalProducts = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
    
    $lowStockCount = $conn->query("SELECT COUNT(*) FROM products WHERE quantity <= min_stock")->fetchColumn();
    
    $outOfStockCount = $conn->query("SELECT COUNT(*) FROM products WHERE quantity = 0")->fetchColumn();
    
    $totalValue = $conn->query("SELECT COALESCE(SUM(quantity * price), 0) FROM products")->fetchColumn();
    
    $totalCategories = $conn->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    
    $lowStockItems = $conn->query("SELECT p.*, c.name as category_name 
                                   FROM products p 
                                   LEFT JOIN categories c ON p.category_id = c.id 
                                   WHERE p.quantity <= p.min_stock 
                                   ORDER BY p.quantity ASC 
                                   LIMIT 10")->fetchAll();
    
    $recentTransactions = $conn->query("SELECT st.*, p.name as product_name, p.sku 
                                        FROM stock_transactions st 
                                        JOIN products p ON st.product_id = p.id 
                                        ORDER BY st.created_at DESC 
                                        LIMIT 5")->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_products' => (int)$totalProducts,
            'low_stock_count' => (int)$lowStockCount,
            'out_of_stock_count' => (int)$outOfStockCount,
            'total_value' => round((float)$totalValue, 2),
            'total_categories' => (int)$totalCategories,
            'low_stock_items' => $lowStockItems,
            'recent_transactions' => $recentTransactions
        ]
    ]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
