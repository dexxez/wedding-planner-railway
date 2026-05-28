<?php
require_once 'config.php';
require_once 'includes/db.php';
session_start();
ensureSchema();
if (!empty($_SESSION['user_id'])) { redirectTo('dashboard.php'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        try {
            $pdo  = getDb();
            $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $username]);
            $row = $stmt->fetch();
            if ($row && password_verify($password, $row['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $row['id'];
                
                $ev = $pdo->prepare("SELECT id FROM events WHERE user_id = ? ORDER BY created_at ASC LIMIT 1");
                $ev->execute([$row['id']]);
                $evRow = $ev->fetch();
                if ($evRow) $_SESSION['event_id'] = $evRow['id'];
                redirectTo('dashboard.php');
            }
        } catch (PDOException $e) {}
        $error = 'Неверный логин или пароль.';
    } else {
        $error = 'Заполните все поля.';
    }
}
function h($s){ return htmlspecialchars($s, ENT_QUOTES,'UTF-8'); }
?>
<!DOCTYPE html><html lang="ru"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Вход — Свадебный планировщик</title>
<link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💍</text></svg>">
</head><body>
<div class="auth-wrap">
<div class="auth-card">
  <div class="auth-logo"><div class="ring">💍</div><h1>Свадебный планировщик</h1><p>Организуй идеальную свадьбу</p></div>
  <?php if ($error): ?><div class="alert alert-error">⚠️ <?= h($error) ?></div><?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label>Логин или e-mail <span>*</span></label>
      <input class="form-control" name="username" type="text" value="<?= h($_POST['username'] ?? '') ?>" required autofocus>
    </div>
    <div class="form-group">
      <label>Пароль <span>*</span></label>
      <input class="form-control" name="password" type="password" required>
    </div>
    <button class="btn btn-primary" style="width:100%" type="submit">Войти</button>
  </form>
  <p style="text-align:center;margin-top:1.25rem;font-size:.875rem;color:#666">
    Нет аккаунта? <a href="<?= app_url('register.php') ?>">Зарегистрироваться</a>
  </p>
</div>
</div>
</body></html>
