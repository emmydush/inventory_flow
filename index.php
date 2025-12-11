<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

$publicPath = __DIR__ . '/public';
$apiPath = __DIR__ . '/api';
$configPath = __DIR__ . '/config';

// Handle API requests
if (preg_match('/^\/api\//', $uri)) {
    $file = $apiPath . str_replace('/api', '', $uri);
    if (file_exists($file)) {
        include $file;
        exit;
    }
}

// Handle config requests
if (preg_match('/^\/config\//', $uri)) {
    $file = $configPath . str_replace('/config', '', $uri);
    if (file_exists($file)) {
        include $file;
        exit;
    }
}

// Handle static assets - Always route through this script
$requestedFile = $publicPath . str_replace('/', DIRECTORY_SEPARATOR, $uri);

if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|json)$/', $uri)) {
    if (file_exists($requestedFile) && is_file($requestedFile)) {
        $extension = pathinfo($requestedFile, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'html' => 'text/html',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
        ];
        
        if (isset($mimeTypes[$extension])) {
            header('Content-Type: ' . $mimeTypes[$extension]);
        }
        
        header('Cache-Control: no-cache, no-store, must-revalidate');
        readfile($requestedFile);
        exit;
    } else {
        // File not found
        http_response_code(404);
        echo 'File not found';
        exit;
    }
}

// Serve index.html for all other requests (SPA routing)
header('Cache-Control: no-cache, no-store, must-revalidate');
include $publicPath . '/index.html';
?>
