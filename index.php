<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

$publicPath = __DIR__ . '/public';
$apiPath = __DIR__ . '/api';
$configPath = __DIR__ . '/config';

if (preg_match('/^\/api\//', $uri)) {
    $file = $apiPath . str_replace('/api', '', $uri);
    if (file_exists($file)) {
        include $file;
        exit;
    }
}

if (preg_match('/^\/config\//', $uri)) {
    $file = $configPath . str_replace('/config', '', $uri);
    if (file_exists($file)) {
        include $file;
        exit;
    }
}

$requestedFile = $publicPath . $uri;

if ($uri !== '/' && file_exists($requestedFile) && is_file($requestedFile)) {
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
}

header('Cache-Control: no-cache, no-store, must-revalidate');
include $publicPath . '/index.html';
?>
