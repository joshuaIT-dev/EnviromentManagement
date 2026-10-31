<?php
require_once __DIR__ . '/../assets/php/layout.php';
$devices = [];
$history = [];
try {
    $pdo = db();
    $devices = $pdo->query('SELECT id,name,location,is_active FROM devices ORDER BY is_active DESC,name,id')->fetchAll();
    $requestedId = filter_var($_GET['device_id'] ?? null, FILTER_VALIDATE_INT);
    if ((!$requestedId || $requestedId < 1) && $devices && !array_filter($devices, static fn($row) => (int)$row['is_active'] === 1)) $requestedId = (int)$devices[0]['id'];
    $data = dashboard_data($pdo, $requestedId && $requestedId > 0 ? (int)$requestedId : null);
    $device = $data['device'];
    $reading = $data['reading'];
    if ($device) {
        $stmt = $pdo->prepare('SELECT temperature_c,humidity_pct,air_adc,light_adc FROM readings WHERE device_id=? ORDER BY measured_at DESC,id DESC LIMIT 100');
        $stmt->execute([$device['id']]);
        $history = $stmt->fetchAll();
    }
    $demo = !$device;
    $waiting = $device && !$reading;
} catch (Throwable $exception) {
    $data = ['device' => null, 'reading' => null, 'alerts' => [], 'open_alerts' => 0, 'coverage' => null];
    $devices = [];
    $device = $reading = null;
    $demo = true;
    $waiting = false;
}
$deviceQuery = $device ? '&device_id=' . (int)$device['id'] : '';
layout_start('Classroom conditions now', 'summary');
?>
<?php if ($devices): ?><section class="summary-device-picker" aria-label="Choose monitored device"><div><span class="device-picker-kicker">SYSTEM VIEW</span><strong>Choose a device</strong><p><?= $device ? e($device['location']) . ' · ' . e($device['name']) : 'Select a device' ?></p></div><form class="summary-device-form" method="get"><label for="summary-device">Device<select id="summary-device" name="device_id"><?php foreach ($devices as $option): ?><option value="<?= (int)$option['id'] ?>" <?= $device && (int)$device['id'] === (int)$option['id'] ? 'selected' : '' ?>>Device #<?= (int)$option['id'] ?> · <?= e($option['name']) ?> · <?= e($option['location']) ?><?= $option['is_active'] ? '' : ' (Disabled)' ?></option><?php endforeach; ?></select></label><button class="button-primary small" type="submit">View device</button></form></section><?php endif; ?>
<section class="overall-status" aria-live="polite">
  <span class="status-pill <?= $demo ? 'warning' : (!$device['is_active'] ? 'neutral' : ($waiting ? 'neutral' : ($data['open_alerts'] ? 'warning' : 'ok'))) ?>" id="overall-pill"><?php if ($demo): ?><span class="desktop-status-label">Sample data</span><span class="mobile-status-label">Design preview</span><?php elseif (!$device['is_active']): ?>Device disabled<?php elseif ($waiting): ?>Waiting for data<?php else: ?><?= $data['open_alerts'] ? $data['open_alerts'] . ' open alerts' : 'Readings received' ?><?php endif; ?></span>
  <p id="overall-message"><?= $demo ? 'Design preview · connect a device to replace these sample readings.' : (!$device['is_active'] ? e($device['location']) . ' · Showing saved data; this device is disabled.' : ($waiting ? e($device['location']) . ' · Waiting for the first sensor reading.' : e($device['location']) . ' · ' . ($data['open_alerts'] ? 'Review active alerts below.' : 'Latest readings from the ESP32.'))) ?></p>
</section>
<p class="cache-note" id="cache-note" role="status" hidden></p>
<?php if (!$device): ?>
<div class="inline-notice"><strong>No device is registered yet</strong><span>Connect an ESP32 to replace the sample readings.</span></div>
<?php elseif ($waiting): ?>
<div class="inline-notice"><strong><?= $device['is_active'] ? 'Waiting for this device' : 'This device is disabled' ?></strong><span><?= $device['is_active'] ? e($device['name']) . ' is selected. Its live readings will appear after its first upload.' : 'Enable ' . e($device['name']) . ' in Admin → Devices to resume live readings.' ?></span></div>
<?php endif; ?>
<section class="reading-grid" aria-label="Current sensor readings">
<?php
$sample = [
 'temperature' => ['label'=>'Temperature','value'=>'27.4','unit'=>'°C','status'=>'Normal','tone'=>'ok','spark'=>'sparkline-temperature.svg'],
 'humidity' => ['label'=>'Humidity','value'=>'58','unit'=>'%','status'=>'Normal','tone'=>'ok','spark'=>'sparkline-humidity.svg'],
 'air' => ['label'=>'Air quality','value'=>'142','unit'=>'index','status'=>'Needs attention','tone'=>'warning','spark'=>'sparkline-air-quality.svg'],
 'light' => ['label'=>'Light','value'=>'320','unit'=>'lux','status'=>'Day mode','tone'=>'neutral','spark'=>'sparkline-light.svg'],
];
?>
<?php foreach ($sample as $key=>$item):
  if (!$demo && $reading) {
    if ($key === 'temperature') [$status,$tone] = reading_status($reading['temperature_c'] === null ? null : (float)$reading['temperature_c'], (float)$device['temp_min'], (float)$device['temp_max']);
    elseif ($key === 'humidity') [$status,$tone] = reading_status($reading['humidity_pct'] === null ? null : (float)$reading['humidity_pct'], (float)$device['humidity_min'], (float)$device['humidity_max']);
    else [$status,$tone] = ['Uncalibrated','neutral'];
    $value = $key === 'temperature' ? ($reading['temperature_c'] === null ? '—' : number_format((float)$reading['temperature_c'],1)) : ($key === 'humidity' ? ($reading['humidity_pct'] === null ? '—' : number_format((float)$reading['humidity_pct'],1)) : (string)$reading[$key === 'air' ? 'air_adc' : 'light_adc']);
    $unit = $key === 'temperature' ? '°C' : ($key === 'humidity' ? '%' : 'raw ADC');
  } elseif ($waiting) {
    $status='Waiting for data'; $tone='neutral'; $value='—'; $unit=$key === 'temperature' ? '°C' : ($key === 'humidity' ? '%' : 'raw ADC');
  } else { $status=$item['status']; $tone=$item['tone']; $value=$item['value']; $unit=$item['unit']; }
?>
  <article class="reading-card" data-sensor="<?= e($key) ?>">
    <h2><?= e($item['label']) ?></h2>
    <div class="reading-value"><strong data-value><?= e($value) ?></strong><span data-unit><?= e($unit) ?></span></div>
    <span class="status-pill <?= e($tone) ?>" data-status><?= e($status) ?></span>
    <div class="reading-chart"><?php if ($demo): ?><img class="sparkline" src="<?= e(app_url('assets/images/' . $item['spark'])) ?>" alt="" width="235" height="36"><?php elseif ($waiting): ?><div class="sparkline-placeholder">Waiting for first reading</div><?php else: ?><div class="sparkline-placeholder">Trend builds as readings arrive</div><?php endif; ?></div>
    <a class="details-link" href="<?= e(app_url('pages/parameter.php?sensor=' . urlencode($key) . $deviceQuery)) ?>">View details</a>
  </article>
<?php endforeach; ?>
</section>

<section class="trend-grid" aria-label="Sensor trends">
<?php
$charts=[
 ['temperature','Temperature','27.4 °C, last 24 hours','trend-temperature.svg','Blue: cool. Green: comfortable. Amber: above 30 °C.'],
 ['humidity','Humidity','58 %, last 24 hours','trend-humidity.svg','Green: comfortable (30 to 70 %). Amber: dry. Blue: humid.'],
 ['air','Air quality','142 index, last 24 hours','trend-air-quality.svg','Zones: normal, warning above 120, alert above 150.'],
];
foreach ($charts as [$key,$label,$meta,$file,$legend]):
?>
<article class="trend-card">
  <header class="trend-header"><h2><?= e($label) ?> <span class="desktop-trend-meta"><?= e($demo ? $meta : 'Live history') ?></span><span class="mobile-trend-meta">, last 24 hours</span></h2><a href="<?= e(app_url('pages/parameter.php?sensor=' . $key . $deviceQuery)) ?>">View details</a></header>
  <?php if ($demo): ?><div class="trend-image-wrap"><img class="trend-image" src="<?= e(app_url('assets/images/' . $file)) ?>" alt="<?= e($label) ?> sample trend" width="<?= $key==='humidity'?'402':'462' ?>" height="130"></div><p class="chart-legend"><span class="desktop-chart-legend"><?= e($legend) ?></span><span class="mobile-chart-legend">Shaded: normal 18 to 30 °C. Amber: above the limit.</span></p>
  <?php elseif ($waiting): ?><div class="empty-chart">Waiting for the first reading from this device.</div>
  <?php else: ?><?= sensor_chart_svg($history, ['temperature'=>'temperature_c','humidity'=>'humidity_pct','air'=>'air_adc'][$key]) ?><p class="chart-legend">Last <?= count($history) ?> readings · newest at right<?php if ($key === 'air'): ?> · raw ADC, not calibrated AQI<?php endif; ?></p><?php endif; ?>
</article>
<?php endforeach; ?>
<article class="trend-card"><header class="trend-header"><h2>Light <span><?= $demo?'Day mode now':'Raw sensor value' ?></span></h2><a href="<?= e(app_url('pages/parameter.php?sensor=light' . $deviceQuery)) ?>">View details</a></header><?php if($demo): ?><div class="light-timeline"><div class="timeline-top"><span>Day</span><span>Night window</span><span>Day</span></div><div class="timeline-row"><span class="room-label">Room 301</span><div class="timeline-track"><i class="day-window"></i><i class="night-off">Light off</i><i class="day-window end"></i></div></div><div class="timeline-row"><span class="room-label">Room 302</span><div class="timeline-track"><i class="day-window"></i><i class="light-on"></i><i class="night-off"></i><i class="day-window end"></i></div></div><p class="light-event">Sample event · 53 minutes</p><div class="timeline-x"><span>6 PM</span><span>9 PM</span><span>1 AM</span><span>5 AM</span><span>8 AM</span></div></div><p class="chart-legend">Figma sample. The light alert rule needs calibration.</p><?php elseif($waiting): ?><div class="empty-chart">Waiting for the first reading from this device.</div><?php else: ?><?= sensor_chart_svg($history, 'light_adc') ?><p class="chart-legend">Last <?= count($history) ?> readings · raw ADC, not calibrated lux</p><?php endif; ?></article>
</section>

<section class="health-grid" aria-label="Device status">
  <article class="health-card"><h2>Last data received</h2><span class="status-pill <?= $demo?'warning':(!$device['is_active']?'neutral':'ok') ?>" id="last-data"><?= e($reading ? time_ago($reading['measured_at']) : 'Waiting for device') ?></span><p><?= e($device['name'] ?? 'ESP32 device not registered') ?></p></article>
  <article class="health-card"><h2>Data coverage today</h2><strong id="coverage-value"><?= $data['coverage'] === null ? '—' : e((string)$data['coverage']) . '%' ?></strong><p>Based on configured reading interval</p></article>
  <article class="health-card"><h2>Open alerts</h2><strong id="open-alert-count"><?= (int)$data['open_alerts'] ?></strong><p>Unacknowledged active alerts</p></article>
</section>

<section class="alerts-card">
  <div class="section-heading"><h2>Active alerts</h2><a href="<?= e(app_url('pages/alerts.php')) ?>">View all alerts</a></div>
  <?php if ($demo): ?><p class="empty-state">Figma sample alerts will be replaced by real alerts after the alert rules are configured.</p>
  <div class="mobile-demo-alerts">
    <article class="mobile-demo-alert"><div class="mobile-demo-alert-head"><span class="status-pill warning">Warning</span><strong>Air quality, Room 301</strong></div><p>Index 142 is above the limit of 120</p><div class="mobile-demo-alert-foot"><span>Oct 12, 9:48 AM</span><a href="<?= e(app_url('pages/alerts.php')) ?>">Acknowledge</a></div></article>
    <article class="mobile-demo-alert"><div class="mobile-demo-alert-head"><span class="status-pill alert">Alert</span><strong>Light, Room 302</strong></div><p>Left on for 53 minutes (182 lux)</p><div class="mobile-demo-alert-foot"><span>Oct 11, 10:12 PM</span><a href="<?= e(app_url('pages/alerts.php')) ?>">Acknowledge</a></div></article>
  </div>
  <?php elseif (!$data['alerts']): ?><p class="empty-state">No active alerts.</p>
  <?php else: ?><div class="table-scroll"><table><thead><tr><th>Time</th><th>Parameter</th><th>Message</th><th>Severity</th></tr></thead><tbody><?php foreach ($data['alerts'] as $alert): ?><tr><td><?= e($alert['created_at']) ?></td><td><?= e(ucfirst($alert['sensor'])) ?></td><td><?= e($alert['message']) ?></td><td><span class="status-pill <?= e($alert['severity']==='alert'?'alert':'warning') ?>"><?= e(ucfirst($alert['severity'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php layout_end(); ?>

