<?php
// pedidos_options.php
header('Content-Type: application/json; charset=utf-8');

require_once("../conexion/config.php");
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$conn) { http_response_code(500); echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$sql = "
  SELECT numero_pedido, departamento_solicitante, estado, fecha_pedido
  FROM public.cabecera_pedido_interno
  WHERE estado <> 'Anulado'
  ORDER BY numero_pedido DESC
  LIMIT 500
";
$res = pg_query($conn, $sql);
$out = [];
if ($res) {
  while ($r = pg_fetch_assoc($res)) $out[] = $r;
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
