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
    --bg: #f5f5f7;
    --card: #ffffff;
    --text: #1d1d1f;
    --text2: #86868b;
    --accent: #0071e3;
    --border: #d2d2d7;
    --radius: 12px;
    --shadow: 0 2px 12px rgba(0,0,0,0.08);
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #000000;
      --card: #1c1c1e;
      --text: #f5f5f7;
      --text2: #8e8e93;
      --accent: #0a84ff;
      --border: #38383a;
      --shadow: 0 2px 12px rgba(0,0,0,0.3);
    }
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    background: var(--bg);
    color: var(--text);
    padding: 16px;
    max-width: 900px;
    margin: 0 auto;
  }
  .topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
  }
  h1 { font-size: 1.5rem; letter-spacing: -0.02em; }
  .topbar a {
    color: var(--text2);
    text-decoration: none;
    font-size: 0.9rem;
  }
  .controls {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
  }
  button, select {
    border: none;
    background: var(--card);
    color: var(--text);
    padding: 10px 16px;
    border-radius: var(--radius);
    font-size: 1rem;
    box-shadow: var(--shadow);
    cursor: pointer;
    border: 1px solid var(--border);
    -webkit-tap-highlight-color: transparent;
  }
  button.primary {
    background: var(--accent);
    color: #fff;
    border: none;
  }
  .sensor-drawer {
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    margin-bottom: 16px;
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.3s ease;
  }
  .sensor-drawer.open { max-height: 700px; }
  .sensor-drawer-inner { padding: 16px; }
  .sensor-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
  }
  .sensor-item:last-child { border-bottom: none; }
  .sensor-item input[type="checkbox"] {
    width: 20px; height: 20px; accent-color: var(--accent); flex-shrink: 0;
  }
  .sensor-item label { flex: 1; font-size: 0.95rem; word-break: break-word; }
  .sensor-item .unit { color: var(--text2); font-size: 0.85rem; flex-shrink: 0; margin-left: 8px; }
  .chart-wrap {
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 12px;
    position: relative;
    height: 60vh;
    min-height: 300px;
  }
  .status { font-size: 0.85rem; color: var(--text2); margin-top: 8px; }
  .spinner {
    display: inline-block;
    width: 16px; height: 16px;
    border: 2px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 8px;
    vertical-align: middle;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
  @media (min-width: 600px) {
    body { padding: 24px; }
    h1 { font-size: 1.8rem; }
    .chart-wrap { padding: 16px; }
  }
</style>
</head>
<body>

  <div class="topbar">
    <h1>Home Temperature</h1>
    <a href="settings.php">⚙ Settings</a>
  </div>

  <div class="controls">
    <button class="primary" id="toggleSensors">Sensors</button>
    <select id="days">
      <option value="1">1 day</option>
      <option value="7" <?= $config['default_days'] == 7 ? 'selected' : '' ?>>7 days</option>
      <option value="14" <?= $config['default_days'] == 14 ? 'selected' : '' ?>>14 days</option>
      <option value="30" <?= $config['default_days'] == 30 ? 'selected' : '' ?>>30 days</option>
    </select>
    <button id="updateBtn" class="primary">Update</button>
  </div>

  <div class="sensor-drawer" id="sensorDrawer">
    <div class="sensor-drawer-inner">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
        <strong>Available sensors</strong>
        <button id="loadSensors" style="padding:6px 12px; font-size:0.9rem;">Load</button>
      </div>
      <div id="sensorList"><p class="status">Tap Load to fetch sensors</p></div>
    </div>
  </div>

  <div class="chart-wrap">
    <canvas id="chart"></canvas>
  </div>
  <p class="status" id="status"></p>

<script>
const defaultSensors = <?= json_encode($config['default_sensors']) ?>;
const defaultDays = <?= intval($config['default_days']) ?>;
let chart = null;
let selectedSensors = new Set(defaultSensors);

function isDark() {
  return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

document.getElementById('toggleSensors').addEventListener('click', () => {
  document.getElementById('sensorDrawer').classList.toggle('open');
});

document.getElementById('loadSensors').addEventListener('click', async () => {
  const list = document.getElementById('sensorList');
  list.innerHTML = '<div class="spinner"></div>';
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
        <input type="checkbox" id="${s.entity_id}" value="${s.entity_id}" ${checked}>
        <label for="${s.entity_id}">${s.name}</label>
        <span class="unit">${parseFloat(s.state).toFixed(1)} ${s.unit}</span>
      `;
      list.appendChild(div);
    });
    list.querySelectorAll('input').forEach(cb => {
      cb.addEventListener('change', () => {
        if (cb.checked) selectedSensors.add(cb.value);
        else selectedSensors.delete(cb.value);
      });
    });
  } catch (e) {
    list.innerHTML = '<p class="status">Error loading sensors</p>';
  }
});

document.getElementById('updateBtn').addEventListener('click', updateChart);
document.getElementById('days').addEventListener('change', updateChart);

async function updateChart() {
  const days = document.getElementById('days').value;
  const ids = Array.from(selectedSensors).join(',');
  if (!ids) { setStatus('Select at least one sensor'); return; }
  setStatus('Loading...');
  try {
    const res = await fetch(`api.php?action=history&days=${days}&entity_ids=${encodeURIComponent(ids)}`);
    const data = await res.json();
    if (!Array.isArray(data)) throw new Error('Bad response: ' + JSON.stringify(data));
    renderChart(data, days);
    setStatus(`Showing ${days} day(s) · ${data.length} sensor(s)`);
  } catch (e) {
    setStatus('Error loading history: ' + e.message);
    console.error('updateChart error:', e);
  }
}

function setStatus(msg) {
  document.getElementById('status').textContent = msg;
}

function renderChart(haData, days) {
  const ctx = document.getElementById('chart').getContext('2d');
  const colors = ['#0071e3','#ff9500','#34c759','#ff3b30','#af52de','#5856d6'];
  const datasets = [];
  haData.forEach((sensorArr, idx) => {
    if (!sensorArr || !sensorArr.length) return;
    const label = sensorArr[0].attributes?.friendly_name || sensorArr[0].entity_id;
    const points = sensorArr
      .map(p => ({ x: new Date(p.last_changed).getTime(), y: parseFloat(parseFloat(p.state).toFixed(1)) }))
      .filter(p => !isNaN(p.y) && !isNaN(p.x));
    datasets.push({
      label,
      data: points,
      borderColor: colors[idx % colors.length],
      backgroundColor: colors[idx % colors.length] + '20',
      fill: true, tension: 0.4, pointRadius: 0, pointHitRadius: 10, borderWidth: 2,
    });
  });
  if (chart) chart.destroy();
  const dark = isDark();
  chart = new Chart(ctx, {
    type: 'line',
    data: { datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'top', labels: { color: dark ? '#f5f5f7' : '#1d1d1f', usePointStyle: true, boxWidth: 8 } },
        tooltip: {
          backgroundColor: dark ? '#1c1c1e' : '#fff',
          titleColor: dark ? '#f5f5f7' : '#1d1d1f',
          bodyColor: dark ? '#f5f5f7' : '#1d1d1f',
          borderColor: dark ? '#38383a' : '#d2d2d7',
          borderWidth: 1, padding: 10, displayColors: true,
          callbacks: {
            label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y.toFixed(1)}°C`
          }
        }
      },
      scales: {
        x: {
          type: 'time',
          time: {
            tooltipFormat: 'dd MMM HH:mm',
            displayFormats: { hour: 'HH:mm', day: 'dd MMM' }
          },
          grid: { color: dark ? '#38383a' : '#e5e5e5' },
          ticks: { color: dark ? '#8e8e93' : '#86868b', maxRotation: 0, autoSkip: true }
        },
        y: {
          grid: { color: dark ? '#38383a' : '#e5e5e5' },
          ticks: {
            color: dark ? '#8e8e93' : '#86868b',
            callback: (val) => val.toFixed(1) + '°'
          },
          title: { display: true, text: 'Temperature (°C)', color: dark ? '#8e8e93' : '#86868b' }
        }
      }
    }
  });
}

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
  if (chart) {
    const dark = isDark();
    chart.options.plugins.legend.labels.color = dark ? '#f5f5f7' : '#1d1d1f';
    chart.options.scales.x.grid.color = dark ? '#38383a' : '#e5e5e5';
    chart.options.scales.x.ticks.color = dark ? '#8e8e93' : '#86868b';
    chart.options.scales.y.grid.color = dark ? '#38383a' : '#e5e5e5';
    chart.options.scales.y.ticks.color = dark ? '#8e8e93' : '#86868b';
    chart.options.scales.y.title.color = dark ? '#8e8e93' : '#86868b';
    chart.options.plugins.tooltip.backgroundColor = dark ? '#1c1c1e' : '#fff';
    chart.options.plugins.tooltip.titleColor = dark ? '#f5f5f7' : '#1d1d1f';
    chart.options.plugins.tooltip.bodyColor = dark ? '#f5f5f7' : '#1d1d1f';
    chart.options.plugins.tooltip.borderColor = dark ? '#38383a' : '#d2d2d7';
    chart.update();
  }
});

if (defaultSensors.length) {
  updateChart();
} else {
  setStatus('Tap Sensors to choose defaults, or go to ⚙ Settings');
}
</script>
</body>
</html>
