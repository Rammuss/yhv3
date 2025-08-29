<?php
// pedidos_listar.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$c) { echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$numero_pedido = isset($_GET['numero_pedido']) ? (int)$_GET['numero_pedido'] : 0;
$estado        = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$depto         = isset($_GET['departamento']) ? trim($_GET['departamento']) : '';
$desde         = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta         = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

$w = []; $p = [];
if ($numero_pedido > 0) { $w[] = "c.numero_pedido = $".(count($p)+1); $p[] = $numero_pedido; }
if ($estado !== '')     { $w[] = "c.estado ILIKE $".(count($p)+1);     $p[] = $estado; }
if ($depto !== '')      { $w[] = "c.departamento_solicitante ILIKE $".(count($p)+1); $p[] = "%$depto%"; }
if ($desde !== '')      { $w[] = "c.fecha_pedido >= $".(count($p)+1);  $p[] = $desde; }
if ($hasta !== '')      { $w[] = "c.fecha_pedido <= $".(count($p)+1);  $p[] = $hasta; }

$where = $w ? "WHERE ".implode(" AND ", $w) : "";

$sql = "
  SELECT
    c.numero_pedido,
    c.departamento_solicitante,
    c.telefono,
    c.correo,
    c.fecha_pedido,
    c.fecha_entrega_solicitada,
    c.estado,

    /* Totales del pedido (cantidad) */
    COALESCE((SELECT SUM(d.cantidad) FROM detalle_pedido_interno d
              WHERE d.numero_pedido=c.numero_pedido),0)::int AS total_pedido,

    /* Cantidad ordenada en OCs no anuladas */
    COALESCE((
      SELECT SUM(ocd.cantidad)
      FROM orden_compra_det ocd
      JOIN orden_compra_cab occ ON occ.id_oc=ocd.id_oc AND occ.estado<>'Anulada'
      WHERE occ.numero_pedido=c.numero_pedido
    ),0)::int AS total_ordenada,

    /* OCs */
    COALESCE((SELECT COUNT(*) FROM orden_compra_cab occ WHERE occ.numero_pedido=c.numero_pedido),0)::int AS ocs_total,
    COALESCE((SELECT COUNT(*) FROM orden_compra_cab occ WHERE occ.numero_pedido=c.numero_pedido AND occ.estado<>'Anulada'),0)::int AS ocs_activas,

    /* Presupuestos (cabeceras) */
    COALESCE((SELECT COUNT(*) FROM presupuestos pr WHERE pr.numero_pedido=c.numero_pedido),0)::int AS presup_total,
    COALESCE((SELECT COUNT(*) FROM presupuestos pr WHERE pr.numero_pedido=c.numero_pedido AND pr.estado NOT ILIKE 'Anulado%'),0)::int AS presup_activos

  FROM cabecera_pedido_interno c
  $where
  ORDER BY c.numero_pedido DESC
  LIMIT 500
";

$res = $p ? pg_query_params($c,$sql,$p) : pg_query($c,$sql);
$out = [];
if ($res) while($r=pg_fetch_assoc($res)) $out[]=$r;

echo json_encode(["ok"=>true,"data"=>$out], JSON_UNESCAPED_UNICODE);
