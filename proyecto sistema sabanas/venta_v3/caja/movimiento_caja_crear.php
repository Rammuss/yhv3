<?php
// POST: id_caja_sesion, tipo(Ingreso|Egreso), origen(Venta|Devolucion|Gasto|Retiro|Ajuste|Deposito),
//       id_referencia (opcional), medio(Efectivo|Tarjeta|Transferencia|Cheque|Credito|Otros),
//       monto, descripcion (opcional)
session_start();
if (empty($_SESSION['id_usuario'])) { http_response_code(401); exit; }
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');
function j($ok,$data=[],$code=200){ http_response_code($code); echo json_encode(['ok'=>$ok]+$data); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id_sesion = (int)($in['id_caja_sesion'] ?? 0);
$tipo      = $in['tipo'] ?? '';
$origen    = $in['origen'] ?? '';
$id_ref    = isset($in['id_referencia']) ? (int)$in['id_referencia'] : null;
$medio     = $in['medio'] ?? '';
$monto     = (float)($in['monto'] ?? 0);
$desc      = $in['descripcion'] ?? null;
$id_usuario= (int)$_SESSION['id_usuario'];

if ($id_sesion<=0 || $monto<0.0) j(false,['error'=>'Datos incompletos'],400);

pg_query($conn,'BEGIN');
try{
  // Validar sesión abierta
  $r = pg_query_params($conn,"SELECT estado FROM public.caja_sesion WHERE id_caja_sesion=$1 FOR UPDATE",[$id_sesion]);
  if(!$r || pg_num_rows($r)==0) throw new Exception("Sesión inexistente.");
  if(strcasecmp(pg_fetch_result($r,0,0),'Abierta')!==0) throw new Exception("La sesión no está abierta.");

  // Insertar movimiento
  $sql = "INSERT INTO public.movimiento_caja
            (id_caja_sesion,tipo,origen,id_referencia,medio,monto,descripcion,id_usuario)
          VALUES ($1,$2,$3,$4,$5,$6,$7,$8)
          RETURNING id_movimiento, fecha";
  $r = pg_query_params($conn,$sql,[$id_sesion,$tipo,$origen,$id_ref,$medio,$monto,$desc,$id_usuario]);
  if(!$r) throw new Exception(pg_last_error($conn));
  $mov = pg_fetch_assoc($r);

  pg_query($conn,'COMMIT');
  j(true,['movimiento'=>$mov],201);
}catch(Exception $e){
  pg_query($conn,'ROLLBACK');
  j(false,['error'=>$e->getMessage()],400);
}
