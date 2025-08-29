<?php
// listar_presupuestos.php
header('Content-Type: application/json; charset=utf-8');

require_once("../conexion/config.php");
$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ http_response_code(500); echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$numero_pedido = isset($_GET['numero_pedido']) ? (int)$_GET['numero_pedido'] : 0;
$id_proveedor  = isset($_GET['id_proveedor'])  ? (int)$_GET['id_proveedor']  : 0;
$estado        = isset($_GET['estado'])        ? trim($_GET['estado'])       : '';

$params = [];
$w = [];
if ($numero_pedido > 0) { $w[] = "p.numero_pedido = $".(count($params)+1); $params[] = $numero_pedido; }
if ($id_proveedor  > 0) { $w[] = "p.id_proveedor  = $".(count($params)+1); $params[] = $id_proveedor; }
if ($estado !== '')     { $w[] = "p.estado ILIKE $".(count($params)+1);     $params[] = $estado; }

$where = $w ? "WHERE ".implode(" AND ", $w) : "";

$sqlCab = "
  SELECT
    p.id_presupuesto,
    p.numero_pedido,
    p.id_proveedor,
    prov.nombre AS proveedor,
    p.fecharegistro,
    p.fechavencimiento,
    p.estado,
    COALESCE(SUM(pd.cantidad * pd.precio_unitario),0)::numeric(12,2)                               AS total_cotizado,
    COALESCE(SUM(CASE WHEN pd.estado_detalle = 'Aprobado' THEN pd.cantidad_aprobada * pd.precio_unitario ELSE 0 END),0)::numeric(12,2) AS total_aprobado
  FROM public.presupuestos p
  LEFT JOIN public.proveedores prov ON prov.id_proveedor = p.id_proveedor
  LEFT JOIN public.presupuesto_detalle pd ON pd.id_presupuesto = p.id_presupuesto
  $where
  GROUP BY p.id_presupuesto, p.numero_pedido, p.id_proveedor, prov.nombre, p.fecharegistro, p.fechavencimiento, p.estado
  ORDER BY p.id_presupuesto DESC
  LIMIT 500
";
$resCab = $params ? pg_query_params($c,$sqlCab,$params) : pg_query($c,$sqlCab);
$cabs = [];
$ids  = [];
if ($resCab){
  while($r = pg_fetch_assoc($resCab)){
    $r['id_presupuesto'] = (int)$r['id_presupuesto'];
    $ids[] = $r['id_presupuesto'];
    $r['lineas'] = []; // se llenará luego
    $cabs[$r['id_presupuesto']] = $r;
  }
}

if (empty($ids)) { echo json_encode(["ok"=>true,"data"=>[]], JSON_UNESCAPED_UNICODE); exit; }

// Traer líneas en un solo query
$in = "(".implode(",", array_map('intval',$ids)).")";
$sqlDet = "
  SELECT
    pd.id_presupuesto_detalle,
    pd.id_presupuesto,
    pd.id_producto,
    p.nombre AS producto,
    pd.cantidad,
    pd.precio_unitario,
    (pd.cantidad * pd.precio_unitario)::numeric(12,2) AS total_linea,
    pd.estado_detalle,
    pd.cantidad_aprobada
  FROM public.presupuesto_detalle pd
  JOIN public.producto p ON p.id_producto = pd.id_producto
  WHERE pd.id_presupuesto IN $in
  ORDER BY pd.id_presupuesto, pd.id_presupuesto_detalle
";
$resDet = pg_query($c,$sqlDet);
if ($resDet){
  while($d = pg_fetch_assoc($resDet)){
    $pid = (int)$d['id_presupuesto'];
    if (isset($cabs[$pid])) $cabs[$pid]['lineas'][] = $d;
  }
}

echo json_encode(["ok"=>true,"data"=>array_values($cabs)], JSON_UNESCAPED_UNICODE);
