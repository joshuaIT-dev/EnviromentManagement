<?php
require_once __DIR__ . '/../assets/php/layout.php';
$sensor=(string)($_GET['sensor']??'');
$allowed=['temperature'=>'temperature_c','humidity'=>'humidity_pct','air'=>'air_adc','light'=>'light_adc'];
$sensorLabels=['temperature'=>'Temperature','humidity'=>'Humidity','air'=>'Air quality','light'=>'Light'];
$sensorUnits=['temperature'=>'°C','humidity'=>'%','air'=>'raw ADC','light'=>'raw ADC'];
if(!isset($allowed[$sensor]))$sensor='';
$dateFrom=(string)($_GET['from']??'');$dateTo=(string)($_GET['to']??'');
$rows=[];$error='';$totalRows=0;$pageSize=10;
$requestedPage=filter_var($_GET['page']??1,FILTER_VALIDATE_INT);$page=max(1,$requestedPage===false?1:$requestedPage);
try{
 $pdo=db();$device=get_active_device($pdo);
 if($device){
  $where=['device_id=?'];$args=[(int)$device['id']];
  if($sensor)$where[]=$allowed[$sensor].' IS NOT NULL';
  if($dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateFrom)){$where[]='measured_at>=?';$args[]=$dateFrom.' 00:00:00';}
  if($dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateTo)){$where[]='measured_at<=?';$args[]=$dateTo.' 23:59:59';}
  $condition=implode(' AND ',$where);$stmt=$pdo->prepare('SELECT COUNT(*) FROM readings WHERE '.$condition);$stmt->execute($args);$totalRows=(int)$stmt->fetchColumn();
  $pageCount=max(1,(int)ceil($totalRows/$pageSize));$page=min($page,$pageCount);$offset=($page-1)*$pageSize;
  $stmt=$pdo->prepare('SELECT * FROM readings WHERE '.$condition.' ORDER BY measured_at DESC,id DESC LIMIT 10 OFFSET '.$offset);$stmt->execute($args);$rows=$stmt->fetchAll();
 }
}catch(Throwable $exception){$error=database_error_message();}
$pageCount=max(1,(int)ceil($totalRows/$pageSize));
$pageUrl=static fn(int $number):string=>app_url('pages/history.php?'.http_build_query(array_filter(['sensor'=>$sensor,'from'=>$dateFrom,'to'=>$dateTo,'device_id'=>$device['id']??null,'page'=>$number],static fn($value)=>$value!==''&&$value!==null)));
layout_start('History','history');
?>
<section class="content-card"><div class="section-heading"><div><h2>Reading history</h2><p><?= $sensor ? e($sensorLabels[$sensor]).' readings' : 'Environment readings and sensor outputs' ?> · <?= $totalRows ?> records · UTC</p></div><span class="status-pill neutral"><?= $rows ? 'Page '.$page.' / '.$pageCount : 'No records' ?></span></div>
<form class="filter-form" method="get"><label>From<input type="date" name="from" value="<?= e($dateFrom) ?>"></label><label>To<input type="date" name="to" value="<?= e($dateTo) ?>"></label><label>Sensor<select name="sensor"><option value="">All sensors</option><?php foreach(['temperature'=>'Temperature','humidity'=>'Humidity','air'=>'Air quality','light'=>'Light'] as $v=>$name): ?><option value="<?= e($v) ?>" <?= $sensor===$v?'selected':'' ?>><?= e($name) ?></option><?php endforeach; ?></select></label><button class="button-primary small" type="submit">Filter</button></form>
<?php if($error): ?><p class="inline-notice"><?= e($error) ?></p><?php elseif(!$rows): ?><p class="empty-state">No readings match these filters.</p><?php elseif($sensor): ?><div class="table-scroll"><table><thead><tr><th>Time (UTC)</th><th>Room</th><th><?= e($sensorLabels[$sensor]) ?></th><th>Unit</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= e($row['measured_at']) ?></td><td><?= e($device['location']) ?></td><td><?= e((string)$row[$allowed[$sensor]]) ?></td><td><?= e($sensorUnits[$sensor]) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="table-scroll"><table><thead><tr><th rowspan="2">Time (UTC)</th><th rowspan="2">Room</th><th colspan="2">Environment</th><th colspan="3">Sensor outputs</th></tr><tr><th>Temperature</th><th>Humidity</th><th>Air ADC</th><th>Gas digital</th><th>Light ADC</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= e($row['measured_at']) ?></td><td><?= e($device['location']) ?></td><td><?= $row['temperature_c']===null?'—':e((string)$row['temperature_c']).' °C' ?></td><td><?= $row['humidity_pct']===null?'—':e((string)$row['humidity_pct']).' %' ?></td><td><?= (int)$row['air_adc'] ?></td><td><?= (int)$row['gas_digital']?'HIGH':'LOW' ?></td><td><?= (int)$row['light_adc'] ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php if($rows): ?><nav class="reading-pagination" aria-label="History pages"><?php if($page>1): ?><a href="<?= e($pageUrl($page-1)) ?>" rel="prev">‹ Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $pageCount ?> · <?= $totalRows ?> readings</span><?php if($page<$pageCount): ?><a href="<?= e($pageUrl($page+1)) ?>" rel="next">Next ›</a><?php endif; ?></nav><?php endif; ?>
</section>
<?php layout_end(); ?>

