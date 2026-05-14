<?php
// PHP built-in server router. Serves real files; otherwise hands off to Kirby.
$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // serve the file directly
}
// PHP's built-in server sets SCRIPT_NAME / PHP_SELF to the requested path when
// falling through to the router for file types it can't serve statically (e.g. .png
// at a URL whose file doesn't exist on disk). That breaks Kirby's base-URL detection.
// Normalize before booting Kirby.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF']    = '/index.php';
require __DIR__ . '/index.php';
