<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Clientes Top — Ventas</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root{
    --bg:#f7f8fa; --card:#fff; --fg:#111827; --muted:#6b7280; --b:#e5e7eb;
    --accent:#2563eb; --accent2:#16a34a; --red:#dc2626;
    --radius:14px; --gap:14px;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--fg);font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto}
  .wrap{max-width:1200px;margin:0 auto;padding:24px}
  h1{margin:0 0 16px 0;font-size:22px}
  .panel{background:var(--card);border:1px solid var(--b);border-radius:var(--radius);padding:14px;box-shadow:0 1px 0 rgba(17,24,39,.03)}
  .filters{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:var(--gap);align-items:end}
  .field{display:flex;flex-direction:column;gap:6px}
  label{font-size:12px;color:var(--muted)}
  input,select,button{border:1px solid var(--b);background:#fff;color:var(--fg);border-radius:10px;padding:10px 12px;font-size:14px;outline:none}
  .btn{cursor:pointer}.btn.primary{background:var(--accent);border-color:transparent;color:#fff;font-weight:600}
  .btn.line{background:#fff}
  .toolbar{display:flex;gap:10px;justify-content:flex-end;margin-top:10px}
  .grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:var(--gap);margin-top:16px}
  .kpi{background:var(--card);border:1px solid var(--b);border-radius:var(--radius);padding:14px}
  .kpi h3{margin:0;font-size:12px;color:var(--muted)} .kpi .val{margin-top:8px;font-weight:700;font-size:22px}
  .kpi .sub{margin-top:4px;font-size:12px;color:var(--muted)}
  .chartBox{background:var(--card);border:1px solid var(--b);border-radius:var(--radius);padding:10px;margin-top:16px}
  .tableWrap{background:var(--card);border:1px solid var(--b);border-radius:var(--radius);margin-top:16px;overflow:auto}
  table{width:100%;border-collapse:collapse;min-width:1000px;font-size:14px}
  thead th{position:sticky;top:0;background:#f3f4f6;color:#374151;text-align:left;font-weight:600;border-bottom:1px solid var(--b);padding:12px}
  tbody td{border-top:1px solid var(--b);padding:10px}
  tbody tr:hover{background:#fafafa}
  .right{text-align:right}
  .status{font-size:12px;color:var(--muted);margin-top:10px}
  .ok{color:var(--accent2)} .bad{color:var(--red)}
  @media (max-width:1000px){.filters{grid-template-columns:repeat(3,minmax(0,1fr))}.grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
  @media (max-width:640px){.filters{grid-template-columns:repeat(2,minmax(0,1fr))}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
</head>
<body>
<div class="wrap">
  <h1>Clientes Top — Ventas</h1>

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
        <label for="order_by">Ordenar por</label>
        <select id="order_by">
          <option value="neto">Ingreso Neto</option>
          <option value="margen">Margen</option>
          <option value="uds">Unidades</option>
          <option value="facturas">Facturas</option>
        </select>
      </div>
      <div class="field">
        <label for="criterio">Criterio de participación</label>
        <select id="criterio">
          <option value="neto">Neto</option>
          <option value="margen">Margen</option>
        </select>
      </div>
      <div class="field">
        <label for="top">Top N</label>
        <input id="top" type="number" min="1" value="20" />
      </div>
      <div class="field">
        <button class="btn primary" id="btn-buscar">Buscar</button>
      </div>
    </div>

    <div class="toolbar">
      <!-- Botón Atrás agregado -->
      <button class="btn line" id="btn-back" title="Volver">Atrás</button>

      <button class="btn line" id="btn-export">Exportar CSV</button>
      <button class="btn line" id="btn-print">Imprimir</button>
    </div>

    <div class="status" id="status"></div>
  </div>

  <!-- KPIs -->
  <div class="grid">
    <div class="kpi"><h3>Clientes en Top</h3><div class="val" id="kpi-clientes">–</div><div class="sub">Cantidad listada</div></div>
    <div class="kpi"><h3>Ingreso Neto (sin IVA)</h3><div class="val" id="kpi-neto">–</div><div class="sub">Suma neta</div></div>
    <div class="kpi"><h3>Ingreso Bruto (con IVA)</h3><div class="val" id="kpi-bruto">–</div><div class="sub">Suma bruta</div></div>
    <div class="kpi"><h3>Margen Bruto</h3><div class="val" id="kpi-margen">–</div><div class="sub" id="kpi-margen-pct">–</div></div>
    <div class="kpi"><h3>Facturas</h3><div class="val" id="kpi-facturas">–</div><div class="sub">Totales</div></div>
  </div>

  <!-- Chart -->
  <div class="chartBox">
    <canvas id="chart" height="320"></canvas>
  </div>

  <!-- Tabla -->
  <div class="tableWrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Cliente</th>
          <th class="right">Facturas</th>
          <th class="right">Unidades</th>
          <th class="right">Ingreso Neto</th>
          <th class="right">Ingreso Bruto</th>
          <th class="right">Costo</th>
          <th class="right">Margen</th>
          <th class="right">% Margen</th>
          <th class="right">% Part. Neto</th>
          <th class="right">% Part. Margen</th>
        </tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="11" style="color:#6b7280;padding:14px">Sin datos. Elegí filtros y presioná <b>Buscar</b>.</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
const API_URL = './api_ventas_clientes_top.php';

const $ = s => document.querySelector(s);
const fmtNum = n => new Intl.NumberFormat('es-PY',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n||0);
const fmtInt = n => new Intl.NumberFormat('es-PY',{maximumFractionDigits:0}).format(n||0);
const fmtGs  = n => 'Gs. ' + fmtNum(n);
const qs = o => Object.entries(o).filter(([,v])=>v!==''&&v!=null).map(([k,v])=>`${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');

let rows = [];
let chartState = null;

function setStatus(t){ $('#status').textContent = t || ''; }

function collectParams(){
  return {
    desde: $('#desde').value || null,
    hasta: $('#hasta').value || null,
    condicion: $('#condicion').value || null,
    order_by: $('#order_by').value || 'neto',
    criterio: $('#criterio').value || 'neto',
    top: $('#top').value || 20
  };
}

async function buscar(){
  setStatus('Cargando…');
  const url = API_URL + '?' + qs(collectParams());
  try{
    const res = await fetch(url, {headers:{'Accept':'application/json'}});
    if(!res.ok) throw new Error('HTTP '+res.status);
    const data = await res.json();
    if(!data.success) throw new Error(data.error || 'Respuesta inválida');
    rows = data.rows || [];
    renderTable(rows);
    renderKPIs(rows);
    renderChart(rows);
    setStatus(`OK • ${data.count} clientes`);
  }catch(e){
    console.error(e);
    setStatus('Error: ' + e.message);
    $('#tbody').innerHTML = `<tr><td colspan="11" style="color:#6b7280;padding:14px">Error al consultar la API.</td></tr>`;
    renderKPIs([]);
    clearChart();
  }
}

function renderTable(rs){
  if(!rs.length){
    $('#tbody').innerHTML = `<tr><td colspan="11" style="color:#6b7280;padding:14px">No se encontraron resultados.</td></tr>`;
    return;
  }
  $('#tbody').innerHTML = rs.map(r => `
    <tr>
      <td>${r.rank}</td>
      <td>${esc(r.cliente)}</td>
      <td class="right">${fmtInt(r.facturas)}</td>
      <td class="right">${fmtNum(r.uds)}</td>
      <td class="right">${fmtGs(r.ingreso_neto)}</td>
      <td class="right">${fmtGs(r.ingreso_bruto)}</td>
      <td class="right">${fmtGs(r.costo)}</td>
      <td class="right">${fmtGs(r.margen_bruto)}</td>
      <td class="right">${fmtNum(r.margen_pct)}%</td>
      <td class="right">${fmtNum(r.part_neto_pct)}%</td>
      <td class="right">${fmtNum(r.part_margen_pct)}%</td>
    </tr>
  `).join('');
}

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}

function renderKPIs(rs){
  const t = rs.reduce((a,r)=>({
    clientes: a.clientes+1,
    neto: a.neto + (r.ingreso_neto||0),
    bruto: a.bruto + (r.ingreso_bruto||0),
    costo: a.costo + (r.costo||0),
    margen: a.margen + (r.margen_bruto||0),
    fact: a.fact + (r.facturas||0)
  }), {clientes:0, neto:0, bruto:0, costo:0, margen:0, fact:0});

  const pct = t.neto>0 ? (t.margen/t.neto*100) : 0;
  $('#kpi-clientes').textContent = fmtInt(t.clientes);
  $('#kpi-neto').textContent = fmtGs(t.neto);
  $('#kpi-bruto').textContent = fmtGs(t.bruto);
  $('#kpi-margen').textContent = fmtGs(t.margen);
  $('#kpi-facturas').textContent = fmtInt(t.fact);
  $('#kpi-margen-pct').innerHTML = `Margen % (sobre neto): <b>${fmtNum(pct)}%</b>`;
}

function clearChart(){
  const c = $('#chart'); const g = c.getContext('2d');
  g.clearRect(0,0,c.width,c.height); chartState=null;
}

function renderChart(rs){
  // Mostramos hasta 12 para que no explote el rótulo
  const slice = rs.slice(0, 12);
  chartState = {
    labels: slice.map(r=>r.cliente || ('ID ' + r.id_cliente)),
    neto: slice.map(r=>+r.ingreso_neto||0),
    margen: slice.map(r=>+r.margen_bruto||0)
  };
  drawChart();
}

function drawChart(){
  if(!chartState){ clearChart(); return; }
  const c = $('#chart'); const g = c.getContext('2d'); g.clearRect(0,0,c.width,c.height);
  const {labels, neto, margen} = chartState;

  const padL=120, padR=20, padT=20, padB=40;
  const W=c.width, H=c.height;
  const plotW=W-padL-padR, plotH=H-padT-padB;

  // Fondo
  g.fillStyle='#fff'; g.fillRect(padL,padT,plotW,plotH);

  // Escala X basada en el mayor de neto/margen
  const maxVal = Math.max(1, ...neto, ...margen);
  const niceMax = niceNumber(maxVal);
  const yN = labels.length;
  const rowH = plotH / yN;

  // Líneas verticales
  g.strokeStyle='#e5e7eb'; g.fillStyle='#4b5563'; g.font='12px system-ui';
  const ticks=4;
  for(let i=0;i<=ticks;i++){
    const x=padL + plotW * (i/ticks);
    g.beginPath(); g.moveTo(x,padT); g.lineTo(x,padT+plotH); g.stroke();
    const val = niceMax * (i/ticks);
    g.fillText(shortMoney(val), x-12, padT+plotH+16);
  }

  // Barras horizontales: Neto (azul) y Margen (verde)
  const colN='#2563eb', colM='#16a34a';
  for(let i=0;i<yN;i++){
    const y = padT + i*rowH + rowH*0.15;
    const h = rowH*0.3;
    // Neto
    const wN = plotW * (neto[i]/niceMax);
    g.fillStyle = colN; g.fillRect(padL, y, wN, h);
    // Margen (debajo)
    const y2 = y + h + 6;
    const h2 = h;
    const wM = plotW * (margen[i]/niceMax);
    g.fillStyle = colM; g.fillRect(padL, y2, wM, h2);
    // Label cliente
    g.fillStyle = '#111827';
    g.textAlign = 'right';
    g.fillText(labels[i], padL - 8, y + h); // centrado vertical aprox
    g.textAlign = 'left';
  }

  // Leyenda
  const lx = padL, ly = padT - 6;
  g.fillStyle=colN; g.fillRect(lx, ly, 10, 10);
  g.fillStyle='#111827'; g.fillText('Ingreso Neto', lx+16, ly+10);
  g.fillStyle=colM; g.fillRect(lx+120, ly, 10, 10);
  g.fillStyle='#111827'; g.fillText('Margen', lx+136, ly+10);
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

function exportCSV(rs){
  if(!rs.length){ alert('No hay datos.'); return; }
  const headers = ['rank','id_cliente','cliente','facturas','uds','ingreso_neto','ingreso_bruto','costo','margen_bruto','margen_pct','part_neto_pct','part_margen_pct'];
  const csv = [
    headers.join(','),
    ...rs.map(r=>headers.map(h=>String(r[h]??'').replace(/"/g,'""')).map(v=>(/[",\n]/.test(v)?`"${v}"`:v)).join(','))
  ].join('\n');
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a'); a.href=url;
  a.download='clientes_top.csv'; document.body.appendChild(a); a.click(); a.remove();
  URL.revokeObjectURL(url);
}

// ===== Botón Atrás =====
function safeBack(){
  const fallback = '/ventas/'; // Cambiá por la ruta que prefieras
  const ref = document.referrer;
  try{
    if(ref){
      const u = new URL(ref);
      if(u.origin === location.origin){ history.back(); return; }
    }
  }catch(e){}
  location.href = fallback;
}

// Eventos
$('#btn-buscar').addEventListener('click', buscar);
$('#btn-print').addEventListener('click', ()=>window.print());
$('#btn-export').addEventListener('click', ()=>exportCSV(rows));
$('#btn-back').addEventListener('click', safeBack);
window.addEventListener('resize', ()=>drawChart());

// Rango por defecto: últimos 30 días
(function init(){
  const hoy = new Date(); const d = new Date(hoy); d.setDate(hoy.getDate()-29);
  $('#hasta').value = hoy.toISOString().slice(0,10);
  $('#desde').value = d.toISOString().slice(0,10);
  buscar();
})();
</script>
</body>
</html>
