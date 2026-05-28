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
$rsvp_f = $_GET['rsvp'] ?? '';
$w=["1=1"]; $p=[];
if($search){$w[]="(g.full_name LIKE ? OR g.email LIKE ?)";$p[]="%$search%";$p[]="%$search%";}
if($rsvp_f){$w[]="g.rsvp_status=?";$p[]=$rsvp_f;}
$where=implode(' AND ',$w);
$total=$pdo->prepare("SELECT COUNT(*) FROM guests g JOIN events e ON g.event_id=e.id JOIN users u ON e.user_id=u.id WHERE $where");
$total->execute($p);$total=(int)$total->fetchColumn();$pages=max(1,(int)ceil($total/$limit));
$stmt=$pdo->prepare("SELECT g.*, e.title ev_title, u.username FROM guests g JOIN events e ON g.event_id=e.id JOIN users u ON e.user_id=u.id WHERE $where ORDER BY g.full_name LIMIT $limit OFFSET $offset");
$stmt->execute($p);$rows=$stmt->fetchAll();
$rsvpRu=['pending'=>'Ожидается','invited'=>'Приглашён','confirmed'=>'Подтвердил','declined'=>'Отказал'];
adminPageHeader('Все гости','guests');
?>
<div class="card">
  <div class="card-header"><h2>🎟️ Все гости (<?= $total ?>)</h2></div>
  <div class="card-body" style="padding-bottom:.5rem">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="🔍 Имя, email...">
      <select name="rsvp" onchange="this.form.submit()">
        <option value="">Любой RSVP</option>
        <?php foreach($rsvpRu as $k=>$v): ?><option value="<?=$k?>" <?=$rsvp_f===$k?'selected':''?>><?=$v?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-outline btn-sm" type="submit">Найти</button>
      <?php if($search||$rsvp_f): ?><a href="<?= app_url('admin/guests.php') ?>" class="btn btn-outline btn-sm">Сбросить</a><?php endif; ?>
    </form>
  </div>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Гость</th><th>Мероприятие</th><th>Владелец</th><th>Сторона</th><th>RSVP</th><th>Трансфер</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
    <tr>
      <td><strong><?= h($r['full_name']) ?></strong><?= $r['email']?'<br><small class="text-muted">'.h($r['email']).'</small>':'' ?></td>
      <td class="text-sm"><?= h($r['ev_title']) ?></td>
      <td class="text-sm text-muted">@<?= h($r['username']) ?></td>
      <td class="text-sm"><?= h($r['side']) ?></td>
      <td><span class="badge badge-<?= h($r['rsvp_status']) ?>"><?= $rsvpRu[$r['rsvp_status']]??h($r['rsvp_status']) ?></span></td>
      <td><?= $r['needs_transfer']?'🚗':'' ?></td>
    </tr>
    <?php endforeach; if(empty($rows)): ?>
      <tr><td colspan="6" style="text-align:center;padding:2rem;color:#999">Ничего</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
  <?php if($pages>1): ?><div style="padding:1rem 1.5rem"><div class="pagination">
    <?php for($i=1;$i<=$pages;$i++): ?>
      <?php if($i===$page): ?><span class="current"><?=$i?></span>
      <?php else: ?><a href="?page=<?=$i?>&q=<?=urlencode($search)?>&rsvp=<?=urlencode($rsvp_f)?>"><?=$i?></a><?php endif; ?>
    <?php endfor; ?>
  </div></div><?php endif; ?>
</div>
<?php adminPageFooter(); ?>
