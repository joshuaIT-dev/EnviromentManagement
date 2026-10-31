<?php
require_once __DIR__ . '/../assets/php/layout.php';
require_admin();

$pdo = null;
$message = $_SESSION['admin_device_notice'] ?? '';
unset($_SESSION['admin_device_notice']);
$devices = $pending = [];
$keyOnce = $_SESSION['new_device_key'] ?? null;
unset($_SESSION['new_device_key']);

try {
    $pdo = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'create' || $action === 'register_detected') {
            $name = trim((string)($_POST['name'] ?? ''));
            $location = trim((string)($_POST['location'] ?? ''));
            if ($name === '' || $location === '') throw new RuntimeException('Enter a device name and location.');
            if ($action === 'register_detected') {
                $mac = strtoupper(trim((string)($_POST['mac_address'] ?? '')));
                if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) throw new RuntimeException('That device address is invalid.');
                $stmt = $pdo->prepare("SELECT mac_address FROM device_enrollment_requests WHERE mac_address=? AND status='pending' FOR UPDATE");
                $pdo->beginTransaction();
                $stmt->execute([$mac]);
                if (!$stmt->fetchColumn()) throw new RuntimeException('That discovery request is no longer pending.');
            } else {
                $mac = null;
                $pdo->beginTransaction();
            }

            $key = bin2hex(random_bytes(24));
            $stmt = $pdo->prepare('INSERT INTO devices (name,location,api_key_prefix,api_key_hash) VALUES (?,?,?,?)');
            $stmt->execute([$name, $location, substr($key, 0, 8), hash('sha256', $key)]);
            $deviceId = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO device_display_settings (device_id) VALUES (?)')->execute([$deviceId]);
            if ($mac !== null) {
                $pdo->prepare("UPDATE device_enrollment_requests SET status='registered',registered_device_id=? WHERE mac_address=?")->execute([$deviceId, $mac]);
            }
            log_activity($pdo, (int)$_SESSION['user_id'], $mac === null ? 'device_created' : 'device_enrolled', $name . ($mac === null ? '' : ' ' . $mac));
            $pdo->commit();
            $_SESSION['new_device_key'] = $key;
            header('Location: ' . app_url('pages/admin_devices.php'));
            exit;
        }

        $id = filter_input(INPUT_POST, 'device_id', FILTER_VALIDATE_INT);
        if (!$id) throw new RuntimeException('Choose a valid device.');
        if ($action === 'remove') {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT name FROM devices WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            $name = $stmt->fetchColumn();
            if (!$name) throw new RuntimeException('That device no longer exists.');
            $pdo->prepare('DELETE FROM device_enrollment_requests WHERE registered_device_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM devices WHERE id=?')->execute([$id]);
            log_activity($pdo, (int)$_SESSION['user_id'], 'device_removed', $name . ' (device, readings, alerts and LCD settings deleted)');
            $pdo->commit();
            $_SESSION['admin_device_notice'] = 'Device and associated data removed.';
            header('Location: ' . app_url('pages/admin_devices.php'));
            exit;
        } elseif ($action === 'save') {
            $name = trim((string)($_POST['name'] ?? ''));
            $location = trim((string)($_POST['location'] ?? ''));
            $interval = filter_input(INPUT_POST, 'sample_interval_seconds', FILTER_VALIDATE_INT);
            $timeout = filter_input(INPUT_POST, 'offline_timeout_minutes', FILTER_VALIDATE_INT);
            $screenSeconds = filter_input(INPUT_POST, 'seconds_per_screen', FILTER_VALIDATE_INT);
            if ($name === '' || $location === '' || $interval === false || $interval === null || $interval < 10 || $interval > 3600 || $timeout === false || $timeout === null || $timeout < 1 || $timeout > 1440) {
                throw new RuntimeException('Use a 10–3600 second sample interval and a 1–1440 minute offline timeout.');
            }
            if ($screenSeconds === false || $screenSeconds === null || $screenSeconds < 1 || $screenSeconds > 30) throw new RuntimeException('LCD screen duration must be 1–30 seconds.');

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE devices SET name=?,location=?,sample_interval_seconds=?,offline_timeout_minutes=?,is_active=? WHERE id=?');
            $stmt->execute([$name, $location, $interval, $timeout, isset($_POST['is_active']) ? 1 : 0, $id]);
            $pdo->prepare('INSERT INTO device_display_settings (device_id,show_temperature,show_humidity,show_air,show_light,seconds_per_screen) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE show_temperature=VALUES(show_temperature),show_humidity=VALUES(show_humidity),show_air=VALUES(show_air),show_light=VALUES(show_light),seconds_per_screen=VALUES(seconds_per_screen)')->execute([
                $id, isset($_POST['show_temperature']) ? 1 : 0, isset($_POST['show_humidity']) ? 1 : 0,
                isset($_POST['show_air']) ? 1 : 0, isset($_POST['show_light']) ? 1 : 0, $screenSeconds
            ]);
            log_activity($pdo, (int)$_SESSION['user_id'], 'device_updated', $name);
            $pdo->commit();
            $message = 'Device settings saved.';
        } elseif ($action === 'rotate') {
            $key = bin2hex(random_bytes(24));
            $stmt = $pdo->prepare('UPDATE devices SET api_key_prefix=?,api_key_hash=? WHERE id=?');
            $stmt->execute([substr($key, 0, 8), hash('sha256', $key), $id]);
            log_activity($pdo, (int)$_SESSION['user_id'], 'device_key_rotated', (string)$id);
            $_SESSION['new_device_key'] = $key;
            header('Location: ' . app_url('pages/admin_devices.php'));
            exit;
        } else {
            throw new RuntimeException('Unknown device action.');
        }
    }

    $devices = $pdo->query('SELECT d.*,COALESCE(s.show_temperature,1) AS show_temperature,COALESCE(s.show_humidity,1) AS show_humidity,COALESCE(s.show_air,1) AS show_air,COALESCE(s.show_light,1) AS show_light,COALESCE(s.seconds_per_screen,5) AS seconds_per_screen FROM devices d LEFT JOIN device_display_settings s ON s.device_id=d.id ORDER BY d.id DESC')->fetchAll();
    $pending = $pdo->query("SELECT mac_address,device_label,client_ip,first_seen_at,last_seen_at FROM device_enrollment_requests WHERE status='pending' ORDER BY last_seen_at DESC")->fetchAll();
} catch (Throwable $exception) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    $message = $exception instanceof RuntimeException ? $exception->getMessage() : database_error_message();
}

layout_start('Devices & LCD', 'admin_devices');
?>
<?php if ($message): ?><p class="inline-notice" role="status"><?= e($message) ?></p><?php endif; ?>

<?php if ($keyOnce): ?>
<section class="content-card key-once"><div><h2>Copy this device key now</h2><p>It is shown once. Add it to the ESP32 sketch and upload again to start sending readings.</p></div><code><?= e($keyOnce) ?></code></section>
<?php endif; ?>

<section class="content-card admin-enrollment-section">
  <div class="admin-feature-heading"><div><h2>Detected devices</h2><p>Unregistered ESP32s that have reached this server.</p></div><span class="status-pill <?= $pending ? 'warning' : 'neutral' ?>"><?= count($pending) ?> awaiting</span></div>
  <?php if (!$pending): ?>
    <div class="admin-empty-callout"><strong>No devices waiting</strong><span>Upload the sketch with <code>deviceKey</code> set to <code>UNREGISTERED</code>. It will send its MAC address and appear here after connecting to Wi-Fi.</span></div>
  <?php else: ?>
    <div class="admin-enrollment-grid">
      <?php foreach ($pending as $request): ?>
      <article class="admin-enrollment-card">
        <header><div><span class="admin-eyebrow">NEW DEVICE</span><h3><?= e($request['device_label']) ?></h3></div><span class="status-pill warning">Awaiting registration</span></header>
        <dl><div><dt>MAC address</dt><dd><?= e($request['mac_address']) ?></dd></div><div><dt>Network address</dt><dd><?= e($request['client_ip']) ?></dd></div><div><dt>Last seen</dt><dd><?= e(time_ago($request['last_seen_at'])) ?></dd></div></dl>
        <form class="admin-enrollment-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="register_detected"><input type="hidden" name="mac_address" value="<?= e($request['mac_address']) ?>"><label>Device name<input name="name" maxlength="120" value="<?= e($request['device_label']) ?>" required></label><label>Room or location<input name="location" maxlength="160" required placeholder="Room 301"></label><button class="button-primary small" type="submit">Register device</button></form>
      </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="content-card admin-register-section">
  <div class="admin-feature-heading"><div><h2>Register manually</h2><p>Use this if the device has not connected to the network yet.</p></div></div>
  <form class="admin-manual-register" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><label>Device name<input name="name" maxlength="120" required placeholder="ESP32-301"></label><label>Room or location<input name="location" maxlength="160" required placeholder="Room 301"></label><button class="button-primary" type="submit">Create device and key</button></form>
</section>

<section class="admin-device-section">
  <div class="admin-feature-heading"><div><h2>Registered devices</h2><p>Sampling, connection and LCD controls.</p></div><span class="status-pill neutral"><?= count($devices) ?> total</span></div>
  <?php if (!$devices): ?><div class="content-card"><p class="empty-state">No registered devices yet.</p></div><?php else: ?>
  <div class="admin-device-grid">
    <?php foreach ($devices as $d): ?>
    <article class="admin-device-card">
      <header><div><span class="admin-eyebrow">DEVICE <?= (int)$d['id'] ?></span><h3><?= e($d['name']) ?></h3><p><?= e($d['location']) ?></p></div><span class="status-pill <?= $d['is_active'] ? 'ok' : 'neutral' ?>"><?= $d['is_active'] ? 'Active' : 'Disabled' ?></span></header>
      <dl><div><dt>Sample interval</dt><dd><?= (int)$d['sample_interval_seconds'] ?> sec</dd></div><div><dt>Offline timeout</dt><dd><?= (int)$d['offline_timeout_minutes'] ?> min</dd></div><div><dt>Last reading</dt><dd><?= e(time_ago($d['last_seen_at'])) ?></dd></div><div><dt>Device key</dt><dd><?= $d['api_key_hash'] ? 'Set' : 'Missing' ?></dd></div></dl>
      <details class="admin-device-manage"><summary>Manage device</summary>
        <form class="admin-device-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>"><label>Device name<input name="name" maxlength="120" value="<?= e($d['name']) ?>" required></label><label>Room or location<input name="location" maxlength="160" value="<?= e($d['location']) ?>" required></label><label>Sample interval (seconds)<input type="number" name="sample_interval_seconds" min="10" max="3600" value="<?= (int)$d['sample_interval_seconds'] ?>" required></label><label>Offline timeout (minutes)<input type="number" name="offline_timeout_minutes" min="1" max="1440" value="<?= (int)$d['offline_timeout_minutes'] ?>" required></label><label class="admin-check"><input type="checkbox" name="is_active" value="1" <?= $d['is_active'] ? 'checked' : '' ?>> Device enabled</label><fieldset class="admin-lcd-options"><legend>LCD screens</legend><label><input type="checkbox" name="show_temperature" value="1" <?= $d['show_temperature'] ? 'checked' : '' ?>> Temperature</label><label><input type="checkbox" name="show_humidity" value="1" <?= $d['show_humidity'] ? 'checked' : '' ?>> Humidity</label><label><input type="checkbox" name="show_air" value="1" <?= $d['show_air'] ? 'checked' : '' ?>> Air quality</label><label><input type="checkbox" name="show_light" value="1" <?= $d['show_light'] ? 'checked' : '' ?>> Light</label></fieldset><label>Seconds per screen<input type="number" name="seconds_per_screen" min="1" max="30" value="<?= (int)$d['seconds_per_screen'] ?>" required></label><button class="button-primary small" type="submit">Save device settings</button></form>
        <form class="admin-rotate-form" method="post" onsubmit="return confirm('Rotate this device key? The ESP32 must be updated or it will stop sending readings.')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="rotate"><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>"><button class="button-link small" type="submit">Rotate device key</button></form>
      </details>
      <form class="admin-remove-form" method="post" onsubmit="return confirm('Remove this device and permanently delete its readings, alerts and LCD settings? The ESP32 must be set up again before it can send readings.')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>"><button type="submit">Remove device</button></form>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php layout_end(); ?>
