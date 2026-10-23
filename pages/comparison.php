<?php
require_once __DIR__ . '/../assets/php/layout.php';
$from=(string)($_GET['from']??gmdate('Y-m-d',strtotime('-7 days')));$to=(string)($_GET['to']??gmdate('Y-m-d'));
$previousFrom=(string)($_GET['previous_from']??gmdate('Y-m-d',strtotime('-14 days')));$previousTo=(string)($_GET['previous_to']??gmdate('Y-m-d',strtotime('-8 days')));
$valid=static fn($v)=>preg_match('/^\d{4}-\d{2}-\d{2}$/',$v);
$results=[];$error='';
try{if(!$valid($from)||!$valid($to)||!$valid($previousFrom)||!$valid($previousTo))throw new RuntimeException('Choose valid dates.');$pdo=db();$device=get_active_device($pdo);if($device){$stmt=$pdo->prepare('SELECT COUNT(*) AS n, ROUND(AVG(temperature_c),1) AS temp, ROUND(AVG(humidity_pct),1) AS humidity, ROUND(AVG(air_adc),0) AS air, ROUND(AVG(light_adc),0) AS light FROM readings WHERE device_id=? AND measured_at>=? AND measured_at<?');foreach([[$from,$to],[$previousFrom,$previousTo]] as $i=>$pair){$stmt->execute([$device['id'],$pair[0].' 00:00:00',date('Y-m-d',strtotime($pair[1].' +1 day')).' 00:00:00']);$results[$i]=$stmt->fetch();}}}catch(Throwable $exception){$error=$exception instanceof RuntimeException?$exception->getMessage():database_error_message();}
layout_start('Comparison','comparison');
?>
<section class="content-card"><div class="section-heading"><div><h2>Compare two periods</h2><p>Compare averages from the selected room. Missing coverage is shown as “—”.</p></div></div>
<form class="filter-form compare-form" method="get"><fieldset><legend>Current period</legend><label>From<input type="date" name="from" value="<?= e($from) ?>"></label><label>To<input type="date" name="to" value="<?= e($to) ?>"></label></fieldset><fieldset><legend>Previous period</legend><label>From<input type="date" name="previous_from" value="<?= e($previousFrom) ?>"></label><label>To<input type="date" name="previous_to" value="<?= e($previousTo) ?>"></label></fieldset><button class="button-primary small" type="submit">Compare</button></form>
<?php if($error): ?><p class="inline-notice"><?= e($error) ?></p><?php elseif(!$results): ?><p class="empty-state">No device data is available to compare yet.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Measure</th><th>Current period (<?= e($results[0]['n']?'data received':'no data') ?>)</th><th>Previous period (<?= e($results[1]['n']?'data received':'no data') ?>)</th><th>Difference</th></tr></thead><tbody><?php foreach(['temp'=>'Temperature (°C)','humidity'=>'Humidity (%)','air'=>'Air raw ADC','light'=>'Light raw ADC'] as $field=>$name): $a=$results[0][$field]??null;$b=$results[1][$field]??null; ?><tr><td><?= e($name) ?></td><td><?= $a===null?'—':e((string)$a) ?></td><td><?= $b===null?'—':e((string)$b) ?></td><td><?= $a!==null&&$b!==null?e((string)round((float)$a-(float)$b,1)):'—' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php layout_end(); ?>

