<?php
// notas_compra_listar.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$fd         = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fh         = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';
$id_prov    = isset($_GET['id_proveedor']) ? (int)$_GET['id_proveedor'] : 0;
$id_suc     = isset($_GET['id_sucursal'])  ? (int)$_GET['id_sucursal']  : 0;
$tipo       = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';      // 'NC' | 'ND' | ''
$estado     = isset($_GET['estado']) ? trim($_GET['estado']) : '';  // 'Registrada'|'Aplicada'|'Aplicada Parcial'|'Anulada'|''
$q          = isset($_GET['q']) ? trim($_GET['q']) : '';            // busca en numero_documento / timbrado / id_nota
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = max(1, min(100, (int)($_GET['limit'] ?? 20)));
$offset     = ($page - 1) * $limit;

$w = ["1=1"];
$p = [];
$idx = 1;

// Fechas (por defecto últimos 90 días)
if ($fd !== '' && $fh !== '') { $w[] = "ncc.fecha_emision BETWEEN $".$idx++." AND $".$idx++; $p[] = $fd; $p[] = $fh; }
elseif ($fd !== '')           { $w[] = "ncc.fecha_emision >= $".$idx++; $p[] = $fd; }
elseif ($fh !== '')           { $w[] = "ncc.fecha_emision <= $".$idx++; $p[] = $fh; }
else                          { $w[] = "ncc.fecha_emision >= (CURRENT_DATE - INTERVAL '90 days')"; }

if ($id_prov > 0) { $w[] = "ncc.id_proveedor = $".$idx++; $p[] = $id_prov; }
if ($id_suc  > 0) { $w[] = "ncc.id_sucursal  = $".$idx++; $p[] = $id_suc; }
if ($tipo !== '') { $w[] = "ncc.tipo = $".$idx++; $p[] = $tipo; }
if ($estado !== '') { $w[] = "ncc.estado = $".$idx++; $p[] = $estado; }

if ($q !== '') {
  $w[] = "(ncc.numero_documento ILIKE $".$idx." OR ncc.timbrado_numero ILIKE $".$idx." OR CAST(ncc.id_nota AS TEXT) ILIKE $".$idx.")";
  $p[] = '%'.$q.'%';
  $idx++;
}

$base = "
  FROM notas_compra_cab ncc
  JOIN proveedores prov ON prov.id_proveedor = ncc.id_proveedor
  LEFT JOIN sucursales suc ON suc.id_sucursal = ncc.id_sucursal
  LEFT JOIN factura_compra_cab fcc ON fcc.id_factura = ncc.id_factura_ref
  WHERE ".implode(' AND ', $w)."
";

$sql_count = "SELECT COUNT(*) AS total $base";
$rc = pg_query_params($c, $sql_count, $p);
if(!$rc){ echo json_encode(["ok"=>false,"error"=>"count"]); exit; }
$tot = (int)pg_fetch_result($rc, 0, 'total');

$sql_sum = "SELECT COALESCE(SUM(ncc.total_nota),0)::numeric AS total_importe $base";
$rs = pg_query_params($c, $sql_sum, $p);
$total_importe = $rs ? (float)pg_fetch_result($rs, 0, 'total_importe') : 0.0;

$sql = "
  SELECT
    ncc.id_nota,
    ncc.fecha_emision,
    ncc.tipo,
    ncc.clase,
    ncc.estado,
    ncc.numero_documento,
    ncc.timbrado_numero,
    ncc.total_nota,
    prov.nombre AS proveedor,
    COALESCE(suc.nombre,'') AS sucursal,
    COALESCE(fcc.numero_documento,'') AS nro_factura_ref
  $base
  ORDER BY ncc.fecha_emision DESC, ncc.id_nota DESC
  LIMIT $limit OFFSET $offset
";
$r = pg_query_params($c, $sql, $p);
if(!$r){ echo json_encode(["ok"=>false,"error"=>"query"]); exit; }

$rows = [];
while($x = pg_fetch_assoc($r)){
  $rows[] = [
    "id_nota"          => (int)$x["id_nota"],
    "fecha_emision"    => $x["fecha_emision"],
    "tipo"             => $x["tipo"],
    "clase"            => $x["clase"],
    "estado"           => $x["estado"],
    "numero_documento" => $x["numero_documento"],
    "timbrado_numero"  => $x["timbrado_numero"],
    "total_nota"       => (float)$x["total_nota"],
    "proveedor"        => $x["proveedor"],
    "sucursal"         => $x["sucursal"],
    "nro_factura_ref"  => $x["nro_factura_ref"]
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
    "sum_total_importe"=>$total_importe
  ]
], JSON_UNESCAPED_UNICODE);
