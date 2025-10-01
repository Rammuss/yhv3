<?php
// producto_manager_ui.php — UI (GET) + API (POST) para crear y gestionar productos (con duración para Servicios)
// Requiere columna: ALTER TABLE public.producto ADD COLUMN IF NOT EXISTS duracion_min integer CHECK (duracion_min IS NULL OR duracion_min > 0);

session_start();
require_once __DIR__ . '/../../conexion/configv2.php'; // <-- AJUSTAR
header('X-Content-Type-Options: nosniff');

function json_error($msg, $code = 400){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => false, 'error' => $msg]);
  exit;
}
function json_ok($data = []){
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => true] + $data);
  exit;
}
function num($x){ return is_numeric($x) ? 0 + $x : 0; }
function s($x){ return is_string($x) ? trim($x) : null; }

$IVA_VALIDOS = ['10%', '5%', 'Exento'];
$ESTADOS_VALIDOS = ['Activo', 'Inactivo'];
$TIPOS_ITEM_VALIDOS = ['P', 'S'];

// ===== API (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
  $op = strtolower(s($in['op'] ?? ''));
  if ($op === '') json_error('Parámetro op requerido');

  try {
    switch ($op) {
      // Crear
      case 'create': {
        $nombre = s($in['nombre'] ?? '');
        $precio_unitario = (float)($in['precio_unitario'] ?? -1);
        $precio_compra   = (float)($in['precio_compra'] ?? -1);
        $estado     = s($in['estado'] ?? 'Activo');
        $tipo_iva   = s($in['tipo_iva'] ?? '10%');
        $categoria  = s($in['categoria'] ?? null);
        $tipo_item  = s($in['tipo_item'] ?? 'P');
        $duracion_min = isset($in['duracion_min']) && $in['duracion_min'] !== ''
                        ? (int)$in['duracion_min'] : null;

        if ($nombre === '') json_error('Nombre requerido');
        if ($precio_unitario < 0 || $precio_compra < 0) json_error('Precios inválidos (>= 0)');
        if (!in_array($estado, $ESTADOS_VALIDOS, true)) json_error('Estado inválido');
        if (!in_array($tipo_iva, $IVA_VALIDOS, true)) json_error('Tipo de IVA inválido');
        if (!in_array($tipo_item, $TIPOS_ITEM_VALIDOS, true)) json_error('Tipo de ítem inválido (P/S)');

        // Si es Servicio, permitimos duracion_min; si es Producto, forzamos NULL
        if ($tipo_item === 'S') {
          if ($duracion_min !== null && $duracion_min <= 0) json_error('Duración inválida');
        } else {
          $duracion_min = null;
        }

        $sql = "INSERT INTO public.producto
                  (nombre, precio_unitario, precio_compra, estado, tipo_iva, categoria, tipo_item, duracion_min)
                VALUES ($1,$2,$3,$4,$5,$6,$7,$8)
                RETURNING id_producto";
        $r = pg_query_params($conn, $sql,
              [$nombre, $precio_unitario, $precio_compra, $estado, $tipo_iva, $categoria, $tipo_item, $duracion_min]);
        if (!$r) json_error('No se pudo crear el producto');
        $id = (int)pg_fetch_result($r, 0, 0);
        json_ok(['id_producto' => $id, 'nombre'=>$nombre]);
      }

      // Actualizar
      case 'update': {
        $id = (int)($in['id_producto'] ?? 0);
        if ($id <= 0) json_error('id_producto inválido');

        $nombre = s($in['nombre'] ?? '');
        $precio_unitario = (float)($in['precio_unitario'] ?? -1);
        $precio_compra   = (float)($in['precio_compra'] ?? -1);
        $estado     = s($in['estado'] ?? 'Activo');
        $tipo_iva   = s($in['tipo_iva'] ?? '10%');
        $categoria  = s($in['categoria'] ?? null);
        $tipo_item  = s($in['tipo_item'] ?? 'P');
        $duracion_min = isset($in['duracion_min']) && $in['duracion_min'] !== ''
                        ? (int)$in['duracion_min'] : null;

        if ($nombre === '') json_error('Nombre requerido');
        if ($precio_unitario < 0 || $precio_compra < 0) json_error('Precios inválidos (>= 0)');
        if (!in_array($estado, $ESTADOS_VALIDOS, true)) json_error('Estado inválido');
        if (!in_array($tipo_iva, $IVA_VALIDOS, true)) json_error('Tipo de IVA inválido');
        if (!in_array($tipo_item, $TIPOS_ITEM_VALIDOS, true)) json_error('Tipo de ítem inválido (P/S)');

        if ($tipo_item === 'S') {
          if ($duracion_min !== null && $duracion_min <= 0) json_error('Duración inválida');
        } else {
          $duracion_min = null;
        }

        $sql = "UPDATE public.producto
                   SET nombre=$2, precio_unitario=$3, precio_compra=$4,
                       estado=$5, tipo_iva=$6, categoria=$7, tipo_item=$8, duracion_min=$9
                 WHERE id_producto=$1";
        $r = pg_query_params($conn, $sql,
              [$id, $nombre, $precio_unitario, $precio_compra, $estado, $tipo_iva, $categoria, $tipo_item, $duracion_min]);
        if (!$r) json_error('No se pudo actualizar el producto');
        json_ok(['updated' => true, 'nombre'=>$nombre]);
      }

      // Activar / Inactivar
      case 'toggle_estado': {
        $id = (int)($in['id_producto'] ?? 0);
        $nuevo = s($in['estado'] ?? '');
        if ($id <= 0) json_error('id_producto inválido');
        if (!in_array($nuevo, $ESTADOS_VALIDOS, true)) json_error('Estado inválido');
        $r = pg_query_params($conn, "UPDATE public.producto SET estado=$2 WHERE id_producto=$1", [$id, $nuevo]);
        if (!$r) json_error('No se pudo cambiar estado');
        json_ok(['estado' => $nuevo]);
      }

      // Obtener 1
      case 'get': {
        $id = (int)($in['id_producto'] ?? 0);
        if ($id <= 0) json_error('id_producto inválido');
        $r = pg_query_params($conn, "SELECT * FROM public.producto WHERE id_producto=$1", [$id]);
        if (!$r || pg_num_rows($r) === 0) json_error('No encontrado', 404);
        json_ok(['row' => pg_fetch_assoc($r)]);
      }

      // Listar
      case 'list': {
        $q = mb_strtolower(s($in['q'] ?? '')) ?? '';
        $estado = s($in['estado'] ?? '');
        $tipo_item = s($in['tipo_item'] ?? '');
        $ps = max(1, min(100, num($in['page_size'] ?? 25)));
        $off = max(0, num($in['offset'] ?? 0));
        $likeAny = '%'.$q.'%';

        $where = "WHERE 1=1";
        $params = [];
        $i = 1;

        if ($q !== '') { $where .= " AND (lower(nombre) LIKE $".$i." OR lower(categoria) LIKE $".$i.")"; $params[] = $likeAny; $i++; }
        if ($estado !== '' && in_array($estado, $ESTADOS_VALIDOS, true)) { $where .= " AND estado = $".$i; $params[] = $estado; $i++; }
        if ($tipo_item !== '' && in_array($tipo_item, $TIPOS_ITEM_VALIDOS, true)) { $where .= " AND tipo_item = $".$i; $params[] = $tipo_item; $i++; }

        $sqlCount = "SELECT COUNT(*) FROM public.producto $where";
        $rC = pg_query_params($conn, $sqlCount, $params);
        if(!$rC) json_error('Error al contar');
        $total = (int)pg_fetch_result($rC, 0, 0);

        $sqlList = "SELECT id_producto, nombre, precio_unitario, precio_compra, estado, tipo_iva, categoria, tipo_item, duracion_min
                    FROM public.producto
                    $where
                    ORDER BY nombre ASC
                    LIMIT $".($i)." OFFSET $".($i+1);
        $params2 = $params; $params2[] = $ps; $params2[] = $off;

        $rL = pg_query_params($conn, $sqlList, $params2);
        if(!$rL) json_error('Error al listar');

        $rows=[]; while($x=pg_fetch_assoc($rL)) $rows[]=$x;
        json_ok(['total'=>$total,'rows'=>$rows]);
      }

      default: json_error('op no reconocido');
    }
  } catch (Throwable $e) {
    json_error($e->getMessage());
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Productos • Alta rápida</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="../css/styles_servicios.css">
<style>
  :root{ --g:#10b981; --r:#ef4444; --b:#111; --shadow:0 10px 20px rgba(0,0,0,.12); }

  body{ font-family:system-ui, Segoe UI, Roboto, Arial; margin:20px; color:#111; background:#fff }
  .card{ border:1px solid #e5e7eb; border-radius:10px; padding:12px; margin:10px 0; background:#fff }
  .row{ display:flex; gap:8px; flex-wrap:wrap; align-items:end }
  .row>*{ flex:1 }
  label{ display:block; font-size:12px; color:#374151; margin:6px 0 4px }
  input,select,button{ padding:8px 10px; border:1px solid #d1d5db; border-radius:8px }
  button{ background:#111; color:#fff; border:none; cursor:pointer; }
  button.sec{ background:#f3f4f6; color:#111 }
  table{ width:100%; border-collapse:collapse; margin-top:8px }
  th,td{ border:1px solid #e5e7eb; padding:8px; font-size:14px }
  th{ background:#f3f4f6; text-align:left }
  .muted{ color:#6b7280; font-size:12px }

  /* Toast centrado */
  .toast-layer{ position:fixed; inset:0; pointer-events:none; display:flex; align-items:center; justify-content:center; z-index:9999; }
  .toast-container{ display:flex; flex-direction:column; gap:10px; max-width:min(520px,95vw); width:100%; align-items:center; }
  .toast{
    pointer-events:auto; background:#111; color:#fff; padding:12px 14px; border-radius:12px;
    box-shadow:var(--shadow); display:flex; gap:10px; align-items:flex-start; animation:toastIn .18s ease-out both;
    max-width:520px; width:100%;
  }
  .toast.error{ background:#991b1b }
  .toast .title{ font-weight:600; margin-bottom:2px }
  .toast .msg{ opacity:.95 }
  .toast .close{ margin-left:auto; background:transparent; color:#fff; border:none; font-size:18px; line-height:1; cursor:pointer }
  @keyframes toastIn{ from{ opacity:0; transform:translateY(8px) scale(.98) } to{ opacity:1; transform:translateY(0) scale(1) } }
  @keyframes toastOut{ to{ opacity:0; transform:translateY(-6px) scale(.98) } }
</style>
</head>
<body>
  <h1>Productos • Alta y Gestión</h1>

  <!-- Alta rápida -->
  <div class="card">
    <h3>Crear producto</h3>
    <div class="row">
      <div>
        <label>Nombre *</label>
        <input id="p_nombre" placeholder="Ej: Corte de cabello / Shampoo Nutritivo" />
      </div>
      <div>
        <label>Precio venta *</label>
        <input type="number" id="p_pu" step="0.01" min="0" value="0" />
      </div>
      <div>
        <label>Precio compra *</label>
        <input type="number" id="p_pc" step="0.01" min="0" value="0" />
      </div>
      <div>
        <label>Tipo IVA *</label>
        <select id="p_iva">
          <option>10%</option><option>5%</option><option>Exento</option>
        </select>
      </div>
    </div>
    <div class="row">
      <div>
        <label>Categoría</label>
        <select id="p_cat">
          <option value="Peluquería" selected>Peluquería</option>
          <option value="Manicura">Manicura</option>
          <option value="Estilista">Estilista</option>
          <option value="Barbería">Barbería</option>
          <option value="Cosmética">Cosmética</option>
          <option value="Otros">Otros</option>
        </select>
      </div>
      <div>
        <label>Tipo ítem *</label>
        <select id="p_tipo" onchange="toggleDuracionCreate()">
          <option value="P" selected>Producto (mueve stock)</option>
          <option value="S">Servicio (no mueve stock)</option>
        </select>
      </div>
      <div id="wrap_p_duracion" style="display:none">
        <label>Duración (min) *</label>
        <select id="p_duracion">
          <option value="15">15</option>
          <option value="30" selected>30</option>
          <option value="45">45</option>
          <option value="60">60</option>
          <option value="90">90</option>
          <option value="120">120</option>
        </select>
      </div>
      <div>
        <label>Estado *</label>
        <select id="p_estado">
          <option>Activo</option><option>Inactivo</option>
        </select>
      </div>
      <div style="flex:0; display:flex; gap:8px">
        <button onclick="crear()">Guardar</button>
      </div>
    </div>
    <div class="muted">Para <strong>Servicio</strong> se habilita la <strong>Duración</strong>. En Producto queda oculto.</div>
  </div>

  <!-- Filtros/Listado -->
  <div class="card">
    <h3>Listado</h3>
    <div class="row">
      <div>
        <label>Buscar</label>
        <input id="q" placeholder="nombre o categoría..." oninput="debouncedList()" />
      </div>
      <div>
        <label>Estado</label>
        <select id="f_estado" onchange="list()">
          <option value="">(Todos)</option><option>Activo</option><option>Inactivo</option>
        </select>
      </div>
      <div>
        <label>Tipo ítem</label>
        <select id="f_tipo" onchange="list()">
          <option value="">(Todos)</option><option value="P">Producto</option><option value="S">Servicio</option>
        </select>
      </div>
      <div style="flex:0">
        <button class="sec" onclick="list()">Actualizar</button>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>#</th><th>Nombre</th><th>Cat.</th><th>IVA</th><th>Tipo</th>
          <th>Venta</th><th>Compra</th><th>Dur (min)</th><th>Estado</th><th style="width:250px">Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody"></tbody>
    </table>
    <div class="muted" id="tot"></div>
  </div>

  <!-- Modal de edición -->
  <div id="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.35); align-items:center; justify-content:center;">
    <div style="background:#fff; padding:16px; border-radius:10px; width:min(680px,95vw);">
      <h3>Editar producto</h3>
      <div class="row">
        <input id="e_id" type="hidden" />
        <div><label>Nombre</label><input id="e_nombre" /></div>
        <div><label>Venta</label><input id="e_pu" type="number" step="0.01" min="0" /></div>
        <div><label>Compra</label><input id="e_pc" type="number" step="0.01" min="0" /></div>
        <div><label>IVA</label>
          <select id="e_iva">
            <option>10%</option><option>5%</option><option>Exento</option>
          </select>
        </div>
      </div>
      <div class="row">
        <div>
          <label>Categoría</label>
          <select id="e_cat">
            <option value="Peluquería">Peluquería</option>
            <option value="Manicura">Manicura</option>
            <option value="Estilista">Estilista</option>
            <option value="Barbería">Barbería</option>
            <option value="Cosmética">Cosmética</option>
            <option value="Otros">Otros</option>
          </select>
        </div>
        <div>
          <label>Tipo</label>
          <select id="e_tipo" onchange="toggleDuracionEdit()"><option value="P">P</option><option value="S">S</option></select>
        </div>
        <div id="wrap_e_duracion" style="display:none">
          <label>Duración (min) *</label>
          <select id="e_duracion">
            <option value="15">15</option>
            <option value="30">30</option>
            <option value="45">45</option>
            <option value="60">60</option>
            <option value="90">90</option>
            <option value="120">120</option>
          </select>
        </div>
        <div>
          <label>Estado</label>
          <select id="e_estado"><option>Activo</option><option>Inactivo</option></select>
        </div>
        <div style="flex:0; display:flex; gap:8px">
          <button onclick="saveEdit()">Guardar</button>
          <button class="sec" onclick="closeModal()">Cancelar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Capa y contenedor de Toast centrado -->
  <div class="toast-layer" aria-live="polite" aria-atomic="true">
    <div class="toast-container" id="toasts"></div>
  </div>

<script>
const API = location.pathname;
let timer=null;
const $ = (id)=>document.getElementById(id);

/* ===== TOAST (centrado) ===== */
function showToast(message, type='success', title=null, timeout=3000){
  const cont = $('toasts');
  const toast = document.createElement('div');
  toast.className = 'toast' + (type==='error' ? ' error' : '');
  toast.innerHTML = `
    <div>
      <div class="title">${title ? title : (type==='error'?'Error':'Listo')}</div>
      <div class="msg">${message}</div>
    </div>
    <button class="close" aria-label="Cerrar" onclick="closeToast(this)">×</button>
  `;
  cont.prepend(toast);
  const t = setTimeout(()=>{
    toast.style.animation = 'toastOut .18s ease-in forwards';
    setTimeout(()=> toast.remove(), 180);
  }, timeout);
  toast._timer = t;
}
function closeToast(btn){
  const toast = btn.closest('.toast');
  if (!toast) return;
  clearTimeout(toast._timer);
  toast.style.animation = 'toastOut .18s ease-in forwards';
  setTimeout(()=> toast.remove(), 180);
}

/* ===== UI helpers ===== */
function toggleDuracionCreate(){
  const isServicio = $('p_tipo').value === 'S';
  $('wrap_p_duracion').style.display = isServicio ? '' : 'none';
}
function toggleDuracionEdit(){
  const isServicio = $('e_tipo').value === 'S';
  $('wrap_e_duracion').style.display = isServicio ? '' : 'none';
}

/* ===== API helper ===== */
async function api(op, payload={}){
  const res = await fetch(API, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({op, ...payload})
  });
  const data = await res.json();
  if (!data.success) throw new Error(data.error || 'Error');
  return data;
}

/* ===== Alta ===== */
async function crear(){
  try{
    const isServicio = $('p_tipo').value === 'S';
    const payload = {
      nombre: $('p_nombre').value.trim(),
      precio_unitario: Number($('p_pu').value || 0),
      precio_compra:   Number($('p_pc').value || 0),
      tipo_iva: $('p_iva').value,
      categoria: $('p_cat').value || null,
      tipo_item: $('p_tipo').value,
      estado: $('p_estado').value,
      duracion_min: isServicio ? Number($('p_duracion').value) : null
    };

    // Si es servicio y el usuario dejó compra > 0, lo dejamos; en general suele ser 0
    const r = await api('create', payload);

    // limpiar y refrescar
    $('p_nombre').value='';
    $('p_pu').value='0';
    $('p_pc').value='0';
    $('p_iva').value='10%';
    $('p_cat').value='Peluquería';
    $('p_tipo').value='P';
    $('p_estado').value='Activo';
    $('p_duracion').value='30';
    toggleDuracionCreate();
    $('p_nombre').focus();

    await list();
    showToast(`"${r.nombre ?? payload.nombre}" creado correctamente.`, 'success', '¡Guardado!');
  }catch(e){
    showToast(e.message, 'error');
  }
}

/* ===== Listado ===== */
async function list(){
  try{
    const r = await api('list', {
      q: $('q').value.trim(),
      estado: $('f_estado').value,
      tipo_item: $('f_tipo').value,
      page_size: 100, offset: 0
    });
    const tbody = $('tbody'); tbody.innerHTML='';
    (r.rows||[]).forEach(row=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${row.id_producto}</td>
        <td>${row.nombre}</td>
        <td>${row.categoria||''}</td>
        <td>${row.tipo_iva}</td>
        <td>${row.tipo_item}</td>
        <td>${Number(row.precio_unitario).toLocaleString()}</td>
        <td>${Number(row.precio_compra).toLocaleString()}</td>
        <td>${row.tipo_item==='S' ? (row.duracion_min ?? '-') : '-'}</td>
        <td>${row.estado}</td>
        <td>
          <button class="sec" onclick="openEdit(${row.id_producto})">Editar</button>
          ${row.estado==='Activo'
            ? `<button class="sec" onclick="toggle(${row.id_producto},'Inactivo')">Inactivar</button>`
            : `<button class="sec" onclick="toggle(${row.id_producto},'Activo')">Activar</button>`
          }
        </td>
      `;
      tbody.appendChild(tr);
    });
    $('tot').textContent = `Total: ${r.total}`;
  }catch(e){
    showToast(e.message, 'error');
  }
}
function debouncedList(){ clearTimeout(timer); timer=setTimeout(list, 300); }

/* ===== Toggle estado ===== */
async function toggle(id, nuevo){
  try{
    await api('toggle_estado', { id_producto:id, estado:nuevo });
    await list();
    showToast(`Producto ${nuevo === 'Activo' ? 'activado' : 'inactivado'}.`);
  }catch(e){
    showToast(e.message, 'error');
  }
}

/* ===== Editar ===== */
async function openEdit(id){
  try{
    const r = await api('get', { id_producto:id });
    const p = r.row;
    $('e_id').value = p.id_producto;
    $('e_nombre').value = p.nombre;
    $('e_pu').value = p.precio_unitario;
    $('e_pc').value = p.precio_compra;
    $('e_iva').value = p.tipo_iva;
    $('e_cat').value = p.categoria || 'Peluquería';
    $('e_tipo').value = p.tipo_item;
    $('e_estado').value = p.estado;

    // duracion
    const isServicio = (p.tipo_item === 'S');
    $('wrap_e_duracion').style.display = isServicio ? '' : 'none';
    if (isServicio) {
      $('e_duracion').value = String(p.duracion_min || 30);
    }

    $('modal').style.display='flex';
  }catch(e){
    showToast(e.message, 'error');
  }
}
function closeModal(){ $('modal').style.display='none'; }

async function saveEdit(){
  try{
    const isServicio = $('e_tipo').value === 'S';
    const payload = {
      id_producto: Number($('e_id').value||0),
      nombre: $('e_nombre').value.trim(),
      precio_unitario: Number($('e_pu').value||0),
      precio_compra:   Number($('e_pc').value||0),
      tipo_iva: $('e_iva').value,
      categoria: $('e_cat').value || null,
      tipo_item: $('e_tipo').value,
      estado: $('e_estado').value,
      duracion_min: isServicio ? Number($('e_duracion').value) : null
    };
    await api('update', payload);
    closeModal(); await list();
    showToast(`"${payload.nombre}" actualizado.`, 'success', 'Cambios guardados');
  }catch(e){
    showToast(e.message, 'error');
  }
}

/* ===== Inicial ===== */
window.addEventListener('DOMContentLoaded', ()=>{
  toggleDuracionCreate();
  list();
});
</script>
</body>
</html>
