<?php
// ui_promos.php — UI + API para administrar promociones/descuentos (producto.tipo_item = 'D')
// Requisitos previos en la BD:
//   ALTER TABLE public.producto DROP CONSTRAINT producto_tipo_item_chk;
//   ALTER TABLE public.producto ADD CONSTRAINT producto_tipo_item_chk
//     CHECK (tipo_item = ANY (ARRAY['P'::bpchar,'S'::bpchar,'D'::bpchar]));
//
//   ALTER TABLE public.producto DROP CONSTRAINT producto_precios_chk;
//   ALTER TABLE public.producto ADD CONSTRAINT producto_precios_chk
//     CHECK (
//       (tipo_item IN ('P','S') AND precio_unitario >= 0::numeric AND precio_compra >= 0::numeric)
//       OR
//       (tipo_item = 'D' AND precio_unitario <= 0::numeric AND precio_compra >= 0::numeric)
//     );
//
// El usuario ingresa montos negativos; en la BD se guardan tal cual (precio_unitario <= 0).

session_start();
require_once __DIR__ . '/../../conexion/configv2.php'; // <-- AJUSTAR si tu config vive en otra ruta
header('X-Content-Type-Options: nosniff');

function json_error($msg, $code = 400){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success'=>false,'error'=>$msg]);
  exit;
}
function json_ok($data = []){
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success'=>true]+$data);
  exit;
}
function s($x){ return is_string($x) ? trim($x) : null; }
function n($x){ return is_numeric($x) ? 0 + $x : 0; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $in = json_decode(file_get_contents('php://input'), true);
  if(!$in) $in = $_POST;
  $op = strtolower(s($in['op'] ?? ''));
  if($op === '') json_error('op requerido');

  try{
    switch ($op) {
      case 'list': {
        $sql = "SELECT id_producto, nombre, precio_unitario, estado, tipo_iva, categoria
                  FROM public.producto
                 WHERE tipo_item='D'
                 ORDER BY nombre";
        $r = pg_query($conn, $sql);
        if(!$r) json_error('No se pudieron listar las promos');

        $rows = [];
        while($row = pg_fetch_assoc($r)){
          $row['precio_unitario'] = (float)$row['precio_unitario'];
          $row['monto_descuento'] = $row['precio_unitario']; // ya negativo
          $rows[] = $row;
        }
        json_ok(['rows'=>$rows]);
      }

      case 'save': {
        $id        = (int)($in['id_producto'] ?? 0);
        $nombre    = s($in['nombre'] ?? '');
        $monto     = n($in['monto_descuento'] ?? 0); // esperamos negativo
        $estado    = s($in['estado'] ?? 'Activo');
        $tipoIva   = s($in['tipo_iva'] ?? 'EXE');   // usamos códigos cortos: 10%, 5%, EXE
        $categoria = s($in['categoria'] ?? null);

        if($nombre === '') json_error('Nombre requerido');
        if(!in_array($estado,['Activo','Inactivo'],true)) json_error('Estado inválido');
        if(!in_array($tipoIva,['10%','5%','EXE'],true)) json_error('IVA inválido');
        if($monto >= 0) json_error('El monto debe ser negativo (ej. -50000)');

        $params = [
          $nombre,
          $monto,    // negativo
          0,         // precio_compra siempre 0 para promos
          $estado,
          $tipoIva,
          $categoria
        ];

        if($id > 0){
          $params[] = $id;
          $sql = "UPDATE public.producto
                     SET nombre=$1,
                         precio_unitario=$2,
                         precio_compra=$3,
                         estado=$4,
                         tipo_iva=$5,
                         categoria=$6
                   WHERE id_producto=$7 AND tipo_item='D'";
          $res = pg_query_params($conn,$sql,$params);
        }else{
          $sql = "INSERT INTO public.producto
                    (nombre, precio_unitario, precio_compra, estado, tipo_iva, categoria, tipo_item, duracion_min)
                  VALUES ($1,$2,$3,$4,$5,$6,'D',30)
                  RETURNING id_producto";
          $res = pg_query_params($conn,$sql,$params);
          if($res) $id = (int)pg_fetch_result($res,0,0);
        }

        if(!$res) json_error('No se pudo guardar la promoción');
        json_ok(['id_producto'=>$id]);
      }

      case 'toggle': {
        $id = (int)($in['id_producto'] ?? 0);
        if($id <= 0) json_error('id_producto requerido');

        $sql = "UPDATE public.producto
                   SET estado = CASE WHEN estado='Activo' THEN 'Inactivo' ELSE 'Activo' END
                 WHERE id_producto=$1 AND tipo_item='D'
                 RETURNING estado";
        $res = pg_query_params($conn,$sql,[$id]);
        if(!$res || pg_num_rows($res) === 0) json_error('No se pudo cambiar el estado');
        json_ok(['estado'=>pg_fetch_result($res,0,0)]);
      }

      case 'delete': {
        $id = (int)($in['id_producto'] ?? 0);
        if($id <= 0) json_error('id_producto requerido');

        $sql = "DELETE FROM public.producto WHERE id_producto=$1 AND tipo_item='D'";
        $res = pg_query_params($conn,$sql,[$id]);
        if(!$res) json_error('No se pudo eliminar (verificá uso en reservas/OT)');
        json_ok();
      }

      default:
        json_error('op no reconocido');
    }
  }catch(Throwable $e){
    json_error($e->getMessage());
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Promociones / Descuentos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" type="text/css" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/css/styles_servicios.css" />
<style>
  :root{ --shadow:0 8px 20px rgba(0,0,0,.12); --gray:#6b7280; }
  body{ font-family:system-ui,Segoe UI,Roboto,Arial; margin:20px; color:#111; background:#fff; }
  h1{ margin:0 0 20px; }
  .flex{ display:flex; gap:16px; flex-wrap:wrap; align-items:flex-start; }
  .card{ flex:1; min-width:300px; border:1px solid #e5e7eb; border-radius:12px; padding:16px; background:#fff; box-shadow:0 4px 12px rgba(15,23,42,.06); }
  label{ display:block; font-size:12px; text-transform:uppercase; color:#4b5563; margin-bottom:4px; letter-spacing:.06em; }
  input, select, button{
    width:100%; padding:9px 12px; border:1px solid #d1d5db; border-radius:8px;
    font-size:14px; transition:border .12s ease, box-shadow .12s ease;
  }
  input:focus, select:focus{ outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(59,130,246,.2); }
  button{ width:auto; cursor:pointer; border:none; background:#111; color:#fff; }
  button.sec{ background:#fff; color:#111; border:1px solid #d1d5db; }
  button.danger{ background:#dc2626; }
  table{ width:100%; border-collapse:collapse; margin-top:12px; font-size:14px; }
  th,td{ border-bottom:1px solid #e5e7eb; padding:10px 8px; text-align:left; }
  th{ background:#f3f4f6; font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#4b5563; }
  tr:hover td{ background:#f9fafb; }
  .badge{ display:inline-flex; align-items:center; gap:4px; font-size:12px; padding:2px 8px; border-radius:999px; }
  .badge.activo{ background:#dcfce7; color:#14532d; }
  .badge.inactivo{ background:#fee2e2; color:#991b1b; }
  .muted{ color:#6b7280; font-size:12px; }
  .toast-layer{ position:fixed; inset:0; pointer-events:none; display:grid; place-items:flex-end center; padding:24px; z-index:9999; }
  .toast{ pointer-events:auto; min-width:300px; padding:14px 18px; background:#111; color:#fff; border-radius:12px; margin-top:12px; box-shadow:var(--shadow); display:flex; justify-content:space-between; gap:12px; animation:toast-in .18s ease-out both; }
  .toast.error{ background:#b91c1c; }
  .toast button{ background:transparent; color:#fff; border:none; font-size:18px; cursor:pointer; padding:0; }
  @keyframes toast-in{ from{ transform:translateY(12px); opacity:0 } to{ transform:translateY(0); opacity:1 } }
</style>
</head>
<body>
    <div id="navbar-container"></div>
<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/navbar/navbar.js"></script>
  <h1>Promociones / Descuentos</h1>
  <div class="flex">
    <div class="card" style="max-width:420px">
      <h2 style="margin:0 0 14px">Nueva promoción</h2>
      <form id="formPromo">
        <input type="hidden" id="id_producto">
        <div style="margin-bottom:12px">
          <label>Nombre</label>
          <input id="nombre" placeholder="Ej: Promo Corte 20%">
        </div>
        <div style="margin-bottom:12px">
          <label>Monto (Gs)</label>
          <input id="monto_descuento" type="number" step="1" placeholder="-50000">
          <div class="muted">Ingresá el valor negativo que se restará al total.</div>
        </div>
        <div style="margin-bottom:12px">
          <label>Estado</label>
          <select id="estado">
            <option value="Activo">Activo</option>
            <option value="Inactivo">Inactivo</option>
          </select>
        </div>
        <div style="margin-bottom:12px">
          <label>IVA</label>
          <select id="tipo_iva">
            <option value="EXE">Exento</option>
            <option value="10%">10%</option>
            <option value="5%">5%</option>
          </select>
        </div>
        <div style="margin-bottom:16px">
          <label>Categoría (opcional)</label>
          <input id="categoria" placeholder="Ej: Promo Primavera">
        </div>
        <div style="display:flex; gap:10px">
          <button type="submit">Guardar promoción</button>
          <button type="button" class="sec" onclick="resetForm()">Limpiar</button>
        </div>
      </form>
    </div>
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
        <div>
          <h2 style="margin:0">Listado</h2>
          <div class="muted">Promos activas e inactivas (tipo_item = 'D')</div>
        </div>
        <button class="sec" onclick="cargarPromos()">Recargar</button>
      </div>
      <table>
        <thead>
          <tr>
            <th>Nombre / estado</th>
            <th>Monto (Gs)</th>
            <th>IVA</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="tbodyPromos"></tbody>
      </table>
    </div>
  </div>

  <div class="toast-layer"><div id="toasts"></div></div>

<script>
const API = location.pathname;
const $ = (id) => document.getElementById(id);

function showToast(msg, type='ok'){
  const layer = $('toasts');
  const el = document.createElement('div');
  el.className = 'toast' + (type==='error' ? ' error' : '');
  el.innerHTML = `<span>${msg}</span><button onclick="this.parentElement.remove()">×</button>`;
  layer.appendChild(el);
  setTimeout(()=>{ el.remove(); }, 3000);
}

async function api(op, payload={}){
  const res = await fetch(API, {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({op, ...payload})
  });
  const data = await res.json();
  if(!data.success) throw new Error(data.error || 'Error inesperado');
  return data;
}

async function cargarPromos(){
  try{
    const { rows } = await api('list');
    const tbody = $('tbodyPromos');
    tbody.innerHTML = '';
    rows.forEach(p=>{
      const badge = `<span class="badge ${p.estado === 'Activo' ? 'activo':'inactivo'}">${p.estado}</span>`;
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <strong>${p.nombre}</strong><br>
          <span class="muted">ID ${p.id_producto} · ${badge}</span>
        </td>
        <td>${Number(p.monto_descuento).toLocaleString()}</td>
        <td>${p.tipo_iva}</td>
        <td>
          <button class="sec" onclick='editar(${JSON.stringify(p)})'>Editar</button>
          <button class="sec" onclick="toggleEstado(${p.id_producto})">${p.estado === 'Activo' ? 'Inactivar' : 'Activar'}</button>
          <button class="danger" onclick="eliminar(${p.id_producto})">Eliminar</button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }catch(e){ showToast(e.message,'error'); }
}

function editar(p){
  $('id_producto').value = p.id_producto;
  $('nombre').value = p.nombre;
  $('monto_descuento').value = p.monto_descuento;
  $('estado').value = p.estado;
  $('tipo_iva').value = p.tipo_iva || 'EXE';
  $('categoria').value = p.categoria || '';
  showToast('Promo cargada para edición');
}

function resetForm(){
  $('formPromo').reset();
  $('id_producto').value = '';
  $('estado').value = 'Activo';
  $('tipo_iva').value = 'EXE';
}

async function toggleEstado(id){
  if(!confirm('¿Cambiar el estado de esta promoción?')) return;
  try{
    const { estado } = await api('toggle',{id_producto:id});
    showToast('Nuevo estado: '+estado);
    cargarPromos();
  }catch(e){ showToast(e.message,'error'); }
}

async function eliminar(id){
  if(!confirm('¿Eliminar la promoción? Esta acción no se puede revertir.')) return;
  try{
    await api('delete',{id_producto:id});
    showToast('Promoción eliminada');
    cargarPromos();
  }catch(e){ showToast(e.message,'error'); }
}

$('formPromo').addEventListener('submit', async ev=>{
  ev.preventDefault();
  try{
    const payload = {
      id_producto: $('id_producto').value || null,
      nombre: $('nombre').value,
      monto_descuento: Number($('monto_descuento').value || 0),
      estado: $('estado').value,
      tipo_iva: $('tipo_iva').value,
      categoria: $('categoria').value
    };
    await api('save', payload);
    showToast('Promoción guardada');
    resetForm();
    cargarPromos();
  }catch(e){ showToast(e.message,'error'); }
});

window.addEventListener('DOMContentLoaded', cargarPromos);
</script>
</body>
</html>
