<?php
require_once '../config.php';
require_once '../includes/db.php';
session_start();
ensureSchema();
if (!empty($_SESSION['admin_id'])) { redirectTo('admin/dashboard.php'); }

function h($s){ return htmlspecialchars($s??'', ENT_QUOTES,'UTF-8'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$login || !$password) {
        $error = 'Заполните все поля.';
    } else {
        try {
            $pdo  = getDb();
            $stmt = $pdo->prepare("SELECT id, password_hash, is_admin, full_name FROM users WHERE (username=? OR email=?) LIMIT 1");
            $stmt->execute([$login, $login]);
            $row  = $stmt->fetch();

            if ($row && password_verify($password, $row['password_hash'])) {
                if ((int)$row['is_admin'] !== 1) {
                    logEvent('warn', 'Попытка входа в админку без прав', "user_id={$row['id']}");
                    $error = 'У этого пользователя нет прав администратора.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['admin_id']   = $row['id'];
                    $_SESSION['admin_name'] = $row['full_name'];
                    logEvent('info', 'Вход в админ-панель', "user_id={$row['id']}");
                    redirectTo('admin/dashboard.php');
                }
            } else {
                logEvent('warn', 'Неверный логин/пароль при входе в админку', "login=$login");
                $error = 'Неверный логин или пароль.';
            }
        } catch (PDOException $e) {
            $error = 'Ошибка базы данных.';
        }
    }
}
?>
<!DOCTYPE html><html lang="ru"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Вход в AdminPanel — Свадебный планировщик</title>
<link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= app_url('admin/assets/admin.css') ?>">
</head><body style="background:linear-gradient(135deg,#2c3e50 0%,#3d5166 100%)">
<div class="auth-wrap">
<div class="auth-card">
  <div class="auth-logo">
    <div class="ring">🔧</div>
    <h1 style="color:#4a6fa5">Admin Panel</h1>
    <p>Свадебный планировщик — панель управления</p>
  </div>
  <?php if ($error): ?>
  <div class="alert alert-error">⚠️ <?= h($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label>Логин или e-mail администратора <span>*</span></label>
      <input class="form-control" name="username" type="text" value="<?= h($_POST['username']??'') ?>" required autofocus>
    </div>
    <div class="form-group">
      <label>Пароль <span>*</span></label>
      <input class="form-control" name="password" type="password" required>
    </div>
    <button class="btn btn-primary" style="width:100%;background:#4a6fa5" type="submit">Войти в админку</button>
  </form>
  <p style="text-align:center;margin-top:1rem;font-size:.8rem;color:#999">
    <a href="<?= app_url('login.php') ?>" style="color:#999">← Обычный вход</a>
  </p>
  <?php
  
  try {
      $pdo = getDb();
      $cnt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin=1")->fetchColumn();
      if ($cnt == 0):
  ?>
  <div class="alert alert-warn" style="margin-top:1rem">
    👋 Ни одного администратора нет.<br>
    Перейдите на <a href="<?= app_url('admin/setup.php') ?>"><strong>admin/setup.php</strong></a> для создания первого.
  </div>
  <?php endif; } catch(PDOException $e){} ?>
</div>
</div>
</body></html>
