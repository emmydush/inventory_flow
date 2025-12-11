<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
            getCustomer($conn, $_GET['id']);
        } else {
            getCustomers($conn);
        }
        break;
    case 'POST':
        createCustomer($conn);
        break;
    case 'PUT':
        updateCustomer($conn);
        break;
    case 'DELETE':
        deleteCustomer($conn);
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getCustomers($conn) {
    try {
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        
        $sql = "SELECT * FROM customers WHERE 1=1";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (name LIKE :search OR email LIKE :search OR phone LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        $sql .= " ORDER BY name ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $customers]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getCustomer($conn, $id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM customers WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $customer = $stmt->fetch();
        
        if ($customer) {
            echo json_encode(['success' => true, 'data' => $customer]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Customer not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createCustomer($conn) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Invalid request data']);
            return;
        }

        if (!isset($data['name']) || empty($data['name'])) {
            echo json_encode(['success' => false, 'error' => 'Customer name is required']);
            return;
        }

        $stmt = $conn->prepare("INSERT INTO customers (name, email, phone, address, credit_limit) 
                                VALUES (:name, :email, :phone, :address, :credit_limit)");
        $stmt->execute([
            ':name' => $data['name'],
            ':email' => $data['email'] ?? '',
            ':phone' => $data['phone'] ?? '',
            ':address' => $data['address'] ?? '',
            ':credit_limit' => $data['credit_limit'] ?? 0
        ]);
        
        $result['id'] = $conn->lastInsertId();
        
        echo json_encode(['success' => true, 'id' => $result['id'], 'message' => 'Customer created successfully']);
    } catch(PDOException $e) {
        error_log('Create customer error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateCustomer($conn) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Invalid request data']);
            return;
        }

        if (!isset($data['id']) || !isset($data['name']) || empty($data['name'])) {
            echo json_encode(['success' => false, 'error' => 'Customer ID and name are required']);
            return;
        }

        $stmt = $conn->prepare("UPDATE customers 
                                SET name = :name, email = :email, phone = :phone, 
                                    address = :address, credit_limit = :credit_limit, updated_at = CURRENT_TIMESTAMP
                                WHERE id = :id");
        $stmt->execute([
            ':id' => $data['id'],
            ':name' => $data['name'],
            ':email' => $data['email'] ?? '',
            ':phone' => $data['phone'] ?? '',
            ':address' => $data['address'] ?? '',
            ':credit_limit' => $data['credit_limit'] ?? 0
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
    } catch(PDOException $e) {
        error_log('Update customer error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteCustomer($conn) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'Customer ID is required']);
            return;
        }

        $stmt = $conn->prepare("DELETE FROM customers WHERE id = :id");
        $stmt->execute([':id' => $data['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
    } catch(PDOException $e) {
        error_log('Delete customer error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
