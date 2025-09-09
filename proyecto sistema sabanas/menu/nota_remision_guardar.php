<?php
// nota_remision_guardar.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

// ===== Inputs =====
$id_proveedor   = isset($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : 0;
$id_factura     = isset($_POST['id_factura'])   ? (int)$_POST['id_factura']   : 0;
$id_sucursal    = isset($_POST['id_sucursal'])  && $_POST['id_sucursal']!=='' ? (int)$_POST['id_sucursal'] : null;
$fecha_remision = isset($_POST['fecha_remision']) ? trim($_POST['fecha_remision']) : '';
$nro_remision   = isset($_POST['nro_remision_prov']) ? trim($_POST['nro_remision_prov']) : '';
$transportista  = isset($_POST['transportista']) ? trim($_POST['transportista']) : '';
$chofer         = isset($_POST['chofer']) ? trim($_POST['chofer']) : '';
$vehiculo       = isset($_POST['vehiculo']) ? trim($_POST['vehiculo']) : '';
$observacion    = isset($_POST['observacion']) ? trim($_POST['observacion']) : '';

$id_det  = isset($_POST['id_factura_det']) ? $_POST['id_factura_det'] : [];
$cants   = isset($_POST['cantidad'])       ? $_POST['cantidad']       : [];

// ===== Validaciones básicas =====
if ($id_proveedor<=0 || $id_factura<=0 || $fecha_remision==='') {
  echo json_encode(["ok"=>false,"error"=>"Parámetros requeridos faltantes"]); exit;
}
if (!is_array($id_det) || !is_array($cants) || count($id_det)==0 || count($id_det)!=count($cants)) {
  echo json_encode(["ok"=>false,"error"=>"Detalle inválido"]); exit;
}

// Normalizar detalle y filtrar cantidades > 0
$items = [];
for($i=0;$i<count($id_det);$i++){
  $idf = (int)$id_det[$i];
  $q   = (float)$cants[$i];
  if ($idf>0 && $q>0){
    $items[] = ["id_factura_det"=>$idf, "cantidad"=>$q];
  }
}
if (count($items)==0){
  echo json_encode(["ok"=>false,"error"=>"No hay cantidades > 0 para remitir"]); exit;
}

// ===== 1) Validar factura del proveedor y no anulada =====
$sql = "SELECT id_factura
        FROM factura_compra_cab
        WHERE id_factura=$1 AND id_proveedor=$2 AND estado<>'Anulada'";
$res = pg_query_params($c, $sql, [$id_factura, $id_proveedor]);
if(!$res || pg_num_rows($res)==0){
  echo json_encode(["ok"=>false,"error"=>"Factura no válida para el proveedor o anulada"]); exit;
}

// ===== 2) Validar que TODOS los id_factura_det pertenezcan a esa factura =====
$ids_det = array_map(fn($x)=>$x["id_factura_det"], $items);
$place   = [];
$params  = [$id_factura];
$idx     = 2;
foreach($ids_det as $idv){ $place[] = '$'.$idx; $params[] = $idv; $idx++; }

$sql = "SELECT COUNT(*) AS cnt
        FROM factura_compra_det
        WHERE id_factura=$1 AND id_factura_det IN (".implode(',', $place).")";
$res = pg_query_params($c, $sql, $params);
$row = $res ? pg_fetch_assoc($res) : null;
if(!$row || (int)$row['cnt'] != count($ids_det)){
  echo json_encode(["ok"=>false,"error"=>"Existen líneas que no pertenecen a la factura"]); exit;
}

// ===== 3) Transacción y bloqueo de líneas para evitar carreras =====
pg_query($c, "BEGIN");

try {
  // Bloquear filas de factura_compra_det afectadas
  $sqlLock = "SELECT id_factura_det, cantidad::numeric AS facturado
              FROM factura_compra_det
              WHERE id_factura = $1 AND id_factura_det IN (".implode(',', $place).")
              FOR UPDATE";
  $resLock = pg_query_params($c, $sqlLock, $params);
  if(!$resLock){ throw new Exception("No se pudieron bloquear las líneas de factura"); }

  // Traer remitido previo (solo remisiones activas) para estas líneas
  $sqlPend = "
    SELECT fcd.id_factura_det,
           fcd.cantidad::numeric AS facturado,
           COALESCE(SUM(CASE WHEN nrc.estado<>'Anulada' THEN nrd.cantidad ELSE 0 END),0)::numeric AS ya_remitido
    FROM factura_compra_det fcd
    LEFT JOIN nota_remision_det nrd ON nrd.id_factura_det = fcd.id_factura_det
    LEFT JOIN nota_remision_cab nrc ON nrc.id_nota_remision = nrd.id_nota_remision
    WHERE fcd.id_factura = $1
      AND fcd.id_factura_det IN (".implode(',', $place).")
    GROUP BY fcd.id_factura_det, fcd.cantidad
  ";
  $resPend = pg_query_params($c, $sqlPend, $params);
  if(!$resPend){ throw new Exception("Error calculando pendientes"); }

  $mapPend = []; // id_factura_det => pendiente
  while($r = pg_fetch_assoc($resPend)){
    $fact   = (float)$r['facturado'];
    $rem    = (float)$r['ya_remitido'];
    $pend   = $fact - $rem;
    if ($pend < 0) $pend = 0;
    $mapPend[(int)$r['id_factura_det']] = $pend;
  }

  // Validar cantidades contra pendiente
  foreach($items as $it){
    $idf = $it['id_factura_det'];
    $q   = $it['cantidad'];
    $pend = $mapPend[$idf] ?? 0.0;
    if ($q <= 0 || $q > $pend + 1e-9) {
      throw new Exception("Cantidad a remitir inválida para línea $idf (pendiente: $pend)");
    }
  }

  // ===== 4) Insertar cabecera =====
  $sqlCab = "INSERT INTO nota_remision_cab
               (id_proveedor, id_factura, id_sucursal, fecha_remision, nro_remision_prov,
                transportista, chofer, vehiculo, observacion, estado, creado_en)
             VALUES
               ($1,$2,$3,$4,$5,$6,$7,$8,$9,'Activo', NOW())
             RETURNING id_nota_remision";
  $paramsCab = [
    $id_proveedor,
    $id_factura,
    $id_sucursal,        // puede ser null
    $fecha_remision,
    $nro_remision,
    $transportista,
    $chofer,
    $vehiculo,
    $observacion
  ];
  $resCab = pg_query_params($c, $sqlCab, $paramsCab);
  if(!$resCab){ throw new Exception("No se pudo insertar cabecera"); }
  $rowCab = pg_fetch_assoc($resCab);
  $id_nota_remision = (int)$rowCab['id_nota_remision'];

  // ===== 5) Insertar detalle =====
  $sqlDet = "INSERT INTO nota_remision_det
               (id_nota_remision, id_factura_det, cantidad)
             VALUES ($1,$2,$3)";
  $total = 0.0;
  foreach($items as $it){
    $ok = pg_query_params($c, $sqlDet, [$id_nota_remision, $it['id_factura_det'], $it['cantidad']]);
    if(!$ok){ throw new Exception("No se pudo insertar detalle (línea ".$it['id_factura_det'].")"); }
    $total += (float)$it['cantidad'];
  }

  // (Opcional) acá podrías actualizar stock por sucursal si tu modelo lo requiere.

  pg_query($c, "COMMIT");
  echo json_encode(["ok"=>true, "id_nota_remision"=>$id_nota_remision, "total_remitido"=>$total], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  pg_query($c, "ROLLBACK");
  echo json_encode(["ok"=>false,"error"=>$e->getMessage()]);
  exit;
}
