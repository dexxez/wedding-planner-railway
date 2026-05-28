<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_layout.php';
session_start(); ensureSchema(); adminRequireAuth();
$pdo = getDb(); $me = adminCurrentUser();

$flash = $error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'change_password') {
        $cur = $_POST['current'] ?? '';
        $new = $_POST['new']     ?? '';
        $rep = $_POST['repeat']  ?? '';
        $row = $pdo->prepare("SELECT password_hash FROM users WHERE id=?");
        $row->execute([$me['id']]); $row=$row->fetch();
        if (!password_verify($cur,$row['password_hash'])) { $error='Неверный текущий пароль.'; }
        elseif ($new!==$rep) { $error='Новые пароли не совпадают.'; }
        elseif (strlen($new)<6) { $error='Пароль минимум 6 символов.'; }
        else {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT),$me['id']]);
            logEvent('info','Администратор сменил свой пароль',"admin #{$me['id']}");
            $flash='Пароль изменён.';
        }
    }
    if (!$error) redirectTo('admin/settings.php?ok=' . urlencode($flash));
}
if(isset($_GET['ok'])) $flash=$_GET['ok'];


$sysInfo = [
    'PHP'          => PHP_VERSION,
    'Сервер'       => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP Built-in',
    'Пользователей'=> $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'Мероприятий'  => $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'Задач'        => $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn(),
    'Гостей'       => $pdo->query("SELECT COUNT(*) FROM guests")->fetchColumn(),
    'Подрядчиков'  => $pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn(),
    'Записей в лог'=> $pdo->query("SELECT COUNT(*) FROM error_logs")->fetchColumn(),
    'БД хост'      => DB_HOST . ':' . DB_PORT,
    'БД имя'       => DB_NAME,
    'Среда'        => getenv('APP_ENV') ?: 'development',
    'ADMIN_SECRET' => getenv('ADMIN_SECRET') ? '✅ Задан' : '❌ Не задан',
    'SESSION_SECRET'=> strlen(SESSION_SECRET)>=32 ? '✅ Надёжный' : '⚠️ Короткий',
];

adminPageHeader('Настройки','settings');
?>
<?php if($flash): ?><div class="alert alert-success" data-auto>✅ <?= h($flash) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-error">⚠️ <?= h($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

<div class="card">
  <div class="card-header"><h2>🔒 Мой пароль</h2></div>
  <div class="card-body">
    <p class="text-muted text-sm" style="margin-bottom:1rem">Изменить пароль учётной записи <strong>@<?= h($me['username']) ?></strong></p>
    <form method="POST">
      <input type="hidden" name="action" value="change_password">
      <div class="form-group"><label>Текущий пароль</label><input class="form-control" name="current" type="password" required></div>
      <div class="form-group"><label>Новый пароль</label><input class="form-control" name="new" type="password" required></div>
      <div class="form-group"><label>Повторите</label><input class="form-control" name="repeat" type="password" required></div>
      <button class="btn btn-primary" type="submit">🔒 Изменить пароль</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2>🖥️ Системная информация</h2></div>
  <div class="card-body" style="padding:0">
  <table>
    <tbody>
    <?php foreach($sysInfo as $k=>$v): ?>
    <tr>
      <td style="padding:.65rem 1rem;font-weight:600;white-space:nowrap;background:var(--cream)"><?= h($k) ?></td>
      <td style="padding:.65rem 1rem"><?= h((string)$v) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

</div>

<div class="card" style="margin-top:1.5rem">
  <div class="card-header"><h2>⚙️ Переменные окружения Railway</h2></div>
  <div class="card-body">
    <p class="text-muted text-sm" style="margin-bottom:1rem">
      Управляйте этими переменными в Railway → ваш сервис → Variables
    </p>
    <table>
      <thead><tr><th>Переменная</th><th>Назначение</th><th>Статус</th></tr></thead>
      <tbody>
        <tr><td><code>MYSQLHOST</code></td><td>Хост MySQL (авто от плагина)</td><td class="text-green">✅ Авто</td></tr>
        <tr><td><code>MYSQLDATABASE</code></td><td>Имя БД (авто)</td><td class="text-green">✅ Авто</td></tr>
        <tr><td><code>MYSQLUSER</code></td><td>Пользователь БД (авто)</td><td class="text-green">✅ Авто</td></tr>
        <tr><td><code>MYSQLPASSWORD</code></td><td>Пароль БД (авто)</td><td class="text-green">✅ Авто</td></tr>
        <tr><td><code>SESSION_SECRET</code></td><td>Секрет сессий (≥32 символа)</td><td><?= strlen(SESSION_SECRET)>=32?'<span class="text-green">✅ Задан</span>':'<span class="text-red">❌ Задайте!</span>' ?></td></tr>
        <tr><td><code>ADMIN_SECRET</code></td><td>Секрет для /admin/setup.php</td><td><?= getenv('ADMIN_SECRET')?'<span class="text-green">✅ Задан</span>':'<span class="text-red">❌ Задайте!</span>' ?></td></tr>
        <tr><td><code>APP_ENV</code></td><td>Среда: production / development</td><td><?= getenv('APP_ENV')?'<span class="text-green">✅ '.h(getenv('APP_ENV')).'</span>':'<span class="text-muted">не задан (dev)</span>' ?></td></tr>
      </tbody>
    </table>
  </div>
</div>

<?php adminPageFooter(); ?>
