<?php
// api_ventas_clientes_top.php — Top de clientes por ventas/margen (corrigido)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '../../../../conexion/configv2.php';

function out($ok, $payload = [], $http = 200) {
  http_response_code($http);
  echo json_encode($ok ? (['success'=>true] + $payload) : (['success'=>false] + $payload), JSON_UNESCAPED_UNICODE);
  exit;
}

// --------- Filtros ---------
$desde     = $_GET['desde']      ?? null; // YYYY-MM-DD
$hasta     = $_GET['hasta']      ?? null; // YYYY-MM-DD
$condicion = $_GET['condicion']  ?? null; // Contado | Credito
$top       = isset($_GET['top']) ? max(1, (int)$_GET['top']) : 50;

// Orden principal: neto|margen|uds|facturas (default: neto)
$order_by  = $_GET['order_by'] ?? 'neto';
if (!in_array($order_by, ['neto','margen','uds','facturas'], true)) $order_by = 'neto';

// Criterio para participación: neto|margen (default: neto)
$criterio  = $_GET['criterio'] ?? 'neto';
if (!in_array($criterio, ['neto','margen'], true)) $criterio = 'neto';

// Validaciones
if ($desde && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = null;
if ($hasta && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = null;
if ($condicion && !in_array($condicion, ['Contado','Credito'], true)) $condicion = null;

// --------- WHERE dinámico ---------
$where = ["vc.estado <> 'Anulada'"];
$params = [];

if ($desde)     { $params[] = $desde;     $where[] = "vc.fecha_emision >= $" . count($params); }
if ($hasta)     { $params[] = $hasta;     $where[] = "vc.fecha_emision <= $" . count($params); }
if ($condicion) { $params[] = $condicion; $where[] = "vc.condicion_venta = $" . count($params); }
$WHERE = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// --------- ORDER BY dinámico ---------
switch ($order_by) {
  case 'margen':   $ORDER = 'margen DESC NULLS LAST';   break;
  case 'uds':      $ORDER = 'uds DESC NULLS LAST';      break;
  case 'facturas': $ORDER = 'facturas DESC NULLS LAST'; break;
  case 'neto':
  default:         $ORDER = 'neto DESC NULLS LAST';      break;
}

// --------- SQL (bruto=CON IVA, neto=SIN IVA) ---------
$sql = "
WITH base AS (
  SELECT
    vc.id_cliente,
    TRIM(COALESCE(cl.nombre,'') || ' ' || COALESCE(cl.apellido,'')) AS cliente,
    COUNT(DISTINCT vc.id_factura)                 AS facturas,
    SUM(vd.cantidad)                              AS uds,
    -- Neto (SIN IVA) desde precio_unitario base
    SUM(vd.cantidad * vd.precio_unitario)         AS neto,
    -- Bruto (CON IVA) tomado de subtotal_neto que viene con IVA según tu esquema
    SUM(vd.subtotal_neto)                         AS bruto,
    -- Costo (unitario de compra * cantidad)
    SUM(vd.cantidad * COALESCE(p.precio_compra,0)) AS costo
  FROM public.factura_venta_cab vc
  JOIN public.factura_venta_det vd ON vd.id_factura = vc.id_factura
  LEFT JOIN public.producto p      ON p.id_producto = vd.id_producto
  LEFT JOIN public.clientes cl     ON cl.id_cliente = vc.id_cliente
  $WHERE
  GROUP BY vc.id_cliente, TRIM(COALESCE(cl.nombre,'') || ' ' || COALESCE(cl.apellido,''))
),
enriq AS (
  SELECT
    b.*,
    (b.neto - b.costo) AS margen  -- margen sobre SIN IVA
  FROM base b
),
tot AS (
  SELECT
    SUM(neto)   AS total_neto,
    SUM(margen) AS total_margen
  FROM enriq
),
ranked AS (
  SELECT
    e.*,
    (e.neto   / NULLIF(t.total_neto,0))   * 100 AS part_neto_pct,
    (e.margen / NULLIF(t.total_margen,0)) * 100 AS part_margen_pct,
    CASE WHEN e.neto > 0 THEN (e.margen / e.neto) * 100 ELSE 0 END AS margen_pct
  FROM enriq e
  CROSS JOIN tot t
)
SELECT *
FROM ranked
ORDER BY $ORDER
LIMIT $" . (count($params)+1) . "
";

$params[] = $top;

$res = pg_query_params($conn, $sql, $params);
if (!$res) {
  out(false, ['error'=>'Error de base de datos', 'detail'=>pg_last_error($conn)], 500);
}

// --------- Salida ---------
$rows = [];
$rank = 0;
while ($r = pg_fetch_assoc($res)) {
  $rank++;
  $rows[] = [
    'rank'            => $rank,
    'id_cliente'      => (int)$r['id_cliente'],
    'cliente'         => $r['cliente'],
    'facturas'        => (int)$r['facturas'],
    'uds'             => (float)$r['uds'],
    'ingreso_neto'    => (float)$r['neto'],   // SIN IVA (base)
    'ingreso_bruto'   => (float)$r['bruto'],  // CON IVA
    'costo'           => (float)$r['costo'],
    'margen_bruto'    => (float)$r['margen'], // sobre neto (sin IVA)
    'margen_pct'      => (float)$r['margen_pct'],
    'part_neto_pct'   => (float)$r['part_neto_pct'],
    'part_margen_pct' => (float)$r['part_margen_pct'],
  ];
}

// Orden secundario visual según criterio (para gráficos/tablas)
if ($criterio === 'margen') {
  usort($rows, fn($a,$b) => $b['margen_bruto'] <=> $a['margen_bruto']);
} else {
  usort($rows, fn($a,$b) => $b['ingreso_neto'] <=> $a['ingreso_neto']);
}

out(true, ['rows'=>$rows, 'count'=>count($rows)]);
