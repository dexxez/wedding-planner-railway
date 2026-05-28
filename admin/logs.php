<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_layout.php';
session_start(); ensureSchema(); adminRequireAuth();
$pdo = getDb(); $me = adminCurrentUser();


if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='clear') {
    $level = $_POST['level'] ?? '';
    if ($level === 'all') {
        $pdo->exec("DELETE FROM error_logs");
        logEvent('info', 'Очищены все логи', "by admin #{$me['id']}");
    } elseif (in_array($level, ['info','warn','error'])) {
        $pdo->prepare("DELETE FROM error_logs WHERE level=?")->execute([$level]);
        logEvent('info', "Очищены логи уровня $level", "by admin #{$me['id']}");
    }
    redirectTo('admin/logs.php?ok=cleared');
}

$level_f = $_GET['level'] ?? '';
$search  = trim($_GET['q'] ?? '');
$page    = max(1,(int)($_GET['page']??1));
$limit   = 30; $offset = ($page-1)*$limit;

$w=["1=1"]; $p=[];
if($level_f && in_array($level_f,['info','warn','error'])){$w[]="level=?";$p[]=$level_f;}
if($search){$w[]="(message LIKE ? OR context LIKE ? OR url LIKE ?)";$p[]="%$search%";$p[]="%$search%";$p[]="%$search%";}
$where=implode(' AND ',$w);

$total=$pdo->prepare("SELECT COUNT(*) FROM error_logs WHERE $where");
$total->execute($p);$total=(int)$total->fetchColumn();$pages=max(1,(int)ceil($total/$limit));

$stmt=$pdo->prepare("SELECT l.*, u.username FROM error_logs l LEFT JOIN users u ON l.user_id=u.id WHERE $where ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($p);$rows=$stmt->fetchAll();


$lcnt = $pdo->query("SELECT level, COUNT(*) c FROM error_logs GROUP BY level")->fetchAll(PDO::FETCH_KEY_PAIR);

$flash = isset($_GET['ok']) ? 'Логи очищены.' : '';
adminPageHeader('Логи / Ошибки', 'logs');
?>
<?php if($flash): ?><div class="alert alert-success" data-auto>✅ <?= h($flash) ?></div><?php endif; ?>

<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem">
  <div class="stat-card"><div class="stat-label">Всего записей</div><div class="stat-value"><?= $total ?></div></div>
  <div class="stat-card blue"><div class="stat-label">Info</div><div class="stat-value"><?= $lcnt['info']??0 ?></div></div>
  <div class="stat-card orange"><div class="stat-label">Warn</div><div class="stat-value"><?= $lcnt['warn']??0 ?></div></div>
  <div class="stat-card <?= ($lcnt['error']??0)>0?'rose':'' ?>"><div class="stat-label">Error</div><div class="stat-value" style="<?= ($lcnt['error']??0)>0?'color:var(--red)':'' ?>"><?= $lcnt['error']??0 ?></div></div>
</div>

<div class="card">
  <div class="card-header">
    <h2>📋 Лог событий (<?= $total ?>)</h2>
    
    <div style="display:flex;gap:.5rem">
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="clear">
        <input type="hidden" name="level" value="error">
        <button class="btn btn-danger btn-sm" type="submit" data-confirm="Очистить все ошибки?">🗑 Ошибки</button>
      </form>
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="clear">
        <input type="hidden" name="level" value="all">
        <button class="btn btn-danger btn-sm" type="submit" data-confirm="Очистить ВСЕ логи?">🗑 Все логи</button>
      </form>
    </div>
  </div>
  <div class="card-body" style="padding-bottom:.5rem">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="🔍 Поиск в сообщениях...">
      <select name="level" onchange="this.form.submit()">
        <option value="">Все уровни</option>
        <option value="info"  <?= $level_f==='info' ?'selected':'' ?>>Info</option>
        <option value="warn"  <?= $level_f==='warn' ?'selected':'' ?>>Warn</option>
        <option value="error" <?= $level_f==='error'?'selected':'' ?>>Error</option>
      </select>
      <button class="btn btn-outline btn-sm" type="submit">Найти</button>
      <?php if($search||$level_f): ?><a href="<?= app_url('admin/logs.php') ?>" class="btn btn-outline btn-sm">Сбросить</a><?php endif; ?>
    </form>
  </div>
  <div>
  <?php if(empty($rows)): ?>
    <div class="empty-state"><div class="empty-icon">📋</div><p>Лог пуст</p></div>
  <?php else: ?>
    <?php foreach($rows as $row): ?>
    <div class="log-entry">
      <span class="log-level <?= h($row['level']) ?>"><?= strtoupper($row['level']) ?></span>
      <span class="log-time" style="min-width:120px"><?= date('d.m.Y H:i:s', strtotime($row['created_at'])) ?></span>
      <span class="log-msg">
        <?= h($row['message']) ?>
        <?php if($row['context']): ?> <span class="text-muted text-xs">(<?= h($row['context']) ?>)</span><?php endif; ?>
      </span>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.15rem;min-width:140px">
        <?php if($row['username']): ?><span class="text-xs text-muted">👤 @<?= h($row['username']) ?></span><?php endif; ?>
        <?php if($row['url']): ?><span class="text-xs text-muted" title="<?= h($row['url']) ?>"><?= h(mb_substr(parse_url($row['url'],PHP_URL_PATH),0,30)) ?></span><?php endif; ?>
        <?php if($row['ip']): ?><span class="text-xs text-muted">🌐 <?= h($row['ip']) ?></span><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
  </div>
  <?php if($pages>1): ?><div style="padding:1rem 1.5rem"><div class="pagination">
    <?php for($i=1;$i<=$pages;$i++): ?>
      <?php if($i===$page): ?><span class="current"><?=$i?></span>
      <?php else: ?><a href="?page=<?=$i?>&q=<?=urlencode($search)?>&level=<?=urlencode($level_f)?>"><?=$i?></a><?php endif; ?>
    <?php endfor; ?>
  </div></div><?php endif; ?>
</div>
<?php adminPageFooter(); ?>
