<?php
session_start();
require_once __DIR__ . '../../../../conexion/configv2.php';

if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /login.php'); exit;
}

/* Proveedores activos: para datalist al cargar ítems */
function q($conn, $sql, $params = []) {
  $res = pg_query_params($conn, $sql, $params);
  if (!$res) return [];
  $out = [];
  while ($r = pg_fetch_assoc($res)) $out[] = $r;
  return $out;
}

$proveedores = q(
  $conn,
  "SELECT id_proveedor, nombre, COALESCE(ruc,'') AS ruc
   FROM public.proveedores
   WHERE estado='Activo'
   ORDER BY nombre ASC"
);

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<title>Fondo Fijo · Rendiciones</title>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<style>
:root{
  --bg:#f6f7fb; --card:#fff; --line:#e5e7eb; --muted:#6b7280; --primary:#2563eb; --ok:#059669; --danger:#dc2626; --warn:#b45309;
  --ink:#111827;
  font-family:"Segoe UI",system-ui,Arial,sans-serif;
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink)}
header{background:#fff;border-bottom:1px solid var(--line);padding:14px 20px;display:flex;gap:12px;align-items:center}
header h1{margin:0;font-size:18px}
main{max-width:1280px;margin:0 auto;padding:18px 20px}
button{font:inherit;font-weight:600;padding:9px 12px;border-radius:10px;border:1px solid var(--line);background:#fff;cursor:pointer}
button.primary{background:var(--primary);color:#fff;border-color:var(--primary)}
button.danger{background:var(--danger);color:#fff;border-color:var(--danger)}
button[disabled]{opacity:.6;cursor:not-allowed}
.filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px}
label{font-size:12px;color:var(--muted);display:grid;gap:6px}
select,input{font:inherit;border:1px solid var(--line);border-radius:8px;padding:8px;background:#fff}
.tablewrap{background:var(--card);border:1px solid var(--line);border-radius:12px;overflow:hidden;margin-top:14px}
table{width:100%;border-collapse:collapse}
thead{background:#eef2ff}
th,td{padding:10px 12px;border-bottom:1px solid var(--line);font-size:14px;text-align:left;vertical-align:middle}
tbody tr:hover{background:#f9fafb}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:700}
.badge.revision{background:#fef3c7;color:#92400e}
.badge.aprobada{background:#dcfce7;color:#166534}
.badge.parcial{background:#e0e7ff;color:#3730a3}
.empty{padding:16px;text-align:center;color:var(--muted)}
.modal-backdrop{position:fixed;inset:0;background:rgba(2,6,23,.5);display:none;align-items:flex-start;justify-content:center;padding:40px 16px;z-index:40}
.modal-backdrop.active{display:flex}
.modal{background:#fff;border:1px solid var(--line);border-radius:12px;max-width:1100px;width:100%;box-shadow:0 24px 60px rgba(2,6,23,.25);overflow:hidden}
.modal header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--line)}
.modal header h2{margin:0;font-size:16px}
.modal header button{border:none;background:none;font-size:22px;cursor:pointer;color:#6b7280}
.modal .body{padding:14px 16px;display:grid;gap:12px}
.modal .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
.modal footer{padding:12px 16px;border-top:1px solid var(--line);display:flex;justify-content:flex-end;gap:8px}
.info{font-size:13px;color:#1e3a8a;background:#eef2ff;border:1px solid #c7d2fe;padding:10px;border-radius:8px}
.err{color:#b91c1c;font-size:13px}
.small{font-size:12px;color:#64748b}
.tblmini th,.tblmini td{font-size:13px;padding:6px 8px}
.tblmini thead{background:#f8fafc}
.right{text-align:right}
.nowrap{white-space:nowrap}
input.tnum{width:120px}
input.tdoc{width:180px}
select.tsel{min-width:120px}
</style>
</head>
<body>
<header>
  <h1>Fondo Fijo · Rendiciones</h1>
  <div style="margin-left:auto;display:flex;gap:8px">
    <button id="btn-new" class="primary">Nueva rendición</button>
    <button id="btn-reload">Actualizar</button>
  </div>
</header>

<main>
  <section class="filters" id="filters">
    <label>Caja FF
      <select name="id_ff" id="f-id-ff">
        <option value="">Todas</option>
      </select>
    </label>
    <label>Estado
      <select name="estado" id="f-estado">
        <option value="">Todos</option>
        <option value="En revisión">En revisión</option>
        <option value="Aprobada">Aprobada</option>
        <option value="Parcial">Parcial</option>
      </select>
    </label>
    <label>Buscar
      <input type="text" name="q" id="f-q" placeholder="Nombre caja / observación">
    </label>
    <div style="display:flex;align-items:end;gap:8px;flex-wrap:wrap">
      <button class="primary" id="btn-apply">Aplicar filtros</button>
      <button id="btn-clear">Limpiar</button>
    </div>
  </section>

  <section class="tablewrap">
    <table id="tbl">
      <thead>
        <tr>
          <th>#</th>
          <th>Caja FF</th>
          <th>Estado</th>
          <th>Observación</th>
          <th>Creado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <div id="empty" class="empty" style="display:none">Sin resultados</div>
  </section>
</main>

<!-- Modal: Nueva Rendición -->
<div class="modal-backdrop" id="new-modal">
  <div class="modal">
    <header>
      <h2>Nueva rendición</h2>
      <button type="button" id="new-close">&times;</button>
    </header>
    <div class="body">
      <div class="info">Creará una rendición en estado <b>En revisión</b>. Luego podrás cargar comprobantes y aprobar en lote.</div>
      <form id="new-form" class="grid">
        <label>Caja FF
          <select name="id_ff" id="new-id-ff" required></select>
        </label>
        <label style="grid-column:1/-1">Observación
          <input type="text" name="observacion" maxlength="250" placeholder="Opcional">
        </label>
        <div class="err" id="new-err"></div>
      </form>
    </div>
    <footer>
      <button type="button" id="new-cancel">Cancelar</button>
      <button type="button" class="primary" id="new-save">Crear</button>
    </footer>
  </div>
</div>

<!-- Modal: Detalle Rendición -->
<div class="modal-backdrop" id="detail-modal">
  <div class="modal">
    <header>
      <h2 id="d-title">Rendición</h2>
      <button type="button" id="d-close">&times;</button>
    </header>
    <div class="body">
      <div id="d-head" class="grid"></div>
      <div>
        <h3 style="margin:6px 0">Items (comprobantes)</h3>
        <table class="tblmini" id="d-items">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Proveedor</th>
              <th>Tipo</th>
              <th>N° Doc</th>
              <th class="right">Grav 10</th>
              <th class="right">IVA 10</th>
              <th class="right">Grav 5</th>
              <th class="right">IVA 5</th>
              <th class="right">Exentas</th>
              <th class="right">Total</th>
              <th>Estado</th>
              <th>Obs</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div class="empty" id="d-empty" style="display:none">Sin ítems</div>
      </div>
      <div id="d-actions" style="display:flex;gap:8px;justify-content:flex-end"></div>
      <div class="err" id="d-err"></div>
    </div>
  </div>
</div>

<!-- Modal: Agregar Ítems (lote) -->
<div class="modal-backdrop" id="items-modal">
  <div class="modal">
    <header>
      <h2>Agregar comprobantes</h2>
      <button type="button" id="items-close">&times;</button>
    </header>
    <div class="body">
      <div class="small">Cargá varias filas y <b>Guardá</b> para enviar en lote. Podés usar el buscador de proveedor (datalist).</div>

      <!-- datalist de proveedores -->
      <datalist id="dl-proveedores">
        <?php foreach ($proveedores as $p): ?>
          <option value="<?= (int)$p['id_proveedor'] ?> · <?= htmlspecialchars($p['nombre']) ?>"><?= htmlspecialchars($p['ruc']) ?></option>
        <?php endforeach; ?>
      </datalist>

      <div style="overflow:auto">
        <table class="tblmini" id="items-grid">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Proveedor (ID · Nombre)</th>
              <th>RUC</th>
              <th>Tipo</th>
              <th>N° Doc</th>
              <th>Timbrado</th>
              <th class="right">Grav 10</th>
              <th class="right">IVA 10</th>
              <th class="right">Grav 5</th>
              <th class="right">IVA 5</th>
              <th class="right">Exentas</th>
              <th class="right">Total</th>
              <th>Obs</th>
              <th class="nowrap">Acción</th>
            </tr>
          </thead>
          <tbody id="items-rows"></tbody>
        </table>
      </div>

      <div style="margin-top:8px;display:flex;gap:8px">
        <button id="row-add">+ Agregar fila</button>
        <div class="err" id="items-err"></div>
      </div>
    </div>
    <footer>
      <button type="button" id="items-cancel">Cancelar</button>
      <button type="button" class="primary" id="items-save">Guardar ítems</button>
    </footer>
  </div>
</div>

<!-- Modal: Aprobación en lote -->
<div class="modal-backdrop" id="aprob-modal">
  <div class="modal">
    <header>
      <h2 id="aprob-title">Aprobación en lote</h2>
      <button type="button" id="aprob-close">&times;</button>
    </header>
    <div class="body">
      <div class="info">Marcá por fila si se <b>Aprueba</b> y si “<i>Imputa a libro</i>”. Las no marcadas se rechazan; podés indicar motivo.</div>
      <table class="tblmini" id="aprob-grid">
        <thead>
          <tr>
            <th>Aprueba</th>
            <th>Libro</th>
            <th>Fecha</th>
            <th>Proveedor</th>
            <th>Tipo</th>
            <th>N° Doc</th>
            <th class="right">Total</th>
            <th>Motivo rechazo</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      <div class="err" id="aprob-err"></div>
    </div>
    <footer>
      <button type="button" id="aprob-cancel">Cancelar</button>
      <button type="button" class="primary" id="aprob-save">Aplicar</button>
    </footer>
  </div>
</div>

<script>
const API = '../rendicion/rendiciones_ff_api.php';
const FF_API = '../asignacion_ff/asignaciones_ff_api.php';

const tbody = document.querySelector('#tbl tbody');
const empty = document.getElementById('empty');

function esc(s){return (s??'').toString().replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
function fmt(n){return Number(n||0).toLocaleString('es-PY',{minimumFractionDigits:0,maximumFractionDigits:2});}
function badge(estado){
  const cls = estado==='Aprobada'?'aprobada':(estado==='Parcial'?'parcial':'revision');
  return `<span class="badge ${cls}">${esc(estado)}</span>`;
}

/* cargar Cajas FF activas (para filtros y para crear rendición) */
async function loadFFSelects(){
  const r = await fetch(`${FF_API}?estado=Activo`, {credentials:'same-origin'});
  const j = await r.json();
  if (!j.ok) throw new Error(j.error||'No se pudo cargar Cajas FF');
  const rows = j.data||[];
  const filterSel = document.getElementById('f-id-ff');
  const newSel    = document.getElementById('new-id-ff');

  filterSel.innerHTML = `<option value="">Todas</option>`;
  newSel.innerHTML    = `<option value="">Seleccioná…</option>`;
  rows.forEach(x=>{
    const label = (x.nombre_caja || x.nombre || `FF #${x.id_ff}`) + (x.responsable? ` — ${x.responsable}` : (x.responsable_nombre? ` — ${x.responsable_nombre}`:'')); // según API de FF
    const opt1 = document.createElement('option');
    opt1.value = x.id_ff; opt1.textContent = label;
    filterSel.appendChild(opt1);

    const opt2 = document.createElement('option');
    opt2.value = x.id_ff; opt2.textContent = label;
    newSel.appendChild(opt2);
  });
}

/* listado */
async function loadList(){
  const qs = new URLSearchParams();
  const id_ff = document.getElementById('f-id-ff').value.trim();
  const estado= document.getElementById('f-estado').value.trim();
  const q     = document.getElementById('f-q').value.trim();
  if (id_ff) qs.set('id_ff', id_ff);
  if (estado)qs.set('estado', estado);
  if (q)     qs.set('q', q);

  const r = await fetch(`${API}?${qs.toString()}`, {credentials:'same-origin'});
  const j = await r.json();
  if (!j.ok) { alert(j.error||'Error al listar'); return; }
  renderRows(j.data||[]);
}
function renderRows(rows){
  tbody.innerHTML='';
  if (!rows.length){ empty.style.display='block'; return; }
  empty.style.display='none';
  rows.forEach(r=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>#${r.id_rendicion}</td>
      <td>${esc(r.nombre_ff||r.nombre_caja||'')}</td>
      <td>${badge(r.estado)}</td>
      <td>${esc(r.observacion||'')}</td>
      <td>${esc(r.created_at||'')}</td>
      <td style="display:flex;gap:6px;flex-wrap:wrap">
        <button data-a="ver" data-id="${r.id_rendicion}">Ver</button>
        ${r.estado==='En revisión'
          ? `<button data-a="items" data-id="${r.id_rendicion}">Agregar ítems</button>
             <button data-a="aprobar" class="primary" data-id="${r.id_rendicion}">Aprobar/Rechazar</button>`
          : ''
        }
      </td>
    `;
    tbody.appendChild(tr);
  });
}

/* Filtros */
document.getElementById('btn-apply').addEventListener('click', loadList);
document.getElementById('btn-clear').addEventListener('click', ()=>{
  document.getElementById('f-id-ff').value='';
  document.getElementById('f-estado').value='';
  document.getElementById('f-q').value='';
  loadList();
});
document.getElementById('btn-reload').addEventListener('click', loadList);

/* Nueva rendición */
const newModal = document.getElementById('new-modal');
const newErr   = document.getElementById('new-err');
document.getElementById('btn-new').addEventListener('click', async ()=>{
  newErr.textContent=''; newModal.classList.add('active');
});
document.getElementById('new-close').addEventListener('click', ()=>newModal.classList.remove('active'));
document.getElementById('new-cancel').addEventListener('click', ()=>newModal.classList.remove('active'));
document.getElementById('new-save').addEventListener('click', async ()=>{
  newErr.textContent='';
  const id_ff = Number(document.getElementById('new-id-ff').value||0);
  const obs   = document.querySelector('#new-form [name="observacion"]').value||'';
  if (!id_ff){ newErr.textContent='Seleccioná una Caja FF.'; return; }
  try{
    const r = await fetch(API, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify({ accion:'crear', id_ff, observacion: obs })
    });
    const j = await r.json();
    if (!j.ok) throw new Error(j.error||'No se pudo crear');
    newModal.classList.remove('active');
    loadList();
  }catch(err){ newErr.textContent = err.message; }
});

/* Detalle rendición */
const dModal = document.getElementById('detail-modal');
const dTitle = document.getElementById('d-title');
const dHead  = document.getElementById('d-head');
const dBody  = document.querySelector('#d-items tbody');
const dEmpty = document.getElementById('d-empty');
const dErr   = document.getElementById('d-err');
const dActions = document.getElementById('d-actions');
let currentR = null;

async function openDetail(id){
  dErr.textContent=''; currentR = id;
  try{
    const r = await fetch(`${API}?id_rendicion=${id}`, {credentials:'same-origin'});
    const j = await r.json();
    if (!j.ok) throw new Error(j.error||'No se pudo cargar');
    const { rendicion, items } = j;
    dTitle.textContent = `Rendición #${rendicion.id_rendicion} — ${rendicion.nombre_ff||''}`;
    dHead.innerHTML = `
      <div><div class="small">Caja FF</div><div><b>${esc(rendicion.nombre_ff||'')}</b></div></div>
      <div><div class="small">Estado</div><div>${badge(rendicion.estado)}</div></div>
      <div><div class="small">Creado</div><div>${esc(rendicion.created_at||'')}</div></div>
      <div style="grid-column:1/-1"><div class="small">Obs</div><div>${esc(rendicion.observacion||'-')}</div></div>
    `;

    dBody.innerHTML='';
    if (!items || !items.length){ dEmpty.style.display='block'; }
    else {
      dEmpty.style.display='none';
      items.forEach(it=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${esc(it.fecha||'')}</td>
          <td>${esc(it.id_proveedor?('#'+it.id_proveedor):'')} ${esc(it.ruc||'')}</td>
          <td>${esc(it.documento_tipo||'')}</td>
          <td>${esc(it.numero_documento||'')}</td>
          <td class="right">${fmt(it.gravada_10)}</td>
          <td class="right">${fmt(it.iva_10)}</td>
          <td class="right">${fmt(it.gravada_5)}</td>
          <td class="right">${fmt(it.iva_5)}</td>
          <td class="right">${fmt(it.exentas)}</td>
          <td class="right"><b>${fmt(it.total)}</b></td>
          <td>${esc(it.estado_item||'')}</td>
          <td>${esc(it.observacion||'')}</td>
        `;
        dBody.appendChild(tr);
      });
    }

    dActions.innerHTML='';
    if (rendicion.estado === 'En revisión'){
      const b1 = document.createElement('button');
      b1.textContent = 'Agregar ítems';
      b1.addEventListener('click', ()=>openItemsModal(rendicion.id_rendicion));
      const b2 = document.createElement('button');
      b2.className = 'primary';
      b2.textContent = 'Aprobar/Rechazar';
      b2.addEventListener('click', ()=>openAprobModal(rendicion.id_rendicion));
      dActions.append(b1,b2);
    }

    dModal.classList.add('active');
  }catch(err){ alert(err.message); }
}
document.getElementById('d-close').addEventListener('click', ()=>dModal.classList.remove('active'));

/* Acciones de fila */
tbody.addEventListener('click', (e)=>{
  const btn = e.target.closest('button[data-a]');
  if (!btn) return;
  const id = Number(btn.dataset.id);
  const a  = btn.dataset.a;
  if (a==='ver') openDetail(id);
  if (a==='items') openItemsModal(id);
  if (a==='aprobar') openAprobModal(id);
});

/* Modal Ítems (lote) */
const itemsModal = document.getElementById('items-modal');
const itemsErr   = document.getElementById('items-err');
const itemsBody  = document.getElementById('items-rows');
let itemsRend = null;

function newRow(){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input type="date" class="tdate" required></td>
    <td><input list="dl-proveedores" class="tprov" placeholder="ID · Nombre (opcional)"></td>
    <td><input type="text" class="truc" placeholder="RUC"></td>
    <td>
      <select class="tsel tdoc">
        <option value="FACT">FACT</option>
        <option value="TICKET">TICKET</option>
        <option value="RECIBO">RECIBO</option>
      </select>
    </td>
    <td><input type="text" class="tnum" placeholder="001-001-0000000" /></td>
    <td><input type="text" class="ttimb" placeholder="Timbrado"></td>
    <td class="right"><input type="number" step="0.01" class="tnum g10"></td>
    <td class="right"><input type="number" step="0.01" class="tnum i10"></td>
    <td class="right"><input type="number" step="0.01" class="tnum g5"></td>
    <td class="right"><input type="number" step="0.01" class="tnum i5"></td>
    <td class="right"><input type="number" step="0.01" class="tnum ex"></td>
    <td class="right"><input type="number" step="0.01" class="tnum tot" required></td>
    <td><input type="text" class="tobs" placeholder="Obs"></td>
    <td><button type="button" class="danger row-del">Quitar</button></td>
  `;
  return tr;
}

function openItemsModal(id){
  itemsRend = id;
  itemsErr.textContent='';
  itemsBody.innerHTML='';
  // fila inicial
  itemsBody.appendChild(newRow());
  itemsModal.classList.add('active');
}
document.getElementById('items-close').addEventListener('click', ()=>itemsModal.classList.remove('active'));
document.getElementById('items-cancel').addEventListener('click', ()=>itemsModal.classList.remove('active'));
document.getElementById('row-add').addEventListener('click', ()=>itemsBody.appendChild(newRow()));
itemsBody.addEventListener('click', (e)=>{
  const b = e.target.closest('.row-del');
  if (!b) return;
  const tr = b.closest('tr');
  tr.remove();
});

document.getElementById('items-save').addEventListener('click', async ()=>{
  itemsErr.textContent='';
  const rows = Array.from(itemsBody.querySelectorAll('tr'));
  if (!rows.length){ itemsErr.textContent='Agregá al menos una fila.'; return; }

  const items = [];
  for (const tr of rows){
    const fecha = tr.querySelector('.tdate').value;
    const prov  = tr.querySelector('.tprov').value.trim();
    const id_proveedor = /^\d+/.test(prov) ? Number(prov.split('·')[0].trim()) : 0;
    const ruc   = tr.querySelector('.truc').value.trim();
    const tipo  = tr.querySelector('.tdoc').value;
    const nro   = tr.querySelector('.tnum').value.trim();
    const tim   = tr.querySelector('.ttimb').value.trim();
    const g10   = parseFloat(tr.querySelector('.g10').value||'0');
    const i10   = parseFloat(tr.querySelector('.i10').value||'0');
    const g5    = parseFloat(tr.querySelector('.g5').value||'0');
    const i5    = parseFloat(tr.querySelector('.i5').value||'0');
    const ex    = parseFloat(tr.querySelector('.ex').value||'0');
    const tot   = parseFloat(tr.querySelector('.tot').value||'0');
    const obs   = tr.querySelector('.tobs').value.trim();
    if (!fecha || !nro || tot<=0){ itemsErr.textContent='Cada fila requiere Fecha, Nº Doc y Total>0'; return; }
    items.push({
      fecha, id_proveedor, ruc, documento_tipo: tipo, numero_documento: nro, timbrado_numero: tim,
      gravada_10: g10, iva_10: i10, gravada_5: g5, iva_5: i5, exentas: ex, total: tot, observacion: obs
    });
  }

  try{
    const r = await fetch(API, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify({ accion:'agregar_items', id_rendicion: itemsRend, items })
    });
    const j = await r.json();
    if (!j.ok) throw new Error(j.error||'No se pudo guardar ítems');
    itemsModal.classList.remove('active');
    // refrescar detalle si está abierto
    if (document.getElementById('detail-modal').classList.contains('active')) openDetail(itemsRend);
    else loadList();
  }catch(err){ itemsErr.textContent = err.message; }
});

/* Modal Aprobación */
const aprobModal = document.getElementById('aprob-modal');
const aprobErr   = document.getElementById('aprob-err');
const aprobGrid  = document.querySelector('#aprob-grid tbody');
let aprobRend = null;
let aprobItems = [];

async function openAprobModal(id){
  aprobErr.textContent=''; aprobGrid.innerHTML=''; aprobRend = id;
  // Traer detalle
  const r = await fetch(`${API}?id_rendicion=${id}`, {credentials:'same-origin'});
  const j = await r.json();
  if (!j.ok){ alert(j.error||'No se pudo cargar'); return; }
  const items = (j.items||[]).filter(x=>x.estado_item==='Pendiente');
  if (!items.length){ alert('No hay ítems pendientes'); return; }

  document.getElementById('aprob-title').textContent = `Aprobación #${id} — ${j.rendicion?.nombre_ff||''}`;

  aprobItems = items.map(x=>({
    id_item: x.id_item,
    aprobar: true,          // default: aprobar
    imputa_libro: false,    // default: no
    motivo_rechazo: ''
  }));

  items.forEach((it, idx)=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td style="text-align:center"><input type="checkbox" class="apb" data-i="${idx}" checked></td>
      <td style="text-align:center"><input type="checkbox" class="lib" data-i="${idx}" ${''}></td>
      <td>${esc(it.fecha||'')}</td>
      <td>${esc(it.id_proveedor?('#'+it.id_proveedor):'')} ${esc(it.ruc||'')}</td>
      <td>${esc(it.documento_tipo||'')}</td>
      <td>${esc(it.numero_documento||'')}</td>
      <td class="right"><b>${fmt(it.total)}</b></td>
      <td><input type="text" class="mot" data-i="${idx}" placeholder="Motivo si rechaza" /></td>
    `;
    aprobGrid.appendChild(tr);
  });

  aprobModal.classList.add('active');
}
document.getElementById('aprob-close').addEventListener('click', ()=>aprobModal.classList.remove('active'));
document.getElementById('aprob-cancel').addEventListener('click', ()=>aprobModal.classList.remove('active'));

aprobGrid.addEventListener('change', (e)=>{
  const cbA = e.target.closest('.apb');
  const cbL = e.target.closest('.lib');
  const inM = e.target.closest('.mot');
  if (cbA){
    const i = Number(cbA.dataset.i);
    aprobItems[i].aprobar = cbA.checked;
    if (!cbA.checked){
      aprobItems[i].imputa_libro = false;
      const rowLib = aprobGrid.querySelector(`.lib[data-i="${i}"]`);
      if (rowLib) rowLib.checked = false;
    }
  }
  if (cbL){
    const i = Number(cbL.dataset.i);
    aprobItems[i].imputa_libro = cbL.checked;
    if (cbL.checked){
      const rowApb = aprobGrid.querySelector(`.apb[data-i="${i}"]`);
      if (rowApb && !rowApb.checked){ rowApb.checked = true; aprobItems[i].aprobar = true; }
    }
  }
  if (inM){
    const i = Number(inM.dataset.i);
    aprobItems[i].motivo_rechazo = inM.value||'';
  }
});

document.getElementById('aprob-save').addEventListener('click', async ()=>{
  aprobErr.textContent='';
  try{
    const r = await fetch(`${API}?id_rendicion=${aprobRend}`, {
      method:'PATCH',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify({ accion:'aprobar_lote', all_or_nothing:false, items: aprobItems })
    });
    const j = await r.json();
    if (!j.ok) throw new Error(j.error||'No se pudo aplicar');
    aprobModal.classList.remove('active');
    // refrescar detalle si está
    if (document.getElementById('detail-modal').classList.contains('active')) openDetail(aprobRend);
    loadList();
  }catch(err){ aprobErr.textContent = err.message; }
});

/* init */
(async function(){
  try{
    await loadFFSelects();
  }catch(e){ console.warn(e); }
  document.getElementById('btn-apply').click();
})();
</script>
</body>
</html>
