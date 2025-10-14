<?php
// api_compras_por_item.php — Compras por producto/categoría (JSON)

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '../../../../conexion/configv2.php';

function jfail($msg, $code = 400) {
  http_response_code($code);
  echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

function norm_iva($s) {
  $s = strtoupper(trim((string)$s));
  if ($s === '10' || $s === '10%') return '10%';
  if ($s === '5'  || $s === '5%')  return '5%';
  if (in_array($s, ['EXE','EXENTO','EXENTAS'], true)) return 'EXE';
  return '';
}

// ====== Parámetros ======
$from        = $_GET['from']        ?? '';
$to          = $_GET['to']          ?? '';
$proveedorId = isset($_GET['proveedor_id']) ? (int)$_GET['proveedor_id'] : 0;
$sucursalId  = isset($_GET['sucursal_id'])  ? (int)$_GET['sucursal_id']  : 0;
$agrupacion  = $_GET['agrupacion']  ?? 'producto';   // producto|categoria
$orderBy     = $_GET['order_by']    ?? 'importe';    // importe|cantidad
$ivaParam    = norm_iva($_GET['iva'] ?? '');
$q           = trim((string)($_GET['q'] ?? ''));
$limit       = isset($_GET['limit']) ? max(1, min(2000, (int)$_GET['limit'])) : 500;

if (!$from || !$to) {
  jfail('Parámetros obligatorios: from=YYYY-MM-DD&to=YYYY-MM-DD');
}
if (!in_array($agrupacion, ['producto','categoria'], true)) {
  jfail('Parámetro "agrupacion" inválido. Use producto|categoria');
}
if (!in_array($orderBy, ['importe','cantidad'], true)) {
  jfail('Parámetro "order_by" inválido. Use importe|cantidad');
}

// ====== Filtros dinámicos ======
$w   = [];
$par = [];

// Fechas
$w[]   = 'fcc.fecha_emision BETWEEN $1 AND $2';
$par[] = $from;
$par[] = $to;
$i     = 3;

// Proveedor
if ($proveedorId > 0) {
  $w[]   = "fcc.id_proveedor = $" . $i;
  $par[] = $proveedorId;
  $i++;
}

// Sucursal
if ($sucursalId > 0) {
  $w[]   = "fcc.id_sucursal = $" . $i;
  $par[] = $sucursalId;
  $i++;
}

// Excluir anuladas si existiera ese estado
$w[] = "(fcc.estado IS NULL OR LOWER(fcc.estado) NOT LIKE 'anul%')";

// IVA
if ($ivaParam !== '') {
  $w[]   = "fcd.tipo_iva = $" . $i;
  $par[] = $ivaParam;
  $i++;
}

// Búsqueda libre por producto/categoría
if ($q !== '') {
  $w[]   = "(LOWER(p.nombre) LIKE $" . $i . " OR LOWER(p.categoria) LIKE $" . ($i+1) . ")";
  $like  = '%' . mb_strtolower($q, 'UTF-8') . '%';
  $par[] = $like;
  $par[] = $like;
  $i += 2;
}

$where = $w ? 'WHERE ' . implode(' AND ', $w) : '';

$orderExpr = ($orderBy === 'cantidad')
  ? 'cantidad_total DESC, importe_total DESC'
  : 'importe_total DESC, cantidad_total DESC';

// ====== SQL ======
$sql = "
WITH compras AS (
  SELECT
    fcd.id_producto,
    p.nombre,
    p.categoria,
    fcd.tipo_iva,
    SUM(fcd.cantidad)::numeric(18,4) AS cantidad_total,
    SUM(fcd.subtotal)::numeric(18,2) AS importe_total
  FROM public.factura_compra_det fcd
  JOIN public.factura_compra_cab fcc ON fcc.id_factura = fcd.id_factura
  JOIN public.producto           p   ON p.id_producto = fcd.id_producto
  $where
  GROUP BY fcd.id_producto, p.nombre, p.categoria, fcd.tipo_iva
),
total AS (
  SELECT COALESCE(SUM(importe_total),0)::numeric(18,2) AS total_importe,
         COALESCE(SUM(cantidad_total),0)::numeric(18,4) AS total_cantidad
  FROM compras
)
SELECT
  c.id_producto,
  c.nombre,
  c.categoria,
  c.tipo_iva,
  c.cantidad_total,
  c.importe_total,
  t.total_importe,
  t.total_cantidad,
  CASE WHEN t.total_importe > 0
       THEN ROUND((c.importe_total / t.total_importe) * 100.0, 2)
       ELSE 0 END AS participacion_pct
FROM compras c
CROSS JOIN total t
ORDER BY $orderExpr
LIMIT $limit
";

// IMPORTANTE: no agregamos $limit a $par porque LIMIT está incrustado como entero validado
$r = pg_query_params($conn, $sql, $par);
if (!$r) {
  jfail('Error de base de datos: ' . pg_last_error($conn), 500);
}

$rows = [];
$total_importe  = 0.0;
$total_cantidad = 0.0;

while ($x = pg_fetch_assoc($r)) {
  if ($total_importe == 0.0 && $total_cantidad == 0.0) {
    $total_importe  = (float)$x['total_importe'];
    $total_cantidad = (float)$x['total_cantidad'];
  }
  $rows[] = [
    'id_producto'       => $x['id_producto'] !== null ? (int)$x['id_producto'] : null,
    'producto'          => $x['nombre'],
    'categoria'         => $x['categoria'],
    'tipo_iva'          => $x['tipo_iva'],
    'cantidad_total'    => (float)$x['cantidad_total'],
    'importe_total'     => (float)$x['importe_total'],
    'participacion_pct' => (float)$x['participacion_pct'],
  ];
}

// Agrupación por categoría (consolidación en PHP)
if ($agrupacion === 'categoria') {
  $byCat = [];
  foreach ($rows as $r0) {
    $k = $r0['categoria'] ?? '(Sin categoría)';
    if (!isset($byCat[$k])) {
      $byCat[$k] = ['cantidad_total'=>0.0, 'importe_total'=>0.0];
    }
    $byCat[$k]['cantidad_total'] += $r0['cantidad_total'];
    $byCat[$k]['importe_total']  += $r0['importe_total'];
  }
  $rows = [];
  foreach ($byCat as $k => $v) {
    $rows[] = [
      'id_producto'       => null,
      'producto'          => null,
      'categoria'         => $k,
      'tipo_iva'          => null,
      'cantidad_total'    => (float)$v['cantidad_total'],
      'importe_total'     => (float)$v['importe_total'],
      'participacion_pct' => ($total_importe > 0 ? round(($v['importe_total'] / $total_importe) * 100, 2) : 0.0),
    ];
  }
  usort($rows, function($a,$b) use ($orderBy){
    $key = ($orderBy === 'cantidad') ? 'cantidad_total' : 'importe_total';
    return $b[$key] <=> $a[$key];
  });
  if (count($rows) > $limit) {
    $rows = array_slice($rows, 0, $limit);
  }
}

echo json_encode([
  'success' => true,
  'params' => [
    'from' => $from, 'to' => $to,
    'proveedor_id' => $proveedorId ?: null,
    'sucursal_id'  => $sucursalId ?: null,
    'agrupacion'   => $agrupacion,
    'order_by'     => $orderBy,
    'iva'          => $ivaParam ?: null,
    'q'            => ($q !== '' ? $q : null),
    'limit'        => $limit
  ],
  'totals' => [
    'importe_total'  => $total_importe,
    'cantidad_total' => $total_cantidad
  ],
  'rows' => $rows
], JSON_UNESCAPED_UNICODE);
