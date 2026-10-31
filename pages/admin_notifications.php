<?php
require_once __DIR__ . '/../assets/php/layout.php';
require_admin();
$message = ''; $requests = []; $settings = ['email_enabled'=>0,'recipient_emails'=>'','warning_enabled'=>1,'alert_enabled'=>1,'device_offline_enabled'=>1,'light_at_night_enabled'=>1];
try {
    $pdo = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf(); $emails = trim((string)($_POST['recipient_emails'] ?? ''));
        $normalized = preg_split('/[,;\s]+/', $emails, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($normalized as $email) if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter valid recipient email addresses, separated by commas.');
        $normalized = array_values(array_unique($normalized));
        $pdo->prepare('UPDATE admin_notification_settings SET email_enabled=?,recipient_emails=?,warning_enabled=?,alert_enabled=?,device_offline_enabled=?,light_at_night_enabled=?,updated_by=? WHERE id=1')->execute([
            isset($_POST['email_enabled']) ? 1 : 0, implode(', ', $normalized), isset($_POST['warning_enabled']) ? 1 : 0,
            isset($_POST['alert_enabled']) ? 1 : 0, isset($_POST['device_offline_enabled']) ? 1 : 0,
            isset($_POST['light_at_night_enabled']) ? 1 : 0, (int)$_SESSION['user_id']
        ]);
        log_activity($pdo,(int)$_SESSION['user_id'],'notification_settings_updated','Admin notification preferences');
        $message = 'Notification preferences saved.';
    }
    $settings = $pdo->query('SELECT * FROM admin_notification_settings WHERE id=1')->fetch() ?: $settings;
    $requests = $pdo->query("SELECT r.id,r.created_at,u.account_id,u.name,u.email FROM password_reset_requests r JOIN users u ON u.id=r.user_id WHERE r.status='pending' ORDER BY r.created_at")->fetchAll();
} catch (Throwable $exception) { $message = $exception instanceof RuntimeException ? $exception->getMessage() : database_error_message(); }
layout_start('Notifications', 'admin_notifications');
?>
<?php if ($message): ?><p class="inline-notice" role="status"><?= e($message) ?></p><?php endif; ?>
<section class="content-card"><h2>Notification rules</h2><form class="settings-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label><input type="checkbox" name="email_enabled" value="1" <?= $settings['email_enabled'] ? 'checked' : '' ?>> Enable email notifications</label><label>Recipient emails (comma separated)<input type="text" name="recipient_emails" maxlength="1000" value="<?= e($settings['recipient_emails']) ?>" placeholder="admin@example.com"></label><div class="form-grid"><label><input type="checkbox" name="warning_enabled" value="1" <?= $settings['warning_enabled'] ? 'checked' : '' ?>> Sensor warnings</label><label><input type="checkbox" name="alert_enabled" value="1" <?= $settings['alert_enabled'] ? 'checked' : '' ?>> Sensor alerts</label><label><input type="checkbox" name="device_offline_enabled" value="1" <?= $settings['device_offline_enabled'] ? 'checked' : '' ?>> Device offline</label><label><input type="checkbox" name="light_at_night_enabled" value="1" <?= $settings['light_at_night_enabled'] ? 'checked' : '' ?>> Light active at night</label></div><button class="button-primary" type="submit">Save notification settings</button></form><p class="chart-legend">Email sending requires SMTP configuration on this server. Until then, alerts are stored in the system and shown below.</p></section>
<section class="content-card"><h2>Pending password reset requests</h2><?php if (!$requests): ?><p class="empty-state">No pending requests.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Faculty ID</th><th>Name</th><th>Email</th><th>Requested</th></tr></thead><tbody><?php foreach ($requests as $request): ?><tr><td><?= e($request['account_id']) ?></td><td><?= e($request['name']) ?></td><td><?= e($request['email']) ?></td><td><?= e($request['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><p><a class="button-primary small" href="<?= e(app_url('pages/admin_users.php')) ?>">Open user management</a></p><?php endif; ?></section>
<?php layout_end(); ?>
