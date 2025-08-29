<?php
// oc_listar.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$c) { echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$numero_pedido = isset($_GET['numero_pedido']) ? (int)$_GET['numero_pedido'] : 0;
$id_proveedor  = isset($_GET['id_proveedor'])  ? (int)$_GET['id_proveedor']  : 0;
$estado        = isset($_GET['estado'])        ? trim($_GET['estado'])       : '';
$desde         = isset($_GET['desde'])         ? trim($_GET['desde'])        : '';
$hasta         = isset($_GET['hasta'])         ? trim($_GET['hasta'])        : '';
$include_det   = isset($_GET['include_det'])   ? (int)$_GET['include_det']   : 0;

$w = []; $p = [];
if ($numero_pedido > 0) { $w[] = "oc.numero_pedido = $".(count($p)+1); $p[] = $numero_pedido; }
if ($id_proveedor  > 0) { $w[] = "oc.id_proveedor  = $".(count($p)+1); $p[] = $id_proveedor; }
if ($estado !== '')     { $w[] = "oc.estado ILIKE $".(count($p)+1);     $p[] = $estado; }
if ($desde !== '')      { $w[] = "oc.fecha_emision >= $".(count($p)+1); $p[] = $desde; }
if ($hasta !== '')      { $w[] = "oc.fecha_emision <= $".(count($p)+1); $p[] = $hasta; }

$where = $w ? "WHERE ".implode(" AND ", $w) : "";

$sql = "
  SELECT
    oc.id_oc,
    oc.numero_pedido,
    oc.id_proveedor,
    prov.nombre AS proveedor,
    oc.fecha_emision,
    oc.estado,
    oc.observacion,
    COALESCE(SUM(od.cantidad * od.precio_unit),0)::numeric(14,2) AS total_oc
  FROM public.orden_compra_cab oc
  LEFT JOIN public.proveedores prov ON prov.id_proveedor = oc.id_proveedor
  LEFT JOIN public.orden_compra_det od ON od.id_oc = oc.id_oc
  $where
  GROUP BY oc.id_oc, oc.numero_pedido, oc.id_proveedor, prov.nombre, oc.fecha_emision, oc.estado, oc.observacion
  ORDER BY oc.id_oc DESC
  LIMIT 500
";
$res = $p ? pg_query_params($c,$sql,$p) : pg_query($c,$sql);
$out = [];
$ids = [];
if ($res) {
  while ($r = pg_fetch_assoc($res)) {
    $r['id_oc'] = (int)$r['id_oc'];
    $ids[] = $r['id_oc'];
    if ($include_det) $r['det'] = [];
    $out[$r['id_oc']] = $r;
  }
}

if ($include_det && !empty($ids)) {
  $in = "(".implode(",", array_map('intval',$ids)).")";
  $sqlDet = "
    SELECT
      d.id_oc_det, d.id_oc, d.id_producto, p.nombre AS producto, d.cantidad, d.precio_unit, d.id_presupuesto_detalle
    FROM public.orden_compra_det d
    JOIN public.producto p ON p.id_producto = d.id_producto
    WHERE d.id_oc IN $in
    ORDER BY d.id_oc, d.id_oc_det
  ";
  $rd = pg_query($c,$sqlDet);
  if ($rd) {
    while ($d = pg_fetch_assoc($rd)) {
      $out[(int)$d['id_oc']]['det'][] = $d;
    }
  }
}

echo json_encode(["ok"=>true,"data"=>array_values($out)], JSON_UNESCAPED_UNICODE);
