<?php
// factura_preparar.php
header('Content-Type: application/json; charset=utf-8');
require_once("../../conexion/configv2.php");

// Conexión PGSQL (tu formato)
$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

// ===== Inputs =====
$id_prov  = isset($_GET['id_proveedor']) ? (int)$_GET['id_proveedor'] : 0;
$id_oc    = isset($_GET['id_oc']) ? (int)$_GET['id_oc'] : 0;
$pedido   = isset($_GET['numero_pedido']) ? trim($_GET['numero_pedido']) : '';

if ($id_prov <= 0) { echo json_encode(["ok"=>false,"error"=>"id_proveedor requerido"]); exit; }

// ===== Subquery para lo YA FACTURADO por id_oc_det (solo facturas no anuladas) =====
$subYaFact = "
  SELECT d.id_oc_det, SUM(fcd.cantidad) AS ya_facturado
  FROM factura_compra_det fcd
  JOIN factura_compra_cab fcc ON fcc.id_factura = fcd.id_factura
  JOIN orden_compra_det d     ON d.id_oc_det   = fcd.id_oc_det
  WHERE fcc.estado <> 'Anulada'
  GROUP BY d.id_oc_det
";

// =====================================================
// MODO DETALLE por id_oc  => ?id_proveedor=..&id_oc=..
// =====================================================
if ($id_oc > 0) {
  $sql = "
    WITH fact AS ($subYaFact)
    SELECT
      occ.id_oc,
      ocd.id_oc_det,
      ocd.id_producto,
      p.nombre AS producto,
      GREATEST(ocd.cantidad - COALESCE(fact.ya_facturado,0), 0)::int AS pendiente
    FROM orden_compra_cab occ
    JOIN orden_compra_det ocd ON ocd.id_oc = occ.id_oc
    JOIN producto p           ON p.id_producto = ocd.id_producto
    LEFT JOIN fact            ON fact.id_oc_det = ocd.id_oc_det
    WHERE occ.id_proveedor = $1
      AND occ.id_oc = $2
      AND occ.estado <> 'Anulada'
      AND GREATEST(ocd.cantidad - COALESCE(fact.ya_facturado,0), 0) > 0
    ORDER BY ocd.id_oc_det
  ";
  $res = pg_query_params($c, $sql, [$id_prov, $id_oc]);
  if(!$res){ echo json_encode(["ok"=>false,"error"=>"Query detalle"]); exit; }

  $data = [];
  while($r = pg_fetch_assoc($res)){
    $r['id_oc']       = (int)$r['id_oc'];
    $r['id_oc_det']   = (int)$r['id_oc_det'];
    $r['id_producto'] = (int)$r['id_producto'];
    $r['pendiente']   = (int)$r['pendiente'];
    $data[] = $r;
  }

  echo json_encode(["ok"=>true, "mode"=>"detail", "data"=>$data], JSON_UNESCAPED_UNICODE);
  exit;
}

// ==================================================================
// MODO DETALLE por NÚMERO DE PEDIDO => ?id_proveedor=..&numero_pedido=..
// (opcional; mantiene tu flujo actual si querés seguir usándolo)
// ==================================================================
if ($pedido !== '') {
  // Si tu numero_pedido es numérico, podés castear/validar; acá va como texto por si tiene prefijos
  $sql = "
    WITH fact AS ($subYaFact)
    SELECT
      occ.id_oc,
      ocd.id_oc_det,
      ocd.id_producto,
      p.nombre AS producto,
      GREATEST(ocd.cantidad - COALESCE(fact.ya_facturado,0), 0)::int AS pendiente
    FROM orden_compra_cab occ
    JOIN orden_compra_det ocd ON ocd.id_oc = occ.id_oc
    JOIN producto p           ON p.id_producto = ocd.id_producto
    LEFT JOIN fact            ON fact.id_oc_det = ocd.id_oc_det
    WHERE occ.id_proveedor = $1
      AND occ.numero_pedido = $2
      AND occ.estado <> 'Anulada'
      AND GREATEST(ocd.cantidad - COALESCE(fact.ya_facturado,0), 0) > 0
    ORDER BY occ.id_oc, ocd.id_oc_det
  ";
  $res = pg_query_params($c, $sql, [$id_prov, $pedido]);
  if(!$res){ echo json_encode(["ok"=>false,"error"=>"Query pedido"]); exit; }

  $data = [];
  while($r = pg_fetch_assoc($res)){
    $r['id_oc']       = (int)$r['id_oc'];
    $r['id_oc_det']   = (int)$r['id_oc_det'];
    $r['id_producto'] = (int)$r['id_producto'];
    $r['pendiente']   = (int)$r['pendiente'];
    $data[] = $r;
  }

  echo json_encode(["ok"=>true, "mode"=>"detail", "data"=>$data], JSON_UNESCAPED_UNICODE);
  exit;
}

// =============================================================
// MODO LISTA: OCs con pendiente => ?id_proveedor=..
// Para poblar el <select> y NO pedir número de pedido
// =============================================================
$sql = "
  WITH fact AS ($subYaFact)
  SELECT
    occ.id_oc,
    occ.numero_pedido,
    occ.fecha_emision,
    COUNT(ocd.id_oc_det)::int AS cant_items,
    SUM( GREATEST(ocd.cantidad - COALESCE(fact.ya_facturado,0), 0) )::int AS pendiente_total
  FROM orden_compra_cab occ
  JOIN orden_compra_det ocd ON ocd.id_oc = occ.id_oc
  LEFT JOIN fact            ON fact.id_oc_det = ocd.id_oc_det
  WHERE occ.id_proveedor = $1
    AND occ.estado <> 'Anulada'
  GROUP BY occ.id_oc, occ.numero_pedido, occ.fecha_emision
  HAVING SUM( GREATEST(ocd.cantidad - COALESCE(fact.ya_facturado,0), 0) ) > 0
  ORDER BY occ.fecha_emision DESC, occ.id_oc DESC
";
$res = pg_query_params($c, $sql, [$id_prov]);
if(!$res){ echo json_encode(["ok"=>false,"error"=>"Query lista"]); exit; }

$ocs = [];
while($r = pg_fetch_assoc($res)){
  $ocs[] = [
    "id_oc"           => (int)$r["id_oc"],
    "numero_pedido"   => $r["numero_pedido"] === null ? null : (string)$r["numero_pedido"],
    "fecha_oc"        => $r["fecha_emision"],
    "cant_items"      => (int)$r["cant_items"],
    "pendiente_total" => (int)$r["pendiente_total"],
  ];
}

echo json_encode(["ok"=>true, "mode"=>"list", "ocs"=>$ocs], JSON_UNESCAPED_UNICODE);
