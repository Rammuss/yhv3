<?php
// POST: id_caja, saldo_inicial
session_start();
if (empty($_SESSION['id_usuario'])) { http_response_code(401); exit; }
require_once __DIR__.'/../../conexion/configv2.php';

header('Content-Type: application/json; charset=utf-8');

function j($ok, $data=[], $code=200){ http_response_code($code); echo json_encode(['ok'=>$ok]+$data); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id_caja = isset($in['id_caja']) ? (int)$in['id_caja'] : 0;
$saldo_inicial = isset($in['saldo_inicial']) ? (float)$in['saldo_inicial'] : 0.0;
$id_usuario = (int)$_SESSION['id_usuario'];

if ($id_caja<=0) j(false,['error'=>'id_caja inválido'],400);

pg_query($conn, 'BEGIN');

try{
  // (a) ¿Usuario ya tiene sesión abierta?
  $sql = "SELECT 1 FROM public.caja_sesion WHERE id_usuario=$1 AND estado='Abierta' LIMIT 1 FOR UPDATE";
  $r = pg_query_params($conn,$sql,[$id_usuario]);
  if ($r && pg_num_rows($r)>0) throw new Exception("El usuario ya posee una sesión abierta.");

  // (b) ¿Caja libre?
  $sql = "SELECT 1 FROM public.caja_sesion WHERE id_caja=$1 AND estado='Abierta' LIMIT 1 FOR UPDATE";
  $r = pg_query_params($conn,$sql,[$id_caja]);
  if ($r && pg_num_rows($r)>0) throw new Exception("La caja ya tiene una sesión abierta.");

  // (c) Crear sesión
  $sql = "INSERT INTO public.caja_sesion(id_caja,id_usuario,saldo_inicial,ip_dispositivo)
          VALUES($1,$2,$3,$4) RETURNING id_caja_sesion, fecha_apertura";
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $r = pg_query_params($conn,$sql,[$id_caja,$id_usuario,$saldo_inicial,$ip]);
  if (!$r) throw new Exception(pg_last_error($conn));
  $sesion = pg_fetch_assoc($r);

  pg_query($conn,'COMMIT');
  j(true,['sesion'=>$sesion],201);
}catch(Exception $e){
  pg_query($conn,'ROLLBACK');
  j(false,['error'=>$e->getMessage()],409);
}
