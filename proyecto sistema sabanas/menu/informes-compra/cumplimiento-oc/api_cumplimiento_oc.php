<?php
// api_cumplimiento_oc.php — Cumplimiento OC vs Facturas (independiente de columnas específicas de proveedor/sucursal)
header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'No autenticado']);
  exit;
}

require_once __DIR__ . '../../../../conexion/configv2.php';

function jerr($msg, $detail = null) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $msg, 'detail' => $detail]);
  exit;
}

// arma un nombre legible de proveedor desde un array asociativo (de row_to_json)
function label_proveedor($p, $id_fallback) {
  if (!is_array($p)) return 'Proveedor ' . (string)$id_fallback;
  $cand = [
    $p['razon_social'] ?? null,
    trim(((($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? '')))) ?: null,
    isset($p['nombre']) ? $p['nombre'] : null,
    isset($p['ruc']) ? ('RUC ' . $p['ruc']) : null,
    isset($p['ruc_ci']) ? ('RUC ' . $p['ruc_ci']) : null,
  ];
  foreach ($cand as $c) {
    if ($c !== null && $c !== '') return $c;
  }
  return 'Proveedor ' . (string)$id_fallback;
}

// arma un nombre legible de sucursal desde un array asociativo (de row_to_json)
function label_sucursal($s, $id_fallback) {
  if (!is_array($s)) return 'Sucursal ' . (string)$id_fallback;
  $cand = [
    $s['descripcion'] ?? null,
    $s['nombre'] ?? null,
    $s['codigo'] ?? null,
  ];
  foreach ($cand as $c) {
    if ($c !== null && $c !== '') return $c;
  }
  return 'Sucursal ' . (string)$id_fallback;
}

try {
  // Filtros
  $fecha_oc_desde   = $_GET['fecha_oc_desde']   ?? null; // YYYY-MM-DD
  $fecha_oc_hasta   = $_GET['fecha_oc_hasta']   ?? null;
  $fecha_fac_desde  = $_GET['fecha_fac_desde']  ?? null;
  $fecha_fac_hasta  = $_GET['fecha_fac_hasta']  ?? null;
  $proveedor_id     = (isset($_GET['proveedor_id']) && $_GET['proveedor_id']!=='') ? (int)$_GET['proveedor_id'] : null;
  $sucursal_id      = (isset($_GET['sucursal_id'])  && $_GET['sucursal_id']!=='')  ? (int)$_GET['sucursal_id']  : null;
  $estado_oc        = $_GET['estado_oc']        ?? null;
  $condicion_pago   = $_GET['condicion_pago']   ?? null;

  $params = [];
  $w_oc   = [];
  if ($fecha_oc_desde) { $params[] = $fecha_oc_desde; $w_oc[] = "oc.fecha_emision >= $" . count($params); }
  if ($fecha_oc_hasta) { $params[] = $fecha_oc_hasta; $w_oc[] = "oc.fecha_emision <= $" . count($params); }
  if ($proveedor_id)   { $params[] = $proveedor_id;   $w_oc[] = "oc.id_proveedor = $"  . count($params); }
  if ($sucursal_id)    { $params[] = $sucursal_id;    $w_oc[] = "oc.id_sucursal  = $"  . count($params); }
  if ($estado_oc !== null && $estado_oc !== '') {
    $params[] = $estado_oc; $w_oc[] = "oc.estado = $" . count($params);
  }
  if ($condicion_pago !== null && $condicion_pago !== '') {
    $params[] = $condicion_pago; $w_oc[] = "oc.condicion_pago = $" . count($params);
  }
  $where_oc = $w_oc ? ('WHERE ' . implode(' AND ', $w_oc)) : '';

  // Filtros de factura (dentro del CTE de facturas)
  $fac_filters = '';
  if ($fecha_fac_desde) { $params[] = $fecha_fac_desde; $fac_filters .= " AND fcc.fecha_emision >= $" . count($params); }
  if ($fecha_fac_hasta) { $params[] = $fecha_fac_hasta; $fac_filters .= " AND fcc.fecha_emision <= $" . count($params); }

  $sql = "
  WITH oc AS (
    SELECT
      oc.id_oc,
      oc.numero_pedido,
      oc.id_proveedor,
      oc.id_sucursal,
      oc.fecha_emision,
      oc.estado,
      oc.condicion_pago,
      SUM(ocd.cantidad * ocd.precio_unit)::numeric(14,2) AS monto_ordenado
    FROM public.orden_compra_cab oc
    JOIN public.orden_compra_det ocd ON ocd.id_oc = oc.id_oc
    $where_oc
    GROUP BY oc.id_oc
  ),
  fac AS (
    SELECT
      ocd.id_oc,
      SUM(fcd.subtotal)::numeric(14,2) AS monto_facturado
    FROM public.factura_compra_det fcd
    JOIN public.orden_compra_det ocd ON ocd.id_oc_det = fcd.id_oc_det
    JOIN public.factura_compra_cab fcc ON fcc.id_factura = fcd.id_factura
    WHERE 1=1
      $fac_filters
    GROUP BY ocd.id_oc
  )
  SELECT
    oc.id_oc,
    oc.numero_pedido,
    oc.fecha_emision,
    oc.estado,
    oc.condicion_pago,
    oc.id_proveedor,
    oc.id_sucursal,
    oc.monto_ordenado,
    COALESCE(fac.monto_facturado, 0)::numeric(14,2) AS monto_facturado,
    (oc.monto_ordenado - COALESCE(fac.monto_facturado, 0))::numeric(14,2) AS pendiente,
    CASE WHEN oc.monto_ordenado > 0
         THEN ROUND(COALESCE(fac.monto_facturado,0)/oc.monto_ordenado*100, 2)
         ELSE 0 END AS cumplimiento_pct,
    row_to_json(p) AS proveedor_json,
    row_to_json(s) AS sucursal_json
  FROM oc
  LEFT JOIN fac ON fac.id_oc = oc.id_oc
  LEFT JOIN public.proveedores p ON p.id_proveedor = oc.id_proveedor
  LEFT JOIN public.sucursales s  ON s.id_sucursal  = oc.id_sucursal
  ORDER BY oc.fecha_emision DESC, oc.id_oc DESC
  ";

  $res = pg_query_params($conn, $sql, $params);
  if (!$res) {
    jerr('Error de base de datos', pg_last_error($conn));
  }

  $rows = [];
  $tot_orden = 0.0; $tot_fact = 0.0; $tot_pend = 0.0;

  while ($r = pg_fetch_assoc($res)) {
    $mo = (float)$r['monto_ordenado'];
    $mf = (float)$r['monto_facturado'];
    $pe = (float)$r['pendiente'];

    $prov_json = isset($r['proveedor_json']) ? json_decode($r['proveedor_json'], true) : null;
    $suc_json  = isset($r['sucursal_json'])  ? json_decode($r['sucursal_json'],  true) : null;

    $rows[] = [
      'id_oc'            => (int)$r['id_oc'],
      'numero_pedido'    => (int)$r['numero_pedido'],
      'fecha_emision'    => $r['fecha_emision'],
      'estado'           => $r['estado'],
      'condicion_pago'   => $r['condicion_pago'],
      'proveedor'        => label_proveedor($prov_json, (int)$r['id_proveedor']),
      'sucursal'         => label_sucursal($suc_json, (int)$r['id_sucursal']),
      'monto_ordenado'   => round($mo, 2),
      'monto_facturado'  => round($mf, 2),
      'pendiente'        => round($pe, 2),
      'cumplimiento_pct' => (float)$r['cumplimiento_pct']
    ];

    $tot_orden += $mo; $tot_fact += $mf; $tot_pend += $pe;
  }

  echo json_encode([
    'success' => true,
    'totals' => [
      'monto_ordenado'  => round($tot_orden, 2),
      'monto_facturado' => round($tot_fact, 2),
      'pendiente'       => round($tot_pend, 2)
    ],
    'rows' => $rows
  ]);

} catch (Throwable $e) {
  jerr('Excepción', $e->getMessage());
}
