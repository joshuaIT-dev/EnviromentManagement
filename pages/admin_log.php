<?php
require_once __DIR__ . '/../assets/php/layout.php';
require_admin();
try{$rows=db()->query('SELECT l.*,u.name FROM audit_log l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 200')->fetchAll();}catch(Throwable $exception){$rows=[];}
layout_start('Activity log','admin');
?>
<section class="content-card"><h2>Recent activity</h2><?php if(!$rows): ?><p class="empty-state">No activity has been recorded yet.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Time (UTC)</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= e($row['created_at']) ?></td><td><?= e($row['name']??'System') ?></td><td><?= e($row['action']) ?></td><td><?= e($row['details']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php layout_end(); ?>

