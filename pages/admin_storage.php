<?php
require_once __DIR__ . '/../assets/php/layout.php';
require_admin();
$tables=[];$total=0;
try{$tables=db()->query("SELECT TABLE_NAME AS name, TABLE_ROWS AS rows_estimate, ROUND((DATA_LENGTH+INDEX_LENGTH)/1024/1024,2) AS size_mb FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME")->fetchAll();foreach($tables as $t)$total+=(float)$t['size_mb'];}catch(Throwable $exception){}
layout_start('Data and storage','admin');
?>
<section class="content-card"><div class="section-heading"><div><h2>Database usage</h2><p>Approximate MySQL table sizes from XAMPP.</p></div><span class="status-pill neutral"><?= number_format($total,2) ?> MB</span></div><?php if(!$tables): ?><p class="empty-state">Import the project SQL to view database usage.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Table</th><th>Approx. rows</th><th>Size</th></tr></thead><tbody><?php foreach($tables as $t): ?><tr><td><?= e($t['name']) ?></td><td><?= number_format((int)$t['rows_estimate']) ?></td><td><?= e((string)$t['size_mb']) ?> MB</td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?><p class="chart-legend">Automatic archival and retention cleanup are not enabled yet. Export the database before removing historical readings.</p></section>
<?php layout_end(); ?>

