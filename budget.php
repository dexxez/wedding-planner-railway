<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
session_start();
requireAuth();

$pdo = getDb(); $event = currentEvent();
if (!$event) { redirectTo('settings.php'); }
$eid = $event['id'];

$items = $pdo->prepare("SELECT bi.*, v.company_name vendor_name FROM budget_items bi LEFT JOIN vendors v ON bi.vendor_id=v.id WHERE bi.event_id=? ORDER BY bi.created_at ASC");
$items->execute([$eid]); $items = $items->fetchAll();

$vendors = $pdo->prepare("SELECT id, company_name FROM vendors WHERE event_id=? ORDER BY company_name");
$vendors->execute([$eid]); $vendors = $vendors->fetchAll();

$totalPlanned = array_sum(array_column($items,'planned_amount'));
$totalActual  = array_sum(array_column($items,'actual_amount'));
$totalBudget  = (float)$event['total_budget'];
$remaining    = $totalBudget - $totalActual;
$budgetPct    = $totalBudget > 0 ? min(round($totalActual/$totalBudget*100),100) : 0;
$over         = $totalActual > $totalBudget && $totalBudget > 0;

$flash = $_GET['ok'] ?? '';
pageHeader('Бюджет', 'budget');
?>
<?php if ($flash): ?><div class="alert alert-success" data-auto>✅ Сохранено</div><?php endif; ?>
<?php if ($over): ?><div class="alert alert-warn">⚠️ Превышен общий бюджет мероприятия!</div><?php endif; ?>

<div class="stat-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card gold">
    <div class="stat-label">Общий бюджет</div>
    <div class="stat-value" style="font-size:1.3rem"><?= money($totalBudget) ?></div>
    <div class="stat-sub"><a href="<?= app_url('settings.php') ?>" style="font-size:.78rem">Изменить</a></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Запланировано</div>
    <div class="stat-value" style="font-size:1.3rem"><?= money($totalPlanned) ?></div>
    <div class="stat-sub">По статьям</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-label">Потрачено факт</div>
    <div class="stat-value" style="font-size:1.3rem"><?= money($totalActual) ?></div>
    <div class="progress-wrap"><div class="progress-bar"><div class="progress-fill gold<?= $over?' danger':'' ?>" style="width:<?= $budgetPct ?>%"></div></div></div>
    <div class="stat-sub"><?= $budgetPct ?>% от бюджета</div>
  </div>
  <div class="stat-card <?= $remaining < 0 ? 'orange' : 'green' ?>">
    <div class="stat-label">Остаток</div>
    <div class="stat-value" style="font-size:1.3rem;color:<?= $remaining<0?'var(--red)':'var(--green)' ?>"><?= money(abs($remaining)) ?></div>
    <div class="stat-sub"><?= $remaining < 0 ? '⚠️ Перерасход!' : 'Свободно' ?></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>💰 Статьи бюджета</h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('addItem')">+ Добавить статью</button>
  </div>
  <div class="table-wrap">
  <?php if (empty($items)): ?>
    <div class="empty-state"><div class="empty-icon">💰</div><p>Добавьте первую статью бюджета</p></div>
  <?php else: ?>
    <table>
      <thead><tr><th>Категория</th><th>Описание</th><th>Подрядчик</th><th>Плановая сумма</th><th>Факт (оплачено)</th><th>Разница</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $it):
          $diff = (float)$it['actual_amount'] - (float)$it['planned_amount'];
      ?>
      <tr>
        <td><strong><?= h($it['category']) ?></strong></td>
        <td><?= h($it['description']) ?></td>
        <td class="text-muted text-sm"><?= $it['vendor_name'] ? h($it['vendor_name']) : '—' ?></td>
        <td><?= money($it['planned_amount']) ?></td>
        <td>
          <div class="inline-edit">
            <input type="number" id="actual_<?= $it['id'] ?>" value="<?= (float)$it['actual_amount'] ?>" min="0" step="100">
            <button class="btn btn-success btn-xs save-actual" data-id="<?= $it['id'] ?>">💾</button>
          </div>
        </td>
        <td class="<?= $diff>0?'text-red':($diff<0?'text-green':'text-muted') ?>">
          <?= $diff > 0 ? '+' : '' ?><?= money($diff) ?>
        </td>
        <td>
          <button class="btn btn-ghost btn-xs" onclick="editItem(<?= htmlspecialchars(json_encode($it),ENT_QUOTES) ?>)">✏️</button>
          <a href="<?= app_url('actions/budget_action.php') ?>?action=delete&item_id=<?= $it['id'] ?>" class="btn btn-ghost btn-xs text-red" data-confirm="Удалить статью?">🗑️</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:var(--cream);font-weight:700">
          <td colspan="3">ИТОГО</td>
          <td><?= money($totalPlanned) ?></td>
          <td><?= money($totalActual) ?></td>
          <td class="<?= ($totalActual-$totalPlanned)>0?'text-red':'text-green' ?>"><?= money($totalActual-$totalPlanned) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  <?php endif; ?>
  </div>
</div>


<div class="modal-backdrop" id="addItem">
  <div class="modal">
    <div class="modal-header"><h3>+ Новая статья бюджета</h3><button class="btn btn-ghost btn-icon" onclick="closeModal('addItem')">✕</button></div>
    <form method="POST" action="<?= app_url('actions/budget_action.php') ?>">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-group"><label>Категория <span>*</span></label><input class="form-control" name="category" placeholder="Напр.: Флористика" required autofocus></div>
        <div class="form-group"><label>Описание</label><input class="form-control" name="description" placeholder="Дополнительное описание"></div>
        <div class="form-row col2">
          <div class="form-group"><label>Планируемая сумма <span>*</span></label><input class="form-control" name="planned_amount" type="number" min="0" step="100" required></div>
          <div class="form-group"><label>Оплачено факт</label><input class="form-control" name="actual_amount" type="number" min="0" step="100" value="0"></div>
        </div>
        <div class="form-group"><label>Подрядчик</label>
          <select class="form-control" name="vendor_id">
            <option value="">— не выбран —</option>
            <?php foreach ($vendors as $v): ?><option value="<?= $v['id'] ?>"><?= h($v['company_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-outline" type="button" onclick="closeModal('addItem')">Отмена</button><button class="btn btn-primary" type="submit">Добавить</button></div>
    </form>
  </div>
</div>


<div class="modal-backdrop" id="editItem">
  <div class="modal">
    <div class="modal-header"><h3>✏️ Редактировать статью</h3><button class="btn btn-ghost btn-icon" onclick="closeModal('editItem')">✕</button></div>
    <form method="POST" action="<?= app_url('actions/budget_action.php') ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="item_id" id="ei_id">
      <div class="modal-body">
        <div class="form-group"><label>Категория <span>*</span></label><input class="form-control" name="category" id="ei_cat" required></div>
        <div class="form-group"><label>Описание</label><input class="form-control" name="description" id="ei_desc"></div>
        <div class="form-row col2">
          <div class="form-group"><label>Планируемая сумма</label><input class="form-control" name="planned_amount" id="ei_plan" type="number" min="0" step="100"></div>
          <div class="form-group"><label>Оплачено факт</label><input class="form-control" name="actual_amount" id="ei_actual" type="number" min="0" step="100"></div>
        </div>
        <div class="form-group"><label>Подрядчик</label>
          <select class="form-control" name="vendor_id" id="ei_vendor">
            <option value="">— не выбран —</option>
            <?php foreach ($vendors as $v): ?><option value="<?= $v['id'] ?>"><?= h($v['company_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-outline" type="button" onclick="closeModal('editItem')">Отмена</button><button class="btn btn-primary" type="submit">Сохранить</button></div>
    </form>
  </div>
</div>
<script>
function editItem(it) {
  document.getElementById('ei_id').value     = it.id;
  document.getElementById('ei_cat').value    = it.category;
  document.getElementById('ei_desc').value   = it.description||'';
  document.getElementById('ei_plan').value   = it.planned_amount;
  document.getElementById('ei_actual').value = it.actual_amount;
  document.getElementById('ei_vendor').value = it.vendor_id||'';
  openModal('editItem');
}
</script>
<?php pageFooter(); ?>
