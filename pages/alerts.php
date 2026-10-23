<?php
require_once __DIR__ . '/../assets/php/layout.php';
$notice='';
try {
 $pdo=db();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  $id=filter_input(INPUT_POST,'alert_id',FILTER_VALIDATE_INT);
  if($id){$stmt=$pdo->prepare('UPDATE alerts SET acknowledged_at=UTC_TIMESTAMP(), acknowledged_by=? WHERE id=? AND acknowledged_at IS NULL AND closed_at IS NULL');$stmt->execute([$_SESSION['user_id'],$id]);log_activity($pdo,(int)$_SESSION['user_id'],'alert_acknowledged',(string)$id);$notice='Alert acknowledged.';}
 }
 $rows=$pdo->query('SELECT a.*, d.name AS device_name, d.location, u.name AS acknowledged_name FROM alerts a JOIN devices d ON d.id=a.device_id LEFT JOIN users u ON u.id=a.acknowledged_by ORDER BY a.closed_at IS NULL DESC, a.created_at DESC LIMIT 200')->fetchAll();
} catch(Throwable $exception){$rows=[];$notice=database_error_message();}
layout_start('Alerts','alerts');
?>
<?php if($notice): ?><p class="inline-notice"><?= e($notice) ?></p><?php endif; ?>
<section class="content-card"><div class="section-heading"><div><h2>Alert history</h2><p>Warnings use text labels as well as color.</p></div></div>
<?php if(!$rows): ?><p class="empty-state">There are no alerts yet. Alerts will appear when calibrated rules are enabled.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Time (UTC)</th><th>Room</th><th>Parameter</th><th>Message</th><th>Severity</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= e($row['created_at']) ?></td><td><?= e($row['location']) ?></td><td><?= e(ucfirst($row['sensor'])) ?></td><td><?= e($row['message']) ?></td><td><span class="status-pill <?= $row['severity']==='alert'?'alert':'warning' ?>"><?= e(ucfirst($row['severity'])) ?></span></td><td><?= $row['closed_at']?'Closed':($row['acknowledged_at']?'Acknowledged':'Open') ?></td><td><?php if(!$row['closed_at']&&!$row['acknowledged_at']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="alert_id" value="<?= (int)$row['id'] ?>"><button class="text-button" type="submit">Acknowledge</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php layout_end(); ?>

