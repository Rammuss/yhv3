<?php
// factura_listar.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$q = "
  SELECT f.id_factura, f.id_proveedor, p.nombre AS proveedor,
         f.fecha_emision, f.numero_documento, f.estado, f.total_factura
  FROM factura_compra_cab f
  LEFT JOIN proveedores p ON p.id_proveedor=f.id_proveedor
  WHERE 1=1
";
$P = [];

if (!empty($_GET['id_proveedor'])){ $P[] = (int)$_GET['id_proveedor']; $q.=" AND f.id_proveedor=$".count($P); }
if (!empty($_GET['estado'])){ $P[] = $_GET['estado']; $q.=" AND f.estado=$".count($P); }
if (!empty($_GET['desde'])){ $P[] = $_GET['desde']; $q.=" AND f.fecha_emision>=$".count($P); }
if (!empty($_GET['hasta'])){ $P[] = $_GET['hasta']; $q.=" AND f.fecha_emision<=$".count($P); }

$q .= " ORDER BY f.id_factura DESC LIMIT 500";

$res = $P ? pg_query_params($c,$q,$P) : pg_query($c,$q);
if(!$res){ echo json_encode(["ok"=>false,"error"=>"Query"]); exit; }

$out = [];
while($r = pg_fetch_assoc($res)) $out[] = $r;

echo json_encode(["ok"=>true,"data"=>$out], JSON_UNESCAPED_UNICODE);
