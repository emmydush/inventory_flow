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
            getUser($conn, $_GET['id']);
        } else {
            getUsers($conn);
        }
        break;
    case 'POST':
        createUser($conn);
        break;
    case 'PUT':
        updateUser($conn);
        break;
    case 'DELETE':
        deleteUser($conn);
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getUsers($conn) {
    try {
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        
        $sql = "SELECT id, username, email, full_name, role, status, created_at FROM users WHERE 1=1";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (username LIKE :search OR email LIKE :search OR full_name LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $users]);
    } catch(PDOException $e) {
        error_log('Get users error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getUser($conn, $id) {
    try {
        $stmt = $conn->prepare("SELECT id, username, email, full_name, role, status FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo json_encode(['success' => true, 'data' => $user]);
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found']);
        }
    } catch(PDOException $e) {
        error_log('Get user error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createUser($conn) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Invalid request data']);
            return;
        }
        
        if (!isset($data['username']) || empty($data['username'])) {
            echo json_encode(['success' => false, 'error' => 'Username is required']);
            return;
        }
        
        if (!isset($data['email']) || empty($data['email'])) {
            echo json_encode(['success' => false, 'error' => 'Email is required']);
            return;
        }
        
        if (!isset($data['password']) || empty($data['password'])) {
            echo json_encode(['success' => false, 'error' => 'Password is required']);
            return;
        }
        
        if (!isset($data['full_name']) || empty($data['full_name'])) {
            echo json_encode(['success' => false, 'error' => 'Full name is required']);
            return;
        }
        
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, role, status) 
                                VALUES (:username, :email, :password_hash, :full_name, :role, :status)");
        $stmt->execute([
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':password_hash' => $passwordHash,
            ':full_name' => $data['full_name'],
            ':role' => $data['role'] ?? 'cashier',
            ':status' => $data['status'] ?? 'active'
        ]);
        
        $userId = $conn->lastInsertId();
        
        echo json_encode(['success' => true, 'id' => $userId, 'message' => 'User created successfully']);
    } catch(PDOException $e) {
        error_log('Create user error: ' . $e->getMessage());
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo json_encode(['success' => false, 'error' => 'Username or email already exists']);
        } else {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}

function updateUser($conn) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Invalid request data']);
            return;
        }
        
        if (!isset($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'User ID is required']);
            return;
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users 
                                    SET full_name = :full_name, email = :email, role = :role, status = :status, password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP
                                    WHERE id = :id");
            $stmt->execute([
                ':id' => $data['id'],
                ':full_name' => $data['full_name'] ?? '',
                ':email' => $data['email'] ?? '',
                ':role' => $data['role'] ?? 'cashier',
                ':status' => $data['status'] ?? 'active',
                ':password_hash' => $passwordHash
            ]);
        } else {
            $stmt = $conn->prepare("UPDATE users 
                                    SET full_name = :full_name, email = :email, role = :role, status = :status, updated_at = CURRENT_TIMESTAMP
                                    WHERE id = :id");
            $stmt->execute([
                ':id' => $data['id'],
                ':full_name' => $data['full_name'] ?? '',
                ':email' => $data['email'] ?? '',
                ':role' => $data['role'] ?? 'cashier',
                ':status' => $data['status'] ?? 'active'
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } catch(PDOException $e) {
        error_log('Update user error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteUser($conn) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data || !isset($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'User ID is required']);
            return;
        }
        
        $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $data['id']]);
        
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } catch(PDOException $e) {
        error_log('Delete user error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
