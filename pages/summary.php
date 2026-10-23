<?php
require_once __DIR__ . '/../assets/php/layout.php';
try {
    $data = dashboard_data(db());
    $device = $data['device'];
    $reading = $data['reading'];
    $demo = !$device || !$reading;
} catch (Throwable $exception) {
    $data = ['device' => null, 'reading' => null, 'alerts' => [], 'open_alerts' => 0, 'coverage' => null];
    $device = $reading = null;
    $demo = true;
}
layout_start('Classroom conditions now', 'summary');
?>
<section class="overall-status" aria-live="polite">
  <span class="status-pill <?= $demo ? 'warning' : ($data['open_alerts'] ? 'warning' : 'ok') ?>" id="overall-pill"><?= $demo ? 'Sample data' : ($data['open_alerts'] ? $data['open_alerts'] . ' open alerts' : 'Readings received') ?></span>
  <p id="overall-message"><?= $demo ? 'Design preview · connect a device to replace these sample readings.' : e($device['location']) . ' · ' . ($data['open_alerts'] ? 'Review active alerts below.' : 'Latest readings from the ESP32.') ?></p>
</section>
<?php if (!$device): ?>
<div class="inline-notice">No device is registered yet. An administrator can add the ESP32 in the admin panel.</div>
<?php endif; ?>
<section class="reading-grid" aria-label="Current sensor readings">
<?php
$sample = [
 'temperature' => ['label'=>'Temperature','value'=>'27.4','unit'=>'°C','status'=>'Normal','tone'=>'ok','spark'=>'sparkline-temperature.svg'],
 'humidity' => ['label'=>'Humidity','value'=>'58','unit'=>'%','status'=>'Normal','tone'=>'ok','spark'=>'sparkline-humidity.svg'],
 'air' => ['label'=>'Air quality','value'=>'142','unit'=>'index','status'=>'Needs attention','tone'=>'warning','spark'=>'sparkline-air-quality.svg'],
 'light' => ['label'=>'Light','value'=>'320','unit'=>'lux','status'=>'Day mode, not judged','tone'=>'neutral','spark'=>'sparkline-light.svg'],
];
foreach ($sample as $key=>$item):
  if (!$demo && $reading) {
    if ($key === 'temperature') [$status,$tone] = reading_status($reading['temperature_c'] === null ? null : (float)$reading['temperature_c'], (float)$device['temp_min'], (float)$device['temp_max']);
    elseif ($key === 'humidity') [$status,$tone] = reading_status($reading['humidity_pct'] === null ? null : (float)$reading['humidity_pct'], (float)$device['humidity_min'], (float)$device['humidity_max']);
    else [$status,$tone] = ['Uncalibrated','neutral'];
    $value = $key === 'temperature' ? ($reading['temperature_c'] === null ? '—' : number_format((float)$reading['temperature_c'],1)) : ($key === 'humidity' ? ($reading['humidity_pct'] === null ? '—' : number_format((float)$reading['humidity_pct'],1)) : (string)$reading[$key === 'air' ? 'air_adc' : 'light_adc']);
    $unit = $key === 'temperature' ? '°C' : ($key === 'humidity' ? '%' : 'raw ADC');
  } else { $status=$item['status']; $tone=$item['tone']; $value=$item['value']; $unit=$item['unit']; }
?>
  <article class="reading-card" data-sensor="<?= e($key) ?>">
    <h2><?= e($item['label']) ?></h2>
    <div class="reading-value"><strong data-value><?= e($value) ?></strong><span data-unit><?= e($unit) ?></span></div>
    <span class="status-pill <?= e($tone) ?>" data-status><?= e($status) ?></span>
    <?php if ($demo): ?><img class="sparkline" src="<?= e(app_url('assets/images/' . $item['spark'])) ?>" alt="" width="235" height="36"><?php else: ?><div class="sparkline-placeholder">Trend builds as readings arrive</div><?php endif; ?>
    <a class="details-link" href="<?= e(app_url('pages/parameter.php?sensor=' . urlencode($key))) ?>">View details</a>
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
  <header class="trend-header"><h2><?= e($label) ?> <span><?= e($demo ? $meta : 'Live history') ?></span></h2><a href="<?= e(app_url('pages/parameter.php?sensor=' . $key)) ?>">View details</a></header>
  <?php if ($demo): ?><img class="trend-image" src="<?= e(app_url('assets/images/' . $file)) ?>" alt="<?= e($label) ?> sample trend from the Figma design" width="<?= $key==='humidity'?'402':'462' ?>" height="130"><p class="chart-legend"><?= e($legend) ?></p>
  <?php else: ?><div class="empty-chart">Historical chart will appear after enough readings are stored.</div><?php endif; ?>
</article>
<?php endforeach; ?>
<article class="trend-card"><header class="trend-header"><h2>Light <span><?= $demo?'Day mode now':'Raw sensor value' ?></span></h2><a href="<?= e(app_url('pages/parameter.php?sensor=light')) ?>">View details</a></header><?php if($demo): ?><div class="light-timeline"><div class="timeline-top"><span>Day</span><span>Night window</span><span>Day</span></div><div class="timeline-row"><span class="room-label">Room 301</span><div class="timeline-track"><i class="day-window"></i><i class="night-off">Light off</i><i class="day-window end"></i></div></div><div class="timeline-row"><span class="room-label">Room 302</span><div class="timeline-track"><i class="day-window"></i><i class="light-on"></i><i class="night-off"></i><i class="day-window end"></i></div></div><p class="light-event">Sample event · 53 minutes</p><div class="timeline-x"><span>6 PM</span><span>9 PM</span><span>1 AM</span><span>5 AM</span><span>8 AM</span></div></div><p class="chart-legend">Figma sample. The light alert rule needs calibration.</p><?php else: ?><div class="empty-chart">Night timeline appears after a calibrated threshold and enough readings are available.</div><?php endif; ?></article>
</section>

<section class="health-grid" aria-label="Device status">
  <article class="health-card"><h2>Last data received</h2><span class="status-pill <?= $demo?'warning':'ok' ?>" id="last-data"><?= e($reading ? time_ago($reading['measured_at']) : 'Waiting for device') ?></span><p><?= e($device['name'] ?? 'ESP32 device not registered') ?></p></article>
  <article class="health-card"><h2>Data coverage today</h2><strong id="coverage-value"><?= $data['coverage'] === null ? '—' : e((string)$data['coverage']) . '%' ?></strong><p>Based on configured reading interval</p></article>
  <article class="health-card"><h2>Open alerts</h2><strong id="open-alert-count"><?= (int)$data['open_alerts'] ?></strong><p>Unacknowledged active alerts</p></article>
</section>

<section class="alerts-card">
  <div class="section-heading"><h2>Active alerts</h2><a href="<?= e(app_url('pages/alerts.php')) ?>">View all alerts</a></div>
  <?php if ($demo): ?><p class="empty-state">Figma sample alerts will be replaced by real alerts after the alert rules are configured.</p>
  <?php elseif (!$data['alerts']): ?><p class="empty-state">No active alerts.</p>
  <?php else: ?><div class="table-scroll"><table><thead><tr><th>Time</th><th>Parameter</th><th>Message</th><th>Severity</th></tr></thead><tbody><?php foreach ($data['alerts'] as $alert): ?><tr><td><?= e($alert['created_at']) ?></td><td><?= e(ucfirst($alert['sensor'])) ?></td><td><?= e($alert['message']) ?></td><td><span class="status-pill <?= e($alert['severity']==='alert'?'alert':'warning') ?>"><?= e(ucfirst($alert['severity'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php layout_end(); ?>

