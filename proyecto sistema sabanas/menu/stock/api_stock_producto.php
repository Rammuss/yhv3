<?php
// api_stock_producto.php
header('Content-Type: application/json');
include("../../conexion/configv2.php");

$id = intval($_GET['id_producto'] ?? 0);
if ($id <= 0) { echo json_encode(['ok'=>false, 'msg'=>'id inválido']); exit; }

$q = "SELECT public.fn_stock_actual($1) AS stock_actual";
$r = pg_query_params($conn, $q, [$id]);
$row = $r ? pg_fetch_assoc($r) : null;

echo json_encode([
  'ok' => true,
  'stock_actual' => intval($row['stock_actual'] ?? 0)
]);
