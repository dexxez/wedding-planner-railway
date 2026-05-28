<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_layout.php';
session_start(); ensureSchema(); adminRequireAuth();
$pdo = getDb();
$search = trim($_GET['q'] ?? '');
$page   = max(1,(int)($_GET['page']??1)); $limit=20; $offset=($page-1)*$limit;
$w=["1=1"]; $p=[];
if($search){$w[]="(v.company_name LIKE ? OR v.contact_name LIKE ? OR u.username LIKE ?)";$p[]="%$search%";$p[]="%$search%";$p[]="%$search%";}
$where=implode(' AND ',$w);
$total=$pdo->prepare("SELECT COUNT(*) FROM vendors v JOIN events e ON v.event_id=e.id JOIN users u ON e.user_id=u.id WHERE $where");
$total->execute($p);$total=(int)$total->fetchColumn();$pages=max(1,(int)ceil($total/$limit));
$stmt=$pdo->prepare("SELECT v.*, e.title ev_title, u.username FROM vendors v JOIN events e ON v.event_id=e.id JOIN users u ON e.user_id=u.id WHERE $where ORDER BY v.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($p);$rows=$stmt->fetchAll();
$stRu=['considering'=>'Рассм.','booked'=>'Забронир.','deposit_paid'=>'Депозит','fully_paid'=>'Оплачен','cancelled'=>'Отменён'];
adminPageHeader('Все подрядчики','vendors');
?>
<div class="card">
  <div class="card-header"><h2>🤝 Все подрядчики (<?= $total ?>)</h2></div>
  <div class="card-body" style="padding-bottom:.5rem">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="🔍 Компания, контакт, владелец...">
      <button class="btn btn-outline btn-sm" type="submit">Найти</button>
      <?php if($search): ?><a href="<?= app_url('admin/vendors.php') ?>" class="btn btn-outline btn-sm">Сбросить</a><?php endif; ?>
    </form>
  </div>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Компания</th><th>Категория</th><th>Мероприятие</th><th>Владелец</th><th>Сумма</th><th>Депозит</th><th>Статус</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
    <tr>
      <td><strong><?= h($r['company_name']) ?></strong><?= $r['contact_name']?'<br><small class="text-muted">'.h($r['contact_name']).'</small>':'' ?></td>
      <td class="text-sm"><?= h($r['category']) ?></td>
      <td class="text-sm"><?= h($r['ev_title']) ?></td>
      <td class="text-sm text-muted">@<?= h($r['username']) ?></td>
      <td><?= money($r['contract_amount']) ?></td>
      <td class="text-sm text-muted"><?= money($r['deposit_paid']) ?></td>
      <td><span class="badge badge-<?= h($r['status']) ?>"><?= $stRu[$r['status']]??h($r['status']) ?></span></td>
    </tr>
    <?php endforeach; if(empty($rows)): ?>
      <tr><td colspan="7" style="text-align:center;padding:2rem;color:#999">Ничего</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
  <?php if($pages>1): ?><div style="padding:1rem 1.5rem"><div class="pagination">
    <?php for($i=1;$i<=$pages;$i++): ?>
      <?php if($i===$page): ?><span class="current"><?=$i?></span>
      <?php else: ?><a href="?page=<?=$i?>&q=<?=urlencode($search)?>"><?=$i?></a><?php endif; ?>
    <?php endfor; ?>
  </div></div><?php endif; ?>
</div>
<?php adminPageFooter(); ?>
