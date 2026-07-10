<?php
if (file_exists(__DIR__ . '/config.local.php')) {
    $config = require __DIR__ . '/config.local.php';
} else {
    $config = require __DIR__ . '/config.php';
}
$latitude  = $config['latitude']  ?? 51.5074;
$longitude = $config['longitude'] ?? -0.1278;
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

  .hero { display: flex; gap: 20px; padding: 20px 20px 12px; overflow-x: auto; flex-shrink: 0; scrollbar-width: none; }
  .hero::-webkit-scrollbar { display: none; }
  .hero-card {
    flex-shrink: 0;
    background: var(--card);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 14px 18px;
    min-width: 120px;
  }
  .hero-card .label { font-size: 0.72rem; font-weight: 500; color: var(--text2); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
  .hero-card .temp { font-size: 2rem; font-weight: 700; letter-spacing: -0.03em; line-height: 1; }
  .hero-card .temp span { font-size: 1rem; font-weight: 400; color: var(--text2); }
  .hero-empty { padding: 20px 20px 12px; font-size: 0.85rem; color: var(--text2); flex-shrink: 0; }

  .chart-hero { flex: 1; padding: 0 16px; min-height: 0; }
  .chart-wrap {
    background: var(--card);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    padding: 16px 12px 12px;
    height: 100%;
  }

  .controls { display: flex; align-items: center; gap: 8px; padding: 12px 20px; flex-shrink: 0; }
  .pill {
    display: inline-flex; align-items: center;
    padding: 8px 16px; border-radius: 999px;
    font-size: 0.875rem; font-weight: 500; cursor: pointer;
    border: 1px solid var(--border);
    background: var(--card);
    backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    color: var(--text); box-shadow: var(--shadow);
    transition: transform 0.1s; -webkit-tap-highlight-color: transparent;
    white-space: nowrap; text-decoration: none;
  }
  .pill:active { transform: scale(0.96); }
  .pill.accent { background: var(--accent); color: #fff; border-color: transparent; }
  select.pill {
    appearance: none; -webkit-appearance: none; padding-right: 28px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center;
  }
  .spacer { flex: 1; }
  .status-bar { padding: 0 20px 10px; font-size: 0.78rem; color: var(--text2); min-height: 18px; }

  .drawer-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.35); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 10; }
  .drawer-backdrop.open { display: block; }
  .sensor-drawer {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--card-solid); border-radius: 24px 24px 0 0;
    box-shadow: var(--shadow-lg); z-index: 20;
    transform: translateY(100%); transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
    max-height: 70dvh; display: flex; flex-direction: column;
  }
  .sensor-drawer.open { transform: translateY(0); }
  .drawer-handle { width: 36px; height: 4px; background: var(--border); border-radius: 2px; margin: 12px auto 0; flex-shrink: 0; }
  .drawer-header { display: flex; align-items: center; padding: 14px 20px 10px; flex-shrink: 0; }
  .drawer-header strong { font-size: 1rem; flex: 1; }
  .drawer-close { background: none; border: none; color: var(--text2); font-size: 1.2rem; cursor: pointer; padding: 4px 8px; line-height: 1; }
  .sensor-list { overflow-y: auto; padding: 0 20px 40px; flex: 1; }
  .sensor-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border); }
  .sensor-item:last-child { border-bottom: none; }
  .sensor-item input[type="checkbox"] { width: 20px; height: 20px; accent-color: var(--accent); flex-shrink: 0; }
  .sensor-item label { flex: 1; font-size: 0.95rem; }
  .sensor-item .val { font-size: 0.875rem; font-weight: 600; flex-shrink: 0; }

  .spinner { display: inline-block; width: 13px; height: 13px; border: 2px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; vertical-align: middle; margin-right: 5px; }
  @keyframes spin { to { transform: rotate(360deg); } }

  @media (min-width: 600px) {
    .chart-hero { padding: 0 24px; }
    .controls, .hero { padding-left: 24px; padding-right: 24px; }
    .sensor-drawer { left: auto; right: 24px; bottom: 24px; width: 360px; border-radius: 24px; max-height: 60dvh; }
  }
</style>
</head>
<body>

  <div id="heroArea"></div>

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
    <button class="pill" id="updateBtn">&#8635;</button>
    <a class="pill" href="settings.php">&#9881;</a>
  </div>
  <p class="status-bar" id="status"></p>

  <div class="drawer-backdrop" id="drawerBackdrop"></div>
  <div class="sensor-drawer" id="sensorDrawer">
    <div class="drawer-handle"></div>
    <div class="drawer-header">
      <strong>Sensors</strong>
      <button class="drawer-close" id="drawerClose">&#215;</button>
    </div>
    <div class="sensor-list" id="sensorList">
      <span class="spinner"></span> Loading…
    </div>
  </div>

<script>
const defaultSensors = <?= json_encode($config['default_sensors']) ?>;
const defaultDays    = <?= intval($config['default_days']) ?>;
const LOCATION = { lat: <?= floatval($latitude) ?>, lon: <?= floatval($longitude) ?> };

let chart = null;
let selectedSensors = new Set(defaultSensors);
let sensorsCache = null;

document.getElementById('days').value = defaultDays;

function isDark() { return window.matchMedia('(prefers-color-scheme: dark)').matches; }

// ── Solar calculations ─────────────────────────────────────────────────────────────────
const DEG = Math.PI / 180;

function julianDay(date) {
  // date is a Date object; return fractional Julian day number
  return date.getTime() / 86400000 + 2440587.5;
}

// Return solar noon in fractional UTC hours for a given Julian day & longitude
function solarNoon(jd, lon) {
  const n   = jd - 2451545.0;
  const L   = ((280.46 + 0.9856474 * n) % 360 + 360) % 360;   // mean longitude
  const g   = ((357.528 + 0.9856003 * n) % 360 + 360) % 360;  // mean anomaly
  const lam = L + 1.915 * Math.sin(g * DEG) + 0.02 * Math.sin(2 * g * DEG);
  const eps = 23.439 - 0.0000004 * n;
  // Right ascension (degrees)
  const RA  = ((Math.atan2(Math.cos(eps * DEG) * Math.sin(lam * DEG), Math.cos(lam * DEG)) / DEG) % 360 + 360) % 360;
  // Equation of time in minutes
  const EqT = 4 * (((L - RA) % 360 + 360) % 360 <= 180
    ? (L - RA) % 360
    : (L - RA) % 360 - 360);
  // Solar noon UTC = 12h - EqT(min)/60 - lon/15
  return 12 - EqT / 60 - lon / 15;
}

// Returns { dawn, sunrise, sunset, dusk } as UTC ms timestamps for the given date
function getSunTimes(date, lat, lon) {
  function cosHourAngle(altDeg, latDeg, declDeg) {
    return (Math.sin(altDeg * DEG) - Math.sin(latDeg * DEG) * Math.sin(declDeg * DEG))
           / (Math.cos(latDeg * DEG) * Math.cos(declDeg * DEG));
  }

  const jd  = julianDay(date);
  const n   = jd - 2451545.0;
  const L   = ((280.46 + 0.9856474 * n) % 360 + 360) % 360;
  const g   = ((357.528 + 0.9856003 * n) % 360 + 360) % 360;
  const lam = L + 1.915 * Math.sin(g * DEG) + 0.02 * Math.sin(2 * g * DEG);
  const eps = 23.439 - 0.0000004 * n;
  const decl = Math.asin(Math.sin(eps * DEG) * Math.sin(lam * DEG)) / DEG;
  const noon = solarNoon(jd, lon); // fractional UTC hours

  // midnight UTC of this date in ms
  const midnight = new Date(date); midnight.setUTCHours(0, 0, 0, 0);
  const ms = midnight.getTime();
  const hToMs = (hOffset) => ms + hOffset * 3600000;

  function eventMs(altDeg, before) {
    const cosH = cosHourAngle(altDeg, lat, decl);
    if (cosH >= 1)  return null;          // polar night for this altitude
    if (cosH <= -1) return before         // midnight sun: always above horizon
      ? hToMs(noon - 12)                 //   clamp to chart boundary
      : hToMs(noon + 12);
    const H = Math.acos(cosH) / DEG;     // 0–180
    return hToMs(before ? noon - H / 15 : noon + H / 15);
  }

  return {
    dawn:    eventMs(-6,     true),
    sunrise: eventMs(-0.833, true),
    sunset:  eventMs(-0.833, false),
    dusk:    eventMs(-6,     false),
  };
}

// Build an ordered list of { from, to, type } bands covering [startMs, endMs].
// Iterates one UTC day at a time; each day contributes up to 5 segments:
//   midnight→dawn (night), dawn→sunrise (twilight), sunrise→sunset (day),
//   sunset→dusk (twilight), dusk→next-midnight (night).
// A 'gap' marker is attached so the drawer plugin can identify dawn vs dusk.
function buildSolarBands(startMs, endMs, lat, lon) {
  const bands = [];

  // Start from the UTC midnight on or before startMs
  const cursor = new Date(startMs);
  cursor.setUTCHours(0, 0, 0, 0);

  while (cursor.getTime() < endMs) {
    const dayStart  = cursor.getTime();
    const dayEnd    = dayStart + 86400000; // next UTC midnight
    const { dawn, sunrise, sunset, dusk } = getSunTimes(cursor, lat, lon);

    // Clamp each event to within this UTC day to avoid cross-day bleed
    const clamp = (t) => t === null ? null : Math.min(Math.max(t, dayStart), dayEnd);
    const d  = clamp(dawn);
    const sr = clamp(sunrise);
    const ss = clamp(sunset);
    const dk = clamp(dusk);

    if (d !== null && sr !== null && ss !== null && dk !== null) {
      // Normal day with distinct twilight periods
      bands.push({ from: dayStart, to: d,       type: 'night'    });
      bands.push({ from: d,        to: sr,      type: 'twilight', phase: 'dawn' });
      bands.push({ from: sr,       to: ss,      type: 'day'      });
      bands.push({ from: ss,       to: dk,      type: 'twilight', phase: 'dusk' });
      bands.push({ from: dk,       to: dayEnd,  type: 'night'    });
    } else if (sr !== null && ss !== null) {
      // Twilight indistinguishable from night (high summer/winter)
      bands.push({ from: dayStart, to: sr,      type: 'night'    });
      bands.push({ from: sr,       to: ss,      type: 'day'      });
      bands.push({ from: ss,       to: dayEnd,  type: 'night'    });
    } else {
      // Polar night or midnight sun — fill entire day
      const type = (dawn === null && dusk === null) ? 'night' : 'day';
      bands.push({ from: dayStart, to: dayEnd,  type });
    }

    cursor.setUTCDate(cursor.getUTCDate() + 1);
  }

  return bands;
}

// ── Temperature colour scale ──────────────────────────────────────────────────────
const TEMP_SCALE = [
  { t: 10, r: 96,  g: 165, b: 250 },
  { t: 18, r: 52,  g: 211, b: 153 },
  { t: 22, r: 251, g: 191, b: 36  },
  { t: 26, r: 251, g: 146, b: 60  },
  { t: 32, r: 239, g: 68,  b: 68  },
];
function tempToRgb(t) {
  if (t <= TEMP_SCALE[0].t) return TEMP_SCALE[0];
  if (t >= TEMP_SCALE[TEMP_SCALE.length-1].t) return TEMP_SCALE[TEMP_SCALE.length-1];
  for (let i = 0; i < TEMP_SCALE.length-1; i++) {
    const a = TEMP_SCALE[i], b = TEMP_SCALE[i+1];
    if (t >= a.t && t <= b.t) {
      const f = (t-a.t)/(b.t-a.t);
      return { r: Math.round(a.r+f*(b.r-a.r)), g: Math.round(a.g+f*(b.g-a.g)), b: Math.round(a.b+f*(b.b-a.b)) };
    }
  }
}
function tempToColor(t, alpha=1) { const {r,g,b}=tempToRgb(t); return `rgba(${r},${g},${b},${alpha})`; }

// ── Prefs ─────────────────────────────────────────────────────────────────────
async function savePrefs() {
  try {
    await fetch('api.php?action=save_prefs', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ default_sensors: Array.from(selectedSensors), default_days: parseInt(document.getElementById('days').value) })
    });
  } catch(e) { console.warn('Could not save prefs:', e); }
}

// ── Hero ──────────────────────────────────────────────────────────────────────
function renderHero(sensorsData) {
  const hero = document.getElementById('heroArea');
  if (!sensorsData||!sensorsData.length) { hero.innerHTML='<div class="hero-empty">Tap <strong>Sensors</strong> to get started.</div>'; return; }
  const selected = sensorsData.filter(s => selectedSensors.has(s.entity_id));
  if (!selected.length) { hero.innerHTML=''; return; }
  hero.innerHTML = '<div class="hero">' + selected.map(s => {
    const val = parseFloat(s.state);
    const col = isNaN(val) ? 'var(--text2)' : tempToColor(val);
    return `<div class="hero-card"><div class="label">${s.name}</div><div class="temp" style="color:${col}">${isNaN(val)?'--':val.toFixed(1)}<span>\u00b0C</span></div></div>`;
  }).join('') + '</div>';
}

// ── Sensor drawer ─────────────────────────────────────────────────────────────
async function loadSensors() {
  if (sensorsCache) return sensorsCache;
  const res = await fetch('api.php?action=sensors');
  const sensors = await res.json();
  if (!Array.isArray(sensors)) throw new Error('Bad response');
  sensorsCache = sensors;
  return sensors;
}
async function populateDrawer() {
  const list = document.getElementById('sensorList');
  list.innerHTML = '<span class="spinner"></span> Loading…';
  try {
    const sensors = await loadSensors();
    renderHero(sensors);
    list.innerHTML = '';
    sensors.forEach(s => {
      const div = document.createElement('div'); div.className='sensor-item';
      const checked = selectedSensors.has(s.entity_id) ? 'checked' : '';
      const val = parseFloat(s.state);
      const col = isNaN(val) ? 'var(--text2)' : tempToColor(val);
      div.innerHTML=`<input type="checkbox" id="cb_${s.entity_id}" value="${s.entity_id}" ${checked}><label for="cb_${s.entity_id}">${s.name}</label><span class="val" style="color:${col}">${isNaN(val)?'--':val.toFixed(1)}\u00b0C</span>`;
      list.appendChild(div);
    });
    list.querySelectorAll('input').forEach(cb => {
      cb.addEventListener('change', () => {
        if (cb.checked) selectedSensors.add(cb.value); else selectedSensors.delete(cb.value);
        renderHero(sensorsCache); savePrefs(); updateChart();
      });
    });
  } catch(e) { list.innerHTML='<p style="color:var(--text2);padding:8px 0">Error loading sensors</p>'; }
}
function openDrawer()  { document.getElementById('sensorDrawer').classList.add('open');    document.getElementById('drawerBackdrop').classList.add('open');    populateDrawer(); }
function closeDrawer() { document.getElementById('sensorDrawer').classList.remove('open'); document.getElementById('drawerBackdrop').classList.remove('open'); }
document.getElementById('toggleSensors').addEventListener('click', openDrawer);
document.getElementById('drawerBackdrop').addEventListener('click', closeDrawer);
document.getElementById('drawerClose').addEventListener('click', closeDrawer);
document.getElementById('updateBtn').addEventListener('click', () => { savePrefs(); updateChart(); });
document.getElementById('days').addEventListener('change', () => { savePrefs(); updateChart(); });

// ── Chart update ──────────────────────────────────────────────────────────────
async function updateChart() {
  const days = document.getElementById('days').value;
  const ids  = Array.from(selectedSensors).join(',');
  if (!ids) { setStatus('Select at least one sensor'); return; }
  setStatus('<span class="spinner"></span> Loading…');
  try {
    const res  = await fetch(`api.php?action=history&days=${days}&entity_ids=${encodeURIComponent(ids)}`);
    const data = await res.json();
    if (!Array.isArray(data)) throw new Error(JSON.stringify(data));
    renderChart(data);
    setStatus(`${days} day(s) \u00b7 updated ${luxon.DateTime.now().toFormat('HH:mm')}`);
  } catch(e) { setStatus('Error: '+e.message); console.error(e); }
}
function setStatus(html) { document.getElementById('status').innerHTML = html; }

// ── Day/Night background plugin ──────────────────────────────────────────────────────
const dayNightPlugin = {
  id: 'dayNight',
  beforeDraw(chartInstance) {
    const { ctx, chartArea: ca, scales } = chartInstance;
    if (!ca) return;
    const xScale = scales.x;
    const xMin = xScale.min, xMax = xScale.max;
    const dark = isDark();

    // Colours — night is distinctly visible in both modes
    const NIGHT_LIGHT    = 'rgba(30, 41, 80, 0.09)';   // blue-tinted wash
    const NIGHT_DARK     = 'rgba(10, 10, 40, 0.55)';   // deep indigo
    const TWIL_NIGHT_L   = 'rgba(30, 41, 80, 0.09)';
    const TWIL_NIGHT_D   = 'rgba(10, 10, 40, 0.55)';
    const TWIL_GLOW_L    = 'rgba(251, 191, 36, 0.13)'; // warm amber wash
    const TWIL_GLOW_D    = 'rgba(251, 146, 60, 0.22)'; // stronger in dark

    const bands = buildSolarBands(xMin, xMax, LOCATION.lat, LOCATION.lon);

    ctx.save();
    ctx.beginPath();
    ctx.rect(ca.left, ca.top, ca.width, ca.height);
    ctx.clip();

    bands.forEach(band => {
      const from = Math.max(band.from, xMin);
      const to   = Math.min(band.to,   xMax);
      if (to <= from) return;
      const x0 = xScale.getPixelForValue(from);
      const x1 = xScale.getPixelForValue(to);
      const w  = x1 - x0;

      if (band.type === 'night') {
        ctx.fillStyle = dark ? NIGHT_DARK : NIGHT_LIGHT;
        ctx.fillRect(x0, ca.top, w, ca.height);

      } else if (band.type === 'twilight') {
        const isDawn = band.phase === 'dawn';
        const grad   = ctx.createLinearGradient(x0, 0, x1, 0);
        // Dawn: night-colour → day-glow (left to right)
        // Dusk: day-glow → night-colour (left to right)
        const nightCol = dark ? TWIL_NIGHT_D : TWIL_NIGHT_L;
        const glowCol  = dark ? TWIL_GLOW_D  : TWIL_GLOW_L;
        grad.addColorStop(0, isDawn ? nightCol : glowCol);
        grad.addColorStop(1, isDawn ? glowCol  : nightCol);
        ctx.fillStyle = grad;
        ctx.fillRect(x0, ca.top, w, ca.height);
      }
      // 'day' — transparent, no fill needed
    });

    // Sunrise / sunset dashed marker lines
    bands.forEach(band => {
      if (band.type !== 'day') return;
      // The day band's edges are sunrise (from) and sunset (to)
      [band.from, band.to].forEach(ts => {
        if (ts <= xMin || ts >= xMax) return;
        const x = xScale.getPixelForValue(ts);
        ctx.beginPath();
        ctx.moveTo(x, ca.top);
        ctx.lineTo(x, ca.bottom);
        ctx.strokeStyle = dark ? 'rgba(251,146,60,0.45)' : 'rgba(251,146,60,0.5)';
        ctx.lineWidth = 1;
        ctx.setLineDash([3, 5]);
        ctx.stroke();
        ctx.setLineDash([]);
      });
    });

    ctx.restore();
  }
};

// ── Temperature gradient line plugin ─────────────────────────────────────────────────
const tempGradientPlugin = {
  id: 'tempGradient',
  beforeDatasetsDraw(chartInstance) {
    const ctx = chartInstance.ctx;
    chartInstance.data.datasets.forEach((ds, dsIdx) => {
      const meta = chartInstance.getDatasetMeta(dsIdx);
      if (!meta.visible || !meta.data.length) return;
      const points  = meta.data;
      const rawData = ds.data;
      if (points.length < 2) return;

      ctx.save();
      const { left, right, top, bottom } = chartInstance.chartArea;
      ctx.beginPath(); ctx.rect(left, top, right-left, bottom-top); ctx.clip();

      function drawPass(width, alpha) {
        ctx.globalAlpha = alpha;
        ctx.lineWidth   = width;
        ctx.lineJoin    = 'round';
        ctx.lineCap     = 'round';
        for (let i = 0; i < points.length-1; i++) {
          const p0=points[i], p1=points[i+1];
          if (p0.skip||p1.skip) continue;
          const t0=rawData[i]?.y??20, t1=rawData[i+1]?.y??20;
          const grad=ctx.createLinearGradient(p0.x,p0.y,p1.x,p1.y);
          grad.addColorStop(0,tempToColor(t0)); grad.addColorStop(1,tempToColor(t1));
          ctx.beginPath(); ctx.moveTo(p0.x,p0.y);
          if (p0.cp2x!==undefined) ctx.bezierCurveTo(p0.cp2x,p0.cp2y,p1.cp1x,p1.cp1y,p1.x,p1.y);
          else ctx.lineTo(p1.x,p1.y);
          ctx.strokeStyle=grad; ctx.stroke();
        }
      }

      drawPass(12, 0.15); // glow
      drawPass(3,  1.0);  // main line

      ctx.globalAlpha = 1;
      ctx.restore();
    });
    chartInstance.data.datasets.forEach((ds,dsIdx) => {
      const opts = chartInstance.getDatasetMeta(dsIdx).dataset.options;
      if (opts) { opts.borderColor='transparent'; opts.backgroundColor='transparent'; }
    });
  }
};

Chart.register(dayNightPlugin);
Chart.register(tempGradientPlugin);

// ── Render chart ──────────────────────────────────────────────────────────────
function renderChart(haData) {
  const ctx = document.getElementById('chart').getContext('2d');
  const datasets = [];
  haData.forEach(sensorArr => {
    if (!sensorArr||!sensorArr.length) return;
    const label  = sensorArr[0].attributes?.friendly_name || sensorArr[0].entity_id;
    const points = sensorArr
      .map(p => { const ts=luxon.DateTime.fromISO(p.last_changed).toMillis(); const val=parseFloat(p.state); return {x:ts,y:isNaN(val)?null:parseFloat(val.toFixed(1))}; })
      .filter(p => p.y!==null && !isNaN(p.x));
    datasets.push({ label, data:points, borderColor:'transparent', backgroundColor:'transparent', fill:false, tension:0.5, pointRadius:0, pointHitRadius:14, borderWidth:0, spanGaps:false });
  });

  if (chart) chart.destroy();
  const dark    = isDark();
  const gridCol = dark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
  const tickCol = dark ? '#6b7280' : '#9ca3af';

  chart = new Chart(ctx, {
    type: 'line',
    data: { datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration:600, easing:'easeInOutQuart' },
      interaction: { mode:'index', intersect:false },
      plugins: {
        legend: { display:false },
        tooltip: {
          backgroundColor: dark?'rgba(15,15,19,0.96)':'rgba(255,255,255,0.96)',
          titleColor: dark?'#f1f1f5':'#1a1a2e',
          bodyColor:  dark?'#d1d5db':'#4b5563',
          borderColor:dark?'rgba(255,255,255,0.1)':'rgba(0,0,0,0.08)',
          borderWidth:1, padding:12, displayColors:true, cornerRadius:10,
          callbacks: {
            labelColor: ctx => { const c=tempToColor(ctx.parsed.y); return {borderColor:c,backgroundColor:c,borderRadius:3}; },
            label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y.toFixed(1)}\u00b0C`
          }
        }
      },
      scales: {
        x: { type:'time', time:{tooltipFormat:'dd MMM HH:mm',displayFormats:{hour:'HH:mm',day:'dd MMM'}}, grid:{color:gridCol}, border:{display:false}, ticks:{color:tickCol,maxRotation:0,autoSkip:true,font:{size:11}} },
        y: { grid:{color:gridCol}, border:{display:false}, ticks:{color:tickCol,font:{size:11},callback:v=>v.toFixed(1)+'\u00b0'} }
      }
    }
  });
}

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (chart) updateChart(); });

(async () => {
  if (defaultSensors.length) {
    updateChart();
    try { const sensors = await loadSensors(); renderHero(sensors); } catch(e) {}
  } else {
    document.getElementById('heroArea').innerHTML = '<div class="hero-empty">Tap <strong>Sensors</strong> to get started.</div>';
  }
})();
</script>
</body>
</html>
