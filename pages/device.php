<?php
require_once __DIR__ . '/../assets/php/layout.php';
try{$pdo=db();$device=get_active_device($pdo);}catch(Throwable $exception){$device=null;}
layout_start('Device settings','device');
?>
<section class="content-card"><div class="section-heading"><div><h2>Device information</h2><p>Read-only configuration for the connected ESP32.</p></div><span class="status-pill <?= $device&&$device['last_seen_at']&&strtotime($device['last_seen_at'].' UTC')>time()-((int)$device['offline_timeout_minutes']*60)?'ok':'neutral' ?>"><?= $device&&$device['last_seen_at']?'Last seen '.e(time_ago($device['last_seen_at'])):'Not connected' ?></span></div>
<?php if(!$device): ?><p class="empty-state">No device has been registered. Ask an administrator to add the ESP32.</p><?php else: ?><dl class="settings-list"><div><dt>Device</dt><dd><?= e($device['name']) ?></dd></div><div><dt>Location</dt><dd><?= e($device['location']) ?></dd></div><div><dt>Sampling interval</dt><dd><?= (int)$device['sample_interval_seconds'] ?> seconds</dd></div><div><dt>Offline timeout</dt><dd><?= (int)$device['offline_timeout_minutes'] ?> minutes</dd></div><div><dt>Temperature range</dt><dd><?= e((string)$device['temp_min']) ?>–<?= e((string)$device['temp_max']) ?> °C</dd></div><div><dt>Humidity range</dt><dd><?= e((string)$device['humidity_min']) ?>–<?= e((string)$device['humidity_max']) ?> %</dd></div><div><dt>Air quality sensor</dt><dd>MQ-135 raw ADC; calibration required before AQI/ppm labels</dd></div><div><dt>Light sensor</dt><dd>LDR raw ADC; lux conversion and night threshold require calibration</dd></div></dl><?php endif; ?>
</section>
<?php layout_end(); ?>

