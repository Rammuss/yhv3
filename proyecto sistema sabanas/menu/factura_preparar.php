<?php
// factura_preparar.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$id_prov = isset($_GET['id_proveedor']) ? (int)$_GET['id_proveedor'] : 0;
$pedido  = isset($_GET['numero_pedido']) ? (int)$_GET['numero_pedido'] : 0;

if ($id_prov <= 0) { echo json_encode(["ok"=>false,"error"=>"id_proveedor requerido"]); exit; }

$where = "occ.id_proveedor=$1 AND occ.estado<>'Anulada'";
$params = [$id_prov];

if ($pedido > 0) { $where .= " AND occ.numero_pedido=$2"; $params[] = $pedido; }

$sql = "
  SELECT
    occ.id_oc, occ.numero_pedido, occ.estado AS estado_oc, occ.fecha_emision,
    ocd.id_oc_det, ocd.id_producto, p.nombre AS producto,
    ocd.cantidad AS oc_cantidad,
    COALESCE((
      SELECT SUM(fcd.cantidad)
      FROM factura_compra_det fcd
      JOIN factura_compra_cab fcc ON fcc.id_factura=fcd.id_factura
      WHERE fcd.id_oc_det=ocd.id_oc_det AND fcc.estado<>'Anulada'
    ),0)::int AS ya_facturado
  FROM orden_compra_cab occ
  JOIN orden_compra_det ocd ON ocd.id_oc = occ.id_oc
  JOIN producto p ON p.id_producto = ocd.id_producto
  WHERE $where
  ORDER BY occ.id_oc, ocd.id_oc_det
";

$res = pg_query_params($c, $sql, $params);
if(!$res){ echo json_encode(["ok"=>false,"error"=>"Query"]); exit; }

$out = [];
while($r = pg_fetch_assoc($res)){
  $pend = (int)$r['oc_cantidad'] - (int)$r['ya_facturado'];
  if ($pend > 0){
    $r['pendiente'] = $pend;
    $out[] = $r;
  }
}

echo json_encode(["ok"=>true,"data"=>$out], JSON_UNESCAPED_UNICODE);
