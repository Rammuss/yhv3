<?php
// sucursales_ref.php  —  Página + API AJAX en un mismo archivo
// Requiere: tabla public.sucursales (la que pasaste)
// Config BD:
require_once __DIR__ . '../../../../conexion/configv2.php'; // Debe crear $conn = pg_connect(...)

if (!$conn) {
  http_response_code(500);
  echo "Error: sin conexión a la BD";
  exit;
}

/* =========================
   API AJAX (JSON)
   ========================= */
if (isset($_GET['action']) || isset($_POST['action'])) {
  header('Content-Type: application/json; charset=utf-8');
  $action = $_GET['action'] ?? $_POST['action'];

  try {
    if ($action === 'list') {
      $q      = trim((string)($_GET['q'] ?? ''));
      $estado = trim((string)($_GET['estado'] ?? '')); // '', ACTIVO, INACTIVO

      $where = [];
      $params = [];
      if ($q !== '') {
        $where[] = "(unaccent(lower(nombre)) LIKE unaccent(lower($1)) OR unaccent(lower(coalesce(direccion,''))) LIKE unaccent(lower($1)))";
        $params[] = '%'.$q.'%';
      }
      if ($estado !== '') {
        $where[] = "estado = $".(count($params)+1);
        $params[] = $estado;
      }
      $sql = "SELECT id_sucursal, nombre, coalesce(direccion,'') AS direccion, estado, to_char(creado_en,'YYYY-MM-DD HH24:MI') AS creado_en
              FROM public.sucursales";
      if ($where) $sql .= " WHERE ".implode(" AND ", $where);
      $sql .= " ORDER BY estado DESC, nombre ASC";

      $res = pg_query_params($conn, $sql, $params);
      $rows = [];
      if ($res) { while ($r = pg_fetch_assoc($res)) { $rows[] = $r; } }
      echo json_encode(["ok"=>true, "data"=>$rows], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($action === 'create') {
      $nombre    = trim((string)($_POST['nombre'] ?? ''));
      $direccion = trim((string)($_POST['direccion'] ?? ''));
      if ($nombre === '') { echo json_encode(["ok"=>false,"error"=>"Nombre requerido"]); exit; }

      $sql = "INSERT INTO public.sucursales (nombre, direccion, estado)
              VALUES ($1, NULLIF($2,''), 'ACTIVO')
              RETURNING id_sucursal";
      $res = pg_query_params($conn, $sql, [$nombre, $direccion]);
      if (!$res) {
        // intentar capturar unique violation
        $pgerr = pg_last_error($conn);
        if (strpos($pgerr, 'sucursales_nombre_key') !== false) {
          echo json_encode(["ok"=>false,"error"=>"Ya existe una sucursal con ese nombre"]); exit;
        }
        echo json_encode(["ok"=>false,"error"=>"No se pudo crear la sucursal"]); exit;
      }
      $id = (int)pg_fetch_result($res,0,0);
      echo json_encode(["ok"=>true,"id_sucursal"=>$id], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($action === 'toggle') {
      $id  = (int)($_POST['id_sucursal'] ?? 0);
      $to  = strtoupper(trim((string)($_POST['to'] ?? ''))); // ACTIVO / INACTIVO

      if ($id<=0 || !in_array($to, ['ACTIVO','INACTIVO'], true)) {
        echo json_encode(["ok"=>false,"error"=>"Parámetros inválidos"]); exit;
      }
      $res = pg_query_params($conn, "UPDATE public.sucursales SET estado=$2 WHERE id_sucursal=$1", [$id, $to]);
      if (!$res) { echo json_encode(["ok"=>false,"error"=>"No se pudo actualizar estado"]); exit; }
      echo json_encode(["ok"=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    // Opcional: editar nombre/dirección (si algún día lo querés usar)
    if ($action === 'update') {
      $id        = (int)($_POST['id_sucursal'] ?? 0);
      $nombre    = trim((string)($_POST['nombre'] ?? ''));
      $direccion = trim((string)($_POST['direccion'] ?? ''));
      if ($id<=0 || $nombre===''){ echo json_encode(["ok"=>false,"error"=>"Datos inválidos"]); exit; }

      $res = pg_query_params($conn, "
        UPDATE public.sucursales
           SET nombre=$2,
               direccion=NULLIF($3,'')
         WHERE id_sucursal=$1
      ", [$id, $nombre, $direccion]);
      if (!$res) {
        $pgerr = pg_last_error($conn);
        if (strpos($pgerr, 'sucursales_nombre_key') !== false) {
          echo json_encode(["ok"=>false,"error"=>"Ya existe una sucursal con ese nombre"]); exit;
        }
        echo json_encode(["ok"=>false,"error"=>"No se pudo actualizar"]); exit;
      }
      echo json_encode(["ok"=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    // Reutilizable: options para combos
    if ($action === 'options') {
      $estado = strtoupper(trim((string)($_GET['estado'] ?? 'ACTIVO'))); // default ACTIVO
      $params=[]; $where='';
      if (in_array($estado,['ACTIVO','INACTIVO'],true)) { $where="WHERE estado=$1"; $params[]=$estado; }
      $res = pg_query_params($conn, "SELECT id_sucursal, nombre FROM public.sucursales $where ORDER BY nombre ASC", $params);
      $rows=[]; if ($res){ while($r=pg_fetch_assoc($res)) $rows[]=$r; }
      echo json_encode($rows, JSON_UNESCAPED_UNICODE); exit;
    }

    echo json_encode(["ok"=>false,"error"=>"Acción desconocida"]); exit;

  } catch (Throwable $e) {
    echo json_encode(["ok"=>false,"error"=>$e->getMessage()]); exit;
  }
}

/* =========================
   HTML + JS (UI)
   ========================= */
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Referencial de Sucursales</title>
<link rel="stylesheet" href="../../styles.css">
<style>
  body{font-family:Arial, sans-serif; background:#f7f7f7; margin:20px}
  .wrap{max-width:1000px; margin:auto}
  h1{margin:0 0 12px}
  .row{display:flex; gap:10px; align-items:flex-end; margin-bottom:12px; flex-wrap:wrap}
  label{font-size:12px; color:#444}
  input,select,button{padding:8px}
  .btn{padding:8px 12px; border:1px solid #ddd; background:#fff; cursor:pointer; border-radius:6px}
  .btn:hover{background:#f3f3f3}
  .btn-primary{border-color:#3149c2; color:#3149c2}
  .btn-danger{border-color:#c23131; color:#c23131}
  table{width:100%; border-collapse:collapse; background:#fff}
  th,td{border:1px solid #eee; padding:8px; text-align:left}
  th{background:#fafafa}
  .muted{color:#777}
  .pill{display:inline-block; padding:2px 8px; border:1px solid #ddd; border-radius:14px; font-size:12px}
  .ok{color:#256029}
  .no{color:#8a1c1c}
  .card{background:#fff; border:1px solid #e5e5e5; border-radius:8px; margin-top:10px; box-shadow:0 1px 4px rgba(0,0,0,.05)}
  .card-body{padding:12px}
</style>
</head>
<body>
  <div id="navbar-container"></div>
  <div class="wrap">
    <h1>Referencial de Sucursales</h1>

    <!-- Alta -->
    <div class="card">
      <div class="card-body">
        <div class="row">
          <div style="flex:1; min-width:220px">
            <label>Nombre</label><br>
            <input type="text" id="nombre" placeholder="Ej: Casa Central" style="width:100%">
          </div>
          <div style="flex:2; min-width:280px">
            <label>Dirección</label><br>
            <input type="text" id="direccion" placeholder="Calle / Nº / Ciudad" style="width:100%">
          </div>
          <div>
            <button class="btn btn-primary" id="btnCrear">Guardar nueva</button>
          </div>
        </div>
        <div class="muted">Sólo se permite **alta** y **baja/activar** (no eliminación física).</div>
      </div>
    </div>

    <!-- Filtros -->
    <div class="row" style="margin-top:10px">
      <div>
        <label>Buscar</label><br>
        <input type="text" id="q" placeholder="Nombre o dirección" style="width:220px">
      </div>
      <div>
        <label>Estado</label><br>
        <select id="f_estado">
          <option value="">Todos</option>
          <option value="ACTIVO" selected>ACTIVO</option>
          <option value="INACTIVO">INACTIVO</option>
        </select>
      </div>
      <div>
        <button class="btn" id="btnBuscar">Buscar</button>
        <button class="btn" id="btnLimpiar">Limpiar</button>
      </div>
    </div>

    <!-- Tabla -->
    <div class="card">
      <div class="card-body">
        <table id="tabla">
          <thead>
            <tr>
              <th>#</th>
              <th>Nombre</th>
              <th>Dirección</th>
              <th>Estado</th>
              <th>Creado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody><!-- rows --></tbody>
        </table>
      </div>
    </div>
  </div>

<script>
const $ = s => document.querySelector(s);
const RUTA = location.pathname; // este mismo archivo

document.addEventListener('DOMContentLoaded', ()=>{
  $('#btnCrear').addEventListener('click', crear);
  $('#btnBuscar').addEventListener('click', listar);
  $('#btnLimpiar').addEventListener('click', ()=>{
    $('#q').value=''; $('#f_estado').value='ACTIVO'; listar();
  });
  listar();
});

async function crear(){
  const nombre = $('#nombre').value.trim();
  const direccion = $('#direccion').value.trim();
  if (!nombre){ alert('Nombre requerido'); return; }

  const fd = new FormData();
  fd.append('action','create');
  fd.append('nombre', nombre);
  fd.append('direccion', direccion);

  try{
    const r = await fetch(RUTA, {method:'POST', body:fd});
    const j = await r.json();
    if (!j.ok){ alert(j.error || 'No se pudo crear'); return; }
    alert('Sucursal creada (#'+j.id_sucursal+')');
    $('#nombre').value=''; $('#direccion').value='';
    listar();
  }catch(e){ console.error(e); alert('Error de red'); }
}

async function listar(){
  const q = $('#q').value.trim();
  const estado = $('#f_estado').value;
  const params = new URLSearchParams({action:'list'});
  if (q) params.set('q', q);
  if (estado) params.set('estado', estado);

  try{
    const r = await fetch(RUTA + '?' + params.toString());
    const j = await r.json();
    if (!j.ok){ alert(j.error||'Error al listar'); return; }
    render(j.data||[]);
  }catch(e){ console.error(e); alert('Error de red'); }
}

function render(rows){
  const tb = $('#tabla tbody');
  tb.innerHTML = '';
  if (!rows.length){
    const tr = document.createElement('tr');
    tr.innerHTML = `<td colspan="6" class="muted">Sin resultados</td>`;
    tb.appendChild(tr);
    return;
  }
  rows.forEach(r=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.id_sucursal}</td>
      <td>${esc(r.nombre)}</td>
      <td>${esc(r.direccion||'')}</td>
      <td>${r.estado==='ACTIVO'
            ? '<span class="pill ok">ACTIVO</span>'
            : '<span class="pill no">INACTIVO</span>'}</td>
      <td class="muted">${r.creado_en||''}</td>
      <td>
        ${r.estado==='ACTIVO'
          ? `<button class="btn btn-danger" onclick="toggleEstado(${r.id_sucursal}, 'INACTIVO')">Inactivar</button>`
          : `<button class="btn" onclick="toggleEstado(${r.id_sucursal}, 'ACTIVO')">Activar</button>`
        }
      </td>
    `;
    tb.appendChild(tr);
  });
}

async function toggleEstado(id, to){
  if (!confirm(`¿Confirmás cambiar a estado ${to} la sucursal #${id}?`)) return;
  const fd = new FormData();
  fd.append('action','toggle');
  fd.append('id_sucursal', id);
  fd.append('to', to);
  try{
    const r = await fetch(RUTA, {method:'POST', body: fd});
    const j = await r.json();
    if (!j.ok){ alert(j.error||'No se pudo actualizar'); return; }
    listar();
  }catch(e){ console.error(e); alert('Error de red'); }
}

function esc(s){ return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
<script src="../../navbar.js"></script>
</body>
</html>
