<?php
// api/index.php — router script for vercel-php's PHP built-in server.
// vercel-php runs `php -S 0.0.0.0:PORT api/index.php`, meaning THIS file
// is invoked for every single request regardless of URL. If it doesn't
// return false, its own output becomes the response for every request —
// which is exactly what made every page on the site run index.php's code.
//
// Fix: if the request maps to a real file in the project (any page like
// register.php, tunnel.php, mentor/index.php, a static asset, etc.),
// return false so PHP's built-in server serves/executes that file itself.
// Only requests that don't match anything (the bare "/") fall through to
// index.php below.
$docroot = __DIR__ . '/..';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = realpath($docroot . $uri);

if ($uri !== '/' && $file !== false && strpos($file, realpath($docroot)) === 0 && is_file($file)) {
    return false; // let the built-in server serve/execute the real file
}

require_once $docroot . '/index.php';
