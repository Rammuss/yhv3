<?php
// informe_facturas.php
// UI + Endpoints JSON + Export CSV para informes de factura_compra_cab / factura_compra_det
// Requiere un config que defina $host,$port,$dbname,$user,$password
require_once(__DIR__ . "../../../conexion/configv2.php");

// ---------- Conexión ----------
$c = @pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$c) {
  http_response_code(500);
  echo "Error de conexión a la BD";
  exit;
}

// ---------- Util ----------
function json_exit($arr, $code=200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}
function csv_out($filename, $rows, $headers){
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
  $out = fopen('php://output', 'w');
  // BOM UTF-8
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
  fputcsv($out, $headers);
  foreach($rows as $r){ fputcsv($out, $r); }
  fclose($out);
  exit;
}
function like_or_null($v){ return $v!=='' ? "%$v%" : null; }

// ---------- Router ----------
$action = $_GET['action'] ?? '';
if ($action === 'proveedores') {
  // Autocomplete / opciones de proveedores activos (ajustar campo nombre si difiere)
  $q = trim($_GET['q'] ?? '');
  $sql = "
    SELECT p.id_proveedor, p.nombre
    FROM public.proveedores p
    WHERE ($1 = '' OR unaccent(p.nombre) ILIKE unaccent('%'||$1||'%'))
    ORDER BY p.nombre ASC
    LIMIT 50
  ";
  $rs = pg_query_params($c, $sql, [$q]);
  $out = [];
  if ($rs) while ($r = pg_fetch_assoc($rs)) $out[] = $r;
  json_exit(["ok"=>true, "items"=>$out]);
}

if ($action === 'listar') {
  // Listado con filtros + paginación + agrupación (sin export)
  $page = max(1, (int)($_GET['page'] ?? 1));
  $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
  $offset = ($page - 1) * $limit;

  $fd = $_GET['fdesde'] ?? ''; // YYYY-MM-DD
  $fh = $_GET['fhasta'] ?? '';
  $prov = (int)($_GET['id_proveedor'] ?? 0);
  $estado = $_GET['estado'] ?? '';
  $suc = (int)($_GET['id_sucursal'] ?? 0);
  $moneda = $_GET['moneda'] ?? '';
  $nrodoc = trim($_GET['numero_documento'] ?? '');
  $timbr = trim($_GET['timbrado_numero'] ?? '');
  $grupo = $_GET['group_by'] ?? ''; // proveedor | mes | sucursal | ''

  // WHERE dinámico
  $w = []; $p = [];
  if ($fd !== '') { $p[] = $fd; $w[] = "cab.fecha_emision >= $".count($p); }
  if ($fh !== '') { $p[] = $fh; $w[] = "cab.fecha_emision <= $".count($p); }
  if ($prov > 0) { $p[] = $prov; $w[] = "cab.id_proveedor = $".count($p); }
  if ($estado !== '') { $p[] = $estado; $w[] = "cab.estado = $".count($p); }
  if ($suc > 0) { $p[] = $suc; $w[] = "cab.id_sucursal = $".count($p); }
  if ($moneda !== '') { $p[] = $moneda; $w[] = "cab.moneda = $".count($p); }
  if ($nrodoc !== '') { $p[] = like_or_null($nrodoc); $w[] = "cab.numero_documento ILIKE $".count($p); }
  if ($timbr !== '') { $p[] = like_or_null($timbr); $w[] = "cab.timbrado_numero::text ILIKE $".count($p); }
  $where = $w ? ("WHERE ".implode(" AND ", $w)) : "";

  // Campos de agrupación
  $grp_select = "cab.id_factura, cab.fecha_emision, cab.numero_documento, cab.moneda, cab.estado, cab.total_factura, cab.timbrado_numero, cab.id_sucursal, cab.id_proveedor, COALESCE(p.nombre,'(sin nombre)') AS proveedor";
  $grp_group  = "cab.id_factura, cab.fecha_emision, cab.numero_documento, cab.moneda, cab.estado, cab.total_factura, cab.timbrado_numero, cab.id_sucursal, cab.id_proveedor, p.nombre";

  $agg_base = "
    SUM(CASE WHEN (d.tipo_iva ILIKE '10%' OR d.tipo_iva ILIKE '10') THEN d.subtotal ELSE 0 END) AS grav10,
    SUM(CASE WHEN (d.tipo_iva ILIKE '5%'  OR d.tipo_iva ILIKE '5')  THEN d.subtotal ELSE 0 END) AS grav5,
    SUM(CASE WHEN (COALESCE(d.tipo_iva,'') NOT ILIKE '10%' AND COALESCE(d.tipo_iva,'') NOT ILIKE '10'
               AND COALESCE(d.tipo_iva,'') NOT ILIKE '5%'  AND COALESCE(d.tipo_iva,'') NOT ILIKE '5') THEN d.subtotal ELSE 0 END) AS exentas,
    SUM(CASE WHEN (d.tipo_iva ILIKE '10%' OR d.tipo_iva ILIKE '10') THEN d.subtotal*0.10
             WHEN (d.tipo_iva ILIKE '5%'  OR d.tipo_iva ILIKE '5')  THEN d.subtotal*0.05
             ELSE 0 END) AS iva_calc,
    SUM(d.subtotal) AS total_subtotal
  ";

  if ($grupo === 'proveedor') {
    $select = "
      SELECT cab.id_proveedor, COALESCE(p.nombre,'(sin nombre)') AS proveedor,
             COUNT(DISTINCT cab.id_factura)::int AS cant_facturas,
             SUM(cab.total_factura)::numeric(14,2) AS total_facturas,
             $agg_base
      FROM public.factura_compra_cab cab
      JOIN public.factura_compra_det d ON d.id_factura = cab.id_factura
      LEFT JOIN public.proveedores p ON p.id_proveedor = cab.id_proveedor
      $where
      GROUP BY cab.id_proveedor, p.nombre
      ORDER BY proveedor ASC
      LIMIT $limit OFFSET $offset
    ";
    $count_sql = "
      SELECT COUNT(*) FROM (
        SELECT cab.id_proveedor
        FROM public.factura_compra_cab cab
        JOIN public.factura_compra_det d ON d.id_factura = cab.id_factura
        LEFT JOIN public.proveedores p ON p.id_proveedor = cab.id_proveedor
        $where
        GROUP BY cab.id_proveedor, p.nombre
      ) x
    ";
  } elseif ($grupo === 'mes') {
    $select = "
      SELECT EXTRACT(YEAR FROM cab.fecha_emision)::int AS anio,
             EXTRACT(MONTH FROM cab.fecha_emision)::int AS mes,
             COUNT(DISTINCT cab.id_factura)::int AS cant_facturas,
             SUM(cab.total_factura)::numeric(14,2) AS total_facturas,
             $agg_base
      FROM public.factura_compra_cab cab
      JOIN public.factura_compra_det d ON d.id_factura = cab.id_factura
      $where
      GROUP BY anio, mes
      ORDER BY anio DESC, mes DESC
      LIMIT $limit OFFSET $offset
    ";
    $count_sql = "
      SELECT COUNT(*) FROM (
        SELECT EXTRACT(YEAR FROM cab.fecha_emision)::int AS anio,
               EXTRACT(MONTH FROM cab.fecha_emision)::int AS mes
        FROM public.factura_compra_cab cab
        JOIN public.factura_compra_det d ON d.id_factura = cab.id_factura
        $where
        GROUP BY anio, mes
      ) x
    ";
  } elseif ($grupo === 'sucursal') {
    $select = "
      SELECT cab.id_sucursal,
             COUNT(DISTINCT cab.id_factura)::int AS cant_facturas,
             SUM(cab.total_factura)::numeric(14,2) AS total_facturas,
             $agg_base
      FROM public.factura_compra_cab cab
      JOIN public.factura_compra_det d ON d.id_factura = cab.id_factura
      $where
      GROUP BY cab.id_sucursal
      ORDER BY cab.id_sucursal NULLS LAST
      LIMIT $limit OFFSET $offset
    ";
    $count_sql = "
      SELECT COUNT(*) FROM (
        SELECT cab.id_sucursal
        FROM public.factura_compra_cab cab
        JOIN public.factura_compra_det d ON d.id_factura = cab.id_factura
        $where
        GROUP BY cab.id_sucursal
      ) x
    ";
  } else {
    // Detalle por factura
    $select = "
      SELECT $grp_select,
             $agg_base
      FROM public.factura_compra_cab cab
      LEFT JOIN public.proveedores p ON p.id_proveedor = cab.id_proveedor
      LEFT JOIN public.factura_compra_det d ON d.id_factura = cab.id_factura
      $where
      GROUP BY $grp_group
      ORDER BY cab.fecha_emision DESC, cab.id_factura DESC
      LIMIT $limit OFFSET $offset
    ";
    $count_sql = "
      SELECT COUNT(*) FROM (
        SELECT cab.id_factura
        FROM public.factura_compra_cab cab
        LEFT JOIN public.proveedores p ON p.id_proveedor = cab.id_proveedor
        $where
        GROUP BY cab.id_factura
      ) x
    ";
  }

  // Total rows
  $crs = pg_query_params($c, $count_sql, $p);
  $total_rows = ($crs && pg_num_rows($crs)>0) ? (int)pg_fetch_result($crs, 0, 0) : 0;

  // Data
  $rs = pg_query_params($c, $select, $p);
  $rows = [];
  if ($rs) while ($r = pg_fetch_assoc($rs)) $rows[] = $r;

  json_exit(["ok"=>true, "rows"=>$rows, "total"=>$total_rows, "page"=>$page, "limit"=>$limit]);
}

if ($action === 'detalle_factura') {
  $id = (int)($_GET['id_factura'] ?? 0);
  if ($id <= 0) json_exit(["ok"=>false, "error"=>"id_factura requerido"], 400);

  $sql = "
    SELECT d.id_factura_det, d.id_oc_det, d.id_producto,
           COALESCE(pr.descripcion, pr.nombre, '(sin nombre)') AS producto,
           d.cantidad, d.precio_unitario, d.subtotal, d.tipo_iva
    FROM public.factura_compra_det d
    LEFT JOIN public.producto pr ON pr.id_producto = d.id_producto
    WHERE d.id_factura = $1
    ORDER BY d.id_factura_det
  ";
  $rs = pg_query_params($c, $sql, [$id]);
  $det = [];
  if ($rs) while ($r = pg_fetch_assoc($rs)) $det[] = $r;
  json_exit(["ok"=>true, "items"=>$det]);
}

if ($action === 'export_csv') {
  // export usando los mismos filtros y grupo que 'listar'
  $fd = $_GET['fdesde'] ?? '';
  $fh = $_GET['fhasta'] ?? '';
  $prov = (int)($_GET['id_proveedor'] ?? 0);
  $estado = $_GET['estado'] ?? '';
  $suc = (int)($_GET['id_sucursal'] ?? 0);
  $moneda = $_GET['moneda'] ?? '';
  $nrodoc = trim($_GET['numero_documento'] ?? '');
  $timbr = trim($_GET['timbrado_numero'] ?? '');
  $grupo = $_GET['group_by'] ?? '';

  $w = []; $p = [];
  if ($fd !== '') { $p[] = $fd; $w[] = "cab.fecha_emision >= $".count($p); }
  if ($fh !== '') { $p[] = $fh; $w[] = "cab.fecha_emision <= $".count($p); }
  if ($prov > 0) { $p[] = $prov; $w[] = "cab.id_proveedor = $".count($p); }
  if ($estado !== '') { $p[] = $estado; $w[] = "cab.estado = $".count($p); }
  if ($suc > 0) { $p[] = $suc; $w[] = "cab.id_sucursal = $".count($p); }
  if ($moneda !== '') { $p[] = $moneda; $w[] = "cab.moneda = $".count($p); }
  if ($nrodoc !== '') { $p[] = like_or_null($nrodoc); $w[] = "cab.numero_documento ILIKE $".count($p); }
  if ($timbr !== '') { $p[] = like_or_null($timbr); $w[] = "cab.timbrado_numero::text ILIKE $".count($p); }
  $where = $w ? ("WHERE ".implode(" AND ", $w)) : "";

  $agg_base = "
    SUM(CASE WHEN (d.tipo_iva ILIKE '10%' OR d.tipo_iva ILIKE '10') THEN d.subtotal ELSE 0 END) AS grav10,
    SUM(CASE WHEN (d.tipo_iva ILIKE '5%'  OR d.tipo_iva ILIKE '5')  THEN d.subtotal ELSE 0 END) AS grav5,
    SUM(CASE WHEN (COALESCE(d.tipo_iva,'') NOT ILIKE '10%' AND COALESCE(d.tipo_iva,'') NOT ILIKE '10'
               AND COALESCE(d.tipo_iva,'') NOT ILIKE '5%'  AND COALESCE(d.tipo_iva,'') NOT ILIKE '5') THEN d.subtotal ELSE 0 END) AS exentas,
    SUM(CASE WHEN (d.tipo_iva ILIKE '10%' OR d.tipo_iva ILIKE '10') THEN d.subtotal*0.10
             WHEN (d.tipo_iva ILIKE '5%'  OR d.tipo_iva ILIKE '5')  THEN d.subtotal*0.05
             ELSE 0 END) AS iva_calc,
    SUM(d.subtotal) AS total_subtotal
  ";

  if ($grupo === 'proveedor') {
    $sql = "
      SELECT COALESCE(p.nombre,'(sin nombre)') AS proveedor,
             COUNT(DISTINCT cab.id_factura)::int AS cant_facturas,
             SUM(cab.total_factura)::numeric(14,2) AS total_facturas,
             $agg_base
      FROM public.factura_compra_cab cab
      JOIN public.factura_compra_det d ON d.id_factura = cab.id_factura
      LEFT JOIN public.proveedores p ON p.id_proveedor = cab.id_proveedor
      $where
      GROUP BY p.nombre
      ORDER BY proveedor ASC
    ";
    $headers = ['Proveedor','Cant Facturas','Total Facturas','Grav.10','Grav.5','Exentas','IVA calc','Subtotal det'];
  } elseif ($grupo === 'mes') {
    $sql = "
      SELECT EXTRACT(YEAR FROM cab.fecha_emision)::int AS anio,
             EXTRACT(MONTH FROM cab.fecha_emision)::int AS mes,
             COUNT(DISTINCT cab.id_factura)::int AS cant_facturas,
             SUM(cab.total_factura)::numeric(14,2) AS total_facturas,
             $agg_base
      FROM public.factura_compra_cab cab
      JOIN public.factura_compra_det d ON d.id_factura = cab.id_factura
      $where
      GROUP BY anio, mes
      ORDER BY anio DESC, mes DESC
    ";
    $headers = ['Año','Mes','Cant Facturas','Total Facturas','Grav.10','Grav.5','Exentas','IVA calc','Subtotal det'];
  } elseif ($grupo === 'sucursal') {
    $sql = "
      SELECT cab.id_sucursal,
             COUNT(DISTINCT cab.id_factura)::int AS cant_facturas,
             SUM(cab.total_factura)::numeric(14,2) AS total_facturas,
             $agg_base
      FROM public.factura_compra_cab cab
      JOIN public.factura_compra_det d ON d.id_factura = cab.id_factura
      $where
      GROUP BY cab.id_sucursal
      ORDER BY cab.id_sucursal NULLS LAST
    ";
    $headers = ['ID Sucursal','Cant Facturas','Total Facturas','Grav.10','Grav.5','Exentas','IVA calc','Subtotal det'];
  } else {
    // detalle por factura
    $sql = "
      SELECT cab.id_factura, cab.fecha_emision, cab.numero_documento,
             COALESCE(p.nombre,'(sin nombre)') AS proveedor,
             cab.moneda, cab.estado, cab.id_sucursal, cab.timbrado_numero,
             cab.total_factura,
             $agg_base
      FROM public.factura_compra_cab cab
      LEFT JOIN public.proveedores p ON p.id_proveedor = cab.id_proveedor
      LEFT JOIN public.factura_compra_det d ON d.id_factura = cab.id_factura
      $where
      GROUP BY cab.id_factura, cab.fecha_emision, cab.numero_documento, p.nombre,
               cab.moneda, cab.estado, cab.id_sucursal, cab.timbrado_numero, cab.total_factura
      ORDER BY cab.fecha_emision DESC, cab.id_factura DESC
    ";
    $headers = ['ID Factura','Fecha','Nro Doc','Proveedor','Moneda','Estado','Sucursal','Timbrado','Total Factura','Grav.10','Grav.5','Exentas','IVA calc','Subtotal det'];
  }

  $rs = pg_query_params($c, $sql, $p);
  $rows = [];
  if ($rs) while ($r = pg_fetch_assoc($rs)) $rows[] = $r;

  // map a CSV arrays
  $csv = [];
  foreach($rows as $r){
    if ($grupo === 'proveedor') {
      $csv[] = [
        $r['proveedor'], $r['cant_facturas'], $r['total_facturas'],
        $r['grav10'], $r['grav5'], $r['exentas'], $r['iva_calc'], $r['total_subtotal']
      ];
    } elseif ($grupo === 'mes') {
      $csv[] = [
        $r['anio'], $r['mes'], $r['cant_facturas'], $r['total_facturas'],
        $r['grav10'], $r['grav5'], $r['exentas'], $r['iva_calc'], $r['total_subtotal']
      ];
    } elseif ($grupo === 'sucursal') {
      $csv[] = [
        $r['id_sucursal'], $r['cant_facturas'], $r['total_facturas'],
        $r['grav10'], $r['grav5'], $r['exentas'], $r['iva_calc'], $r['total_subtotal']
      ];
    } else {
      $csv[] = [
        $r['id_factura'], $r['fecha_emision'], $r['numero_documento'], $r['proveedor'],
        $r['moneda'], $r['estado'], $r['id_sucursal'], $r['timbrado_numero'],
        $r['total_factura'], $r['grav10'], $r['grav5'], $r['exentas'], $r['iva_calc'], $r['total_subtotal']
      ];
    }
  }
  csv_out("informe_facturas.csv", $csv, $headers);
}

// ---------- UI (HTML) ----------
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<title>Informes · Facturas de Compra</title>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<style>
:root{ --bg:#0f172a; --card:#111827; --muted:#94a3b8; --txt:#e5e7eb; --line:#243244; }
*{ box-sizing:border-box; }
body{ margin:0; font-family:system-ui,Segoe UI,Roboto,Ubuntu; background:var(--bg); color:var(--txt); }
.wrap{ max-width:1200px; margin:24px auto; padding:12px; }
.card{ background:var(--card); border:1px solid #1f2937; border-radius:16px; padding:16px; box-shadow:0 10px 24px rgba(0,0,0,.25); }
h1{ margin:0 0 12px; font-size:20px; }
.grid{ display:grid; grid-template-columns:repeat(6,1fr); gap:10px; }
label{ font-size:12px; color:var(--muted); display:block; margin-bottom:6px; }
input,select{ width:100%; padding:10px 12px; border-radius:10px; border:1px solid #1f2937; background:#0b1220; color:#e5e7eb; }
.tbl{ width:100%; border-collapse:collapse; margin-top:10px; font-size:14px; }
.tbl th,.tbl td{ border:1px solid var(--line); padding:8px; text-align:left; }
.tbl th{ color:#cbd5e1; background:#0b1220; cursor:pointer; white-space:nowrap; }
.right{ text-align:right; }
.muted{ color:var(--muted); font-size:12px; }
.row{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.btn{ padding:10px 14px; border-radius:10px; border:1px solid #334155; background:#0b1220; color:#e5e7eb; cursor:pointer; }
.btn:hover{ filter:brightness(1.12); }
.badge{ display:inline-block; padding:2px 8px; border-radius:999px; border:1px solid #334155; background:#0b1220; font-size:12px; color:#cbd5e1; }
tfoot td{ font-weight:600; }
.modal-back{ position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index:1000; }
.modal{ width:min(900px,95vw); max-height:85vh; overflow:auto; background:#111827; border:1px solid #243244; border-radius:16px; padding:16px; }
.modal h3{ margin:0 0 8px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Informes · Facturas de Compra</h1>
    <form id="filtros" onsubmit="return loadData(1)">
      <div class="grid">
        <div>
          <label>Desde</label>
          <input type="date" name="fdesde" id="fdesde">
        </div>
        <div>
          <label>Hasta</label>
          <input type="date" name="fhasta" id="fhasta">
        </div>
        <div>
          <label>Proveedor</label>
          <select name="id_proveedor" id="id_proveedor"></select>
        </div>
        <div>
          <label>Estado</label>
          <select name="estado" id="estado">
            <option value="">(Todos)</option>
            <option>Registrada</option>
            <option>Pendiente</option>
            <option>Cancelada</option>
            <option>Anulada</option>
          </select>
        </div>
        <div>
          <label>Sucursal</label>
          <input type="number" name="id_sucursal" id="id_sucursal" placeholder="ID">
        </div>
        <div>
          <label>Moneda</label>
          <select name="moneda" id="moneda">
            <option value="">(Todas)</option>
            <option value="PYG">PYG</option>
            <option value="USD">USD</option>
          </select>
        </div>
        <div>
          <label>Nro Documento</label>
          <input type="text" name="numero_documento" id="numero_documento" placeholder="contiene…">
        </div>
        <div>
          <label>Timbrado</label>
          <input type="text" name="timbrado_numero" id="timbrado_numero" placeholder="contiene…">
        </div>
        <div>
          <label>Ver por</label>
          <select name="group_by" id="group_by">
            <option value="">Detalle por factura</option>
            <option value="proveedor">Proveedor</option>
            <option value="mes">Mes/Año</option>
            <option value="sucursal">Sucursal</option>
          </select>
        </div>
        <div>
          <label>Orden</label>
          <select id="orden">
            <option value="">(Predeterminado)</option>
            <option value="fecha_emision DESC">Fecha ↓</option>
            <option value="fecha_emision ASC">Fecha ↑</option>
            <option value="total_factura DESC">Total ↓</option>
            <option value="total_factura ASC">Total ↑</option>
          </select>
        </div>
        <div>
          <label>Mostrar</label>
          <select id="limit">
            <option>25</option><option selected>50</option><option>100</option>
          </select>
        </div>
      </div>

      <div class="row" style="margin-top:10px; justify-content:space-between">
        <div class="row">
          <button class="btn" type="submit">Aplicar filtros</button>
          <button class="btn" type="button" onclick="exportCSV()">Exportar CSV</button>
        </div>
        <span class="muted" id="status"></span>
      </div>
    </form>

    <table class="tbl" id="grid">
      <thead id="thead"></thead>
      <tbody id="tbody"><tr><td>Cargá filtros y presioná “Aplicar filtros”.</td></tr></tbody>
      <tfoot id="tfoot"></tfoot>
    </table>

    <div class="row" style="justify-content:space-between; margin-top:8px">
      <div id="paginador" class="row"></div>
      <span class="muted" id="resumen"></span>
    </div>
  </div>
</div>

<!-- Modal Detalle Factura -->
<div class="modal-back" id="mback">
  <div class="modal">
    <h3>Detalle de factura <span id="mf_id"></span></h3>
    <div id="mf_info" class="muted" style="margin-bottom:6px"></div>
    <div id="mf_body">Cargando…</div>
    <div class="row" style="justify-content:flex-end; margin-top:8px">
      <button class="btn" onclick="closeModal()">Cerrar</button>
    </div>
  </div>
</div>

<script>
const QS = s => document.querySelector(s);
const QSA = s => Array.from(document.querySelectorAll(s));

function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m])); }

// Proveedores
async function loadProveedores(){
  const sel = QS('#id_proveedor');
  sel.innerHTML = `<option value="">(Todos)</option>`;
  try{
    const r = await fetch('?action=proveedores');
    const data = await r.json();
    (data.items||[]).forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id_proveedor; opt.textContent = p.nombre;
      sel.appendChild(opt);
    });
  }catch(e){}
}
document.addEventListener('DOMContentLoaded', loadProveedores);

// Listado
async function loadData(page=1){
  const st = QS('#status'); st.textContent = 'Cargando…';
  const params = new URLSearchParams(new FormData(QS('#filtros')));
  params.set('action','listar');
  params.set('page', page);
  params.set('limit', QS('#limit').value);
  const r = await fetch('?'+params.toString());
  const data = await r.json().catch(()=>({ok:false}));
  if(!data.ok){ st.textContent='Error'; return false; }

  const grupo = QS('#group_by').value;
  renderTable(data.rows||[], grupo);
  renderPager(data.total||0, data.page||1, data.limit||50);
  st.textContent = `Filas: ${(data.rows||[]).length} / ${data.total||0}`;
  return false;
}

function renderPager(total, page, limit){
  const p = QS('#paginador'); p.innerHTML='';
  const pages = Math.max(1, Math.ceil(total/limit));
  for(let i=1; i<=pages && i<=20; i++){
    const b=document.createElement('button'); b.className='btn';
    if(i===page) b.style.filter='brightness(1.25)';
    b.textContent=i; b.onclick=()=>loadData(i);
    p.appendChild(b);
  }
  QS('#resumen').textContent = `Página ${page} de ${pages}`;
}

function renderTable(rows, grupo){
  const thead = QS('#thead'), tbody = QS('#tbody'), tfoot=QS('#tfoot');
  thead.innerHTML=''; tbody.innerHTML=''; tfoot.innerHTML='';
  if(grupo==='proveedor'){
    thead.innerHTML = `<tr>
      <th>Proveedor</th><th class="right">Cant</th><th class="right">Total Factura</th>
      <th class="right">Grav.10</th><th class="right">Grav.5</th><th class="right">Exentas</th><th class="right">IVA (calc)</th><th class="right">Subtotal det</th>
    </tr>`;
    let tot = {cant:0, tf:0, g10:0, g5:0, ex:0, iva:0, sub:0};
    tbody.innerHTML = rows.map(r=>{
      tot.cant += Number(r.cant_facturas||0);
      tot.tf   += Number(r.total_facturas||0);
      tot.g10  += Number(r.grav10||0);
      tot.g5   += Number(r.grav5||0);
      tot.ex   += Number(r.exentas||0);
      tot.iva  += Number(r.iva_calc||0);
      tot.sub  += Number(r.total_subtotal||0);
      return `<tr>
        <td>${escapeHtml(r.proveedor)}</td>
        <td class="right">${r.cant_facturas}</td>
        <td class="right">${fmt(r.total_facturas)}</td>
        <td class="right">${fmt(r.grav10)}</td>
        <td class="right">${fmt(r.grav5)}</td>
        <td class="right">${fmt(r.exentas)}</td>
        <td class="right">${fmt(r.iva_calc)}</td>
        <td class="right">${fmt(r.total_subtotal)}</td>
      </tr>`;
    }).join('');
    tfoot.innerHTML = `<tr>
      <td>Total</td>
      <td class="right">${tot.cant}</td>
      <td class="right">${fmt(tot.tf)}</td>
      <td class="right">${fmt(tot.g10)}</td>
      <td class="right">${fmt(tot.g5)}</td>
      <td class="right">${fmt(tot.ex)}</td>
      <td class="right">${fmt(tot.iva)}</td>
      <td class="right">${fmt(tot.sub)}</td>
    </tr>`;
  } else if (grupo==='mes'){
    thead.innerHTML = `<tr>
      <th>Año</th><th>Mes</th><th class="right">Cant</th><th class="right">Total Factura</th>
      <th class="right">Grav.10</th><th class="right">Grav.5</th><th class="right">Exentas</th><th class="right">IVA (calc)</th><th class="right">Subtotal det</th>
    </tr>`;
    let tot = {cant:0, tf:0, g10:0, g5:0, ex:0, iva:0, sub:0};
    tbody.innerHTML = rows.map(r=>{
      tot.cant += Number(r.cant_facturas||0);
      tot.tf   += Number(r.total_facturas||0);
      tot.g10  += Number(r.grav10||0);
      tot.g5   += Number(r.grav5||0);
      tot.ex   += Number(r.exentas||0);
      tot.iva  += Number(r.iva_calc||0);
      tot.sub  += Number(r.total_subtotal||0);
      return `<tr>
        <td>${r.anio}</td>
        <td>${r.mes}</td>
        <td class="right">${r.cant_facturas}</td>
        <td class="right">${fmt(r.total_facturas)}</td>
        <td class="right">${fmt(r.grav10)}</td>
        <td class="right">${fmt(r.grav5)}</td>
        <td class="right">${fmt(r.exentas)}</td>
        <td class="right">${fmt(r.iva_calc)}</td>
        <td class="right">${fmt(r.total_subtotal)}</td>
      </tr>`;
    }).join('');
    tfoot.innerHTML = `<tr>
      <td colspan="2">Total</td>
      <td class="right">${tot.cant}</td>
      <td class="right">${fmt(tot.tf)}</td>
      <td class="right">${fmt(tot.g10)}</td>
      <td class="right">${fmt(tot.g5)}</td>
      <td class="right">${fmt(tot.ex)}</td>
      <td class="right">${fmt(tot.iva)}</td>
      <td class="right">${fmt(tot.sub)}</td>
    </tr>`;
  } else if (grupo==='sucursal'){
    thead.innerHTML = `<tr>
      <th>ID Sucursal</th><th class="right">Cant</th><th class="right">Total Factura</th>
      <th class="right">Grav.10</th><th class="right">Grav.5</th><th class="right">Exentas</th><th class="right">IVA (calc)</th><th class="right">Subtotal det</th>
    </tr>`;
    let tot = {cant:0, tf:0, g10:0, g5:0, ex:0, iva:0, sub:0};
    tbody.innerHTML = rows.map(r=>{
      tot.cant += Number(r.cant_facturas||0);
      tot.tf   += Number(r.total_facturas||0);
      tot.g10  += Number(r.grav10||0);
      tot.g5   += Number(r.grav5||0);
      tot.ex   += Number(r.exentas||0);
      tot.iva  += Number(r.iva_calc||0);
      tot.sub  += Number(r.total_subtotal||0);
      return `<tr>
        <td>${r.id_sucursal ?? ''}</td>
        <td class="right">${r.cant_facturas}</td>
        <td class="right">${fmt(r.total_facturas)}</td>
        <td class="right">${fmt(r.grav10)}</td>
        <td class="right">${fmt(r.grav5)}</td>
        <td class="right">${fmt(r.exentas)}</td>
        <td class="right">${fmt(r.iva_calc)}</td>
        <td class="right">${fmt(r.total_subtotal)}</td>
      </tr>`;
    }).join('');
    tfoot.innerHTML = `<tr>
      <td>Total</td>
      <td class="right">${tot.cant}</td>
      <td class="right">${fmt(tot.tf)}</td>
      <td class="right">${fmt(tot.g10)}</td>
      <td class="right">${fmt(tot.g5)}</td>
      <td class="right">${fmt(tot.ex)}</td>
      <td class="right">${fmt(tot.iva)}</td>
      <td class="right">${fmt(tot.sub)}</td>
    </tr>`;
  } else {
    // Detalle por factura
    thead.innerHTML = `<tr>
      <th>ID</th><th>Fecha</th><th>Nro Doc</th><th>Proveedor</th><th>Moneda</th>
      <th>Estado</th><th>Sucursal</th><th>Timbrado</th>
      <th class="right">Grav.10</th><th class="right">Grav.5</th><th class="right">Exentas</th>
      <th class="right">IVA (calc)</th><th class="right">Subtotal det</th><th class="right">Total Factura</th><th></th>
    </tr>`;
    let tot = {tf:0, g10:0, g5:0, ex:0, iva:0, sub:0};
    tbody.innerHTML = rows.map(r=>{
      tot.tf  += Number(r.total_factura||0);
      tot.g10 += Number(r.grav10||0);
      tot.g5  += Number(r.grav5||0);
      tot.ex  += Number(r.exentas||0);
      tot.iva += Number(r.iva_calc||0);
      tot.sub += Number(r.total_subtotal||0);
      return `<tr>
        <td>${r.id_factura}</td>
        <td>${escapeHtml((r.fecha_emision||'').substring(0,10))}</td>
        <td>${escapeHtml(r.numero_documento||'')}</td>
        <td>${escapeHtml(r.proveedor||'')}</td>
        <td>${escapeHtml(r.moneda||'')}</td>
        <td><span class="badge">${escapeHtml(r.estado||'')}</span></td>
        <td>${r.id_sucursal ?? ''}</td>
        <td>${escapeHtml(r.timbrado_numero||'')}</td>
        <td class="right">${fmt(r.grav10)}</td>
        <td class="right">${fmt(r.grav5)}</td>
        <td class="right">${fmt(r.exentas)}</td>
        <td class="right">${fmt(r.iva_calc)}</td>
        <td class="right">${fmt(r.total_subtotal)}</td>
        <td class="right">${fmt(r.total_factura)}</td>
        <td><button class="btn" onclick="openDetalle(${r.id_factura}, '${escapeHtml(r.proveedor||'')}', '${escapeHtml(r.numero_documento||'')}')">Ver</button></td>
      </tr>`;
    }).join('');
    tfoot.innerHTML = `<tr>
      <td colspan="8">Totales</td>
      <td class="right">${fmt(tot.g10)}</td>
      <td class="right">${fmt(tot.g5)}</td>
      <td class="right">${fmt(tot.ex)}</td>
      <td class="right">${fmt(tot.iva)}</td>
      <td class="right">${fmt(tot.sub)}</td>
      <td class="right">${fmt(tot.tf)}</td>
      <td></td>
    </tr>`;
  }
}

function fmt(x){ const n = Number(x||0); return n.toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); }

// Modal Detalle
async function openDetalle(id, prov, nro){
  QS('#mf_id').textContent = `#${id}`;
  QS('#mf_info').textContent = `Proveedor: ${prov} · Doc: ${nro}`;
  QS('#mf_body').innerHTML = 'Cargando…';
  QS('#mback').style.display='flex';
  const r = await fetch('?action=detalle_factura&id_factura='+encodeURIComponent(id));
  const data = await r.json().catch(()=>({ok:false}));
  if(!data.ok){ QS('#mf_body').textContent = 'Error'; return; }
  const rows = data.items||[];
  if(rows.length===0){ QS('#mf_body').textContent='Sin detalle'; return; }
  QS('#mf_body').innerHTML = `
    <table class="tbl">
      <thead><tr>
        <th>ID Det</th><th>id_oc_det</th><th>Producto</th>
        <th class="right">Cant</th><th class="right">Precio</th><th class="right">Subtotal</th><th>IVA</th>
      </tr></thead>
      <tbody>
        ${rows.map(r=>`
          <tr>
            <td>${r.id_factura_det}</td>
            <td>${r.id_oc_det}</td>
            <td>${escapeHtml(r.producto||('ID '+r.id_producto))}</td>
            <td class="right">${Number(r.cantidad)}</td>
            <td class="right">${fmt(r.precio_unitario)}</td>
            <td class="right">${fmt(r.subtotal)}</td>
            <td>${escapeHtml(r.tipo_iva||'')}</td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}
function closeModal(){ QS('#mback').style.display='none'; }

// Export
function exportCSV(){
  const params = new URLSearchParams(new FormData(QS('#filtros')));
  params.set('action','export_csv');
  window.location = '?'+params.toString();
}
</script>
</body>
</html>
