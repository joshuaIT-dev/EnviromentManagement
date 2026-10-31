<?php
require_once __DIR__ . '/../assets/php/layout.php';
require_admin();
$message = ''; $device = null;
try {
    $pdo = db(); $device = get_active_device($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf(); $deviceId = filter_input(INPUT_POST, 'device_id', FILTER_VALIDATE_INT);
        $values = [];
        foreach (['temp_min','temp_max','humidity_min','humidity_max'] as $field) {
            $value = filter_input(INPUT_POST, $field, FILTER_VALIDATE_FLOAT);
            if ($value === false || $value === null) throw new RuntimeException('Enter valid temperature and humidity ranges.');
            $values[] = $value;
        }
        if (!$deviceId || $values[0] >= $values[1] || $values[2] >= $values[3]) throw new RuntimeException('Each minimum must be below its maximum.');
        $airWarn = filter_input(INPUT_POST, 'air_warning_adc', FILTER_VALIDATE_INT); $airAlert = filter_input(INPUT_POST, 'air_alert_adc', FILTER_VALIDATE_INT); $light = filter_input(INPUT_POST, 'light_on_adc', FILTER_VALIDATE_INT);
        foreach ([$airWarn,$airAlert,$light] as $value) if ($value !== null && $value !== false && ($value < 0 || $value > 4095)) throw new RuntimeException('Raw ADC thresholds must be from 0 to 4095.');
        if ($airWarn && $airAlert && $airAlert < $airWarn) throw new RuntimeException('Air alert threshold must be at least the warning threshold.');
        $start = (string)($_POST['light_night_start'] ?? ''); $end = (string)($_POST['light_night_end'] ?? '');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) throw new RuntimeException('Enter a valid night window.');
        $cooldown = filter_input(INPUT_POST, 'alert_cooldown_minutes', FILTER_VALIDATE_INT); $duration = filter_input(INPUT_POST, 'light_min_duration_minutes', FILTER_VALIDATE_INT);
        if ($cooldown === false || $cooldown < 0 || $cooldown > 1440 || $duration === false || $duration < 1 || $duration > 1440) throw new RuntimeException('Cooldown and light duration must be from 1 to 1440 minutes.');
        $stmt = $pdo->prepare('UPDATE devices SET temp_min=?,temp_max=?,humidity_min=?,humidity_max=?,air_warning_adc=?,air_alert_adc=?,light_on_adc=?,light_night_start=?,light_night_end=?,alert_cooldown_minutes=?,light_min_duration_minutes=? WHERE id=?');
        $stmt->execute([...$values,$airWarn ?: null,$airAlert ?: null,$light ?: null,$start,$end,$cooldown,$duration,$deviceId]);
        log_activity($pdo,(int)$_SESSION['user_id'],'thresholds_updated',(string)$deviceId); $message = 'Thresholds saved.'; $device = get_active_device($pdo);
    }
} catch (Throwable $exception) { $message = $exception instanceof RuntimeException ? $exception->getMessage() : database_error_message(); }
layout_start('Thresholds', 'admin_thresholds');
?>
<?php if ($message): ?><p class="inline-notice" role="status"><?= e($message) ?></p><?php endif; ?>
<section class="content-card"><h2>Sensor thresholds and night window</h2><?php if (!$device): ?><p class="empty-state">Register an active device before setting its thresholds.</p><?php else: ?><p>MQ-135 and LDR values stay uncalibrated until measured in the room.</p><form class="settings-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= (int)$device['id'] ?>"><div class="form-grid"><label>Temperature minimum (°C)<input type="number" step="0.1" name="temp_min" value="<?= e((string)$device['temp_min']) ?>" required></label><label>Temperature maximum (°C)<input type="number" step="0.1" name="temp_max" value="<?= e((string)$device['temp_max']) ?>" required></label><label>Humidity minimum (%)<input type="number" step="0.1" name="humidity_min" value="<?= e((string)$device['humidity_min']) ?>" required></label><label>Humidity maximum (%)<input type="number" step="0.1" name="humidity_max" value="<?= e((string)$device['humidity_max']) ?>" required></label><label>Air warning ADC<input type="number" min="0" max="4095" name="air_warning_adc" value="<?= e((string)$device['air_warning_adc']) ?>"></label><label>Air alert ADC<input type="number" min="0" max="4095" name="air_alert_adc" value="<?= e((string)$device['air_alert_adc']) ?>"></label><label>Light on ADC<input type="number" min="0" max="4095" name="light_on_adc" value="<?= e((string)$device['light_on_adc']) ?>"></label><label>Night starts<input type="time" name="light_night_start" value="<?= e(substr((string)$device['light_night_start'],0,5)) ?>" required></label><label>Night ends<input type="time" name="light_night_end" value="<?= e(substr((string)$device['light_night_end'],0,5)) ?>" required></label><label>Alert cooldown (minutes)<input type="number" min="0" max="1440" name="alert_cooldown_minutes" value="<?= (int)$device['alert_cooldown_minutes'] ?>" required></label><label>Light duration (minutes)<input type="number" min="1" max="1440" name="light_min_duration_minutes" value="<?= (int)$device['light_min_duration_minutes'] ?>" required></label></div><button class="button-primary" type="submit">Save thresholds</button></form><?php endif; ?></section>
<?php layout_end(); ?>
