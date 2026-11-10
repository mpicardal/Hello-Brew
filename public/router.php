<?php
/* Router for PHP's built-in server:  php -S localhost:8000 -t public public/router.php */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
// Never execute or list anything inside uploads/ — serve images only.
if (str_starts_with($path, '/uploads/')) {
    if (!preg_match('#^/uploads/[a-z]+/[a-f0-9]{32}\.(jpg|png)$#', $path) || !is_file(__DIR__ . $path)) {
        http_response_code(404);
        exit('Not found');
    }
    header('Content-Type: ' . (str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg'));
    header('X-Content-Type-Options: nosniff');
    readfile(__DIR__ . $path);
    return true;
}
if (basename($path) === 'router.php' || preg_match('#/\.#', $path)) {
    http_response_code(404);
    exit('Not found');
}
if ($path === '/' || is_dir(__DIR__ . $path)) {
    $index = rtrim($path, '/') . '/index.php';
    if (is_file(__DIR__ . $index)) {
        $_SERVER['SCRIPT_NAME'] = $index;
        require __DIR__ . $index;
        return true;
    }
}
return false;   // let the built-in server serve the file (or 404)
