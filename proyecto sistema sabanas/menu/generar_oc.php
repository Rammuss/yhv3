<?php
// generar_oc.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ http_response_code(500); echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

/**
 * Espera POST con:
 * - numero_pedido (int)
 * - id_proveedor (int)
 * - observacion (opcional)
 * - id_presupuesto_detalle[] (array)
 * - cantidad[] (array, misma longitud)
 */

$pedido = isset($_POST['numero_pedido']) ? (int)$_POST['numero_pedido'] : 0;
$prov   = isset($_POST['id_proveedor'])  ? (int)$_POST['id_proveedor']  : 0;
$obs    = $_POST['observacion'] ?? null;

$ids    = $_POST['id_presupuesto_detalle'] ?? [];   // arrays paralelos
$cants  = $_POST['cantidad'] ?? [];

if ($pedido<=0 || $prov<=0) { http_response_code(400); echo json_encode(["ok"=>false,"error"=>"Parámetros requeridos"]); exit; }
if (!is_array($ids) || count($ids)==0) { http_response_code(400); echo json_encode(["ok"=>false,"error"=>"Sin líneas"]); exit; }
if (!is_array($cants) || count($cants)!=count($ids)) { http_response_code(400); echo json_encode(["ok"=>false,"error"=>"Datos de líneas inválidos"]); exit; }

pg_query($c, "BEGIN");

$insCab = "
  INSERT INTO public.orden_compra_cab (numero_pedido, id_proveedor, observacion)
  VALUES ($1,$2,$3) RETURNING id_oc
";
$rCab = pg_query_params($c, $insCab, [$pedido, $prov, $obs]);
if(!$rCab){ pg_query($c,"ROLLBACK"); http_response_code(500); echo json_encode(["ok"=>false,"error"=>"No se pudo crear OC"]); exit; }
$id_oc = (int)pg_fetch_result($rCab, 0, 0);

$insDet = "
  INSERT INTO public.orden_compra_det (id_oc, id_producto, cantidad, precio_unit, id_presupuesto_detalle)
  VALUES ($1,$2,$3,$4,$5)
";

$presupuestosTocados = []; // <-- acumulamos acá los presupuestos que participan

for($i=0; $i<count($ids); $i++){
  $id_det = (int)$ids[$i];
  $cant   = (int)$cants[$i];
  if ($id_det<=0 || $cant<=0) { pg_query($c,"ROLLBACK"); http_response_code(400); echo json_encode(["ok"=>false,"error"=>"Línea inválida"]); exit; }

  // Traer línea aprobada y validar proveedor y pedido
  $q = "
    SELECT
      pd.id_presupuesto_detalle,
      pd.id_producto,
      pd.precio_unitario,
      pd.cantidad_aprobada,
      pr.id_proveedor,
      pr.numero_pedido,
      pr.id_presupuesto              -- <-- lo necesitamos para recalcular estado del presupuesto
    FROM public.presupuesto_detalle pd
    JOIN public.presupuestos pr ON pr.id_presupuesto = pd.id_presupuesto
    WHERE pd.id_presupuesto_detalle = $1
    LIMIT 1
  ";
  $r = pg_query_params($c,$q,[$id_det]);
  if(!$r || pg_num_rows($r)==0){ pg_query($c,"ROLLBACK"); http_response_code(400); echo json_encode(["ok"=>false,"error"=>"Línea aprobada no encontrada"]); exit; }
  $L = pg_fetch_assoc($r);

  if ((int)$L['id_proveedor'] !== $prov || (int)$L['numero_pedido'] !== $pedido){
    pg_query($c,"ROLLBACK"); http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"La línea no corresponde al pedido/proveedor seleccionados"]); exit;
  }

  $disp = (int)$L['cantidad_aprobada'];
  if ($cant > $disp){
    pg_query($c,"ROLLBACK"); http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"Cantidad supera lo aprobado para esta línea"]); exit;
  }

  $id_prod = (int)$L['id_producto'];
  $precio  = (float)$L['precio_unitario'];

  // Insert OC detalle
  $okDet = pg_query_params($c,$insDet,[$id_oc, $id_prod, $cant, $precio, $id_det]);
  if(!$okDet){ pg_query($c,"ROLLBACK"); http_response_code(500); echo json_encode(["ok"=>false,"error"=>"No se pudo insertar detalle OC"]); exit; }

  // Descontar de lo aprobado (evitar reuso) con tope en 0
  $upd = "UPDATE public.presupuesto_detalle SET cantidad_aprobada = GREATEST(cantidad_aprobada - $2, 0) WHERE id_presupuesto_detalle = $1";
  $okU = pg_query_params($c,$upd,[$id_det,$cant]);
  if(!$okU){ pg_query($c,"ROLLBACK"); http_response_code(500); echo json_encode(["ok"=>false,"error"=>"No se pudo actualizar aprobación"]); exit; }

  // Acumular presupuesto tocado
  $presupuestosTocados[] = (int)$L['id_presupuesto'];
}

// 1) Ajustar estado_detalle por si quedaron en 0
$idsDet = '{'.implode(',', array_map('intval',$ids)).'}'; // array literal para ANY($1::int[])
pg_query_params($c, "
  UPDATE public.presupuesto_detalle
  SET estado_detalle = CASE
    WHEN cantidad_aprobada > 0 THEN 'Aprobado'
    ELSE 'Comprometido'
  END
  WHERE id_presupuesto_detalle = ANY($1::int[])
", [$idsDet]);

// 2) Recalcular estado de cada presupuesto involucrado
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

// 3) Recalcular estado del pedido
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
", [$pedido]);

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

// no pises 'Anulado'
pg_query_params($c, "
  UPDATE cabecera_pedido_interno
  SET estado = CASE WHEN estado='Anulado' THEN estado ELSE $2 END
  WHERE numero_pedido=$1
", [$pedido, $estadoPedido]);

pg_query($c,"COMMIT");
echo json_encode(["ok"=>true,"id_oc"=>$id_oc], JSON_UNESCAPED_UNICODE);
