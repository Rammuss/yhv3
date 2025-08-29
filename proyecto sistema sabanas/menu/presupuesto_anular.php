<?php
// presupuesto_anular.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ http_response_code(500); echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$idp = isset($_POST['id_presupuesto']) ? (int)$_POST['id_presupuesto'] : 0;
$permitir = isset($_POST['permitir_con_oc']) ? (int)$_POST['permitir_con_oc'] : 0;
$motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';

if ($idp <= 0) { http_response_code(400); echo json_encode(["ok"=>false,"error"=>"id_presupuesto requerido"]); exit; }

pg_query($c, "BEGIN");

// Lock & basic existence
$rp = pg_query_params($c, "SELECT id_presupuesto, estado FROM presupuestos WHERE id_presupuesto=$1 FOR UPDATE", [$idp]);
if (!$rp || pg_num_rows($rp)==0) {
  pg_query($c,"ROLLBACK"); http_response_code(404); echo json_encode(["ok"=>false,"error"=>"Presupuesto no encontrado"]); exit;
}
$pres = pg_fetch_assoc($rp);
if ($pres['estado'] === 'Anulado' || $pres['estado'] === 'Anulado Parcial') {
  pg_query($c,"ROLLBACK");
  echo json_encode(["ok"=>true,"msg"=>"El presupuesto ya estaba anulado"]); exit;
}

// ¿Tiene líneas comprometidas en OCs activas?
$rComp = pg_query_params($c, "
  SELECT COUNT(*)::int
  FROM presupuesto_detalle pd
  JOIN orden_compra_det ocd ON ocd.id_presupuesto_detalle = pd.id_presupuesto_detalle
  JOIN orden_compra_cab occ ON occ.id_oc = ocd.id_oc
  WHERE pd.id_presupuesto = $1
    AND occ.estado <> 'Anulada'
", [$idp]);
$comprometidas = $rComp ? (int)pg_fetch_result($rComp,0,0) : 0;

if ($comprometidas > 0 && !$permitir) {
  pg_query($c,"ROLLBACK");
  http_response_code(409);
  echo json_encode([
    "ok"=>false,
    "error"=>"El presupuesto tiene líneas comprometidas en OCs activas. Anulá esas OCs primero o envía permitir_con_oc=1 para marcar Anulado Parcial."
  ]);
  exit;
}

// Anular SOLO líneas no comprometidas (dejan de estar disponibles)
$uDet = pg_query_params($c, "
  UPDATE presupuesto_detalle pd
  SET estado_detalle = 'Anulado',
      cantidad_aprobada = 0
  WHERE pd.id_presupuesto = $1
    AND NOT EXISTS (
      SELECT 1
      FROM orden_compra_det ocd
      JOIN orden_compra_cab occ ON occ.id_oc = ocd.id_oc
      WHERE ocd.id_presupuesto_detalle = pd.id_presupuesto_detalle
        AND occ.estado <> 'Anulada'
    )
", [$idp]);
if (!$uDet) { pg_query($c,"ROLLBACK"); http_response_code(500); echo json_encode(["ok"=>false,"error"=>"No se pudo actualizar líneas"]); exit; }

// Si hay comprometidas y permitimos, queda "Anulado Parcial"; si no hay, "Anulado"
$nuevoEstado = ($comprometidas > 0 && $permitir) ? 'Anulado Parcial' : 'Anulado';

// Marcar cabecera
$uCab = pg_query_params($c, "
  UPDATE presupuestos
  SET estado = $2
  WHERE id_presupuesto = $1
", [$idp, $nuevoEstado]);
if (!$uCab) { pg_query($c,"ROLLBACK"); http_response_code(500); echo json_encode(["ok"=>false,"error"=>"No se pudo actualizar cabecera"]); exit; }

// (Opcional) Recalcular estados agregados por prolijidad:

// 1) Si querés recalcular el estado del pedido asociado, podés hacerlo si tu tabla presupuestos tiene numero_pedido.
//    Si tu esquema ya lo incluye (como usan en guardar_presupuesto.php), habilitá este bloque.
//    Nota: si tu tabla presupuestos aún NO tiene numero_pedido, comentá este bloque o agrégalo al esquema.
$hasPedidoCol = pg_query($c, "SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='presupuestos' AND column_name='numero_pedido'");
if ($hasPedidoCol && pg_num_rows($hasPedidoCol)>0) {
  $rnp = pg_query_params($c, "SELECT numero_pedido FROM presupuestos WHERE id_presupuesto=$1", [$idp]);
  if ($rnp && pg_num_rows($rnp)>0) {
    $numero_pedido = (int)pg_fetch_result($rnp,0,0);

    // Recalcular estado del pedido: solo considera OCs no anuladas
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
  }
}

pg_query($c,"COMMIT");

echo json_encode([
  "ok"=>true,
  "msg"=> ($nuevoEstado === 'Anulado' ? "Presupuesto anulado" : "Presupuesto anulado parcialmente (hay líneas comprometidas en OCs activas)"),
  "estado"=>$nuevoEstado
], JSON_UNESCAPED_UNICODE);
