<?php
/**
 * Router for PHP's built-in development server.
 *
 *   php -S 127.0.0.1:8080 server-router.php
 *
 * It mirrors what the .htaccess rewrite rules do on Apache, so the app can
 * be previewed locally without installing Apache. Not used in production.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

// Mirror the .htaccess deny rules so local testing matches production.
$blocked = ['/app', '/config', '/database', '/routes', '/resources', '/storage', '/docs'];
foreach ($blocked as $prefix) {
    if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
        http_response_code(403);
        exit('Forbidden');
    }
}
if (preg_match('#^/(bootstrap|console|server-router)\.php$#', $path) || preg_match('#(^|/)\.#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

// Uploaded files are never executed.
if (str_starts_with($path, '/public/uploads/') && preg_match('/\.(php\d?|phtml|phar|pl|py|cgi|sh)$/i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

if ($path !== '/' && is_file($file)) {
    return false; // let the built-in server serve the static asset
}

require __DIR__ . '/index.php';
