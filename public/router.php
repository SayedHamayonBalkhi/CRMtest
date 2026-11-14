<?php
// Router for PHP's built-in server only (php -S localhost:8000 -t public public/router.php). Not used on a normal host.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(customers|jobs|reports|login|logout|setup)/?$#', $path, $m)) {
    require __DIR__ . '/' . $m[1] . '.php';
    return true;
}
return false;
