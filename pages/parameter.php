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
$rows=[];$chartRows=[];$device=null;$totalRows=0;$pageSize=10;
$requestedPage=filter_var($_GET['page']??1,FILTER_VALIDATE_INT);
$page=max(1,$requestedPage===false?1:$requestedPage);
try {
  $pdo=db(); $device=get_active_device($pdo);
  if($device){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM readings WHERE device_id=?');$stmt->execute([$device['id']]);$totalRows=(int)$stmt->fetchColumn();
    $pageCount=max(1,(int)ceil($totalRows/$pageSize));$page=min($page,$pageCount);$offset=($page-1)*$pageSize;
    $stmt=$pdo->prepare("SELECT measured_at, {$field} AS value FROM readings WHERE device_id=? ORDER BY measured_at DESC,id DESC LIMIT 10 OFFSET {$offset}");$stmt->execute([$device['id']]);$rows=$stmt->fetchAll();
    $stmt=$pdo->prepare("SELECT measured_at, {$field} AS value FROM readings WHERE device_id=? ORDER BY measured_at DESC,id DESC LIMIT 100");$stmt->execute([$device['id']]);$chartRows=$stmt->fetchAll();
  }
} catch(Throwable $exception) {}
$pageCount=max(1,(int)ceil($totalRows/$pageSize));
$pageUrl=static fn(int $number): string => app_url('pages/parameter.php?sensor=' . urlencode($key) . ($device ? '&device_id=' . (int)$device['id'] : '') . '&page=' . $number);
layout_start($label, $key);
?>
<section class="content-card"><div class="section-heading"><div><h2><?= e($label) ?> readings</h2><p><?= e($device['location'] ?? 'No device selected') ?> · <?= e($unit) ?></p></div><span class="status-pill neutral"><?= $rows ? count($rows).' readings · page '.$page.'/'.$pageCount : 'Waiting for data' ?></span></div>
<?php if(!$device): ?><p class="empty-state">Register a device and connect its upload endpoint to see readings here.</p>
<?php elseif(!$rows): ?><p class="empty-state">No readings have been received from this device yet.</p>
<?php else: ?><div class="reading-hero"><strong><?= e((string)$rows[0]['value']) ?></strong><span><?= e($unit) ?></span><small><?= $page===1?'Latest reading':'Reading on page '.$page ?> · <?= e($rows[0]['measured_at']) ?> UTC</small></div><?= sensor_chart_svg($chartRows) ?><p class="chart-legend">Trend from the latest <?= count($chartRows) ?> readings · newest at right</p><div class="table-scroll sensor-history-table"><table><thead><tr><th>Measured at (UTC)</th><th><?= e($label) ?></th><th>Unit</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= e($row['measured_at']) ?></td><td><?= e((string)$row['value']) ?></td><td><?= e($unit) ?></td></tr><?php endforeach; ?></tbody></table></div><div class="mobile-reading-list" aria-label="<?= e($label) ?> readings, page <?= $page ?>"><?php foreach($rows as $row): ?><article class="mobile-reading-item"><strong><?= e((string)$row['value']) ?> <span><?= e($unit) ?></span></strong><time><?= e($row['measured_at']) ?> UTC</time></article><?php endforeach; ?></div><nav class="reading-pagination" aria-label="Reading pages"><?php if($page>1): ?><a href="<?= e($pageUrl($page-1)) ?>" rel="prev">‹ Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $pageCount ?> · <?= $totalRows ?> readings</span><?php if($page<$pageCount): ?><a href="<?= e($pageUrl($page+1)) ?>" rel="next">Next ›</a><?php endif; ?></nav><?php endif; ?>
</section>
<?php if(in_array($key,['air','light'],true)): ?><p class="inline-notice"><?= $key==='air'?'MQ-135 is currently shown as raw ADC; it is not AQI or ppm until calibrated.':'The current LDR reading is raw ADC; lux needs a calibrated sensor conversion.' ?></p><?php endif; ?>
<?php layout_end(); ?>

