<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reporte de Ingresos y Margen</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root{
    /* Tema claro */
    --bg:#f7f8fa;
    --card:#ffffff;
    --muted:#5b6472;
    --fg:#111827;
    --b:#e5e7eb;
    --accent:#2563eb;   /* azul */
    --accent-2:#16a34a; /* verde */
    --accent-3:#ca8a04; /* amarillo */
    --red:#dc2626;
    --radius:14px;
    --gap:14px;
    --pad:12px;
  }
  *{box-sizing:border-box}
  body{
    margin:0;background:var(--bg);color:var(--fg);
    font-family: ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Noto Sans",Arial;
  }
  .wrap{max-width:1200px;margin:0 auto;padding:24px}
  h1{font-size:22px;margin:0 0 16px 0;font-weight:700;letter-spacing:.2px}
  .panel{
    background:var(--card);border:1px solid var(--b);border-radius:var(--radius);padding:14px;
    box-shadow:0 1px 0 rgba(17,24,39,.03);
  }
  .filters{
    display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:var(--gap);align-items:end;
  }
  .filters .field{display:flex;flex-direction:column;gap:6px}
  label{font-size:12px;color:var(--muted)}
  input,select,button{
    border:1px solid var(--b);background:#fff;color:var(--fg);
    border-radius:10px;padding:10px 12px;font-size:14px;outline:none;
  }
  input::placeholder{color:#9aa3b2}
  .btn{cursor:pointer;transition:transform .05s ease, opacity .15s ease}
  .btn:active{transform:translateY(1px)}
  .btn.primary{background:var(--accent);border-color:transparent;color:white;font-weight:600}
  .btn.ghost{background:transparent}
  .btn.line{background:#fff}
  .btnRow{display:flex;gap:10px}
  .grid{
    display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:var(--gap);margin-top:16px;
  }
  .kpi{
    background:var(--card);border:1px solid var(--b);border-radius:var(--radius);padding:14px;
  }
  .kpi h3{margin:0;color:var(--muted);font-size:12px;font-weight:600}
  .kpi .val{margin-top:8px;font-size:22px;font-weight:700}
  .kpi .sub{margin-top:4px;font-size:12px;color:var(--muted)}
  .chartBox{
    background:var(--card);border:1px solid var(--b);border-radius:var(--radius);padding:10px;margin-top:16px;
  }
  .tableWrap{
    background:var(--card);border:1px solid var(--b);border-radius:var(--radius);margin-top:16px;overflow:auto;
  }
  table{width:100%;border-collapse:collapse;font-size:14px;min-width:860px}
  thead th{
    position:sticky;top:0;background:#f3f4f6;color:#374151;
    text-align:left;font-weight:600;border-bottom:1px solid var(--b);padding:12px;
  }
  tbody td{border-top:1px solid var(--b);padding:10px}
  tbody tr:hover{background:#fafafa}
  .right{text-align:right}
  .status{font-size:12px;color:var(--muted);margin-top:10px}
  .bad{color:var(--red)}
  .ok{color:var(--accent-2)}
  .toolbar{display:flex;gap:10px;justify-content:flex-end;margin-top:10px}
  .empty{padding:16px;color:var(--muted)}
  @media (max-width:1000px){
    .filters{grid-template-columns:repeat(3,minmax(0,1fr))}
    .grid{grid-template-columns:repeat(3,minmax(0,1fr))}
  }
  @media (max-width:640px){
    .filters{grid-template-columns:repeat(2,minmax(0,1fr))}
    .grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  }
  @media (max-width:420px){
    .grid{grid-template-columns:1fr}
  }
</style>
</head>
<body>
  <div class="wrap">
    <h1>Reporte de Ingresos y Margen</h1>

    <!-- Filtros -->
    <div class="panel" id="panel-filtros">
      <div class="filters">
        <div class="field">
          <label for="desde">Desde (YYYY-MM-DD)</label>
          <input id="desde" type="date" />
        </div>
        <div class="field">
          <label for="hasta">Hasta (YYYY-MM-DD)</label>
          <input id="hasta" type="date" />
        </div>
        <div class="field">
          <label for="id_cliente">ID Cliente (opcional)</label>
          <input id="id_cliente" type="number" min="1" placeholder="Ej: 123" />
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
          <label for="group">Agrupar por</label>
          <select id="group">
            <option value="dia">Día</option>
            <option value="mes">Mes</option>
            <option value="cliente">Cliente</option>
            <option value="producto">Producto</option>
          </select>
        </div>
        <div class="field btnRow">
          <button class="btn primary" id="btn-buscar">Buscar</button>
          <button class="btn line" id="btn-limpiar" title="Limpiar filtros">Limpiar</button>
        </div>
      </div>

      <div class="toolbar">
        <!-- Botón Atrás -->
        <button class="btn ghost" id="btn-back" title="Volver (Esc)">Atrás</button>

        <button class="btn ghost" id="btn-export">Exportar CSV</button>
        <button class="btn ghost" id="btn-print">Imprimir</button>
      </div>

      <div class="status" id="status"></div>
    </div>

    <!-- KPIs -->
    <div class="grid" id="kpis">
      <div class="kpi">
        <h3>Facturas</h3>
        <div class="val" id="kpi-facturas">–</div>
        <div class="sub">Total de comprobantes</div>
      </div>
      <div class="kpi">
        <h3>Unidades</h3>
        <div class="val" id="kpi-uds">–</div>
        <div class="sub">Σ Cantidades</div>
      </div>
      <div class="kpi">
        <h3>Ingreso Neto (sin IVA)</h3>
        <div class="val" id="kpi-neto">–</div>
        <div class="sub">Base imponible</div>
      </div>
      <div class="kpi">
        <h3>Ingreso Bruto (con IVA)</h3>
        <div class="val" id="kpi-bruto">–</div>
        <div class="sub">Σ (precio * cant)</div>
      </div>
      <div class="kpi">
        <h3>Margen Bruto</h3>
        <div class="val" id="kpi-margen">–</div>
        <div class="sub" id="kpi-margen-pct">–</div>
      </div>
    </div>

    <!-- Chart -->
    <div class="chartBox">
      <canvas id="chart" height="260"></canvas>
    </div>

    <!-- Tabla -->
    <div class="tableWrap">
      <table>
        <thead>
          <tr>
            <th>Grupo</th>
            <th class="right">Facturas</th>
            <th class="right">Unidades</th>
            <th class="right">Ingreso Bruto (con IVA)</th>
            <th class="right">Ingreso Neto (sin IVA)</th>
            <th class="right">Costo</th>
            <th class="right">Margen</th>
            <th class="right">Margen % (sobre neto)</th>
          </tr>
        </thead>
        <tbody id="tbody">
          <tr><td colspan="8" class="empty">Sin datos. Elegí filtros y presioná <b>Buscar</b>.</td></tr>
        </tbody>
      </table>
    </div>
  </div>

<script>
  // ========== Config ==========
  const API_URL = './api_ventas_ingresos.php'; // Ajustá si tu endpoint vive en otra ruta

  // ========== Helpers ==========
  const $ = (q) => document.querySelector(q);
  const fmtInt = (n) => new Intl.NumberFormat('es-PY', {maximumFractionDigits:0}).format(n||0);
  const fmtNum = (n) => new Intl.NumberFormat('es-PY', {minimumFractionDigits:2, maximumFractionDigits:2}).format(n||0);
  const fmtGs  = (n) => 'Gs. ' + fmtNum(n);
  const qs = (params) => Object.entries(params)
    .filter(([,v]) => v !== null && v !== undefined && v !== '')
    .map(([k,v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');

  // ========== Estado ==========
  let lastRows = [];
  let chartState = null;

  function setStatus(msg, type=''){
    const el = $('#status');
    el.textContent = msg || '';
    el.className = 'status ' + (type || '');
  }

  function limpiar(){
    $('#desde').value = '';
    $('#hasta').value = '';
    $('#id_cliente').value = '';
    $('#condicion').value = '';
    $('#group').value = 'dia';
    $('#tbody').innerHTML = `<tr><td colspan="8" class="empty">Sin datos. Elegí filtros y presioná <b>Buscar</b>.</td></tr>`;
    renderKPIs([]);
    clearChart();
    lastRows = [];
    setStatus('');
  }

  // ========== Volver ==========
  function safeBack() {
    const fallback = '/ventas/'; // <-- cambiá si querés otro módulo
    const ref = document.referrer;
    try {
      if (ref) {
        const u = new URL(ref);
        if (u.origin === location.origin) {
          history.back();
          return;
        }
      }
    } catch (e) {}
    location.href = fallback;
  }

  // ========== Fetch ==========
  async function buscar(){
    const params = {
      desde: $('#desde').value || null,
      hasta: $('#hasta').value || null,
      id_cliente: $('#id_cliente').value || null,
      condicion: $('#condicion').value || null,
      group: $('#group').value || 'dia'
    };
    const url = API_URL + (qs(params) ? `?${qs(params)}` : '');
    setStatus('Cargando…');
    try{
      const res = await fetch(url, {headers:{'Accept':'application/json'}});
      if(!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      if(!data.success){ throw new Error(data.error || 'Respuesta inválida'); }
      lastRows = Array.isArray(data.rows) ? data.rows : [];
      renderTable(lastRows);
      renderKPIs(lastRows);
      renderChart(lastRows, params.group);
      setStatus(`OK • ${data.count ?? lastRows.length} registros`);
    }catch(err){
      console.error(err);
      setStatus('Error al cargar datos: ' + (err.message || err), 'bad');
      $('#tbody').innerHTML = `<tr><td colspan="8" class="empty">Ocurrió un error al consultar la API.</td></tr>`;
      renderKPIs([]);
      clearChart();
    }
  }

  // ========== Render Tabla ==========
  function renderTable(rows){
    if(!rows.length){
      $('#tbody').innerHTML = `<tr><td colspan="8" class="empty">No se encontraron resultados con los filtros seleccionados.</td></tr>`;
      return;
    }
    const html = rows.map(r => `
      <tr>
        <td>${esc(r.grupo)}</td>
        <td class="right">${fmtInt(r.facturas)}</td>
        <td class="right">${fmtNum(r.uds)}</td>
        <td class="right">${fmtGs(r.ingreso_bruto)}</td>   <!-- con IVA -->
        <td class="right">${fmtGs(r.ingreso_neto)}</td>    <!-- sin IVA -->
        <td class="right">${fmtGs(r.costo)}</td>
        <td class="right">${fmtGs(r.margen_bruto)}</td>
        <td class="right">${fmtNum(pctSobreNeto(r))}%</td>
      </tr>
    `).join('');
    $('#tbody').innerHTML = html;
  }

  function esc(s){
    return String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  }

  // ========== KPIs ==========
  function pctSobreNeto(r){
    const neto = Number(r.ingreso_neto||0);
    const margen = Number(r.margen_bruto||0);
    return neto>0 ? (margen/neto*100) : 0;
  }

  function renderKPIs(rows){
    const totals = rows.reduce((acc,r) => {
      acc.facturas += (r.facturas||0);
      acc.uds += (r.uds||0);
      acc.bruto += (r.ingreso_bruto||0);  // con IVA
      acc.neto += (r.ingreso_neto||0);    // sin IVA
      acc.costo += (r.costo||0);
      acc.margen += (r.margen_bruto||0);
      return acc;
    }, {facturas:0, uds:0, bruto:0, neto:0, costo:0, margen:0});

    const margenPctSobreNeto = totals.neto>0 ? (totals.margen/totals.neto*100) : 0;

    $('#kpi-facturas').textContent = fmtInt(totals.facturas);
    $('#kpi-uds').textContent      = fmtNum(totals.uds);
    $('#kpi-neto').textContent     = fmtGs(totals.neto);
    $('#kpi-bruto').textContent    = fmtGs(totals.bruto);
    $('#kpi-margen').textContent   = fmtGs(totals.margen);
    $('#kpi-margen-pct').innerHTML = `Margen % (sobre neto): <b>${fmtNum(margenPctSobreNeto)}%</b>`;
    $('#kpi-margen-pct').className = 'sub ' + (margenPctSobreNeto < 0 ? 'bad':'ok');
  }

  // ========== Chart (Canvas nativo) ==========
  function clearChart(){
    const c = $('#chart');
    const g = c.getContext('2d');
    g.clearRect(0,0,c.width,c.height);
    chartState = null;
  }

  function renderChart(rows, group){
    const labels = rows.map(r => String(r.grupo));
    const serieMargen = rows.map(r => Number(r.margen_bruto||0));
    const serieBruto  = rows.map(r => Number(r.ingreso_bruto||0)); // con IVA

    chartState = {labels, serieMargen, serieBruto, group};
    drawChart();
  }

  function drawChart(){
    if(!chartState){ clearChart(); return; }
    const {labels, serieMargen, serieBruto} = chartState;
    const c = $('#chart');
    const g = c.getContext('2d');
    g.clearRect(0,0,c.width,c.height);

    // padding interno
    const padL = 64, padR = 20, padT = 20, padB = 64;
    const W = c.width, H = c.height;
    const plotW = W - padL - padR;
    const plotH = H - padT - padB;

    // Ejes
    const maxVal = Math.max(1, ...serieBruto, ...serieMargen);
    const niceMax = niceNumber(maxVal);
    const yTicks = 4;

    // Fondo del área
    g.fillStyle = '#ffffff';
    g.fillRect(padL, padT, plotW, plotH);

    // Líneas horizontales + etiquetas
    g.strokeStyle = '#e5e7eb';
    g.lineWidth = 1;
    g.fillStyle = '#4b5563';
    g.font = '12px system-ui, -apple-system, Segoe UI, Roboto';
    for(let i=0;i<=yTicks;i++){
      const y = padT + (plotH * i / yTicks);
      g.beginPath(); g.moveTo(padL, y); g.lineTo(padL + plotW, y); g.stroke();
      const val = niceMax * (1 - i/yTicks);
      g.fillText(shortMoney(val), 6, y + 4);
    }

    // Barras (Bruto vs Margen lado a lado)
    const n = labels.length;
    if(n === 0) return;

    const groupGap = 12;
    const barGap   = 6;
    const barsPerGroup = 2;
    const groupWidth = plotW / n;
    const barWidth = Math.max(2, (groupWidth - groupGap) / barsPerGroup - barGap);

    const colBruto = '#2563eb'; // azul
    const colMarg  = '#16a34a'; // verde

    for(let i=0;i<n;i++){
      const x0 = padL + groupWidth * i + groupGap/2;

      // Bruto
      const hB = Math.max(0, plotH * (serieBruto[i] / niceMax));
      const yB = padT + plotH - hB;
      g.fillStyle = colBruto;
      g.fillRect(x0, yB, barWidth, hB);

      // Margen
      const hM = Math.max(0, plotH * (serieMargen[i] / niceMax));
      const yM = padT + plotH - hM;
      g.fillStyle = colMarg;
      g.fillRect(x0 + barWidth + barGap, yM, barWidth, hM);

      // Etiqueta X (cada ~5)
      if(n <= 16 || i % Math.ceil(n/16) === 0){
        g.save();
        g.translate(x0 + barWidth, padT + plotH + 14);
        g.rotate(-Math.PI/6);
        g.fillStyle = '#6b7280';
        g.fillText(labels[i], 0, 0);
        g.restore();
      }
    }

    // Leyenda
    const lx = padL + 10, ly = padT + 10;
    g.fillStyle = colBruto; g.fillRect(lx, ly, 10, 10);
    g.fillStyle = '#111827'; g.fillText('Ingreso Bruto (IVA)', lx+16, ly+10);
    g.fillStyle = colMarg; g.fillRect(lx+160, ly, 10, 10);
    g.fillStyle = '#111827'; g.fillText('Margen', lx+176, ly+10);
  }

  function niceNumber(n){
    const p = Math.pow(10, Math.floor(Math.log10(n)));
    const d = n / p;
    let nice;
    if(d < 1.5) nice = 1;
    else if(d < 3) nice = 2;
    else if(d < 7) nice = 5;
    else nice = 10;
    return nice * p;
  }
  function shortMoney(n){
    const abs = Math.abs(n);
    if(abs >= 1e12) return (n/1e12).toFixed(1)+' Bn';
    if(abs >= 1e9)  return (n/1e9).toFixed(1)+' Mm';
    if(abs >= 1e6)  return (n/1e6).toFixed(1)+' M';
    if(abs >= 1e3)  return (n/1e3).toFixed(1)+' k';
    return fmtNum(n);
  }

  // ========== Export CSV ==========
  function exportCSV(rows){
    if(!rows.length){ alert('No hay datos para exportar.'); return; }
    const headers = ['grupo','grupo_id','facturas','uds','ingreso_bruto','ingreso_neto','costo','margen_bruto','margen_pct'];
    const csv = [
      headers.join(','),
      ...rows.map(r => {
        const obj = {...r};
        obj.margen_pct = (Number(r.ingreso_neto||0) > 0) ? (Number(r.margen_bruto||0)/Number(r.ingreso_neto))*100 : 0;
        return headers.map(h => String(obj[h] ?? '')).map(csvEscape).join(',');
      })
    ].join('\n');
    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const ts = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');
    a.download = `reporte_ingresos_${ts}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }
  function csvEscape(s){
    if(/[",\n]/.test(s)) return `"${s.replace(/"/g,'""')}"`;
    return s;
  }

  // ========== Eventos ==========
  $('#btn-buscar').addEventListener('click', buscar);
  $('#btn-limpiar').addEventListener('click', limpiar);
  $('#btn-export').addEventListener('click', ()=>exportCSV(lastRows));
  $('#btn-print').addEventListener('click', ()=>window.print());
  $('#btn-back').addEventListener('click', safeBack);
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') safeBack(); });
  window.addEventListener('resize', ()=>drawChart());

  // Cargar al entrar (opcional: últimos 30 días)
  (function initDefaultRange(){
    const hoy = new Date();
    const desde = new Date(hoy); desde.setDate(hoy.getDate()-29);
    $('#hasta').value = hoy.toISOString().slice(0,10);
    $('#desde').value = desde.toISOString().slice(0,10);
    buscar();
  })();
</script>
</body>
</html>
