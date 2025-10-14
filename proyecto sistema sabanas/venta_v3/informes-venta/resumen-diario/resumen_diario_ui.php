<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Resumen Diario de Ventas</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root{ --bg:#f7f8fa; --card:#fff; --fg:#111827; --muted:#6b7280; --b:#e5e7eb;
         --accent:#2563eb; --accent2:#16a34a; --red:#dc2626; --radius:14px; --gap:14px; }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--fg);font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto}
  .wrap{max-width:1100px;margin:0 auto;padding:24px}
  h1{margin:0 0 16px 0;font-size:22px}
  .panel{background:var(--card);border:1px solid var(--b);border-radius:var(--radius);padding:14px;box-shadow:0 1px 0 rgba(17,24,39,.03)}
  .filters{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:var(--gap);align-items:end}
  .field{display:flex;flex-direction:column;gap:6px}
  label{font-size:12px;color:var(--muted)}
  input,select,button{border:1px solid var(--b);background:#fff;color:var(--fg);border-radius:10px;padding:10px 12px;font-size:14px;outline:none}
  .btn{cursor:pointer}.btn.primary{background:var(--accent);border-color:transparent;color:#fff;font-weight:600}
  .btn.line{background:#fff}
  .status{font-size:12px;color:var(--muted);margin-top:10px}
  .grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:var(--gap);margin-top:16px}
  .kpi{background:var(--card);border:1px solid var(--b);border-radius:var(--radius);padding:14px}
  .kpi h3{margin:0;color:var(--muted);font-size:12px}
  .kpi .val{margin-top:8px;font-weight:700;font-size:22px}
  .kpi .sub{margin-top:4px;font-size:12px;color:var(--muted)}
  .chartBox{background:var(--card);border:1px solid var(--b);border-radius:var(--radius);padding:10px;margin-top:16px}
  .tableWrap{background:var(--card);border:1px solid var(--b);border-radius:var(--radius);margin-top:16px;overflow:auto}
  table{width:100%;border-collapse:collapse;min-width:880px;font-size:14px}
  thead th{position:sticky;top:0;background:#f3f4f6;color:#374151;text-align:left;font-weight:600;border-bottom:1px solid var(--b);padding:12px}
  tbody td{border-top:1px solid var(--b);padding:10px}
  tbody tr:hover{background:#fafafa}
  .right{text-align:right}

  /* Toolbar de acciones */
  .toolbar{display:flex;gap:10px;justify-content:flex-end;margin-top:10px}

  /* Ocultar controles en impresión */
  @media print {
    .no-print { display: none !important; }
    @page { margin: 12mm; }
  }

  @media (max-width:900px){.filters{grid-template-columns:repeat(3,minmax(0,1fr))}.grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
  @media (max-width:640px){.filters{grid-template-columns:repeat(2,minmax(0,1fr))}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
</head>
<body>
<div class="wrap">
  <h1>Resumen Diario de Ventas</h1>

  <div class="panel">
    <div class="filters">
      <div class="field">
        <label for="desde">Desde</label>
        <input id="desde" type="date" />
      </div>
      <div class="field">
        <label for="hasta">Hasta</label>
        <input id="hasta" type="date" />
      </div>
      <div class="field">
        <label for="condicion">Condición</label>
        <select id="condicion">
          <option value="">Todas</option>
          <option value="Contado">Contado</option>
          <option value="Credito">Crédito</option>
        </select>
      </div>
      <div class="field">
        <label for="orden">Orden</label>
        <select id="orden">
          <option value="ASC">Cronológico (ASC)</option>
          <option value="DESC">Más reciente (DESC)</option>
        </select>
      </div>
      <div class="field">
        <button class="btn primary no-print" id="btn-buscar">Buscar</button>
      </div>
      <div class="field">
        <button class="btn line no-print" id="btn-export">Exportar CSV</button>
      </div>
    </div>

    <!-- Toolbar de acciones -->
    <div class="toolbar no-print">
      <button class="btn line" id="btn-back" title="Volver (Esc)">Atrás</button>
      <button class="btn line" id="btn-print" title="Imprimir (Ctrl+P)">Imprimir</button>
    </div>

    <div class="status" id="status"></div>
  </div>

  <!-- KPIs totales -->
  <div class="grid">
    <div class="kpi"><h3>Facturas</h3><div class="val" id="kpi-fact">–</div><div class="sub">Totales</div></div>
    <div class="kpi"><h3>Unidades</h3><div class="val" id="kpi-uds">–</div><div class="sub">Σ Cantidades</div></div>
    <div class="kpi"><h3>Neto (sin IVA)</h3><div class="val" id="kpi-neto">–</div><div class="sub">Base imponible</div></div>
    <div class="kpi"><h3>Bruto (con IVA)</h3><div class="val" id="kpi-bruto">–</div><div class="sub">Total facturado</div></div>
    <div class="kpi"><h3>Margen</h3><div class="val" id="kpi-margen">–</div><div class="sub" id="kpi-margen-pct">–</div></div>
  </div>

  <!-- Chart -->
  <div class="chartBox">
    <canvas id="chart" height="300"></canvas>
  </div>

  <!-- Tabla -->
  <div class="tableWrap">
    <table>
      <thead>
        <tr>
          <th>Fecha</th>
          <th class="right">Facturas</th>
          <th class="right">Unidades</th>
          <th class="right">Ingreso Neto</th>
          <th class="right">Ingreso Bruto</th>
          <th class="right">Costo</th>
          <th class="right">Margen</th>
          <th class="right">% Margen (sobre neto)</th>
        </tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="8" style="color:#6b7280;padding:14px">Sin datos. Elegí filtros y presioná <b>Buscar</b>.</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
const API_URL = './api_ventas_resumen_diario.php';
const $ = s => document.querySelector(s);
const fmtNum = n => new Intl.NumberFormat('es-PY',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n||0);
const fmtInt = n => new Intl.NumberFormat('es-PY',{maximumFractionDigits:0}).format(n||0);
const fmtGs  = n => 'Gs. ' + fmtNum(n);
const qs = o => Object.entries(o).filter(([,v])=>v!==''&&v!=null).map(([k,v])=>`${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');

let rows = [];
let chartState = null;

function setStatus(t){ $('#status').textContent = t || ''; }

function params(){
  return {
    desde: $('#desde').value || null,
    hasta: $('#hasta').value || null,
    condicion: $('#condicion').value || null,
    orden: $('#orden').value || 'ASC'
  };
}

// ====== Volver (historial o fallback) ======
function safeBack(){
  const fallback = '/ventas/'; // Cambiá por la ruta que quieras como destino fijo
  const ref = document.referrer;
  try{
    if(ref){
      const u = new URL(ref);
      if(u.origin === location.origin){ history.back(); return; }
    }
  }catch(e){}
  location.href = fallback;
}

// ====== Buscar / Render ======
async function buscar(){
  setStatus('Cargando…');
  try{
    const res = await fetch(API_URL + '?' + qs(params()), {headers:{'Accept':'application/json'}});
    if(!res.ok) throw new Error('HTTP '+res.status);
    const data = await res.json();
    if(!data.success) throw new Error(data.error || 'Respuesta inválida');
    rows = data.rows || [];
    renderTable(rows);
    renderKPIs(data.totales || null);
    renderChart(rows);
    setStatus(`OK • ${rows.length} días`);
  }catch(e){
    console.error(e);
    setStatus('Error: ' + e.message);
    $('#tbody').innerHTML = `<tr><td colspan="8" style="color:#6b7280;padding:14px">Error al consultar la API.</td></tr>`;
    clearChart();
  }
}

function renderTable(rs){
  if(!rs.length){
    $('#tbody').innerHTML = `<tr><td colspan="8" style="color:#6b7280;padding:14px">No se encontraron resultados.</td></tr>`;
    return;
  }
  $('#tbody').innerHTML = rs.map(r => `
    <tr>
      <td>${esc(r.fecha)}</td>
      <td class="right">${fmtInt(r.facturas)}</td>
      <td class="right">${fmtNum(r.uds)}</td>
      <td class="right">${fmtGs(r.ingreso_neto)}</td>
      <td class="right">${fmtGs(r.ingreso_bruto)}</td>
      <td class="right">${fmtGs(r.costo)}</td>
      <td class="right">${fmtGs(r.margen_bruto)}</td>
      <td class="right">${fmtNum(r.margen_pct)}%</td>
    </tr>
  `).join('');
}

function renderKPIs(t){
  if(!t){
    $('#kpi-fact').textContent = '–';
    $('#kpi-uds').textContent = '–';
    $('#kpi-neto').textContent = '–';
    $('#kpi-bruto').textContent = '–';
    $('#kpi-margen').textContent = '–';
    $('#kpi-margen-pct').textContent = '–';
    return;
  }
  $('#kpi-fact').textContent = fmtInt(t.facturas);
  $('#kpi-uds').textContent = fmtNum(t.uds);
  $('#kpi-neto').textContent = fmtGs(t.neto);
  $('#kpi-bruto').textContent = fmtGs(t.bruto);
  $('#kpi-margen').textContent = fmtGs(t.margen);
  $('#kpi-margen-pct').innerHTML = `Margen % (sobre neto): <b>${fmtNum(t.margen_pct)}%</b>`;
}

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}

function clearChart(){ const c=$('#chart'), g=c.getContext('2d'); g.clearRect(0,0,c.width,c.height); chartState=null; }

function renderChart(rs){
  const slice = rs.slice(-30);
  chartState = {
    labels: slice.map(r=>r.fecha),
    neto: slice.map(r=>+r.ingreso_neto||0),
    margen: slice.map(r=>+r.margen_bruto||0)
  };
  drawChart();
}

function drawChart(){
  if(!chartState){ clearChart(); return; }
  const c=$('#chart'), g=c.getContext('2d'); g.clearRect(0,0,c.width,c.height);
  const {labels, neto, margen} = chartState;

  const padL=48, padR=12, padT=20, padB=64;
  const W=c.width, H=c.height;
  const plotW=W-padL-padR, plotH=H-padT-padB;

  g.fillStyle='#fff'; g.fillRect(padL,padT,plotW,plotH);

  const maxVal = Math.max(1, ...neto, ...margen);
  const niceMax = niceNumber(maxVal);
  const ticks=4;
  g.strokeStyle='#e5e7eb'; g.fillStyle='#4b5563'; g.font='12px system-ui';
  for(let i=0;i<=ticks;i++){
    const y=padT + plotH*(i/ticks);
    g.beginPath(); g.moveTo(padL,y); g.lineTo(padL+plotW,y); g.stroke();
    const val = niceMax*(1 - i/ticks);
    g.fillText(shortMoney(val), 6, y+4);
  }

  const n=labels.length;
  const step = n<=15 ? 1 : Math.ceil(n/15);
  for(let i=0;i<n;i+=step){
    const x = padL + plotW*(i/(n-1||1));
    g.fillStyle='#6b7280';
    g.save(); g.translate(x, padT+plotH+16); g.rotate(-Math.PI/6);
    g.fillText(labels[i], 0, 0); g.restore();
  }

  const colN='#2563eb', colM='#16a34a';
  const y = v => padT + plotH*(1 - v/niceMax);
  const x = i => padL + plotW*(i/(n-1||1));

  g.beginPath();
  for(let i=0;i<n;i++){ const xi=x(i), yi=y(neto[i]); i?g.lineTo(xi,yi):g.moveTo(xi,yi); }
  g.strokeStyle=colN; g.lineWidth=2; g.stroke();

  g.beginPath();
  for(let i=0;i<n;i++){ const xi=x(i), yi=y(margen[i]); i?g.lineTo(xi,yi):g.moveTo(xi,yi); }
  g.strokeStyle=colM; g.lineWidth=2; g.stroke();

  const lx = padL, ly = padT - 6;
  g.fillStyle=colN; g.fillRect(lx, ly, 10, 10);
  g.fillStyle='#111827'; g.fillText('Neto (sin IVA)', lx+16, ly+10);
  g.fillStyle=colM; g.fillRect(lx+130, ly, 10, 10);
  g.fillStyle='#111827'; g.fillText('Margen', lx+146, ly+10);
}

function niceNumber(n){
  const p = Math.pow(10, Math.floor(Math.log10(n||1)));
  const d = (n||1)/p;
  let nice; if(d<1.5) nice=1; else if(d<3) nice=2; else if(d<7) nice=5; else nice=10;
  return nice*p;
}
function shortMoney(n){
  const a=Math.abs(n);
  if(a>=1e12) return (n/1e12).toFixed(1)+' Bn';
  if(a>=1e9)  return (n/1e9).toFixed(1)+' Mm';
  if(a>=1e6)  return (n/1e6).toFixed(1)+' M';
  if(a>=1e3)  return (n/1e3).toFixed(1)+' k';
  return fmtNum(n);
}

function exportCSV(){
  if(!rows.length){ alert('No hay datos.'); return; }
  const headers=['fecha','facturas','uds','ingreso_neto','ingreso_bruto','costo','margen_bruto','margen_pct'];
  const csv = [
    headers.join(','),
    ...rows.map(r=>headers.map(h=>String(r[h]??'').replace(/"/g,'""')).map(v=>(/[",\n]/.test(v)?`"${v}"`:v)).join(','))
  ].join('\n');
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a'); a.href=url; a.download='resumen_diario.csv';
  document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
}

// ====== Eventos de UI ======
$('#btn-buscar').addEventListener('click', buscar);
$('#btn-export').addEventListener('click', exportCSV);
$('#btn-back').addEventListener('click', safeBack);
$('#btn-print').addEventListener('click', ()=>window.print());

// Atajos: Ctrl/Cmd+P para imprimir, Esc para volver
document.addEventListener('keydown', (e)=>{
  const tag = (e.target.tagName || '').toLowerCase();
  if(tag === 'input' || tag === 'select' || tag === 'textarea' || e.target.isContentEditable) {
    // si está escribiendo en un campo, no interceptamos
  } else {
    const isMac = navigator.platform.toUpperCase().includes('MAC');
    const mod = isMac ? e.metaKey : e.ctrlKey;
    if(mod && (e.key === 'p' || e.key === 'P')) { e.preventDefault(); window.print(); }
    if(e.key === 'Escape') safeBack();
  }
});

window.addEventListener('resize', ()=>drawChart());

// Carga inicial: últimos 30 días
(function init(){
  const hoy=new Date(); const d=new Date(hoy); d.setDate(hoy.getDate()-29);
  $('#hasta').value=hoy.toISOString().slice(0,10);
  $('#desde').value=d.toISOString().slice(0,10);
  buscar();
})();
</script>
</body>
</html>
