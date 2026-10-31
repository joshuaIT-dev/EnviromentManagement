<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('enviro_session');
    session_start([
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

const APP_BASE = '/EnviromentManagement/';

function app_url(string $path = ''): string {
    return APP_BASE . ltrim($path, '/');
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host = getenv('ENV_DB_HOST') ?: '127.0.0.1';
    $name = getenv('ENV_DB_NAME') ?: 'environment_management';
    $user = getenv('ENV_DB_USER') ?: 'root';
    $pass = getenv('ENV_DB_PASS') ?: '';
    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('Invalid request token. Refresh the page and try again.');
    }
}

function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . app_url('login.php'));
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Administrator access required.');
    }
}

function log_activity(PDO $pdo, ?int $userId, string $action, string $details = ''): void {
    $stmt = $pdo->prepare('INSERT INTO audit_log (user_id, action, details) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $action, $details]);
}

function time_ago(?string $datetime): string {
    if (!$datetime) return 'No data received';
    $seconds = max(0, time() - strtotime($datetime . ' UTC'));
    if ($seconds < 60) return 'Just now';
    if ($seconds < 3600) return floor($seconds / 60) . ' minutes ago';
    if ($seconds < 86400) return floor($seconds / 3600) . ' hours ago';
    return floor($seconds / 86400) . ' days ago';
}

function database_error_message(): string {
    return 'Database is not ready. Import assets/sql/environment_management.sql in phpMyAdmin, then reload this page.';
}

function layout_start(string $title, string $active): void {
    $userName = $_SESSION['name'] ?? 'Faculty';
    $role = $_SESSION['role'] ?? 'faculty';
    $nameParts = preg_split('/\s+/', trim($userName)) ?: [];
    $initials = strtoupper(substr($nameParts[0] ?? 'F', 0, 1) . (count($nameParts) > 1 ? substr(end($nameParts), 0, 1) : ''));
    $facultyNav = [
        ['Summary', 'pages/summary.php', 'layout-dashboard', 'summary'],
        ['Temperature', 'pages/parameter.php?sensor=temperature', 'thermometer', 'temperature'],
        ['Humidity', 'pages/parameter.php?sensor=humidity', 'droplets', 'humidity'],
        ['Air quality', 'pages/parameter.php?sensor=air', 'wind', 'air'],
        ['Light intensity', 'pages/parameter.php?sensor=light', 'sun', 'light'],
        ['Alerts', 'pages/alerts.php', 'bell', 'alerts'],
        ['History', 'pages/history.php', 'history', 'history'],
        ['Reports', 'pages/reports.php', 'file-chart-column', 'reports'],
        ['Comparison', 'pages/comparison.php', 'chart-no-axes-combined', 'comparison'],
        ['Device settings', 'pages/device.php', 'settings-2', 'device'],
    ];
    $adminNav = [
        ['Admin overview', 'pages/admin.php', 'layout-dashboard', 'admin'],
        ['Devices and LCD', 'pages/admin_devices.php', 'cpu', 'admin_devices'],
        ['Users and roles', 'pages/admin_users.php', 'users', 'admin_users'],
        ['Thresholds and night window', 'pages/admin_thresholds.php', 'sliders-horizontal', 'admin_thresholds'],
        ['Notifications', 'pages/admin_notifications.php', 'bell', 'admin_notifications'],
        ['Data and storage', 'pages/admin_storage.php', 'database', 'admin_storage'],
        ['Activity log', 'pages/admin_log.php', 'history', 'admin_log'],
    ];
    $nav = $role === 'admin' ? array_merge($facultyNav, $adminNav) : $facultyNav;
    $selectedDevice = filter_var($_GET['device_id'] ?? null, FILTER_VALIDATE_INT);
    $navDeviceId = $selectedDevice && $selectedDevice > 0 ? (int)$selectedDevice : null;
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="theme-color" content="#f4f8f4">
      <title><?= e($title) ?> · Classroom Environment Monitor</title>
      <script>try{const get=n=>{const c=document.cookie.split('; ').find(v=>v.startsWith('enviro-'+n+'='));if(c)return decodeURIComponent(c.slice(c.indexOf('=')+1));try{return localStorage.getItem('enviro-'+n)}catch{return null}};const s=get('font-size'),t=get('theme'),m=get('motion'),r=document.documentElement;r.dataset.theme=t==='dark'?'dark':'light';r.dataset.fontSize=['small','default','large'].includes(s)?s:'default';r.dataset.motion=m==='off'?'off':'on';r.classList.add('site-loading');document.addEventListener('DOMContentLoaded',()=>r.classList.remove('site-loading'),{once:true})}catch{}</script>
      <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css?v=20261029-6')) ?>">
      <link rel="stylesheet" href="<?= e(app_url('responsiveness/desktop/desktop.css')) ?>">
      <link rel="stylesheet" href="<?= e(app_url('responsiveness/laptop/laptop.css')) ?>">
      <link rel="stylesheet" href="<?= e(app_url('responsiveness/tablet/tablet.css?v=20261024-1')) ?>">
      <link rel="stylesheet" href="<?= e(app_url('responsiveness/mobile/mobile.css?v=20261029-11')) ?>">
      <script src="https://unpkg.com/lucide@latest"></script>
      <script src="<?= e(app_url('assets/js/app.js?v=20261029-3')) ?>" defer></script>
    </head>
    <body class="app-page app-page-<?= e($active) ?> app-role-<?= e($role) ?>" data-app-base="<?= e(app_url()) ?>" data-cache-scope="<?= (int)($_SESSION['user_id'] ?? 0) ?>">
      <header class="mobile-topbar">
        <strong><span class="mobile-brand-full"><?= $role === 'admin' ? 'Environment Monitor' : 'Classroom Environment Monitor' ?></span><span class="mobile-brand-short"><?= $role === 'admin' ? 'Environment Monitor' : 'CEM' ?></span></strong>
        <?php if ($role === 'admin'): ?><a class="mobile-profile mobile-admin-profile" href="<?= e(app_url('pages/settings.php')) ?>" aria-label="Account settings"><span class="mobile-avatar"><?= e($initials) ?></span></a><?php else: ?><a class="mobile-profile" href="<?= e(app_url('pages/settings.php')) ?>"><span class="mobile-avatar"><?= e($initials) ?></span><span>Profile</span></a><?php endif; ?>
      </header>
      <nav class="tablet-nav" aria-label="Main navigation">
        <?php foreach (array_slice($nav, 0, 3) as [$label, $href, $icon, $key]): $navHref = $navDeviceId ? $href . (str_contains($href, '?') ? '&' : '?') . 'device_id=' . $navDeviceId : $href; ?>
          <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(app_url($navHref)) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>><i data-lucide="<?= e($icon) ?>" aria-hidden="true"></i><span><?= $role === 'admin' && $key === 'admin_devices' ? 'Devices' : ($role === 'admin' && $key === 'admin_users' ? 'Users' : e($label)) ?></span></a>
        <?php endforeach; ?>
        <details class="tablet-more <?= in_array($active, array_column(array_slice($nav, 3), 3), true) ? 'has-active' : '' ?>">
          <summary class="nav-link"><i data-lucide="sliders-horizontal" aria-hidden="true"></i><span>More</span></summary>
          <button class="mobile-more-backdrop" type="button" aria-label="Close menu"></button>
          <div class="tablet-more-menu">
            <div class="mobile-more-heading"><span class="mobile-sheet-grip"></span><strong>More</strong><p>Explore more tools</p><button class="mobile-more-close" type="button" aria-label="Close menu">×</button></div>
            <div class="mobile-more-links">
              <?php foreach (array_slice($nav, 3) as [$label, $href, $icon, $key]): $navHref = $navDeviceId ? $href . (str_contains($href, '?') ? '&' : '?') . 'device_id=' . $navDeviceId : $href; ?>
                <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(app_url($navHref)) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>><i data-lucide="<?= e($icon) ?>" aria-hidden="true"></i><span><?= e($label) ?></span></a>
              <?php endforeach; ?>
            </div>
            <a class="mobile-account-row" href="<?= e(app_url('pages/settings.php')) ?>"><span class="mobile-avatar"><?= e($initials) ?></span><span><strong><?= e($userName) ?></strong><small><?= e(ucfirst($role)) ?> · Account settings</small></span></a>
            <form class="mobile-logout-form" method="post" action="<?= e(app_url('logout.php')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="mobile-logout" type="submit"><i data-lucide="log-out" aria-hidden="true"></i>Log out</button></form>
          </div>
        </details>
      </nav>
    <div class="app-shell" id="top">
      <aside class="sidebar" aria-label="Main navigation">
        <div class="brand-row">
          <div class="brand"><strong>Classroom Environment Monitor</strong></div>
          <button class="sidebar-toggle" type="button" aria-label="Hide navigation" aria-expanded="true" title="Hide navigation"><i class="toggle-expanded" data-lucide="panel-left-close" aria-hidden="true"></i><i class="toggle-collapsed" data-lucide="chevron-right" aria-hidden="true"></i></button>
        </div>
        <nav class="primary-nav">
          <?php foreach ($nav as [$label, $href, $icon, $key]): $navHref = $navDeviceId ? $href . (str_contains($href, '?') ? '&' : '?') . 'device_id=' . $navDeviceId : $href; ?>
            <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(app_url($navHref)) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>>
              <i data-lucide="<?= e($icon) ?>" aria-hidden="true"></i><span><?= e($label) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
        <div class="sidebar-bottom">
          <div class="user-card">
          <details class="profile-menu">
            <summary class="profile-summary"><div class="user-details"><span class="avatar" aria-hidden="true"><?= e($initials) ?></span><div><strong><?= e($userName) ?></strong><span class="role"><?= e(ucfirst($role)) ?></span><small>CITCS</small></div></div><i data-lucide="ellipsis" aria-hidden="true"></i></summary>
            <div class="profile-popover">
              <a href="<?= e(app_url('pages/settings.php')) ?>"><i data-lucide="settings-2" aria-hidden="true"></i>Settings</a>
              <a href="<?= e(app_url('pages/about.php')) ?>"><i data-lucide="info" aria-hidden="true"></i>About</a>
              <?php if ($role === 'admin'): ?><a href="<?= e(app_url('pages/admin.php')) ?>"><i data-lucide="layout-dashboard" aria-hidden="true"></i>Admin panel</a><?php endif; ?>
            </div>
          </details>
          <form method="post" action="<?= e(app_url('logout.php')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="logout" type="submit"><i data-lucide="log-out" aria-hidden="true"></i>Log out</button></form>
          </div>
        </div>
      </aside>
      <main class="main-content">
        <header class="page-header"><div class="page-header-copy"><h1><?= e($title) ?></h1><p id="page-subtitle"><?= $active === 'summary' ? 'Room 301 · Sample data · Updated 1 minute ago' : 'Classroom Environment Monitor' ?></p></div><span class="connection-dot" id="connection-dot" aria-hidden="true"></span></header>
    <?php
}

function layout_end(): void {
    ?>
        <p class="prototype-note">Live readings are stored by the website database. Sensor thresholds and unit calibration must match the installed hardware.</p>
      </main>
    </div>
    </body>
    </html>
    <?php
}

function get_active_device(PDO $pdo, ?int $deviceId = null): ?array {
    if ($deviceId === null && isset($_GET['device_id'])) {
        $queryId = filter_var($_GET['device_id'], FILTER_VALIDATE_INT);
        if ($queryId !== false && $queryId > 0) $deviceId = (int)$queryId;
    }
    if ($deviceId !== null && $deviceId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM devices WHERE id=? LIMIT 1');
        $stmt->execute([$deviceId]);
        $device = $stmt->fetch();
        if ($device) return $device;
    }
    return $pdo->query('SELECT * FROM devices WHERE is_active = 1 ORDER BY id LIMIT 1')->fetch() ?: null;
}

function get_latest_reading(PDO $pdo, int $deviceId): ?array {
    $stmt = $pdo->prepare('SELECT * FROM readings WHERE device_id = ? ORDER BY measured_at DESC, id DESC LIMIT 1');
    $stmt->execute([$deviceId]);
    return $stmt->fetch() ?: null;
}

function sensor_chart_svg(array $rows, string $field = 'value'): string {
    $values = array_values(array_filter(array_map(static fn($row) => is_numeric($row[$field] ?? null) ? (float)$row[$field] : null, array_reverse($rows)), static fn($value) => $value !== null));
    if (!$values) return '';
    $min = min($values); $max = max($values); $range = max(1, $max - $min); $last = count($values) - 1;
    $points = [];
    foreach ($values as $i => $value) $points[] = number_format(24 + ($last ? $i * 712 / $last : 356), 1, '.', '') . ',' . number_format(108 - (($value - $min) / $range) * 88, 1, '.', '');
    return '<svg class="sensor-history-chart" viewBox="0 0 760 128" role="img" aria-label="Sensor readings over time"><path class="chart-gridline" d="M24 20H736 M24 64H736 M24 108H736"/><polyline points="' . e(implode(' ', $points)) . '"/><circle cx="' . e(explode(',', $points[$last])[0]) . '" cy="' . e(explode(',', $points[$last])[1]) . '" r="4"/></svg>';
}

function dashboard_data(PDO $pdo, ?int $deviceId = null): array {
    $device = get_active_device($pdo, $deviceId);
    if (!$device) return ['device' => null, 'reading' => null, 'alerts' => [], 'open_alerts' => 0, 'coverage' => null];

    $reading = get_latest_reading($pdo, (int)$device['id']);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM alerts WHERE device_id = ? AND acknowledged_at IS NULL AND closed_at IS NULL");
    $stmt->execute([$device['id']]);
    $openAlerts = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT * FROM alerts WHERE device_id = ? ORDER BY created_at DESC LIMIT 5');
    $stmt->execute([$device['id']]);
    $alerts = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM readings WHERE device_id = ? AND measured_at >= UTC_DATE()');
    $stmt->execute([$device['id']]);
    $count = (int)$stmt->fetchColumn();
    $expected = max(1, (int)floor(86400 / max(10, (int)$device['sample_interval_seconds'])));
    return ['device' => $device, 'reading' => $reading, 'alerts' => $alerts, 'open_alerts' => $openAlerts, 'coverage' => min(100, round($count * 100 / $expected, 1))];
}

function set_sensor_alert(PDO $pdo, int $deviceId, string $sensor, bool $breached, string $severity, string $message, int $cooldown): void {
    $stmt = $pdo->prepare('SELECT id FROM alerts WHERE device_id=? AND sensor=? AND closed_at IS NULL ORDER BY id DESC LIMIT 1');
    $stmt->execute([$deviceId, $sensor]);
    $activeId = $stmt->fetchColumn();
    if ($breached && !$activeId) {
        $cooldown = max(0, min(1440, $cooldown));
        $stmt = $pdo->prepare("SELECT id FROM alerts WHERE device_id=? AND sensor=? AND created_at >= UTC_TIMESTAMP() - INTERVAL {$cooldown} MINUTE ORDER BY id DESC LIMIT 1");
        $stmt->execute([$deviceId, $sensor]);
        if (!$stmt->fetchColumn()) {
            $stmt = $pdo->prepare('INSERT INTO alerts (device_id, sensor, severity, message) VALUES (?, ?, ?, ?)');
            $stmt->execute([$deviceId, $sensor, $severity, $message]);
        }
    } elseif (!$breached && $activeId) {
        $stmt = $pdo->prepare('UPDATE alerts SET closed_at=UTC_TIMESTAMP() WHERE id=?');
        $stmt->execute([$activeId]);
    }
}

function evaluate_reading_alerts(PDO $pdo, array $device, array $reading): void {
    $deviceId = (int)$device['id'];
    $cooldown = (int)$device['alert_cooldown_minutes'];
    foreach ([
        ['temperature', 'temperature_c', $device['temp_min'], $device['temp_max'], 'Temperature is outside its configured range.'],
        ['humidity', 'humidity_pct', $device['humidity_min'], $device['humidity_max'], 'Humidity is outside its configured range.'],
    ] as [$sensor, $column, $minimum, $maximum, $message]) {
        $current = $reading[$column] === null ? null : (float)$reading[$column];
        if ($current === null) continue;
        $stmt = $pdo->prepare("SELECT {$column} FROM readings WHERE device_id=? ORDER BY measured_at DESC, id DESC LIMIT 3");
        $stmt->execute([$deviceId]);
        $values = array_map('floatval', array_values(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN), static fn($value) => $value !== null)));
        $low = count($values) === 3 && max($values) < (float)$minimum;
        $high = count($values) === 3 && min($values) > (float)$maximum;
        set_sensor_alert($pdo, $deviceId, $sensor, $low || $high, 'warning', $message, $cooldown);
    }

    $airWarning = $device['air_warning_adc'] === null ? null : (int)$device['air_warning_adc'];
    if ($airWarning !== null) {
        $stmt = $pdo->prepare('SELECT air_adc FROM readings WHERE device_id=? ORDER BY measured_at DESC, id DESC LIMIT 3');
        $stmt->execute([$deviceId]);
        $values = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $breached = count($values) === 3 && min($values) >= $airWarning;
        $airAlert = $device['air_alert_adc'] === null ? null : (int)$device['air_alert_adc'];
        $severity = $breached && $airAlert !== null && min($values) >= $airAlert ? 'alert' : 'warning';
        set_sensor_alert($pdo, $deviceId, 'air', $breached, $severity, 'MQ-135 raw reading is above its calibrated threshold.', $cooldown);
    }

    $lightThreshold = $device['light_on_adc'] === null ? null : (int)$device['light_on_adc'];
    if ($lightThreshold !== null) {
        $localTime = date('H:i:s');
        $start = (string)$device['light_night_start'];
        $end = (string)$device['light_night_end'];
        $isNight = $start <= $end ? ($localTime >= $start && $localTime <= $end) : ($localTime >= $start || $localTime <= $end);
        $breached = false;
        if ($isNight) {
            $duration = max(1, (int)$device['light_min_duration_minutes']);
            $interval = max(10, (int)$device['sample_interval_seconds']);
            $required = max(2, (int)floor($duration * 60 / $interval) - 1);
            $stmt = $pdo->query("SELECT COUNT(*) AS n, MIN(light_adc) AS minimum FROM readings WHERE device_id={$deviceId} AND measured_at >= UTC_TIMESTAMP() - INTERVAL {$duration} MINUTE");
            $window = $stmt->fetch();
            $breached = (int)$window['n'] >= $required && (int)$window['minimum'] >= $lightThreshold;
        }
        set_sensor_alert($pdo, $deviceId, 'light', $breached, 'alert', 'Room light has stayed above its calibrated night threshold.', $cooldown);
    }
}

function reading_status(?float $value, ?float $min, ?float $max, string $unit = ''): array {
    if ($value === null) return ['Unavailable', 'neutral'];
    if (($min !== null && $value < $min) || ($max !== null && $value > $max)) return ['Needs attention', 'warning'];
    if ($min === null && $max === null) return ['Uncalibrated', 'neutral'];
    return ['Normal', 'ok'];
}

function database_page_notice(): void {
    ?>
    <section class="content-card setup-notice">
      <h2>Database setup needed</h2>
      <p><?= e(database_error_message()) ?></p>
      <ol><li>Open phpMyAdmin from XAMPP.</li><li>Import the SQL file from the project folder.</li><li>Reload the sign-in page.</li></ol>
      <a class="button-link" href="<?= e(app_url('login.php')) ?>">Open sign in</a>
    </section>
    <?php
}

