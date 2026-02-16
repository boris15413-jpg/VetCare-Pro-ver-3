<?php
// PHP built-in server router
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$file = __DIR__ . $path;

// Handle /booking/ directory (and /booking/api requests)
if (preg_match('#^/booking(/.*)?$#', $path)) {
    $bookingFile = __DIR__ . '/booking/index.php';
    if (is_file($bookingFile)) {
        require $bookingFile;
        return true;
    }
}

// Handle /api/ direct access
if (preg_match('#^/api/(\w+)\.php#', $path, $m)) {
    $apiFile = __DIR__ . '/api/' . $m[1] . '.php';
    if (is_file($apiFile)) {
        require $apiFile;
        return true;
    }
}

// Serve static files directly
if ($path !== '/' && is_file($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css' => 'text/css',
        'js'  => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg'=> 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff'=> 'font/woff',
        'woff2'=>'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'json'=> 'application/json',
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext] . '; charset=UTF-8');
        readfile($file);
        return true;
    }
    // For PHP files, let PHP handle them
    if ($ext === 'php') {
        return false;
    }
    return false;
}

// Route everything else to index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
