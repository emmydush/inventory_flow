<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

// Check if user has permission to view audit logs
$sql = "SELECT u.role, up.permission FROM users u LEFT JOIN user_permissions up ON u.role = up.role WHERE u.id = :user_id AND up.permission = 'view_audit_logs'";
$stmt = $conn->prepare($sql);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user && $_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        getAuditLogs($conn);
        break;
    case 'POST':
        logAuditEntry($conn);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function getAuditLogs($conn) {
    try {
        $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
        $table_name = isset($_GET['table_name']) ? $_GET['table_name'] : null;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        $sql = "SELECT 
                    al.id,
                    al.user_id,
                    u.username,
                    u.full_name,
                    al.action,
                    al.table_name,
                    al.record_id,
                    al.changes,
                    al.status,
                    al.error_message,
                    al.created_at
                FROM audit_logs al
                JOIN users u ON al.user_id = u.id
                WHERE 1=1";

        $params = [];

        if ($user_id) {
            $sql .= " AND al.user_id = :user_id";
            $params[':user_id'] = $user_id;
        }
        
        if ($table_name) {
            $sql .= " AND al.table_name = :table_name";
            $params[':table_name'] = $table_name;
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset";

        // We need to prepare the statement differently for limit/offset
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM audit_logs WHERE 1=1";
        $countParams = [];

        if ($user_id) {
            $countSql .= " AND user_id = :user_id";
            $countParams[':user_id'] = $user_id;
        }
        
        if ($table_name) {
            $countSql .= " AND table_name = :table_name";
            $countParams[':table_name'] = $table_name;
        }

        $countStmt = $conn->prepare($countSql);
        foreach ($countParams as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $countRow = $countStmt->fetch();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $logs,
            'total' => $countRow['total']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function logAuditEntry($conn) {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request data']);
            exit;
        }

        $action = $data['action'] ?? '';
        $table_name = $data['table_name'] ?? null;
        $record_id = $data['record_id'] ?? null;
        $changes = isset($data['changes']) ? json_encode($data['changes']) : null;
        $status = $data['status'] ?? 'success';
        $error_message = $data['error_message'] ?? null;

        $sql = "INSERT INTO audit_logs 
                (user_id, action, table_name, record_id, changes, status, error_message) 
                VALUES (:user_id, :action, :table_name, :record_id, :changes, :status, :error_message)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':action' => $action,
            ':table_name' => $table_name,
            ':record_id' => $record_id,
            ':changes' => $changes,
            ':status' => $status,
            ':error_message' => $error_message
        ]);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'id' => $conn->lastInsertId()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>