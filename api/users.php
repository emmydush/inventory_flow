<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// For GET requests, anyone can view users (with restrictions)
// For POST/PUT/DELETE, only admins can manage users
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = :user_id AND status = 'active'");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
}

switch ($_SERVER['REQUEST_METHOD']) {
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
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function getUsers($conn) {
    try {
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        
        $sql = "SELECT u.id, u.username, u.email, u.full_name, u.role, u.status, u.created_at, u.department_id, d.name as department_name 
                FROM users u 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (u.username LIKE :search OR u.email LIKE :search OR u.full_name LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        $sql .= " ORDER BY u.created_at DESC";
        
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
        $stmt = $conn->prepare("SELECT u.id, u.username, u.email, u.full_name, u.role, u.status, u.department_id, d.name as department_name 
                               FROM users u 
                               LEFT JOIN departments d ON u.department_id = d.id 
                               WHERE u.id = :id");
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
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, role, department_id, status) 
                                VALUES (:username, :email, :password_hash, :full_name, :role, :department_id, :status)");
        $stmt->execute([
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':password_hash' => $passwordHash,
            ':full_name' => $data['full_name'],
            ':role' => $data['role'] ?? 'cashier',
            ':department_id' => $data['department_id'] ?? null,
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
        
        if (!$data || !isset($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'User ID is required']);
            return;
        }
        
        $userId = $data['id'];
        
        // Prevent users from updating themselves to a higher role
        if ($userId == $_SESSION['user_id']) {
            $stmt = $conn->prepare("SELECT role FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $currentUser = $stmt->fetch();
            
            if ($currentUser && isset($data['role']) && $data['role'] !== $currentUser['role']) {
                // Check if trying to escalate privileges
                $roleHierarchy = ['cashier' => 1, 'manager' => 2, 'admin' => 3];
                if ($roleHierarchy[$data['role']] > $roleHierarchy[$currentUser['role']]) {
                    echo json_encode(['success' => false, 'error' => 'Cannot escalate your own privileges']);
                    return;
                }
            }
        }
        
        // Build dynamic update query
        $fields = [];
        $params = [':id' => $userId];
        
        if (isset($data['full_name'])) {
            $fields[] = "full_name = :full_name";
            $params[':full_name'] = $data['full_name'];
        }
        
        if (isset($data['email'])) {
            $fields[] = "email = :email";
            $params[':email'] = $data['email'];
        }
        
        if (isset($data['role'])) {
            $fields[] = "role = :role";
            $params[':role'] = $data['role'];
        }
        
        if (isset($data['department_id'])) {
            $fields[] = "department_id = :department_id";
            $params[':department_id'] = $data['department_id'];
        }
        
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $data['status'];
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = "password_hash = :password_hash";
            $params[':password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        if (empty($fields)) {
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            return;
        }
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
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
        
        // Prevent users from deleting themselves
        if ($data['id'] == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Cannot delete yourself']);
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