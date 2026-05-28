<?php
require_once __DIR__ . '/../config.php';

function getDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $attempts = [[DB_HOST, DB_PORT]];
    $localHosts = ['localhost', '127.0.0.1', '::1'];
    if (in_array(DB_HOST, $localHosts, true)) {
        foreach (['3306', '3307'] as $p) {
            if ($p !== (string)DB_PORT) $attempts[] = [DB_HOST, $p];
        }
    }

    $lastError = null;
    foreach ($attempts as [$host, $port]) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, DB_NAME);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            $lastError = $e;
        }
    }

    throw $lastError ?: new PDOException('Unknown database connection error');
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensureSchema(): void {
    try {
        $pdo = getDb();
        $existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        if (empty($existing)) {
            $sql = file_get_contents(__DIR__ . '/../schema.sql');
            $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => strlen($s) > 5);
            foreach ($statements as $stmt) {
                try { $pdo->exec($stmt); } catch (PDOException $ex) {  }
            }
        }

        if (tableExists($pdo, 'users') && !columnExists($pdo, 'users', 'is_admin')) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_admin` TINYINT(1) DEFAULT 0");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS `error_logs` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `level`      ENUM('info','warn','error') DEFAULT 'info',
            `message`    TEXT NOT NULL,
            `context`    TEXT,
            `user_id`    INT DEFAULT NULL,
            `ip`         VARCHAR(45),
            `url`        VARCHAR(500),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_level` (`level`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        die('⚠️ Не удалось подключиться к базе данных. Детали: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES));
    }
}

function logEvent(string $level, string $message, ?string $context = null): void {
    try {
        $pdo = getDb();
        $uid = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
        $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
        $url = $_SERVER['REQUEST_URI'] ?? null;
        $pdo->prepare("INSERT INTO error_logs (level,message,context,user_id,ip,url) VALUES (?,?,?,?,?,?)")
            ->execute([$level, $message, $context, $uid, $ip, $url]);
    } catch (PDOException $e) {  }
}
