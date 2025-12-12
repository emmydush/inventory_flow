<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:8000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['username']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Username and password are required']);
    exit;
}

$username = $data['username'];
$password = $data['password'];

try {
    $sql = "SELECT id, username, email, full_name, role, status, password_hash FROM users WHERE (username = :username OR email = :email) LIMIT 1";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed");
    }
    
    $stmt->execute(['username' => $username, 'email' => $username]);
    
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
        exit;
    }
    
    if ($user['status'] !== 'active') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Account is inactive']);
        exit;
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
        exit;
    }
    
    // Note: last_login column not in current schema, skipping update
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];

    // Create user session record
    $session_token = bin2hex(random_bytes(32));
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

    // Create user session record
    $session_token = bin2hex(random_bytes(32));
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

    $session_sql = "INSERT INTO user_sessions
                    (user_id, session_token, ip_address, user_agent, expires_at)
                    VALUES (:user_id, :session_token, :ip_address, :user_agent, :expires_at)";
    $session_stmt = $conn->prepare($session_sql);
    $session_stmt->execute([
        'user_id' => $user['id'],
        'session_token' => $session_token,
        'ip_address' => $ip_address,
        'user_agent' => $user_agent,
        'expires_at' => $expires_at
    ]);

    // Log activity
    $activity_sql = "INSERT INTO activity_logs
                    (user_id, action, ip_address, user_agent)
                    VALUES (:user_id, 'login', :ip_address, :user_agent)";
    $activity_stmt = $conn->prepare($activity_sql);
    $activity_stmt->execute([
        'user_id' => $user['id'],
        'ip_address' => $ip_address,
        'user_agent' => $user_agent
    ]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
}
?>