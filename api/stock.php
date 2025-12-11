<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getTransactions($conn);
        break;
    case 'POST':
        adjustStock($conn);
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getTransactions($conn) {
    try {
        $product_id = isset($_GET['product_id']) ? $_GET['product_id'] : null;
        
        $sql = "SELECT st.*, p.name as product_name, p.sku 
                FROM stock_transactions st 
                JOIN products p ON st.product_id = p.id";
        $params = [];
        
        if ($product_id) {
            $sql .= " WHERE st.product_id = :product_id";
            $params[':product_id'] = $product_id;
        }
        
        $sql .= " ORDER BY st.created_at DESC LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $transactions]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function adjustStock($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("SELECT quantity FROM products WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $data['product_id']]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception('Product not found');
        }
        
        $adjustment = $data['type'] === 'add' ? $data['quantity'] : -$data['quantity'];
        $newQuantity = $product['quantity'] + $adjustment;
        
        if ($newQuantity < 0) {
            throw new Exception('Insufficient stock');
        }
        
        $updateStmt = $conn->prepare("UPDATE products SET quantity = :quantity, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $updateStmt->execute([':quantity' => $newQuantity, ':id' => $data['product_id']]);
        
        $transStmt = $conn->prepare("INSERT INTO stock_transactions (product_id, type, quantity, notes) 
                                     VALUES (:product_id, :type, :quantity, :notes)");
        $transStmt->execute([
            ':product_id' => $data['product_id'],
            ':type' => $data['type'],
            ':quantity' => $data['quantity'],
            ':notes' => $data['notes'] ?? ''
        ]);
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'new_quantity' => $newQuantity, 'message' => 'Stock adjusted successfully']);
    } catch(Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
