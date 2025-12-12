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
    $stmt = $conn->prepare("SELECT u.role, u.department_id, u.organization_id, d.name as department_name 
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
            getProduct($conn, $_GET['id']);
        } else {
            getProducts($conn, $userInfo);
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

function getProducts($conn, $userInfo) {
    try {
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $category = isset($_GET['category']) ? $_GET['category'] : '';
        
        // Base SQL query with ownership information
        $sql = "SELECT p.*, c.name as category_name,
                CASE 
                    WHEN p.created_by = :current_user_id THEN 'own'
                    ELSE 'shared'
                END as ownership_level
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.organization_id = :organization_id";
        $params = [':current_user_id' => $userInfo['id'], ':organization_id' => $userInfo['organization_id']];
        
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
                    // View department data - users can see products created by users in their department
                    $sql .= " AND (p.created_by = :current_user_id OR p.created_by IN (SELECT id FROM users WHERE department_id = :department_id))";
                    $params[':department_id'] = $userInfo['department_id'];
                } else {
                    // View own data only
                    $sql .= " AND p.created_by = :current_user_id";
                }
            }
        }
        
        if (!empty($search)) {
            // Check if exact match is requested
            $exact = isset($_GET['exact']) && $_GET['exact'] === 'true';
            
            if ($exact) {
                $sql .= " AND p.sku = :search";
                $params[':search'] = $search;
            } else {
                $sql .= " AND (p.name LIKE :search OR p.sku LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }
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
        // First check if user has permission to view this product
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
        
        // First get the product
        $stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.organization_id = ?");
        $stmt->execute([$id, $userInfo['organization_id']]);
        $product = $stmt->fetch();
        
        if (!$product) {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
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
                
                // Check if user owns the product
                if (isset($product['created_by']) && $product['created_by'] == $_SESSION['user_id']) {
                    $canView = true;
                }
                // Check if user can view department data and product belongs to same department
                else if ($hasDeptPermission && $userInfo['department_id']) {
                    $creatorStmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
                    $creatorStmt->execute([$product['created_by']]);
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
        
        echo json_encode(['success' => true, 'data' => $product]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createProduct($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Add created_by field to track ownership
        $stmt = $conn->prepare("INSERT INTO products (sku, name, description, category_id, quantity, price, cost_price, min_stock, created_by, organization_id) 
                                VALUES (:sku, :name, :description, :category_id, :quantity, :price, :cost_price, :min_stock, :created_by, :organization_id)");
        $stmt->execute([
            ':sku' => $data['sku'],
            ':name' => $data['name'],
            ':description' => $data['description'] ?? '',
            ':category_id' => isset($data['category_id']) && $data['category_id'] !== '' ? $data['category_id'] : null,
            ':quantity' => $data['quantity'] ?? 0,
            ':price' => $data['price'] ?? 0,
            ':cost_price' => $data['cost_price'] ?? 0,
            ':min_stock' => $data['min_stock'] ?? 10,
            ':created_by' => $_SESSION['user_id'],
            ':organization_id' => $_SESSION['organization_id']
        ]);
        
        $productId = $conn->lastInsertId();
        
        if ($data['quantity'] > 0) {
            $transStmt = $conn->prepare("INSERT INTO stock_transactions (product_id, type, quantity, notes) 
                                         VALUES (:product_id, 'initial', :quantity, 'Initial stock')");
            $transStmt->execute([':product_id' => $productId, ':quantity' => $data['quantity']]);
        }
        
        // Log the activity
        $activityStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $activityStmt->execute([
            $_SESSION['user_id'],
            'create',
            'product',
            $productId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        echo json_encode(['success' => true, 'id' => $productId, 'message' => 'Product created successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateProduct($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // First check if user has permission to update this product
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }
        
        // Get user information
        $stmt = $conn->prepare("SELECT u.role, u.department_id, u.organization_id FROM users u WHERE u.id = ? AND u.status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        $userInfo = $stmt->fetch();
        
        if (!$userInfo) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'User not found or inactive']);
            return;
        }
        
        // Check if user has permission to update this product
        if ($userInfo['role'] !== 'admin') {
            // First get the product to check ownership
            $checkStmt = $conn->prepare("SELECT created_by FROM products WHERE id = ? AND organization_id = ?");
            $checkStmt->execute([$data['id'], $userInfo['organization_id']]);
            $product = $checkStmt->fetch();
            
            if (!$product) {
                echo json_encode(['success' => false, 'error' => 'Product not found']);
                return;
            }
            
            // Check if user has permission to manage all data
            $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'manage_products'");
            $permStmt->execute([$userInfo['role']]);
            
            $hasManagePermission = $permStmt->fetchColumn() > 0;
            
            // Check individual permissions
            $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'manage_products'");
            $indPermStmt->execute([$_SESSION['user_id']]);
            $individualPermission = $indPermStmt->fetch();
            
            if ($individualPermission) {
                $hasManagePermission = $individualPermission['granted'] == 1;
            }
            
            // If user doesn't have manage_products permission, check ownership
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
                
                // Check if user owns the product
                if ($product['created_by'] == $_SESSION['user_id']) {
                    $canUpdate = true;
                }
                // Check if user can manage department data and product belongs to same department
                else if ($hasDeptPermission && $userInfo['department_id']) {
                    $creatorStmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
                    $creatorStmt->execute([$product['created_by']]);
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
        
        $stmt = $conn->prepare("UPDATE products 
                                SET sku = :sku, name = :name, description = :description, 
                                    category_id = :category_id, quantity = :quantity, 
                                    price = :price, cost_price = :cost_price, min_stock = :min_stock, updated_at = CURRENT_TIMESTAMP
                                WHERE id = :id");
        $stmt->execute([
            ':id' => $data['id'],
            ':sku' => $data['sku'],
            ':name' => $data['name'],
            ':description' => $data['description'] ?? '',
            ':category_id' => isset($data['category_id']) && $data['category_id'] !== '' ? $data['category_id'] : null,
            ':quantity' => $data['quantity'] ?? 0,
            ':price' => $data['price'] ?? 0,
            ':cost_price' => $data['cost_price'] ?? 0,
            ':min_stock' => $data['min_stock'] ?? 10
        ]);
        
        // Log the activity
        $activityStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $activityStmt->execute([
            $_SESSION['user_id'],
            'update',
            'product',
            $data['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteProduct($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // First check if user has permission to delete this product
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }
        
        // Get user information
        $stmt = $conn->prepare("SELECT u.role, u.department_id, u.organization_id FROM users u WHERE u.id = ? AND u.status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        $userInfo = $stmt->fetch();
        
        if (!$userInfo) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'User not found or inactive']);
            return;
        }
        
        // Check if user has permission to delete this product
        if ($userInfo['role'] !== 'admin') {
            // First get the product to check ownership
            $checkStmt = $conn->prepare("SELECT created_by FROM products WHERE id = ? AND organization_id = ?");
            $checkStmt->execute([$data['id'], $userInfo['organization_id']]);
            $product = $checkStmt->fetch();
            
            if (!$product) {
                echo json_encode(['success' => false, 'error' => 'Product not found']);
                return;
            }
            
            // Check if user has permission to manage all data
            $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'manage_products'");
            $permStmt->execute([$userInfo['role']]);
            
            $hasManagePermission = $permStmt->fetchColumn() > 0;
            
            // Check individual permissions
            $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'manage_products'");
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
            
            // If user doesn't have manage_products or delete_records permission, check ownership
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
                
                // Check if user owns the product
                if ($product['created_by'] == $_SESSION['user_id']) {
                    $canDelete = true;
                }
                // Check if user can delete department data and product belongs to same department
                else if ($hasDeptPermission && $userInfo['department_id']) {
                    $creatorStmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
                    $creatorStmt->execute([$product['created_by']]);
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
        
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND organization_id = ?");
        $stmt->execute([$data['id'], $userInfo['organization_id']]);
        
        // Log the activity
        $activityStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $activityStmt->execute([
            $_SESSION['user_id'],
            'delete',
            'product',
            $data['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
