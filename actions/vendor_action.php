<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
session_start();
requireAuth();

$pdo    = getDb();
$eid    = (int)$_SESSION['event_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function ownVendor(PDO $pdo, int $id, int $eid): bool {
    $s = $pdo->prepare("SELECT id FROM vendors WHERE id=? AND event_id=?");
    $s->execute([$id,$eid]); return (bool)$s->fetch();
}
function valid(string $v, array $a): bool { return in_array($v,$a,true); }

$cats    = ['photo','video','florist','catering','music','decor','transport','beauty','venue','other'];
$statuses= ['considering','booked','deposit_paid','fully_paid','cancelled'];

if ($action === 'create') {
    $name    = trim($_POST['company_name']   ?? '');
    $cat     = valid($_POST['category']??'',$cats) ? $_POST['category'] : 'other';
    $contact = trim($_POST['contact_name']  ?? '');
    $phone   = trim($_POST['phone']         ?? '');
    $email   = trim($_POST['email']         ?? '');
    $website = trim($_POST['website']       ?? '');
    $amount  = max(0,(float)($_POST['contract_amount'] ?? 0));
    $deposit = max(0,(float)($_POST['deposit_paid']    ?? 0));
    $status  = valid($_POST['status']??'',$statuses) ? $_POST['status'] : 'considering';
    $notes   = trim($_POST['notes']         ?? '');
    if (!$name) { redirectTo('vendors.php?error=1'); }
    $pdo->prepare("INSERT INTO vendors (event_id,company_name,category,contact_name,phone,email,website,contract_amount,deposit_paid,status,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$eid,$name,$cat,$contact,$phone,$email,$website,$amount,$deposit,$status,$notes]);
    redirectTo('vendors.php?ok=1');
}
if ($action === 'update') {
    $vid     = (int)($_POST['vendor_id']    ?? 0);
    $name    = trim($_POST['company_name']  ?? '');
    $cat     = valid($_POST['category']??'',$cats) ? $_POST['category'] : 'other';
    $contact = trim($_POST['contact_name']  ?? '');
    $phone   = trim($_POST['phone']         ?? '');
    $email   = trim($_POST['email']         ?? '');
    $website = trim($_POST['website']       ?? '');
    $amount  = max(0,(float)($_POST['contract_amount'] ?? 0));
    $deposit = max(0,(float)($_POST['deposit_paid']    ?? 0));
    $status  = valid($_POST['status']??'',$statuses) ? $_POST['status'] : 'considering';
    $notes   = trim($_POST['notes']         ?? '');
    if (!$vid || !$name || !ownVendor($pdo,$vid,$eid)) { redirectTo('vendors.php'); }
    $pdo->prepare("UPDATE vendors SET company_name=?,category=?,contact_name=?,phone=?,email=?,website=?,contract_amount=?,deposit_paid=?,status=?,notes=? WHERE id=?")
        ->execute([$name,$cat,$contact,$phone,$email,$website,$amount,$deposit,$status,$notes,$vid]);
    redirectTo('vendors.php?ok=1');
}
if ($action === 'delete') {
    $vid = (int)($_GET['vendor_id'] ?? 0);
    if ($vid && ownVendor($pdo,$vid,$eid))
        $pdo->prepare("DELETE FROM vendors WHERE id=?")->execute([$vid]);
    redirectTo('vendors.php?ok=1');
}
redirectTo('vendors.php');
