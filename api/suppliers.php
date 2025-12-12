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
    $stmt = $conn->prepare("SELECT u.id, u.role, u.department_id, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ? AND u.status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
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
            getSupplier($conn, $_GET['id'], $userInfo);
        } else {
            getSuppliers($conn, $userInfo);
        }
        break;
    case 'POST':
        createSupplier($conn, $userInfo);
        break;
    case 'PUT':
        updateSupplier($conn, $userInfo);
        break;
    case 'DELETE':
        deleteSupplier($conn, $userInfo);
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getSuppliers($conn, $userInfo) {
    try {
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        
        // Base SQL query
        $sql = "SELECT s.*, 
                CASE 
                    WHEN s.created_by = :current_user_id THEN 'own'
                    ELSE 'shared'
                END as ownership_level
                FROM suppliers s WHERE 1=1";
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
                    // View department data - users can see suppliers created by users in their department
                    $sql .= " AND (s.created_by = :current_user_id OR s.created_by IN (SELECT id FROM users WHERE department_id = :department_id))";
                    $params[':department_id'] = $userInfo['department_id'];
                } else {
                    // View own data only
                    $sql .= " AND s.created_by = :current_user_id";
                }
            }
        }
        
        if (!empty($search)) {
            $sql .= " AND (s.name LIKE :search OR s.contact_person LIKE :search OR s.email LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        $sql .= " ORDER BY s.name ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $suppliers = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $suppliers]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getSupplier($conn, $id, $userInfo) {
    try {
        // First get the supplier to check ownership
        $stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $supplier = $stmt->fetch();
        
        if (!$supplier) {
            echo json_encode(['success' => false, 'error' => 'Supplier not found']);
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
            $indPermStmt->execute([$userInfo['id']]);
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
                $indPermStmt->execute([$userInfo['id']]);
                $individualDeptPermission = $indPermStmt->fetch();
                
                if ($individualDeptPermission) {
                    $hasDeptPermission = $individualDeptPermission['granted'] == 1;
                }
                
                $canView = false;
                
                // Check if user owns the supplier
                if (isset($supplier['created_by']) && $supplier['created_by'] == $userInfo['id']) {
                    $canView = true;
                }
                // Check if user can view department data and supplier belongs to same department
                else if ($hasDeptPermission && $userInfo['department_id']) {
                    $creatorStmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
                    $creatorStmt->execute([$supplier['created_by']]);
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
        
        echo json_encode(['success' => true, 'data' => $supplier]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createSupplier($conn, $userInfo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Invalid request data']);
            return;
        }

        if (!isset($data['name']) || empty($data['name'])) {
            echo json_encode(['success' => false, 'error' => 'Supplier name is required']);
            return;
        }

        // Add created_by field to track ownership
        $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_person, email, phone, address, notes, created_by)
                                VALUES (:name, :contact_person, :email, :phone, :address, :notes, :created_by)");
        $stmt->execute([
            ':name' => $data['name'],
            ':contact_person' => $data['contact_person'] ?? '',
            ':email' => $data['email'] ?? '',
            ':phone' => $data['phone'] ?? '',
            ':address' => $data['address'] ?? '',
            ':notes' => $data['notes'] ?? '',
            ':created_by' => $userInfo['id']
        ]);

        $supplierId = $conn->lastInsertId();
        
        // Log the activity
        $activityStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $activityStmt->execute([
            $userInfo['id'],
            'create',
            'supplier',
            $supplierId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        echo json_encode(['success' => true, 'id' => $supplierId, 'message' => 'Supplier created successfully']);
    } catch(PDOException $e) {
        error_log('Create supplier error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateSupplier($conn, $userInfo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Invalid request data']);
            return;
        }

        if (!isset($data['id']) || !isset($data['name']) || empty($data['name'])) {
            echo json_encode(['success' => false, 'error' => 'Supplier ID and name are required']);
            return;
        }
        
        // Check if user has permission to update this supplier
        if ($userInfo['role'] !== 'admin') {
            // First get the supplier to check ownership
            $checkStmt = $conn->prepare("SELECT created_by FROM suppliers WHERE id = ?");
            $checkStmt->execute([$data['id']]);
            $supplier = $checkStmt->fetch();
            
            if (!$supplier) {
                echo json_encode(['success' => false, 'error' => 'Supplier not found']);
                return;
            }
            
            // Check if user has permission to manage all data
            $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'manage_suppliers'");
            $permStmt->execute([$userInfo['role']]);
            
            $hasManagePermission = $permStmt->fetchColumn() > 0;
            
            // Check individual permissions
            $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'manage_suppliers'");
            $indPermStmt->execute([$userInfo['id']]);
            $individualPermission = $indPermStmt->fetch();
            
            if ($individualPermission) {
                $hasManagePermission = $individualPermission['granted'] == 1;
            }
            
            // If user doesn't have manage_suppliers permission, check ownership
            if (!$hasManagePermission) {
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
                
                $canUpdate = false;
                
                // Check if user owns the supplier
                if ($supplier['created_by'] == $userInfo['id']) {
                    $canUpdate = true;
                }
                // Check if user can manage department data and supplier belongs to same department
                else if ($hasDeptPermission && $userInfo['department_id']) {
                    $creatorStmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
                    $creatorStmt->execute([$supplier['created_by']]);
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
        
        // Log the activity
        $activityStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $activityStmt->execute([
            $userInfo['id'],
            'update',
            'supplier',
            $data['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        echo json_encode(['success' => true, 'message' => 'Supplier updated successfully']);
    } catch(PDOException $e) {
        error_log('Update supplier error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteSupplier($conn, $userInfo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'Supplier ID is required']);
            return;
        }
        
        // Check if user has permission to delete this supplier
        if ($userInfo['role'] !== 'admin') {
            // First get the supplier to check ownership
            $checkStmt = $conn->prepare("SELECT created_by FROM suppliers WHERE id = ?");
            $checkStmt->execute([$data['id']]);
            $supplier = $checkStmt->fetch();
            
            if (!$supplier) {
                echo json_encode(['success' => false, 'error' => 'Supplier not found']);
                return;
            }
            
            // Check if user has permission to manage all data
            $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'manage_suppliers'");
            $permStmt->execute([$userInfo['role']]);
            
            $hasManagePermission = $permStmt->fetchColumn() > 0;
            
            // Check individual permissions
            $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'manage_suppliers'");
            $indPermStmt->execute([$userInfo['id']]);
            $individualPermission = $indPermStmt->fetch();
            
            if ($individualPermission) {
                $hasManagePermission = $individualPermission['granted'] == 1;
            }
            
            // If user doesn't have manage_suppliers permission, check ownership
            if (!$hasManagePermission) {
                // Check department-level permission
                $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'delete_records'");
                $permStmt->execute([$userInfo['role']]);
                
                $hasDeletePermission = $permStmt->fetchColumn() > 0;
                
                // Check individual permissions for delete records
                $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'delete_records'");
                $indPermStmt->execute([$userInfo['id']]);
                $individualDeletePermission = $indPermStmt->fetch();
                
                if ($individualDeletePermission) {
                    $hasDeletePermission = $individualDeletePermission['granted'] == 1;
                }
                
                $canDelete = false;
                
                // Check if user owns the supplier
                if ($supplier['created_by'] == $userInfo['id']) {
                    $canDelete = true;
                }
                // Check if user can delete department data and supplier belongs to same department
                else if ($hasDeletePermission && $userInfo['department_id']) {
                    $creatorStmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
                    $creatorStmt->execute([$supplier['created_by']]);
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

        $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        // Log the activity
        $activityStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $activityStmt->execute([
            $userInfo['id'],
            'delete',
            'supplier',
            $data['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully']);
    } catch(PDOException $e) {
        error_log('Delete supplier error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
