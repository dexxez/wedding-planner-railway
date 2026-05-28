<?php


function pageHeader(string $title, string $active): void {
    $user  = currentUser();
    $event = currentEvent();
    $evTitle = $event ? htmlspecialchars($event['title'], ENT_QUOTES) : 'Нет мероприятия';
    $styleUrl = app_url('assets/css/style.css');
    echo <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title} — Свадебный планировщик</title>
<link rel="stylesheet" href="{$styleUrl}">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💍</text></svg>">
</head>
<body>
<div class="app-layout">
<div class="sidebar-overlay" id="sidebar-overlay"></div>
HTML;

    $nav = [
        ['href'=>app_url('dashboard.php'), 'icon'=>'🏠','label'=>'Главная',        'key'=>'dashboard'],
        ['href'=>app_url('tasks.php'),     'icon'=>'✅','label'=>'Задачи',          'key'=>'tasks'],
        ['href'=>app_url('budget.php'),    'icon'=>'💰','label'=>'Бюджет',          'key'=>'budget'],
        ['href'=>app_url('guests.php'),    'icon'=>'👥','label'=>'Гостевой список', 'key'=>'guests'],
        ['href'=>app_url('vendors.php'),   'icon'=>'🤝','label'=>'Подрядчики',      'key'=>'vendors'],
        ['href'=>app_url('settings.php'),  'icon'=>'⚙️','label'=>'Настройки',       'key'=>'settings'],
    ];
    $userName = $user ? htmlspecialchars($user['full_name'], ENT_QUOTES) : '';
    echo '<aside class="sidebar" id="sidebar">';
    echo '<div class="sidebar-logo"><div class="ring">💍</div><h2>Свадебный планировщик</h2><small>' . $evTitle . '</small></div>';
    echo '<nav class="sidebar-nav"><div class="nav-section">Меню</div>';
    foreach ($nav as $item) {
        $cls = ($active === $item['key']) ? 'nav-link active' : 'nav-link';
        echo '<a href="' . $item['href'] . '" class="' . $cls . '"><span class="icon">' . $item['icon'] . '</span>' . $item['label'] . '</a>';
    }
    echo '</nav>';
    echo '<div class="sidebar-footer">👤 ' . $userName . '<br><a href="' . app_url('logout.php') . '">Выйти</a></div>';
    echo '</aside>';

    echo '<div class="main-content">';
    echo '<header class="topbar">';
    echo '<div style="display:flex;align-items:center;gap:.75rem">';
    echo '<button class="hamburger" id="hamburger" aria-label="Меню"><span></span><span></span><span></span></button>';
    echo '<h1>' . htmlspecialchars($title, ENT_QUOTES) . '</h1></div>';
    echo '<div class="topbar-right">';
    if ($event) {
        $days = daysLeft($event['event_date']);
        if ($days !== null) {
            $txt = $days > 0 ? "До свадьбы: <strong>{$days} дн.</strong>" : ($days === 0 ? '🎉 Сегодня!' : '<span class="text-red">Прошло</span>');
            echo '<span style="font-size:.82rem">' . $txt . '</span>';
        }
    }
    echo '</div></header>';
    echo '<div class="page-body">';
}

function pageFooter(): void {
    echo '</div></div></div>';
    echo '<script src="' . app_url('assets/js/main.js') . '"></script>';
    echo '</body></html>';
}
