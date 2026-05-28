<?php
// PHP built-in server router. Serves real files; otherwise hands off to Kirby.
$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    // sw.min.js needs Service-Worker-Allowed: / to claim the whole site.
    if (substr($uri, -strlen('/sw.min.js')) === '/sw.min.js') {
        header('Content-Type: application/javascript');
        header('Service-Worker-Allowed: /');
        readfile($file);
        exit;
    }
    return false;
}
// php -S sets SCRIPT_NAME/PHP_SELF to the request URI for unrecognized file
// types when it falls through; reset so Kirby's base-URL detection works.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF']    = '/index.php';
require __DIR__ . '/index.php';
