<?php
if (file_exists(__DIR__ . '/config.local.php')) {
    $config = require __DIR__ . '/config.local.php';
} else {
    $config = require __DIR__ . '/config.php';
}

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $config;

    if (!empty(trim($_POST['ha_url'] ?? ''))) {
        $new['ha_url'] = trim($_POST['ha_url']);
    }
    if (!empty(trim($_POST['ha_token'] ?? ''))) {
        $new['ha_token'] = trim($_POST['ha_token']);
    }
    if (isset($_POST['default_days'])) {
        $days = intval($_POST['default_days']);
        if ($days >= 1 && $days <= 30) $new['default_days'] = $days;
    }
    if (isset($_POST['default_sensors'])) {
        $raw = trim($_POST['default_sensors']);
        $new['default_sensors'] = $raw === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
    if (isset($_POST['latitude']) && $_POST['latitude'] !== '') {
        $lat = floatval($_POST['latitude']);
        if ($lat >= -90 && $lat <= 90) $new['latitude'] = $lat;
    }
    if (isset($_POST['longitude']) && $_POST['longitude'] !== '') {
        $lon = floatval($_POST['longitude']);
        if ($lon >= -180 && $lon <= 180) $new['longitude'] = $lon;
    }

    $php = "<?php\nreturn " . var_export($new, true) . ";\n";
    $target = __DIR__ . '/config.local.php';

    if (file_put_contents($target, $php) !== false) {
        $config = $new;
        $success = 'Settings saved.';
    } else {
        $error = 'Could not write config.local.php — check file permissions.';
    }
}

$tokenSet = !empty($config['ha_token']) && $config['ha_token'] !== 'YOUR_LONG_LIVED_ACCESS_TOKEN_HERE';
$sensorsStr = implode(', ', $config['default_sensors'] ?? []);
$lat = $config['latitude'] ?? 51.5074;
$lon = $config['longitude'] ?? -0.1278;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Settings · Home Temperature</title>
<style>
  :root {
    --bg: #f0f0f5;
    --card: #ffffff;
    --text: #1a1a2e;
    --text2: #6b7280;
    --accent: #6366f1;
    --border: rgba(0,0,0,0.1);
    --radius: 16px;
    --shadow: 0 4px 24px rgba(0,0,0,0.07);
    --green: #10b981;
    --red: #ef4444;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #0f0f13;
      --card: #1a1a24;
      --text: #f1f1f5;
      --text2: #6b7280;
      --accent: #818cf8;
      --border: rgba(255,255,255,0.08);
      --shadow: 0 4px 24px rgba(0,0,0,0.3);
    }
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: var(--bg);
    color: var(--text);
    padding: 20px;
    max-width: 600px;
    margin: 0 auto;
  }
  .topbar { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
  .topbar a { color: var(--accent); text-decoration: none; font-size: 0.95rem; }
  h1 { font-size: 1.5rem; letter-spacing: -0.02em; }
  .card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 20px; margin-bottom: 16px; border: 1px solid var(--border); }
  .card h2 { font-size: 0.95rem; color: var(--text2); margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
  .field { margin-bottom: 18px; }
  .field:last-child { margin-bottom: 0; }
  .field-row { display: flex; gap: 12px; }
  .field-row .field { flex: 1; margin-bottom: 0; }
  label { display: block; font-size: 0.82rem; color: var(--text2); margin-bottom: 6px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; }
  input[type="text"],
  input[type="password"],
  input[type="number"] {
    width: 100%;
    background: var(--bg);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 1rem;
    outline: none;
    transition: border-color 0.15s;
  }
  input:focus { border-color: var(--accent); }
  .hint { font-size: 0.8rem; color: var(--text2); margin-top: 5px; }
  .token-status { display: inline-flex; align-items: center; gap: 5px; font-size: 0.82rem; margin-top: 5px; }
  .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
  .dot.set { background: var(--green); }
  .dot.unset { background: var(--red); }
  .btn {
    display: inline-block;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 999px;
    padding: 12px 24px;
    font-size: 1rem;
    cursor: pointer;
    width: 100%;
    margin-top: 4px;
    font-weight: 500;
  }
  .alert { border-radius: var(--radius); padding: 12px 16px; margin-bottom: 16px; font-size: 0.95rem; }
  .alert.success { background: #d1fae5; color: #065f46; }
  .alert.error   { background: #fee2e2; color: #991b1b; }
  @media (prefers-color-scheme: dark) {
    .alert.success { background: #052e16; color: #6ee7b7; }
    .alert.error   { background: #3b0f0f; color: #fca5a5; }
  }
</style>
</head>
<body>

<div class="topbar">
  <a href="index.php">← Dashboard</a>
  <h1>Settings</h1>
</div>

<?php if ($success): ?>
  <div class="alert success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
  <div class="card">
    <h2>Home Assistant</h2>
    <div class="field">
      <label for="ha_url">URL</label>
      <input type="text" id="ha_url" name="ha_url"
             value="<?= htmlspecialchars($config['ha_url']) ?>"
             placeholder="http://homeassistant.local:8123">
    </div>
    <div class="field">
      <label for="ha_token">Access Token</label>
      <input type="password" id="ha_token" name="ha_token"
             placeholder="Paste new token to update" autocomplete="new-password">
      <div class="token-status">
        <span class="dot <?= $tokenSet ? 'set' : 'unset' ?>"></span>
        <?= $tokenSet ? 'Token is set — leave blank to keep current' : 'No token configured' ?>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>Location</h2>
    <p class="hint" style="margin-bottom:14px;">Used to calculate sunrise &amp; sunset on the chart.</p>
    <div class="field-row">
      <div class="field">
        <label for="latitude">Latitude</label>
        <input type="number" id="latitude" name="latitude"
               value="<?= htmlspecialchars($lat) ?>" step="0.0001" min="-90" max="90"
               placeholder="51.5074">
      </div>
      <div class="field">
        <label for="longitude">Longitude</label>
        <input type="number" id="longitude" name="longitude"
               value="<?= htmlspecialchars($lon) ?>" step="0.0001" min="-180" max="180"
               placeholder="-0.1278">
      </div>
    </div>
  </div>

  <div class="card">
    <h2>Chart Defaults</h2>
    <div class="field">
      <label for="default_days">Default days</label>
      <input type="number" id="default_days" name="default_days"
             value="<?= intval($config['default_days']) ?>" min="1" max="30">
    </div>
    <div class="field">
      <label for="default_sensors">Default sensors</label>
      <input type="text" id="default_sensors" name="default_sensors"
             value="<?= htmlspecialchars($sensorsStr) ?>"
             placeholder="sensor.bedroom_temp, sensor.lounge_temp">
      <p class="hint">Comma-separated entity IDs.</p>
    </div>
  </div>

  <button type="submit" class="btn">Save Settings</button>
</form>

</body>
</html>
