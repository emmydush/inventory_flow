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

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getCreditSale($conn, $_GET['id']);
        } else {
            getCreditSales($conn);
        }
        break;
    case 'POST':
        makePayment($conn);
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getCreditSales($conn) {
    try {
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        $customerId = isset($_GET['customer_id']) ? $_GET['customer_id'] : '';
        
        $sql = "SELECT cs.*, c.name as customer_name, s.invoice_number 
                FROM credit_sales cs 
                JOIN customers c ON cs.customer_id = c.id 
                JOIN sales s ON cs.sale_id = s.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($status)) {
            $sql .= " AND cs.status = :status";
            $params[':status'] = $status;
        }
        
        if (!empty($customerId)) {
            $sql .= " AND cs.customer_id = :customer_id";
            $params[':customer_id'] = $customerId;
        }
        
        $sql .= " ORDER BY cs.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $credits = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $credits]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getCreditSale($conn, $id) {
    try {
        $stmt = $conn->prepare("SELECT cs.*, c.name as customer_name, s.invoice_number 
                                FROM credit_sales cs 
                                JOIN customers c ON cs.customer_id = c.id 
                                JOIN sales s ON cs.sale_id = s.id 
                                WHERE cs.id = :id");
        $stmt->execute([':id' => $id]);
        $credit = $stmt->fetch();
        
        if ($credit) {
            $paymentsStmt = $conn->prepare("SELECT * FROM credit_payments WHERE credit_sale_id = :id ORDER BY created_at DESC");
            $paymentsStmt->execute([':id' => $id]);
            $credit['payments'] = $paymentsStmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $credit]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Credit sale not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function makePayment($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $conn->beginTransaction();
        
        $creditStmt = $conn->prepare("SELECT * FROM credit_sales WHERE id = :id FOR UPDATE");
        $creditStmt->execute([':id' => $data['credit_sale_id']]);
        $credit = $creditStmt->fetch();
        
        if (!$credit) {
            throw new Exception('Credit sale not found');
        }
        
        if ($data['amount'] > $credit['balance']) {
            throw new Exception('Payment amount exceeds balance');
        }
        
        $paymentStmt = $conn->prepare("INSERT INTO credit_payments (credit_sale_id, amount, payment_method, notes) 
                                       VALUES (:credit_sale_id, :amount, :payment_method, :notes)");
        $paymentStmt->execute([
            ':credit_sale_id' => $data['credit_sale_id'],
            ':amount' => $data['amount'],
            ':payment_method' => $data['payment_method'] ?? 'cash',
            ':notes' => $data['notes'] ?? ''
        ]);
        
        $newAmountPaid = $credit['amount_paid'] + $data['amount'];
        $newBalance = $credit['balance'] - $data['amount'];
        $newStatus = $newBalance <= 0 ? 'paid' : 'partial';
        
        $updateCredit = $conn->prepare("UPDATE credit_sales 
                                        SET amount_paid = :amount_paid, balance = :balance, status = :status, updated_at = CURRENT_TIMESTAMP 
                                        WHERE id = :id");
        $updateCredit->execute([
            ':amount_paid' => $newAmountPaid,
            ':balance' => $newBalance,
            ':status' => $newStatus,
            ':id' => $data['credit_sale_id']
        ]);
        
        $updateCustomer = $conn->prepare("UPDATE customers SET credit_balance = credit_balance - :amount WHERE id = :id");
        $updateCustomer->execute([':amount' => $data['amount'], ':id' => $credit['customer_id']]);
        
        if ($newBalance <= 0) {
            $updateSale = $conn->prepare("UPDATE sales SET payment_status = 'paid' WHERE id = :id");
            $updateSale->execute([':id' => $credit['sale_id']]);
        }
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'new_balance' => $newBalance, 'message' => 'Payment recorded successfully']);
    } catch(Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
