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

// Check if user has permission to manage users
$sql = "SELECT role FROM users WHERE id = :user_id AND status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['user_id'])) {
            getUserPermissions($conn, $_GET['user_id']);
        } else {
            getAllPermissions($conn);
        }
        break;
    case 'POST':
        updateUserPermissions($conn);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function getAllPermissions($conn) {
    try {
        // Return all available permissions
        $permissions = [
            // Dashboard
            'view_dashboard',
            
            // Users
            'manage_users',
            
            // Products
            'manage_products',
            'view_products',
            
            // Categories
            'manage_categories',
            
            // Customers
            'manage_customers',
            'view_customers',
            
            // Suppliers
            'manage_suppliers',
            
            // Sales
            'process_sales',
            
            // Credit Sales
            'manage_credit_sales',
            
            // Reports
            'view_reports',
            
            // Settings
            'manage_settings',
            
            // Audit Logs
            'view_audit_logs',
            
            // Inventory
            'manage_inventory',
            
            // Data
            'export_data',
            'import_data',
            'delete_records',
            
            // Departments
            'manage_departments',
            
            // Data Access
            'view_all_data',
            'view_department_data',
            'view_own_data'
        ];
        
        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $permissions]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getUserPermissions($conn, $userId) {
    try {
        // Get user role
        $sql = "SELECT u.role FROM users u WHERE u.id = :user_id AND u.status = 'active'";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        $role = $user['role'];

        // Get all permissions for the role
        $sql = "SELECT permission FROM user_permissions WHERE role = :role";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['role' => $role]);
        $rolePermissions = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        // Get individual user permissions (overrides)
        $sql = "SELECT permission, granted FROM individual_permissions WHERE user_id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $individualPermissions = $stmt->fetchAll();

        // Create a map of individual permissions
        $individualPermMap = [];
        foreach ($individualPermissions as $perm) {
            $individualPermMap[$perm['permission']] = $perm['granted'];
        }

        // Combine role permissions with individual overrides
        $finalPermissions = [];
        foreach ($rolePermissions as $perm) {
            // If there's an individual override, use that value
            if (isset($individualPermMap[$perm])) {
                if ($individualPermMap[$perm]) {
                    $finalPermissions[] = $perm;
                }
            } else {
                // Otherwise, use the role permission
                $finalPermissions[] = $perm;
            }
        }

        // Add any individually granted permissions that aren't in the role
        foreach ($individualPermMap as $perm => $granted) {
            if ($granted && !in_array($perm, $rolePermissions)) {
                $finalPermissions[] = $perm;
            }
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'role' => $role,
            'role_permissions' => $rolePermissions,
            'individual_permissions' => $individualPermissions,
            'effective_permissions' => array_values(array_unique($finalPermissions))
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateUserPermissions($conn) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data || !isset($data['user_id']) || !isset($data['permissions'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'User ID and permissions are required']);
            exit;
        }

        $userId = $data['user_id'];
        $permissions = $data['permissions']; // Array of {permission, granted} objects

        // Validate user exists
        $sql = "SELECT id FROM users WHERE id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        // Begin transaction
        $conn->beginTransaction();

        // Clear existing individual permissions for this user
        $sql = "DELETE FROM individual_permissions WHERE user_id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        // Insert new individual permissions
        if (!empty($permissions)) {
            $sql = "INSERT INTO individual_permissions (user_id, permission, granted) VALUES (:user_id, :permission, :granted)";
            $stmt = $conn->prepare($sql);
            
            foreach ($permissions as $perm) {
                $stmt->execute([
                    'user_id' => $userId,
                    'permission' => $perm['permission'],
                    'granted' => $perm['granted'] ? 1 : 0
                ]);
            }
        }

        // Commit transaction
        $conn->commit();

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'User permissions updated successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>