<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    getSessions($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    createSession($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    deleteSession($conn);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function getSessions($conn) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $_SESSION['user_id'];

    try {
        $sql = "SELECT 
                    id,
                    user_id,
                    ip_address,
                    user_agent,
                    last_activity,
                    is_active,
                    created_at,
                    DATE_ADD(expires_at, INTERVAL 0 HOUR) as expires_at
                FROM user_sessions
                WHERE user_id = ? AND is_active = TRUE
                ORDER BY last_activity DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id]);
        $sessions = $stmt->fetchAll();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $sessions
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createSession($conn) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        $user_id = $_SESSION['user_id'];
        $session_token = bin2hex(random_bytes(32));
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

        $sql = "INSERT INTO user_sessions 
                (user_id, session_token, ip_address, user_agent, expires_at) 
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $user_id,
            $session_token,
            $ip_address,
            $user_agent,
            $expires_at
        ]);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'session_token' => $session_token,
            'expires_at' => $expires_at
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteSession($conn) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $session_id = $data['session_id'] ?? null;

        if (!$session_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Session ID is required']);
            exit;
        }

        $sql = "UPDATE user_sessions SET is_active = FALSE WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$session_id, $_SESSION['user_id']]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Session terminated']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
