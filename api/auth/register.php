<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Log all headers
$headers = getallheaders();
error_log("Headers: " . print_r($headers, true));

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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

// Debug: Log raw input
$input = file_get_contents('php://input');
error_log("Raw input: " . $input);
error_log("Content length: " . strlen($input));

$data = json_decode($input, true);

// Debug: Log parsed data
error_log("Parsed data: " . print_r($data, true));
error_log("Data is array: " . (is_array($data) ? 'Yes' : 'No'));

$required_fields = ['username', 'email', 'full_name', 'password'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ucfirst($field) . ' is required']);
        exit;
    }
}

$username = $data['username'];
$email = $data['email'];
$full_name = $data['full_name'];
$password = $data['password'];

if (strlen($username) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Username must be at least 3 characters']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
    exit;
}

try {
    // Check if username or email already exists
    $check_sql = "SELECT id FROM users WHERE username = :username OR email = :email";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute(['username' => $username, 'email' => $email]);
    
    if ($check_stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Username or email already exists']);
        exit;
    }
    
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $role = 'cashier';
    $status = 'active';
    
    // Insert new user
    $insert_sql = "INSERT INTO users (username, email, full_name, password_hash, role, status) VALUES (:username, :email, :full_name, :password_hash, :role, :status)";
    $insert_stmt = $conn->prepare($insert_sql);
    $result = $insert_stmt->execute([
        'username' => $username,
        'email' => $email,
        'full_name' => $full_name,
        'password_hash' => $hashed_password,
        'role' => $role,
        'status' => $status
    ]);
    
    if (!$result) {
        throw new Exception("Failed to insert user");
    }
    
    $user_id = $conn->lastInsertId();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful. Please login with your credentials.',
        'user' => [
            'id' => $user_id,
            'username' => $username,
            'email' => $email,
            'full_name' => $full_name,
            'role' => $role
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
}
?>