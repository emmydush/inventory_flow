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

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getSupplier($conn, $_GET['id']);
        } else {
            getSuppliers($conn);
        }
        break;
    case 'POST':
        createSupplier($conn);
        break;
    case 'PUT':
        updateSupplier($conn);
        break;
    case 'DELETE':
        deleteSupplier($conn);
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getSuppliers($conn) {
    try {
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        
        $sql = "SELECT * FROM suppliers WHERE 1=1";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (name ILIKE :search OR contact_person ILIKE :search OR email ILIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        $sql .= " ORDER BY name ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $suppliers = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $suppliers]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getSupplier($conn, $id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $supplier = $stmt->fetch();
        
        if ($supplier) {
            echo json_encode(['success' => true, 'data' => $supplier]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Supplier not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createSupplier($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_person, email, phone, address, notes) 
                                VALUES (:name, :contact_person, :email, :phone, :address, :notes)
                                RETURNING id");
        $stmt->execute([
            ':name' => $data['name'],
            ':contact_person' => $data['contact_person'] ?? '',
            ':email' => $data['email'] ?? '',
            ':phone' => $data['phone'] ?? '',
            ':address' => $data['address'] ?? '',
            ':notes' => $data['notes'] ?? ''
        ]);
        
        $result = $stmt->fetch();
        
        echo json_encode(['success' => true, 'id' => $result['id'], 'message' => 'Supplier created successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateSupplier($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $conn->prepare("UPDATE suppliers 
                                SET name = :name, contact_person = :contact_person, email = :email, 
                                    phone = :phone, address = :address, notes = :notes, updated_at = CURRENT_TIMESTAMP
                                WHERE id = :id");
        $stmt->execute([
            ':id' => $data['id'],
            ':name' => $data['name'],
            ':contact_person' => $data['contact_person'] ?? '',
            ':email' => $data['email'] ?? '',
            ':phone' => $data['phone'] ?? '',
            ':address' => $data['address'] ?? '',
            ':notes' => $data['notes'] ?? ''
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Supplier updated successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteSupplier($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = :id");
        $stmt->execute([':id' => $data['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
