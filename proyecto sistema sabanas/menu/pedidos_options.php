<?php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ echo json_encode([]); exit; }

$scope = isset($_GET['scope']) ? trim($_GET['scope']) : ''; // ej: presupuesto

$where = "1=1";
$params = [];
if ($scope === 'presupuesto') {
  // Solo pedidos en los que tiene sentido cargar presupuestos
  $where = "estado IN ('Abierto','Parcialmente Entregado')";
}

$sql = "
  SELECT
    numero_pedido,
    departamento_solicitante,
    estado
  FROM public.cabecera_pedido_interno
  WHERE $where
  ORDER BY numero_pedido DESC
  LIMIT 500
";

$res = pg_query($c, $sql);
$out = [];
if ($res) while ($r = pg_fetch_assoc($res)) $out[] = $r;

echo json_encode($out, JSON_UNESCAPED_UNICODE);
