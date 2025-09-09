<?php
// nota_remision_detalle.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");
$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$id = isset($_GET['id_nota_remision']) ? (int)$_GET['id_nota_remision'] : 0;
if($id<=0){ echo json_encode(["ok"=>false,"error"=>"id requerido"]); exit; }

$sqlCab = "
  SELECT nrc.id_nota_remision, nrc.fecha_remision, nrc.estado,
         nrc.nro_remision_prov, nrc.transportista, nrc.chofer, nrc.vehiculo,
         nrc.observacion,
         prov.nombre AS proveedor, fcc.numero_documento AS nro_factura,
         COALESCE(suc.nombre,'') AS sucursal
  FROM nota_remision_cab nrc
  JOIN proveedores prov ON prov.id_proveedor = nrc.id_proveedor
  JOIN factura_compra_cab fcc ON fcc.id_factura = nrc.id_factura
  LEFT JOIN sucursales suc ON suc.id_sucursal = nrc.id_sucursal
  WHERE nrc.id_nota_remision = $1
";
$rc = pg_query_params($c, $sqlCab, [$id]);
if(!$rc || pg_num_rows($rc)==0){ echo json_encode(["ok"=>false,"error"=>"No existe"]); exit; }
$cab = pg_fetch_assoc($rc);

$sqlDet = "
  SELECT nrd.id_factura_det, nrd.cantidad,
         fcd.id_producto, p.nombre AS producto
  FROM nota_remision_det nrd
  JOIN factura_compra_det fcd ON fcd.id_factura_det = nrd.id_factura_det
  JOIN producto p ON p.id_producto = fcd.id_producto
  WHERE nrd.id_nota_remision = $1
  ORDER BY nrd.id_factura_det
";
$rd = pg_query_params($c, $sqlDet, [$id]);
$det = [];
while($r = pg_fetch_assoc($rd)){
  $det[] = [
    "id_factura_det" => (int)$r["id_factura_det"],
    "id_producto"    => (int)$r["id_producto"],
    "producto"       => $r["producto"],
    "cantidad"       => (float)$r["cantidad"]
  ];
}

echo json_encode(["ok"=>true, "cab"=>$cab, "det"=>$det], JSON_UNESCAPED_UNICODE);
