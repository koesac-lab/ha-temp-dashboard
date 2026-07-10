<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/config.php';

function ha_request($endpoint, $params = []) {
    global $config;
    $url = rtrim($config['ha_url'], '/') . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $config['ha_token'],
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        http_response_code(500);
        echo json_encode(['error' => curl_error($ch)]);
        exit;
    }
    curl_close($ch);
    if ($http_code >= 400) {
        http_response_code($http_code);
        echo json_encode(['error' => 'Home Assistant returned HTTP ' . $http_code, 'response' => $response]);
        exit;
    }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        echo json_encode(['error' => 'JSON decode error: ' . json_last_error_msg()]);
        exit;
    }
    return $data;
}

$action = $_GET['action'] ?? '';

if ($action === 'sensors') {
    $states = ha_request('/api/states');
    $sensors = [];
    foreach ($states as $state) {
        if (strpos($state['entity_id'], 'sensor.') === 0) {
            $unit = $state['attributes']['unit_of_measurement'] ?? '';
            $device_class = $state['attributes']['device_class'] ?? '';
            if ($device_class === 'temperature' || stripos($unit, '°') !== false || stripos($unit, 'C') !== false || stripos($unit, 'F') !== false) {
                $sensors[] = [
                    'entity_id' => $state['entity_id'],
                    'name' => $state['attributes']['friendly_name'] ?? $state['entity_id'],
                    'unit' => $unit,
                    'state' => $state['state'],
                ];
            }
        }
    }
    echo json_encode($sensors);
    exit;
}

if ($action === 'history') {
    $days = intval($_GET['days'] ?? $config['default_days']);
    if ($days < 1) $days = 1;
    if ($days > 30) $days = 30;

    $entity_ids = $_GET['entity_ids'] ?? '';
    if (empty($entity_ids)) {
        $entity_ids = implode(',', $config['default_sensors']);
    }
    if (empty($entity_ids)) {
        http_response_code(400);
        echo json_encode(['error' => 'No sensors selected and no defaults configured']);
        exit;
    }

    $end = new DateTime('now', new DateTimeZone('UTC'));
    $start = clone $end;
    $start->modify("-{$days} days");

    $start_str = $start->format('Y-m-d\TH:i:s\Z');
    $end_str = $end->format('Y-m-d\TH:i:s\Z');

    $params = [
        'filter_entity_id' => $entity_ids,
        'end_time' => $end_str,
    ];

    $history = ha_request('/api/history/period/' . $start_str, $params);
    echo json_encode($history);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
