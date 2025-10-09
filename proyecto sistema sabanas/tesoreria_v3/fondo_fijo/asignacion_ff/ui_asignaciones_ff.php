<?php
session_start();
require_once __DIR__ . '../../../../conexion/configv2.php';

if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /login.php');
  exit;
}

function q($conn, $sql, $params = []) {
  $res = pg_query_params($conn, $sql, $params);
  if (!$res) return [];
  $out = [];
  while ($r = pg_fetch_assoc($res)) $out[] = $r;
  return $out;
}

/* Proveedores FF para crear nuevas cajas */
$proveedoresFF = q(
  $conn,
  "SELECT id_proveedor, nombre, ruc
   FROM public.proveedores
   WHERE estado='Activo' AND tipo='FONDO_FIJO'
   ORDER BY nombre"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<title>Cajas de Fondo Fijo</title>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<style>
:root{
  --bg:#f6f7fb; --card:#fff; --line:#e5e7eb; --muted:#6b7280; --primary:#2563eb; --ok:#059669; --danger:#dc2626;
  --ink:#111827;
  font-family:"Segoe UI",system-ui,Arial,sans-serif;
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink)}
header{background:#fff;border-bottom:1px solid var(--line);padding:14px 20px;display:flex;gap:12px;align-items:center}
header h1{margin:0;font-size:18px}
main{max-width:1200px;margin:0 auto;padding:18px 20px}
button{font:inherit;font-weight:600;padding:9px 12px;border-radius:10px;border:1px solid var(--line);background:#fff;cursor:pointer}
button.primary{background:var(--primary);color:#fff;border-color:var(--primary)}
button.danger{background:var(--danger);color:#fff;border-color:var(--danger)}
button[disabled]{opacity:.6;cursor:not-allowed}
.filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px}
label{font-size:12px;color:var(--muted);display:grid;gap:6px}
select,input{font:inherit;border:1px solid var(--line);border-radius:8px;padding:8px;background:#fff}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:14px 0}
.kpi{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px}
.kpi .h{color:var(--muted);font-size:12px}
.kpi .v{font-size:22px;font-weight:700;margin-top:4px}
.tablewrap{background:var(--card);border:1px solid var(--line);border-radius:12px;overflow:hidden}
table{width:100%;border-collapse:collapse}
thead{background:#eef2ff}
th,td{padding:10px 12px;border-bottom:1px solid var(--line);font-size:14px;text-align:left;vertical-align:middle}
tbody tr:hover{background:#f9fafb}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:700}
.badge.activo{background:#dcfce7;color:#166534}
.badge.cerrado{background:#fee2e2;color:#991b1b}
.empty{padding:16px;text-align:center;color:#64748b}
.modal-backdrop{position:fixed;inset:0;background:rgba(2,6,23,.5);display:none;align-items:flex-start;justify-content:center;padding:40px 16px;z-index:40}
.modal-backdrop.active{display:flex}
.modal{background:#fff;border:1px solid var(--line);border-radius:12px;max-width:960px;width:100%;box-shadow:0 24px 60px rgba(2,6,23,.25);overflow:hidden}
.modal header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--line)}
.modal header h2{margin:0;font-size:16px}
.modal header button{border:none;background:none;font-size:22px;cursor:pointer;color:#6b7280}
.modal .body{padding:14px 16px;display:grid;gap:12px}
.modal .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
.modal footer{padding:12px 16px;border-top:1px solid var(--line);display:flex;justify-content:flex-end;gap:8px}
.info{font-size:13px;color:#1e3a8a;background:#eef2ff;border:1px solid #c7d2fe;padding:10px;border-radius:8px}
.err{color:#b91c1c;font-size:13px}
.table-sub{width:100%;border-collapse:collapse;border:1px solid var(--line);border-radius:8px;overflow:hidden}
.table-sub th,.table-sub td{font-size:13px;padding:8px;border-bottom:1px solid var(--line)}
.right{text-align:right}
.small{font-size:12px;color:#6b7280}
</style>
</head>
<body>
<header>
  <h1>Cajas de Fondo Fijo</h1>
  <div style="margin-left:auto;display:flex;gap:8px">
    <button id="btn-new-caja" class="primary">Nueva caja</button>
    <button id="btn-reload">Actualizar</button>
  </div>
</header>

<main>
  <section class="filters" id="filters">
    <label>Responsable (Proveedor FF)
      <select name="id_proveedor">
        <option value="">Todos</option>
        <?php foreach($proveedoresFF as $p): ?>
          <option value="<?= (int)$p['id_proveedor'] ?>">
            <?= htmlspecialchars($p['nombre']) ?> (RUC <?= htmlspecialchars($p['ruc']??'-') ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Estado
      <select name="estado">
        <option value="">Todos</option>
        <option value="Activo">Activo</option>
        <option value="Cerrado">Cerrado</option>
      </select>
    </label>
    <label>Moneda
      <select name="moneda">
        <option value="">Todas</option>
        <option value="PYG">PYG</option>
        <option value="USD">USD</option>
      </select>
    </label>
    <label>Buscar por nombre de caja
      <input type="text" name="q" placeholder="Ej: Caja Suc. Centro">
    </label>
    <div style="display:flex;align-items:end;gap:8px;flex-wrap:wrap">
      <button class="primary" id="btn-apply">Aplicar filtros</button>
      <button id="btn-clear">Limpiar</button>
    </div>
  </section>

  <section class="kpis">
    <div class="kpi">
      <div class="h">Cajas activas</div>
      <div class="v" id="kpi-open">-</div>
    </div>
    <div class="kpi">
      <div class="h">Saldo total (por movimientos) de activas</div>
      <div class="v" id="kpi-saldo">-</div>
    </div>
  </section>

  <section class="tablewrap">
    <table id="tbl">
      <thead>
        <tr>
          <th># Caja</th>
          <th>Nombre / Responsable</th>
          <th>Moneda</th>
          <th class="right">Tope</th>
          <th class="right">Saldo (cache)</th>
          <th class="right">Saldo (mov)</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <div id="empty" class="empty" style="display:none">Sin resultados</div>
  </section>
</main>

<!-- Modal: NUEVA CAJA -->
<div class="modal-backdrop" id="caja-modal">
  <div class="modal">
    <header>
      <h2>Nueva caja</h2>
      <button type="button" id="caja-close">&times;</button>
    </header>
    <div class="body">
      <div class="info">
        Creará una <b>caja de Fondo Fijo</b> para un <b>proveedor responsable</b>.  
        Definí <i>Nombre de caja</i>, <i>Moneda</i> y <i>Tope</i>. El saldo inicia en 0.
      </div>
      <form id="caja-form" class="grid">
        <label>Proveedor responsable
          <select name="id_proveedor" required>
            <option value="">Seleccioná…</option>
            <?php foreach($proveedoresFF as $p): ?>
              <option value="<?= (int)$p['id_proveedor'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Nombre de caja
          <input type="text" name="nombre_caja" maxlength="120" required placeholder="Ej: Caja Suc. Centro">
        </label>
        <label>Moneda
          <select name="moneda" required>
            <option value="PYG">PYG</option>
            <option value="USD">USD</option>
          </select>
        </label>
        <label>Tope
          <input type="number" step="0.01" min="0" name="tope" required>
        </label>
        <label style="grid-column:1/-1">Observación
          <input type="text" name="observacion" maxlength="255" placeholder="Opcional">
        </label>
        <div class="err" id="caja-err"></div>
      </form>
    </div>
    <footer>
      <button type="button" id="caja-cancel">Cancelar</button>
      <button type="button" class="primary" id="caja-save">Crear caja</button>
    </footer>
  </div>
</div>

<!-- Modal: Detalle -->
<div class="modal-backdrop" id="detail-modal">
  <div class="modal">
    <header>
      <h2 id="d-title">Caja</h2>
      <button type="button" id="d-close">&times;</button>
    </header>
    <div class="body">
      <div class="grid" id="d-cards"></div>
      <div>
        <h3 style="margin:6px 0">Movimientos</h3>
        <table class="table-sub" id="d-movs">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Tipo</th>
              <th class="right">Signo</th>
              <th class="right">Monto</th>
              <th>Detalle</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div class="empty" id="d-movs-empty" style="display:none">Sin movimientos</div>
      </div>
      <div id="d-actions" style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px"></div>
      <div class="err" id="d-err"></div>
    </div>
  </div>
</div>

<script>
const API = '../asignacion_ff/asignaciones_ff_api.php';

const tbody   = document.querySelector('#tbl tbody');
const empty   = document.getElementById('empty');
const kpiOpen = document.getElementById('kpi-open');
const kpiSaldo= document.getElementById('kpi-saldo');

function fmt(n, cur='') {
  const num = Number(n||0).toLocaleString('es-PY',{minimumFractionDigits:0,maximumFractionDigits:2});
  return cur ? `${cur} ${num}` : num;
}
function esc(s){return (s??'').toString().replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
function badgeEstado(e){
  const cls = (e==='Activo')?'badge activo':'badge cerrado';
  return `<span class="${cls}">${esc(e)}</span>`;
}

/* Listado */
async function loadList(){
  const params = new URLSearchParams();
  document.querySelectorAll('#filters [name]').forEach(el=>{
    const v = (el.value||'').trim();
    if (v) params.set(el.name, v);
  });
  try{
    const r = await fetch(`${API}?${params.toString()}`, {credentials:'same-origin'});
    const j = await r.json();
    if(!j.ok) throw new Error(j.error||'Error al listar');
    renderRows(j.data||[]);
  }catch(err){ alert(err.message); }
}

async function fetchDetalle(id_ff){
  try{
    const r = await fetch(`${API}?id_ff=${id_ff}`, {credentials:'same-origin'});
    const j = await r.json();
    if(!j.ok) return null;
    return j; // { ff, movimientos }
  }catch{ return null; }
}

function renderRows(rows){
  tbody.innerHTML='';
  if(!rows.length){
    empty.style.display='block';
    kpiOpen.textContent='0';
    kpiSaldo.textContent='0';
    return;
  }
  empty.style.display='none';

  let activas = 0;
  let totalSaldoMovActivas = 0;
  const pending = [];

  rows.forEach(row=>{
    if (row.estado==='Activo') activas++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>#${row.id_ff}</td>
      <td>
        <div style="font-weight:600">${esc(row.nombre || row.nombre_caja || `FF #${row.id_ff}`)}</div>
        <div class="small">${esc(row.responsable || row.responsable_nombre || '')}</div>
      </td>
      <td>${esc(row.moneda||'')}</td>
      <td class="right">${fmt(row.monto_asignado ?? row.tope, row.moneda)}</td>
      <td class="right">${fmt(row.saldo_cache ?? row.saldo_actual, row.moneda)}</td>
      <td class="right" data-saldomov="${row.id_ff}"><span class="small">cargando…</span></td>
      <td>${badgeEstado(row.estado)}</td>
      <td style="display:flex;gap:6px;flex-wrap:wrap">
        <button data-a="ver" data-id="${row.id_ff}">Ver</button>
        ${row.estado==='Activo'
          ? `<button data-a="cerrar" data-id="${row.id_ff}" class="danger">Cerrar</button>`
          : `<button data-a="reabrir" data-id="${row.id_ff}">Reabrir</button>`}
      </td>
    `;
    tbody.appendChild(tr);
    pending.push({ id_ff: row.id_ff, moneda: row.moneda, estado: row.estado });
  });

  (async ()=>{
    for (const p of pending){
      const cell = tbody.querySelector(`[data-saldomov="${p.id_ff}"]`);
      const det  = await fetchDetalle(p.id_ff);
      const sm   = det?.ff?.saldo ?? det?.ff?.saldo_mov ?? null;
      cell.textContent = sm===null ? '-' : fmt(sm, p.moneda||'');
      if (p.estado==='Activo' && typeof sm === 'number') totalSaldoMovActivas += sm;
      kpiOpen.textContent = String(activas);
      kpiSaldo.textContent = fmt(totalSaldoMovActivas);
    }
  })();
}

/* Nueva CAJA */
const cajaModal = document.getElementById('caja-modal');
const cajaForm  = document.getElementById('caja-form');
const cajaErr   = document.getElementById('caja-err');

document.getElementById('btn-new-caja').addEventListener('click', ()=>{
  cajaForm.reset();
  cajaErr.textContent='';
  cajaModal.classList.add('active');
});
document.getElementById('caja-close').addEventListener('click', ()=>cajaModal.classList.remove('active'));
document.getElementById('caja-cancel').addEventListener('click', ()=>cajaModal.classList.remove('active'));

document.getElementById('caja-save').addEventListener('click', async ()=>{
  cajaErr.textContent='';
  const f = new FormData(cajaForm);
  const payload = {
    accion: 'crear_caja',
    id_proveedor: Number(f.get('id_proveedor')||0),
    nombre_caja: (f.get('nombre_caja')||'').trim(),
    moneda: f.get('moneda')||'PYG',
    tope: parseFloat(f.get('tope')||'0'),
    observacion: (f.get('observacion')||'').trim()
  };
  if (!payload.id_proveedor){ cajaErr.textContent='Seleccioná el responsable.'; return; }
  if (!payload.nombre_caja){ cajaErr.textContent='Indicá el nombre de la caja.'; return; }
  if (isNaN(payload.tope) || payload.tope < 0){ cajaErr.textContent='El tope debe ser >= 0.'; return; }

  try{
    const r = await fetch(API, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify(payload)
    });
    const j = await r.json();
    if(!j.ok) throw new Error(j.error||'No se pudo crear la caja');
    cajaModal.classList.remove('active');
    loadList();
  }catch(err){ cajaErr.textContent = err.message; }
});

/* Detalle / Cerrar / Reabrir */
const dModal = document.getElementById('detail-modal');
const dTitle = document.getElementById('d-title');
const dCards = document.getElementById('d-cards');
const dBody  = document.querySelector('#d-movs tbody');
const dEmpty = document.getElementById('d-movs-empty');
const dErr   = document.getElementById('d-err');
const dActions = document.getElementById('d-actions');

tbody.addEventListener('click', async (e)=>{
  const btn = e.target.closest('button[data-a]');
  if(!btn) return;
  const id = Number(btn.dataset.id);
  const a  = btn.dataset.a;
  if (a==='ver'){ openDetail(id); }
  if (a==='cerrar'){ await actionCaja('cerrar', id); }
  if (a==='reabrir'){ await actionCaja('reabrir', id); }
});

async function openDetail(id){
  dErr.textContent = '';
  try{
    const r = await fetch(`${API}?id_ff=${id}`, {credentials:'same-origin'});
    const j = await r.json();
    if(!j.ok) throw new Error(j.error||'No se pudo cargar');
    const { ff, movimientos } = j;

    dTitle.textContent = `Caja #${ff.id_ff} — ${ff.nombre_caja || ff.nombre || ''}`;
    const tope = ff.monto_asignado ?? ff.tope;
    const saldoCache = ff.saldo_cache ?? ff.saldo_actual;
    const saldoMov   = ff.saldo ?? ff.saldo_mov ?? saldoCache;

    dCards.innerHTML = `
      <div class="kpi"><div class="h">Estado</div><div class="v">${esc(ff.estado)}</div></div>
      <div class="kpi"><div class="h">Moneda</div><div class="v">${esc(ff.moneda||'')}</div></div>
      <div class="kpi"><div class="h">Tope</div><div class="v">${fmt(tope, ff.moneda)}</div></div>
      <div class="kpi"><div class="h">Saldo (cache)</div><div class="v">${fmt(saldoCache, ff.moneda)}</div></div>
      <div class="kpi"><div class="h">Saldo (mov)</div><div class="v">${fmt(saldoMov, ff.moneda)}</div></div>
      <div class="kpi"><div class="h">Responsable</div><div class="v">${esc(ff.responsable || ff.responsable_nombre || '-')}</div></div>
    `;

    dBody.innerHTML = '';
    if (!movimientos || !movimientos.length){
      dEmpty.style.display='block';
    } else {
      dEmpty.style.display='none';
      movimientos.forEach(m=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${esc(m.fecha||'')}</td>
          <td>${esc(m.tipo||'')}</td>
          <td class="right">${m.signo > 0 ? '+' : '-'}</td>
          <td class="right">${fmt(m.monto, ff.moneda)}</td>
          <td>${esc(m.descripcion||'')}</td>
        `;
        dBody.appendChild(tr);
      });
    }

    dActions.innerHTML = '';
    if (ff.estado === 'Activo'){
      const b = document.createElement('button');
      b.className = 'danger';
      b.textContent = 'Cerrar';
      b.addEventListener('click', ()=>actionCaja('cerrar', id));
      dActions.appendChild(b);
    } else {
      const b = document.createElement('button');
      b.textContent = 'Reabrir';
      b.addEventListener('click', ()=>actionCaja('reabrir', id));
      dActions.appendChild(b);
    }

    dModal.classList.add('active');
  }catch(err){ alert(err.message); }
}
document.getElementById('d-close').addEventListener('click', ()=>dModal.classList.remove('active'));

async function actionCaja(accion, id){
  try{
    const r = await fetch(API, {
      method:'PATCH',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify({ accion, id_ff: id })
    });
    const j = await r.json();
    if(!j.ok) throw new Error(j.error||`No se pudo ${accion}`);
    dModal.classList.remove('active');
    loadList();
  }catch(err){
    if (dModal.classList.contains('active')) dErr.textContent = err.message;
    else alert(err.message);
  }
}

/* init */
document.getElementById('btn-reload').addEventListener('click', loadList);
document.getElementById('btn-apply').addEventListener('click', loadList);
document.getElementById('btn-clear').addEventListener('click', ()=>{
  document.querySelectorAll('#filters [name]').forEach(el=>el.value='');
  loadList();
});
loadList();
</script>
</body>
</html>
