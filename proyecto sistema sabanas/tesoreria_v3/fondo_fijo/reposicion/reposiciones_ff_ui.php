<?php
session_start();
require_once __DIR__ . '../../../../conexion/configv2.php';
if (empty($_SESSION['nombre_usuario'])) { header('Location: /login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<title>Fondo Fijo · Reposición</title>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<style>
:root{
  --bg:#f6f7fb; --card:#fff; --line:#e5e7eb; --muted:#6b7280; --primary:#2563eb;
  --ok:#059669; --danger:#dc2626; --ink:#111827;
  font-family:"Segoe UI",system-ui,Arial,sans-serif;
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink)}
header{background:#fff;border-bottom:1px solid var(--line);padding:14px 20px;display:flex;gap:12px;align-items:center}
header h1{margin:0;font-size:18px}
main{max-width:1100px;margin:0 auto;padding:18px 20px}
button{font:inherit;font-weight:600;padding:9px 12px;border-radius:10px;border:1px solid var(--line);background:#fff;cursor:pointer}
button.primary{background:var(--primary);color:#fff;border-color:var(--primary)}
button[disabled]{opacity:.6;cursor:not-allowed}
.card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px}
.tablewrap{border:1px solid var(--line);border-radius:12px;overflow:hidden}
table{width:100%;border-collapse:collapse}
thead{background:#eef2ff}
th,td{padding:10px 12px;border-bottom:1px solid var(--line);font-size:14px;text-align:left;vertical-align:middle}
tbody tr:hover{background:#f9fafb}
.small{font-size:12px;color:var(--muted)}
.right{text-align:right}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:14px 0}
.kpi{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px}
.kpi .h{color:var(--muted);font-size:12px}
.kpi .v{font-size:22px;font-weight:700;margin-top:4px}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:700}
.badge.ok{background:#dcfce7;color:#166534}
.badge.info{background:#e0f2fe;color:#075985}
.empty{padding:16px;text-align:center;color:var(--muted)}
.modal-backdrop{position:fixed;inset:0;background:rgba(2,6,23,.5);display:none;align-items:flex-start;justify-content:center;padding:40px 16px;z-index:40}
.modal-backdrop.active{display:flex}
.modal{background:#fff;border:1px solid var(--line);border-radius:12px;max-width:640px;width:100%;box-shadow:0 24px 60px rgba(2,6,23,.25);overflow:hidden}
.modal header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--line)}
.modal header h2{margin:0;font-size:16px}
.modal header button{border:none;background:none;font-size:22px;cursor:pointer;color:#6b7280}
.modal .body{padding:14px 16px;display:grid;gap:12px}
.modal .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px}
.modal footer{padding:12px 16px;border-top:1px solid var(--line);display:flex;justify-content:flex-end;gap:8px}
.err{color:#b91c1c;font-size:13px}
.info{font-size:13px;color:#1e3a8a;background:#eef2ff;border:1px solid #c7d2fe;padding:10px;border-radius:8px}
input,select,textarea{font:inherit;border:1px solid var(--line);border-radius:8px;padding:8px;background:#fff}
</style>
</head>
<body>
<header>
  <h1>Fondo Fijo · Reposición</h1>
  <div style="margin-left:auto;display:flex;gap:8px">
    <button id="btn-reload">Actualizar</button>
  </div>
</header>

<main>
  <div class="card">
    <div class="kpis">
      <div class="kpi"><div class="h">Rendiciones elegibles</div><div class="v" id="kpi-cant">-</div></div>
      <div class="kpi"><div class="h">Total aprobado (vista)</div><div class="v" id="kpi-total">-</div></div>
    </div>
    <div class="tablewrap">
      <table>
        <thead>
          <tr>
            <th># Rendición</th>
            <th>Fondo Fijo</th>
            <th>Moneda</th>
            <th class="right">Total aprobado</th>
            <th>Estado</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody id="tb"></tbody>
      </table>
      <div class="empty" id="empty" style="display:none">No hay rendiciones aprobadas/parciales.</div>
    </div>
  </div>
</main>

<!-- Modal Generar CxP -->
<div class="modal-backdrop" id="m">
  <div class="modal">
    <header>
      <h2>Generar CxP de Reposición</h2>
      <button type="button" id="m-x">&times;</button>
    </header>
    <div class="body">
      <div class="info" id="m-info"></div>
      <form id="m-form" class="grid">
        <label>Fecha emisión
          <input type="date" name="fecha_emision" required/>
        </label>
        <label>Fecha vencimiento
          <input type="date" name="fecha_venc" required/>
        </label>
        <label style="grid-column:1/-1">Observación
          <textarea name="observacion" rows="2" placeholder="Opcional"></textarea>
        </label>
        <div class="err" id="m-err"></div>
      </form>
    </div>
    <footer>
      <button type="button" id="m-cancel">Cancelar</button>
      <button type="button" class="primary" id="m-ok">Crear CxP</button>
    </footer>
  </div>
</div>

<script>
const API = '../reposicion/reposiciones_ff_api.php';

const tb = document.getElementById('tb');
const empty = document.getElementById('empty');
const kpiCant = document.getElementById('kpi-cant');
const kpiTotal = document.getElementById('kpi-total');

function fmt(n, cur=''){
  const num = Number(n||0).toLocaleString('es-PY',{minimumFractionDigits:0,maximumFractionDigits:2});
  return cur ? `${cur} ${num}` : num;
}
function esc(s){return (s??'').toString().replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}

async function loadList(){
  try{
    // La API, sin filtros, devuelve rendiciones Aprobadas/Parciales elegibles
    const r = await fetch(API, {credentials:'same-origin'});
    const j = await r.json();
    if(!j.ok) throw new Error(j.error||'Error al listar');
    renderRows(j.data||[]);
  }catch(err){ alert(err.message); }
}

function renderRows(rows){
  tb.innerHTML='';
  if (!rows.length){ empty.style.display='block'; kpiCant.textContent='0'; kpiTotal.textContent='0'; return; }
  empty.style.display='none';
  let total = 0;
  rows.forEach(x=>{
    total += Number(x.total_aprobado||0);
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>#${x.id_rendicion}</td>
      <td>${esc(x.nombre_ff||'')}</td>
      <td>${esc(x.moneda||'')}</td>
      <td class="right">${fmt(x.total_aprobado, x.moneda)}</td>
      <td><span class="badge ${x.estado==='Aprobada'?'ok':'info'}">${esc(x.estado)}</span>
          ${x.ya_reposicionada?'<span class="badge info" style="margin-left:6px">ya con CxP</span>':''}
      </td>
      <td>
        <button data-a="cxp" data-id="${x.id_rendicion}" ${x.total_aprobado<=0 || x.ya_reposicionada ? 'disabled':''}>Generar CxP</button>
      </td>
    `;
    tb.appendChild(tr);
  });
  kpiCant.textContent = String(rows.length);
  kpiTotal.textContent = fmt(total);
}

document.getElementById('btn-reload').addEventListener('click', loadList);

/* Modal */
const M = {
  root: document.getElementById('m'),
  info: document.getElementById('m-info'),
  form: document.getElementById('m-form'),
  err: document.getElementById('m-err'),
  id: null,
  moneda: '',
  total: 0,
  nombre_ff: ''
};
document.getElementById('m-x').addEventListener('click', ()=>M.root.classList.remove('active'));
document.getElementById('m-cancel').addEventListener('click', ()=>M.root.classList.remove('active'));

tb.addEventListener('click', async (e)=>{
  const btn = e.target.closest('button[data-a="cxp"]');
  if (!btn) return;
  const id = Number(btn.dataset.id);
  try{
    // Traer detalle para mostrar totales y moneda
    const r = await fetch(`${API}?id_rendicion=${id}`, {credentials:'same-origin'});
    const j = await r.json();
    if (!j.ok) throw new Error(j.error||'No se pudo cargar');
    M.id = id;
    M.total = Number(j.total_aprobado||0);
    M.moneda = j.rendicion?.moneda || '';
    M.nombre_ff = j.rendicion?.nombre_ff || '';
    const hoy = new Date().toISOString().slice(0,10);
    M.form.reset();
    M.form.fecha_emision.value = hoy;
    M.form.fecha_venc.value = hoy;
    M.info.innerHTML = `
      <b>Rendición #${id}</b> — ${esc(M.nombre_ff)}
      <br/>Total aprobado: <b>${esc(M.moneda)} ${fmt(M.total)}</b>
    `;
    M.err.textContent='';
    M.root.classList.add('active');
  }catch(err){ alert(err.message); }
});

document.getElementById('m-ok').addEventListener('click', async ()=>{
  M.err.textContent='';
  if (!M.id) { M.err.textContent='Falta rendición.'; return; }
  const f = new FormData(M.form);
  const payload = {
    accion: 'crear_cxp', // <-- coincide con la API
    id_rendicion: M.id,
    fecha_emision: f.get('fecha_emision'),
    fecha_venc: f.get('fecha_venc') || f.get('fecha_emision'),
    observacion: f.get('observacion')||''
  };
  if (!payload.fecha_emision || !payload.fecha_venc){
    M.err.textContent = 'Completá las fechas.';
    return;
  }
  try{
    const r = await fetch(API, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify(payload)
    });
    const j = await r.json();
    if (!j.ok) throw new Error(j.error||'No se pudo crear la CxP');
    M.root.classList.remove('active');
    alert(`CxP #${j.id_cxp} creada por ${j.moneda} ${fmt(j.total)}.`);
    loadList();
  }catch(err){ M.err.textContent = err.message; }
});

/* init */
loadList();
</script>
</body>
</html>
