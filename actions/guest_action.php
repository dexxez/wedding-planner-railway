<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
session_start();
requireAuth();

$pdo    = getDb();
$eid    = (int)$_SESSION['event_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function ownGuest(PDO $pdo, int $id, int $eid): bool {
    $s = $pdo->prepare("SELECT id FROM guests WHERE id=? AND event_id=?");
    $s->execute([$id, $eid]); return (bool)$s->fetch();
}
function valid(string $v, array $a): bool { return in_array($v,$a,true); }

$sides = ['bride','groom','mutual'];
$cats  = ['family','friends','colleagues','other'];
$rsvps = ['pending','invited','confirmed','declined'];

if ($action === 'create') {
    $name     = trim($_POST['full_name']     ?? '');
    $email    = trim($_POST['email']         ?? '');
    $phone    = trim($_POST['phone']         ?? '');
    $side     = valid($_POST['side']??'',    $sides) ? $_POST['side']     : 'mutual';
    $cat      = valid($_POST['category']??'',$cats)  ? $_POST['category'] : 'friends';
    $rsvp     = valid($_POST['rsvp_status']??'',$rsvps) ? $_POST['rsvp_status'] : 'pending';
    $diet     = trim($_POST['dietary_notes'] ?? '');
    $transfer = isset($_POST['needs_transfer']) ? 1 : 0;
    $table    = (int)($_POST['table_number'] ?? 0) ?: null;
    if (!$name) { redirectTo('guests.php?error=1'); }
    $pdo->prepare("INSERT INTO guests (event_id,full_name,email,phone,side,category,rsvp_status,dietary_notes,needs_transfer,table_number) VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([$eid,$name,$email,$phone,$side,$cat,$rsvp,$diet,$transfer,$table]);
    redirectTo('guests.php?ok=1');
}
if ($action === 'update') {
    $gid      = (int)($_POST['guest_id']     ?? 0);
    $name     = trim($_POST['full_name']     ?? '');
    $email    = trim($_POST['email']         ?? '');
    $phone    = trim($_POST['phone']         ?? '');
    $side     = valid($_POST['side']??'',    $sides) ? $_POST['side']     : 'mutual';
    $cat      = valid($_POST['category']??'',$cats)  ? $_POST['category'] : 'friends';
    $rsvp     = valid($_POST['rsvp_status']??'',$rsvps) ? $_POST['rsvp_status'] : 'pending';
    $diet     = trim($_POST['dietary_notes'] ?? '');
    $transfer = isset($_POST['needs_transfer']) ? 1 : 0;
    $table    = (int)($_POST['table_number'] ?? 0) ?: null;
    if (!$gid || !$name || !ownGuest($pdo,$gid,$eid)) { redirectTo('guests.php'); }
    $pdo->prepare("UPDATE guests SET full_name=?,email=?,phone=?,side=?,category=?,rsvp_status=?,dietary_notes=?,needs_transfer=?,table_number=? WHERE id=?")
        ->execute([$name,$email,$phone,$side,$cat,$rsvp,$diet,$transfer,$table,$gid]);
    redirectTo('guests.php?ok=1');
}
if ($action === 'rsvp') {
    $gid  = (int)($_POST['guest_id']    ?? 0);
    $rsvp = $_POST['rsvp_status']       ?? '';
    if ($gid && ownGuest($pdo,$gid,$eid) && valid($rsvp,$rsvps))
        $pdo->prepare("UPDATE guests SET rsvp_status=? WHERE id=?")->execute([$rsvp,$gid]);
    redirectTo('guests.php');
}
if ($action === 'delete') {
    $gid = (int)($_GET['guest_id'] ?? 0);
    if ($gid && ownGuest($pdo,$gid,$eid))
        $pdo->prepare("DELETE FROM guests WHERE id=?")->execute([$gid]);
    redirectTo('guests.php?ok=1');
}
redirectTo('guests.php');
