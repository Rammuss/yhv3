<?php
// notas_compra_detalle.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");
$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$id = isset($_GET['id_nota']) ? (int)$_GET['id_nota'] : 0;
if($id<=0){ echo json_encode(["ok"=>false,"error"=>"id requerido"]); exit; }

$sqlCab = "
  SELECT ncc.id_nota, ncc.fecha_emision, ncc.tipo, ncc.clase, ncc.estado,
         ncc.numero_documento, ncc.timbrado_numero, ncc.moneda, ncc.total_nota,
         ncc.observacion,
         prov.nombre AS proveedor,
         COALESCE(suc.nombre,'') AS sucursal,
         COALESCE(fcc.numero_documento,'') AS nro_factura_ref
  FROM notas_compra_cab ncc
  JOIN proveedores prov ON prov.id_proveedor = ncc.id_proveedor
  LEFT JOIN sucursales suc ON suc.id_sucursal = ncc.id_sucursal
  LEFT JOIN factura_compra_cab fcc ON fcc.id_factura = ncc.id_factura_ref
  WHERE ncc.id_nota = $1
";
$rc = pg_query_params($c, $sqlCab, [$id]);
if(!$rc || pg_num_rows($rc)==0){ echo json_encode(["ok"=>false,"error"=>"No existe"]); exit; }
$cab = pg_fetch_assoc($rc);

$sqlDet = "
  SELECT ncd.id_nota_det, ncd.id_producto, p.nombre AS producto,
         ncd.descripcion, ncd.cantidad, ncd.precio_unitario, ncd.tipo_iva, ncd.subtotal
  FROM notas_compra_det ncd
  LEFT JOIN producto p ON p.id_producto = ncd.id_producto
  WHERE ncd.id_nota = $1
  ORDER BY ncd.id_nota_det
";
$rd = pg_query_params($c, $sqlDet, [$id]);
$det = [];
while($r = pg_fetch_assoc($rd)){
  $det[] = [
    "id_nota_det"    => (int)$r["id_nota_det"],
    "id_producto"    => $r["id_producto"]!==null ? (int)$r["id_producto"] : null,
    "producto"       => $r["producto"],
    "descripcion"    => $r["descripcion"],
    "cantidad"       => (float)$r["cantidad"],
    "precio_unitario"=> (float)$r["precio_unitario"],
    "tipo_iva"       => $r["tipo_iva"],
    "subtotal"       => (float)$r["subtotal"]
  ];
}

echo json_encode(["ok"=>true, "cab"=>$cab, "det"=>$det], JSON_UNESCAPED_UNICODE);
