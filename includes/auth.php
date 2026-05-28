<?php
function requireAuth(): void {
    if (empty($_SESSION['user_id'])) {
        redirectTo('login.php');
    }
}

function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user === null) {
        $pdo  = getDb();
        $stmt = $pdo->prepare("SELECT id, username, email, full_name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function currentEvent(): ?array {
    if (empty($_SESSION['event_id'])) return null;
    static $event = null;
    if ($event === null) {
        $pdo  = getDb();
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND user_id = ?");
        $stmt->execute([$_SESSION['event_id'], $_SESSION['user_id']]);
        $event = $stmt->fetch() ?: null;
    }
    return $event;
}

function h($s = ''): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($v): string {
    return number_format((float)$v, 0, '.', ' ') . ' ₽';
}

function daysLeft(?string $date): int|null {
    if (!$date) return null;
    $d = new DateTime($date);
    $n = new DateTime('today');
    return (int)$n->diff($d)->format('%r%a');
}
