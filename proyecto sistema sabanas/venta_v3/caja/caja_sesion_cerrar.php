<?php
// cerrar_caja.php
// Acepta: GET/POST id (id_caja_sesion)
// Body JSON/POST: conteo_efectivo, conteo_tarjeta, conteo_transferencia, conteo_otros, observacion
session_start();
if (empty($_SESSION['id_usuario'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'No autenticado']); exit; }
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

function j($ok,$data=[],$code=200){ http_response_code($code); echo json_encode(['ok'=>$ok]+$data); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$cEf = isset($in['conteo_efectivo'])      ? max(0,(float)$in['conteo_efectivo'])      : 0.0;
$cTa = isset($in['conteo_tarjeta'])       ? max(0,(float)$in['conteo_tarjeta'])       : 0.0;
$cTr = isset($in['conteo_transferencia']) ? max(0,(float)$in['conteo_transferencia']) : 0.0;
$cOt = isset($in['conteo_otros'])         ? max(0,(float)$in['conteo_otros'])         : 0.0;
$obs = isset($in['observacion']) ? trim($in['observacion']) : null;

if ($id<=0) j(false,['error'=>'id de sesión inválido'],400);

pg_query($conn,'BEGIN');
try{
  // 1) Lock + validar estado abierto
  $r = pg_query_params(
    $conn,
    "SELECT id_caja_sesion, id_usuario, estado
       FROM public.caja_sesion
      WHERE id_caja_sesion=$1
      FOR UPDATE",
    [$id]
  );
  if(!$r || pg_num_rows($r)==0) throw new Exception("Sesión inexistente.");
  $rowSes = pg_fetch_assoc($r);
  if ($rowSes['estado'] !== 'Abierta') throw new Exception("La sesión ya está cerrada.");

  // (Opcional) si querés forzar que solo el dueño cierre:
  // if ((int)$rowSes['id_usuario'] !== (int)$_SESSION['id_usuario']) throw new Exception("No sos el dueño de esta sesión.");

  // 2) Totales teóricos (desde vista) — si no existe la vista, calculalo con SUM() en movimiento_caja
  $rt = pg_query_params(
    $conn,
    "SELECT COALESCE(efectivo,0), COALESCE(tarjeta,0), COALESCE(transferencia,0), COALESCE(otros,0)
       FROM public.v_caja_saldos_teoricos
      WHERE id_caja_sesion=$1",
    [$id]
  );
  if(!$rt) throw new Exception(pg_last_error($conn));
  $tEf=0; $tTa=0; $tTr=0; $tOt=0;
  if (pg_num_rows($rt)>0){
    [$tEf,$tTa,$tTr,$tOt] = array_map('floatval', pg_fetch_row($rt));
  }

  // 3) Diferencia global
  $diff = ($cEf+$cTa+$cTr+$cOt) - ($tEf+$tTa+$tTr+$tOt);

  // 4) Cerrar sesión
  $ru = pg_query_params(
    $conn,
    "UPDATE public.caja_sesion
        SET estado='Cerrada',
            fecha_cierre=NOW(),
            conteo_efectivo=$1,
            conteo_tarjeta=$2,
            conteo_transferencia=$3,
            conteo_otros=$4,
            total_teorico_efectivo=$5,
            total_teorico_tarjeta=$6,
            total_teorico_transferencia=$7,
            total_teorico_otros=$8,
            diferencia_total=$9,
            observacion=$10,
            actualizado_en=NOW()
      WHERE id_caja_sesion=$11
      RETURNING id_caja_sesion, estado, fecha_cierre, diferencia_total",
    [$cEf,$cTa,$cTr,$cOt,$tEf,$tTa,$tTr,$tOt,$diff,($obs?:null),$id]
  );
  if(!$ru) throw new Exception(pg_last_error($conn));
  $out = pg_fetch_assoc($ru);

  pg_query($conn,'COMMIT');

  // 5) Limpiar cache de sesión si la que cerramos es la activa
  if (!empty($_SESSION['id_caja_sesion']) && (int)$_SESSION['id_caja_sesion'] === (int)$id) {
    unset($_SESSION['id_caja_sesion']);
    if (function_exists('session_write_close')) session_write_close();
  }

  j(true,['cierre'=>$out],200);

}catch(Exception $e){
  pg_query($conn,'ROLLBACK');
  j(false,['error'=>$e->getMessage()],400);
}
