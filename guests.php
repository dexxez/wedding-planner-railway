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

$filterSide = $_GET['side'] ?? ''; $filterRsvp = $_GET['rsvp'] ?? '';
$where = ["event_id=?"]; $params = [$eid];
if ($filterSide) { $where[]='side=?'; $params[]=$filterSide; }
if ($filterRsvp) { $where[]='rsvp_status=?'; $params[]=$filterRsvp; }
$sql = "SELECT * FROM guests WHERE ".implode(' AND ',$where)." ORDER BY full_name";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $guests = $stmt->fetchAll();

$stat = $pdo->prepare("SELECT rsvp_status, COUNT(*) c FROM guests WHERE event_id=? GROUP BY rsvp_status"); $stat->execute([$eid]);
$smap = []; foreach ($stat->fetchAll() as $r) $smap[$r['rsvp_status']] = (int)$r['c'];
$total = array_sum($smap);

$sideRu = ['bride'=>'Невеста','groom'=>'Жених','mutual'=>'Общий'];
$catRu  = ['family'=>'Семья','friends'=>'Друзья','colleagues'=>'Коллеги','other'=>'Прочие'];
$rsvpRu = ['pending'=>'Ожидается','invited'=>'Приглашён','confirmed'=>'Подтвердил','declined'=>'Отказал'];

$flash = $_GET['ok'] ?? '';
pageHeader('Гостевой список', 'guests');
?>
<?php if ($flash): ?><div class="alert alert-success" data-auto>✅ Сохранено</div><?php endif; ?>

<div class="stat-grid" style="grid-template-columns:repeat(5,1fr)">
  <div class="stat-card rose"><div class="stat-label">Всего гостей</div><div class="stat-value"><?= $total ?></div></div>
  <div class="stat-card"><div class="stat-label">Ожидается</div><div class="stat-value"><?= $smap['pending']??0 ?></div></div>
  <div class="stat-card blue"><div class="stat-label">Приглашены</div><div class="stat-value"><?= $smap['invited']??0 ?></div></div>
  <div class="stat-card green"><div class="stat-label">Подтвердили</div><div class="stat-value"><?= $smap['confirmed']??0 ?></div></div>
  <div class="stat-card orange"><div class="stat-label">Отказали</div><div class="stat-value"><?= $smap['declined']??0 ?></div></div>
</div>

<div class="card">
  <div class="card-header">
    <h2>👥 Гости</h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('addGuest')">+ Добавить гостя</button>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <div class="filter-bar">
      <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap">
        <select name="side" onchange="this.form.submit()">
          <option value="">Любая сторона</option>
          <?php foreach ($sideRu as $k=>$v): ?><option value="<?= $k ?>" <?= $filterSide===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
        <select name="rsvp" onchange="this.form.submit()">
          <option value="">Любой RSVP</option>
          <?php foreach ($rsvpRu as $k=>$v): ?><option value="<?= $k ?>" <?= $filterRsvp===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
        <?php if ($filterSide||$filterRsvp): ?><a href="<?= app_url('guests.php') ?>" class="btn btn-outline btn-sm">Сбросить</a><?php endif; ?>
      </form>
      <input type="text" id="guestSearch" placeholder="🔍 Поиск..." style="margin-left:auto;max-width:200px">
    </div>
  </div>
  <div class="table-wrap">
  <?php if (empty($guests)): ?>
    <div class="empty-state"><div class="empty-icon">👥</div><p>Список гостей пуст. Добавьте первого гостя!</p></div>
  <?php else: ?>
    <table id="guestsTable">
      <thead><tr><th>Имя</th><th>Контакты</th><th>Сторона</th><th>Категория</th><th>Стол</th><th>Особое</th><th>RSVP</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($guests as $g): ?>
      <tr>
        <td><strong><?= h($g['full_name']) ?></strong></td>
        <td class="text-sm text-muted">
          <?= $g['email'] ? h($g['email']).'<br>' : '' ?>
          <?= $g['phone'] ? h($g['phone']) : '' ?>
        </td>
        <td><span class="badge badge-pending"><?= $sideRu[$g['side']] ?? h($g['side']) ?></span></td>
        <td><?= $catRu[$g['category']] ?? h($g['category']) ?></td>
        <td><?= $g['table_number'] ? '#'.$g['table_number'] : '—' ?></td>
        <td class="text-xs">
          <?= $g['needs_transfer'] ? '🚗 Трансфер<br>' : '' ?>
          <?= $g['dietary_notes'] ? '🍽️ '.h(mb_substr($g['dietary_notes'],0,30)) : '' ?>
        </td>
        <td>
          <select data-rsvp="<?= $g['id'] ?>" class="form-control" style="width:auto;padding:.3rem .5rem;font-size:.8rem">
            <?php foreach ($rsvpRu as $k=>$v): ?><option value="<?= $k ?>" <?= $g['rsvp_status']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
          </select>
        </td>
        <td style="white-space:nowrap">
          <button class="btn btn-ghost btn-xs" onclick="editGuest(<?= htmlspecialchars(json_encode($g),ENT_QUOTES) ?>)">✏️</button>
          <a href="<?= app_url('actions/guest_action.php') ?>?action=delete&guest_id=<?= $g['id'] ?>" class="btn btn-ghost btn-xs text-red" data-confirm="Удалить гостя?">🗑️</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  </div>
</div>


<div class="modal-backdrop" id="addGuest">
  <div class="modal">
    <div class="modal-header"><h3>+ Новый гость</h3><button class="btn btn-ghost btn-icon" onclick="closeModal('addGuest')">✕</button></div>
    <form method="POST" action="<?= app_url('actions/guest_action.php') ?>">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-group"><label>ФИО <span>*</span></label><input class="form-control" name="full_name" required autofocus></div>
        <div class="form-row col2">
          <div class="form-group"><label>E-mail</label><input class="form-control" name="email" type="email"></div>
          <div class="form-group"><label>Телефон</label><input class="form-control" name="phone" type="tel"></div>
        </div>
        <div class="form-row col2">
          <div class="form-group"><label>Сторона <span>*</span></label>
            <select class="form-control" name="side" required>
              <?php foreach ($sideRu as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Категория</label>
            <select class="form-control" name="category">
              <?php foreach ($catRu as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row col2">
          <div class="form-group"><label>RSVP статус</label>
            <select class="form-control" name="rsvp_status">
              <?php foreach ($rsvpRu as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Номер стола</label><input class="form-control" name="table_number" type="number" min="1"></div>
        </div>
        <div class="form-group"><label>Пищевые ограничения / особые пожелания</label><input class="form-control" name="dietary_notes" placeholder="Вегетарианское меню, аллергия и т.д."></div>
        <div class="form-group"><label><input type="checkbox" name="needs_transfer" value="1"> Нужен трансфер</label></div>
      </div>
      <div class="modal-footer"><button class="btn btn-outline" type="button" onclick="closeModal('addGuest')">Отмена</button><button class="btn btn-primary" type="submit">Добавить</button></div>
    </form>
  </div>
</div>


<div class="modal-backdrop" id="editGuest">
  <div class="modal">
    <div class="modal-header"><h3>✏️ Редактировать гостя</h3><button class="btn btn-ghost btn-icon" onclick="closeModal('editGuest')">✕</button></div>
    <form method="POST" action="<?= app_url('actions/guest_action.php') ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="guest_id" id="eg_id">
      <div class="modal-body">
        <div class="form-group"><label>ФИО</label><input class="form-control" name="full_name" id="eg_name" required></div>
        <div class="form-row col2">
          <div class="form-group"><label>E-mail</label><input class="form-control" name="email" id="eg_email" type="email"></div>
          <div class="form-group"><label>Телефон</label><input class="form-control" name="phone" id="eg_phone"></div>
        </div>
        <div class="form-row col2">
          <div class="form-group"><label>Сторона</label><select class="form-control" name="side" id="eg_side"><?php foreach($sideRu as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach;?></select></div>
          <div class="form-group"><label>Категория</label><select class="form-control" name="category" id="eg_cat"><?php foreach($catRu as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach;?></select></div>
        </div>
        <div class="form-row col2">
          <div class="form-group"><label>RSVP</label><select class="form-control" name="rsvp_status" id="eg_rsvp"><?php foreach($rsvpRu as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach;?></select></div>
          <div class="form-group"><label>Номер стола</label><input class="form-control" name="table_number" id="eg_table" type="number" min="1"></div>
        </div>
        <div class="form-group"><label>Пожелания</label><input class="form-control" name="dietary_notes" id="eg_diet"></div>
        <div class="form-group"><label><input type="checkbox" name="needs_transfer" id="eg_transfer" value="1"> Нужен трансфер</label></div>
      </div>
      <div class="modal-footer"><button class="btn btn-outline" type="button" onclick="closeModal('editGuest')">Отмена</button><button class="btn btn-primary" type="submit">Сохранить</button></div>
    </form>
  </div>
</div>
<script>
function editGuest(g) {
  document.getElementById('eg_id').value       = g.id;
  document.getElementById('eg_name').value     = g.full_name;
  document.getElementById('eg_email').value    = g.email||'';
  document.getElementById('eg_phone').value    = g.phone||'';
  document.getElementById('eg_side').value     = g.side;
  document.getElementById('eg_cat').value      = g.category||'friends';
  document.getElementById('eg_rsvp').value     = g.rsvp_status;
  document.getElementById('eg_table').value    = g.table_number||'';
  document.getElementById('eg_diet').value     = g.dietary_notes||'';
  document.getElementById('eg_transfer').checked = g.needs_transfer == 1;
  openModal('editGuest');
}
filterTable('guestSearch','guestsTable');
</script>
<?php pageFooter(); ?>
