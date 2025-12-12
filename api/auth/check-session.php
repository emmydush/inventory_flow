<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:8000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Also allow same-origin requests
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Debug information
error_log("Check session called. Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));

if (isset($_SESSION['user_id'])) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role'],
            'organization_id' => $_SESSION['organization_id'] ?? null,
            'organization_name' => $_SESSION['organization_name'] ?? null
        ]
    ]);
} else {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'authenticated' => false
    ]);
}
?>