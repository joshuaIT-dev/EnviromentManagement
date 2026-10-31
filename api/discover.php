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

$raw = file_get_contents('php://input', false, null, 0, 1025);
if ($raw === false || strlen($raw) > 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Request too large']);
    exit;
}

try {
    $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    $mac = strtoupper(trim((string)($data['macAddress'] ?? '')));
    if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
        http_response_code(422);
        echo json_encode(['error' => 'Valid device MAC address required']);
        exit;
    }
    $label = trim((string)($data['deviceName'] ?? ''));
    if ($label === '') $label = 'ESP32-' . substr(str_replace(':', '', $mac), -6);
    $label = substr($label, 0, 100);
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);

    $pdo = db();
    $stmt = $pdo->prepare('SELECT status FROM device_enrollment_requests WHERE mac_address=?');
    $stmt->execute([$mac]);
    $existing = $stmt->fetchColumn();
    if ($existing === 'registered') {
        http_response_code(409);
        echo json_encode(['error' => 'Already registered; update the firmware key']);
        exit;
    }
    if ($existing === false && (int)$pdo->query("SELECT COUNT(*) FROM device_enrollment_requests WHERE status='pending'")->fetchColumn() >= 100) {
        http_response_code(429);
        echo json_encode(['error' => 'Enrollment queue is full']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO device_enrollment_requests (mac_address,device_label,client_ip,status) VALUES (?,?,?,'pending') ON DUPLICATE KEY UPDATE device_label=VALUES(device_label),client_ip=VALUES(client_ip),last_seen_at=UTC_TIMESTAMP()");
    $stmt->execute([$mac, $label, $ip]);
    http_response_code(202);
    echo json_encode(['ok' => true, 'registered' => false, 'message' => 'Device detected; awaiting admin registration']);
} catch (JsonException $exception) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode(['error' => 'Could not record device discovery']);
}
