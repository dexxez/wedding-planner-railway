<?php



$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));


if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    
    $ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
    $mime = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
    ];
    if (isset($mime[$ext])) {
        header('Content-Type: ' . $mime[$ext]);
    }
    return false; 
}


$routes = [
    '/'           => '/index.php',
    '/login'      => '/login.php',
    '/logout'     => '/logout.php',
    '/register'   => '/register.php',
    '/dashboard'  => '/dashboard.php',
    '/tasks'      => '/tasks.php',
    '/budget'     => '/budget.php',
    '/guests'     => '/guests.php',
    '/vendors'    => '/vendors.php',
    '/settings'   => '/settings.php',
    '/admin'      => '/admin/index.php',
    '/admin/'     => '/admin/index.php',
    '/admin/login' => '/admin/login.php',
    '/admin/logout' => '/admin/logout.php',
    '/admin/setup' => '/admin/setup.php',
    '/admin/dashboard' => '/admin/dashboard.php',
    '/admin/users' => '/admin/users.php',
    '/admin/events' => '/admin/events.php',
    '/admin/tasks' => '/admin/tasks.php',
    '/admin/budget' => '/admin/budget.php',
    '/admin/guests' => '/admin/guests.php',
    '/admin/vendors' => '/admin/vendors.php',
    '/admin/logs' => '/admin/logs.php',
    '/admin/settings' => '/admin/settings.php',
];

$path = strtok($uri, '?');

if (isset($routes[$path])) {
    require __DIR__ . $routes[$path];
    return true;
}


if (file_exists(__DIR__ . $uri . '.php')) {
    require __DIR__ . $uri . '.php';
    return true;
}


return false;
