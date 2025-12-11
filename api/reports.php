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

$reportType = isset($_GET['type']) ? $_GET['type'] : 'summary';

switch ($reportType) {
    case 'summary':
        getSummaryReport($conn);
        break;
    case 'sales':
        getSalesReport($conn);
        break;
    case 'inventory':
        getInventoryReport($conn);
        break;
    case 'credit':
        getCreditReport($conn);
        break;
    case 'top_products':
        getTopProducts($conn);
        break;
    default:
        echo json_encode(['error' => 'Invalid report type']);
}

function getSummaryReport($conn) {
    try {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m');
        
        $todaySales = $conn->query("SELECT COALESCE(SUM(total), 0) as total, COUNT(*) as count FROM sales WHERE DATE(created_at) = '$today'")->fetch();
        $monthSales = $conn->query("SELECT COALESCE(SUM(total), 0) as total, COUNT(*) as count FROM sales WHERE TO_CHAR(created_at, 'YYYY-MM') = '$thisMonth'")->fetch();
        
        $totalProducts = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $lowStockCount = $conn->query("SELECT COUNT(*) FROM products WHERE quantity <= min_stock")->fetchColumn();
        $outOfStockCount = $conn->query("SELECT COUNT(*) FROM products WHERE quantity = 0")->fetchColumn();
        $inventoryValue = $conn->query("SELECT COALESCE(SUM(quantity * price), 0) FROM products")->fetchColumn();
        
        $totalCustomers = $conn->query("SELECT COUNT(*) FROM customers")->fetchColumn();
        $totalSuppliers = $conn->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
        
        $pendingCredits = $conn->query("SELECT COALESCE(SUM(balance), 0) as total, COUNT(*) as count FROM credit_sales WHERE status != 'paid'")->fetch();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'today_sales' => ['total' => round((float)$todaySales['total'], 2), 'count' => (int)$todaySales['count']],
                'month_sales' => ['total' => round((float)$monthSales['total'], 2), 'count' => (int)$monthSales['count']],
                'total_products' => (int)$totalProducts,
                'low_stock_count' => (int)$lowStockCount,
                'out_of_stock_count' => (int)$outOfStockCount,
                'inventory_value' => round((float)$inventoryValue, 2),
                'total_customers' => (int)$totalCustomers,
                'total_suppliers' => (int)$totalSuppliers,
                'pending_credits' => ['total' => round((float)$pendingCredits['total'], 2), 'count' => (int)$pendingCredits['count']]
            ]
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getSalesReport($conn) {
    try {
        $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
        $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
        
        $stmt = $conn->prepare("SELECT DATE(created_at) as date, 
                                       COUNT(*) as transactions, 
                                       SUM(total) as total_sales,
                                       SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as paid_sales,
                                       SUM(CASE WHEN payment_status = 'pending' THEN total ELSE 0 END) as credit_sales
                                FROM sales 
                                WHERE DATE(created_at) BETWEEN :date_from AND :date_to 
                                GROUP BY DATE(created_at) 
                                ORDER BY date DESC");
        $stmt->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
        $dailySales = $stmt->fetchAll();
        
        $totals = $conn->prepare("SELECT COUNT(*) as transactions, 
                                         COALESCE(SUM(total), 0) as total_sales,
                                         COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) as paid_sales,
                                         COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN total ELSE 0 END), 0) as credit_sales
                                  FROM sales 
                                  WHERE DATE(created_at) BETWEEN :date_from AND :date_to");
        $totals->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
        $summary = $totals->fetch();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'daily' => $dailySales,
                'summary' => [
                    'transactions' => (int)$summary['transactions'],
                    'total_sales' => round((float)$summary['total_sales'], 2),
                    'paid_sales' => round((float)$summary['paid_sales'], 2),
                    'credit_sales' => round((float)$summary['credit_sales'], 2)
                ]
            ]
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getInventoryReport($conn) {
    try {
        $stmt = $conn->query("SELECT p.*, c.name as category_name,
                                     (p.quantity * p.price) as stock_value,
                                     (p.quantity * p.cost_price) as cost_value
                              FROM products p 
                              LEFT JOIN categories c ON p.category_id = c.id 
                              ORDER BY p.quantity ASC");
        $products = $stmt->fetchAll();
        
        $categoryStats = $conn->query("SELECT c.name, 
                                              COUNT(p.id) as product_count, 
                                              COALESCE(SUM(p.quantity), 0) as total_quantity,
                                              COALESCE(SUM(p.quantity * p.price), 0) as total_value
                                       FROM categories c 
                                       LEFT JOIN products p ON c.id = p.category_id 
                                       GROUP BY c.id, c.name 
                                       ORDER BY total_value DESC")->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'products' => $products,
                'by_category' => $categoryStats
            ]
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getCreditReport($conn) {
    try {
        $stmt = $conn->query("SELECT c.name as customer_name, 
                                     c.credit_limit,
                                     c.credit_balance,
                                     COUNT(cs.id) as credit_count,
                                     COALESCE(SUM(CASE WHEN cs.status != 'paid' THEN cs.balance ELSE 0 END), 0) as pending_amount
                              FROM customers c 
                              LEFT JOIN credit_sales cs ON c.id = cs.customer_id 
                              GROUP BY c.id, c.name, c.credit_limit, c.credit_balance 
                              HAVING c.credit_balance > 0 OR COUNT(cs.id) > 0
                              ORDER BY pending_amount DESC");
        $customerCredits = $stmt->fetchAll();
        
        $overdue = $conn->query("SELECT cs.*, c.name as customer_name, s.invoice_number 
                                 FROM credit_sales cs 
                                 JOIN customers c ON cs.customer_id = c.id 
                                 JOIN sales s ON cs.sale_id = s.id 
                                 WHERE cs.status != 'paid' AND cs.due_date < CURRENT_DATE 
                                 ORDER BY cs.due_date ASC")->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'customer_credits' => $customerCredits,
                'overdue' => $overdue
            ]
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getTopProducts($conn) {
    try {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        
        $stmt = $conn->prepare("SELECT p.name, p.sku, 
                                       COALESCE(SUM(si.quantity), 0) as total_sold,
                                       COALESCE(SUM(si.total), 0) as total_revenue
                                FROM products p 
                                LEFT JOIN sale_items si ON p.id = si.product_id 
                                GROUP BY p.id, p.name, p.sku 
                                ORDER BY total_sold DESC 
                                LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $topProducts = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $topProducts]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
