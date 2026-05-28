<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
session_start();
requireAuth();

$pdo    = getDb();
$eid    = (int)$_SESSION['event_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function ownTask(PDO $pdo, int $id, int $eid): bool {
    $s = $pdo->prepare("SELECT id FROM tasks WHERE id=? AND event_id=?");
    $s->execute([$id, $eid]); return (bool)$s->fetch();
}
function valid(string $v, array $a): bool { return in_array($v, $a, true); }

$cats  = ['venue','catering','decor','photo','music','documents','transport','beauty','honeymoon','other'];
$catRu = ['venue'=>'Площадка','catering'=>'Кейтеринг','decor'=>'Декор','photo'=>'Фото/Видео','music'=>'Музыка','documents'=>'Документы','transport'=>'Транспорт','beauty'=>'Красота','honeymoon'=>'Медовый месяц','other'=>'Прочее'];
$pris  = ['low','medium','high'];
$stats = ['not_started','in_progress','done'];

if ($action === 'create') {
    $title    = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? '';
    $due_date = $_POST['due_date'] ?: null;
    $priority = valid($_POST['priority']??'', $pris) ? $_POST['priority'] : 'medium';
    $status   = valid($_POST['status']??'',   $stats)? $_POST['status']   : 'not_started';
    $notes    = trim($_POST['notes'] ?? '');
    if (!$title || !valid($category, $cats)) { redirectTo('tasks.php?error=1'); }
    $pdo->prepare("INSERT INTO tasks (event_id,title,category,due_date,priority,status,notes) VALUES (?,?,?,?,?,?,?)")
        ->execute([$eid, $title, $category, $due_date, $priority, $status, $notes]);
    redirectTo('tasks.php?ok=add');
}
if ($action === 'update') {
    $tid  = (int)($_POST['task_id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? '';
    $due_date = $_POST['due_date'] ?: null;
    $priority = valid($_POST['priority']??'', $pris) ? $_POST['priority'] : 'medium';
    $status   = valid($_POST['status']??'',   $stats)? $_POST['status']   : 'not_started';
    $notes    = trim($_POST['notes'] ?? '');
    if (!$tid || !$title || !ownTask($pdo,$tid,$eid)) { redirectTo('tasks.php'); }
    $pdo->prepare("UPDATE tasks SET title=?,category=?,due_date=?,priority=?,status=?,notes=? WHERE id=?")
        ->execute([$title, $category, $due_date, $priority, $status, $notes, $tid]);
    redirectTo('tasks.php?ok=updated');
}
if ($action === 'status') {
    $tid    = (int)($_POST['task_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($tid && ownTask($pdo,$tid,$eid) && valid($status,$stats))
        $pdo->prepare("UPDATE tasks SET status=? WHERE id=?")->execute([$status, $tid]);
    redirectTo('tasks.php');
}
if ($action === 'delete') {
    $tid = (int)($_GET['task_id'] ?? 0);
    if ($tid && ownTask($pdo,$tid,$eid))
        $pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([$tid]);
    redirectTo('tasks.php?ok=del');
}
redirectTo('tasks.php');
