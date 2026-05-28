<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
session_start();
requireAuth();
$pdo  = getDb();
$user = currentUser();
$event = currentEvent();

$flash = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'event') {
        $title  = trim($_POST['title'] ?? '');
        $date   = $_POST['event_date'] ?? '';
        $venue  = trim($_POST['venue'] ?? '');
        $budget = (float)($_POST['total_budget'] ?? 0);
        $p1     = trim($_POST['partner1'] ?? '');
        $p2     = trim($_POST['partner2'] ?? '');
        $status = $_POST['status'] ?? 'planning';
        if (!$title || !$date) { $error = 'Заполните название и дату.'; }
        else {
            if ($event) {
                $s = $pdo->prepare("UPDATE events SET title=?,event_date=?,venue=?,total_budget=?,partner1=?,partner2=?,status=? WHERE id=? AND user_id=?");
                $s->execute([$title,$date,$venue,$budget,$p1,$p2,$status,$event['id'],$user['id']]);
                
                $_SESSION['event_cache_bust'] = time();
                $flash = 'Мероприятие обновлено.';
            } else {
                $s = $pdo->prepare("INSERT INTO events (user_id,title,event_date,venue,total_budget,partner1,partner2,status) VALUES (?,?,?,?,?,?,?,?)");
                $s->execute([$user['id'],$title,$date,$venue,$budget,$p1,$p2,$status]);
                $_SESSION['event_id'] = (int)$pdo->lastInsertId();
                $flash = 'Мероприятие создано!';
            }
            redirectTo('settings.php?ok=1');
        }
    } elseif ($act === 'password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $rep = $_POST['repeat_password'] ?? '';
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id=?");
        $stmt->execute([$user['id']]); $row = $stmt->fetch();
        if (!password_verify($cur, $row['password_hash'])) { $error = 'Неверный текущий пароль.'; }
        elseif ($new !== $rep) { $error = 'Новые пароли не совпадают.'; }
        elseif (strlen($new) < 6) { $error = 'Минимум 6 символов.'; }
        else {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT),$user['id']]);
            $flash = 'Пароль изменён.';
        }
    }
}
if (isset($_GET['ok'])) $flash = 'Сохранено.';


$evStmt = $pdo->prepare("SELECT * FROM events WHERE id=? AND user_id=?");
$evStmt->execute([$_SESSION['event_id'] ?? 0, $user['id']]);
$event = $evStmt->fetch() ?: null;

$first = isset($_GET['first']);
pageHeader('Настройки', 'settings');
?>
<?php if ($flash): ?><div class="alert alert-success" data-auto>✅ <?= h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error">⚠️ <?= h($error) ?></div><?php endif; ?>
<?php if ($first): ?><div class="alert alert-info">👋 Добро пожаловать! Заполните информацию о вашей свадьбе.</div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

<div class="card">
  <div class="card-header"><h2>💍 Мероприятие</h2></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="action" value="event">
      <div class="form-group"><label>Название <span>*</span></label><input class="form-control" name="title" value="<?= h($event['title']??'') ?>" placeholder="Свадьба Анны и Ивана" required></div>
      <div class="form-row col2">
        <div class="form-group"><label>Имя партнёра 1</label><input class="form-control" name="partner1" value="<?= h($event['partner1']??'') ?>"></div>
        <div class="form-group"><label>Имя партнёра 2</label><input class="form-control" name="partner2" value="<?= h($event['partner2']??'') ?>"></div>
      </div>
      <div class="form-row col2">
        <div class="form-group"><label>Дата свадьбы <span>*</span></label><input class="form-control" name="event_date" type="date" value="<?= h($event['event_date']??'') ?>" required></div>
        <div class="form-group"><label>Статус</label>
          <select class="form-control" name="status">
            <option value="planning" <?= ($event['status']??'')==='planning'?'selected':'' ?>>Планирование</option>
            <option value="confirmed" <?= ($event['status']??'')==='confirmed'?'selected':'' ?>>Подтверждено</option>
            <option value="completed" <?= ($event['status']??'')==='completed'?'selected':'' ?>>Завершено</option>
          </select>
        </div>
      </div>
      <div class="form-group"><label>Место проведения</label><input class="form-control" name="venue" value="<?= h($event['venue']??'') ?>" placeholder="Название площадки, адрес"></div>
      <div class="form-group"><label>Общий бюджет (₽)</label><input class="form-control" name="total_budget" type="number" min="0" step="1000" value="<?= h($event['total_budget']??'0') ?>"></div>
      <button class="btn btn-primary" type="submit">💾 Сохранить</button>
    </form>
  </div>
</div>

<div>
  <div class="card" style="margin-bottom:1.5rem">
    <div class="card-header"><h2>👤 Мой аккаунт</h2></div>
    <div class="card-body">
      <p class="text-sm text-muted" style="margin-bottom:.75rem">Логин: <strong><?= h($user['username']) ?></strong> · E-mail: <strong><?= h($user['email']) ?></strong></p>
      <p class="text-sm">ФИО: <strong><?= h($user['full_name']) ?></strong></p>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2>🔒 Смена пароля</h2></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="password">
        <div class="form-group"><label>Текущий пароль</label><input class="form-control" name="current_password" type="password" required></div>
        <div class="form-group"><label>Новый пароль</label><input class="form-control" name="new_password" type="password" required></div>
        <div class="form-group"><label>Повторите новый</label><input class="form-control" name="repeat_password" type="password" required></div>
        <button class="btn btn-gold" type="submit">🔒 Изменить пароль</button>
      </form>
    </div>
  </div>
</div>

</div>
<?php pageFooter(); ?>
