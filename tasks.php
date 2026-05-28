<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';
session_start();
requireAuth();

$pdo   = getDb();
$event = currentEvent();
if (!$event) { redirectTo('settings.php?first=1'); }
$eid = $event['id'];

$filterStatus   = $_GET['status']   ?? '';
$filterCategory = $_GET['category'] ?? '';
$filterPriority = $_GET['priority'] ?? '';

$where = ["event_id=?"];
$params = [$eid];
if ($filterStatus)   { $where[]='status=?';   $params[]=$filterStatus; }
if ($filterCategory) { $where[]='category=?'; $params[]=$filterCategory; }
if ($filterPriority) { $where[]='priority=?'; $params[]=$filterPriority; }

$sql = "SELECT * FROM tasks WHERE " . implode(' AND ',$where) . " ORDER BY FIELD(priority,'high','medium','low'), due_date ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$tasks = $stmt->fetchAll();


$s = $pdo->prepare("SELECT status, COUNT(*) c FROM tasks WHERE event_id=? GROUP BY status"); $s->execute([$eid]);
$smap = []; foreach ($s->fetchAll() as $r) $smap[$r['status']] = (int)$r['c'];
$total = array_sum($smap); $done = $smap['done']??0;
$pct = $total ? round($done/$total*100) : 0;

$cats  = ['venue','catering','decor','photo','music','documents','transport','beauty','honeymoon','other'];
$catRu = ['venue'=>'Площадка','catering'=>'Кейтеринг','decor'=>'Декор','photo'=>'Фото/Видео','music'=>'Музыка','documents'=>'Документы','transport'=>'Транспорт','beauty'=>'Красота','honeymoon'=>'Медовый месяц','other'=>'Прочее'];
$priRu = ['low'=>'Низкий','medium'=>'Средний','high'=>'Высокий'];
$stRu  = ['not_started'=>'Не начато','in_progress'=>'В процессе','done'=>'Выполнено'];

$flash = $_GET['ok'] ?? '';
pageHeader('Задачи', 'tasks');
?>

<?php if ($flash): ?><div class="alert alert-success" data-auto>✅ <?= $flash==='add'?'Задача добавлена':($flash==='del'?'Задача удалена':'Сохранено') ?></div><?php endif; ?>

<div class="stat-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card rose">
    <div class="stat-label">Всего задач</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="progress-wrap"><div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div></div>
    <div class="stat-sub">Выполнено: <?= $pct ?>%</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Не начато</div>
    <div class="stat-value"><?= $smap['not_started']??0 ?></div>
  </div>
  <div class="stat-card orange">
    <div class="stat-label">В процессе</div>
    <div class="stat-value"><?= $smap['in_progress']??0 ?></div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Выполнено</div>
    <div class="stat-value"><?= $done ?></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>📋 Список задач</h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('addTask')">+ Добавить задачу</button>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <div class="filter-bar">
      <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap">
        <select name="status" onchange="this.form.submit()">
          <option value="">Любой статус</option>
          <?php foreach ($stRu as $k=>$v): ?><option value="<?= $k ?>" <?= $filterStatus===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
        <select name="category" onchange="this.form.submit()">
          <option value="">Любая категория</option>
          <?php foreach ($catRu as $k=>$v): ?><option value="<?= $k ?>" <?= $filterCategory===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
        <select name="priority" onchange="this.form.submit()">
          <option value="">Любой приоритет</option>
          <?php foreach ($priRu as $k=>$v): ?><option value="<?= $k ?>" <?= $filterPriority===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
        <?php if ($filterStatus||$filterCategory||$filterPriority): ?><a href="<?= app_url('tasks.php') ?>" class="btn btn-outline btn-sm">Сбросить</a><?php endif; ?>
      </form>
      <input type="text" id="taskSearch" placeholder="🔍 Поиск..." style="margin-left:auto;max-width:200px">
    </div>
  </div>
  <div class="table-wrap">
  <?php if (empty($tasks)): ?>
    <div class="empty-state"><div class="empty-icon">📋</div><p>Задач нет. Добавьте первую!</p><button class="btn btn-primary btn-sm" onclick="openModal('addTask')">+ Добавить</button></div>
  <?php else: ?>
    <table id="tasksTable">
      <thead><tr><th>Задача</th><th>Категория</th><th>Срок</th><th>Приоритет</th><th>Статус</th><th>Действия</th></tr></thead>
      <tbody>
      <?php foreach ($tasks as $t):
          $d = $t['due_date'] ? daysLeft($t['due_date']) : null;
          $overdue = $d !== null && $d < 0 && $t['status'] !== 'done';
      ?>
      <tr class="<?= $overdue?'overdue':'' ?> <?= $t['status']==='done'?'done':'' ?>">
        <td>
          <div style="font-weight:600"><?= h($t['title']) ?></div>
          <?php if ($t['notes']): ?><div class="text-muted text-xs" style="margin-top:.2rem"><?= h(mb_substr($t['notes'],0,60)) ?>…</div><?php endif; ?>
        </td>
        <td><span class="badge badge-pending"><?= $catRu[$t['category']] ?? h($t['category']) ?></span></td>
        <td style="white-space:nowrap">
          <?= $t['due_date'] ? date('d.m.Y', strtotime($t['due_date'])) : '—' ?>
          <?php if ($d !== null): ?><br><small class="<?= $overdue?'text-red':($d<=7?'text-rose':'text-muted') ?>"><?= $overdue?'⚠️ просрочено':($d===0?'сегодня':$d.' дн.') ?></small><?php endif; ?>
        </td>
        <td><span class="badge badge-<?= h($t['priority']) ?>"><?= $priRu[$t['priority']] ?? '' ?></span></td>
        <td>
          <select data-task="<?= $t['id'] ?>" class="form-control" style="width:auto;padding:.3rem .6rem;font-size:.8rem">
            <?php foreach ($stRu as $k=>$v): ?><option value="<?= $k ?>" <?= $t['status']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
          </select>
        </td>
        <td style="white-space:nowrap">
          <button class="btn btn-ghost btn-xs" onclick="editTask(<?= htmlspecialchars(json_encode($t),ENT_QUOTES) ?>)">✏️</button>
          <a href="<?= app_url('actions/task_action.php') ?>?action=delete&task_id=<?= $t['id'] ?>" class="btn btn-ghost btn-xs text-red" data-confirm="Удалить задачу «<?= h($t['title']) ?>»?">🗑️</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  </div>
</div>


<div class="modal-backdrop" id="addTask">
  <div class="modal">
    <div class="modal-header"><h3>➕ Новая задача</h3><button class="btn btn-ghost btn-icon" onclick="closeModal('addTask')">✕</button></div>
    <form method="POST" action="<?= app_url('actions/task_action.php') ?>">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-group"><label>Название <span>*</span></label><input class="form-control" name="title" required autofocus></div>
        <div class="form-row col2">
          <div class="form-group"><label>Категория <span>*</span></label>
            <select class="form-control" name="category" required>
              <?php foreach ($catRu as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Срок выполнения</label><input class="form-control" name="due_date" type="date"></div>
        </div>
        <div class="form-row col2">
          <div class="form-group"><label>Приоритет</label>
            <select class="form-control" name="priority">
              <option value="low">Низкий</option><option value="medium" selected>Средний</option><option value="high">Высокий</option>
            </select>
          </div>
          <div class="form-group"><label>Статус</label>
            <select class="form-control" name="status">
              <option value="not_started" selected>Не начато</option><option value="in_progress">В процессе</option><option value="done">Выполнено</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Заметки</label><textarea class="form-control" name="notes"></textarea></div>
      </div>
      <div class="modal-footer"><button class="btn btn-outline" type="button" onclick="closeModal('addTask')">Отмена</button><button class="btn btn-primary" type="submit">Добавить</button></div>
    </form>
  </div>
</div>


<div class="modal-backdrop" id="editTask">
  <div class="modal">
    <div class="modal-header"><h3>✏️ Редактировать задачу</h3><button class="btn btn-ghost btn-icon" onclick="closeModal('editTask')">✕</button></div>
    <form method="POST" action="<?= app_url('actions/task_action.php') ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="task_id" id="edit_task_id">
      <div class="modal-body">
        <div class="form-group"><label>Название <span>*</span></label><input class="form-control" name="title" id="edit_title" required></div>
        <div class="form-row col2">
          <div class="form-group"><label>Категория</label>
            <select class="form-control" name="category" id="edit_category">
              <?php foreach ($catRu as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Срок</label><input class="form-control" name="due_date" id="edit_due_date" type="date"></div>
        </div>
        <div class="form-row col2">
          <div class="form-group"><label>Приоритет</label>
            <select class="form-control" name="priority" id="edit_priority">
              <option value="low">Низкий</option><option value="medium">Средний</option><option value="high">Высокий</option>
            </select>
          </div>
          <div class="form-group"><label>Статус</label>
            <select class="form-control" name="status" id="edit_status">
              <option value="not_started">Не начато</option><option value="in_progress">В процессе</option><option value="done">Выполнено</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Заметки</label><textarea class="form-control" name="notes" id="edit_notes"></textarea></div>
      </div>
      <div class="modal-footer"><button class="btn btn-outline" type="button" onclick="closeModal('editTask')">Отмена</button><button class="btn btn-primary" type="submit">Сохранить</button></div>
    </form>
  </div>
</div>

<script>
function editTask(t) {
  document.getElementById('edit_task_id').value = t.id;
  document.getElementById('edit_title').value    = t.title;
  document.getElementById('edit_category').value = t.category;
  document.getElementById('edit_due_date').value = t.due_date || '';
  document.getElementById('edit_priority').value = t.priority;
  document.getElementById('edit_status').value   = t.status;
  document.getElementById('edit_notes').value    = t.notes || '';
  openModal('editTask');
}
filterTable('taskSearch','tasksTable');
</script>
<?php pageFooter(); ?>
