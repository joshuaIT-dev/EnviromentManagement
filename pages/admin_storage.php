<?php
require_once __DIR__ . '/../assets/php/layout.php';
require_admin();
$tables = []; $total = 0; $message = ''; $days = 30;
try {
    $pdo = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf(); $action = (string)($_POST['action'] ?? 'save');
        $daysInput = filter_input(INPUT_POST, 'raw_readings_days', FILTER_VALIDATE_INT);
        if ($daysInput === false || $daysInput === null || $daysInput < 1 || $daysInput > 3650) throw new RuntimeException('Retention must be between 1 and 3650 days.');
        $pdo->prepare('UPDATE data_retention_settings SET raw_readings_days=?,updated_by=? WHERE id=1')->execute([$daysInput,(int)$_SESSION['user_id']]); $days = $daysInput;
        if ($action === 'cleanup') {
            $stmt = $pdo->prepare("DELETE FROM readings WHERE measured_at < UTC_TIMESTAMP() - INTERVAL {$days} DAY"); $stmt->execute();
            log_activity($pdo,(int)$_SESSION['user_id'],'readings_retention_cleanup',$days . ' days; ' . $stmt->rowCount() . ' rows removed');
            $message = number_format($stmt->rowCount()) . ' old readings removed.';
        } else {
            log_activity($pdo,(int)$_SESSION['user_id'],'retention_settings_updated',$days . ' days'); $message = 'Retention setting saved.';
        }
    }
    $days = (int)$pdo->query('SELECT raw_readings_days FROM data_retention_settings WHERE id=1')->fetchColumn() ?: $days;
    $tables = $pdo->query("SELECT TABLE_NAME AS name, TABLE_ROWS AS rows_estimate, ROUND((DATA_LENGTH+INDEX_LENGTH)/1024/1024,2) AS size_mb FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME")->fetchAll();
    foreach ($tables as $table) $total += (float)$table['size_mb'];
} catch (Throwable $exception) { $message = $exception instanceof RuntimeException ? $exception->getMessage() : database_error_message(); }
layout_start('Data & storage', 'admin_storage');
?>
<?php if ($message): ?><p class="inline-notice" role="status"><?= e($message) ?></p><?php endif; ?>
<section class="content-card"><div class="section-heading"><div><h2>Database usage</h2><p>Approximate MySQL table sizes from XAMPP.</p></div><span class="status-pill neutral"><?= number_format($total,2) ?> MB</span></div><?php if (!$tables): ?><p class="empty-state">Import the project SQL to view database usage.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Table</th><th>Approx. rows</th><th>Size</th></tr></thead><tbody><?php foreach ($tables as $table): ?><tr><td><?= e($table['name']) ?></td><td><?= number_format((int)$table['rows_estimate']) ?></td><td><?= e((string)$table['size_mb']) ?> MB</td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<section class="content-card"><h2>Reading retention</h2><p>Choose how long raw sensor readings should be kept. Cleanup runs only when an administrator starts it.</p><form class="settings-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><label>Keep readings for (days)<input type="number" name="raw_readings_days" min="1" max="3650" value="<?= $days ?>" required></label><button class="button-primary" type="submit">Save retention</button></form><form method="post" onsubmit="return confirm('Permanently delete readings older than this retention period? Export the database first if you need a backup.')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="cleanup"><input type="hidden" name="raw_readings_days" value="<?= $days ?>"><button class="button-link" type="submit">Run cleanup now</button></form></section>
<?php layout_end(); ?>
