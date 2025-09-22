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
  // 1) Lock + validar sesión abierta
  $r = pg_query_params(
    $conn,
    "SELECT id_caja_sesion, id_caja, id_usuario, estado
       FROM public.caja_sesion
      WHERE id_caja_sesion=$1
      FOR UPDATE",
    [$id]
  );
  if(!$r || pg_num_rows($r)==0) { pg_query($conn,'ROLLBACK'); j(false,['error'=>'Sesión inexistente.'],404); }
  $rowSes = pg_fetch_assoc($r);
  if ($rowSes['estado'] !== 'Abierta') { pg_query($conn,'ROLLBACK'); j(false,['error'=>'La sesión ya está cerrada.'],409); }

  // (Opcional) exigir dueño o rol admin
  // if ((int)$rowSes['id_usuario'] !== (int)$_SESSION['id_usuario'] && empty($_SESSION['es_admin'])) {
  //   pg_query($conn,'ROLLBACK'); j(false,['error'=>'No sos el dueño de esta sesión.'],403);
  // }

  // 2) Totales teóricos (vista o fallback)
  $tEf=0; $tTa=0; $tTr=0; $tOt=0;

  $rt = pg_query_params(
    $conn,
    "SELECT COALESCE(efectivo,0), COALESCE(tarjeta,0), COALESCE(transferencia,0), COALESCE(otros,0)
       FROM public.v_caja_saldos_teoricos
      WHERE id_caja_sesion=$1",
    [$id]
  );

  if ($rt && pg_num_rows($rt)>0) {
    [$tEf,$tTa,$tTr,$tOt] = array_map('floatval', pg_fetch_row($rt));
  } else {
    // Fallback por si la vista no existe o no trae nada
    $rf = pg_query_params(
      $conn,
      "SELECT
         COALESCE(SUM(CASE WHEN medio='EFECTIVO'      THEN importe ELSE 0 END),0) AS efectivo,
         COALESCE(SUM(CASE WHEN medio='TARJETA'       THEN importe ELSE 0 END),0) AS tarjeta,
         COALESCE(SUM(CASE WHEN medio='TRANSFERENCIA' THEN importe ELSE 0 END),0) AS transferencia,
         COALESCE(SUM(CASE WHEN medio NOT IN ('EFECTIVO','TARJETA','TRANSFERENCIA') THEN importe ELSE 0 END),0) AS otros
       FROM public.movimiento_caja
       WHERE id_caja_sesion=$1",
      [$id]
    );
    if ($rf && pg_num_rows($rf)>0) {
      [$tEf,$tTa,$tTr,$tOt] = array_map('floatval', pg_fetch_row($rf));
    }
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
      RETURNING id_caja_sesion, id_caja, estado, fecha_cierre, diferencia_total",
    [$cEf,$cTa,$cTr,$cOt,$tEf,$tTa,$tTr,$tOt,$diff,($obs?:null),$id]
  );
  if(!$ru) throw new Exception(pg_last_error($conn));
  $out = pg_fetch_assoc($ru);

  pg_query($conn,'COMMIT');

  // 5) Limpiar sesión si es la activa
  if (!empty($_SESSION['id_caja_sesion']) && (int)$_SESSION['id_caja_sesion'] === (int)$id) {
    unset($_SESSION['id_caja_sesion']);
    // También limpiar id_caja y cualquier otro flag relacionado a caja
    if (!empty($_SESSION['id_caja']) && (int)$_SESSION['id_caja'] === (int)$out['id_caja']) {
      unset($_SESSION['id_caja']);
    }
    if (isset($_SESSION['caja_estado'])) unset($_SESSION['caja_estado']);
    if (function_exists('session_write_close')) session_write_close();
  }

  j(true,['cierre'=>$out],200);

}catch(Exception $e){
  pg_query($conn,'ROLLBACK');
  j(false,['error'=>$e->getMessage()],400);
}
