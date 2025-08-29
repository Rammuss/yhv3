<?php
// pedido_resumen.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$conn) { http_response_code(500); echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$numero_pedido = isset($_GET['numero_pedido']) ? (int)$_GET['numero_pedido'] : 0;
if ($numero_pedido <= 0) { http_response_code(400); echo json_encode(["ok"=>false,"error"=>"numero_pedido inválido"]); exit; }

$sql = "
SELECT
  d.id_producto,
  p.nombre,
  p.precio_compra AS precio_sugerido,
  d.cantidad                                   AS pedida,
  COALESCE(q.aprobada,0)::int                  AS aprobada,
  (d.cantidad - COALESCE(q.aprobada,0))::int   AS pendiente
FROM public.detalle_pedido_interno d
JOIN public.producto p ON p.id_producto = d.id_producto
LEFT JOIN (
  SELECT pr.numero_pedido, pd.id_producto,
         SUM(pd.cantidad_aprobada) AS aprobada
  FROM public.presupuestos pr
  JOIN public.presupuesto_detalle pd ON pd.id_presupuesto = pr.id_presupuesto
  WHERE pd.estado_detalle = 'Aprobado'
  GROUP BY pr.numero_pedido, pd.id_producto
) q ON q.numero_pedido = d.numero_pedido AND q.id_producto = d.id_producto
WHERE d.numero_pedido = $1
ORDER BY p.nombre;
";

$res = pg_query_params($conn, $sql, [$numero_pedido]);

$out = [];
if ($res) while ($r = pg_fetch_assoc($res)) $out[] = $r;

echo json_encode(["ok"=>true, "data"=>$out], JSON_UNESCAPED_UNICODE);
