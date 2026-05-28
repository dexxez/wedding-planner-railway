<?php
require_once 'config.php';
require_once 'includes/db.php';
session_start();
ensureSchema();
if (!empty($_SESSION['user_id'])) {
    redirectTo('dashboard.php');
}
redirectTo('login.php');
