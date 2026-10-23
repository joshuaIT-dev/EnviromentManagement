<?php
require_once __DIR__ . '/../assets/php/layout.php';
require_admin();
try {
    $requests = db()->query("SELECT r.id,r.created_at,u.account_id,u.name,u.email FROM password_reset_requests r JOIN users u ON u.id=r.user_id WHERE r.status='pending' ORDER BY r.created_at")->fetchAll();
} catch (Throwable $exception) { $requests = []; }
layout_start('Notifications', 'admin');
?>
<section class="content-card"><h2>Pending password reset requests</h2><?php if (!$requests): ?><p class="empty-state">No pending requests.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Faculty ID</th><th>Name</th><th>Email</th><th>Requested</th></tr></thead><tbody><?php foreach ($requests as $request): ?><tr><td><?= e($request['account_id']) ?></td><td><?= e($request['name']) ?></td><td><?= e($request['email']) ?></td><td><?= e($request['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><p><a class="button-primary small" href="<?= e(app_url('pages/admin_users.php')) ?>">Open user management</a></p><?php endif; ?></section>
<section class="content-card"><h2>Email notifications</h2><p class="inline-notice">Email delivery is not configured. Requests appear here and in user management.</p></section>
<?php layout_end(); ?>
