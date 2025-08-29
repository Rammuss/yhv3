<?php
// factura_anular.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ http_response_code(500); echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$id = (int)($_POST['id_factura'] ?? 0);
$motivo = $_POST['motivo'] ?? '';

if ($id<=0){ http_response_code(400); echo json_encode(["ok"=>false,"error"=>"id_factura requerido"]); exit; }

pg_query($c,"BEGIN");

// Lock cabecera
$r = pg_query_params($c,"SELECT estado FROM factura_compra_cab WHERE id_factura=$1 FOR UPDATE",[$id]);
if(!$r || pg_num_rows($r)==0){ pg_query($c,"ROLLBACK"); http_response_code(404); echo json_encode(["ok"=>false,"error"=>"Factura no encontrada"]); exit; }

if (pg_fetch_result($r,0,0) === 'Anulada'){
  pg_query($c,"ROLLBACK"); echo json_encode(["ok"=>true,"msg"=>"Factura ya estaba anulada"]); exit;
}

// Revertir stock: cada línea -> salida
$rd = pg_query_params($c,"
  SELECT id_producto, cantidad
  FROM factura_compra_det WHERE id_factura=$1
",[$id]);
while($d = pg_fetch_assoc($rd)){
  $ok = pg_query_params($c,"
    INSERT INTO movimiento_stock (id_producto, tipo_movimiento, cantidad)
    VALUES ($1,'salida',$2)
  ", [(int)$d['id_producto'], (int)$d['cantidad']]);
  if(!$ok){ pg_query($c,"ROLLBACK"); http_response_code(500); echo json_encode(["ok"=>false,"error"=>"No se pudo revertir stock"]); exit; }
}

// Marcar factura como anulada (+motivo en observación)
pg_query_params($c,"
  UPDATE factura_compra_cab
  SET estado='Anulada',
      observacion = COALESCE(observacion,'') || CASE WHEN $2<>'' THEN ' | Anulado: '||$2 ELSE '' END
  WHERE id_factura=$1
", [$id, $motivo]);

// Recalcular estados de OCs y Pedidos afectados por esta factura
$ro = pg_query_params($c,"
  SELECT DISTINCT occ.id_oc, occ.numero_pedido
  FROM orden_compra_cab occ
  JOIN orden_compra_det ocd ON ocd.id_oc=occ.id_oc
  JOIN factura_compra_det fcd ON fcd.id_oc_det=ocd.id_oc_det
  WHERE fcd.id_factura=$1
",[$id]);

$map = [];
while($row = pg_fetch_assoc($ro)){
  $map[(int)$row['id_oc']] = (int)$row['numero_pedido'];
}

foreach($map as $id_oc=>$num_pedido){
  // Recalcular estado de OC
  $r1 = pg_query_params($c,"
    WITH x AS (
      SELECT ocd.id_oc_det, ocd.cantidad AS oc_qty,
             COALESCE((
               SELECT SUM(fcd.cantidad)
               FROM factura_compra_det fcd
               JOIN factura_compra_cab fcc ON fcc.id_factura=fcd.id_factura AND fcc.estado<>'Anulada'
               WHERE fcd.id_oc_det=ocd.id_oc_det
             ),0) AS fact_qty
      FROM orden_compra_det ocd
      WHERE ocd.id_oc=$1
    )
    SELECT
      SUM(CASE WHEN fact_qty>=oc_qty THEN 1 ELSE 0 END) AS completas,
      COUNT(*) AS total,
      SUM(CASE WHEN fact_qty>0 AND fact_qty<oc_qty THEN 1 ELSE 0 END) AS parciales
    FROM x
  ", [$id_oc]);
  list($c1,$t1,$p1) = array_map('intval', pg_fetch_row($r1));
  $estadoOC = 'Emitida';
  if ($t1>0 && $c1 === $t1) $estadoOC = 'Totalmente Recibida';
  else if ($p1>0 || $c1>0)  $estadoOC = 'Parcialmente Recibida';

  pg_query_params($c,"UPDATE orden_compra_cab SET estado=$2 WHERE id_oc=$1 AND estado<>'Anulada'", [$id_oc,$estadoOC]);

  // Recalcular estado de Pedido
  $r2 = pg_query_params($c,"
    WITH x AS (
      SELECT d.id_producto,
             d.cantidad AS pedida,
             COALESCE((
               SELECT SUM(fcd.cantidad)
               FROM orden_compra_det ocd
               JOIN orden_compra_cab occ ON occ.id_oc=ocd.id_oc
               JOIN factura_compra_det fcd ON fcd.id_oc_det=ocd.id_oc_det
               JOIN factura_compra_cab fcc ON fcc.id_factura=fcd.id_factura AND fcc.estado<>'Anulada'
               WHERE occ.numero_pedido=d.numero_pedido
                 AND ocd.id_producto=d.id_producto
             ),0) AS recibida
      FROM detalle_pedido_interno d
      WHERE d.numero_pedido=$1
    )
    SELECT
      SUM(CASE WHEN recibida>=pedida THEN 1 ELSE 0 END) AS completas,
      COUNT(*) AS total,
      SUM(CASE WHEN recibida>0 AND recibida<pedida THEN 1 ELSE 0 END) AS parciales
    FROM x
  ", [$num_pedido]);
  list($c2,$t2,$p2) = array_map('intval', pg_fetch_row($r2));
  $estadoPed = 'Abierto';
  if ($t2>0 && $c2 === $t2) $estadoPed = 'Totalmente Entregado';
  else if ($p2>0 || $c2>0)  $estadoPed = 'Parcialmente Entregado';

  pg_query_params($c,"
    UPDATE cabecera_pedido_interno
    SET estado = CASE WHEN estado='Anulado' THEN estado ELSE $2 END
    WHERE numero_pedido=$1
  ", [$num_pedido,$estadoPed]);
}

pg_query($c,"COMMIT");
echo json_encode(["ok"=>true,"msg"=>"Factura anulada y stock revertido"]);
