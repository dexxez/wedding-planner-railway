<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
session_start();
requireAuth();

$pdo    = getDb();
$eid    = (int)$_SESSION['event_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function ownItem(PDO $pdo, int $id, int $eid): bool {
    $s = $pdo->prepare("SELECT id FROM budget_items WHERE id=? AND event_id=?");
    $s->execute([$id, $eid]); return (bool)$s->fetch();
}

if ($action === 'create') {
    $cat      = trim($_POST['category'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $planned  = max(0, (float)($_POST['planned_amount'] ?? 0));
    $actual   = max(0, (float)($_POST['actual_amount']  ?? 0));
    $vendorId = (int)($_POST['vendor_id'] ?? 0) ?: null;
    if (!$cat) { redirectTo('budget.php?error=1'); }
    $pdo->prepare("INSERT INTO budget_items (event_id,category,description,planned_amount,actual_amount,vendor_id) VALUES (?,?,?,?,?,?)")
        ->execute([$eid, $cat, $desc, $planned, $actual, $vendorId]);
    redirectTo('budget.php?ok=1');
}
if ($action === 'update') {
    $iid      = (int)($_POST['item_id'] ?? 0);
    $cat      = trim($_POST['category'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $planned  = max(0, (float)($_POST['planned_amount'] ?? 0));
    $actual   = max(0, (float)($_POST['actual_amount']  ?? 0));
    $vendorId = (int)($_POST['vendor_id'] ?? 0) ?: null;
    if (!$iid || !$cat || !ownItem($pdo,$iid,$eid)) { redirectTo('budget.php'); }
    $pdo->prepare("UPDATE budget_items SET category=?,description=?,planned_amount=?,actual_amount=?,vendor_id=? WHERE id=?")
        ->execute([$cat, $desc, $planned, $actual, $vendorId, $iid]);
    redirectTo('budget.php?ok=1');
}
if ($action === 'set_actual') {
    $iid    = (int)($_POST['item_id'] ?? 0);
    $actual = max(0, (float)($_POST['actual_amount'] ?? 0));
    if ($iid && ownItem($pdo,$iid,$eid))
        $pdo->prepare("UPDATE budget_items SET actual_amount=? WHERE id=?")->execute([$actual, $iid]);
    redirectTo('budget.php?ok=1');
}
if ($action === 'delete') {
    $iid = (int)($_GET['item_id'] ?? 0);
    if ($iid && ownItem($pdo,$iid,$eid))
        $pdo->prepare("DELETE FROM budget_items WHERE id=?")->execute([$iid]);
    redirectTo('budget.php?ok=1');
}
redirectTo('budget.php');
