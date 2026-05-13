<?php
// PHP built-in server router. Serves real files; otherwise hands off to Kirby.
$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // serve the file directly
}
require __DIR__ . '/index.php';
