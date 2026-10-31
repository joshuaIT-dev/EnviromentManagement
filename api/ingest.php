<?php
require_once __DIR__ . '/../assets/php/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'POST required']);
    exit;
}
$raw = file_get_contents('php://input', false, null, 0, 4097);
if ($raw === false || strlen($raw) > 4096) {
    http_response_code(413);
    echo json_encode(['error' => 'Request too large']);
    exit;
}
$key = $_SERVER['HTTP_X_DEVICE_KEY'] ?? '';
if (strlen($key) < 32 || strlen($key) > 128) {
    http_response_code(401);
    echo json_encode(['error' => 'Device key required']);
    exit;
}
try {
    $pdo = db();
    $prefix = substr($key, 0, 8);
    $stmt = $pdo->prepare('SELECT * FROM devices WHERE api_key_prefix = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$prefix]);
    $device = $stmt->fetch();
    if (!$device || !hash_equals($device['api_key_hash'], hash('sha256', $key))) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid device key']);
        exit;
    }
    $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    $temperature = $data['temperature'] ?? null;
    $humidity = $data['humidity'] ?? null;
    $air = filter_var($data['air'] ?? null, FILTER_VALIDATE_INT);
    $gas = filter_var($data['gasDigital'] ?? null, FILTER_VALIDATE_INT);
    $light = filter_var($data['light'] ?? null, FILTER_VALIDATE_INT);
    if (($temperature !== null && (!is_numeric($temperature) || $temperature < 0 || $temperature > 50)) ||
        ($humidity !== null && (!is_numeric($humidity) || $humidity < 0 || $humidity > 100)) ||
        $air === false || $air < 0 || $air > 4095 ||
        !in_array($gas, [0, 1], true) ||
        $light === false || $light < 0 || $light > 4095) {
        http_response_code(422);
        echo json_encode(['error' => 'Sensor values are outside the accepted ranges']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT received_at FROM readings WHERE device_id = ? ORDER BY received_at DESC LIMIT 1');
    $stmt->execute([$device['id']]);
    $last = $stmt->fetchColumn();
    if ($last && strtotime($last . ' UTC') > time() - max(10, (int)$device['sample_interval_seconds'] - 2)) {
        http_response_code(429);
        echo json_encode(['error' => 'Reading interval not reached']);
        exit;
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO readings (device_id, temperature_c, humidity_pct, air_adc, gas_digital, light_adc, measured_at) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())');
    $stmt->execute([$device['id'], $temperature, $humidity, $air, $gas, $light]);
    $pdo->prepare('UPDATE devices SET last_seen_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$device['id']]);
    evaluate_reading_alerts($pdo, $device, ['temperature_c' => $temperature, 'humidity_pct' => $humidity]);
    $pdo->commit();
    $stmt = $pdo->prepare('SELECT show_temperature,show_humidity,show_air,show_light,seconds_per_screen FROM device_display_settings WHERE device_id=?');
    $stmt->execute([$device['id']]);
    $display = $stmt->fetch() ?: ['show_temperature'=>1,'show_humidity'=>1,'show_air'=>1,'show_light'=>1,'seconds_per_screen'=>5];
    echo json_encode(['ok' => true, 'receivedAt' => gmdate('c'), 'display' => $display]);
} catch (JsonException $exception) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(503);
    echo json_encode(['error' => 'Could not store reading']);
}

