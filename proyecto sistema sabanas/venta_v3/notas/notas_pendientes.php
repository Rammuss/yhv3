<?php
session_start();
if (empty($_SESSION['id_usuario'])) { http_response_code(401); exit; }
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json');

$filtro = isset($_GET['filtro']) ? trim($_GET['filtro']) : "";

$sql = "
  SELECT nc.id_nc, nc.numero_documento, nc.total_neto, c.nombre || ' ' || c.apellido AS cliente
  FROM public.nc_venta_cab nc
  JOIN public.clientes c ON c.id_cliente = nc.id_cliente
  WHERE nc.estado = 'Emitida'
";
$params = [];
if ($filtro !== "") {
  $sql .= " AND (nc.numero_documento ILIKE $1 OR c.nombre ILIKE $1 OR c.apellido ILIKE $1)";
  $params[] = "%".$filtro."%";
}
$sql .= " ORDER BY nc.fecha_emision DESC LIMIT 20";

$r = pg_query_params($conn,$sql,$params);
$data = [];
if($r){ while($row=pg_fetch_assoc($r)) $data[]=$row; }

echo json_encode(['ok'=>true,'data'=>$data]);
