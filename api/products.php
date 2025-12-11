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
            getProduct($conn, $_GET['id']);
        } else {
            getProducts($conn);
        }
        break;
    case 'POST':
        createProduct($conn);
        break;
    case 'PUT':
        updateProduct($conn);
        break;
    case 'DELETE':
        deleteProduct($conn);
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getProducts($conn) {
    try {
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $category = isset($_GET['category']) ? $_GET['category'] : '';
        
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (p.name ILIKE :search OR p.sku ILIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        if (!empty($category)) {
            $sql .= " AND p.category_id = :category";
            $params[':category'] = $category;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $products]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getProduct($conn, $id) {
    try {
        $stmt = $conn->prepare("SELECT p.*, c.name as category_name 
                                FROM products p 
                                LEFT JOIN categories c ON p.category_id = c.id 
                                WHERE p.id = :id");
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch();
        
        if ($product) {
            echo json_encode(['success' => true, 'data' => $product]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createProduct($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $conn->prepare("INSERT INTO products (sku, name, description, category_id, quantity, price, min_stock) 
                                VALUES (:sku, :name, :description, :category_id, :quantity, :price, :min_stock)
                                RETURNING id");
        $stmt->execute([
            ':sku' => $data['sku'],
            ':name' => $data['name'],
            ':description' => $data['description'] ?? '',
            ':category_id' => $data['category_id'] ?: null,
            ':quantity' => $data['quantity'] ?? 0,
            ':price' => $data['price'] ?? 0,
            ':min_stock' => $data['min_stock'] ?? 10
        ]);
        
        $result = $stmt->fetch();
        
        if ($data['quantity'] > 0) {
            $transStmt = $conn->prepare("INSERT INTO stock_transactions (product_id, type, quantity, notes) 
                                         VALUES (:product_id, 'initial', :quantity, 'Initial stock')");
            $transStmt->execute([':product_id' => $result['id'], ':quantity' => $data['quantity']]);
        }
        
        echo json_encode(['success' => true, 'id' => $result['id'], 'message' => 'Product created successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateProduct($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $conn->prepare("UPDATE products 
                                SET sku = :sku, name = :name, description = :description, 
                                    category_id = :category_id, quantity = :quantity, 
                                    price = :price, min_stock = :min_stock, updated_at = CURRENT_TIMESTAMP
                                WHERE id = :id");
        $stmt->execute([
            ':id' => $data['id'],
            ':sku' => $data['sku'],
            ':name' => $data['name'],
            ':description' => $data['description'] ?? '',
            ':category_id' => $data['category_id'] ?: null,
            ':quantity' => $data['quantity'] ?? 0,
            ':price' => $data['price'] ?? 0,
            ':min_stock' => $data['min_stock'] ?? 10
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteProduct($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $conn->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute([':id' => $data['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
