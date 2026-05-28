<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_layout.php';
session_start();
ensureSchema();
adminRequireAuth();
$pdo  = getDb();
$me   = adminCurrentUser();


$flash = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $uid = (int)($_POST['user_id'] ?? 0);

    if ($act === 'make_admin' && $uid && $uid !== $me['id']) {
        $pdo->prepare("UPDATE users SET is_admin=1 WHERE id=?")->execute([$uid]);
        logEvent('info', "Назначен администратор #$uid", "by admin #{$me['id']}");
        $flash = 'Пользователь назначен администратором.';
    }
    if ($act === 'revoke_admin' && $uid && $uid !== $me['id']) {
        $cnt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin=1")->fetchColumn();
        if ($cnt <= 1) { $error = 'Нельзя снять права с единственного администратора.'; }
        else {
            $pdo->prepare("UPDATE users SET is_admin=0 WHERE id=?")->execute([$uid]);
            logEvent('info', "Сняты права администратора #$uid", "by admin #{$me['id']}");
            $flash = 'Права администратора сняты.';
        }
    }
    if ($act === 'reset_password' && $uid) {
        $newPass = $_POST['new_password'] ?? '';
        if (strlen($newPass) < 6) { $error = 'Пароль слишком короткий (мин. 6 символов).'; }
        else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $uid]);
            logEvent('info', "Сброс пароля пользователя #$uid", "by admin #{$me['id']}");
            $flash = 'Пароль пользователя изменён.';
        }
    }
    if ($act === 'delete' && $uid && $uid !== $me['id']) {
        
        $target = $pdo->prepare("SELECT username FROM users WHERE id=?");
        $target->execute([$uid]);
        $trow = $target->fetch();
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        logEvent('warn', "Удалён пользователь #$uid (@{$trow['username']})", "by admin #{$me['id']}");
        $flash = "Пользователь удалён.";
    }
    if ($act === 'create') {
        $uname = trim($_POST['username'] ?? '');
        $email = trim($_POST['email']    ?? '');
        $fname = trim($_POST['full_name']?? '');
        $pass  = $_POST['password']      ?? '';
        $adm   = isset($_POST['is_admin']) ? 1 : 0;
        if (!$uname||!$email||!$fname||strlen($pass)<6) { $error='Заполните все поля (пароль ≥6).'; }
        else {
            try {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $pdo->prepare("INSERT INTO users (username,password_hash,email,full_name,is_admin) VALUES (?,?,?,?,?)")
                    ->execute([$uname,$hash,$email,$fname,$adm]);
                logEvent('info', "Создан пользователь $uname", "is_admin=$adm, by admin #{$me['id']}");
                $flash = "Пользователь «$uname» создан.";
            } catch(PDOException $e) {
                $error = str_contains($e->getMessage(),'Duplicate') ? 'Логин или email уже занят.' : 'Ошибка БД.';
            }
        }
    }
    if (!$error) { redirectTo('admin/users.php?ok=' . urlencode($flash)); }
}
if (isset($_GET['ok'])) $flash = $_GET['ok'];


$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page-1)*$limit;

$where  = $search ? "WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?" : '';
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];

$total = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$total->execute($params);
$total = (int)$total->fetchColumn();
$pages = max(1, (int)ceil($total/$limit));

$stmt = $pdo->prepare(
    "SELECT u.*,
     (SELECT COUNT(*) FROM events WHERE user_id=u.id) ev_cnt
     FROM users u $where ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$users = $stmt->fetchAll();

adminPageHeader('Пользователи', 'users');
?>

<?php if ($flash): ?><div class="alert alert-success" data-auto>✅ <?= h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error">⚠️ <?= h($error) ?></div><?php endif; ?>

<div class="card">
  <div class="card-header">
    <h2>👥 Пользователи (<?= $total ?>)</h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('createUser')">+ Создать пользователя</button>
  </div>
  <div class="card-body" style="padding-bottom:.5rem">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="🔍 Поиск по логину, email, имени..." style="width:300px">
      <button class="btn btn-outline btn-sm" type="submit">Найти</button>
      <?php if ($search): ?><a href="<?= app_url('admin/users.php') ?>" class="btn btn-outline btn-sm">Сбросить</a><?php endif; ?>
    </form>
  </div>
  <div class="table-wrap">
  <table>
    <thead>
      <tr><th>#</th><th>Пользователь</th><th>E-mail</th><th>Роль</th><th>Мероприятий</th><th>Зарег.</th><th>Действия</th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr <?= $u['id']==$me['id']?'style="background:var(--gold-l)"':'' ?>>
      <td class="text-muted text-sm"><?= $u['id'] ?></td>
      <td>
        <strong><?= h($u['full_name']) ?></strong><br>
        <small class="text-muted">@<?= h($u['username']) ?></small>
      </td>
      <td class="text-sm"><?= h($u['email']) ?></td>
      <td>
        <?php if ($u['is_admin']): ?>
          <span class="badge badge-confirmed">Администратор</span>
        <?php else: ?>
          <span class="badge badge-pending">Пользователь</span>
        <?php endif; ?>
        <?php if ($u['id']==$me['id']): ?>
          <span class="badge badge-invited">Это вы</span>
        <?php endif; ?>
      </td>
      <td class="text-sm"><?= $u['ev_cnt'] ?></td>
      <td class="text-xs text-muted"><?= ago($u['created_at']) ?></td>
      <td>
        <div class="action-btns">
          
          <button class="btn btn-outline btn-xs" onclick="resetPwd(<?= $u['id'] ?>, '<?= h($u['username']) ?>')">🔒 Пароль</button>

          
          <?php if ($u['id'] !== $me['id']): ?>
            <?php if (!$u['is_admin']): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="make_admin">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn btn-success btn-xs" type="submit">↑ Admin</button>
              </form>
            <?php else: ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="revoke_admin">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn btn-outline btn-xs" type="submit">↓ Снять</button>
              </form>
            <?php endif; ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button class="btn btn-danger btn-xs" type="submit" data-confirm="Удалить пользователя @<?= h($u['username']) ?> и все его данные? Это необратимо!">🗑️</button>
            </form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($users)): ?>
    <tr><td colspan="7" class="text-muted" style="text-align:center;padding:2rem">Ничего не найдено</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>

  
  <?php if ($pages > 1): ?>
  <div style="padding:1rem 1.5rem">
    <div class="pagination">
      <?php for ($i=1; $i<=$pages; $i++): ?>
        <?php if ($i===$page): ?>
          <span class="current"><?= $i ?></span>
        <?php else: ?>
          <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>


<div class="modal-backdrop" id="resetModal">
  <div class="modal">
    <div class="modal-header"><h3>🔒 Сбросить пароль</h3><button class="btn btn-ghost btn-icon" onclick="closeModal('resetModal')">✕</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="reset_uid">
      <div class="modal-body">
        <p class="text-muted" style="margin-bottom:1rem">Новый пароль для пользователя <strong id="reset_uname"></strong>:</p>
        <div class="form-group">
          <label>Новый пароль <span>*</span></label>
          <input class="form-control" name="new_password" type="text" placeholder="Минимум 6 символов" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" type="button" onclick="closeModal('resetModal')">Отмена</button>
        <button class="btn btn-primary" type="submit">Установить пароль</button>
      </div>
    </form>
  </div>
</div>


<div class="modal-backdrop" id="createUser">
  <div class="modal">
    <div class="modal-header"><h3>+ Создать пользователя</h3><button class="btn btn-ghost btn-icon" onclick="closeModal('createUser')">✕</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-row col2">
          <div class="form-group"><label>Логин <span>*</span></label><input class="form-control" name="username" required autofocus></div>
          <div class="form-group"><label>Имя <span>*</span></label><input class="form-control" name="full_name" required></div>
        </div>
        <div class="form-group"><label>E-mail <span>*</span></label><input class="form-control" name="email" type="email" required></div>
        <div class="form-group"><label>Пароль <span>*</span></label><input class="form-control" name="password" type="text" placeholder="Мин. 6 символов" required></div>
        <div class="form-group"><label><input type="checkbox" name="is_admin" value="1"> Назначить администратором</label></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" type="button" onclick="closeModal('createUser')">Отмена</button>
        <button class="btn btn-primary" type="submit">Создать</button>
      </div>
    </form>
  </div>
</div>

<script>
function resetPwd(uid, uname) {
  document.getElementById('reset_uid').value   = uid;
  document.getElementById('reset_uname').textContent = '@' + uname;
  openModal('resetModal');
}
</script>
<?php adminPageFooter(); ?>
