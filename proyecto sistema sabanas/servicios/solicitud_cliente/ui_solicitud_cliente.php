<?php
// solicitud_cliente_ui.php — UI (GET) + API (POST)
// Flujo: 1) Buscar cliente 2) Tarjetas de productos (peluquería) 3) Grilla local (+/-/🗑) 4) Crear Solicitud (sube carrito)
// API: create, add_item, set_item_qty, remove_item, set_estado, get, preview_import, buscar_productos
session_start();
require_once __DIR__ . '/../../conexion/configv2.php'; // <-- AJUSTAR
header('X-Content-Type-Options: nosniff');

function json_error($msg,$code=400){ http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'error'=>$msg]); exit; }
function json_ok($data=[]){ header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>true]+$data); exit; }
function num($x){ return is_numeric($x)?0+$x:0; }
function s($x){ return is_string($x)?trim($x):null; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
  $op = strtolower(s($in['op'] ?? ''));
  if ($op==='') json_error('Parámetro op requerido');

  try {
    $SQL_DISP = "
      WITH mov AS (
        SELECT ms.id_producto,
               SUM(CASE
                    WHEN lower(ms.tipo_movimiento) IN ('entrada','ingreso','compra','ajuste_pos') THEN ms.cantidad
                    WHEN lower(ms.tipo_movimiento) IN ('salida','egreso','venta','ot','ajuste_neg') THEN -ms.cantidad
                    ELSE 0 END)::numeric(14,2) AS stock_actual
        FROM public.movimiento_stock ms
        GROUP BY ms.id_producto
      ),
      res AS (
        SELECT r.id_producto, COALESCE(SUM(r.cantidad),0)::numeric(14,2) AS reservado
        FROM public.reserva_stock r
        WHERE lower(r.estado)='activa'
        GROUP BY r.id_producto
      )
      SELECT p.id_producto,
             COALESCE(mov.stock_actual,0) AS stock_actual,
             COALESCE(res.reservado,0)    AS reservado,
             (COALESCE(mov.stock_actual,0)-COALESCE(res.reservado,0))::numeric(14,2) AS disponible
      FROM public.producto p
      LEFT JOIN mov ON mov.id_producto=p.id_producto
      LEFT JOIN res ON res.id_producto=p.id_producto
    ";

    switch ($op) {
      case 'create': {
        $id_cliente = num($in['id_cliente'] ?? 0);
        $notas = s($in['notas'] ?? null);
        if ($id_cliente<=0) json_error('id_cliente requerido');
        $r = pg_query_params($conn,
          "INSERT INTO public.solicitud_cliente (id_cliente, notas) VALUES ($1,$2)
           RETURNING id_solicitud, estado, created_at",
          [$id_cliente, $notas]
        );
        if(!$r) json_error('No se pudo crear la solicitud');
        json_ok(['solicitud'=>pg_fetch_assoc($r)]);
      }

      case 'add_item': { // suma cantidad (UPSERT)
        $id_solicitud = num($in['id_solicitud'] ?? 0);
        $id_producto  = num($in['id_producto'] ?? 0);
        $cantidad     = (float)($in['cantidad'] ?? 0);
        if ($id_solicitud<=0 || $id_producto<=0 || $cantidad<=0) json_error('Datos de item inválidos');

        pg_query($conn,'BEGIN');
        $lock = pg_query_params($conn,"SELECT estado FROM public.solicitud_cliente WHERE id_solicitud=$1 FOR UPDATE",[$id_solicitud]);
        if(!$lock || pg_num_rows($lock)===0){ pg_query($conn,'ROLLBACK'); json_error('Solicitud no encontrada'); }
        $est = strtolower(pg_fetch_result($lock,0,'estado'));
        if (in_array($est,['consumida','descartada'],true)){ pg_query($conn,'ROLLBACK'); json_error('La solicitud ya no admite cambios'); }

        $r = pg_query_params($conn, "
          INSERT INTO public.solicitud_cliente_item (id_solicitud,id_producto,cantidad)
          VALUES ($1,$2,$3)
          ON CONFLICT (id_solicitud,id_producto)
          DO UPDATE SET cantidad = public.solicitud_cliente_item.cantidad + EXCLUDED.cantidad
          RETURNING id_item, id_producto, cantidad
        ", [$id_solicitud,$id_producto,$cantidad]);
        if(!$r){ pg_query($conn,'ROLLBACK'); json_error('No se pudo agregar/actualizar el ítem'); }
        $row = pg_fetch_assoc($r);
        pg_query($conn,'COMMIT');
        json_ok(['item'=>$row]);
      }

      case 'set_item_qty': { // setea cantidad exacta (0 = elimina)
        $id_solicitud  = num($in['id_solicitud'] ?? 0);
        $id_producto   = num($in['id_producto'] ?? 0);
        $new_cantidad  = (float)($in['new_cantidad'] ?? -1);
        if ($id_solicitud<=0 || $id_producto<=0 || $new_cantidad<0) json_error('Parámetros inválidos');

        pg_query($conn,'BEGIN');
        $lock = pg_query_params($conn,"SELECT estado FROM public.solicitud_cliente WHERE id_solicitud=$1 FOR UPDATE",[$id_solicitud]);
        if(!$lock || pg_num_rows($lock)===0){ pg_query($conn,'ROLLBACK'); json_error('Solicitud no encontrada'); }
        $est = strtolower(pg_fetch_result($lock,0,'estado'));
        if (in_array($est,['consumida','descartada'],true)){ pg_query($conn,'ROLLBACK'); json_error('La solicitud ya no admite cambios'); }

        if ($new_cantidad == 0) {
          $r = pg_query_params($conn,"DELETE FROM public.solicitud_cliente_item WHERE id_solicitud=$1 AND id_producto=$2",[$id_solicitud,$id_producto]);
          if($r===false){ pg_query($conn,'ROLLBACK'); json_error('No se pudo eliminar ítem'); }
          pg_query($conn,'COMMIT');
          json_ok(['deleted'=>true]);
        } else {
          $r = pg_query_params($conn,"
            UPDATE public.solicitud_cliente_item
               SET cantidad=$3, updated_at=NOW()
             WHERE id_solicitud=$1 AND id_producto=$2
            RETURNING id_item, id_producto, cantidad
          ",[$id_solicitud,$id_producto,$new_cantidad]);
          if(!$r || pg_num_rows($r)===0){ pg_query($conn,'ROLLBACK'); json_error('Ítem no encontrado para actualizar'); }
          $row = pg_fetch_assoc($r);
          pg_query($conn,'COMMIT');
          json_ok(['item'=>$row]);
        }
      }

      case 'remove_item': {
        $id_solicitud = num($in['id_solicitud'] ?? 0);
        $id_producto  = num($in['id_producto'] ?? 0);
        if ($id_solicitud<=0 || $id_producto<=0) json_error('Parámetros inválidos');
        $r = pg_query_params($conn,"DELETE FROM public.solicitud_cliente_item WHERE id_solicitud=$1 AND id_producto=$2",[$id_solicitud,$id_producto]);
        if($r===false) json_error('No se pudo eliminar el ítem');
        json_ok(['deleted'=>true]);
      }

      case 'set_estado': {
        $id_solicitud = num($in['id_solicitud'] ?? 0);
        $estado = strtolower(s($in['estado'] ?? ''));
        $valid = ['abierta','lista','consumida','descartada'];
        if ($id_solicitud<=0 || !in_array($estado,$valid,true)) json_error('Estado inválido');
        $r = pg_query_params($conn,"UPDATE public.solicitud_cliente SET estado=INITCAP($1), updated_at=NOW() WHERE id_solicitud=$2",[$estado,$id_solicitud]);
        if(!$r) json_error('No se pudo actualizar estado');
        json_ok(['id_solicitud'=>$id_solicitud,'estado'=>$estado]);
      }

      case 'get': {
        $id_solicitud = num($in['id_solicitud'] ?? 0);
        if ($id_solicitud<=0) json_error('id_solicitud requerido');

        $h = pg_query_params($conn,"SELECT id_solicitud,id_cliente,estado,notas,created_at,updated_at FROM public.solicitud_cliente WHERE id_solicitud=$1",[$id_solicitud]);
        if(!$h || pg_num_rows($h)===0) json_error('Solicitud no encontrada');
        $head = pg_fetch_assoc($h);

        $sql = "
          WITH disp AS ($SQL_DISP)
          SELECT i.id_item, i.id_producto, p.nombre, p.tipo_item, p.precio_unitario,
                 i.cantidad,
                 COALESCE(d.disponible,0)::numeric(14,2) AS disponible
          FROM public.solicitud_cliente_item i
          JOIN public.producto p ON p.id_producto = i.id_producto
          LEFT JOIN disp d ON d.id_producto = i.id_producto
          WHERE i.id_solicitud = $1
          ORDER BY p.nombre
        ";
        $ri = pg_query_params($conn,$sql,[$id_solicitud]);
        if($ri===false) json_error('No se pudo obtener ítems');
        $items=[]; while($row=pg_fetch_assoc($ri)) $items[]=$row;

        json_ok(['solicitud'=>$head,'items'=>$items]);
      }

      case 'preview_import': {
        $id_solicitud = num($in['id_solicitud'] ?? 0);
        if ($id_solicitud<=0) json_error('id_solicitud requerido');
        $sql = "
          WITH disp AS ($SQL_DISP)
          SELECT i.id_producto, p.nombre,
                 i.cantidad AS qty_pedida,
                 GREATEST(COALESCE(d.disponible,0),0)::numeric(14,2) AS qty_disponible,
                 LEAST(i.cantidad, GREATEST(COALESCE(d.disponible,0),0))::numeric(14,2) AS qty_sugerida,
                 p.tipo_item, p.precio_unitario
          FROM public.solicitud_cliente_item i
          JOIN public.producto p ON p.id_producto = i.id_producto
          LEFT JOIN disp d ON d.id_producto = i.id_producto
          WHERE i.id_solicitud = $1
          ORDER BY p.nombre
        ";
        $r = pg_query_params($conn,$sql,[$id_solicitud]);
        if($r===false) json_error('No se pudo preparar preview');
        $items=[]; $stats=['agregables'=>0,'sin_stock'=>0];
        while($row=pg_fetch_assoc($r)){
          $row['qty_pedida']=(float)$row['qty_pedida'];
          $row['qty_disponible']=(float)$row['qty_disponible'];
          $row['qty_sugerida']=(float)$row['qty_sugerida'];
          if ($row['qty_sugerida']>0) $stats['agregables']++; else $stats['sin_stock']++;
          $items[]=$row;
        }
        json_ok(['items'=>$items,'stats'=>$stats]);
      }

      case 'buscar_productos': { // catálogo completo (peluquería)
        $q  = mb_strtolower(s($in['q'] ?? '')) ?? '';
        $ps = max(1, min(500, num($in['page_size'] ?? 500)));
        $off= max(0, num($in['offset'] ?? 0));
        $likeAny = '%'.$q.'%';
        $sql = "
          SELECT id_producto, nombre, precio_unitario
          FROM public.producto
          WHERE estado='Activo'
            AND tipo_item='P'
            AND (categoria ILIKE 'pelu%' OR categoria ILIKE 'peluquer%' OR categoria ILIKE '%pelu%')
            AND ($1 = '' OR lower(nombre) LIKE $2)
          ORDER BY nombre ASC
          LIMIT $3 OFFSET $4
        ";
        $r = pg_query_params($conn,$sql,[$q,$likeAny,$ps,$off]);
        if(!$r) json_error('No se pudo buscar productos');
        $rows=[]; while($x=pg_fetch_assoc($r)) $rows[]=$x;
        json_ok(['rows'=>$rows]);
      }

      default: json_error('op no reconocido');
    }
  } catch(Throwable $e){
    json_error($e->getMessage());
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Solicitud de Cliente</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" type="text/css" href="../css/styles_servicios.css" />
<style>
  :root{ --g:#10b981; --r:#ef4444; --b:#111; --shadow:0 10px 20px rgba(0,0,0,.12); }
  body{ font-family:system-ui,Segoe UI,Roboto,Arial; margin:20px; color:#111 }
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
  .grid{ display:grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr)); gap:10px; }
  .prod{ border:1px solid #e5e7eb; border-radius:10px; padding:10px; background:#fff; transition:transform .05s; cursor:pointer }
  .prod:hover{ transform:scale(1.01) }
  .prod h4{ margin:0 0 6px; font-size:14px; }
  .prod .price{ font-weight:600; }
  .qtybtn{ padding:4px 8px; border-radius:6px; margin:0 3px; }
  .danger{ background:#fee2e2; color:#991b1b; }
  .muted{ color:#6b7280; font-size:12px }

  /* ===== Toast centrado en pantalla ===== */
  .toast-layer{
    position: fixed !important;
    inset: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    pointer-events: none !important;
    z-index: 2147483647 !important;
  }
  .toast-container{
    display: flex !important;
    flex-direction: column !important;
    gap: 10px !important;
    max-width: min(90vw, 480px) !important;
    pointer-events: none !important;
  }
  .toast{
    pointer-events: auto !important;
    display:flex; align-items:flex-start; gap:10px;
    background:#111; color:#fff; border-radius:10px;
    box-shadow: var(--shadow);
    padding:12px 14px; min-width:260px;
    animation: toastIn .18s ease-out;
  }
  .toast.error{ background:#b91c1c; }
  .toast .title{ font-weight:700; margin-bottom:2px; }
  .toast .msg{ opacity:.95 }
  .toast .close{
    margin-left:auto; background:transparent; border:0; color:#fff;
    font-size:18px; line-height:1; cursor:pointer;
  }
  @keyframes toastIn{ from{ transform:translateY(-6px); opacity:0 } to{ transform:translateY(0); opacity:1 } }
  @keyframes toastOut{ to{ transform:translateY(-6px); opacity:0 } }
</style>
</head>
<body>
  <!-- 1) Buscar cliente -->
  <div class="card">
    <h3>1) Buscar Cliente</h3>
    <div class="row">
      <div>
        <label>Nombre o RUC/CI</label>
        <input id="q_cliente" placeholder="Ej: Ana López o 1234567-8" />
      </div>
      <div style="flex:0">
        <button onclick="buscarClientes()">Buscar</button>
      </div>
      <div>
        <label>Resultados</label>
        <select id="sel_cliente"></select>
      </div>
      <div style="flex:0">
        <button class="sec" onclick="usarCliente()">Usar cliente</button>
      </div>
      <div>
        <label>ID Cliente seleccionado</label>
        <input id="id_cliente" type="number" placeholder="Id cliente" />
      </div>
    </div>
  </div>

  <!-- 2) Tarjetas de productos (peluquería) -->
  <div class="card">
    <h3>2) Productos (Peluquería)</h3>
    <div class="muted">Clic en una tarjeta = agrega 1 a la grilla.</div>
    <div id="catalogo" class="grid" style="margin-top:10px"></div>
  </div>

  <!-- 3) Grilla (carrito local) -->
  <div class="card">
    <h3>3) Grilla</h3>
    <table>
      <thead>
        <tr>
          <th>Producto</th><th>Cant.</th><th style="width:160px">Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody"></tbody>
    </table>
    <div class="muted">Ajustá con ＋ / − o quita con 🗑. Aún no guarda en la base.</div>
  </div>

  <!-- 4) Crear Solicitud -->
  <div class="card">
    <h3>4) Crear Solicitud</h3>
    <div class="row">
      <div>
        <label>Notas</label>
        <input id="notas" placeholder="Ej: quiere línea nutritiva" />
      </div>
      <div>
        <label>ID Solicitud (resultado)</label>
        <input id="id_solicitud" type="number" placeholder="Se completará al crear" />
      </div>
      <div style="flex:0">
        <button id="btnCrear" onclick="crearSolicitud()">Crear Solicitud</button>
      </div>
      <div style="flex:0">
        <button class="sec" onclick="verSolicitud()">Ver/Refrescar Solicitud</button>
      </div>
    </div>
  </div>

  <!-- Capa y contenedor del Toast -->
  <div class="toast-layer" aria-live="polite" aria-atomic="true">
    <div class="toast-container" id="toasts"></div>
  </div>

<script>
const API = location.pathname;
const CLIENTES_API = '../../venta_v3/cliente/clientes_buscar.php'; // <-- AJUSTAR

let catalogo = [];           // [{id_producto, nombre, precio_unitario}]
let cart = {};               // { [id_producto]: qty }
const $ = (id)=>document.getElementById(id);

/* ===== Toast ===== */
function showToast(message, type='success', title=null, timeout=2200){
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

/* ===== Helpers ===== */
function fmt(n){ return Number(n).toLocaleString() }
async function api(op, payload={}){
  const res = await fetch(API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({op, ...payload}) });
  const data = await res.json();
  if (!data.success) throw new Error(data.error||'Error');
  return data;
}

/* ===== Reset de UI tras crear ===== */
function resetSolicitudUI(){
  // Inputs y selects
  $('q_cliente').value = '';
  $('id_cliente').value = '';
  $('sel_cliente').innerHTML = '';
  $('notas').value = '';
  $('id_solicitud').value = '';

  // Grilla y carrito
  cart = {};
  renderGrilla();

  // (Opcional) recargar catálogo si querés refrescar precios/nombres
  // cargarCatalogo();
}

/* ===== 1) CLIENTES ===== */
async function buscarClientes(){
  try{
    const q = $('q_cliente').value.trim();
    const url = `${CLIENTES_API}?q=${encodeURIComponent(q)}&page=1&page_size=10`;
    const res = await fetch(url, { headers:{'Accept':'application/json'} });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error||'Error al buscar clientes');
    const sel = $('sel_cliente'); sel.innerHTML='';
    (data.data||[]).forEach(c=>{
      const opt = document.createElement('option');
      opt.value = c.id_cliente;
      opt.textContent = `${c.nombre_completo} — ${c.ruc_ci||'-'}`;
      sel.appendChild(opt);
    });
    showToast(`Encontrados ${data.data?.length||0} clientes.`, 'success');
  }catch(e){
    showToast(e.message, 'error');
  }
}
function usarCliente(){
  const id = $('sel_cliente').value ? Number($('sel_cliente').value) : 0;
  if (id>0){ $('id_cliente').value = id; showToast('Cliente seleccionado.'); }
}

/* ===== 2) CATÁLOGO (peluquería) ===== */
async function cargarCatalogo(){
  try{
    const r = await api('buscar_productos', { q:'', page_size: 500, offset: 0 });
    catalogo = r.rows||[];
    renderCatalogo();
    showToast(`Catálogo cargado (${catalogo.length} ítems).`, 'success');
  }catch(e){
    showToast(e.message, 'error');
  }
}
function renderCatalogo(){
  const cont = $('catalogo'); cont.innerHTML='';
  catalogo.forEach(p=>{
    const card = document.createElement('div');
    card.className = 'prod';
    card.onclick = ()=>agregarLocal(p.id_producto, 1);
    card.innerHTML = `
      <h4>${p.nombre}</h4>
      <div class="price">Gs ${fmt(p.precio_unitario)}</div>
      <div class="muted">Clic para agregar ＋1</div>
    `;
    cont.appendChild(card);
  });
}

/* ===== 3) GRILLA (local) ===== */
function agregarLocal(id_producto, qty){
  cart[id_producto] = (cart[id_producto]||0) + qty;
  if (cart[id_producto] <= 0) delete cart[id_producto];
  renderGrilla();
}
function renderGrilla(){
  const tbody = $('tbody'); tbody.innerHTML='';
  const ids = Object.keys(cart).map(Number);
  ids.forEach(id=>{
    const prod = catalogo.find(p=>p.id_producto==id);
    const nombre = prod ? prod.nombre : `#${id}`;
    const cant = cart[id];
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${nombre}</td>
      <td>${cant}</td>
      <td>
        <button class="qtybtn" onclick="agregarLocal(${id}, +1)">＋</button>
        <button class="qtybtn" onclick="agregarLocal(${id}, -1)">－</button>
        <button class="qtybtn danger" onclick="quitarLocal(${id})">🗑</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}
function quitarLocal(id){ delete cart[id]; renderGrilla(); }

/* ===== 4) CREAR SOLICITUD ===== */
async function crearSolicitud(){
  const btn = $('btnCrear');
  try{
    const id_cliente = Number($('id_cliente').value||0);
    if(!id_cliente) throw new Error('Seleccione un cliente');
    const ids = Object.keys(cart);
    if(ids.length===0) throw new Error('La grilla está vacía');

    // evitar doble clic
    btn.disabled = true;
    btn.textContent = 'Creando...';

    // 4.1 crear cabecera
    const rCab = await api('create', { id_cliente, notas: $('notas').value||null });
    const id_solicitud = rCab.solicitud?.id_solicitud;
    if(!id_solicitud) throw new Error('No se obtuvo id_solicitud');
    $('id_solicitud').value = id_solicitud;

    // 4.2 subir ítems
    for(const id of ids){
      const qty = cart[id];
      await api('add_item', { id_solicitud, id_producto: Number(id), cantidad: Number(qty) });
    }

    // 4.3 feedback y reset (dejamos ver el toast y luego limpiamos)
    showToast(`Solicitud #${id_solicitud} creada.`, 'success', '¡Listo!');
    setTimeout(()=>{ resetSolicitudUI(); }, 1200);
  }catch(e){
    showToast(e.message, 'error');
  }finally{
    btn.disabled = false;
    btn.textContent = 'Crear Solicitud';
  }
}

/* Ver/Refrescar solicitud desde DB */
async function verSolicitud(){
  try{
    const id = Number($('id_solicitud').value||0);
    if(!id) throw new Error('No hay id_solicitud');
    const r = await api('get', { id_solicitud:id });

    const tbody = $('tbody'); tbody.innerHTML='';
    (r.items||[]).forEach(row=>{
      const cant = Number(row.cantidad);
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${row.nombre}</td>
        <td>${cant}</td>
        <td>
          <button class="qtybtn" onclick="updateQty(${row.id_producto}, ${cant+1})">＋</button>
          <button class="qtybtn" onclick="updateQty(${row.id_producto}, ${Math.max(0,cant-1)})">－</button>
          <button class="qtybtn danger" onclick="removeSrv(${row.id_producto})">🗑</button>
        </td>
      `;
      tbody.appendChild(tr);
    });
    showToast('Solicitud cargada.', 'success');
  }catch(e){
    showToast(e.message, 'error');
  }
}

/* Ajustes de ítems ya guardados en DB */
async function updateQty(id_producto, new_cantidad){
  try{
    const id = Number($('id_solicitud').value||0);
    await api('set_item_qty', { id_solicitud:id, id_producto, new_cantidad });
    await verSolicitud();
  }catch(e){
    showToast(e.message, 'error');
  }
}
async function removeSrv(id_producto){
  try{
    const id = Number($('id_solicitud').value||0);
    await api('remove_item', { id_solicitud:id, id_producto });
    await verSolicitud();
  }catch(e){
    showToast(e.message, 'error');
  }
}

/* Inicial */
window.addEventListener('DOMContentLoaded', ()=>{
  cargarCatalogo(); // carga todo el catálogo de peluquería apenas abre
});
</script>
</body>
</html>
