<?php
require_once '../config.php';
session_start();
unset($_SESSION['admin_id']);

redirectTo('admin/login.php');
