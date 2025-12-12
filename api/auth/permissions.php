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

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['user_id'])) {
            getUserPermissions($conn, $_GET['user_id']);
        } else {
            getCurrentUserPermissions($conn);
        }
        break;
    case 'POST':
        updateUserPermissions($conn);
        break;
    case 'PUT':
        updateRolePermissions($conn);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function getCurrentUserPermissions($conn) {
    try {
        // Get user role
        $sql = "SELECT u.role, u.department_id, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ? AND u.status = 'active'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        $role = $user['role'];
        $department_id = $user['department_id'];
        $department_name = $user['department_name'];

        // Get all permissions for the role
        $sql = "SELECT permission FROM user_permissions WHERE role = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$role]);
        $rolePermissions = $stmt->fetchAll();

        // Get individual user permissions (overrides)
        $sql = "SELECT permission, granted FROM individual_permissions WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        $individualPermissions = $stmt->fetchAll();

        // Merge permissions (individual overrides role)
        $permissions = [];
        foreach ($rolePermissions as $perm) {
            $permissions[] = $perm['permission'];
        }

        // Apply individual permission overrides
        foreach ($individualPermissions as $perm) {
            if ($perm['granted']) {
                if (!in_array($perm['permission'], $permissions)) {
                    $permissions[] = $perm['permission'];
                }
            } else {
                $key = array_search($perm['permission'], $permissions);
                if ($key !== false) {
                    unset($permissions[$key]);
                }
            }
        }

        // Re-index array
        $permissions = array_values($permissions);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'role' => $role,
            'department_id' => $department_id,
            'department_name' => $department_name,
            'permissions' => $permissions,
            'has_permission' => function($permission) use ($permissions) {
                return in_array($permission, $permissions);
            }
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getUserPermissions($conn, $userId) {
    try {
        // Check if requesting user has permission to view other users' permissions
        $requesterSql = "SELECT role FROM users WHERE id = ? AND status = 'active'";
        $requesterStmt = $conn->prepare($requesterSql);
        $requesterStmt->execute([$_SESSION['user_id']]);
        $requester = $requesterStmt->fetch();

        if (!$requester || ($requester['role'] !== 'admin' && $_SESSION['user_id'] != $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            exit;
        }

        // Get user role
        $sql = "SELECT u.role, u.department_id, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ? AND u.status = 'active'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        $role = $user['role'];
        $department_id = $user['department_id'];
        $department_name = $user['department_name'];

        // Get all permissions for the role
        $sql = "SELECT permission FROM user_permissions WHERE role = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$role]);
        $rolePermissions = $stmt->fetchAll();

        // Get individual user permissions (overrides)
        $sql = "SELECT permission, granted FROM individual_permissions WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId]);
        $individualPermissions = $stmt->fetchAll();

        // Merge permissions (individual overrides role)
        $permissions = [];
        foreach ($rolePermissions as $perm) {
            $permissions[] = $perm['permission'];
        }

        // Apply individual permission overrides
        foreach ($individualPermissions as $perm) {
            if ($perm['granted']) {
                if (!in_array($perm['permission'], $permissions)) {
                    $permissions[] = $perm['permission'];
                }
            } else {
                $key = array_search($perm['permission'], $permissions);
                if ($key !== false) {
                    unset($permissions[$key]);
                }
            }
        }

        // Re-index array
        $permissions = array_values($permissions);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'role' => $role,
            'department_id' => $department_id,
            'department_name' => $department_name,
            'permissions' => $permissions
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateUserPermissions($conn) {
    try {
        // Check if requesting user has permission to manage users
        $requesterSql = "SELECT role FROM users WHERE id = ? AND status = 'active'";
        $requesterStmt = $conn->prepare($requesterSql);
        $requesterStmt->execute([$_SESSION['user_id']]);
        $requester = $requesterStmt->fetch();

        if (!$requester || $requester['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['user_id']) || !isset($data['permissions'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'User ID and permissions are required']);
            exit;
        }

        $userId = $data['user_id'];
        $permissions = $data['permissions']; // Array of {permission, granted} objects

        // Clear existing individual permissions for this user
        $sql = "DELETE FROM individual_permissions WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId]);

        // Insert new individual permissions
        if (!empty($permissions)) {
            $sql = "INSERT INTO individual_permissions (user_id, permission, granted) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            foreach ($permissions as $perm) {
                $stmt->execute([$userId, $perm['permission'], $perm['granted'] ? 1 : 0]);
            }
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'User permissions updated successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateRolePermissions($conn) {
    try {
        // Check if requesting user has permission to manage roles
        $requesterSql = "SELECT role FROM users WHERE id = ? AND status = 'active'";
        $requesterStmt = $conn->prepare($requesterSql);
        $requesterStmt->execute([$_SESSION['user_id']]);
        $requester = $requesterStmt->fetch();

        if (!$requester || $requester['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['role']) || !isset($data['permissions'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Role and permissions are required']);
            exit;
        }

        $role = $data['role'];
        $permissions = $data['permissions']; // Array of permission strings

        // Clear existing permissions for this role
        $sql = "DELETE FROM user_permissions WHERE role = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$role]);

        // Insert new role permissions
        if (!empty($permissions)) {
            $sql = "INSERT INTO user_permissions (role, permission) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            
            foreach ($permissions as $permission) {
                $stmt->execute([$role, $permission]);
            }
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Role permissions updated successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>