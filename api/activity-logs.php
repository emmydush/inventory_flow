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

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    getActivityLogs($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logActivity($conn);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function getActivityLogs($conn) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
    $entity_type = isset($_GET['entity_type']) ? $_GET['entity_type'] : null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    try {
        $sql = "SELECT 
                    al.id,
                    al.user_id,
                    u.username,
                    u.full_name,
                    al.action,
                    al.entity_type,
                    al.entity_id,
                    al.new_values,
                    al.created_at
                FROM activity_logs al
                JOIN users u ON al.user_id = u.id
                WHERE 1=1";

        if ($user_id) {
            $sql .= " AND al.user_id = " . intval($user_id);
        }
        if ($entity_type) {
            $sql .= " AND al.entity_type = '" . $conn->quote($entity_type) . "'";
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT " . $limit . " OFFSET " . $offset;

        $result = $conn->query($sql);
        $logs = $result->fetchAll();

        $countResult = $conn->query("SELECT COUNT(*) as total FROM activity_logs WHERE 1=1" . 
            ($user_id ? " AND user_id = " . intval($user_id) : "") .
            ($entity_type ? " AND entity_type = '" . $conn->quote($entity_type) . "'" : ""));
        $countRow = $countResult->fetch();

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

function logActivity($conn) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request data']);
            exit;
        }

        $action = $data['action'] ?? '';
        $entity_type = $data['entity_type'] ?? null;
        $entity_id = $data['entity_id'] ?? null;
        $old_values = isset($data['old_values']) ? json_encode($data['old_values']) : null;
        $new_values = isset($data['new_values']) ? json_encode($data['new_values']) : null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $sql = "INSERT INTO activity_logs 
                (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) 
                VALUES (:user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, :user_agent)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':action' => $action,
            ':entity_type' => $entity_type,
            ':entity_id' => $entity_id,
            ':old_values' => $old_values,
            ':new_values' => $new_values,
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent
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