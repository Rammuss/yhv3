<?php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");
$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ http_response_code(500); echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$pedido = isset($_GET['numero_pedido']) ? (int)$_GET['numero_pedido'] : 0;
if ($pedido<=0){ http_response_code(400); echo json_encode(["ok"=>false,"error"=>"numero_pedido requerido"]); exit; }

$sqlCab = "
  SELECT oc.id_oc, oc.numero_pedido, oc.id_proveedor, prov.nombre AS proveedor,
         oc.fecha_emision, oc.estado, oc.observacion
  FROM public.orden_compra_cab oc
  LEFT JOIN public.proveedores prov ON prov.id_proveedor = oc.id_proveedor
  WHERE oc.numero_pedido = $1
  ORDER BY oc.id_oc DESC
";
$rc = pg_query_params($c,$sqlCab,[$pedido]);

$map = []; $ids=[];
if($rc){
  while($x=pg_fetch_assoc($rc)){
    $x['id_oc']=(int)$x['id_oc'];
    $x['det']=[]; $ids[]=$x['id_oc'];
    $map[$x['id_oc']]=$x;
  }
}
if(empty($ids)){ echo json_encode(["ok"=>true,"data"=>[]]); exit; }

$in = "(".implode(",", array_map('intval',$ids)).")";
$sqlDet = "
  SELECT d.id_oc_det, d.id_oc, d.id_producto, p.nombre AS producto,
         d.cantidad, d.precio_unit, d.id_presupuesto_detalle
  FROM public.orden_compra_det d
  JOIN public.producto p ON p.id_producto = d.id_producto
  WHERE d.id_oc IN $in
  ORDER BY d.id_oc, d.id_oc_det
";
$rd = pg_query($c,$sqlDet);
if($rd){
  while($d=pg_fetch_assoc($rd)){
    $map[(int)$d['id_oc']]['det'][] = $d;
  }
}

echo json_encode(["ok"=>true,"data"=>array_values($map)], JSON_UNESCAPED_UNICODE);
