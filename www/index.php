<?php
if (file_exists(__DIR__ . '/config.local.php')) {
    $config = require __DIR__ . '/config.local.php';
} else {
    $config = require __DIR__ . '/config.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Home Temperature</title>
<script src="https://cdn.jsdelivr.net/npm/luxon@3.4.4/build/global/luxon.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1.3.1/dist/chartjs-adapter-luxon.umd.min.js"></script>
<style>
  :root {
    --bg: #f0f0f5;
    --card: rgba(255,255,255,0.85);
    --card-solid: #ffffff;
    --text: #1a1a2e;
    --text2: #6b7280;
    --accent: #6366f1;
    --border: rgba(0,0,0,0.08);
    --radius: 16px;
    --shadow: 0 4px 24px rgba(0,0,0,0.07);
    --shadow-lg: 0 8px 40px rgba(0,0,0,0.12);
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #0f0f13;
      --card: rgba(255,255,255,0.05);
      --card-solid: #1a1a24;
      --text: #f1f1f5;
      --text2: #6b7280;
      --accent: #818cf8;
      --border: rgba(255,255,255,0.08);
      --shadow: 0 4px 24px rgba(0,0,0,0.3);
      --shadow-lg: 0 8px 40px rgba(0,0,0,0.5);
    }
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { height: 100%; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: var(--bg);
    color: var(--text);
    display: flex;
    flex-direction: column;
    min-height: 100dvh;
    overflow-x: hidden;
  }

  .topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    flex-shrink: 0;
  }
  .topbar h1 { font-size: 1rem; font-weight: 600; color: var(--text2); letter-spacing: -0.01em; }
  .topbar a { color: var(--text2); text-decoration: none; font-size: 0.85rem; opacity: 0.7; transition: opacity 0.15s; }
  .topbar a:hover { opacity: 1; }

  .chart-hero { flex: 1; padding: 0 16px; min-height: 0; }
  .chart-wrap {
    background: var(--card);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    padding: 20px 12px 12px;
    height: 100%;
  }

  .controls {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 20px;
    flex-shrink: 0;
  }
  .pill {
    display: inline-flex;
    align-items: center;
    padding: 8px 16px;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid var(--border);
    background: var(--card);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    color: var(--text);
    box-shadow: var(--shadow);
    transition: transform 0.1s;
    -webkit-tap-highlight-color: transparent;
    white-space: nowrap;
  }
  .pill:active { transform: scale(0.96); }
  .pill.accent { background: var(--accent); color: #fff; border-color: transparent; }
  select.pill {
    appearance: none; -webkit-appearance: none;
    padding-right: 28px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
  }
  .spacer { flex: 1; }
  .status-bar { padding: 0 20px 12px; font-size: 0.78rem; color: var(--text2); min-height: 20px; }

  .drawer-backdrop {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.35);
    backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    z-index: 10;
  }
  .drawer-backdrop.open { display: block; }

  .sensor-drawer {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--card-solid);
    border-radius: 24px 24px 0 0;
    box-shadow: var(--shadow-lg);
    z-index: 20;
    transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
    max-height: 70dvh;
    display: flex; flex-direction: column;
  }
  .sensor-drawer.open { transform: translateY(0); }
  .drawer-handle { width: 36px; height: 4px; background: var(--border); border-radius: 2px; margin: 12px auto 0; flex-shrink: 0; }
  .drawer-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px 10px; flex-shrink: 0;
  }
  .drawer-header strong { font-size: 1rem; }
  .drawer-header button { background: none; border: none; color: var(--accent); font-size: 0.875rem; cursor: pointer; padding: 4px 8px; }
  .sensor-list { overflow-y: auto; padding: 0 20px 40px; flex: 1; }
  .sensor-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 0; border-bottom: 1px solid var(--border);
  }
  .sensor-item:last-child { border-bottom: none; }
  .sensor-item input[type="checkbox"] { width: 20px; height: 20px; accent-color: var(--accent); flex-shrink: 0; }
  .sensor-item label { flex: 1; font-size: 0.95rem; }
  .sensor-item .val { font-size: 0.875rem; font-weight: 600; color: var(--accent); flex-shrink: 0; }

  .spinner {
    display: inline-block; width: 13px; height: 13px;
    border: 2px solid var(--border); border-top-color: var(--accent);
    border-radius: 50%; animation: spin 0.8s linear infinite;
    vertical-align: middle; margin-right: 5px;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  @media (min-width: 600px) {
    .chart-hero { padding: 0 24px; }
    .controls, .topbar { padding-left: 24px; padding-right: 24px; }
    .sensor-drawer { left: auto; right: 24px; bottom: 24px; width: 360px; border-radius: 24px; max-height: 60dvh; }
  }
</style>
</head>
<body>

  <div class="topbar">
    <h1>&#127777; Home Temperature</h1>
    <a href="settings.php">&#9881; Settings</a>
  </div>

  <div class="chart-hero">
    <div class="chart-wrap">
      <canvas id="chart"></canvas>
    </div>
  </div>

  <div class="controls">
    <button class="pill accent" id="toggleSensors">Sensors</button>
    <select class="pill" id="days">
      <option value="1">1 day</option>
      <option value="7" <?= $config['default_days'] == 7 ? 'selected' : '' ?>>7 days</option>
      <option value="14" <?= $config['default_days'] == 14 ? 'selected' : '' ?>>14 days</option>
      <option value="30" <?= $config['default_days'] == 30 ? 'selected' : '' ?>>30 days</option>
    </select>
    <div class="spacer"></div>
    <button class="pill" id="updateBtn">&#8635; Refresh</button>
  </div>
  <p class="status-bar" id="status"></p>

  <div class="drawer-backdrop" id="drawerBackdrop"></div>
  <div class="sensor-drawer" id="sensorDrawer">
    <div class="drawer-handle"></div>
    <div class="drawer-header">
      <strong>Sensors</strong>
      <button id="loadSensors">Load list</button>
    </div>
    <div class="sensor-list" id="sensorList">
      <p style="color:var(--text2);font-size:0.9rem;">Tap “Load list” to fetch sensors from Home Assistant.</p>
    </div>
  </div>

<script>
const defaultSensors = <?= json_encode($config['default_sensors']) ?>;
const defaultDays = <?= intval($config['default_days']) ?>;
let chart = null;
let selectedSensors = new Set(defaultSensors);

document.getElementById('days').value = defaultDays;

function isDark() { return window.matchMedia('(prefers-color-scheme: dark)').matches; }

async function savePrefs() {
  try {
    await fetch('api.php?action=save_prefs', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        default_sensors: Array.from(selectedSensors),
        default_days: parseInt(document.getElementById('days').value)
      })
    });
  } catch (e) { console.warn('Could not save prefs:', e); }
}

function openDrawer() {
  document.getElementById('sensorDrawer').classList.add('open');
  document.getElementById('drawerBackdrop').classList.add('open');
}
function closeDrawer() {
  document.getElementById('sensorDrawer').classList.remove('open');
  document.getElementById('drawerBackdrop').classList.remove('open');
}

document.getElementById('toggleSensors').addEventListener('click', openDrawer);
document.getElementById('drawerBackdrop').addEventListener('click', closeDrawer);

document.getElementById('loadSensors').addEventListener('click', async () => {
  const list = document.getElementById('sensorList');
  list.innerHTML = '<span class="spinner"></span> Loading…';
  try {
    const res = await fetch('api.php?action=sensors');
    const sensors = await res.json();
    if (!Array.isArray(sensors)) throw new Error('Bad response');
    list.innerHTML = '';
    sensors.forEach(s => {
      const div = document.createElement('div');
      div.className = 'sensor-item';
      const checked = selectedSensors.has(s.entity_id) ? 'checked' : '';
      div.innerHTML = `
        <input type="checkbox" id="cb_${s.entity_id}" value="${s.entity_id}" ${checked}>
        <label for="cb_${s.entity_id}">${s.name}</label>
        <span class="val">${parseFloat(s.state).toFixed(1)}${s.unit}</span>
      `;
      list.appendChild(div);
    });
    list.querySelectorAll('input').forEach(cb => {
      cb.addEventListener('change', () => {
        if (cb.checked) selectedSensors.add(cb.value);
        else selectedSensors.delete(cb.value);
        savePrefs();
      });
    });
  } catch (e) {
    list.innerHTML = '<p style="color:var(--text2)">Error loading sensors</p>';
  }
});

document.getElementById('updateBtn').addEventListener('click', () => { savePrefs(); updateChart(); });
document.getElementById('days').addEventListener('change', () => { savePrefs(); updateChart(); });

async function updateChart() {
  const days = document.getElementById('days').value;
  const ids = Array.from(selectedSensors).join(',');
  if (!ids) { setStatus('Select at least one sensor'); return; }
  setStatus('<span class="spinner"></span> Loading…');
  try {
    const res = await fetch(`api.php?action=history&days=${days}&entity_ids=${encodeURIComponent(ids)}`);
    const data = await res.json();
    if (!Array.isArray(data)) throw new Error(JSON.stringify(data));
    renderChart(data, days);
    setStatus(`Showing ${days} day(s) \u00b7 ${data.length} sensor(s)`);
  } catch (e) {
    setStatus('Error: ' + e.message);
    console.error('updateChart error:', e);
  }
}

function setStatus(html) { document.getElementById('status').innerHTML = html; }

function renderChart(haData, days) {
  const ctx = document.getElementById('chart').getContext('2d');
  const colors = ['#6366f1','#f59e0b','#10b981','#ef4444','#8b5cf6','#3b82f6'];
  const datasets = [];
  haData.forEach((sensorArr, idx) => {
    if (!sensorArr || !sensorArr.length) return;
    const label = sensorArr[0].attributes?.friendly_name || sensorArr[0].entity_id;
    const points = sensorArr
      .map(p => {
        const ts = luxon.DateTime.fromISO(p.last_changed).toMillis();
        const val = parseFloat(p.state);
        return { x: ts, y: isNaN(val) ? null : parseFloat(val.toFixed(1)) };
      })
      .filter(p => p.y !== null && !isNaN(p.x));
    datasets.push({
      label, data: points,
      borderColor: colors[idx % colors.length],
      backgroundColor: colors[idx % colors.length] + '18',
      fill: true, tension: 0.4, pointRadius: 0, pointHitRadius: 12, borderWidth: 2.5,
    });
  });
  if (chart) chart.destroy();
  const dark = isDark();
  const gridCol = dark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
  const tickCol = dark ? '#6b7280' : '#9ca3af';
  chart = new Chart(ctx, {
    type: 'line',
    data: { datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          position: 'top',
          labels: { color: dark ? '#e5e7eb' : '#374151', usePointStyle: true, pointStyleWidth: 8, boxHeight: 6, padding: 16, font: { size: 12 } }
        },
        tooltip: {
          backgroundColor: dark ? 'rgba(15,15,19,0.95)' : 'rgba(255,255,255,0.95)',
          titleColor: dark ? '#f1f1f5' : '#1a1a2e',
          bodyColor: dark ? '#d1d5db' : '#4b5563',
          borderColor: dark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.08)',
          borderWidth: 1, padding: 12, displayColors: true, cornerRadius: 10,
          callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y.toFixed(1)}\u00b0C` }
        }
      },
      scales: {
        x: {
          type: 'time',
          time: { tooltipFormat: 'dd MMM HH:mm', displayFormats: { hour: 'HH:mm', day: 'dd MMM' } },
          grid: { color: gridCol },
          border: { display: false },
          ticks: { color: tickCol, maxRotation: 0, autoSkip: true, font: { size: 11 } }
        },
        y: {
          grid: { color: gridCol },
          border: { display: false },
          ticks: { color: tickCol, font: { size: 11 }, callback: (v) => v.toFixed(1) + '\u00b0' }
        }
      }
    }
  });
}

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (chart) updateChart(); });

if (defaultSensors.length) {
  updateChart();
} else {
  setStatus('Tap <strong>Sensors</strong> to get started, or go to &#9881; Settings.');
}
</script>
</body>
</html>
