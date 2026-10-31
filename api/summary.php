<?php
require_once __DIR__ . '/../assets/php/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
try {
    $requestedId = filter_input(INPUT_GET, 'device_id', FILTER_VALIDATE_INT);
    $data = dashboard_data(db(), $requestedId && $requestedId > 0 ? (int)$requestedId : null);
    $device = $data['device'];
    if (!$device) {
        echo json_encode(['demo' => true, 'online' => false]);
        exit;
    }

    $reading = $data['reading'];
    $waiting = !$reading;
    $deviceEnabled = (bool)$device['is_active'];
    $online = $deviceEnabled && !$waiting && $device['last_seen_at'] && strtotime($device['last_seen_at'] . ' UTC') >= time() - ((int)$device['offline_timeout_minutes'] * 60);
    $temp = !$reading || $reading['temperature_c'] === null ? null : (float)$reading['temperature_c'];
    $humidity = !$reading || $reading['humidity_pct'] === null ? null : (float)$reading['humidity_pct'];
    [$tempStatus, $tempTone] = reading_status($temp, (float)$device['temp_min'], (float)$device['temp_max']);
    [$humidityStatus, $humidityTone] = reading_status($humidity, (float)$device['humidity_min'], (float)$device['humidity_max']);
    $attention = (int)(in_array($tempTone, ['warning'], true) + in_array($humidityTone, ['warning'], true) + ($data['open_alerts'] > 0));
    $time = $reading ? time_ago($reading['measured_at']) : 'Waiting for first reading';
    echo json_encode([
        'demo' => false,
        'waiting' => $waiting,
        'deviceEnabled' => $deviceEnabled,
        'deviceId' => (int)$device['id'],
        'room' => $device['location'],
        'source' => 'ESP32',
        'updatedAt' => $time,
        'online' => (bool)$online,
        'attentionCount' => $attention,
        'message' => !$deviceEnabled ? 'Device is disabled. Showing its saved readings.' : ($waiting ? 'Device selected. Waiting for its first sensor reading.' : ($attention ? 'One or more readings or alerts need attention.' : 'Temperature and humidity are within their configured ranges.')),
        'readings' => [
            'temperature' => ['value' => $temp === null ? null : number_format($temp, 1), 'unit' => '°C', 'status' => $waiting ? 'Waiting for data' : $tempStatus, 'tone' => $tempTone],
            'humidity' => ['value' => $humidity === null ? null : number_format($humidity, 1), 'unit' => '%', 'status' => $waiting ? 'Waiting for data' : $humidityStatus, 'tone' => $humidityTone],
            'air' => ['value' => $reading ? (int)$reading['air_adc'] : null, 'unit' => 'raw ADC', 'status' => $waiting ? 'Waiting for data' : ($device['air_warning_adc'] === null ? 'Uncalibrated' : 'See alerts'), 'tone' => 'neutral'],
            'light' => ['value' => $reading ? (int)$reading['light_adc'] : null, 'unit' => 'raw ADC', 'status' => $waiting ? 'Waiting for data' : 'Night rule not calibrated', 'tone' => 'neutral'],
        ],
        'uptime' => $data['coverage'] === null ? '—' : $data['coverage'] . '%',
        'alertCount' => $data['open_alerts'],
        'alerts' => array_map(static fn($alert) => ['time' => $alert['created_at'], 'room' => $device['location'], 'sensor' => $alert['sensor'], 'message' => $alert['message'], 'severity' => $alert['severity']], $data['alerts']),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode(['error' => 'Summary is unavailable until the database is ready.']);
}
