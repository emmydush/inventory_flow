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
        getCategories($conn);
        break;
    case 'POST':
        createCategory($conn);
        break;
    case 'DELETE':
        deleteCategory($conn);
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getCategories($conn) {
    try {
        $stmt = $conn->query("SELECT c.*, COUNT(p.id) as product_count 
                              FROM categories c 
                              LEFT JOIN products p ON c.id = p.category_id 
                              GROUP BY c.id 
                              ORDER BY c.name");
        $categories = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $categories]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createCategory($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (:name, :description) RETURNING id");
        $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'] ?? ''
        ]);
        
        $result = $stmt->fetch();
        
        echo json_encode(['success' => true, 'id' => $result['id'], 'message' => 'Category created successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteCategory($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute([':id' => $data['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
