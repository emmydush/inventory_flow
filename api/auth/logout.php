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

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Log activity
if (isset($_SESSION['user_id']) && $conn) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Log logout activity
        $activity_sql = "INSERT INTO activity_logs
                        (user_id, action, ip_address, user_agent)
                        VALUES (?, 'logout', ?, ?)";
        $activity_stmt = $conn->prepare($activity_sql);
        $activity_stmt->execute([$_SESSION['user_id'], $ip_address, $user_agent]);

        // Mark sessions as inactive
        $session_sql = "UPDATE user_sessions SET is_active = FALSE WHERE user_id = ?";
        $session_stmt = $conn->prepare($session_sql);
        $session_stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        error_log("Error during logout: " . $e->getMessage());
    }
}

session_destroy();

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Logout successful'
]);
