<?php
// oc_anular.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$c) { http_response_code(500); echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$id_oc  = isset($_POST['id_oc']) ? (int)$_POST['id_oc'] : 0;
$motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';
$devolver = isset($_POST['devolver_aprobaciones']) ? (int)$_POST['devolver_aprobaciones'] : 0;

if ($id_oc <= 0) { http_response_code(400); echo json_encode(["ok"=>false,"error"=>"id_oc requerido"]); exit; }

pg_query($c, "BEGIN");

$rc = pg_query_params($c, "SELECT id_oc, numero_pedido, id_proveedor, estado, COALESCE(observacion,'') FROM orden_compra_cab WHERE id_oc=$1 FOR UPDATE", [$id_oc]);
if (!$rc || pg_num_rows($rc)==0) { pg_query($c,"ROLLBACK"); http_response_code(404); echo json_encode(["ok"=>false,"error"=>"OC no encontrada"]); exit; }
$oc = pg_fetch_row($rc);
$numero_pedido = (int)$oc[1];

if ($oc[3] === 'Anulada') {
  pg_query($c,"ROLLBACK");
  echo json_encode(["ok"=>true,"msg"=>"OC ya estaba anulada"]); exit;
}

// (opcional) devolver aprobaciones usadas por esta OC
$presupuestosTocados = [];
if ($devolver === 1) {
  $rd = pg_query_params($c, "
    SELECT d.id_presupuesto_detalle, d.cantidad,
           pd.id_presupuesto
    FROM orden_compra_det d
    LEFT JOIN presupuesto_detalle pd ON pd.id_presupuesto_detalle = d.id_presupuesto_detalle
    WHERE d.id_oc = $1
      AND d.id_presupuesto_detalle IS NOT NULL
  ", [$id_oc]);

  if ($rd) {
    while ($r = pg_fetch_assoc($rd)) {
      $id_det = (int)$r['id_presupuesto_detalle'];
      $cant   = (int)$r['cantidad'];
      $idp    = (int)$r['id_presupuesto'];

      if ($id_det > 0 && $cant > 0) {
        // devolver a cantidad_aprobada
        $u = pg_query_params($c, "
          UPDATE presupuesto_detalle
          SET cantidad_aprobada = COALESCE(cantidad_aprobada,0) + $2,
              estado_detalle = 'Aprobado'
          WHERE id_presupuesto_detalle = $1
        ", [$id_det, $cant]);
        if (!$u) { pg_query($c,"ROLLBACK"); http_response_code(500); echo json_encode(["ok"=>false,"error"=>"No se pudo devolver aprobaciones"]); exit; }
      }
      if ($idp > 0) $presupuestosTocados[] = $idp;
    }
  }
}

// marcar OC como Anulada (guardar motivo en observación)
$obs = $motivo ? ($oc[4]." | Anulación: ".$motivo) : $oc[4];
$u2 = pg_query_params($c, "UPDATE orden_compra_cab SET estado='Anulada', observacion=$2 WHERE id_oc=$1", [$id_oc, $obs]);
if (!$u2) { pg_query($c,"ROLLBACK"); http_response_code(500); echo json_encode(["ok"=>false,"error"=>"No se pudo anular OC"]); exit; }

// Recalcular estados de presupuestos tocados (si hubo devolución)
$presupuestosTocados = array_values(array_unique($presupuestosTocados));
foreach ($presupuestosTocados as $idp) {
  // remanente (aprobado que aún no se llevó a OC)
  $r1 = pg_query_params($c, "
    SELECT COALESCE(SUM(cantidad_aprobada),0)
    FROM presupuesto_detalle
    WHERE id_presupuesto=$1 AND estado_detalle='Aprobado'
  ", [$idp]);
  $remanente = $r1 ? (float)pg_fetch_result($r1,0,0) : 0;

  // comprometida (usada en OCs no anuladas)
  $r2 = pg_query_params($c, "
    SELECT COALESCE(SUM(ocd.cantidad),0)
    FROM presupuesto_detalle pd
    JOIN orden_compra_det ocd ON ocd.id_presupuesto_detalle=pd.id_presupuesto_detalle
    JOIN orden_compra_cab occ ON occ.id_oc=ocd.id_oc AND occ.estado<>'Anulada'
    WHERE pd.id_presupuesto=$1
  ", [$idp]);
  $comprom = $r2 ? (float)pg_fetch_result($r2,0,0) : 0;

  $nuevo = 'Registrado';
  if ($remanente==0 && $comprom>0) $nuevo='Totalmente Ordenado';
  else if ($remanente>0 && $comprom>0) $nuevo='Parcialmente Ordenado';
  else if ($remanente>0 && $comprom==0) $nuevo='Con Aprobaciones';

  pg_query_params($c, "UPDATE presupuestos SET estado=$2 WHERE id_presupuesto=$1", [$idp, $nuevo]);
}

// Recalcular estado de PEDIDO (usa solo OCs no anuladas)
$r = pg_query_params($c, "
  WITH x AS (
    SELECT d.id_producto,
           d.cantidad AS pedida,
           COALESCE((
             SELECT SUM(ocd.cantidad)
             FROM orden_compra_det ocd
             JOIN orden_compra_cab occ ON occ.id_oc=ocd.id_oc
             WHERE occ.numero_pedido=d.numero_pedido
               AND ocd.id_producto=d.id_producto
               AND occ.estado<>'Anulada'
           ),0) AS ordenada
    FROM detalle_pedido_interno d
    WHERE d.numero_pedido=$1
  )
  SELECT
    SUM(CASE WHEN ordenada>=pedida THEN 1 ELSE 0 END) AS completas,
    COUNT(*) AS total,
    SUM(CASE WHEN ordenada>0 AND ordenada<pedida THEN 1 ELSE 0 END) AS parciales
  FROM x
", [$numero_pedido]);

$completas=0; $total=0; $parciales=0;
if ($r && pg_num_rows($r)>0) {
  $row = pg_fetch_row($r);
  if ($row){
    $completas = (int)$row[0];
    $total     = (int)$row[1];
    $parciales = (int)$row[2];
  }
}
$estadoPedido = 'Abierto';
if ($total>0 && $completas === $total) $estadoPedido = 'Totalmente Ordenado';
else if ($parciales > 0 || $completas>0) $estadoPedido = 'Parcialmente Ordenado';

pg_query_params($c, "
  UPDATE cabecera_pedido_interno
  SET estado = CASE WHEN estado='Anulado' THEN estado ELSE $2 END
  WHERE numero_pedido=$1
", [$numero_pedido, $estadoPedido]);

pg_query($c,"COMMIT");
echo json_encode(["ok"=>true,"msg"=>"OC anulada","id_oc"=>$id_oc], JSON_UNESCAPED_UNICODE);
