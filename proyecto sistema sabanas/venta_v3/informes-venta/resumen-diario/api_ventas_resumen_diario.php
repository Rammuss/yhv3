<?php
// api_ventas_resumen_diario.php — Resumen por día (neto, bruto, margen)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '../../../../conexion/configv2.php';

function out($ok, $payload = [], $http = 200) {
  http_response_code($http);
  echo json_encode($ok ? (['success'=>true] + $payload) : (['success'=>false] + $payload), JSON_UNESCAPED_UNICODE);
  exit;
}

// Filtros
$desde     = $_GET['desde']      ?? null; // YYYY-MM-DD
$hasta     = $_GET['hasta']      ?? null; // YYYY-MM-DD
$condicion = $_GET['condicion']  ?? null; // Contado | Credito
$orden     = $_GET['orden']      ?? 'ASC'; // ASC | DESC
$orden = strtoupper($orden) === 'DESC' ? 'DESC' : 'ASC';

// Validaciones suaves
if ($desde && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = null;
if ($hasta && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = null;
if ($condicion && !in_array($condicion, ['Contado','Credito'], true)) $condicion = null;

// WHERE dinámico
$where  = ["vc.estado <> 'Anulada'"];
$params = [];
if ($desde)     { $params[] = $desde;     $where[] = "vc.fecha_emision >= $" . count($params); }
if ($hasta)     { $params[] = $hasta;     $where[] = "vc.fecha_emision <= $" . count($params); }
if ($condicion) { $params[] = $condicion; $where[] = "vc.condicion_venta = $" . count($params); }
$WHERE = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// SQL: agrupar por día
$sql = "
  WITH base AS (
    SELECT
      vc.fecha_emision::date                  AS fecha,
      COUNT(DISTINCT vc.id_factura)           AS facturas,
      SUM(vd.cantidad)                        AS uds,
      -- Neto (SIN IVA)
      SUM(vd.subtotal_neto - COALESCE(vd.iva_monto, 0)) AS neto,
      -- Bruto (CON IVA)
      SUM(vd.subtotal_neto)                             AS bruto,
      -- Costo
      SUM(vd.cantidad * COALESCE(p.precio_compra,0)) AS costo
    FROM public.factura_venta_cab vc
    JOIN public.factura_venta_det vd ON vd.id_factura = vc.id_factura
    LEFT JOIN public.producto p      ON p.id_producto = vd.id_producto
    $WHERE
    GROUP BY vc.fecha_emision::date
  )
  SELECT
    fecha,
    facturas,
    uds,
    neto    AS ingreso_neto,
    bruto   AS ingreso_bruto,
    costo,
    (neto - costo) AS margen_bruto
  FROM base
  ORDER BY fecha $orden
";

$res = pg_query_params($conn, $sql, $params);
if (!$res) {
  out(false, ['error' => 'Error de base de datos', 'detail' => pg_last_error($conn)], 500);
}

$rows = [];
$tot = ['facturas'=>0,'uds'=>0,'neto'=>0.0,'bruto'=>0.0,'costo'=>0.0,'margen'=>0.0];

while ($r = pg_fetch_assoc($res)) {
  $ingreso_neto  = (float)$r['ingreso_neto'];
  $margen        = (float)$r['margen_bruto'];
  $margen_pct    = $ingreso_neto > 0 ? round(($margen / $ingreso_neto) * 100, 2) : 0.0;

  $rows[] = [
    'fecha'         => $r['fecha'],
    'facturas'      => (int)$r['facturas'],
    'uds'           => (float)$r['uds'],
    'ingreso_neto'  => (float)$r['ingreso_neto'],
    'ingreso_bruto' => (float)$r['ingreso_bruto'],
    'costo'         => (float)$r['costo'],
    'margen_bruto'  => $margen,
    'margen_pct'    => $margen_pct
  ];

  $tot['facturas'] += (int)$r['facturas'];
  $tot['uds']      += (float)$r['uds'];
  $tot['neto']     += (float)$r['ingreso_neto'];
  $tot['bruto']    += (float)$r['ingreso_bruto'];
  $tot['costo']    += (float)$r['costo'];
  $tot['margen']   += $margen;
}

$tot['margen_pct'] = $tot['neto'] > 0 ? round(($tot['margen'] / $tot['neto']) * 100, 2) : 0.0;

out(true, ['rows'=>$rows, 'count'=>count($rows), 'totales'=>$tot]);
