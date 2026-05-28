<?php
function adminPageHeader(string $title, string $active): void {
    $admin = adminCurrentUser();
    $name  = h($admin['full_name'] ?? 'Admin');

    $nav = [
        ['href'=>app_url('admin/dashboard.php'), 'icon'=>'📊', 'label'=>'Dashboard',    'key'=>'dashboard'],
        ['href'=>app_url('admin/users.php'),     'icon'=>'👥', 'label'=>'Пользователи', 'key'=>'users'],
        ['href'=>app_url('admin/events.php'),    'icon'=>'💍', 'label'=>'Мероприятия',  'key'=>'events'],
        ['href'=>app_url('admin/tasks.php'),     'icon'=>'✅', 'label'=>'Задачи',        'key'=>'tasks'],
        ['href'=>app_url('admin/budget.php'),    'icon'=>'💰', 'label'=>'Бюджеты',       'key'=>'budget'],
        ['href'=>app_url('admin/guests.php'),    'icon'=>'🎟️', 'label'=>'Гости',         'key'=>'guests'],
        ['href'=>app_url('admin/vendors.php'),   'icon'=>'🤝', 'label'=>'Подрядчики',   'key'=>'vendors'],
        ['href'=>app_url('admin/logs.php'),      'icon'=>'📋', 'label'=>'Логи/Ошибки',  'key'=>'logs'],
        ['href'=>app_url('admin/settings.php'),  'icon'=>'⚙️', 'label'=>'Настройки',    'key'=>'settings'],
    ];

    echo '<!DOCTYPE html><html lang="ru"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' — WeddingPlan Admin</title>';
    echo '<link rel="stylesheet" href="' . app_url('assets/css/style.css') . '">';
    echo '<link rel="stylesheet" href="' . app_url('admin/assets/admin.css') . '">';
    echo '<link rel="icon" href="data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'><text y=\'.9em\' font-size=\'90\'>🔧</text></svg>">';
    echo '</head><body class="admin-body">';
    echo '<div class="app-layout">';

    echo '<aside class="sidebar admin-sidebar" id="sidebar">';
    echo '<div class="sidebar-logo">';
    echo '<div class="ring">🔧</div>';
    echo '<h2 style="color:var(--admin-accent)">Admin Panel</h2>';
    echo '<small>WeddingPlan</small>';
    echo '</div>';
    echo '<nav class="sidebar-nav"><div class="nav-section">Управление</div>';
    foreach ($nav as $item) {
        $cls = $active === $item['key'] ? 'nav-link active' : 'nav-link';
        echo '<a href="' . $item['href'] . '" class="' . $cls . '">';
        echo '<span class="icon">' . $item['icon'] . '</span>' . $item['label'];
        echo '</a>';
    }
    echo '</nav>';
    echo '<div class="sidebar-footer">';
    echo '🛡️ ' . $name . '<br>';
    echo '<a href="' . app_url('admin/logout.php') . '">Выйти из админки</a> · <a href="' . app_url('dashboard.php') . '">→ Сайт</a>';
    echo '</div></aside>';

    echo '<div class="main-content">';
    echo '<header class="topbar admin-topbar">';
    echo '<div style="display:flex;align-items:center;gap:.75rem">';
    echo '<button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>';
    echo '<h1>' . h($title) . '</h1>';
    echo '<span class="admin-badge">ADMIN</span>';
    echo '</div>';
    echo '<div class="topbar-right">';
    echo '<span style="font-size:.8rem;color:var(--text-m)">👤 ' . $name . '</span>';
    echo '</div></header>';
    echo '<div class="page-body">';
}

function adminPageFooter(): void {
    echo '</div></div></div>';
    echo '<script src="' . app_url('assets/js/main.js') . '"></script>';
    echo '</body></html>';
}
