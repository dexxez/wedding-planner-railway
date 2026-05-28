<?php



function adminRequireAuth(): void {
    if (empty($_SESSION['admin_id'])) {
        redirectTo('admin/login.php');
    }
}

function adminCurrentUser(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    static $admin = null;
    if ($admin === null) {
        $pdo  = getDb();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? AND is_admin=1 LIMIT 1");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch() ?: null;
        if (!$admin) {
            
            session_destroy();
            redirectTo('admin/login.php');
        }
    }
    return $admin;
}

if (!function_exists('h')) {
    function h(string $s = ''): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('money')) {
    function money(float $v): string {
        return number_format($v, 0, '.', ' ') . ' ₽';
    }
}

if (!function_exists('ago')) {
    function ago(string $dt): string {
        $diff = time() - strtotime($dt);
        if ($diff < 60) return 'только что';
        if ($diff < 3600) return floor($diff / 60) . ' мин. назад';
        if ($diff < 86400) return floor($diff / 3600) . ' ч. назад';
        if ($diff < 604800) return floor($diff / 86400) . ' дн. назад';
        return date('d.m.Y', strtotime($dt));
    }
}
