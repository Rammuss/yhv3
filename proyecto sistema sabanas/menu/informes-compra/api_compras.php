<?php
// api_compras.php — JSON API (Compras por proveedor y documento)
// Filtros soportados (GET):
//   desde (YYYY-MM-DD) | hasta (YYYY-MM-DD) | prov (id_proveedor)
//   estado | moneda | suc (id_sucursal) | doc (busca por número)
//   limit (default 50) | offset (default 0)
//   sort (ej: fecha_emision DESC, proveedor ASC, total_factura DESC)
//
// Respuesta:
// { rows:[...], summary:{total_factura,total_detalle,gravado_10,gravado_5,exentas,iva_calc}, count:n }

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['error' => 'No autorizado']); exit;
}

function jerr($m, $c=400){ http_response_code($c); echo json_encode(['error'=>$m]); exit; }

$hoy = date('Y-m-d');
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? $hoy;
$prov  = isset($_GET['prov']) && $_GET['prov']!=='' ? (int)$_GET['prov'] : null;
$estado= $_GET['estado'] ?? null;
$moneda= $_GET['moneda'] ?? null;
$suc   = isset($_GET['suc']) && $_GET['suc']!=='' ? (int)$_GET['suc'] : null;
$doc   = $_GET['doc'] ?? null;

$limit = (int)($_GET['limit'] ?? 50);
$offset= (int)($_GET['offset'] ?? 0);
if ($limit <= 0 || $limit > 500) $limit = 50;
if ($offset < 0) $offset = 0;

// Orden seguro (white-list simple)
$sort = trim($_GET['sort'] ?? 'fecha_emision DESC, proveedor ASC, numero_documento ASC');
$allowed = [
  'fecha_emision','numero_documento','moneda','estado','total_factura',
  'proveedor','sucursal'
];
$parts = array_map('trim', explode(',', $sort));
$ordClauses = [];
foreach ($parts as $p) {
  if ($p === '') continue;
  $sp = preg_split('/\s+/', $p);
  $col = $sp[0] ?? '';
  $dir = strtoupper($sp[1] ?? 'ASC');
  if (!in_array($col, $allowed, true)) continue;
  if (!in_array($dir, ['ASC','DESC'], true)) $dir = 'ASC';
  $ordClauses[] = "$col $dir";
}
if (!$ordClauses) $ordClauses[] = 'fecha_emision DESC';
$orderBy = implode(', ', $ordClauses);

// Construcción dinámica de filtros
$params = [];
$w = [];

$params[] = $desde; $params[] = $hasta;
$w[] = "f.fecha_emision BETWEEN $1 AND $2";

if (!is_null($prov))   { $params[] = $prov;   $w[] = "f.id_proveedor = $".count($params); }
if (!empty($estado))  { $params[] = $estado; $w[] = "f.estado = $".count($params); }
if (!empty($moneda))  { $params[] = $moneda; $w[] = "f.moneda = $".count($params); }
if (!is_null($suc))    { $params[] = $suc;    $w[] = "f.id_sucursal = $".count($params); }
if (!empty($doc))     { $params[] = "%$doc%"; $w[] = "f.numero_documento ILIKE $".count($params); }

$where = $w ? ('WHERE '.implode(' AND ', $w)) : '';

// Consulta base agregada por factura (sin vistas)
$sqlBase = "
  SELECT
    f.id_factura,
    f.fecha_emision,
    f.numero_documento,
    f.moneda,
    COALESCE(f.estado,'Registrada') AS estado,
    COALESCE(f.total_factura,0)::numeric(14,2) AS total_factura,
    f.id_proveedor,
    p.razon_social AS proveedor,
    p.ruc_ci       AS proveedor_ruc,
    f.id_sucursal,
    s.descripcion  AS sucursal,
    -- agregados desde el detalle
    SUM(d.subtotal) AS total_detalle,
    SUM(CASE WHEN COALESCE(d.tipo_iva,'EXE') ILIKE '10%%' THEN d.subtotal ELSE 0 END) AS gravado_10,
    SUM(CASE WHEN COALESCE(d.tipo_iva,'EXE') ILIKE '5%%'  THEN d.subtotal ELSE 0 END) AS gravado_5,
    SUM(CASE WHEN COALESCE(d.tipo_iva,'EXE') = 'EXE'      THEN d.subtotal ELSE 0 END) AS exentas
  FROM public.factura_compra_cab f
  JOIN public.proveedores p   ON p.id_proveedor = f.id_proveedor
  LEFT JOIN public.sucursales s ON s.id_sucursal = f.id_sucursal
  LEFT JOIN public.factura_compra_det d ON d.id_factura = f.id_factura
  $where
  GROUP BY
    f.id_factura, f.fecha_emision, f.numero_documento, f.moneda, f.estado, f.total_factura,
    f.id_proveedor, p.razon_social, p.ruc_ci, f.id_sucursal, s.descripcion
";

// Conteo total (facturas) para paginación
$sqlCount = "
  SELECT COUNT(*)::int AS c FROM (
    SELECT f.id_factura
    FROM public.factura_compra_cab f
    JOIN public.proveedores p   ON p.id_proveedor = f.id_proveedor
    LEFT JOIN public.sucursales s ON s.id_sucursal = f.id_sucursal
    LEFT JOIN public.factura_compra_det d ON d.id_factura = f.id_factura
    $where
    GROUP BY f.id_factura
  ) t
";

// Data + orden + paginación
$sqlData = $sqlBase . " ORDER BY $orderBy LIMIT $limit OFFSET $offset";

// Totales (summary) sobre todo el conjunto filtrado
$sqlSum = "
  SELECT
    COALESCE(SUM(x.total_factura),0)::numeric(14,2) AS total_factura,
    COALESCE(SUM(x.total_detalle),0)::numeric(14,2) AS total_detalle,
    COALESCE(SUM(x.gravado_10),0)::numeric(14,2)   AS gravado_10,
    COALESCE(SUM(x.gravado_5),0)::numeric(14,2)    AS gravado_5,
    COALESCE(SUM(x.exentas),0)::numeric(14,2)      AS exentas
  FROM (
    $sqlBase
  ) x
";

// Ejecutar
$rcount = pg_query_params($conn, $sqlCount, $params);
if (!$rcount) jerr('Error count: '.pg_last_error($conn), 500);
$count = (int)pg_fetch_result($rcount, 0, 'c');

$rdata = pg_query_params($conn, $sqlData, $params);
if (!$rdata) jerr('Error data: '.pg_last_error($conn), 500);

$rsum  = pg_query_params($conn, $sqlSum, $params);
if (!$rsum) jerr('Error summary: '.pg_last_error($conn), 500);
$sum = pg_fetch_assoc($rsum);
$sum['iva_calc'] = (float)$sum['gravado_10']*0.10 + (float)$sum['gravado_5']*0.05;

// Armar filas
$rows = [];
while ($row = pg_fetch_assoc($rdata)) {
  $row['iva_calc'] = (float)$row['gravado_10']*0.10 + (float)$row['gravado_5']*0.05;
  $rows[] = $row;
}

echo json_encode([
  'rows' => $rows,
  'summary' => $sum,
  'count' => $count,
  'limit' => $limit,
  'offset' => $offset,
  'order' => $orderBy
], JSON_UNESCAPED_UNICODE);
