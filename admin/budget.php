<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_layout.php';
session_start(); ensureSchema(); adminRequireAuth();
$pdo = getDb();
$rows = $pdo->query(
    "SELECT bi.*, e.title ev_title, u.username,
     v.company_name vendor_name
     FROM budget_items bi
     JOIN events e ON bi.event_id=e.id
     JOIN users u ON e.user_id=u.id
     LEFT JOIN vendors v ON bi.vendor_id=v.id
     ORDER BY bi.created_at DESC LIMIT 200"
)->fetchAll();

$totPlanned = array_sum(array_column($rows,'planned_amount'));
$totActual  = array_sum(array_column($rows,'actual_amount'));
adminPageHeader('Бюджеты', 'budget');
?>
<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem">
  <div class="stat-card gold"><div class="stat-label">Статей бюджета</div><div class="stat-value"><?= count($rows) ?></div></div>
  <div class="stat-card"><div class="stat-label">Суммарно планово</div><div class="stat-value" style="font-size:1.2rem"><?= money($totPlanned) ?></div></div>
  <div class="stat-card orange"><div class="stat-label">Суммарно факт</div><div class="stat-value" style="font-size:1.2rem"><?= money($totActual) ?></div></div>
</div>
<div class="card">
  <div class="card-header"><h2>💰 Все бюджетные статьи</h2></div>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Категория</th><th>Мероприятие</th><th>Владелец</th><th>Подрядчик</th><th>Планово</th><th>Факт</th><th>Разница</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $diff=(float)$r['actual_amount']-(float)$r['planned_amount']; ?>
    <tr>
      <td><?= h($r['category']) ?><?= $r['description']?'<br><small class="text-muted">'.h($r['description']).'</small>':'' ?></td>
      <td class="text-sm"><?= h($r['ev_title']) ?></td>
      <td class="text-sm text-muted">@<?= h($r['username']) ?></td>
      <td class="text-sm"><?= $r['vendor_name'] ? h($r['vendor_name']) : '—' ?></td>
      <td><?= money($r['planned_amount']) ?></td>
      <td><?= money($r['actual_amount']) ?></td>
      <td class="<?= $diff>0?'text-red':($diff<0?'text-green':'text-muted') ?>"><?= $diff>0?'+':'' ?><?= money($diff) ?></td>
    </tr>
    <?php endforeach; if(empty($rows)): ?>
      <tr><td colspan="7" style="text-align:center;padding:2rem;color:#999">Нет данных</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
<?php adminPageFooter(); ?>
