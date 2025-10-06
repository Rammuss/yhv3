<?php
/**
 * cajas_admin.php
 * UI + API en un solo archivo para administrar Cajas:
 * - Listar (con búsqueda)
 * - Crear / Editar
 * - Activar / Desactivar
 *
 * Requisitos:
 * - PostgreSQL
 * - Tabla "public.caja" igual a la que ya tenés
 * - Archivo de conexión: ../../conexion/configv2.php que exponga $conn (pg_connect)
 */

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header_remove('X-Powered-By'); // higiene

/* ---------------- Helpers ---------------- */
function json_out($data, $code = 200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function is_post(){ return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function body_json(){
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}

/* ---------------- Endpoints internos (mismo archivo) ---------------- */
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($action === 'list') {
  $q = $_GET['q'] ?? null;
  $activa = isset($_GET['activa']) ? ($_GET['activa'] === 'true' ? 't' : 'f') : null;
  $limit = (int)($_GET['limit'] ?? 50);
  $offset = (int)($_GET['offset'] ?? 0);

  $sql = "SELECT id_caja, nombre, id_sucursal, activa, observacion, creado_en, actualizado_en
          FROM public.caja
          WHERE ($1::text IS NULL OR lower(nombre) LIKE lower('%' || $1 || '%'))
            AND ($2::boolean IS NULL OR activa = $2)
          ORDER BY nombre ASC
          LIMIT $3 OFFSET $4";
  $res = pg_query_params($conn, $sql, [$q, $activa, $limit, $offset]);
  if (!$res) json_out(['ok'=>false, 'error'=>pg_last_error($conn)], 500);

  $rows = pg_fetch_all($res) ?: [];
  json_out(['ok'=>true, 'items'=>$rows]);
}

if ($action === 'save' && is_post()) {
  $in = body_json();
  $id = $in['id_caja'] ?? null;
  $nombre = trim($in['nombre'] ?? '');
  $id_suc = $in['id_sucursal'] ?? null;
  $obs = $in['observacion'] ?? null;

  if ($nombre === '') json_out(['ok'=>false, 'error'=>'El nombre es requerido.'], 400);

  if ($id) {
    $sql = "UPDATE public.caja
            SET nombre=$2, id_sucursal=$3, observacion=$4, actualizado_en=now()
            WHERE id_caja=$1
            RETURNING id_caja";
    $res = pg_query_params($conn, $sql, [$id, $nombre, $id_suc, $obs]);
  } else {
    $sql = "INSERT INTO public.caja (nombre, id_sucursal, observacion)
            VALUES ($1,$2,$3)
            RETURNING id_caja";
    $res = pg_query_params($conn, $sql, [$nombre, $id_suc, $obs]);
  }
  if (!$res) json_out(['ok'=>false, 'error'=>pg_last_error($conn)], 500);
  $row = pg_fetch_assoc($res);
  json_out(['ok'=>true, 'id_caja'=>$row['id_caja'] ?? null]);
}

if ($action === 'toggle' && is_post()) {
  $in = body_json();
  $id = $in['id_caja'] ?? null;
  $activa = $in['activa'] ?? null;
  if (!$id || !is_bool($activa)) json_out(['ok'=>false, 'error'=>'Parámetros inválidos.'], 400);

  $sql = "UPDATE public.caja SET activa=$2, actualizado_en=now() WHERE id_caja=$1";
  $res = pg_query_params($conn, $sql, [$id, $activa]);
  if (!$res) json_out(['ok'=>false, 'error'=>pg_last_error($conn)], 500);
  json_out(['ok'=>true]);
}
?>
<!doctype html>
<html lang="es">
<head>
  <link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">

<meta charset="utf-8" />
<title>Administración de Cajas</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root{
    --bg:#f6f7fb; --card:#fff; --muted:#6a6f7b; --line:#e9e9ef;
    --primary:#2563eb; --danger:#e11d48; --ok:#16a34a;
  }
  *{box-sizing:border-box}
  body{margin:0;font-family:system-ui,Segoe UI,Arial;background:var(--bg);color:#111}
  header.top{display:flex;gap:12px;align-items:center;padding:16px 20px;background:#fff;border-bottom:1px solid var(--line)}
  h1{font-size:20px;margin:0}
  main{padding:20px;max-width:1100px;margin:0 auto}
  .toolbar{display:flex;gap:8px;align-items:center;margin:0 0 12px 0}
  input[type="text"]{padding:10px 12px;border:1px solid var(--line);border-radius:10px;min-width:240px;background:#fff}
  .btn{padding:10px 14px;border:1px solid var(--line);border-radius:10px;background:#fff;cursor:pointer}
  .btn.primary{background:var(--primary);border-color:var(--primary);color:#fff}
  .btn.ghost{background:transparent}
  .btn.danger{background:var(--danger);border-color:var(--danger);color:#fff}
  table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--line);border-radius:12px;overflow:hidden}
  th,td{padding:10px 12px;border-bottom:1px solid var(--line);font-size:14px}
  th{background:#fafafa;text-align:left;color:#333}
  tr:last-child td{border-bottom:none}
  .badge{padding:2px 8px;border-radius:999px;font-size:12px;border:1px solid var(--line)}
  .badge.ok{background:#ecfdf5;color:#065f46;border-color:#a7f3d0}
  .badge.off{background:#fff1f2;color:#9f1239;border-color:#fecdd3}
  .muted{color:var(--muted)}
  .right{margin-left:auto}
  .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.38);z-index:30}
  .card{background:#fff;border-radius:14px;box-shadow:0 15px 50px rgba(0,0,0,.2);width:100%;max-width:520px}
  .card header{padding:14px 16px;border-bottom:1px solid var(--line);font-weight:600}
  .card .content{padding:16px;display:grid;gap:10px}
  .card .actions{padding:12px 16px;border-top:1px solid var(--line);display:flex;gap:8px;justify-content:flex-end}
  label.l{display:grid;gap:6px;font-size:13px}
  input[type="text"], textarea{padding:10px 12px;border:1px solid var(--line);border-radius:10px}
  textarea{resize:vertical}
  .switch{display:inline-flex;align-items:center;gap:8px;cursor:pointer}
  .switch input{width:42px;height:22px;-webkit-appearance:none;appearance:none;background:#ddd;border-radius:999px;position:relative;outline:none;transition:.2s}
  .switch input:checked{background:#4ade80}
  .switch input::after{content:"";position:absolute;top:3px;left:3px;width:16px;height:16px;background:#fff;border-radius:50%;transition:.2s}
  .switch input:checked::after{left:23px}
</style>
</head>
<body>
  <div id="navbar-container"></div>
  <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js"></script>


<header class="top">
  <button class="btn" onclick="goBack()">← Volver</button>
  <h1>Administración de Cajas</h1>
  <div class="right"></div>
  <button class="btn" onclick="loadGrid()">Refrescar</button>
  <button class="btn primary" onclick="openForm()">Nueva Caja</button>
</header>

<main>
  <div class="toolbar">
    <input id="q" type="text" placeholder="Buscar por nombre..." />
    <label class="switch"><input id="soloActivas" type="checkbox"><span class="muted">Solo activas</span></label>
    <button class="btn" onclick="loadGrid()">Buscar</button>
  </div>

  <table id="grid">
    <thead>
      <tr>
        <th style="width:70px">ID</th>
        <th>Nombre</th>
        <th style="width:120px">Sucursal</th>
        <th style="width:120px">Estado</th>
        <th>Observación</th>
        <th style="width:180px">Fechas</th>
        <th style="width:150px">Acciones</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</main>

<div class="modal" id="modal">
  <div class="card">
    <header id="modalTitle">Nueva Caja</header>
    <div class="content">
      <input type="hidden" id="f_id">
      <label class="l">Nombre
        <input id="f_nombre" type="text" placeholder="Caja Principal" />
      </label>
      <label class="l">Sucursal (opcional)
        <input id="f_suc" type="text" placeholder="ID de sucursal o vacío" />
      </label>
      <label class="l">Observación
        <textarea id="f_obs" rows="3" placeholder="Notas internas..."></textarea>
      </label>
    </div>
    <div class="actions">
      <button class="btn" onclick="closeForm()">Cancelar</button>
      <button class="btn primary" onclick="saveForm()">Guardar</button>
    </div>
  </div>
</div>

<script>
const endpoint = (a) => `?action=${encodeURIComponent(a)}`;

function goBack(){
  if (window.history.length > 1){
    window.history.back();
  } else {
    window.location.href = '/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/dashboard/dashboardv1.php';
  }
}

async function loadGrid(){
  const q = document.getElementById('q').value.trim();
  const solo = document.getElementById('soloActivas').checked ? 'true' : '';
  const url = endpoint('list') + `&q=${encodeURIComponent(q)}&activa=${solo}&limit=200&offset=0`;
  const res = await fetch(url);
  const j = await res.json();
  const tbody = document.querySelector('#grid tbody');
  tbody.innerHTML = '';
  (j.items || []).forEach(r=>{
    const tr = document.createElement('tr');
    const activa = (r.activa === true || r.activa === 't');
    tr.innerHTML = `
      <td>${r.id_caja}</td>
      <td>${escapeHtml(r.nombre)}</td>
      <td>${r.id_sucursal ?? ''}</td>
      <td>${activa ? '<span class="badge ok">Activa</span>' : '<span class="badge off">Inactiva</span>'}</td>
      <td>${escapeNl(escapeHtml(r.observacion||''))}</td>
      <td>
        <div class="muted" title="Creada">${fmtDateTime(r.creado_en)}</div>
        ${r.actualizado_en ? `<div class="muted" title="Actualizada">${fmtDateTime(r.actualizado_en)}</div>` : ''}
      </td>
      <td>
        <button class="btn" onclick="openForm(${r.id_caja}, '${escAttr(r.nombre)}', '${escAttr(r.id_sucursal??'')}', '${escAttr(r.observacion??'')}')">Editar</button>
        <label class="switch" title="Activar/Desactivar">
          <input type="checkbox" ${activa?'checked':''} onchange="toggleActiva(${r.id_caja}, this.checked)">
          <span></span>
        </label>
      </td>`;
    tbody.appendChild(tr);
  });
}

function openForm(id=null, nombre='', suc='', obs=''){
  document.getElementById('modal').style.display='flex';
  document.getElementById('modalTitle').textContent = id? 'Editar Caja' : 'Nueva Caja';
  document.getElementById('f_id').value = id || '';
  document.getElementById('f_nombre').value = unescAttr(nombre);
  document.getElementById('f_suc').value = unescAttr(suc);
  document.getElementById('f_obs').value = unescAttr(obs);
}
function closeForm(){ document.getElementById('modal').style.display='none'; }

async function saveForm(){
  const payload = {
    id_caja: toNull(document.getElementById('f_id').value),
    nombre: document.getElementById('f_nombre').value.trim(),
    id_sucursal: toNull(document.getElementById('f_suc').value.trim()),
    observacion: document.getElementById('f_obs').value.trim(),
  };
  if (!payload.nombre) { alert('El nombre es requerido'); return; }

  const res = await fetch(endpoint('save'), {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });
  const j = await res.json();
  if (!j.ok){ alert(j.error || 'Error al guardar'); return; }
  closeForm(); loadGrid();
}

async function toggleActiva(id, on){
  const res = await fetch(endpoint('toggle'), {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({id_caja:id, activa: !!on})
  });
  const j = await res.json();
  if (!j.ok){ alert(j.error || 'No se pudo actualizar'); loadGrid(); }
}

function toNull(v){ return (v===''||v===null||v===undefined) ? null : (/^-?\d+$/.test(v) ? Number(v) : v); }
function fmtDateTime(s){
  if(!s) return '';
  const d = new Date(s.replace(' ', 'T')+'Z');
  if (isNaN(d)) return s;
  return d.toLocaleString();
}
function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m])); }
function escAttr(s){ return escapeHtml(s).replace(/\n/g,'&#10;'); }
function unescAttr(s){ const e=document.createElement('textarea'); e.innerHTML=s||''; return e.value; }
function escapeNl(s){ return (s||'').replace(/\n/g,'<br>'); }

loadGrid();
</script>
</body>
</html>
