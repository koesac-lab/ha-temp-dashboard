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
  .card, .settings-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 20px; margin-bottom: 16px; border: 1px solid var(--border); }
  .card h2, .settings-card h2 { font-size: 0.95rem; color: var(--text2); margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
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
  .hint { font-size: 0.8rem; color: var(--text2); margin-top: 5px; margin-bottom: 0; }
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
  /* Sensor visibility */
  .sensor-group { margin-bottom: 1.5rem; }
  .sensor-group h3 { font-size: 0.8rem; text-transform: uppercase; opacity: 0.5; margin-bottom: 0.5rem; }
  .sensor-toggle { display: flex; align-items: center; gap: 0.5rem; padding: 0.3rem 0; cursor: pointer; font-size: 0.95rem; }
  .sensor-toggle input { accent-color: var(--accent); }
  .hidden-sensor-row { display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0; border-bottom: 1px solid var(--border); }
  .btn-restore { font-size: 0.8rem; padding: 0.2rem 0.6rem; background: var(--accent); color: white; border: none; border-radius: 4px; cursor: pointer; }
  /* Save status indicator */
  .save-status { font-size: 0.8rem; margin-left: 1rem; }
  .save-status.saving { color: var(--text2); }
  .save-status.saved { color: #10b981; }
  .save-status.error { color: #ef4444; }
  .loading { font-size: 0.9rem; color: var(--text2); }
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
  </div>

  <button type="submit" class="btn">Save Settings</button>
</form>

<!-- SENSOR VISIBILITY SECTION -->
<div id="sensors" class="settings-card" style="margin-top:24px; scroll-margin-top: 80px;">
  <h2>Sensor Visibility <span id="sensor-save-status" class="save-status"></span></h2>
  <p class="hint" style="margin-bottom:14px;">Uncheck a sensor to hide it from the drawer and chart. Changes save automatically.</p>
  <div id="visible-sensors-list">
    <p class="loading">Loading sensors…</p>
  </div>
</div>

<div id="hidden-sensors-card" class="settings-card" style="display:none;">
  <h2>Hidden Sensors</h2>
  <p class="hint" style="margin-bottom:14px;">Restore a sensor to make it visible again in the drawer.</p>
  <div id="hidden-sensors-list"></div>
</div>

<script>
(function () {
  if (window.location.hash === '#sensors') {
    setTimeout(() => {
      document.getElementById('sensors')?.scrollIntoView({ behavior: 'smooth' });
    }, 200);
  }

  let allSensors = [];
  let prefs = {};

  Promise.all([
    fetch('api.php?action=get_prefs').then(r => r.json()),
    fetch('api.php?action=sensors').then(r => r.json())
  ]).then(([prefsData, sensorsData]) => {
    prefs = prefsData || {};
    allSensors = Array.isArray(sensorsData) ? sensorsData : [];
    renderSensorVisibility();
  }).catch(() => {
    document.getElementById('visible-sensors-list').innerHTML = '<p class="hint">Could not load sensors.</p>';
  });

  function renderSensorVisibility() {
    const hiddenIds = prefs.hidden_sensors || [];
    const visibleSensors = allSensors.filter(s => !hiddenIds.includes(s.entity_id));
    const hiddenSensors  = allSensors.filter(s =>  hiddenIds.includes(s.entity_id));

    // Group visible sensors by domain
    const groups = {};
    visibleSensors.forEach(s => {
      const type = s.entity_id.split('.')[0];
      if (!groups[type]) groups[type] = [];
      groups[type].push(s);
    });

    const visibleList = document.getElementById('visible-sensors-list');
    visibleList.innerHTML = Object.entries(groups).map(([type, sensors]) => `
      <div class="sensor-group">
        <h3>${type}</h3>
        ${sensors.map(s => `
          <label class="sensor-toggle">
            <input type="checkbox" checked
              data-sensor="${s.entity_id}"
              onchange="toggleSensorVisibility('${s.entity_id}', this.checked)">
            ${s.name || s.entity_id}
          </label>
        `).join('')}
      </div>
    `).join('') || '<p class="hint">No visible sensors.</p>';

    const hiddenCard = document.getElementById('hidden-sensors-card');
    const hiddenList = document.getElementById('hidden-sensors-list');

    if (hiddenSensors.length > 0) {
      hiddenCard.style.display = 'block';
      hiddenList.innerHTML = hiddenSensors.map(s => `
        <div class="hidden-sensor-row">
          <span>${s.name || s.entity_id}</span>
          <button class="btn-restore" onclick="restoreSensor('${s.entity_id}')">Restore</button>
        </div>
      `).join('');
    } else {
      hiddenCard.style.display = 'none';
    }
  }

  window.toggleSensorVisibility = function (entityId, visible) {
    if (!visible) {
      prefs.hidden_sensors = [...(prefs.hidden_sensors || []), entityId];
    } else {
      prefs.hidden_sensors = (prefs.hidden_sensors || []).filter(id => id !== entityId);
    }
    savePrefsAjax();
    renderSensorVisibility();
  };

  window.restoreSensor = function (entityId) {
    prefs.hidden_sensors = (prefs.hidden_sensors || []).filter(id => id !== entityId);
    savePrefsAjax();
    renderSensorVisibility();
  };

  function savePrefsAjax() {
    const status = document.getElementById('sensor-save-status');
    status.textContent = 'Saving…';
    status.className = 'save-status saving';

    fetch('api.php?action=save_prefs', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(prefs)
    })
    .then(r => r.json())
    .then(() => {
      status.textContent = 'Saved ✓';
      status.className = 'save-status saved';
      setTimeout(() => { status.textContent = ''; }, 2000);
    })
    .catch(() => {
      status.textContent = 'Error saving';
      status.className = 'save-status error';
    });
  }
})();
</script>

</body>
</html>
