<?php
// factura_anular.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ http_response_code(500); echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$id = (int)($_POST['id_factura'] ?? 0);
$motivo = trim($_POST['motivo'] ?? '');

if ($id<=0){
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"id_factura requerido"]);
  exit;
}

pg_query($c,"BEGIN");

// 1) Lock cabecera
$r = pg_query_params($c,"SELECT estado FROM factura_compra_cab WHERE id_factura=$1 FOR UPDATE",[$id]);
if(!$r || pg_num_rows($r)==0){
  pg_query($c,"ROLLBACK");
  http_response_code(404);
  echo json_encode(["ok"=>false,"error"=>"Factura no encontrada"]);
  exit;
}

$estadoFac = pg_fetch_result($r,0,0);
if ($estadoFac === 'Anulada'){
  pg_query($c,"ROLLBACK");
  echo json_encode(["ok"=>true,"msg"=>"La factura ya estaba anulada"]);
  exit;
}

// 2) Chequear CxP y pagos aplicados
//    Regla: si existe CxP y alguna cuota tiene saldo_cuota < monto_cuota => ya hubo pago -> bloquear
$qc = pg_query_params($c,"
  SELECT cxp.id_cxp,
         COALESCE(SUM(CASE WHEN cd.saldo_cuota < cd.monto_cuota THEN 1 ELSE 0 END),0) AS cuotas_pagadas
    FROM cuenta_pagar cxp
    LEFT JOIN cuenta_det_x_pagar cd ON cd.id_cxp = cxp.id_cxp
   WHERE cxp.id_factura = $1
   GROUP BY cxp.id_cxp
",[$id]);

$id_cxp = null;
if ($qc && pg_num_rows($qc) > 0){
  $CX = pg_fetch_assoc($qc);
  $id_cxp = (int)$CX['id_cxp'];
  $cuotas_pagadas = (int)$CX['cuotas_pagadas'];
  if ($cuotas_pagadas > 0){
    pg_query($c,"ROLLBACK");
    echo json_encode(["ok"=>false,"error"=>"No se puede anular: existen pagos aplicados a esta cuenta por pagar"]);
    exit;
  }
}

// 3) Revertir stock: cada línea -> salida
$rd = pg_query_params($c,"
  SELECT id_producto, cantidad
    FROM factura_compra_det
   WHERE id_factura=$1
",[$id]);
if ($rd){
  while($d = pg_fetch_assoc($rd)){
    $id_prod = (int)$d['id_producto'];
    $cant    = (int)$d['cantidad'];
    if ($id_prod>0 && $cant>0){
      $ok = pg_query_params($c,"
        INSERT INTO movimiento_stock (id_producto, tipo_movimiento, cantidad)
        VALUES ($1,'salida',$2)
      ", [$id_prod, $cant]);
      if(!$ok){
        pg_query($c,"ROLLBACK");
        http_response_code(500);
        echo json_encode(["ok"=>false,"error"=>"No se pudo revertir stock"]);
        exit;
      }
    }
  }
}

// 4) Libro de Compras -> Anulada (mantener registro)
$uLibro = pg_query_params($c,"
  UPDATE libro_compras
     SET estado='Anulada'
   WHERE id_factura=$1
",[$id]);
if(!$uLibro){
  pg_query($c,"ROLLBACK");
  echo json_encode(["ok"=>false,"error"=>"No se pudo actualizar libro de compras"]);
  exit;
}

// 5) Anular CxP (si existe) y sus cuotas, saldos a 0
if ($id_cxp){
  $uCxp = pg_query_params($c,"
    UPDATE cuenta_pagar
       SET estado='Anulada',
           saldo_actual=0
     WHERE id_cxp=$1
  ",[$id_cxp]);
  if(!$uCxp){
    pg_query($c,"ROLLBACK");
    echo json_encode(["ok"=>false,"error"=>"No se pudo anular la cuenta por pagar"]);
    exit;
  }

  $uCuotas = pg_query_params($c,"
    UPDATE cuenta_det_x_pagar
       SET estado='Anulada',
           saldo_cuota=0
     WHERE id_cxp=$1
  ",[$id_cxp]);
  if(!$uCuotas){
    pg_query($c,"ROLLBACK");
    echo json_encode(["ok"=>false,"error"=>"No se pudo anular las cuotas de la CxP"]);
    exit;
  }
}

// 6) Marcar factura como Anulada (+ motivo en observación)
$obsExtra = " | Anulado: ".date('Y-m-d H:i').($motivo!=='' ? " - ".$motivo : "");
$uFac = pg_query_params($c,"
  UPDATE factura_compra_cab
     SET estado='Anulada',
         observacion = COALESCE(observacion,'') || $2
   WHERE id_factura=$1
", [$id, $obsExtra]);
if(!$uFac){
  pg_query($c,"ROLLBACK");
  echo json_encode(["ok"=>false,"error"=>"No se pudo anular la factura"]);
  exit;
}

// 7) Recalcular estados de OCs y Pedidos afectados por esta factura
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
  // Recalcular estado de OC (excluye facturas anuladas)
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

  pg_query_params($c,"
    UPDATE orden_compra_cab
       SET estado=$2
     WHERE id_oc=$1
       AND estado<>'Anulada'
  ", [$id_oc,$estadoOC]);

  // Recalcular estado de Pedido Interno (excluye facturas anuladas)
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
echo json_encode(["ok"=>true,"msg"=>"Factura anulada correctamente"]);
