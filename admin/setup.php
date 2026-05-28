<?php







require_once '../config.php';
require_once '../includes/db.php';
session_start();
ensureSchema();

function h($s){ return htmlspecialchars($s??'', ENT_QUOTES,'UTF-8'); }

$adminSecret = getenv('ADMIN_SECRET') ?: '';
$inputSecret = $_GET['secret'] ?? $_POST['secret'] ?? '';


if (!$adminSecret) {
    http_response_code(404);
    die('<div style="font-family:monospace;padding:2rem">
        ❌ Переменная окружения <code>ADMIN_SECRET</code> не задана.<br><br>
        Добавьте её в Railway: <strong>Settings → Variables → ADMIN_SECRET = ваша_строка</strong>
    </div>');
}


if ($inputSecret !== $adminSecret) {
    http_response_code(403);
    die('<div style="font-family:monospace;padding:2rem">
        🔒 Неверный секрет. Передайте его через параметр <code>?secret=...</code>
    </div>');
}

$pdo   = getDb();
$flash = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'promote') {
        
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
            $pdo->prepare("UPDATE users SET is_admin=1 WHERE id=?")->execute([$uid]);
            logEvent('info', "Пользователь #$uid назначен администратором через setup.php");
            $flash = "Пользователь назначен администратором! Войдите в admin/login.php";
        }
    } elseif ($action === 'demote') {
        $uid = (int)($_POST['user_id'] ?? 0);
        
        $cnt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin=1")->fetchColumn();
        if ($uid && $cnt > 1) {
            $pdo->prepare("UPDATE users SET is_admin=0 WHERE id=?")->execute([$uid]);
            $flash = "Права администратора сняты.";
        } else {
            $error = "Нельзя снять права с единственного администратора.";
        }
    } elseif ($action === 'create_admin') {
        
        $uname = trim($_POST['username'] ?? '');
        $email = trim($_POST['email']    ?? '');
        $fname = trim($_POST['full_name']?? '');
        $pass  = $_POST['password']      ?? '';
        if (!$uname || !$email || !$fname || strlen($pass) < 6) {
            $error = 'Заполните все поля (пароль ≥ 6 символов).';
        } else {
            try {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $pdo->prepare("INSERT INTO users (username,password_hash,email,full_name,is_admin) VALUES (?,?,?,?,1)")
                    ->execute([$uname, $hash, $email, $fname]);
                logEvent('info', "Создан новый администратор: $uname");
                $flash = "Администратор «$uname» создан! Войдите на admin/login.php";
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(),'Duplicate') ? 'Такой логин или email уже занят.' : 'Ошибка: '.$e->getMessage();
            }
        }
    }
}

$users = $pdo->query("SELECT id, username, email, full_name, is_admin, created_at FROM users ORDER BY is_admin DESC, created_at ASC")->fetchAll();
?>
<!DOCTYPE html><html lang="ru"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Настройка администратора — WeddingPlan</title>
<link rel="stylesheet" href="<?= app_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= app_url('admin/assets/admin.css') ?>">
</head><body style="background:linear-gradient(135deg,#2c3e50,#3d5166);padding:2rem">
<div style="max-width:700px;margin:0 auto">
  <div class="card" style="margin-bottom:1.5rem">
    <div class="card-header" style="background:#2c3e50;color:#7eb3f5;border-radius:var(--radius) var(--radius) 0 0">
      <h2 style="color:#7eb3f5">🔧 Настройка администратора</h2>
      <span class="admin-badge">SETUP</span>
    </div>
    <div class="card-body">
      <?php if ($flash): ?><div class="alert alert-success">✅ <?= h($flash) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-error">⚠️ <?= h($error) ?></div><?php endif; ?>

      
      <h3 style="margin-bottom:1rem;font-size:1rem">➕ Создать нового администратора</h3>
      <form method="POST">
        <input type="hidden" name="secret" value="<?= h($inputSecret) ?>">
        <input type="hidden" name="form_action" value="create_admin">
        <div class="form-row col2">
          <div class="form-group"><label>Логин <span>*</span></label><input class="form-control" name="username" required></div>
          <div class="form-group"><label>Имя <span>*</span></label><input class="form-control" name="full_name" required></div>
        </div>
        <div class="form-row col2">
          <div class="form-group"><label>E-mail <span>*</span></label><input class="form-control" name="email" type="email" required></div>
          <div class="form-group"><label>Пароль (≥6 симв.) <span>*</span></label><input class="form-control" name="password" type="password" required></div>
        </div>
        <button class="btn btn-primary" type="submit">Создать администратора</button>
      </form>

      <hr class="divider">

      
      <h3 style="margin-bottom:1rem;font-size:1rem">👥 Все пользователи</h3>
      <table>
        <thead><tr><th>ID</th><th>Логин</th><th>Имя</th><th>Роль</th><th>Действие</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><?= h($u['username']) ?></td>
          <td><?= h($u['full_name']) ?></td>
          <td><?= $u['is_admin'] ? '<span class="badge badge-confirmed">Администратор</span>' : '<span class="badge badge-pending">Пользователь</span>' ?></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="secret" value="<?= h($inputSecret) ?>">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <?php if (!$u['is_admin']): ?>
              <input type="hidden" name="form_action" value="promote">
              <button class="btn btn-success btn-xs" type="submit">↑ Сделать админом</button>
              <?php else: ?>
              <input type="hidden" name="form_action" value="demote">
              <button class="btn btn-danger btn-xs" type="submit">↓ Снять права</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
        <tr><td colspan="5" style="text-align:center;color:#999">Пользователей нет. Создайте первого выше.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p style="text-align:center;color:#8fa8bf;font-size:.8rem">
    После настройки войдите в <a href="<?= app_url('admin/login.php') ?>" style="color:#7eb3f5">admin/login.php</a>
  </p>
</div>
</body></html>
