<?php
// nota_remision_listar.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$fd         = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fh         = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';
$id_prov    = isset($_GET['id_proveedor']) ? (int)$_GET['id_proveedor'] : 0;
$id_suc     = isset($_GET['id_sucursal']) ? (int)$_GET['id_sucursal'] : 0;
$estado     = isset($_GET['estado']) ? trim($_GET['estado']) : ''; // 'Activo' | 'Anulada' | ''
$q          = isset($_GET['q']) ? trim($_GET['q']) : '';           // busca en nro_remision_prov / id_nota_remision / numero_documento
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = max(1, min(100, (int)($_GET['limit'] ?? 20)));
$offset     = ($page - 1) * $limit;

$w = ["1=1"];
$p = [];
$idx = 1;

if ($fd !== '' && $fh !== '') { $w[] = "nrc.fecha_remision BETWEEN $".$idx++." AND $".$idx++; $p[] = $fd; $p[] = $fh; }
elseif ($fd !== '')           { $w[] = "nrc.fecha_remision >= $".$idx++; $p[] = $fd; }
elseif ($fh !== '')           { $w[] = "nrc.fecha_remision <= $".$idx++; $p[] = $fh; }
else                          { $w[] = "nrc.fecha_remision >= (CURRENT_DATE - INTERVAL '90 days')"; }

if ($id_prov > 0) { $w[] = "nrc.id_proveedor = $".$idx++; $p[] = $id_prov; }
if ($id_suc  > 0) { $w[] = "nrc.id_sucursal  = $".$idx++; $p[] = $id_suc; }
if ($estado !== '') { $w[] = "nrc.estado = $".$idx++; $p[] = $estado; }

if ($q !== '') {
  $w[] = "(nrc.nro_remision_prov ILIKE $".$idx." OR CAST(nrc.id_nota_remision AS TEXT) ILIKE $".$idx." OR fcc.numero_documento ILIKE $".$idx.")";
  $p[] = '%'.$q.'%';
  $idx++;
}

$base = "
  FROM nota_remision_cab nrc
  JOIN proveedores prov ON prov.id_proveedor = nrc.id_proveedor
  LEFT JOIN sucursales suc ON suc.id_sucursal = nrc.id_sucursal
  JOIN factura_compra_cab fcc ON fcc.id_factura = nrc.id_factura
  WHERE ".implode(' AND ', $w)."
";

$sql_count = "SELECT COUNT(*) AS total $base";
$rc = pg_query_params($c, $sql_count, $p);
if(!$rc){ echo json_encode(["ok"=>false,"error"=>"count"]); exit; }
$tot = (int)pg_fetch_result($rc, 0, 'total');

$sql_sum = "SELECT COALESCE(SUM(nrd.cantidad),0)::numeric AS total_remitido
  FROM nota_remision_det nrd
  JOIN nota_remision_cab nrc ON nrc.id_nota_remision = nrd.id_nota_remision
  JOIN factura_compra_cab fcc ON fcc.id_factura = nrc.id_factura
  WHERE ".implode(' AND ', $w);
$rs = pg_query_params($c, $sql_sum, $p);
$sum_total = $rs ? (float)pg_fetch_result($rs, 0, 'total_remitido') : 0.0;

$sql = "
  SELECT
    nrc.id_nota_remision,
    nrc.fecha_remision,
    nrc.estado,
    nrc.nro_remision_prov,
    prov.nombre AS proveedor,
    COALESCE(suc.nombre,'') AS sucursal,
    fcc.numero_documento AS nro_factura,
    -- total remitido de esta nota
    (SELECT COALESCE(SUM(nrd.cantidad),0)::numeric
     FROM nota_remision_det nrd
     WHERE nrd.id_nota_remision = nrc.id_nota_remision) AS total_nota
  $base
  ORDER BY nrc.fecha_remision DESC, nrc.id_nota_remision DESC
  LIMIT $limit OFFSET $offset
";
$r = pg_query_params($c, $sql, $p);
if(!$r){ echo json_encode(["ok"=>false,"error"=>"query"]); exit; }

$rows = [];
while($x = pg_fetch_assoc($r)){
  $rows[] = [
    "id_nota_remision" => (int)$x["id_nota_remision"],
    "fecha_remision"   => $x["fecha_remision"],
    "estado"           => $x["estado"],
    "nro_remision_prov"=> $x["nro_remision_prov"],
    "proveedor"        => $x["proveedor"],
    "sucursal"         => $x["sucursal"],
    "nro_factura"      => $x["nro_factura"],
    "total_nota"       => (float)$x["total_nota"]
  ];
}

echo json_encode([
  "ok"=>true,
  "data"=>$rows,
  "meta"=>[
    "page"=>$page,
    "limit"=>$limit,
    "total"=>$tot,
    "pages"=>ceil($tot/$limit),
    "sum_total_remitido"=>$sum_total
  ]
], JSON_UNESCAPED_UNICODE);
