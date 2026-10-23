<?php
require_once __DIR__ . '/../assets/php/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
try {
    $data = dashboard_data(db());
    $device = $data['device'];
    $reading = $data['reading'];
    if (!$device || !$reading) {
        echo json_encode(['demo' => true, 'online' => false]);
        exit;
    }
    $online = strtotime($device['last_seen_at'] . ' UTC') >= time() - ((int)$device['offline_timeout_minutes'] * 60);
    $temp = $reading['temperature_c'] === null ? null : (float)$reading['temperature_c'];
    $humidity = $reading['humidity_pct'] === null ? null : (float)$reading['humidity_pct'];
    [$tempStatus, $tempTone] = reading_status($temp, (float)$device['temp_min'], (float)$device['temp_max']);
    [$humidityStatus, $humidityTone] = reading_status($humidity, (float)$device['humidity_min'], (float)$device['humidity_max']);
    $attention = (int)(in_array($tempTone, ['warning'], true) + in_array($humidityTone, ['warning'], true) + ($data['open_alerts'] > 0));
    $time = time_ago($reading['measured_at']);
    echo json_encode([
        'demo' => false,
        'room' => $device['location'],
        'source' => 'ESP32',
        'updatedAt' => $time,
        'online' => $online,
        'attentionCount' => $attention,
        'message' => $attention ? 'One or more readings or alerts need attention.' : 'Temperature and humidity are within their configured ranges.',
        'readings' => [
            'temperature' => ['value' => $temp === null ? null : number_format($temp, 1), 'unit' => '°C', 'status' => $tempStatus, 'tone' => $tempTone],
            'humidity' => ['value' => $humidity === null ? null : number_format($humidity, 1), 'unit' => '%', 'status' => $humidityStatus, 'tone' => $humidityTone],
            'air' => ['value' => (int)$reading['air_adc'], 'unit' => 'raw ADC', 'status' => $device['air_warning_adc'] === null ? 'Uncalibrated' : 'See alerts', 'tone' => 'neutral'],
            'light' => ['value' => (int)$reading['light_adc'], 'unit' => 'raw ADC', 'status' => 'Night rule not calibrated', 'tone' => 'neutral'],
        ],
        'uptime' => $data['coverage'] === null ? '—' : $data['coverage'] . '%',
        'alertCount' => $data['open_alerts'],
        'alerts' => array_map(static fn($a) => ['time' => $a['created_at'], 'room' => $device['location'], 'sensor' => $a['sensor'], 'message' => $a['message'], 'severity' => $a['severity']], $data['alerts']),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode(['error' => 'Summary is unavailable until the database is ready.']);
}

