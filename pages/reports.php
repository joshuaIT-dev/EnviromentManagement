<?php
require_once __DIR__ . '/../assets/php/layout.php';
$period=(string)($_GET['period']??'week');if(!in_array($period,['week','month','year'],true))$period='week';
$span=$period==='week'?'7 DAY':($period==='month'?'30 DAY':'365 DAY');
$rows=[];$error='';
try{$pdo=db();$device=get_active_device($pdo);if($device){$sql="SELECT DATE(measured_at) AS day, COUNT(*) AS readings, ROUND(AVG(temperature_c),1) AS avg_temp, ROUND(AVG(humidity_pct),1) AS avg_humidity, MIN(temperature_c) AS min_temp, MAX(temperature_c) AS max_temp FROM readings WHERE device_id=? AND measured_at >= UTC_TIMESTAMP() - INTERVAL {$span} GROUP BY DATE(measured_at) ORDER BY day DESC";$stmt=$pdo->prepare($sql);$stmt->execute([$device['id']]);$rows=$stmt->fetchAll();}}catch(Throwable $exception){$error=database_error_message();}
layout_start('Reports','reports');
?>
<section class="content-card"><div class="section-heading"><div><h2><?= e(ucfirst($period)) ?> report</h2><p>Daily averages from readings received in the selected period.</p></div><nav class="segmented"><?php foreach(['week'=>'7 days','month'=>'30 days','year'=>'1 year'] as $key=>$label): ?><a class="<?= $period===$key?'selected':'' ?>" href="?period=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?></nav></div>
<?php if($error): ?><p class="inline-notice"><?= e($error) ?></p><?php elseif(!$rows): ?><p class="empty-state">Reports will be available when the device has uploaded readings.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Date (UTC)</th><th>Readings</th><th>Avg temp</th><th>Min temp</th><th>Max temp</th><th>Avg humidity</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= e($row['day']) ?></td><td><?= (int)$row['readings'] ?></td><td><?= e((string)$row['avg_temp']) ?> °C</td><td><?= e((string)$row['min_temp']) ?> °C</td><td><?= e((string)$row['max_temp']) ?> °C</td><td><?= e((string)$row['avg_humidity']) ?> %</td></tr><?php endforeach; ?></tbody></table></div><p class="chart-legend">This first version calculates reports from raw readings. CSV/PDF export and saved report tiers are not enabled yet.</p><?php endif; ?>
</section>
<?php layout_end(); ?>

