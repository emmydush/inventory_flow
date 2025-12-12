<?php
require_once 'config/database.php';

session_start();

// Get the requested URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Define the public path
$publicPath = __DIR__ . '/public';

// Check if user is authenticated
$isAuthenticated = isset($_SESSION['user_id']);

// Handle API requests first
if (strpos($uri, '/api/') === 0) {
    // Remove /api/ prefix and construct the file path
    $apiPath = __DIR__ . $uri;
    if (file_exists($apiPath)) {
        include $apiPath;
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'API endpoint not found']);
        exit;
    }
}

// Handle config requests
if (strpos($uri, '/config/') === 0) {
    // Remove /config/ prefix and construct the file path
    $configPath = __DIR__ . $uri;
    if (file_exists($configPath)) {
        include $configPath;
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Config file not found']);
        exit;
    }
}

// Serve static files directly
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|woff|woff2|ttf|eot|svg)$/', $uri)) {
    $filePath = $publicPath . $uri;
    if (file_exists($filePath)) {
        // Set appropriate content type
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        
        if (isset($mimeTypes[$extension])) {
            header('Content-Type: ' . $mimeTypes[$extension]);
        }
        
        readfile($filePath);
        exit;
    }
}

// Handle HTML files specifically
if (preg_match('/\.html$/', $uri) || $uri === '/login.html') {
    $filePath = $publicPath . $uri;
    // If the direct path doesn't exist, try adding /public prefix
    if (!file_exists($filePath) && strpos($uri, '/public/') === false) {
        $filePath = $publicPath . '/' . ltrim($uri, '/');
    }
    
    if (file_exists($filePath)) {
        header('Content-Type: text/html');
        readfile($filePath);
        exit;
    }
}

// Serve the main application for all other routes
include $publicPath . '/index.html';
?>