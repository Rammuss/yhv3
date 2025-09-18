<?php
// GET: ?sucursal=ID (opcional)
session_start();
if (empty($_SESSION['id_usuario'])) { http_response_code(401); exit; }
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

$suc = isset($_GET['sucursal']) ? (int)$_GET['sucursal'] : null;

$sql = "SELECT cs.*, c.nombre AS caja_nombre, c.id_sucursal
        FROM public.v_caja_sesiones_abiertas cs
        JOIN public.caja c ON c.id_caja = cs.id_caja";
$params = [];
if ($suc){ $sql .= " WHERE c.id_sucursal = $1"; $params[] = $suc; }

$r = $params ? pg_query_params($conn,$sql,$params) : pg_query($conn,$sql);
if(!$r){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>pg_last_error($conn)]); exit; }

$out = [];
while($x = pg_fetch_assoc($r)){ $out[] = $x; }

echo json_encode(['ok'=>true,'sesiones'=>$out]);
