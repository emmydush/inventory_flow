<?php
$publicPath = __DIR__ . '/public';
$uri = '/js/app.js';  // Changed from auth.js to app.js since we removed auth.js
$requestedFile = $publicPath . str_replace('/', DIRECTORY_SEPARATOR, $uri);
echo "Public path: $publicPath\n";
echo "URI: $uri\n";
echo "Requested file: $requestedFile\n";
echo "File exists: " . (file_exists($requestedFile) ? 'Yes' : 'No') . "\n";
echo "Is file: " . (is_file($requestedFile) ? 'Yes' : 'No') . "\n";
?>