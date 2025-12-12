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

// Get current user info
$userStmt = $conn->prepare("SELECT id, username, full_name FROM users WHERE id = :user_id");
$userStmt->execute(['user_id' => $_SESSION['user_id']]);
$user = $userStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create a test real-time event
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $eventType = $data['event_type'] ?? 'test_event';
        $entityType = $data['entity_type'] ?? 'test';
        $entityId = $data['entity_id'] ?? null;
        $eventData = $data['event_data'] ?? null;
        
        $sql = "INSERT INTO real_time_events 
                (user_id, event_type, entity_type, entity_id, event_data) 
                VALUES (:user_id, :event_type, :entity_type, :entity_id, :event_data)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':event_type' => $eventType,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':event_data' => $eventData ? json_encode($eventData) : null
        ]);
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Test event created',
            'id' => $conn->lastInsertId()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    // Return a simple success message
    echo json_encode([
        'success' => true,
        'message' => 'Real-time collaboration system is ready',
        'user' => $user
    ]);
}
?>