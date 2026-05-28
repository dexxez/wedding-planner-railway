<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_layout.php';
session_start();
ensureSchema();
adminRequireAuth();

$pdo = getDb();


$stats = [
    'users'   => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'events'  => $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'tasks'   => $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn(),
    'done'    => $pdo->query("SELECT COUNT(*) FROM tasks WHERE status='done'")->fetchColumn(),
    'guests'  => $pdo->query("SELECT COUNT(*) FROM guests")->fetchColumn(),
    'confirmed'=> $pdo->query("SELECT COUNT(*) FROM guests WHERE rsvp_status='confirmed'")->fetchColumn(),
    'vendors' => $pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn(),
    'budget_planned' => (float)$pdo->query("SELECT COALESCE(SUM(planned_amount),0) FROM budget_items")->fetchColumn(),
    'budget_actual'  => (float)$pdo->query("SELECT COALESCE(SUM(actual_amount),0) FROM budget_items")->fetchColumn(),
    'errors'  => $pdo->query("SELECT COUNT(*) FROM error_logs WHERE level='error'")->fetchColumn(),
    'warns'   => $pdo->query("SELECT COUNT(*) FROM error_logs WHERE level='warn'")->fetchColumn(),
];


$newUsers = $pdo->query("SELECT id, username, full_name, email, is_admin, created_at FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll();


$newEvents = $pdo->query(
    "SELECT e.*, u.username, u.full_name owner_name,
     (SELECT COUNT(*) FROM tasks WHERE event_id=e.id) task_cnt,
     (SELECT COUNT(*) FROM guests WHERE event_id=e.id) guest_cnt
     FROM events e JOIN users u ON e.user_id=u.id
     ORDER BY e.created_at DESC LIMIT 6"
)->fetchAll();


$recentLogs = $pdo->query("SELECT * FROM error_logs ORDER BY created_at DESC LIMIT 10")->fetchAll();


$growth = $pdo->query(
    "SELECT DATE(created_at) d, COUNT(*) c FROM users
     WHERE created_at >= NOW() - INTERVAL 14 DAY
     GROUP BY DATE(created_at) ORDER BY d"
)->fetchAll(PDO::FETCH_KEY_PAIR);

adminPageHeader('Dashboard', 'dashboard');
?>


<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1rem">
  <div class="stat-card admin-stat">
    <div class="stat-label">Пользователей</div>
    <div class="stat-value"><?= $stats['users'] ?></div>
    <div class="stat-sub"><a href="<?= app_url('admin/users.php') ?>">Управление →</a></div>
  </div>
  <div class="stat-card gold">
    <div class="stat-label">Мероприятий</div>
    <div class="stat-value"><?= $stats['events'] ?></div>
    <div class="stat-sub"><a href="<?= app_url('admin/events.php') ?>">Управление →</a></div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Задач / Выполнено</div>
    <div class="stat-value"><?= $stats['tasks'] ?></div>
    <div class="stat-sub"><?= $stats['done'] ?> выполнено (<?= $stats['tasks']>0 ? round($stats['done']/$stats['tasks']*100) : 0 ?>%)</div>
  </div>
  <div class="stat-card rose">
    <div class="stat-label">Гостей / Подтв.</div>
    <div class="stat-value"><?= $stats['guests'] ?></div>
    <div class="stat-sub"><?= $stats['confirmed'] ?> подтвердили</div>
  </div>
</div>

<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem">
  <div class="stat-card gold">
    <div class="stat-label">Бюджет (план)</div>
    <div class="stat-value" style="font-size:1.3rem"><?= money($stats['budget_planned']) ?></div>
  </div>
  <div class="stat-card orange">
    <div class="stat-label">Бюджет (факт)</div>
    <div class="stat-value" style="font-size:1.3rem"><?= money($stats['budget_actual']) ?></div>
  </div>
  <div class="stat-card <?= $stats['errors']>0?'orange':'' ?>">
    <div class="stat-label">Ошибок / Предупреждений</div>
    <div class="stat-value" style="color:<?= $stats['errors']>0?'var(--red)':'var(--green)' ?>"><?= $stats['errors'] ?></div>
    <div class="stat-sub"><?= $stats['warns'] ?> предупреждений · <a href="<?= app_url('admin/logs.php') ?>">Смотреть →</a></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">


<div class="card">
  <div class="card-header">
    <h2>👥 Пользователи</h2>
    <a href="<?= app_url('admin/users.php') ?>" class="btn btn-outline btn-sm">Все →</a>
  </div>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Пользователь</th><th>Роль</th><th>Зарег.</th></tr></thead>
    <tbody>
    <?php foreach ($newUsers as $u): ?>
    <tr>
      <td>
        <strong><?= h($u['full_name']) ?></strong><br>
        <small class="text-muted">@<?= h($u['username']) ?></small>
      </td>
      <td><?= $u['is_admin'] ? '<span class="badge badge-confirmed">Админ</span>' : '<span class="badge badge-pending">User</span>' ?></td>
      <td class="text-xs text-muted"><?= ago($u['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>


<div class="card">
  <div class="card-header">
    <h2>💍 Мероприятия</h2>
    <a href="<?= app_url('admin/events.php') ?>" class="btn btn-outline btn-sm">Все →</a>
  </div>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Название</th><th>Владелец</th><th>Дата</th><th>Задачи/Гости</th></tr></thead>
    <tbody>
    <?php foreach ($newEvents as $e): ?>
    <tr>
      <td><strong><?= h($e['title']) ?></strong></td>
      <td class="text-sm text-muted">@<?= h($e['username']) ?></td>
      <td class="text-xs" style="white-space:nowrap"><?= date('d.m.Y', strtotime($e['event_date'])) ?></td>
      <td class="text-xs text-muted">✅ <?= $e['task_cnt'] ?> · 👥 <?= $e['guest_cnt'] ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

</div>


<div class="card">
  <div class="card-header">
    <h2>📋 Последние события лога</h2>
    <a href="<?= app_url('admin/logs.php') ?>" class="btn btn-outline btn-sm">Все логи →</a>
  </div>
  <div>
  <?php if (empty($recentLogs)): ?>
    <div class="empty-state" style="padding:1.5rem"><p>Лог пуст</p></div>
  <?php else: ?>
    <?php foreach ($recentLogs as $log): ?>
    <div class="log-entry">
      <span class="log-level <?= h($log['level']) ?>"><?= h($log['level']) ?></span>
      <span class="log-time"><?= date('d.m H:i', strtotime($log['created_at'])) ?></span>
      <span class="log-msg"><?= h($log['message']) ?><?= $log['context'] ? ' <span class="text-muted text-xs">('.h($log['context']).')</span>' : '' ?></span>
      <?php if ($log['url']): ?><span class="text-xs text-muted"><?= h(parse_url($log['url'], PHP_URL_PATH)) ?></span><?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
  </div>
</div>

<?php adminPageFooter(); ?>
