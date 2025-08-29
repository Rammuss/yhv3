<?php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");
$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ http_response_code(500); echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$pedido = isset($_GET['numero_pedido']) ? (int)$_GET['numero_pedido'] : 0;
$prov   = isset($_GET['id_proveedor'])  ? (int)$_GET['id_proveedor']  : 0;
if ($pedido<=0 || $prov<=0){ http_response_code(400); echo json_encode(["ok"=>false,"error"=>"Parámetros"]); exit; }

$sql = "
SELECT
  pd.id_presupuesto_detalle,
  pd.id_producto,
  p.nombre AS producto,
  pd.precio_unitario,
  pd.cantidad_aprobada    -- disponible para OC (estrategia simple)
FROM public.presupuesto_detalle pd
JOIN public.presupuestos pr ON pr.id_presupuesto = pd.id_presupuesto
JOIN public.producto p ON p.id_producto = pd.id_producto
WHERE pr.numero_pedido = $1
  AND pr.id_proveedor  = $2
  AND pd.estado_detalle = 'Aprobado'
ORDER BY p.nombre;
";
$r = pg_query_params($c,$sql,[$pedido,$prov]);
$rows=[]; if($r) while($x=pg_fetch_assoc($r)) $rows[]=$x;

echo json_encode(["ok"=>true,"data"=>$rows], JSON_UNESCAPED_UNICODE);
