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

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check if user has permission to manage departments
$sql = "SELECT role FROM users WHERE id = :user_id AND status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || ($user['role'] !== 'admin' && $user['role'] !== 'manager')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            getDepartment($conn, $_GET['id']);
        } else {
            getDepartments($conn);
        }
        break;
    case 'POST':
        createDepartment($conn);
        break;
    case 'PUT':
        updateDepartment($conn);
        break;
    case 'DELETE':
        deleteDepartment($conn);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function getDepartments($conn) {
    try {
        $sql = "SELECT id, name, description, created_at, updated_at FROM departments ORDER BY name";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $departments = $stmt->fetchAll();
        
        // Get user count for each department
        $userCountSql = "SELECT department_id, COUNT(*) as user_count FROM users WHERE department_id IS NOT NULL GROUP BY department_id";
        $userCountStmt = $conn->prepare($userCountSql);
        $userCountStmt->execute();
        $userCounts = $userCountStmt->fetchAll();
        
        // Create a map of department_id to user_count
        $userCountMap = [];
        foreach ($userCounts as $count) {
            $userCountMap[$count['department_id']] = $count['user_count'];
        }
        
        // Add user count to each department
        foreach ($departments as &$dept) {
            $dept['user_count'] = isset($userCountMap[$dept['id']]) ? $userCountMap[$dept['id']] : 0;
        }
        
        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $departments]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getDepartment($conn, $id) {
    try {
        $sql = "SELECT id, name, description, created_at, updated_at FROM departments WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        $department = $stmt->fetch();
        
        if (!$department) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Department not found']);
            return;
        }
        
        // Get users in this department
        $userSql = "SELECT id, username, full_name, email, role, status FROM users WHERE department_id = :department_id";
        $userStmt = $conn->prepare($userSql);
        $userStmt->execute(['department_id' => $id]);
        $department['users'] = $userStmt->fetchAll();
        
        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $department]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createDepartment($conn) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data || !isset($data['name']) || empty($data['name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Department name is required']);
            return;
        }
        
        // Check if department already exists
        $checkSql = "SELECT id FROM departments WHERE name = :name";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute(['name' => $data['name']]);
        
        if ($checkStmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Department with this name already exists']);
            return;
        }
        
        $sql = "INSERT INTO departments (name, description) VALUES (:name, :description)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? ''
        ]);
        
        $departmentId = $conn->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'id' => $departmentId,
            'message' => 'Department created successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateDepartment($conn) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data || !isset($data['id']) || !isset($data['name']) || empty($data['name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Department ID and name are required']);
            return;
        }
        
        // Check if another department already exists with this name
        $checkSql = "SELECT id FROM departments WHERE name = :name AND id != :id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute(['name' => $data['name'], 'id' => $data['id']]);
        
        if ($checkStmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Another department with this name already exists']);
            return;
        }
        
        $sql = "UPDATE departments SET name = :name, description = :description, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            'id' => $data['id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? ''
        ]);
        
        if ($result) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Department updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update department']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteDepartment($conn) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data || !isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Department ID is required']);
            return;
        }
        
        // Check if department has users
        $checkSql = "SELECT COUNT(*) as user_count FROM users WHERE department_id = :id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute(['id' => $data['id']]);
        $result = $checkStmt->fetch();
        
        if ($result['user_count'] > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Cannot delete department with active users. Please reassign users first.']);
            return;
        }
        
        $sql = "DELETE FROM departments WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute(['id' => $data['id']]);
        
        if ($result) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Department deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete department']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>