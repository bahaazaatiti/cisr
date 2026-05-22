<?php
// PHP built-in server router. Serves real files; otherwise hands off to Kirby.
$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    // Serve the WebTorrent service worker ourselves so we can set
    // Service-Worker-Allowed: / — lets it control the whole site, not just
    // its own directory. (PHP's built-in server doesn't invoke this router
    // for static files, so a `header()` call there would be a no-op.)
    if (substr($uri, -strlen('/sw.min.js')) === '/sw.min.js') {
        header('Content-Type: application/javascript');
        header('Service-Worker-Allowed: /');
        readfile($file);
        exit;
    }
    return false; // serve the file directly
}
// PHP's built-in server sets SCRIPT_NAME / PHP_SELF to the requested path when
// falling through to the router for file types it can't serve statically (e.g. .png
// at a URL whose file doesn't exist on disk). That breaks Kirby's base-URL detection.
// Normalize before booting Kirby.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF']    = '/index.php';
require __DIR__ . '/index.php';
