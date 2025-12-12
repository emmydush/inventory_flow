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
    getRecentEvents($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    recordEvent($conn);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function getRecentEvents($conn) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $event_type = isset($_GET['event_type']) ? $_GET['event_type'] : null;

    try {
        $sql = "SELECT 
                    re.id,
                    re.user_id,
                    u.username,
                    u.full_name,
                    re.event_type,
                    re.entity_type,
                    re.entity_id,
                    re.event_data,
                    re.created_at,
                    CASE 
                        WHEN TIMESTAMPDIFF(MINUTE, re.created_at, NOW()) < 1 THEN 'Just now'
                        WHEN TIMESTAMPDIFF(HOUR, re.created_at, NOW()) < 1 THEN CONCAT(TIMESTAMPDIFF(MINUTE, re.created_at, NOW()), ' min ago')
                        WHEN TIMESTAMPDIFF(DAY, re.created_at, NOW()) < 1 THEN CONCAT(TIMESTAMPDIFF(HOUR, re.created_at, NOW()), ' hours ago')
                        ELSE DATE_FORMAT(re.created_at, '%Y-%m-%d %H:%i')
                    END as time_ago
                FROM real_time_events re
                JOIN users u ON re.user_id = u.id
                WHERE 1=1";

        if ($event_type) {
            $sql .= " AND re.event_type = '" . $conn->quote($event_type) . "'";
        }

        $sql .= " ORDER BY re.created_at DESC LIMIT " . $limit;

        $result = $conn->query($sql);
        $events = $result->fetchAll();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $events
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function recordEvent($conn) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['event_type'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Event type is required']);
        exit;
    }

    try {
        $user_id = $_SESSION['user_id'];
        $event_type = $data['event_type'];
        $entity_type = $data['entity_type'] ?? null;
        $entity_id = $data['entity_id'] ?? null;
        $event_data = $data['event_data'] ? json_encode($data['event_data']) : null;

        $sql = "INSERT INTO real_time_events 
                (user_id, event_type, entity_type, entity_id, event_data) 
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $user_id,
            $event_type,
            $entity_type,
            $entity_id,
            $event_data
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
