<?php
// pedido_anular.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ http_response_code(500); echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$numero_pedido = isset($_POST['numero_pedido']) ? (int)$_POST['numero_pedido'] : 0;
$motivo        = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';
$anular_pres   = isset($_POST['anular_presupuestos']) ? (int)$_POST['anular_presupuestos'] : 1; // por defecto SÍ

if ($numero_pedido<=0) { http_response_code(400); echo json_encode(["ok"=>false,"error"=>"numero_pedido requerido"]); exit; }

pg_query($c,"BEGIN");

/* Lock fila del pedido */
$r = pg_query_params($c,"SELECT estado FROM cabecera_pedido_interno WHERE numero_pedido=$1 FOR UPDATE", [$numero_pedido]);
if(!$r || pg_num_rows($r)==0){ pg_query($c,"ROLLBACK"); http_response_code(404); echo json_encode(["ok"=>false,"error"=>"Pedido no encontrado"]); exit; }
$estado = pg_fetch_result($r,0,0);
if ($estado === 'Anulado'){
  pg_query($c,"ROLLBACK");
  echo json_encode(["ok"=>true,"msg"=>"El pedido ya estaba anulado"]); exit;
}

/* Chequear OCs activas */
$roc = pg_query_params($c,"
  SELECT COUNT(*)::int
  FROM orden_compra_cab
  WHERE numero_pedido=$1 AND estado<>'Anulada'
", [$numero_pedido]);
$ocs_activas = $roc ? (int)pg_fetch_result($roc,0,0) : 0;
if ($ocs_activas > 0){
  pg_query($c,"ROLLBACK");
  http_response_code(409);
  echo json_encode(["ok"=>false,"error"=>"No se puede anular: existen Órdenes de Compra activas para este pedido. Anulá esas OCs primero."]);
  exit;
}

/* (Opcional) anular en cascada presupuestos y sus líneas (no hay OCs activas, es seguro) */
if ($anular_pres === 1){
  // líneas
  $u1 = pg_query_params($c,"
    UPDATE presupuesto_detalle pd
    SET estado_detalle='Anulado', cantidad_aprobada=0
    WHERE pd.id_presupuesto IN (SELECT pr.id_presupuesto FROM presupuestos pr WHERE pr.numero_pedido=$1)
  ",[$numero_pedido]);
  if(!$u1){ pg_query($c,"ROLLBACK"); http_response_code(500); echo json_encode(["ok"=>false,"error"=>"No se pudieron anular líneas de presupuestos"]); exit; }

  // cabeceras
  $u2 = pg_query_params($c,"
    UPDATE presupuestos
    SET estado='Anulado'
    WHERE numero_pedido=$1 AND estado NOT ILIKE 'Anulado%'
  ",[$numero_pedido]);
  if(!$u2){ pg_query($c,"ROLLBACK"); http_response_code(500); echo json_encode(["ok"=>false,"error"=>"No se pudieron anular presupuestos"]); exit; }
}

/* Marcar pedido como Anulado */
$u = pg_query_params($c,"
  UPDATE cabecera_pedido_interno
  SET estado='Anulado', motivo_anulacion=$2, fecha_cierre=CURRENT_DATE
  WHERE numero_pedido=$1
", [$numero_pedido, $motivo]);
if(!$u){ pg_query($c,"ROLLBACK"); http_response_code(500); echo json_encode(["ok"=>false,"error"=>"No se pudo anular el pedido"]); exit; }

pg_query($c,"COMMIT");
echo json_encode(["ok"=>true,"msg"=>"Pedido anulado correctamente","numero_pedido"=>$numero_pedido], JSON_UNESCAPED_UNICODE);
