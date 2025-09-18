<?php
// POST: id_sucursal (opcional), ids_sesiones[ ] (array), observacion (opcional)
session_start();
if (empty($_SESSION['id_usuario'])) { http_response_code(401); exit; }
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');
function j($ok,$data=[],$code=200){ http_response_code($code); echo json_encode(['ok'=>$ok]+$data); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$ids = $in['ids_sesiones'] ?? [];
if (!is_array($ids) || count($ids)==0) j(false,['error'=>'ids_sesiones vacío'],400);

$id_suc = isset($in['id_sucursal']) ? (int)$in['id_sucursal'] : null;
$obs = $in['observacion'] ?? null;

pg_query($conn,'BEGIN');
try{
  // Cabecera
  $r = pg_query_params($conn,
    "INSERT INTO public.recaudacion_deposito(id_sucursal, observacion)
     VALUES($1,$2) RETURNING id_recaudacion",
    [$id_suc, $obs]
  );
  if(!$r) throw new Exception(pg_last_error($conn));
  $idRec = (int)pg_fetch_result($r,0,0);

  // Preparar statements
  $qSes = pg_prepare($conn,'qSes',
    "SELECT id_caja_sesion, estado, COALESCE(conteo_efectivo,0) ef, COALESCE(conteo_tarjeta,0) ta,
            COALESCE(conteo_transferencia,0) tr, COALESCE(conteo_otros,0) ot
     FROM public.caja_sesion WHERE id_caja_sesion=$1 FOR UPDATE");
  $insDet = pg_prepare($conn,'insDet',
    "INSERT INTO public.recaudacion_detalle(id_recaudacion,id_caja_sesion,monto_efectivo,monto_tarjeta,monto_transferencia,monto_otros)
     VALUES($1,$2,$3,$4,$5,$6)");
  $upCab = pg_prepare($conn,'upCab',
    "UPDATE public.recaudacion_deposito SET monto_total=$1, actualizado_en=now() WHERE id_recaudacion=$2");

  $sum = 0.0;
  foreach($ids as $sid){
    $sid = (int)$sid;
    $r = pg_execute($conn,'qSes',[$sid]);
    if(!$r || pg_num_rows($r)==0) throw new Exception("Sesión $sid no existe.");
    $row = pg_fetch_assoc($r);
    if (strcasecmp($row['estado'],'Cerrada')!==0) throw new Exception("Sesión $sid no está cerrada.");

    $ef=(float)$row['ef']; $ta=(float)$row['ta']; $tr=(float)$row['tr']; $ot=(float)$row['ot'];
    $sum += ($ef+$ta+$tr+$ot);

    $r2 = pg_execute($conn,'insDet',[$idRec,$sid,$ef,$ta,$tr,$ot]);
    if(!$r2) throw new Exception(pg_last_error($conn));
  }

  pg_execute($conn,'upCab',[$sum,$idRec]);

  pg_query($conn,'COMMIT');
  j(true,['id_recaudacion'=>$idRec, 'monto_total'=>$sum],201);
}catch(Exception $e){
  pg_query($conn,'ROLLBACK');
  j(false,['error'=>$e->getMessage()],400);
}
