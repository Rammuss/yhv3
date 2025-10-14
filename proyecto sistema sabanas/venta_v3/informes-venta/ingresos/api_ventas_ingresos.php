<?php
// api_ventas_ingresos.php — JSON: ingresos y margen por período / cliente / producto
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '../../../../conexion/configv2.php';

function out($ok, $payload = [], $http = 200) {
  http_response_code($http);
  echo json_encode($ok ? (['success'=>true] + $payload) : (['success'=>false] + $payload), JSON_UNESCAPED_UNICODE);
  exit;
}

// -------------------------
// Filtros
// -------------------------
$desde       = $_GET['desde']        ?? null;     // YYYY-MM-DD
$hasta       = $_GET['hasta']        ?? null;     // YYYY-MM-DD
$id_cliente  = (isset($_GET['id_cliente']) && is_numeric($_GET['id_cliente'])) ? (int)$_GET['id_cliente'] : null;
$condicion   = $_GET['condicion']    ?? null;     // Contado | Credito
$group       = $_GET['group']        ?? 'dia';    // dia | mes | cliente | producto

// Validaciones suaves
if ($desde && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = null;
if ($hasta && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = null;
if ($condicion && !in_array($condicion, ['Contado','Credito'], true)) $condicion = null;
if (!in_array($group, ['dia','mes','cliente','producto'], true)) $group = 'dia';

// -------------------------
// WHERE dinámico
// -------------------------
$where  = ["vc.estado <> 'Anulada'"];
$params = [];

if ($desde)     { $params[] = $desde;     $where[] = "vc.fecha_emision >= $" . count($params); }
if ($hasta)     { $params[] = $hasta;     $where[] = "vc.fecha_emision <= $" . count($params); }
if ($id_cliente){ $params[] = $id_cliente;$where[] = "vc.id_cliente = $" . count($params); }
if ($condicion) { $params[] = $condicion; $where[] = "vc.condicion_venta = $" . count($params); }

$WHERE = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// -------------------------
// Agrupación
// -------------------------
switch ($group) {
  case 'mes':
    $grp_select = "to_char(vc.fecha_emision, 'YYYY-MM') AS grupo";
    $grp_group  = "to_char(vc.fecha_emision, 'YYYY-MM')";
    $grp_order  = "to_char(vc.fecha_emision, 'YYYY-MM')";
    break;
  case 'cliente':
    // Evitamos nulls en nombre/apellido
    $grp_select = "vc.id_cliente::int AS grupo_id, (COALESCE(cl.nombre,'') || ' ' || COALESCE(cl.apellido,'')) AS grupo";
    $grp_group  = "vc.id_cliente, (COALESCE(cl.nombre,'') || ' ' || COALESCE(cl.apellido,''))";
    $grp_order  = "(COALESCE(cl.nombre,'') || ' ' || COALESCE(cl.apellido,''))";
    break;
  case 'producto':
    $grp_select = "vd.id_producto::int AS grupo_id, COALESCE(p.nombre, vd.descripcion) AS grupo";
    $grp_group  = "vd.id_producto, COALESCE(p.nombre, vd.descripcion)";
    $grp_order  = "COALESCE(p.nombre, vd.descripcion)";
    break;
  case 'dia':
  default:
    $grp_select = "vc.fecha_emision::date AS grupo";
    $grp_group  = "vc.fecha_emision";
    $grp_order  = "vc.fecha_emision";
    break;
}

// -------------------------
// Cálculo
//   ingreso_con_iva  = SUM(vd.cantidad * vd.precio_unitario)
//   ingreso_sin_iva  = SUM(vd.subtotal_neto)         (base imponible)
//   costo            = SUM(vd.cantidad * p.precio_compra)
//   margen_bruto     = ingreso_sin_iva - costo
//   margen_pct       = (margen_bruto / ingreso_sin_iva) * 100
// -------------------------
$sql = "
  SELECT
    {$grp_select},
    COUNT(DISTINCT vc.id_factura)                         AS facturas,
    SUM(vd.cantidad)                                      AS uds,

    -- Ingresos con/sin IVA (subtotal_neto incluye IVA y descuentos)
    SUM(vd.subtotal_neto)                                  AS ingreso_con_iva,
    SUM(vd.subtotal_neto - COALESCE(vd.iva_monto,0))       AS ingreso_sin_iva,

    -- Costo y margen (sobre ingreso sin IVA)
    SUM(vd.cantidad * COALESCE(p.precio_compra,0))        AS costo,
    (SUM(vd.subtotal_neto - COALESCE(vd.iva_monto,0)) - SUM(vd.cantidad * COALESCE(p.precio_compra,0))) AS margen_bruto
  FROM public.factura_venta_cab vc
  JOIN public.factura_venta_det vd ON vd.id_factura = vc.id_factura
  LEFT JOIN public.producto p      ON p.id_producto = vd.id_producto
  LEFT JOIN public.clientes cl     ON cl.id_cliente = vc.id_cliente
  $WHERE
  GROUP BY {$grp_group}
  ORDER BY {$grp_order} ASC
";

$res = pg_query_params($conn, $sql, $params);
if (!$res) {
  out(false, ['error'=>'Error de base de datos', 'detail'=>pg_last_error($conn)], 500);
}

// -------------------------
// Salida
// -------------------------
$data = [];
while ($r = pg_fetch_assoc($res)) {
  $ingreso_con_iva = (float)$r['ingreso_con_iva'];
  $ingreso_sin_iva = (float)$r['ingreso_sin_iva'];
  $costo           = (float)$r['costo'];
  $margen          = (float)$r['margen_bruto'];

  $data[] = [
    'grupo'         => $r['grupo'],
    'grupo_id'      => isset($r['grupo_id']) ? (int)$r['grupo_id'] : null,
    'facturas'      => (int)$r['facturas'],
    'uds'           => (float)$r['uds'],

    // Exponemos con nombres coherentes para la UI actual:
    'ingreso_bruto' => $ingreso_con_iva,    // BRUTO = CON IVA
    'ingreso_neto'  => $ingreso_sin_iva,    // NETO  = SIN IVA (base imponible)

    'costo'         => $costo,
    'margen_bruto'  => $margen,             // margen sobre NETO
    'margen_pct'    => ($ingreso_sin_iva > 0) ? round(($margen / $ingreso_sin_iva) * 100, 2) : 0
  ];
}

out(true, ['rows'=>$data, 'count'=>count($data)]);
