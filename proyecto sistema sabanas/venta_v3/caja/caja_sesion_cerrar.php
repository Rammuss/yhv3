<?php
// POST (o GET+POST): id (id_caja_sesion)
// body: conteo_efectivo, conteo_tarjeta, conteo_transferencia, conteo_otros, observacion
session_start();
if (empty($_SESSION['id_usuario'])) { http_response_code(401); exit; }
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');
function j($ok,$data=[],$code=200){ http_response_code($code); echo json_encode(['ok'=>$ok]+$data); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$cEf = isset($in['conteo_efectivo'])      ? (float)$in['conteo_efectivo']      : 0;
$cTa = isset($in['conteo_tarjeta'])       ? (float)$in['conteo_tarjeta']       : 0;
$cTr = isset($in['conteo_transferencia']) ? (float)$in['conteo_transferencia'] : 0;
$cOt = isset($in['conteo_otros'])         ? (float)$in['conteo_otros']         : 0;
$obs = $in['observacion'] ?? null;

if ($id<=0) j(false,['error'=>'id de sesión inválido'],400);

pg_query($conn,'BEGIN');
try{
  // Lock + estado
  $r = pg_query_params($conn,"SELECT estado FROM public.caja_sesion WHERE id_caja_sesion=$1 FOR UPDATE",[$id]);
  if(!$r || pg_num_rows($r)==0) throw new Exception("Sesión inexistente.");
  if(strcasecmp(pg_fetch_result($r,0,0),'Abierta')!==0) throw new Exception("La sesión ya está cerrada.");

  // Totales teóricos desde la vista (o calcular directo de movimiento_caja)
  $sqlT = "SELECT COALESCE(efectivo,0),COALESCE(tarjeta,0),COALESCE(transferencia,0),COALESCE(otros,0)
           FROM public.v_caja_saldos_teoricos WHERE id_caja_sesion=$1";
  $rt = pg_query_params($conn,$sqlT,[$id]);
  if(!$rt) throw new Exception(pg_last_error($conn));
  $row = pg_fetch_row($rt);
  $tEf = (float)($row[0] ?? 0); $tTa=(float)($row[1] ?? 0); $tTr=(float)($row[2] ?? 0); $tOt=(float)($row[3] ?? 0);

  $diff = ($cEf+$cTa+$cTr+$cOt) - ($tEf+$tTa+$tTr+$tOt);

  $sqlU = "UPDATE public.caja_sesion
              SET estado='Cerrada',
                  fecha_cierre=now(),
                  conteo_efectivo=$1, conteo_tarjeta=$2, conteo_transferencia=$3, conteo_otros=$4,
                  total_teorico_efectivo=$5, total_teorico_tarjeta=$6, total_teorico_transferencia=$7, total_teorico_otros=$8,
                  diferencia_total=$9, observacion=$10, actualizado_en=now()
            WHERE id_caja_sesion=$11
            RETURNING id_caja_sesion, diferencia_total";
  $ru = pg_query_params($conn,$sqlU,[$cEf,$cTa,$cTr,$cOt,$tEf,$tTa,$tTr,$tOt,$diff,$obs,$id]);
  if(!$ru) throw new Exception(pg_last_error($conn));
  $out = pg_fetch_assoc($ru);

  pg_query($conn,'COMMIT');
  j(true,['cierre'=>$out],200);
}catch(Exception $e){
  pg_query($conn,'ROLLBACK');
  j(false,['error'=>$e->getMessage()],400);
}
