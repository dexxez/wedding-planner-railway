<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_layout.php';
session_start(); ensureSchema(); adminRequireAuth();
$pdo = getDb();
$search = trim($_GET['q'] ?? '');
$page   = max(1,(int)($_GET['page']??1));
$limit  = 20; $offset = ($page-1)*$limit;
$status_filter = $_GET['status'] ?? '';

$w = ["1=1"];
$p = [];
if ($search) { $w[]="(t.title LIKE ? OR u.username LIKE ?)"; $p[]="%$search%"; $p[]="%$search%"; }
if ($status_filter) { $w[]="t.status=?"; $p[]=$status_filter; }
$where = implode(' AND ',$w);

$total = $pdo->prepare("SELECT COUNT(*) FROM tasks t JOIN events e ON t.event_id=e.id JOIN users u ON e.user_id=u.id WHERE $where");
$total->execute($p); $total=(int)$total->fetchColumn();
$pages = max(1,(int)ceil($total/$limit));

$stmt = $pdo->prepare("SELECT t.*, e.title ev_title, u.username FROM tasks t JOIN events e ON t.event_id=e.id JOIN users u ON e.user_id=u.id WHERE $where ORDER BY t.due_date ASC LIMIT $limit OFFSET $offset");
$stmt->execute($p); $rows=$stmt->fetchAll();

$stRu = ['not_started'=>'Не начато','in_progress'=>'В процессе','done'=>'Выполнено'];
$priRu = ['low'=>'Низкий','medium'=>'Средний','high'=>'Высокий'];

adminPageHeader('Все задачи', 'tasks');
?>
<div class="card">
  <div class="card-header"><h2>✅ Все задачи (<?= $total ?>)</h2></div>
  <div class="card-body" style="padding-bottom:.5rem">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="🔍 Название, владелец...">
      <select name="status" onchange="this.form.submit()">
        <option value="">Любой статус</option>
        <?php foreach ($stRu as $k=>$v): ?><option value="<?=$k?>" <?=$status_filter===$k?'selected':''?>><?=$v?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-outline btn-sm" type="submit">Найти</button>
      <?php if ($search||$status_filter): ?><a href="<?= app_url('admin/tasks.php') ?>" class="btn btn-outline btn-sm">Сбросить</a><?php endif; ?>
    </form>
  </div>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Задача</th><th>Мероприятие</th><th>Владелец</th><th>Срок</th><th>Приоритет</th><th>Статус</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $over = $r['due_date'] && $r['status']!=='done' && strtotime($r['due_date']) < time(); ?>
    <tr class="<?=$over?'overdue':'' ?>">
      <td><?= h($r['title']) ?><br><small class="text-muted"><?= h($r['category']) ?></small></td>
      <td class="text-sm"><?= h($r['ev_title']) ?></td>
      <td class="text-sm text-muted">@<?= h($r['username']) ?></td>
      <td class="text-xs <?=$over?'text-red':'' ?>"><?= $r['due_date'] ? date('d.m.Y',strtotime($r['due_date'])) : '—' ?></td>
      <td><span class="badge badge-<?= h($r['priority']) ?>"><?= $priRu[$r['priority']]??h($r['priority']) ?></span></td>
      <td><span class="badge badge-<?= h($r['status']) ?>"><?= $stRu[$r['status']]??h($r['status']) ?></span></td>
    </tr>
    <?php endforeach; if(empty($rows)): ?>
      <tr><td colspan="6" style="text-align:center;padding:2rem;color:#999">Ничего не найдено</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
  <?php if ($pages>1): ?>
  <div style="padding:1rem 1.5rem"><div class="pagination">
    <?php for($i=1;$i<=$pages;$i++): ?>
      <?php if($i===$page): ?><span class="current"><?=$i?></span>
      <?php else: ?><a href="?page=<?=$i?>&q=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>"><?=$i?></a><?php endif; ?>
    <?php endfor; ?>
  </div></div>
  <?php endif; ?>
</div>
<?php adminPageFooter(); ?>
