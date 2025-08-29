<?php
// oc_get.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$c) { echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$id_oc = isset($_GET['id_oc']) ? (int)$_GET['id_oc'] : 0;
if ($id_oc <= 0) { echo json_encode(["ok"=>false,"error"=>"id_oc requerido"]); exit; }

$sqlCab = "
  SELECT
    oc.id_oc, oc.numero_pedido, oc.id_proveedor, prov.nombre AS proveedor,
    oc.fecha_emision, oc.estado, oc.observacion
  FROM public.orden_compra_cab oc
  LEFT JOIN public.proveedores prov ON prov.id_proveedor = oc.id_proveedor
  WHERE oc.id_oc = $1
  LIMIT 1
";
$rc = pg_query_params($c,$sqlCab,[$id_oc]);
if (!$rc || pg_num_rows($rc)==0) { echo json_encode(["ok"=>false,"error"=>"OC no encontrada"]); exit; }
$cab = pg_fetch_assoc($rc);

$sqlDet = "
  SELECT d.id_oc_det, d.id_producto, p.nombre AS producto,
         d.cantidad, d.precio_unit, d.id_presupuesto_detalle
  FROM public.orden_compra_det d
  JOIN public.producto p ON p.id_producto = d.id_producto
  WHERE d.id_oc = $1
  ORDER BY d.id_oc_det
";
$rd = pg_query_params($c,$sqlDet,[$id_oc]);
$det = []; $total = 0;
if ($rd) {
  while ($d = pg_fetch_assoc($rd)) {
    $det[] = $d;
    $total += (float)$d['cantidad'] * (float)$d['precio_unit'];
  }
}

$cab['total_oc'] = number_format($total, 2, '.', '');
$cab['det'] = $det;

echo json_encode(["ok"=>true,"data"=>$cab], JSON_UNESCAPED_UNICODE);
