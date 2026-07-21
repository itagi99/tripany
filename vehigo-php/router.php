<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Let existing PHP API files serve themselves
if (str_starts_with($uri, '/api/') && str_ends_with($uri, '.php')) {
    return false;
}

// Route /api/* (non-PHP) to the REST handler
if (str_starts_with($uri, '/api/')) {
    require __DIR__ . '/api/rest.php';
    return true;
}

// Serve files from vehigo-php/ first
$localPath = __DIR__ . $uri;
if (is_file($localPath)) {
    return false;
}

// Fallback to parent directory (vehigo/) for shared/ and standalone apps
$parentPath = dirname(__DIR__) . $uri;
if (is_file($parentPath)) {
    $ext = pathinfo($parentPath, PATHINFO_EXTENSION);
    $mime = match($ext) {
        'css' => 'text/css',
        'js' => 'application/javascript',
        'html' => 'text/html',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        default => mime_content_type($parentPath) ?: 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    readfile($parentPath);
    return true;
}

// Default: let PHP handle .php files and root
if (str_ends_with($uri, '.php') || $uri === '/' || !pathinfo($uri, PATHINFO_EXTENSION)) {
    return false;
}

http_response_code(404);
echo json_encode(['error' => 'Not found']);
return true;
