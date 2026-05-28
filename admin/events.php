<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_layout.php';
session_start(); ensureSchema(); adminRequireAuth();
$pdo = getDb(); $me = adminCurrentUser();

$flash = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $eid = (int)($_POST['event_id'] ?? 0);
    if ($act === 'delete' && $eid) {
        $ev = $pdo->prepare("SELECT title, user_id FROM events WHERE id=?"); $ev->execute([$eid]); $evr = $ev->fetch();
        $pdo->prepare("DELETE FROM events WHERE id=?")->execute([$eid]);
        logEvent('warn', "Удалено мероприятие #$eid «{$evr['title']}»", "user_id={$evr['user_id']}, by admin #{$me['id']}");
        $flash = "Мероприятие удалено.";
    }
    if ($act === 'update' && $eid) {
        $title  = trim($_POST['title'] ?? '');
        $date   = $_POST['event_date'] ?? '';
        $venue  = trim($_POST['venue'] ?? '');
        $budget = max(0,(float)($_POST['total_budget'] ?? 0));
        $status = $_POST['status'] ?? 'planning';
        if ($title && $date) {
            $pdo->prepare("UPDATE events SET title=?,event_date=?,venue=?,total_budget=?,status=? WHERE id=?")
                ->execute([$title,$date,$venue,$budget,$status,$eid]);
            $flash = "Мероприятие обновлено.";
        } else { $error = 'Заполните название и дату.'; }
    }
    if (!$error) { redirectTo('admin/events.php?ok=' . urlencode($flash)); }
}
if (isset($_GET['ok'])) $flash = $_GET['ok'];

$search = trim($_GET['q'] ?? '');
$page   = max(1,(int)($_GET['page']??1));
$limit  = 15; $offset = ($page-1)*$limit;
$w = $search ? "WHERE e.title LIKE ? OR u.username LIKE ? OR e.venue LIKE ?" : '';
$p = $search ? ["%$search%","%$search%","%$search%"] : [];
$total = $pdo->prepare("SELECT COUNT(*) FROM events e JOIN users u ON e.user_id=u.id $w"); $total->execute($p); $total=(int)$total->fetchColumn();
$pages = max(1,(int)ceil($total/$limit));
$stmt  = $pdo->prepare(
    "SELECT e.*, u.username, u.full_name owner_name,
     (SELECT COUNT(*) FROM tasks WHERE event_id=e.id) task_cnt,
     (SELECT COUNT(*) FROM tasks WHERE event_id=e.id AND status='done') done_cnt,
     (SELECT COUNT(*) FROM guests WHERE event_id=e.id) guest_cnt,
     (SELECT COUNT(*) FROM guests WHERE event_id=e.id AND rsvp_status='confirmed') conf_cnt,
     (SELECT COALESCE(SUM(planned_amount),0) FROM budget_items WHERE event_id=e.id) b_plan,
     (SELECT COALESCE(SUM(actual_amount),0)  FROM budget_items WHERE event_id=e.id) b_actual
     FROM events e JOIN users u ON e.user_id=u.id
     $w ORDER BY e.created_at DESC LIMIT $limit OFFSET $offset"
); $stmt->execute($p); $events = $stmt->fetchAll();

$statusRu = ['planning'=>'Планирование','confirmed'=>'Подтверждено','completed'=>'Завершено'];

adminPageHeader('Мероприятия', 'events');
?>
<?php if ($flash): ?><div class="alert alert-success" data-auto>✅ <?= h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error">⚠️ <?= h($error) ?></div><?php endif; ?>

<div class="card">
  <div class="card-header"><h2>💍 Мероприятия (<?= $total ?>)</h2></div>
  <div class="card-body" style="padding-bottom:.5rem">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="🔍 Название, владелец, место...">
      <button class="btn btn-outline btn-sm" type="submit">Найти</button>
      <?php if ($search): ?><a href="<?= app_url('admin/events.php') ?>" class="btn btn-outline btn-sm">Сбросить</a><?php endif; ?>
    </form>
  </div>
  <div class="table-wrap">
  <table>
    <thead><tr><th>#</th><th>Название</th><th>Владелец</th><th>Дата</th><th>Статус</th><th>Задачи</th><th>Гости</th><th>Бюджет (план/факт)</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($events as $e): ?>
    <tr>
      <td class="text-muted text-xs"><?= $e['id'] ?></td>
      <td>
        <strong><?= h($e['title']) ?></strong><br>
        <?php if ($e['venue']): ?><small class="text-muted">📍 <?= h($e['venue']) ?></small><?php endif; ?>
      </td>
      <td class="text-sm">@<?= h($e['username']) ?><br><small class="text-muted"><?= h($e['owner_name']) ?></small></td>
      <td class="text-sm" style="white-space:nowrap"><?= date('d.m.Y', strtotime($e['event_date'])) ?></td>
      <td><span class="badge badge-<?= $e['status']==='confirmed'?'confirmed-ev':h($e['status']) ?>"><?= $statusRu[$e['status']] ?? h($e['status']) ?></span></td>
      <td class="text-sm"><?= $e['done_cnt'] ?>/<?= $e['task_cnt'] ?></td>
      <td class="text-sm"><?= $e['conf_cnt'] ?>/<?= $e['guest_cnt'] ?></td>
      <td class="text-xs">
        <span class="text-muted"><?= money($e['b_plan']) ?></span><br>
        <span class="<?= (float)$e['b_actual']>(float)$e['b_plan']?'text-red':'text-green' ?>"><?= money($e['b_actual']) ?></span>
      </td>
      <td style="white-space:nowrap">
        <button class="btn btn-outline btn-xs" onclick='editEv(<?= htmlspecialchars(json_encode($e),ENT_QUOTES) ?>)'>✏️</button>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="event_id" value="<?= $e['id'] ?>">
          <button class="btn btn-danger btn-xs" type="submit" data-confirm="Удалить мероприятие «<?= h($e['title']) ?>» со всеми данными?">🗑️</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($events)): ?>
      <tr><td colspan="9" style="text-align:center;padding:2rem;color:#999">Ничего не найдено</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
  <?php if ($pages>1): ?>
  <div style="padding:1rem 1.5rem">
    <div class="pagination">
      <?php for($i=1;$i<=$pages;$i++): ?>
        <?php if($i===$page): ?><span class="current"><?=$i?></span>
        <?php else: ?><a href="?page=<?=$i?>&q=<?=urlencode($search)?>"><?=$i?></a><?php endif; ?>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>


<div class="modal-backdrop" id="editEv">
  <div class="modal">
    <div class="modal-header"><h3>✏️ Редактировать мероприятие</h3><button class="btn btn-ghost btn-icon" onclick="closeModal('editEv')">✕</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="event_id" id="ee_id">
      <div class="modal-body">
        <div class="form-group"><label>Название <span>*</span></label><input class="form-control" name="title" id="ee_title" required></div>
        <div class="form-row col2">
          <div class="form-group"><label>Дата <span>*</span></label><input class="form-control" name="event_date" id="ee_date" type="date" required></div>
          <div class="form-group"><label>Статус</label>
            <select class="form-control" name="status" id="ee_status">
              <option value="planning">Планирование</option>
              <option value="confirmed">Подтверждено</option>
              <option value="completed">Завершено</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Место</label><input class="form-control" name="venue" id="ee_venue"></div>
        <div class="form-group"><label>Бюджет (₽)</label><input class="form-control" name="total_budget" id="ee_budget" type="number" min="0" step="1000"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" type="button" onclick="closeModal('editEv')">Отмена</button>
        <button class="btn btn-primary" type="submit">Сохранить</button>
      </div>
    </form>
  </div>
</div>
<script>
function editEv(e) {
  document.getElementById('ee_id').value     = e.id;
  document.getElementById('ee_title').value  = e.title;
  document.getElementById('ee_date').value   = e.event_date;
  document.getElementById('ee_status').value = e.status;
  document.getElementById('ee_venue').value  = e.venue||'';
  document.getElementById('ee_budget').value = e.total_budget;
  openModal('editEv');
}
</script>
<?php adminPageFooter(); ?>
