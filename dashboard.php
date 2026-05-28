<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
session_start();
requireAuth();
$catRu = [
    'venue' => 'Площадка',
    'catering' => 'Кейтеринг',
    'decor' => 'Декор',
    'photo' => 'Фотограф',
    'video' => 'Видеограф',
    'music' => 'Музыка',
    'documents' => 'Документы',
    'transport' => 'Транспорт',
    'beauty' => 'Красота',
    'honeymoon' => 'Медовый месяц',
    'florist' => 'Флористика',
    'flowers' => 'Цветы',
    'rings' => 'Кольца',
    'dress' => 'Платье',
    'guests' => 'Гости',
    'family' => 'Семья',
    'friends' => 'Друзья',
    'colleagues' => 'Коллеги',
    'other' => 'Другое'
];

$pdo   = getDb();
$event = currentEvent();

if (!$event) {
    
    redirectTo('settings.php?first=1');
}

$eid = $event['id'];


$tasks     = $pdo->prepare("SELECT status FROM tasks WHERE event_id=?");
$tasks->execute([$eid]); $allTasks = $tasks->fetchAll();
$total = count($allTasks); $done = count(array_filter($allTasks, fn($t)=>$t['status']==='done'));
$taskPct = $total ? round($done/$total*100) : 0;

$budget    = $pdo->prepare("SELECT SUM(planned_amount) p, SUM(actual_amount) a FROM budget_items WHERE event_id=?");
$budget->execute([$eid]); $brow = $budget->fetch();
$planned = (float)($brow['p'] ?? 0); $actual = (float)($brow['a'] ?? 0);
$totalBudget = (float)$event['total_budget'];
$budgetPct = $totalBudget > 0 ? min(round($actual/$totalBudget*100), 100) : 0;
$budgetOver = $actual > $totalBudget && $totalBudget > 0;

$guestStmt = $pdo->prepare("SELECT rsvp_status, COUNT(*) c FROM guests WHERE event_id=? GROUP BY rsvp_status");
$guestStmt->execute([$eid]); $gmap = [];
foreach ($guestStmt->fetchAll() as $g) $gmap[$g['rsvp_status']] = (int)$g['c'];
$gTotal     = array_sum($gmap);
$gConfirmed = $gmap['confirmed'] ?? 0;
$gDeclined  = $gmap['declined']  ?? 0;

$vendStmt = $pdo->prepare("SELECT status, COUNT(*) c FROM vendors WHERE event_id=? GROUP BY status");
$vendStmt->execute([$eid]); $vmap = [];
foreach ($vendStmt->fetchAll() as $v) $vmap[$v['status']] = (int)$v['c'];
$vBooked = ($vmap['booked']??0)+($vmap['deposit_paid']??0)+($vmap['fully_paid']??0);
$vTotal  = array_sum($vmap);

$upcoming = $pdo->prepare("SELECT * FROM tasks WHERE event_id=? AND status!='done' AND due_date IS NOT NULL ORDER BY due_date ASC LIMIT 5");
$upcoming->execute([$eid]); $upcomingTasks = $upcoming->fetchAll();

$days = daysLeft($event['event_date']);

pageHeader('Главная', 'dashboard');
?>

<?php if ($days !== null): ?>
<div class="countdown-banner">
  <div>
    <div class="countdown-label">До вашей свадьбы</div>
    <div class="countdown-num"><?= $days > 0 ? $days : ($days===0?'🎉':'—') ?></div>
    <div class="countdown-sub"><?= $days > 0 ? 'дней' : ($days===0?'Сегодня! Поздравляем!':'Мероприятие прошло') ?></div>
  </div>
  <div style="text-align:right">
    <div style="font-size:1.1rem;opacity:.9">📍 <?= h($event['venue'] ?: 'Место не указано') ?></div>
    <div style="font-size:.9rem;opacity:.8;margin-top:.25rem">📅 <?= date('d.m.Y', strtotime($event['event_date'])) ?></div>
    <div style="font-size:.9rem;opacity:.8">💍 <?= h($event['title']) ?></div>
  </div>
</div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat-card rose">
    <div class="stat-label">Задачи выполнены</div>
    <div class="stat-value"><?= $taskPct ?>%</div>
    <div class="progress-wrap"><div class="progress-bar"><div class="progress-fill" style="width:<?= $taskPct ?>%"></div></div></div>
    <div class="stat-sub"><?= $done ?> из <?= $total ?> задач</div>
  </div>
  <div class="stat-card gold">
    <div class="stat-label">Бюджет потрачено</div>
    <div class="stat-value"><?= $budgetPct ?>%</div>
    <div class="progress-wrap"><div class="progress-bar"><div class="progress-fill gold<?= $budgetOver?' danger':'' ?>" style="width:<?= $budgetPct ?>%"></div></div></div>
    <div class="stat-sub<?= $budgetOver?' text-red':'' ?>"><?= money($actual) ?> из <?= money($totalBudget) ?></div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Гостей подтвердили</div>
    <div class="stat-value"><?= $gConfirmed ?></div>
    <div class="stat-sub">Всего приглашено: <?= $gTotal ?> · Отказало: <?= $gDeclined ?></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-label">Подрядчики забронированы</div>
    <div class="stat-value"><?= $vBooked ?></div>
    <div class="stat-sub">Всего в списке: <?= $vTotal ?></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;flex-wrap:wrap">

<div class="card">
  <div class="card-header"><h2>⏰ Ближайшие задачи</h2><a href="<?= app_url('tasks.php') ?>" class="btn btn-outline btn-sm">Все задачи</a></div>
  <div class="card-body" style="padding:0">
  <?php if (empty($upcomingTasks)): ?>
    <div class="empty-state"><div class="empty-icon">✅</div><p>Все задачи выполнены или не заданы сроки</p></div>
  <?php else: ?>
    <table><thead><tr><th>Задача</th><th>Срок</th><th>Приоритет</th></tr></thead><tbody>
    <?php foreach ($upcomingTasks as $t):
        $d = daysLeft($t['due_date']);
        $overdue = $d !== null && $d < 0;
    ?>
    <tr <?= $overdue?'class="overdue"':'' ?>>
      <td><?= h($t['title']) ?><br><small class="text-muted"><?= $catRu[$t['category']] ?? h($t['category']) ?></small></td>
      <td style="white-space:nowrap">
        <?= date('d.m', strtotime($t['due_date'])) ?>
        <?php if ($d !== null): ?><br><small class="<?= $overdue?'text-red':($d<=3?'text-rose':'text-muted') ?>"><?= $overdue?'просрочено':($d===0?'сегодня':$d.'д.') ?></small><?php endif; ?>
      </td>
      <td><span class="badge badge-<?= h($t['priority']) ?>"><?= ['low'=>'Низкий','medium'=>'Средний','high'=>'Высокий'][$t['priority']]??h($t['priority']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2>💰 Бюджет по категориям</h2><a href="<?= app_url('budget.php') ?>" class="btn btn-outline btn-sm">Весь бюджет</a></div>
  <div class="card-body" style="padding:0">
  <?php
    $cats = $pdo->prepare("SELECT category, SUM(planned_amount) p, SUM(actual_amount) a FROM budget_items WHERE event_id=? GROUP BY category ORDER BY p DESC LIMIT 6");
    $cats->execute([$eid]); $catRows = $cats->fetchAll();
    if (empty($catRows)):
  ?>
    <div class="empty-state"><div class="empty-icon">💰</div><p>Добавьте статьи бюджета</p><a href="<?= app_url('budget.php') ?>" class="btn btn-primary btn-sm">Открыть бюджет</a></div>
  <?php else: ?>
    <table><thead><tr><th>Категория</th><th>Планово</th><th>Факт</th></tr></thead><tbody>
    <?php foreach ($catRows as $c): ?>
    <tr>
      <td><?= $catRu[$c['category']] ?? h($c['category']) ?></td>
      <td><?= money((float)$c['p']) ?></td>
      <td class="<?= (float)$c['a']>(float)$c['p']?'text-red':'text-green' ?>"><?= money((float)$c['a']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
  </div>
</div>

</div>

<?php pageFooter(); ?>
