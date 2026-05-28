<?php
require_once 'config.php';
require_once 'includes/db.php';
session_start();
ensureSchema();
if (!empty($_SESSION['user_id'])) { redirectTo('dashboard.php'); }

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $password2 = $_POST['password2']      ?? '';
    $ev_title  = trim($_POST['ev_title']  ?? '');
    $ev_date   = $_POST['ev_date']        ?? '';
    $ev_budget = (float)($_POST['ev_budget'] ?? 0);

    if (!$full_name || !$username || !$email || !$password || !$ev_title || !$ev_date) {
        $error = 'Заполните все обязательные поля.';
    } elseif ($password !== $password2) {
        $error = 'Пароли не совпадают.';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен быть не менее 6 символов.';
    } else {
        try {
            $pdo  = getDb();
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username,password_hash,email,full_name) VALUES (?,?,?,?)");
            $stmt->execute([$username, $hash, $email, $full_name]);
            $uid  = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO events (user_id,title,event_date,total_budget) VALUES (?,?,?,?)");
            $stmt->execute([$uid, $ev_title, $ev_date, $ev_budget]);
            $evid = (int)$pdo->lastInsertId();
            
            session_regenerate_id(true);
            $_SESSION['user_id']  = $uid;
            $_SESSION['event_id'] = $evid;
            redirectTo('dashboard.php');
        } catch (PDOException $e) {
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Такой логин или e-mail уже занят.' : 'Ошибка регистрации.';
        }
    }
}
function h($s){ return htmlspecialchars($s, ENT_QUOTES,'UTF-8'); }
?>
<!DOCTYPE html><html lang="ru"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Регистрация — Свадебный планировщик</title>
<link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💍</text></svg>">
</head><body>
<div class="auth-wrap">
<div class="auth-card" style="max-width:500px">
  <div class="auth-logo"><div class="ring">💍</div><h1>Свадебный планировщик</h1><p>Создайте аккаунт и начните планирование</p></div>
  <?php if ($error): ?><div class="alert alert-error">⚠️ <?= h($error) ?></div><?php endif; ?>
  <form method="POST">
    <p style="font-weight:700;margin-bottom:.75rem;font-size:.9rem">👤 Ваши данные</p>
    <div class="form-row col2">
      <div class="form-group">
        <label>Имя <span>*</span></label>
        <input class="form-control" name="full_name" value="<?= h($_POST['full_name']??'') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label>Логин <span>*</span></label>
        <input class="form-control" name="username" value="<?= h($_POST['username']??'') ?>" required>
      </div>
    </div>
    <div class="form-group">
      <label>E-mail <span>*</span></label>
      <input class="form-control" name="email" type="email" value="<?= h($_POST['email']??'') ?>" required>
    </div>
    <div class="form-row col2">
      <div class="form-group">
        <label>Пароль <span>*</span></label>
        <input class="form-control" name="password" type="password" required>
      </div>
      <div class="form-group">
        <label>Повторите пароль <span>*</span></label>
        <input class="form-control" name="password2" type="password" required>
      </div>
    </div>
    <hr class="divider">
    <p style="font-weight:700;margin-bottom:.75rem;font-size:.9rem">💍 Информация о свадьбе</p>
    <div class="form-group">
      <label>Название мероприятия <span>*</span></label>
      <input class="form-control" name="ev_title" placeholder="Напр.: Свадьба Анны и Ивана" value="<?= h($_POST['ev_title']??'') ?>" required>
    </div>
    <div class="form-row col2">
      <div class="form-group">
        <label>Дата свадьбы <span>*</span></label>
        <input class="form-control" name="ev_date" type="date" value="<?= h($_POST['ev_date']??'') ?>" required>
      </div>
      <div class="form-group">
        <label>Бюджет (₽)</label>
        <input class="form-control" name="ev_budget" type="number" min="0" placeholder="1 000 000" value="<?= h($_POST['ev_budget']??'') ?>">
      </div>
    </div>
    <button class="btn btn-primary" style="width:100%;margin-top:.5rem" type="submit">Создать аккаунт</button>
  </form>
  <p style="text-align:center;margin-top:1rem;font-size:.875rem;color:#666">
    Уже есть аккаунт? <a href="login.php">Войти</a>
  </p>
</div></div>
</body></html>
