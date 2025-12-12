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
    $stmt = $conn->prepare("SELECT u.id, u.role, u.department_id, u.organization_id, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ? AND u.status = 'active'");
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
            getPurchase($conn, $_GET['id'], $userInfo);
        } else {
            getPurchases($conn, $userInfo);
        }
        break;
    case 'POST':
        createPurchase($conn, $userInfo);
        break;
    case 'PUT':
        updatePurchase($conn, $userInfo);
        break;
    case 'DELETE':
        deletePurchase($conn, $userInfo);
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getPurchases($conn, $userInfo) {
    try {
        $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
        $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
        $supplierId = isset($_GET['supplier_id']) ? $_GET['supplier_id'] : '';
        
        // Base SQL query
        $sql = "SELECT p.*, s.name as supplier_name,
                CASE 
                    WHEN p.created_by = :current_user_id THEN 'own'
                    ELSE 'shared'
                END as ownership_level
                FROM purchases p 
                LEFT JOIN suppliers s ON p.supplier_id = s.id 
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
                    // View department data - users can see purchases created by users in their department
                    $sql .= " AND (p.created_by = :current_user_id OR p.created_by IN (SELECT id FROM users WHERE department_id = :department_id))";
                    $params[':department_id'] = $userInfo['department_id'];
                } else {
                    // View own data only
                    $sql .= " AND p.created_by = :current_user_id";
                }
            }
        }
        
        if (!empty($dateFrom)) {
            $sql .= " AND DATE(p.created_at) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $sql .= " AND DATE(p.created_at) <= :date_to";
            $params[':date_to'] = $dateTo;
        }
        
        if (!empty($supplierId)) {
            $sql .= " AND p.supplier_id = :supplier_id";
            $params[':supplier_id'] = $supplierId;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $purchases = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $purchases]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getPurchase($conn, $id, $userInfo) {
    try {
        // First get the purchase to check ownership
        $stmt = $conn->prepare("SELECT * FROM purchases WHERE id = :id AND organization_id = :organization_id");
        $stmt->execute([':id' => $id, ':organization_id' => $userInfo['organization_id']]);
        $purchase = $stmt->fetch();
        
        if (!$purchase) {
            echo json_encode(['success' => false, 'error' => 'Purchase not found']);
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
                
                // Check if user owns the purchase
                if (isset($purchase['created_by']) && $purchase['created_by'] == $userInfo['id']) {
                    $canView = true;
                }
                // Check if user can view department data and purchase belongs to same department
                else if ($hasDeptPermission && $userInfo['department_id']) {
                    $creatorStmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
                    $creatorStmt->execute([$purchase['created_by']]);
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
        
        // Get purchase items
        $itemsStmt = $conn->prepare("SELECT * FROM purchase_items WHERE purchase_id = ?");
        $itemsStmt->execute([$id]);
        $items = $itemsStmt->fetchAll();
        
        $purchase['items'] = $items;
        
        echo json_encode(['success' => true, 'data' => $purchase]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createPurchase($conn, $userInfo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Invalid request data']);
            return;
        }

        if (!isset($data['supplier_id']) || empty($data['supplier_id'])) {
            echo json_encode(['success' => false, 'error' => 'Supplier is required']);
            return;
        }

        if (!isset($data['items']) || !is_array($data['items']) || count($data['items']) == 0) {
            echo json_encode(['success' => false, 'error' => 'At least one item is required']);
            return;
        }

        // Validate items
        foreach ($data['items'] as $item) {
            if (!isset($item['product_id']) || !isset($item['quantity']) || !isset($item['unit_price'])) {
                echo json_encode(['success' => false, 'error' => 'Each item must have product_id, quantity, and unit_price']);
                return;
            }
            
            if ($item['quantity'] <= 0 || $item['unit_price'] < 0) {
                echo json_encode(['success' => false, 'error' => 'Item quantity must be positive and unit price must be non-negative']);
                return;
            }
        }

        // Generate invoice number
        $invoiceNumber = 'PUR-' . date('Ymd') . '-' . strtoupper(substr(md5(time()), 0, 6));

        // Calculate totals
        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $subtotal += $item['quantity'] * $item['unit_price'];
        }
        
        $tax = isset($data['tax']) ? $data['tax'] : 0;
        $discount = isset($data['discount']) ? $data['discount'] : 0;
        $total = $subtotal + $tax - $discount;

        // Insert purchase
        $stmt = $conn->prepare("INSERT INTO purchases (invoice_number, supplier_id, subtotal, tax, discount, total, payment_method, payment_status, notes, created_by, organization_id) 
                                VALUES (:invoice_number, :supplier_id, :subtotal, :tax, :discount, :total, :payment_method, :payment_status, :notes, :created_by, :organization_id)");
        $stmt->execute([
            ':invoice_number' => $invoiceNumber,
            ':supplier_id' => $data['supplier_id'],
            ':subtotal' => $subtotal,
            ':tax' => $tax,
            ':discount' => $discount,
            ':total' => $total,
            ':payment_method' => $data['payment_method'] ?? 'cash',
            ':payment_status' => $data['payment_status'] ?? 'pending',
            ':notes' => $data['notes'] ?? '',
            ':created_by' => $userInfo['id'],
            ':organization_id' => $userInfo['organization_id']
        ]);
        
        $purchaseId = $conn->lastInsertId();
        
        // Insert purchase items and update product quantities
        foreach ($data['items'] as $item) {
            // Get product name
            $prodStmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
            $prodStmt->execute([$item['product_id']]);
            $product = $prodStmt->fetch();
            
            if (!$product) {
                // Rollback by deleting the purchase
                $delStmt = $conn->prepare("DELETE FROM purchases WHERE id = ?");
                $delStmt->execute([$purchaseId]);
                
                echo json_encode(['success' => false, 'error' => 'Product not found: ' . $item['product_id']]);
                return;
            }
            
            $itemName = $product['name'];
            $itemTotal = $item['quantity'] * $item['unit_price'];
            
            // Insert purchase item
            $itemStmt = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, product_name, quantity, unit_price, total, organization_id) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?)");
            $itemStmt->execute([$purchaseId, $item['product_id'], $itemName, $item['quantity'], $item['unit_price'], $itemTotal, $userInfo['organization_id']]);
            
            // Update product quantity (increase stock)
            $updStmt = $conn->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
            $updStmt->execute([$item['quantity'], $item['product_id']]);
            
            // Log stock transaction
            $logStmt = $conn->prepare("INSERT INTO stock_transactions (product_id, type, quantity, notes) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$item['product_id'], 'add', $item['quantity'], 'Purchase #' . $invoiceNumber]);
        }
        
        // Log the activity
        $activityStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $activityStmt->execute([
            $userInfo['id'],
            'create',
            'purchase',
            $purchaseId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        echo json_encode(['success' => true, 'id' => $purchaseId, 'invoice_number' => $invoiceNumber, 'message' => 'Purchase created successfully']);
    } catch(PDOException $e) {
        error_log('Create purchase error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updatePurchase($conn, $userInfo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Invalid request data']);
            return;
        }

        if (!isset($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'Purchase ID is required']);
            return;
        }

        // Check if user has permission to update this purchase
        if ($userInfo['role'] !== 'admin') {
            // First get the purchase to check ownership
            $checkStmt = $conn->prepare("SELECT created_by FROM purchases WHERE id = ? AND organization_id = ?");
            $checkStmt->execute([$data['id'], $userInfo['organization_id']]);
            $purchase = $checkStmt->fetch();
            
            if (!$purchase) {
                echo json_encode(['success' => false, 'error' => 'Purchase not found']);
                return;
            }
            
            // Check if user has permission to manage all data
            $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'manage_purchases'");
            $permStmt->execute([$userInfo['role']]);
            
            $hasManagePermission = $permStmt->fetchColumn() > 0;
            
            // Check individual permissions
            $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'manage_purchases'");
            $indPermStmt->execute([$userInfo['id']]);
            $individualPermission = $indPermStmt->fetch();
            
            if ($individualPermission) {
                $hasManagePermission = $individualPermission['granted'] == 1;
            }
            
            // If user doesn't have manage_purchases permission, check ownership
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
                
                // Check if user owns the purchase
                if ($purchase['created_by'] == $userInfo['id']) {
                    $canUpdate = true;
                }
                // Check if user can manage department data and purchase belongs to same department
                else if ($hasDeptPermission && $userInfo['department_id']) {
                    $creatorStmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
                    $creatorStmt->execute([$purchase['created_by']]);
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

        // Update purchase
        $fields = [];
        $params = [];
        
        if (isset($data['payment_status'])) {
            $fields[] = "payment_status = ?";
            $params[] = $data['payment_status'];
        }
        
        if (isset($data['notes'])) {
            $fields[] = "notes = ?";
            $params[] = $data['notes'];
        }
        
        if (empty($fields)) {
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            return;
        }
        
        $params[] = $data['id'];
        $params[] = $userInfo['organization_id'];
        $sql = "UPDATE purchases SET " . implode(", ", $fields) . " WHERE id = ? AND organization_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        // Log the activity
        $activityStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $activityStmt->execute([
            $userInfo['id'],
            'update',
            'purchase',
            $data['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Purchase updated successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deletePurchase($conn, $userInfo) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'Purchase ID is required']);
            return;
        }

        // Check if user has permission to delete this purchase
        if ($userInfo['role'] !== 'admin') {
            // First get the purchase to check ownership
            $checkStmt = $conn->prepare("SELECT created_by FROM purchases WHERE id = ? AND organization_id = ?");
            $checkStmt->execute([$data['id'], $userInfo['organization_id']]);
            $purchase = $checkStmt->fetch();
            
            if (!$purchase) {
                echo json_encode(['success' => false, 'error' => 'Purchase not found']);
                return;
            }
            
            // Check if user has permission to manage all data
            $permStmt = $conn->prepare("SELECT COUNT(*) FROM user_permissions WHERE role = ? AND permission = 'manage_purchases'");
            $permStmt->execute([$userInfo['role']]);
            
            $hasManagePermission = $permStmt->fetchColumn() > 0;
            
            // Check individual permissions
            $indPermStmt = $conn->prepare("SELECT granted FROM individual_permissions WHERE user_id = ? AND permission = 'manage_purchases'");
            $indPermStmt->execute([$userInfo['id']]);
            $individualPermission = $indPermStmt->fetch();
            
            if ($individualPermission) {
                $hasManagePermission = $individualPermission['granted'] == 1;
            }
            
            // If user doesn't have manage_purchases permission, check ownership
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
                
                $canDelete = false;
                
                // Check if user owns the purchase
                if ($purchase['created_by'] == $userInfo['id']) {
                    $canDelete = true;
                }
                // Check if user can manage department data and purchase belongs to same department
                else if ($hasDeptPermission && $userInfo['department_id']) {
                    $creatorStmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
                    $creatorStmt->execute([$purchase['created_by']]);
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

        // Delete purchase (will cascade delete purchase items)
        $stmt = $conn->prepare("DELETE FROM purchases WHERE id = ? AND organization_id = ?");
        $stmt->execute([$data['id'], $userInfo['organization_id']]);
        
        // Log the activity
        $activityStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $activityStmt->execute([
            $userInfo['id'],
            'delete',
            'purchase',
            $data['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Purchase deleted successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>