<?php
// aprobar_linea_presupuesto.php
header('Content-Type: application/json; charset=utf-8');

require_once("../conexion/config.php");
$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ http_response_code(500); echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$id_detalle = isset($_POST['id_presupuesto_detalle']) ? (int)$_POST['id_presupuesto_detalle'] : 0;
$cant_apro  = isset($_POST['cantidad_aprobada'])      ? (int)$_POST['cantidad_aprobada']      : 0;

if ($id_detalle<=0 || $cant_apro<=0) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"Parámetros inválidos"]);
  exit;
}

// Traer datos de la línea y su contexto (pedido, producto, cantidad ofertada)
$sqlInfo = "
  SELECT
    pd.id_presupuesto_detalle,
    pd.id_presupuesto,
    pr.numero_pedido,
    pd.id_producto,
    pd.cantidad         AS cantidad_ofertada,
    COALESCE(pd.cantidad_aprobada,0) AS ya_aprobada_linea
  FROM public.presupuesto_detalle pd
  JOIN public.presupuestos pr ON pr.id_presupuesto = pd.id_presupuesto
  WHERE pd.id_presupuesto_detalle = $1
  LIMIT 1
";
$rInfo = pg_query_params($c,$sqlInfo,[$id_detalle]);
if(!$rInfo || pg_num_rows($rInfo)==0){
  http_response_code(404);
  echo json_encode(["ok"=>false,"error"=>"Línea no encontrada"]);
  exit;
}
$info = pg_fetch_assoc($rInfo);
$numero_pedido = (int)$info['numero_pedido'];
$id_producto   = (int)$info['id_producto'];
$ofertada      = (int)$info['cantidad_ofertada'];

// Validar contra oferta de la línea
if ($cant_apro > $ofertada) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"No puede aprobar más que lo ofertado en la línea"]);
  exit;
}

// Calcular PENDIENTE del pedido (usa aprobadas)
$sqlPend = "
  SELECT
    d.cantidad AS pedida,
    COALESCE((
      SELECT SUM(pd2.cantidad_aprobada)
      FROM public.presupuestos pr2
      JOIN public.presupuesto_detalle pd2 ON pd2.id_presupuesto = pr2.id_presupuesto
      WHERE pr2.numero_pedido = d.numero_pedido
        AND pd2.id_producto = d.id_producto
        AND pd2.estado_detalle = 'Aprobado'
        -- excluir esta misma línea si ya estaba aprobada antes
        AND pd2.id_presupuesto_detalle <> $1
    ),0)::int AS aprobada_otros
  FROM public.detalle_pedido_interno d
  WHERE d.numero_pedido = $2 AND d.id_producto = $3
  LIMIT 1
";
$rPend = pg_query_params($c,$sqlPend,[$id_detalle, $numero_pedido, $id_producto]);
if(!$rPend || pg_num_rows($rPend)==0){
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"No se encontró la línea del pedido para validar pendiente"]);
  exit;
}
$P = pg_fetch_assoc($rPend);
$pedida        = (int)$P['pedida'];
$aprobadaOtros = (int)$P['aprobada_otros'];
$pendiente     = $pedida - $aprobadaOtros;

if ($cant_apro > $pendiente) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"Supera el pendiente del pedido (pendiente=$pendiente)"]);
  exit;
}

// Aprobar
$upd = "
  UPDATE public.presupuesto_detalle
  SET estado_detalle = 'Aprobado',
      cantidad_aprobada = $2
  WHERE id_presupuesto_detalle = $1
";
$ok = pg_query_params($c,$upd,[$id_detalle,$cant_apro]);

if(!$ok){
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>"No se pudo aprobar la línea"]);
  exit;
}

echo json_encode(["ok"=>true]);
