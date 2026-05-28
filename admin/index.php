<?php
require_once '../config.php';
require_once '../includes/db.php';
session_start();
ensureSchema();
if (!empty($_SESSION['admin_id'])) {
    redirectTo('admin/dashboard.php');
}
redirectTo('admin/login.php');
