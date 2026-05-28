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

$filterCat = $_GET['cat'] ?? '';
$where = ["event_id=?"]; $params = [$eid];
if ($filterCat) { $where[]='category=?'; $params[]=$filterCat; }
$sql = "SELECT * FROM vendors WHERE ".implode(' AND ',$where)." ORDER BY category, company_name";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $vendors = $stmt->fetchAll();

$catRu = ['photo'=>'Фото','video'=>'Видео','florist'=>'Флористика','catering'=>'Кейтеринг','music'=>'Музыка','decor'=>'Декор','transport'=>'Транспорт','beauty'=>'Красота','venue'=>'Площадка','other'=>'Прочее'];
$stRu  = ['considering'=>'Рассматривается','booked'=>'Забронирован','deposit_paid'=>'Внесён депозит','fully_paid'=>'Оплачен полностью','cancelled'=>'Отменён'];

$totalContract = array_sum(array_column($vendors,'contract_amount'));
$totalDeposit  = array_sum(array_column($vendors,'deposit_paid'));
$booked = count(array_filter($vendors, fn($v) => in_array($v['status'],['booked','deposit_paid','fully_paid'])));

$flash = $_GET['ok'] ?? '';
pageHeader('Подрядчики', 'vendors');
?>
<?php if ($flash): ?><div class="alert alert-success" data-auto>✅ Сохранено</div><?php endif; ?>

<div class="stat-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card rose"><div class="stat-label">Всего подрядчиков</div><div class="stat-value"><?= count($vendors) ?></div></div>
  <div class="stat-card green"><div class="stat-label">Забронированы</div><div class="stat-value"><?= $booked ?></div></div>
  <div class="stat-card gold"><div class="stat-label">Сумма договоров</div><div class="stat-value" style="font-size:1.2rem"><?= money($totalContract) ?></div></div>
  <div class="stat-card orange"><div class="stat-label">Депозиты уплачены</div><div class="stat-value" style="font-size:1.2rem"><?= money($totalDeposit) ?></div></div>
</div>

<div class="card">
  <div class="card-header">
    <h2>🤝 Подрядчики</h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('addVendor')">+ Добавить подрядчика</button>
  </div>
  <div class="card-body" style="padding-bottom:.5rem">
    <div class="filter-bar">
      <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap">
        <select name="cat" onchange="this.form.submit()">
          <option value="">Все категории</option>
          <?php foreach ($catRu as $k=>$v): ?><option value="<?= $k ?>" <?= $filterCat===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
        <?php if ($filterCat): ?><a href="<?= app_url('vendors.php') ?>" class="btn btn-outline btn-sm">Сбросить</a><?php endif; ?>
      </form>
      <input type="text" id="vSearch" placeholder="🔍 Поиск...">
    </div>
  </div>
  <?php if (empty($vendors)): ?>
    <div class="empty-state"><div class="empty-icon">🤝</div><p>Список подрядчиков пуст</p></div>
  <?php else: ?>
  <div class="vendor-grid" style="padding:0 1.5rem 1.5rem" id="vendorsGrid">
  <?php foreach ($vendors as $v): ?>
  <div class="vendor-card" data-name="<?= h(strtolower($v['company_name'].' '.$v['contact_name'])) ?>">
    <div class="vendor-card-top">
      <div>
        <div class="vendor-cat"><?= $catRu[$v['category']] ?? h($v['category']) ?></div>
        <h3><?= h($v['company_name']) ?></h3>
      </div>
      <span class="badge badge-<?= h($v['status']) ?>"><?= $stRu[$v['status']] ?? h($v['status']) ?></span>
    </div>
    <div class="vendor-info">
      <?= $v['contact_name'] ? '👤 '.h($v['contact_name']).'<br>' : '' ?>
      <?= $v['phone']        ? '📞 <a href="tel:'.h($v['phone']).'">'.h($v['phone']).'</a><br>' : '' ?>
      <?= $v['email']        ? '✉️ <a href="mailto:'.h($v['email']).'">'.h($v['email']).'</a><br>' : '' ?>
      <?= $v['website']      ? '🌐 <a href="'.h($v['website']).'" target="_blank">Сайт</a><br>' : '' ?>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.25rem">
      <div>
        <div class="vendor-amount"><?= money($v['contract_amount']) ?></div>
        <?php if ((float)$v['deposit_paid']>0): ?><div class="text-xs text-muted">Депозит: <?= money($v['deposit_paid']) ?></div><?php endif; ?>
      </div>
    </div>
    <?php if ($v['notes']): ?><div class="text-xs text-muted" style="margin-top:.25rem;font-style:italic"><?= h(mb_substr($v['notes'],0,80)) ?></div><?php endif; ?>
    <div class="vendor-actions">
      <button class="btn btn-outline btn-xs" onclick="editVendor(<?= htmlspecialchars(json_encode($v),ENT_QUOTES) ?>)">✏️ Изменить</button>
      <a href="<?= app_url('actions/vendor_action.php') ?>?action=delete&vendor_id=<?= $v['id'] ?>" class="btn btn-outline btn-xs text-red" data-confirm="Удалить подрядчика?">🗑️ Удалить</a>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>


<div class="modal-backdrop" id="addVendor">
  <div class="modal">
    <div class="modal-header"><h3>+ Новый подрядчик</h3><button class="btn btn-ghost btn-icon" onclick="closeModal('addVendor')">✕</button></div>
    <form method="POST" action="<?= app_url('actions/vendor_action.php') ?>">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-row col2">
          <div class="form-group"><label>Компания / ФИО <span>*</span></label><input class="form-control" name="company_name" required autofocus></div>
          <div class="form-group"><label>Категория <span>*</span></label>
            <select class="form-control" name="category" required>
              <?php foreach ($catRu as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row col2">
          <div class="form-group"><label>Контактное лицо</label><input class="form-control" name="contact_name"></div>
          <div class="form-group"><label>Телефон</label><input class="form-control" name="phone" type="tel"></div>
        </div>
        <div class="form-row col2">
          <div class="form-group"><label>E-mail</label><input class="form-control" name="email" type="email"></div>
          <div class="form-group"><label>Сайт</label><input class="form-control" name="website" type="url" placeholder="https://"></div>
        </div>
        <div class="form-row col2">
          <div class="form-group"><label>Сумма договора</label><input class="form-control" name="contract_amount" type="number" min="0" step="100" value="0"></div>
          <div class="form-group"><label>Депозит уплачен</label><input class="form-control" name="deposit_paid" type="number" min="0" step="100" value="0"></div>
        </div>
        <div class="form-group"><label>Статус</label>
          <select class="form-control" name="status">
            <?php foreach ($stRu as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Заметки</label><textarea class="form-control" name="notes"></textarea></div>
      </div>
      <div class="modal-footer"><button class="btn btn-outline" type="button" onclick="closeModal('addVendor')">Отмена</button><button class="btn btn-primary" type="submit">Добавить</button></div>
    </form>
  </div>
</div>


<div class="modal-backdrop" id="editVendor">
  <div class="modal">
    <div class="modal-header"><h3>✏️ Редактировать подрядчика</h3><button class="btn btn-ghost btn-icon" onclick="closeModal('editVendor')">✕</button></div>
    <form method="POST" action="<?= app_url('actions/vendor_action.php') ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="vendor_id" id="ev_id">
      <div class="modal-body">
        <div class="form-row col2">
          <div class="form-group"><label>Компания <span>*</span></label><input class="form-control" name="company_name" id="ev_name" required></div>
          <div class="form-group"><label>Категория</label><select class="form-control" name="category" id="ev_cat"><?php foreach($catRu as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach;?></select></div>
        </div>
        <div class="form-row col2">
          <div class="form-group"><label>Контакт</label><input class="form-control" name="contact_name" id="ev_contact"></div>
          <div class="form-group"><label>Телефон</label><input class="form-control" name="phone" id="ev_phone" type="tel"></div>
        </div>
        <div class="form-row col2">
          <div class="form-group"><label>E-mail</label><input class="form-control" name="email" id="ev_email" type="email"></div>
          <div class="form-group"><label>Сайт</label><input class="form-control" name="website" id="ev_web" type="url"></div>
        </div>
        <div class="form-row col2">
          <div class="form-group"><label>Сумма договора</label><input class="form-control" name="contract_amount" id="ev_amount" type="number" min="0" step="100"></div>
          <div class="form-group"><label>Депозит</label><input class="form-control" name="deposit_paid" id="ev_deposit" type="number" min="0" step="100"></div>
        </div>
        <div class="form-group"><label>Статус</label><select class="form-control" name="status" id="ev_status"><?php foreach($stRu as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach;?></select></div>
        <div class="form-group"><label>Заметки</label><textarea class="form-control" name="notes" id="ev_notes"></textarea></div>
      </div>
      <div class="modal-footer"><button class="btn btn-outline" type="button" onclick="closeModal('editVendor')">Отмена</button><button class="btn btn-primary" type="submit">Сохранить</button></div>
    </form>
  </div>
</div>
<script>
function editVendor(v) {
  document.getElementById('ev_id').value      = v.id;
  document.getElementById('ev_name').value    = v.company_name;
  document.getElementById('ev_cat').value     = v.category;
  document.getElementById('ev_contact').value = v.contact_name||'';
  document.getElementById('ev_phone').value   = v.phone||'';
  document.getElementById('ev_email').value   = v.email||'';
  document.getElementById('ev_web').value     = v.website||'';
  document.getElementById('ev_amount').value  = v.contract_amount;
  document.getElementById('ev_deposit').value = v.deposit_paid;
  document.getElementById('ev_status').value  = v.status;
  document.getElementById('ev_notes').value   = v.notes||'';
  openModal('editVendor');
}
document.getElementById('vSearch')?.addEventListener('input', function(){
  const q = this.value.toLowerCase();
  document.querySelectorAll('#vendorsGrid .vendor-card').forEach(c => {
    c.style.display = c.dataset.name.includes(q) ? '' : 'none';
  });
});
</script>
<?php pageFooter(); ?>
