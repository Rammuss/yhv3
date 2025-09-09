<?php
// nota_remision_preparar.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$id_prov     = isset($_GET['id_proveedor']) ? (int)$_GET['id_proveedor'] : 0;
$id_factura  = isset($_GET['id_factura'])   ? (int)$_GET['id_factura']   : 0;
$fd          = isset($_GET['fecha_desde'])  ? trim($_GET['fecha_desde']) : '';
$fh          = isset($_GET['fecha_hasta'])  ? trim($_GET['fecha_hasta']) : '';
$q           = isset($_GET['q'])            ? trim($_GET['q'])           : '';

if ($id_prov <= 0) { echo json_encode(["ok"=>false,"error"=>"id_proveedor requerido"]); exit; }

/*
  CTE 'remit': suma lo ya remitido por cada id_factura_det en notas NO anuladas.
  Esto permite calcular el pendiente = facturado - ya_remitido.
*/
$cte = "
WITH remit AS (
  SELECT nrd.id_factura_det, SUM(nrd.cantidad)::numeric AS ya_remitido
  FROM nota_remision_det nrd
  JOIN nota_remision_cab nrc ON nrc.id_nota_remision = nrd.id_nota_remision
  WHERE nrc.estado <> 'Anulada'
  GROUP BY nrd.id_factura_det
)
";

if ($id_factura > 0) {
  // =======================
  // MODO DETALLE por factura
  // =======================
  $sql = $cte . "
    SELECT
      fcd.id_factura_det,
      fcd.id_factura,
      fcd.id_producto,
      p.nombre AS producto,
      fcd.cantidad::numeric AS facturado,
      COALESCE(remit.ya_remitido,0)::numeric AS ya_remitido,
      GREATEST(fcd.cantidad::numeric - COALESCE(remit.ya_remitido,0)::numeric, 0)::numeric AS pendiente
    FROM factura_compra_cab fcc
    JOIN factura_compra_det fcd ON fcd.id_factura = fcc.id_factura
    JOIN producto p             ON p.id_producto  = fcd.id_producto
    LEFT JOIN remit             ON remit.id_factura_det = fcd.id_factura_det
    WHERE fcc.estado <> 'Anulada'
      AND fcc.id_proveedor = $1
      AND fcc.id_factura   = $2
      AND GREATEST(fcd.cantidad::numeric - COALESCE(remit.ya_remitido,0)::numeric, 0) > 0
    ORDER BY fcd.id_factura_det
  ";
  $res = pg_query_params($c, $sql, [$id_prov, $id_factura]);
  if(!$res){ echo json_encode(["ok"=>false,"error"=>"Query detalle"]); exit; }

  $data = [];
  while($r = pg_fetch_assoc($res)){
    $data[] = [
      'id_factura'     => (int)$r['id_factura'],
      'id_factura_det' => (int)$r['id_factura_det'],
      'id_producto'    => (int)$r['id_producto'],
      'producto'       => $r['producto'],
      'facturado'      => (float)$r['facturado'],
      'ya_remitido'    => (float)$r['ya_remitido'],
      'pendiente'      => (float)$r['pendiente'],
    ];
  }
  echo json_encode(["ok"=>true, "mode"=>"detail", "data"=>$data], JSON_UNESCAPED_UNICODE);
  exit;
}

// ==========================================================
// MODO LISTA: facturas del proveedor con pendiente de remitir
// Filtros: fecha_desde, fecha_hasta, q (numero_documento)
// Por defecto: últimos 90 días si no se pasan fechas.
// ==========================================================
$params = [$id_prov];
$wheres = ["fcc.estado <> 'Anulada'", "fcc.id_proveedor = $1"];

// Fechas
if ($fd !== '' && $fh !== '') {
  $wheres[] = "fcc.fecha_emision BETWEEN $2 AND $3";
  $params[] = $fd;
  $params[] = $fh;
} elseif ($fd !== '') {
  $wheres[] = "fcc.fecha_emision >= $2";
  $params[] = $fd;
} elseif ($fh !== '') {
  $wheres[] = "fcc.fecha_emision <= $2";
  $params[] = $fh;
} else {
  $wheres[] = "fcc.fecha_emision >= (CURRENT_DATE - INTERVAL '90 days')";
}

// Búsqueda por Nº documento (opcional)
if ($q !== '') {
  $wheres[] = "fcc.numero_documento ILIKE $" . (count($params)+1);
  $params[] = '%'.$q.'%';
}

$sql = $cte . "
  SELECT
    fcc.id_factura,
    fcc.numero_documento,
    fcc.fecha_emision,
    SUM(GREATEST(fcd.cantidad::numeric - COALESCE(remit.ya_remitido,0)::numeric, 0))::numeric AS pendiente_total,
    COUNT(*)::int AS cant_items
  FROM factura_compra_cab fcc
  JOIN factura_compra_det fcd ON fcd.id_factura = fcc.id_factura
  LEFT JOIN remit ON remit.id_factura_det = fcd.id_factura_det
  WHERE ". implode(' AND ', $wheres) ."
  GROUP BY fcc.id_factura, fcc.numero_documento, fcc.fecha_emision
  HAVING SUM(GREATEST(fcd.cantidad::numeric - COALESCE(remit.ya_remitido,0)::numeric, 0)) > 0
  ORDER BY fcc.fecha_emision DESC, fcc.id_factura DESC
";

$res = pg_query_params($c, $sql, $params);
if(!$res){ echo json_encode(["ok"=>false,"error"=>"Query lista"]); exit; }

$facturas = [];
while($r = pg_fetch_assoc($res)){
  $facturas[] = [
    "id_factura"       => (int)$r["id_factura"],
    "numero_documento" => $r["numero_documento"],
    "fecha_emision"    => $r["fecha_emision"],
    "pendiente_total"  => (float)$r["pendiente_total"],
    "cant_items"       => (int)$r["cant_items"],
  ];
}

echo json_encode(["ok"=>true, "mode"=>"list", "facturas"=>$facturas], JSON_UNESCAPED_UNICODE);
