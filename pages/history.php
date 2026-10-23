<?php
require_once __DIR__ . '/../assets/php/layout.php';
$sensor=(string)($_GET['sensor']??'');
$allowed=['temperature'=>'temperature_c','humidity'=>'humidity_pct','air'=>'air_adc','light'=>'light_adc'];
if(!isset($allowed[$sensor]))$sensor='';
$dateFrom=(string)($_GET['from']??'');$dateTo=(string)($_GET['to']??'');
$rows=[];$error='';
try{
 $pdo=db();$device=get_active_device($pdo);
 if($device){
  $where=['device_id=?'];$args=[(int)$device['id']];
  if($dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateFrom)){$where[]='measured_at>=?';$args[]=$dateFrom.' 00:00:00';}
  if($dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateTo)){$where[]='measured_at<=?';$args[]=$dateTo.' 23:59:59';}
  $stmt=$pdo->prepare('SELECT * FROM readings WHERE '.implode(' AND ',$where).' ORDER BY measured_at DESC LIMIT 200');$stmt->execute($args);$rows=$stmt->fetchAll();
 }
}catch(Throwable $exception){$error=database_error_message();}
layout_start('History','history');
?>
<section class="content-card"><div class="section-heading"><div><h2>Reading history</h2><p>Latest 200 readings, newest first · stored in UTC</p></div></div>
<form class="filter-form" method="get"><label>From<input type="date" name="from" value="<?= e($dateFrom) ?>"></label><label>To<input type="date" name="to" value="<?= e($dateTo) ?>"></label><label>Sensor<select name="sensor"><option value="">All sensors</option><?php foreach(['temperature'=>'Temperature','humidity'=>'Humidity','air'=>'Air quality','light'=>'Light'] as $v=>$name): ?><option value="<?= e($v) ?>" <?= $sensor===$v?'selected':'' ?>><?= e($name) ?></option><?php endforeach; ?></select></label><button class="button-primary small" type="submit">Filter</button></form>
<?php if($error): ?><p class="inline-notice"><?= e($error) ?></p><?php elseif(!$rows): ?><p class="empty-state">No readings match these filters.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Time (UTC)</th><th>Room</th><th>Temperature</th><th>Humidity</th><th>Air ADC</th><th>Gas digital</th><th>Light ADC</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= e($row['measured_at']) ?></td><td><?= e($device['location']) ?></td><td><?= e((string)$row['temperature_c']) ?> °C</td><td><?= e((string)$row['humidity_pct']) ?> %</td><td><?= (int)$row['air_adc'] ?></td><td><?= (int)$row['gas_digital']?'HIGH':'LOW' ?></td><td><?= (int)$row['light_adc'] ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php layout_end(); ?>

