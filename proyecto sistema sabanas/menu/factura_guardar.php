<?php
// factura_guardar.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ http_response_code(500); echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$id_prov  = (int)($_POST['id_proveedor'] ?? 0);
$fecha    = $_POST['fecha_emision'] ?? date('Y-m-d');
$nro      = trim($_POST['numero_documento'] ?? '');
$obs      = $_POST['observacion'] ?? null;

$ids   = $_POST['id_oc_det'] ?? [];
$cants = $_POST['cantidad'] ?? [];
$precs = $_POST['precio_unitario'] ?? [];
$ivas  = $_POST['tipo_iva'] ?? []; // opcional

if ($id_prov<=0 || $nro===''){ http_response_code(400); echo json_encode(["ok"=>false,"error"=>"Parámetros requeridos"]); exit; }
if (!is_array($ids) || !count($ids)){ http_response_code(400); echo json_encode(["ok"=>false,"error"=>"Sin líneas"]); exit; }
if (count($ids)!=count($cants) || count($ids)!=count($precs)){ http_response_code(400); echo json_encode(["ok"=>false,"error"=>"Datos de líneas incompletos"]); exit; }

pg_query($c,"BEGIN");

$insCab = pg_query_params($c,"
  INSERT INTO factura_compra_cab (id_proveedor, fecha_emision, numero_documento, observacion, estado)
  VALUES ($1,$2,$3,$4,'Registrada') RETURNING id_factura
", [$id_prov, $fecha, $nro, $obs]);
if(!$insCab){ pg_query($c,"ROLLBACK"); http_response_code(500); echo json_encode(["ok"=>false,"error"=>"No se pudo crear factura"]); exit; }
$id_factura = (int)pg_fetch_result($insCab,0,0);

$total = 0;
$oc_tocadas = []; // id_oc => numero_pedido

for($i=0; $i<count($ids); $i++){
  $id_oc_det = (int)$ids[$i];
  $cant      = (int)$cants[$i];
  $prec      = (float)$precs[$i];
  $tiva      = is_array($ivas) ? ($ivas[$i] ?? null) : null;

  if ($id_oc_det<=0 || $cant<=0 || $prec<0){
    pg_query($c,"ROLLBACK"); http_response_code(400); echo json_encode(["ok"=>false,"error"=>"Línea inválida"]); exit;
  }

  // Traer línea de OC y validar proveedor
  $q = pg_query_params($c,"
    SELECT ocd.id_oc_det, ocd.id_oc, ocd.id_producto, ocd.cantidad AS oc_cantidad,
           occ.id_proveedor, occ.numero_pedido
    FROM orden_compra_det ocd
    JOIN orden_compra_cab occ ON occ.id_oc=ocd.id_oc
    WHERE ocd.id_oc_det=$1
    LIMIT 1
  ", [$id_oc_det]);
  if(!$q || pg_num_rows($q)==0){ pg_query($c,"ROLLBACK"); http_response_code(400); echo json_encode(["ok"=>false,"error"=>"OC det no encontrada"]); exit; }
  $L = pg_fetch_assoc($q);

  if ((int)$L['id_proveedor'] !== $id_prov){
    pg_query($c,"ROLLBACK"); http_response_code(400); echo json_encode(["ok"=>false,"error"=>"La línea no corresponde al proveedor indicado"]); exit;
  }

  // Calcular pendiente
  $rpend = pg_query_params($c,"
    SELECT (ocd.cantidad - COALESCE((
      SELECT SUM(fcd.cantidad)
      FROM factura_compra_det fcd
      JOIN factura_compra_cab fcc ON fcc.id_factura=fcd.id_factura AND fcc.estado<>'Anulada'
      WHERE fcd.id_oc_det=ocd.id_oc_det
    ),0))::int AS pendiente
    FROM orden_compra_det ocd
    WHERE ocd.id_oc_det=$1
  ", [$id_oc_det]);
  $pend = (int)pg_fetch_result($rpend,0,0);

  if ($cant > $pend){
    pg_query($c,"ROLLBACK"); http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"Cantidad supera pendiente por recibir (pendiente=$pend)"]); exit;
  }

  // Insert detalle
  $insDet = pg_query_params($c,"
    INSERT INTO factura_compra_det (id_factura, id_oc_det, id_producto, cantidad, precio_unitario, tipo_iva)
    VALUES ($1,$2,$3,$4,$5,$6)
  ", [$id_factura, $id_oc_det, (int)$L['id_producto'], $cant, $prec, $tiva]);
  if(!$insDet){ pg_query($c,"ROLLBACK"); http_response_code(500); echo json_encode(["ok"=>false,"error"=>"No se pudo insertar detalle"]); exit; }

  // Movimiento de stock (entrada)
  $mov = pg_query_params($c,"
    INSERT INTO movimiento_stock (id_producto, tipo_movimiento, cantidad)
    VALUES ($1,'entrada',$2)
  ", [(int)$L['id_producto'], $cant]);
  if(!$mov){ pg_query($c,"ROLLBACK"); http_response_code(500); echo json_encode(["ok"=>false,"error"=>"No se pudo registrar movimiento de stock"]); exit; }

  $total += $cant * $prec;
  $oc_tocadas[(int)$L['id_oc']] = (int)$L['numero_pedido'];
}

// Actualizar total
pg_query_params($c,"UPDATE factura_compra_cab SET total_factura=$2 WHERE id_factura=$1", [$id_factura, $total]);

/* Recalcular estados de OC y Pedido */
foreach($oc_tocadas as $id_oc=>$num_pedido){
  // OC: Recibida parcial/total según facturas no anuladas
  $r = pg_query_params($c,"
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
  list($completas,$totalRows,$parciales) = array_map('intval', pg_fetch_row($r));
  $estadoOC = 'Emitida';
  if ($totalRows>0 && $completas === $totalRows) $estadoOC = 'Totalmente Recibida';
  else if ($parciales>0 || $completas>0) $estadoOC = 'Parcialmente Recibida';

  pg_query_params($c,"UPDATE orden_compra_cab SET estado=$2 WHERE id_oc=$1 AND estado<>'Anulada'", [$id_oc, $estadoOC]);

  // Pedido: Entregado parcial/total según facturas
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
  else if ($p2>0 || $c2>0) $estadoPed = 'Parcialmente Entregado';

  pg_query_params($c,"
    UPDATE cabecera_pedido_interno
    SET estado = CASE WHEN estado='Anulado' THEN estado ELSE $2 END
    WHERE numero_pedido=$1
  ", [$num_pedido, $estadoPed]);
}

pg_query($c,"COMMIT");
echo json_encode(["ok"=>true,"id_factura"=>$id_factura,"total"=>$total], JSON_UNESCAPED_UNICODE);
