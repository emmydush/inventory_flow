<?php
require_once 'config/database.php';

session_start();

// Get the requested URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Define the public path
$publicPath = __DIR__ . '/public';

// Check if user is authenticated
$isAuthenticated = isset($_SESSION['user_id']);

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

// Serve the main application
include $publicPath . '/index.html';
?>