<?php
require_once __DIR__ . '/../assets/php/layout.php';
$sensors = [
 'temperature' => ['Temperature','temperature_c','°C'],
 'humidity' => ['Humidity','humidity_pct','%'],
 'air' => ['Air quality','air_adc','raw ADC'],
 'light' => ['Light intensity','light_adc','raw ADC'],
];
$key = (string)($_GET['sensor'] ?? 'temperature');
if (!isset($sensors[$key])) $key = 'temperature';
[$label,$field,$unit] = $sensors[$key];
$rows=[];$device=null;
try {
  $pdo=db(); $device=get_active_device($pdo);
  if($device){$stmt=$pdo->prepare("SELECT measured_at, {$field} AS value FROM readings WHERE device_id=? ORDER BY measured_at DESC LIMIT 100");$stmt->execute([$device['id']]);$rows=$stmt->fetchAll();}
} catch(Throwable $exception) {}
layout_start($label, $key);
?>
<section class="content-card"><div class="section-heading"><div><h2><?= e($label) ?> readings</h2><p><?= e($device['location'] ?? 'No device selected') ?> · <?= e($unit) ?></p></div><span class="status-pill neutral"><?= $rows ? count($rows).' recent records' : 'Waiting for data' ?></span></div>
<?php if(!$device): ?><p class="empty-state">Register a device and connect its upload endpoint to see readings here.</p>
<?php elseif(!$rows): ?><p class="empty-state">No readings have been received from this device yet.</p>
<?php else: ?><div class="reading-hero"><strong><?= e((string)$rows[0]['value']) ?></strong><span><?= e($unit) ?></span><small>Latest reading · <?= e($rows[0]['measured_at']) ?> UTC</small></div><div class="table-scroll"><table><thead><tr><th>Measured at (UTC)</th><th><?= e($label) ?></th><th>Unit</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= e($row['measured_at']) ?></td><td><?= e((string)$row['value']) ?></td><td><?= e($unit) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php if(in_array($key,['air','light'],true)): ?><p class="inline-notice"><?= $key==='air'?'MQ-135 is currently shown as raw ADC; it is not AQI or ppm until calibrated.':'The current LDR reading is raw ADC; lux needs a calibrated sensor conversion.' ?></p><?php endif; ?>
<?php layout_end(); ?>

