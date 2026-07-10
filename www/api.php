<?php
header('Content-Type: application/json');

if (file_exists(__DIR__ . '/config.local.php')) {
    $config = require __DIR__ . '/config.local.php';
} else {
    $config = require __DIR__ . '/config.php';
}

function ha_get($endpoint, $params = []) {
    global $config;
    $url = rtrim($config['ha_url'], '/') . $endpoint;
    if (!empty($params)) $url .= '?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $config['ha_token'],
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        http_response_code(500); echo json_encode(['error' => curl_error($ch)]); exit;
    }
    curl_close($ch);
    if ($http_code >= 400) {
        http_response_code($http_code);
        echo json_encode(['error' => 'HA returned HTTP ' . $http_code, 'response' => $response]); exit;
    }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500); echo json_encode(['error' => 'JSON: ' . json_last_error_msg()]); exit;
    }
    return $data;
}

function save_config($new_config) {
    $php = "<?php\nreturn " . var_export($new_config, true) . ";\n";
    return file_put_contents(__DIR__ . '/config.local.php', $php) !== false;
}

// Fetch one 30-day (or shorter) history window and accumulate into $raw / $names.
function fetch_chunk($entity_ids, $start, $end, &$raw, &$names) {
    $ids  = array_map('trim', explode(',', $entity_ids));
    $data = ha_get('/api/history/period/' . $start->format('Y-m-d\TH:i:s\Z'), [
        'filter_entity_id' => $entity_ids,
        'end_time'         => $end->format('Y-m-d\TH:i:s\Z'),
        'minimal_response' => '',
        'no_attributes'    => '',
    ]);
    if (!is_array($data)) return;
    foreach ($data as $i => $arr) {
        if (empty($arr)) continue;
        $eid = $arr[0]['entity_id'] ?? ($ids[$i] ?? '');
        if (!$eid) continue;
        if (!isset($names[$eid]))
            $names[$eid] = $arr[0]['attributes']['friendly_name'] ?? $eid;
        if (!isset($raw[$eid])) $raw[$eid] = [];
        foreach ($arr as $pt) {
            $val = is_numeric($pt['state']) ? floatval($pt['state']) : null;
            if ($val === null) continue;
            $ts = strtotime($pt['last_changed']);
            if ($ts === false) continue;
            $raw[$eid][] = [$ts, $val];
        }
    }
}

$action = $_GET['action'] ?? '';

// ── Save prefs ───────────────────────────────────────────────────────────────
if ($action === 'save_prefs') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) { http_response_code(400); echo json_encode(['error' => 'Invalid body']); exit; }
    $new = $config;
    if (isset($body['default_sensors']) && is_array($body['default_sensors']))
        $new['default_sensors'] = array_values(array_filter($body['default_sensors'], 'is_string'));
    if (isset($body['default_days'])) {
        $d = intval($body['default_days']);
        if ($d >= 1) $new['default_days'] = $d;
    }
    echo save_config($new) ? json_encode(['ok' => true]) : (http_response_code(500) ?: json_encode(['error' => 'Write failed']));
    exit;
}

// ── Sensors ────────────────────────────────────────────────────────────────────
if ($action === 'sensors') {
    $states  = ha_get('/api/states');
    $sensors = [];
    foreach ($states as $state) {
        if (strpos($state['entity_id'], 'sensor.') !== 0) continue;
        $unit = $state['attributes']['unit_of_measurement'] ?? '';
        $dc   = $state['attributes']['device_class'] ?? '';
        if ($dc === 'temperature'
            || stripos($unit, '\xc2\xb0') !== false
            || stripos($unit, 'C') !== false
            || stripos($unit, 'F') !== false) {
            $sensors[] = [
                'entity_id' => $state['entity_id'],
                'name'      => $state['attributes']['friendly_name'] ?? $state['entity_id'],
                'unit'      => $unit,
                'state'     => $state['state'],
            ];
        }
    }
    echo json_encode($sensors);
    exit;
}

// ── History: raw state changes (≤ 30 days) ─────────────────────────────────────
if ($action === 'history') {
    $days = max(1, min(30, intval($_GET['days'] ?? $config['default_days'])));
    $entity_ids = $_GET['entity_ids'] ?? implode(',', $config['default_sensors']);
    if (empty($entity_ids)) { http_response_code(400); echo json_encode(['error' => 'No sensors']); exit; }

    $raw = []; $names = [];
    $end   = new DateTime('now', new DateTimeZone('UTC'));
    $start = (clone $end)->modify("-{$days} days");
    fetch_chunk($entity_ids, $start, $end, $raw, $names);

    $result = [];
    foreach ($raw as $eid => $points) {
        $out = [];
        foreach ($points as [$ts, $val]) {
            $out[] = [
                'entity_id'    => $eid,
                'state'        => $val,
                'last_changed' => gmdate('Y-m-d\TH:i:s\Z', $ts),
                'attributes'   => ['friendly_name' => $names[$eid] ?? $eid],
            ];
        }
        if (!empty($out)) $result[] = $out;
    }
    echo json_encode($result);
    exit;
}

// ── LTS: chunked history + server-side hourly downsampling ─────────────────────
// /api/statistics_during_period is WebSocket-only (404 over HTTP REST).
// Instead: fetch in 30-day chunks with minimal_response + no_attributes,
// bucket readings into 1-hour slots, return the mean. Same shape as history.
if ($action === 'lts') {
    $days = max(1, intval($_GET['days'] ?? 90));
    $entity_ids = $_GET['entity_ids'] ?? implode(',', $config['default_sensors']);
    if (empty($entity_ids)) { http_response_code(400); echo json_encode(['error' => 'No sensors']); exit; }

    $raw   = [];
    $names = [];
    $end   = new DateTime('now', new DateTimeZone('UTC'));
    $chunk = (clone $end)->modify("-{$days} days");

    while ($chunk < $end) {
        $next = min((clone $chunk)->modify('+30 days'), $end);
        fetch_chunk($entity_ids, $chunk, $next, $raw, $names);
        $chunk = $next;
    }

    // Downsample: floor each reading to its hour bucket, compute mean per bucket
    $result = [];
    foreach ($raw as $eid => $points) {
        if (empty($points)) continue;
        $buckets = [];
        foreach ($points as [$ts, $val]) {
            $b = $ts - ($ts % 3600);
            if (!isset($buckets[$b])) $buckets[$b] = [0.0, 0];
            $buckets[$b][0] += $val;
            $buckets[$b][1]++;
        }
        ksort($buckets);
        $out = [];
        foreach ($buckets as $b => [$sum, $cnt]) {
            $out[] = [
                'entity_id'    => $eid,
                'state'        => round($sum / $cnt, 1),
                'last_changed' => gmdate('Y-m-d\TH:i:s\Z', $b),
                'attributes'   => ['friendly_name' => $names[$eid] ?? $eid],
            ];
        }
        $result[] = $out;
    }
    echo json_encode($result);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
