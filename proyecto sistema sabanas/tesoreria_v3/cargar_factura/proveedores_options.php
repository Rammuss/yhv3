<?php
// proveedores_options.php
header('Content-Type: application/json; charset=utf-8');

require_once("../../conexion/configv2.php");
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$conn) { http_response_code(500); echo json_encode([]); exit; }

$sql = "
  SELECT id_proveedor, nombre
  FROM public.proveedores
  -- Si tenés un campo estado, podés filtrar: WHERE estado = 'Activo'
  ORDER BY nombre ASC
";
$res = pg_query($conn, $sql);

$out = [];
if ($res) while ($r = pg_fetch_assoc($res)) $out[] = $r;

echo json_encode($out, JSON_UNESCAPED_UNICODE);
