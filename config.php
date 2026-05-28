<?php
putenv('ADMIN_SECRET=12345');



function env_first(array $names, ?string $default = null): ?string {
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }
    return $default;
}

function parse_database_url(): array {
    $url = env_first(['MYSQL_URL', 'DATABASE_URL', 'JAWSDB_URL', 'CLEARDB_DATABASE_URL']);
    if (!$url) return [];
    $parts = parse_url($url);
    if (!$parts) return [];
    return [
        'host' => $parts['host'] ?? null,
        'port' => isset($parts['port']) ? (string)$parts['port'] : null,
        'name' => isset($parts['path']) ? ltrim($parts['path'], '/') : null,
        'user' => isset($parts['user']) ? urldecode($parts['user']) : null,
        'pass' => isset($parts['pass']) ? urldecode($parts['pass']) : null,
    ];
}

$dbUrl = parse_database_url();

define('DB_HOST', env_first(['MYSQLHOST', 'MYSQL_HOST', 'DB_HOST'], $dbUrl['host'] ?? '127.0.0.1'));
define('DB_PORT', env_first(['MYSQLPORT', 'MYSQL_PORT', 'DB_PORT'], $dbUrl['port'] ?? '3306'));
define('DB_NAME', env_first(['MYSQLDATABASE', 'MYSQL_DATABASE', 'DB_DATABASE', 'DB_NAME'], $dbUrl['name'] ?? 'wedding_planner'));
define('DB_USER', env_first(['MYSQLUSER', 'MYSQL_USER', 'DB_USERNAME', 'DB_USER'], $dbUrl['user'] ?? 'root'));
define('DB_PASS', env_first(['MYSQLPASSWORD', 'MYSQL_PASSWORD', 'DB_PASSWORD', 'DB_PASS'], $dbUrl['pass'] ?? ''));

define('APP_NAME', env_first(['APP_NAME'], 'Свадебный планировщик'));
define('SESSION_SECRET', env_first(['SESSION_SECRET'], 'change_me_32chars_secret_key_here'));

function app_base_path(): string {
    static $base = null;
    if ($base !== null) return $base;

    $envBase = env_first(['BASE_URL', 'APP_BASE_URL', 'APP_BASE_PATH']);
    if ($envBase !== null) {
        $base = '/' . trim($envBase, '/') . '/';
        if ($base === '//') $base = '/';
        return $base;
    }

    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(__DIR__) ?: __DIR__;

    $documentRoot = str_replace('\\', '/', rtrim($documentRoot, '/'));
    $appRoot = str_replace('\\', '/', rtrim($appRoot, '/'));

    if ($documentRoot && str_starts_with($appRoot, $documentRoot)) {
        $relative = trim(substr($appRoot, strlen($documentRoot)), '/');
        $base = $relative === '' ? '/' : '/' . $relative . '/';
    } else {
        $base = '/';
    }
    return $base;
}

function app_url(string $path = ''): string {
    $path = ltrim($path, '/');
    return app_base_path() . $path;
}

function redirectTo(string $path): never {
    header('Location: ' . app_url($path));
    exit;
}

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', '86400');
ini_set('session.cookie_samesite', 'Lax');

if (getenv('APP_ENV') === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
