<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getSale($conn, $_GET['id']);
        } else {
            getSales($conn);
        }
        break;
    case 'POST':
        createSale($conn);
        break;
    case 'DELETE':
        deleteSale($conn);
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getSales($conn) {
    try {
        $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
        $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
        $paymentStatus = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';
        
        $sql = "SELECT s.*, c.name as customer_name 
                FROM sales s 
                LEFT JOIN customers c ON s.customer_id = c.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($dateFrom)) {
            $sql .= " AND DATE(s.created_at) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $sql .= " AND DATE(s.created_at) <= :date_to";
            $params[':date_to'] = $dateTo;
        }
        
        if (!empty($paymentStatus)) {
            $sql .= " AND s.payment_status = :payment_status";
            $params[':payment_status'] = $paymentStatus;
        }
        
        $sql .= " ORDER BY s.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $sales = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $sales]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getSale($conn, $id) {
    try {
        $stmt = $conn->prepare("SELECT s.*, c.name as customer_name 
                                FROM sales s 
                                LEFT JOIN customers c ON s.customer_id = c.id 
                                WHERE s.id = :id");
        $stmt->execute([':id' => $id]);
        $sale = $stmt->fetch();
        
        if ($sale) {
            $itemsStmt = $conn->prepare("SELECT * FROM sale_items WHERE sale_id = :sale_id");
            $itemsStmt->execute([':sale_id' => $id]);
            $sale['items'] = $itemsStmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $sale]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Sale not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createSale($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $conn->beginTransaction();
        
        $prefixStmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'invoice_prefix'");
        $prefix = $prefixStmt->fetchColumn() ?: 'INV-';
        
        $lastInvoice = $conn->query("SELECT invoice_number FROM sales ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($lastInvoice) {
            $lastNum = intval(str_replace($prefix, '', $lastInvoice));
            $invoiceNumber = $prefix . str_pad($lastNum + 1, 6, '0', STR_PAD_LEFT);
        } else {
            $invoiceNumber = $prefix . '000001';
        }
        
        $paymentStatus = $data['payment_method'] === 'credit' ? 'pending' : 'paid';
        
        $stmt = $conn->prepare("INSERT INTO sales (invoice_number, customer_id, subtotal, tax, discount, total, payment_method, payment_status, notes) 
                                VALUES (:invoice_number, :customer_id, :subtotal, :tax, :discount, :total, :payment_method, :payment_status, :notes)");
        $stmt->execute([
            ':invoice_number' => $invoiceNumber,
            ':customer_id' => $data['customer_id'] ?: null,
            ':subtotal' => $data['subtotal'],
            ':tax' => $data['tax'] ?? 0,
            ':discount' => $data['discount'] ?? 0,
            ':total' => $data['total'],
            ':payment_method' => $data['payment_method'] ?? 'cash',
            ':payment_status' => $paymentStatus,
            ':notes' => $data['notes'] ?? ''
        ]);
        
        $saleId = $conn->lastInsertId();
        
        foreach ($data['items'] as $item) {
            $itemStmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, unit_price, total) 
                                        VALUES (:sale_id, :product_id, :product_name, :quantity, :unit_price, :total)");
            $itemStmt->execute([
                ':sale_id' => $saleId,
                ':product_id' => $item['product_id'],
                ':product_name' => $item['product_name'],
                ':quantity' => $item['quantity'],
                ':unit_price' => $item['unit_price'],
                ':total' => $item['total']
            ]);
            
            $updateStock = $conn->prepare("UPDATE products SET quantity = quantity - :qty, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $updateStock->execute([':qty' => $item['quantity'], ':id' => $item['product_id']]);
            
            $transStmt = $conn->prepare("INSERT INTO stock_transactions (product_id, type, quantity, notes) 
                                         VALUES (:product_id, 'remove', :quantity, :notes)");
            $transStmt->execute([
                ':product_id' => $item['product_id'],
                ':quantity' => $item['quantity'],
                ':notes' => 'Sale: ' . $invoiceNumber
            ]);
        }
        
        if ($data['payment_method'] === 'credit' && $data['customer_id']) {
            $creditStmt = $conn->prepare("INSERT INTO credit_sales (sale_id, customer_id, amount, balance, due_date, status) 
                                          VALUES (:sale_id, :customer_id, :amount, :balance, :due_date, 'pending')");
            $dueDate = date('Y-m-d', strtotime('+30 days'));
            $creditStmt->execute([
                ':sale_id' => $saleId,
                ':customer_id' => $data['customer_id'],
                ':amount' => $data['total'],
                ':balance' => $data['total'],
                ':due_date' => $dueDate
            ]);
            
            $updateCustomer = $conn->prepare("UPDATE customers SET credit_balance = credit_balance + :amount WHERE id = :id");
            $updateCustomer->execute([':amount' => $data['total'], ':id' => $data['customer_id']]);
        }
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'id' => $saleId, 'invoice_number' => $invoiceNumber, 'message' => 'Sale completed successfully']);
    } catch(Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteSale($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $conn->prepare("DELETE FROM sales WHERE id = :id");
        $stmt->execute([':id' => $data['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Sale deleted successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
