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

// Get user information for data isolation
$userInfo = [];
try {
    $stmt = $conn->prepare("SELECT u.role, u.department_id, d.name as department_name 
                           FROM users u 
                           LEFT JOIN departments d ON u.department_id = d.id 
                           WHERE u.id = :user_id AND u.status = 'active'");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $userInfo = $stmt->fetch();
    
    if (!$userInfo) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'User not found or inactive']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error retrieving user information']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getCustomer($conn, $_GET['id']);
        } else {
            getCustomers($conn, $userInfo);
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

function getCustomers($conn, $userInfo) {
    try {
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        
        // Base SQL query with ownership information
        $sql = "SELECT c.*,
                CASE 
                    WHEN c.created_by = :current_user_id THEN 'own'
                    ELSE 'shared'
                END as ownership_level
                FROM customers c WHERE 1=1";
        $params = [':current_user_id' => $userInfo['id']];
        
        // Apply data isolation based on user permissions
        if ($userInfo['role'] !== 'admin') {
            // Check if user has permission to view all data
            $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'view_all_data'");
            $permStmt->execute([$userInfo['role']]);
            
            $hasViewAllPermission = $permStmt->fetchColumn() > 0;
            
            // Check individual permissions
            $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'view_all_data'");
            $indPermStmt->execute([$userInfo['id']]);
            $individualPermission = $indPermStmt->fetch();
            
            if ($individualPermission) {
                $hasViewAllPermission = $individualPermission['granted'] == 1;
            }
            
            // If user doesn't have view_all_data permission, apply data isolation
            if (!$hasViewAllPermission) {
                // Check department-level permission
                $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'view_department_data'");
                $permStmt->execute([$userInfo['role']]);
                
                $hasDeptPermission = $permStmt->fetchColumn() > 0;
                
                // Check individual permissions for department data
                $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'view_department_data'");
                $indPermStmt->execute([$userInfo['id']]);
                $individualDeptPermission = $indPermStmt->fetch();
                
                if ($individualDeptPermission) {
                    $hasDeptPermission = $individualDeptPermission['granted'] == 1;
                }
                
                if ($hasDeptPermission && $userInfo['department_id']) {
                    // View department data - users can see customers created by users in their department
                    $sql .= " AND (c.created_by = :current_user_id OR c.created_by IN (SELECT id FROM users WHERE department_id = :department_id))";
                    $params[':department_id'] = $userInfo['department_id'];
                } else {
                    // View own data only
                    $sql .= " AND c.created_by = :current_user_id";
                }
            }
        }
        
        if (!empty($search)) {
            $sql .= " AND (c.name LIKE :search OR c.email LIKE :search OR c.phone LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        $sql .= " ORDER BY c.name ASC";
        
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
        // First check if user has permission to view this customer
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }
        
        // Get user information
        $stmt = $conn->prepare("SELECT u.role, u.department_id FROM users u WHERE u.id = ? AND u.status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        $userInfo = $stmt->fetch();
        
        if (!$userInfo) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'User not found or inactive']);
            return;
        }
        
        // First get the customer
        $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        
        if (!$customer) {
            echo json_encode(['success' => false, 'error' => 'Customer not found']);
            return;
        }
        
        // Apply data isolation
        if ($userInfo['role'] !== 'admin') {
            // Check if user has permission to view all data
            $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'view_all_data'");
            $permStmt->execute([$userInfo['role']]);
            
            $hasViewAllPermission = $permStmt->fetchColumn() > 0;
            
            // Check individual permissions
            $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'view_all_data'");
            $indPermStmt->execute([$_SESSION['user_id']]);
            $individualPermission = $indPermStmt->fetch();
            
            if ($individualPermission) {
                $hasViewAllPermission = $individualPermission['granted'] == 1;
            }
            
            // If user doesn't have view_all_data permission, check ownership
            if (!$hasViewAllPermission) {
                // Check department-level permission
                $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'view_department_data'");
                $permStmt->execute([$userInfo['role']]);
                
                $hasDeptPermission = $permStmt->fetchColumn() > 0;
                
                // Check individual permissions for department data
                $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'view_department_data'");
                $indPermStmt->execute([$_SESSION['user_id']]);
                $individualDeptPermission = $indPermStmt->fetch();
                
                if ($individualDeptPermission) {
                    $hasDeptPermission = $individualDeptPermission['granted'] == 1;
                }
                
                $canView = false;
                
                // Check if user owns the customer
                if (isset($customer['created_by']) && $customer['created_by'] == $_SESSION['user_id']) {
                    $canView = true;
                }
                // Check if user can view department data and customer belongs to same department
                else if ($hasDeptPermission && $userInfo['department_id']) {
                    $creatorStmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
                    $creatorStmt->execute([$customer['created_by']]);
                    $creator = $creatorStmt->fetch();
                    
                    if ($creator && $creator['department_id'] == $userInfo['department_id']) {
                        $canView = true;
                    }
                }
                
                if (!$canView) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Access denied']);
                    return;
                }
            }
        }
        
        echo json_encode(['success' => true, 'data' => $customer]);
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

        // Add created_by field to track ownership
        $stmt = $conn->prepare("INSERT INTO customers (name, email, phone, address, credit_limit, created_by) 
                                VALUES (:name, :email, :phone, :address, :credit_limit, :created_by)");
        $stmt->execute([
            ':name' => $data['name'],
            ':email' => $data['email'] ?? '',
            ':phone' => $data['phone'] ?? '',
            ':address' => $data['address'] ?? '',
            ':credit_limit' => $data['credit_limit'] ?? 0,
            ':created_by' => $_SESSION['user_id']
        ]);
        
        $customerId = $conn->lastInsertId();
        
        // Log the activity
        $activityStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $activityStmt->execute([
            $_SESSION['user_id'],
            'create',
            'customer',
            $customerId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        echo json_encode(['success' => true, 'id' => $customerId, 'message' => 'Customer created successfully']);
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
        
        // First check if user has permission to update this customer
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }
        
        // Get user information
        $stmt = $conn->prepare("SELECT u.role, u.department_id FROM users u WHERE u.id = ? AND u.status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        $userInfo = $stmt->fetch();
        
        if (!$userInfo) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'User not found or inactive']);
            return;
        }
        
        // Check if user has permission to update this customer
        if ($userInfo['role'] !== 'admin') {
            // First get the customer to check ownership
            $checkStmt = $conn->prepare("SELECT created_by FROM customers WHERE id = ?");
            $checkStmt->execute([$data['id']]);
            $customer = $checkStmt->fetch();
            
            if (!$customer) {
                echo json_encode(['success' => false, 'error' => 'Customer not found']);
                return;
            }
            
            // Check if user has permission to manage all data
            $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'manage_customers'");
            $permStmt->execute([$userInfo['role']]);
            
            $hasManagePermission = $permStmt->fetchColumn() > 0;
            
            // Check individual permissions
            $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'manage_customers'");
            $indPermStmt->execute([$_SESSION['user_id']]);
            $individualPermission = $indPermStmt->fetch();
            
            if ($individualPermission) {
                $hasManagePermission = $individualPermission['granted'] == 1;
            }
            
            // If user doesn't have manage_customers permission, check ownership
            if (!$hasManagePermission) {
                // Check department-level permission
                $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'view_department_data'");
                $permStmt->execute([$userInfo['role']]);
                
                $hasDeptPermission = $permStmt->fetchColumn() > 0;
                
                // Check individual permissions for department data
                $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'view_department_data'");
                $indPermStmt->execute([$_SESSION['user_id']]);
                $individualDeptPermission = $indPermStmt->fetch();
                
                if ($individualDeptPermission) {
                    $hasDeptPermission = $individualDeptPermission['granted'] == 1;
                }
                
                $canUpdate = false;
                
                // Check if user owns the customer
                if ($customer['created_by'] == $_SESSION['user_id']) {
                    $canUpdate = true;
                }
                // Check if user can manage department data and customer belongs to same department
                else if ($hasDeptPermission && $userInfo['department_id']) {
                    $creatorStmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
                    $creatorStmt->execute([$customer['created_by']]);
                    $creator = $creatorStmt->fetch();
                    
                    if ($creator && $creator['department_id'] == $userInfo['department_id']) {
                        $canUpdate = true;
                    }
                }
                
                if (!$canUpdate) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Access denied']);
                    return;
                }
            }
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
        
        // Log the activity
        $activityStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $activityStmt->execute([
            $_SESSION['user_id'],
            'update',
            'customer',
            $data['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
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
        
        // First check if user has permission to delete this customer
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }
        
        // Get user information
        $stmt = $conn->prepare("SELECT u.role, u.department_id FROM users u WHERE u.id = ? AND u.status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        $userInfo = $stmt->fetch();
        
        if (!$userInfo) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'User not found or inactive']);
            return;
        }
        
        // Check if user has permission to delete this customer
        if ($userInfo['role'] !== 'admin') {
            // First get the customer to check ownership
            $checkStmt = $conn->prepare("SELECT created_by FROM customers WHERE id = ?");
            $checkStmt->execute([$data['id']]);
            $customer = $checkStmt->fetch();
            
            if (!$customer) {
                echo json_encode(['success' => false, 'error' => 'Customer not found']);
                return;
            }
            
            // Check if user has permission to manage all data
            $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'manage_customers'");
            $permStmt->execute([$userInfo['role']]);
            
            $hasManagePermission = $permStmt->fetchColumn() > 0;
            
            // Check individual permissions
            $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'manage_customers'");
            $indPermStmt->execute([$_SESSION['user_id']]);
            $individualPermission = $indPermStmt->fetch();
            
            if ($individualPermission) {
                $hasManagePermission = $individualPermission['granted'] == 1;
            }
            
            // Check if user has permission to delete records
            $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'delete_records'");
            $permStmt->execute([$userInfo['role']]);
            
            $hasDeletePermission = $permStmt->fetchColumn() > 0;
            
            // Check individual permissions for delete records
            $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'delete_records'");
            $indPermStmt->execute([$_SESSION['user_id']]);
            $individualDeletePermission = $indPermStmt->fetch();
            
            if ($individualDeletePermission) {
                $hasDeletePermission = $individualDeletePermission['granted'] == 1;
            }
            
            // If user doesn't have manage_customers or delete_records permission, check ownership
            if (!$hasManagePermission && !$hasDeletePermission) {
                // Check department-level permission
                $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'delete_records'");
                $permStmt->execute([$userInfo['role']]);
                
                $hasDeptPermission = $permStmt->fetchColumn() > 0;
                
                // Check individual permissions for department data
                $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'delete_records'");
                $indPermStmt->execute([$_SESSION['user_id']]);
                $individualDeptPermission = $indPermStmt->fetch();
                
                if ($individualDeptPermission) {
                    $hasDeptPermission = $individualDeptPermission['granted'] == 1;
                }
                
                $canDelete = false;
                
                // Check if user owns the customer
                if ($customer['created_by'] == $_SESSION['user_id']) {
                    $canDelete = true;
                }
                // Check if user can delete department data and customer belongs to same department
                else if ($hasDeptPermission && $userInfo['department_id']) {
                    $creatorStmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
                    $creatorStmt->execute([$customer['created_by']]);
                    $creator = $creatorStmt->fetch();
                    
                    if ($creator && $creator['department_id'] == $userInfo['department_id']) {
                        $canDelete = true;
                    }
                }
                
                if (!$canDelete) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Access denied']);
                    return;
                }
            }
        }

        $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        // Log the activity
        $activityStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $activityStmt->execute([
            $_SESSION['user_id'],
            'delete',
            'customer',
            $data['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
    } catch(PDOException $e) {
        error_log('Delete customer error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
