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
    background: var(--bg); color: var(--text);
    display: flex; flex-direction: column;
    min-height: 100dvh; overflow-x: hidden;
  }
  .hero { display:flex; gap:20px; padding:20px 20px 12px; overflow-x:auto; flex-shrink:0; scrollbar-width:none; }
  .hero::-webkit-scrollbar { display:none; }
  .hero-card { flex-shrink:0; background:var(--card); backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); padding:14px 18px; min-width:120px; }
  .hero-card .label { font-size:0.72rem; font-weight:500; color:var(--text2); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:140px; }
  .hero-card .temp { font-size:2rem; font-weight:700; letter-spacing:-0.03em; line-height:1; }
  .hero-card .temp span { font-size:1rem; font-weight:400; color:var(--text2); }
  .hero-empty { padding:20px 20px 12px; font-size:0.85rem; color:var(--text2); flex-shrink:0; }
  .chart-hero { flex:1; padding:0 16px; min-height:0; }
  .chart-wrap { background:var(--card); backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow-lg); padding:16px 12px 12px; height:100%; }
  .controls { display:flex; align-items:center; gap:8px; padding:12px 20px; flex-shrink:0; }
  .pill { display:inline-flex; align-items:center; padding:8px 16px; border-radius:999px; font-size:0.875rem; font-weight:500; cursor:pointer; border:1px solid var(--border); background:var(--card); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); color:var(--text); box-shadow:var(--shadow); transition:transform 0.1s; -webkit-tap-highlight-color:transparent; white-space:nowrap; text-decoration:none; }
  .pill:active { transform:scale(0.96); }
  .pill.accent { background:var(--accent); color:#fff; border-color:transparent; }
  select.pill { appearance:none; -webkit-appearance:none; padding-right:28px; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; }
  .spacer { flex:1; }
  .status-bar { padding:0 20px 10px; font-size:0.78rem; color:var(--text2); min-height:18px; }
  .drawer-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); z-index:10; }
  .drawer-backdrop.open { display:block; }
  .sensor-drawer { position:fixed; bottom:0; left:0; right:0; background:var(--card-solid); border-radius:24px 24px 0 0; box-shadow:var(--shadow-lg); z-index:20; transform:translateY(100%); transition:transform 0.3s cubic-bezier(0.4,0,0.2,1); max-height:70dvh; display:flex; flex-direction:column; }
  .sensor-drawer.open { transform:translateY(0); }
  .drawer-handle { width:36px; height:4px; background:var(--border); border-radius:2px; margin:12px auto 0; flex-shrink:0; }
  .drawer-header { display:flex; align-items:center; padding:14px 20px 10px; flex-shrink:0; }
  .drawer-header strong { font-size:1rem; flex:1; }
  .drawer-close { background:none; border:none; color:var(--text2); font-size:1.2rem; cursor:pointer; padding:4px 8px; line-height:1; }
  .sensor-list { overflow-y:auto; padding:0 20px 40px; flex:1; }
  .sensor-group-label { font-size:0.72rem; font-weight:700; color:var(--text2); text-transform:uppercase; letter-spacing:0.06em; padding:12px 0 4px; }
  .sensor-item { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); }
  .sensor-item:last-child { border-bottom:none; }
  .sensor-item input[type="checkbox"] { width:20px; height:20px; accent-color:var(--accent); flex-shrink:0; }
  .sensor-item label { flex:1; font-size:0.95rem; }
  .sensor-item .val { font-size:0.875rem; font-weight:600; flex-shrink:0; }
  .hide-btn { background:none; border:1px solid var(--border); border-radius:6px; padding:2px 8px; font-size:0.75rem; color:var(--text2); cursor:pointer; flex-shrink:0; }
  .hide-btn:hover { color:var(--text); border-color:var(--text2); }
  .drawer-footer { padding:0.75rem 0 0; border-top:1px solid var(--border); margin-top:0.5rem; font-size:0.85rem; }
  .drawer-footer a { color:var(--accent); text-decoration:none; }
  .drawer-empty { font-size:0.9rem; color:var(--text2); padding:12px 0; }
  .drawer-empty a { color:var(--accent); }
  .spinner { display:inline-block; width:13px; height:13px; border:2px solid var(--border); border-top-color:var(--accent); border-radius:50%; animation:spin 0.8s linear infinite; vertical-align:middle; margin-right:5px; }
  @keyframes spin { to { transform:rotate(360deg); } }
  @media (min-width:600px) {
    .chart-hero { padding:0 24px; }
    .controls, .hero { padding-left:24px; padding-right:24px; }
    .sensor-drawer { left:auto; right:24px; bottom:24px; width:360px; border-radius:24px; max-height:60dvh; }
  }
</style>
</head>
<body>
  <div id="heroArea"></div>
  <div class="chart-hero">
    <div class="chart-wrap"><canvas id="chart"></canvas></div>
  </div>
  <div class="controls">
    <button class="pill accent" id="toggleSensors">Sensors</button>
    <select class="pill" id="days">
      <option value="1">1 day</option>
      <option value="7"   <?= $config['default_days'] ==  7 ? 'selected' : '' ?>>7 days</option>
      <option value="14"  <?= $config['default_days'] == 14 ? 'selected' : '' ?>>14 days</option>
      <option value="30"  <?= $config['default_days'] == 30 ? 'selected' : '' ?>>30 days</option>
      <option value="90"  <?= $config['default_days'] == 90 ? 'selected' : '' ?>>3 months &#x2605;</option>
      <option value="365" <?= $config['default_days'] == 365 ? 'selected' : '' ?>>1 year &#x2605;</option>
    </select>
    <div class="spacer"></div>
    <button class="pill" id="updateBtn">&#8635;</button>
    <a class="pill" href="settings.php">&#9881;</a>
  </div>
  <p class="status-bar" id="status"></p>
  <div class="drawer-backdrop" id="drawerBackdrop"></div>
  <div class="sensor-drawer" id="sensorDrawer">
    <div class="drawer-handle"></div>
    <div class="drawer-header"><strong>Sensors</strong><button class="drawer-close" id="drawerClose">&#215;</button></div>
    <div class="sensor-list" id="sensorList"><span class="spinner"></span> Loading…</div>
  </div>

<script>
const defaultSensors = <?= json_encode($config['default_sensors']) ?>;
const defaultDays    = <?= intval($config['default_days']) ?>;
const LOCATION       = { lat: <?= floatval($latitude) ?>, lon: <?= floatval($longitude) ?> };
const LTS_THRESHOLD  = 30;

let chart = null;
let selectedSensors = new Set(defaultSensors);
let sensorsCache    = null;

document.getElementById('days').value = defaultDays;
function isDark() { return window.matchMedia('(prefers-color-scheme:dark)').matches; }

// ── Solar math ───────────────────────────────────────────────────────────────────
const R = Math.PI / 180;
function getSunTimes(utcMidnightMs, lat, lon) {
  const jd  = utcMidnightMs / 86400000 + 2440587.5;
  const n   = jd - 2451545.0;
  const L   = ((280.46 + 0.9856474 * n) % 360 + 360) % 360;
  const g   = ((357.528 + 0.9856003 * n) % 360 + 360) % 360;
  const lam = L + 1.915 * Math.sin(g * R) + 0.02 * Math.sin(2 * g * R);
  const eps = 23.439 - 0.0000004 * n;
  const decl = Math.asin(Math.sin(eps * R) * Math.sin(lam * R)) / R;
  const RA   = Math.atan2(Math.cos(eps * R) * Math.sin(lam * R), Math.cos(lam * R)) / R;
  const diff = ((L - RA) % 360 + 360) % 360;
  const EqT  = 4 * (diff <= 180 ? diff : diff - 360);
  const noon = 12 - EqT / 60 - lon / 15;
  const hMs  = (h) => utcMidnightMs + h * 3600000;
  function event(altDeg, rising) {
    const cosH = (Math.sin(altDeg*R) - Math.sin(lat*R)*Math.sin(decl*R)) / (Math.cos(lat*R)*Math.cos(decl*R));
    if (cosH >= 1)  return null;
    if (cosH <= -1) return hMs(rising ? noon-12 : noon+12);
    const H = Math.acos(cosH) / R;
    return hMs(rising ? noon - H/15 : noon + H/15);
  }
  return { dawn: event(-6,true), sunrise: event(-0.833,true), sunset: event(-0.833,false), dusk: event(-6,false) };
}
function buildSolarBands(startMs, endMs, lat, lon) {
  const bands = [];
  let cursor = startMs - (startMs % 86400000);
  while (cursor < endMs) {
    const dayStart = cursor, dayEnd = cursor + 86400000;
    const { dawn, sunrise, sunset, dusk } = getSunTimes(dayStart, lat, lon);
    const c = (t) => t === null ? null : Math.max(dayStart, Math.min(dayEnd, t));
    const D=c(dawn), Sr=c(sunrise), Ss=c(sunset), Dk=c(dusk);
    if (Sr !== null && Ss !== null) {
      const hasTwi = D!==null && Dk!==null && D<Sr && Ss<Dk;
      if (hasTwi) {
        if (dayStart<D)  bands.push({from:dayStart,to:D,  type:'night'});
                         bands.push({from:D,  to:Sr, type:'twilight',phase:'dawn'});
                         bands.push({from:Sr, to:Ss, type:'day'});
                         bands.push({from:Ss, to:Dk, type:'twilight',phase:'dusk'});
        if (Dk<dayEnd)   bands.push({from:Dk, to:dayEnd,type:'night'});
      } else {
        if (dayStart<Sr) bands.push({from:dayStart,to:Sr,type:'night'});
                         bands.push({from:Sr,to:Ss,type:'day'});
        if (Ss<dayEnd)   bands.push({from:Ss,to:dayEnd,type:'night'});
      }
    } else {
      bands.push({from:dayStart,to:dayEnd,type:(dawn===null&&dusk===null&&sunrise===null)?'night':'day'});
    }
    cursor = dayEnd;
  }
  return bands;
}

// ── Temperature colour ─────────────────────────────────────────────────────────────
const TEMP_SCALE=[
  {t:10,r:96,g:165,b:250},{t:18,r:52,g:211,b:153},
  {t:22,r:251,g:191,b:36},{t:26,r:251,g:146,b:60},{t:32,r:239,g:68,b:68}
];
function tempToRgb(t){
  if(t<=TEMP_SCALE[0].t)return TEMP_SCALE[0];
  if(t>=TEMP_SCALE[TEMP_SCALE.length-1].t)return TEMP_SCALE[TEMP_SCALE.length-1];
  for(let i=0;i<TEMP_SCALE.length-1;i++){
    const a=TEMP_SCALE[i],b=TEMP_SCALE[i+1];
    if(t>=a.t&&t<=b.t){const f=(t-a.t)/(b.t-a.t);return{r:Math.round(a.r+f*(b.r-a.r)),g:Math.round(a.g+f*(b.g-a.g)),b:Math.round(a.b+f*(b.b-a.b))};}
  }
}
function tempToColor(t,alpha=1){const{r,g,b}=tempToRgb(t);return`rgba(${r},${g},${b},${alpha})`;}

// ── Sensor type config ─────────────────────────────────────────────────────────
const TYPE_CONFIG = {
  temperature: { axis: 'y',  unit: '\u00b0C', label: 'Temperature', color: null },
  co2:         { axis: 'y2', unit: 'ppm',     label: 'CO\u2082',    color: 'rgba(251,146,60,{a})' },
  humidity:    { axis: 'y3', unit: '%',        label: 'Humidity',    color: 'rgba(59,130,246,{a})' },
  aqi:         { axis: 'y3', unit: 'AQI',      label: 'AQI',         color: 'rgba(168,85,247,{a})' },
};
function typeColor(type, val, alpha=1) {
  if (type === 'temperature') return tempToColor(val, alpha);
  const tmpl = TYPE_CONFIG[type]?.color || 'rgba(156,163,175,{a})';
  return tmpl.replace('{a}', alpha);
}

// ── Prefs / Hero / Drawer ───────────────────────────────────────────────────────────
async function savePrefs(extra={}){
  try{
    await fetch('api.php?action=save_prefs',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({default_sensors:Array.from(selectedSensors),default_days:parseInt(document.getElementById('days').value),...extra})});
  }catch(e){console.warn('prefs:',e);}
}

function renderHero(sensorsData){
  const hero=document.getElementById('heroArea');
  if(!sensorsData||!sensorsData.length){hero.innerHTML='<div class="hero-empty">Tap <strong>Sensors</strong> to get started.</div>';return;}
  const sel=sensorsData.filter(s=>selectedSensors.has(s.entity_id)&&!s.hidden);
  if(!sel.length){hero.innerHTML='';return;}
  hero.innerHTML='<div class="hero">'+sel.map(s=>{
    const v=parseFloat(s.state);
    const col=isNaN(v)?'var(--text2)':typeColor(s.type||'temperature',v);
    const unit=s.unit||(TYPE_CONFIG[s.type||'temperature']?.unit??'\u00b0C');
    return`<div class="hero-card"><div class="label">${s.name}</div><div class="temp" style="color:${col}">${isNaN(v)?'--':v.toFixed(1)}<span>${unit}</span></div></div>`;
  }).join('')+'</div>';
}

async function loadSensors(){
  if(sensorsCache)return sensorsCache;
  const r=await fetch('api.php?action=sensors');const s=await r.json();
  if(!Array.isArray(s))throw new Error('bad');
  sensorsCache=s;return s;
}

async function populateDrawer(){
  const list=document.getElementById('sensorList');
  list.innerHTML='<span class="spinner"></span> Loading\u2026';
  try{
    const sensors=await loadSensors();renderHero(sensors);

    const visibleSensors = sensors.filter(s => !s.hidden);

    if (!visibleSensors.length) {
      list.innerHTML = `<p class="drawer-empty">All sensors are hidden.<br><a href="settings.php#sensors">Manage hidden sensors \u2192</a></p>
        <div class="drawer-footer"><a href="settings.php#sensors">Manage hidden sensors \u2192</a></div>`;
      return;
    }

    list.innerHTML='';
    const TYPE_ORDER  = ['temperature','co2','humidity','aqi'];
    const TYPE_LABELS = {temperature:'\ud83c\udf21\ufe0f Temperature',co2:'\ud83d\udca8 CO\u2082',humidity:'\ud83d\udca7 Humidity',aqi:'\ud83c\udf2b\ufe0f AQI'};
    TYPE_ORDER.forEach(type=>{
      const group=visibleSensors.filter(s=>s.type===type);
      if(!group.length)return;
      const hdr=document.createElement('p');
      hdr.className='sensor-group-label';
      hdr.textContent=TYPE_LABELS[type]||type;
      list.appendChild(hdr);
      group.forEach(s=>{
        const div=document.createElement('div');div.className='sensor-item';
        const v=parseFloat(s.state);
        const col=isNaN(v)?'var(--text2)':typeColor(s.type,v);
        const unit=s.unit||(TYPE_CONFIG[s.type]?.unit??'');
        div.innerHTML=`
          <input type="checkbox" id="cb_${s.entity_id}" value="${s.entity_id}" ${selectedSensors.has(s.entity_id)?'checked':''}>
          <label for="cb_${s.entity_id}">${s.name}</label>
          <button class="hide-btn" data-id="${s.entity_id}">Hide</button>
          <span class="val" style="color:${col}">${isNaN(v)?'--':v.toFixed(1)}${unit}</span>`;
        list.appendChild(div);
      });
    });

    const footer = document.createElement('div');
    footer.className = 'drawer-footer';
    footer.innerHTML = '<a href="settings.php#sensors">Manage hidden sensors \u2192</a>';
    list.appendChild(footer);

    list.querySelectorAll('input[type="checkbox"]').forEach(cb=>cb.addEventListener('change',()=>{
      cb.checked?selectedSensors.add(cb.value):selectedSensors.delete(cb.value);
      renderHero(sensorsCache);savePrefs();updateChart();
    }));

    list.querySelectorAll('.hide-btn').forEach(btn=>btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      const s = sensorsCache.find(x => x.entity_id === id);
      if (!s) return;
      s.hidden = true;
      selectedSensors.delete(id);
      const hidden = sensorsCache.filter(x => x.hidden).map(x => x.entity_id);
      await savePrefs({ hidden_sensors: hidden });
      renderHero(sensorsCache);
      updateChart();
      populateDrawer();
    }));
  }catch(e){list.innerHTML='<p style="color:var(--text2);padding:8px 0">Error loading sensors</p>';}
}

function openDrawer(){document.getElementById('sensorDrawer').classList.add('open');document.getElementById('drawerBackdrop').classList.add('open');populateDrawer();}
function closeDrawer(){document.getElementById('sensorDrawer').classList.remove('open');document.getElementById('drawerBackdrop').classList.remove('open');}
document.getElementById('toggleSensors').addEventListener('click',openDrawer);
document.getElementById('drawerBackdrop').addEventListener('click',closeDrawer);
document.getElementById('drawerClose').addEventListener('click',closeDrawer);
document.getElementById('updateBtn').addEventListener('click',()=>{savePrefs();updateChart();});
document.getElementById('days').addEventListener('change',()=>{savePrefs();updateChart();});

// ── Chart update ───────────────────────────────────────────────────────────────
async function updateChart(){
  const days=parseInt(document.getElementById('days').value);
  const activeSensors = sensorsCache
    ? Array.from(selectedSensors).filter(id=>{
        const s=sensorsCache.find(x=>x.entity_id===id);
        return s && !s.hidden;
      })
    : Array.from(selectedSensors);
  const ids=activeSensors.join(',');
  if(!ids){setStatus('Select at least one sensor');return;}

  const useLTS = days > LTS_THRESHOLD;
  const endpoint = useLTS
    ? `api.php?action=lts&days=${days}&entity_ids=${encodeURIComponent(ids)}`
    : `api.php?action=history&days=${days}&entity_ids=${encodeURIComponent(ids)}`;

  setStatus('<span class="spinner"></span> Loading\u2026');
  try{
    const res = await fetch(endpoint);
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch(parseErr) {
      console.error('JSON parse failed. HTTP', res.status, '\nResponse length:', text.length, '\nFirst 500 chars:', text.slice(0, 500));
      setStatus(`Error: Response malformed (HTTP ${res.status}, ${text.length} bytes) — check console`);
      return;
    }
    if (!res.ok || !Array.isArray(data)) {
      const msg = data?.error || data?.message || JSON.stringify(data).slice(0, 120);
      const detail = data?.preview ? ` · preview: ${data.preview.slice(0,80)}` : '';
      const size   = data?.length  ? ` · ${data.length} bytes` : '';
      console.error('API error:', data);
      setStatus(`Error (HTTP ${res.status}): ${msg}${size}${detail}`);
      return;
    }
    const label = days >= 365 ? '1 year' : days >= 90 ? '3 months' : `${days} day${days>1?'s':''}`;
    const mode  = useLTS ? ' \u00b7 hourly averages' : '';
    renderChart(data);
    setStatus(`${label}${mode} \u00b7 updated ${luxon.DateTime.now().toFormat('HH:mm')}`);
  }catch(e){
    console.error('updateChart exception:', e);
    setStatus('Error: ' + e.message);
  }
}
function setStatus(html){document.getElementById('status').innerHTML=html;}

// ── Day/Night plugin ───────────────────────────────────────────────────────────────
let solarBands=[];
const dayNightPlugin={
  id:'dayNight',
  beforeDraw(ci){
    const{ctx,chartArea:ca,scales}=ci;
    if(!ca||!solarBands.length)return;
    const xs=scales.x,dark=isDark();
    const NIGHT   = dark?'rgba(20,20,80,0.55)' :'rgba(30,41,120,0.08)';
    const TWIL    = dark?'rgba(251,146,60,0.18)':'rgba(251,191,36,0.12)';
    const SR_LINE = dark?'rgba(251,146,60,0.55)':'rgba(251,146,60,0.55)';
    ctx.save();
    ctx.beginPath();ctx.rect(ca.left,ca.top,ca.width,ca.height);ctx.clip();
    solarBands.forEach(band=>{
      const fromX=xs.getPixelForValue(band.from),toX=xs.getPixelForValue(band.to);
      if(toX<=ca.left||fromX>=ca.right)return;
      const x0=Math.max(fromX,ca.left),x1=Math.min(toX,ca.right),w=x1-x0;
      if(w<=0)return;
      if(band.type==='night'){
        ctx.fillStyle=NIGHT;ctx.fillRect(x0,ca.top,w,ca.height);
      }else if(band.type==='twilight'){
        const grad=ctx.createLinearGradient(x0,0,x1,0);
        const isDawn=band.phase==='dawn';
        grad.addColorStop(0,isDawn?NIGHT:TWIL);grad.addColorStop(1,isDawn?TWIL:NIGHT);
        ctx.fillStyle=grad;ctx.fillRect(x0,ca.top,w,ca.height);
      }
    });
    solarBands.forEach(band=>{
      if(band.type!=='day')return;
      [band.from,band.to].forEach(ts=>{
        const x=xs.getPixelForValue(ts);
        if(x<ca.left||x>ca.right)return;
        ctx.beginPath();ctx.moveTo(x,ca.top);ctx.lineTo(x,ca.bottom);
        ctx.strokeStyle=SR_LINE;ctx.lineWidth=1;ctx.setLineDash([3,5]);ctx.stroke();ctx.setLineDash([]);
      });
    });
    ctx.restore();
  }
};

// ── Smooth glow line plugin (all sensor types) ────────────────────────────────────
const smoothGlowPlugin={
  id:'smoothGlow',
  beforeDatasetsDraw(ci){
    const ctx=ci.ctx;
    ci.data.datasets.forEach((ds,di)=>{
      const meta=ci.getDatasetMeta(di);
      if(!meta.visible||!meta.data.length)return;
      const pts=meta.data,raw=ds.data;
      if(pts.length<2)return;
      const stype=ds._stype||'temperature';

      ctx.save();
      const{left,right,top,bottom}=ci.chartArea;
      ctx.beginPath();ctx.rect(left,top,right-left,bottom-top);ctx.clip();

      const sampleVal=raw.find(p=>p&&p.y!=null)?.y??20;
      const solidColor=typeColor(stype,sampleVal,1);
      const glowColor =typeColor(stype,sampleVal,0.15);

      function drawPass(lw,color){
        ctx.lineWidth=lw;ctx.lineJoin='round';ctx.lineCap='round';ctx.strokeStyle=color;
        for(let i=0;i<pts.length-1;i++){
          const p0=pts[i],p1=pts[i+1];
          if(p0.skip||p1.skip)continue;
          if(stype==='temperature'){
            const t0=raw[i]?.y??20,t1=raw[i+1]?.y??20;
            const g=ctx.createLinearGradient(p0.x,p0.y,p1.x,p1.y);
            const alpha=lw>4?0.15:1.0;
            g.addColorStop(0,tempToColor(t0,alpha));g.addColorStop(1,tempToColor(t1,alpha));
            ctx.strokeStyle=g;
          }
          ctx.beginPath();ctx.moveTo(p0.x,p0.y);
          if(p0.cp2x!==undefined)ctx.bezierCurveTo(p0.cp2x,p0.cp2y,p1.cp1x,p1.cp1y,p1.x,p1.y);
          else ctx.lineTo(p1.x,p1.y);
          ctx.stroke();
        }
      }

      if(stype==='temperature'){
        drawPass(12,glowColor);
        drawPass(3,solidColor);
      } else {
        drawPass(12,glowColor);
        drawPass(2,solidColor);
      }

      ctx.globalAlpha=1;ctx.restore();
    });

    ci.data.datasets.forEach((_,di)=>{
      const o=ci.getDatasetMeta(di).dataset.options;
      if(o){o.borderColor='transparent';o.backgroundColor='transparent';}
    });
  }
};

Chart.register(dayNightPlugin);
Chart.register(smoothGlowPlugin);

// ── Render chart ───────────────────────────────────────────────────────────────
function renderChart(haData){
  const ctx=document.getElementById('chart').getContext('2d');
  const datasets=[];
  let xMin=Infinity,xMax=-Infinity;
  let hasY2=false,hasY3=false;

  haData.forEach(arr=>{
    if(!arr||!arr.length)return;
    const eid=arr[0].entity_id||'';
    const meta=sensorsCache?sensorsCache.find(s=>s.entity_id===eid):null;
    const stype=(meta?.type)||'temperature';
    if(meta?.hidden)return;

    const label=arr[0].attributes?.friendly_name||eid;
    const pts=arr
      .map(p=>{const ts=luxon.DateTime.fromISO(p.last_changed).toMillis();const v=parseFloat(p.state);return{x:ts,y:isNaN(v)?null:parseFloat(v.toFixed(2))};})
      .filter(p=>p.y!==null&&!isNaN(p.x));
    if(!pts.length)return;
    xMin=Math.min(xMin,pts[0].x);xMax=Math.max(xMax,pts[pts.length-1].x);

    const axisCfg=TYPE_CONFIG[stype]||TYPE_CONFIG.temperature;
    if(axisCfg.axis==='y2')hasY2=true;
    if(axisCfg.axis==='y3')hasY3=true;

    datasets.push({
      label,data:pts,
      yAxisID:axisCfg.axis,
      borderColor:'transparent',backgroundColor:'transparent',
      fill:false,tension:0.4,pointRadius:0,pointHitRadius:14,
      borderWidth:0,spanGaps:false,
      _stype:stype,
    });
  });

  if(xMin<Infinity){
    solarBands=buildSolarBands(xMin-86400000,xMax+86400000,LOCATION.lat,LOCATION.lon);
  }
  if(chart)chart.destroy();
  const dark=isDark();
  const gridCol=dark?'rgba(255,255,255,0.05)':'rgba(0,0,0,0.05)';
  const tickCol=dark?'#6b7280':'#9ca3af';

  chart=new Chart(ctx,{
    type:'line',data:{datasets},
    options:{
      responsive:true,maintainAspectRatio:false,
      animation:{duration:600,easing:'easeInOutQuart'},
      interaction:{mode:'index',intersect:false},
      plugins:{
        legend:{
          display:true,
          labels:{color:tickCol,font:{size:11},boxWidth:12,padding:12,
            filter:(item,data)=>{
              const ds=data.datasets[item.datasetIndex];
              return ds && ds._stype !== 'temperature';
            }
          }
        },
        tooltip:{
          backgroundColor:dark?'rgba(15,15,19,0.96)':'rgba(255,255,255,0.96)',
          titleColor:dark?'#f1f1f5':'#1a1a2e',bodyColor:dark?'#d1d5db':'#4b5563',
          borderColor:dark?'rgba(255,255,255,0.1)':'rgba(0,0,0,0.08)',
          borderWidth:1,padding:12,displayColors:true,cornerRadius:10,
          callbacks:{
            labelColor:c=>{
              const ds=c.chart.data.datasets[c.datasetIndex];
              const stype=ds._stype||'temperature';
              const col=typeColor(stype,c.parsed.y);
              return{borderColor:col,backgroundColor:col,borderRadius:3};
            },
            label:c=>{
              const ds=c.chart.data.datasets[c.datasetIndex];
              const stype=ds._stype||'temperature';
              const unit=TYPE_CONFIG[stype]?.unit||'\u00b0C';
              return` ${c.dataset.label}: ${c.parsed.y.toFixed(1)}${unit}`;
            }
          }
        }
      },
      scales:{
        x:{type:'time',time:{tooltipFormat:'dd MMM HH:mm',displayFormats:{hour:'HH:mm',day:'dd MMM',month:'MMM yyyy'}},grid:{color:gridCol},border:{display:false},ticks:{color:tickCol,maxRotation:0,autoSkip:true,font:{size:11}}},
        y:{position:'left',grid:{color:gridCol},border:{display:false},
          title:{display:true,text:'\u00b0C',color:tickCol,font:{size:11}},
          ticks:{color:tickCol,font:{size:11},callback:v=>v.toFixed(1)+'\u00b0'}},
        ...(hasY2?{y2:{
          position:'right',grid:{drawOnChartArea:false},border:{display:false},
          title:{display:true,text:'CO\u2082 (ppm)',color:'rgba(251,146,60,0.9)',font:{size:11}},
          ticks:{color:'rgba(251,146,60,0.9)',font:{size:11}},
          min:400,
        }}:{}),
        ...(hasY3?{y3:{
          position:'right',grid:{drawOnChartArea:false},border:{display:false},
          title:{display:true,text:'% / AQI',color:'rgba(59,130,246,0.9)',font:{size:11}},
          ticks:{color:'rgba(59,130,246,0.9)',font:{size:11}},
          min:0,
          offset:!!hasY2,
        }}:{}),
      }
    }
  });
}

window.matchMedia('(prefers-color-scheme:dark)').addEventListener('change',()=>{if(chart)updateChart();});
(async()=>{
  if(defaultSensors.length){
    updateChart();
    try{const s=await loadSensors();renderHero(s);}catch(e){}
  }else{
    document.getElementById('heroArea').innerHTML='<div class="hero-empty">Tap <strong>Sensors</strong> to get started.</div>';
  }
})();
</script>
</body>
</html>
