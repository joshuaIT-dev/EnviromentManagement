<?php
require_once __DIR__ . '/../assets/php/layout.php';
require_admin();

$devices = $alerts = [];
$deviceCount = $onlineCount = $readingCount = $expectedCount = $warningCount = $alertCount = $openCount = 0;
$storageMb = 0.0;
$error = '';
try {
    $pdo = db();
    $devices = $pdo->query('SELECT id,name,location,is_active,sample_interval_seconds,offline_timeout_minutes,last_seen_at FROM devices ORDER BY id')->fetchAll();
    foreach ($devices as $device) {
        if (!(int)$device['is_active']) continue;
        $deviceCount++;
        $lastSeen = $device['last_seen_at'] ? strtotime($device['last_seen_at'] . ' UTC') : false;
        if ($lastSeen !== false && $lastSeen >= time() - max(1, (int)$device['offline_timeout_minutes']) * 60) $onlineCount++;
        $expectedCount += (int)floor(86400 / max(10, (int)$device['sample_interval_seconds']));
    }
    $readingCount = (int)$pdo->query('SELECT COUNT(*) FROM readings WHERE measured_at >= UTC_TIMESTAMP() - INTERVAL 24 HOUR')->fetchColumn();
    $severityCounts = $pdo->query("SELECT severity,COUNT(*) AS total FROM alerts WHERE closed_at IS NULL GROUP BY severity")->fetchAll(PDO::FETCH_KEY_PAIR);
    $warningCount = (int)($severityCounts['warning'] ?? 0); $alertCount = (int)($severityCounts['alert'] ?? 0); $openCount = $warningCount + $alertCount;
    $alerts = $pdo->query("SELECT a.id,a.sensor,a.severity,a.message,a.created_at,d.name,d.location FROM alerts a JOIN devices d ON d.id=a.device_id WHERE a.closed_at IS NULL ORDER BY a.created_at DESC LIMIT 6")->fetchAll();
    $storageMb = (float)$pdo->query('SELECT COALESCE(SUM(DATA_LENGTH+INDEX_LENGTH),0)/1024/1024 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn();
} catch (Throwable $exception) {
    $error = database_error_message();
}
$coverage = $expectedCount ? min(100, round($readingCount * 100 / $expectedCount, 1)) : 0;
$attention = [];
foreach ($devices as $device) {
    if (!(int)$device['is_active']) continue;
    $lastSeen = $device['last_seen_at'] ? strtotime($device['last_seen_at'] . ' UTC') : false;
    if ($lastSeen === false || $lastSeen < time() - max(1, (int)$device['offline_timeout_minutes']) * 60) {
        $attention[] = ['title' => $device['location'] . ' · ' . $device['name'], 'detail' => 'Last data received ' . time_ago($device['last_seen_at']), 'status' => 'Offline', 'tone' => 'alert'];
    }
}
foreach ($alerts as $alert) {
    $attention[] = ['title' => $alert['location'] . ' · ' . ucfirst($alert['sensor']), 'detail' => $alert['message'], 'status' => ucfirst($alert['severity']), 'tone' => $alert['severity']];
}
layout_start('Admin overview', 'admin');
?>
<?php if ($error): ?><p class="inline-notice" role="alert"><?= e($error) ?></p><?php endif; ?>
<section class="health-grid admin-stats" aria-label="System health">
  <article class="health-card"><h2>Devices online</h2><strong><?= $onlineCount ?> of <?= $deviceCount ?></strong><p><?= $deviceCount - $onlineCount ?> offline</p></article>
  <article class="health-card"><h2>Open alerts</h2><strong><?= $openCount ?></strong><p><?= $warningCount ?> warning · <?= $alertCount ?> alert</p></article>
  <article class="health-card"><h2>Data received, last 24 hours</h2><strong><?= number_format($readingCount) ?> / <?= number_format($expectedCount) ?></strong><p><?= number_format($coverage, 1) ?>% expected</p></article>
</section>
<section class="admin-overview-attention" aria-label="Needs attention">
  <h2>Needs attention</h2>
  <?php if (!$attention): ?><article class="mobile-admin-card"><div><strong>Everything looks good</strong><small>No devices or alerts need attention.</small></div></article>
  <?php else: foreach (array_slice($attention, 0, 5) as $item): ?><article class="mobile-admin-card"><div><strong><?= e($item['title']) ?></strong><small><?= e($item['detail']) ?></small></div><span class="status-pill <?= e($item['tone']) ?>"><?= e($item['status']) ?></span></article><?php endforeach; endif; ?>
</section>
<section class="content-card admin-storage-card">
  <div class="section-heading"><div><h2>Database usage</h2><p>Current MySQL database size</p></div><span class="status-pill neutral"><?= number_format($storageMb, 1) ?> MB of 100 MB</span></div>
  <div class="storage-meter" role="progressbar" aria-label="Database usage" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)min(100, round($storageMb)) ?>"><span style="width:<?= (int)min(100, round($storageMb)) ?>%"></span></div>
  <p class="chart-legend"><?= number_format($storageMb, 1) ?> MB used. Normal below 70%; review storage at 70%, critical at 85%, emergency at 95%.</p>
  <a class="details-link" href="<?= e(app_url('pages/admin_storage.php')) ?>">Review data and storage</a>
</section>
<?php layout_end(); ?>
