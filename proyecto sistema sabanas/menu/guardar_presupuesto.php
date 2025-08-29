<?php
// guardar_presupuesto.php
header('Content-Type: application/json; charset=utf-8');

require_once("../conexion/config.php");
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$conn) { http_response_code(500); echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

// Leer POST (form-data o x-www-form-urlencoded)
$numero_pedido  = isset($_POST['numero_pedido']) ? (int)$_POST['numero_pedido'] : 0;
$id_proveedor   = isset($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : 0;
$fecharegistro  = $_POST['fecharegistro'] ?? date('Y-m-d');
$fechavenc      = $_POST['fechavencimiento'] ?? null;
if ($fechavenc === '') $fechavenc = null;

$id_producto    = $_POST['id_producto']     ?? [];
$cantidad       = $_POST['cantidad']        ?? [];
$precio_unit    = $_POST['precio_unitario'] ?? [];

if ($numero_pedido <= 0 || $id_proveedor <= 0) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"Faltan numero_pedido o id_proveedor"]);
  exit;
}
if (!is_array($id_producto) || count($id_producto) === 0) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"Detalle vacío"]);
  exit;
}

// ⚠️ Importante: YA NO validamos contra "pendiente" aquí.
// Se permite registrar cualquier cantidad en presupuestos.
// El pendiente se consumirá recién cuando se APRUEBE el presupuesto.

// Insertar cabecera + detalle en transacción
pg_query($conn, "BEGIN");

$insCab = "
  INSERT INTO public.presupuestos
    (numero_pedido, id_proveedor, fecharegistro, fechavencimiento, estado)
  VALUES
    ($1, $2, $3, $4, 'Registrado')
  RETURNING id_presupuesto
";
$resCab = pg_query_params($conn, $insCab, [$numero_pedido, $id_proveedor, $fecharegistro, $fechavenc]);

if (!$resCab) {
  pg_query($conn, "ROLLBACK");
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>"No se pudo insertar cabecera"]);
  exit;
}
$id_presupuesto = (int)pg_fetch_result($resCab, 0, 0);

// Preparar INSERT de detalle con los campos nuevos
$insDet = "
  INSERT INTO public.presupuesto_detalle
    (id_presupuesto, id_producto, cantidad, precio_unitario, id_detalle_pedido, estado_detalle, cantidad_aprobada)
  VALUES ($1, $2, $3, $4, $5, 'Propuesto', 0)
";

$N = count($id_producto);
for ($i=0; $i<$N; $i++) {
  $pid = (int)$id_producto[$i];
  $qty = (int)$cantidad[$i];
  $prc = (float)$precio_unit[$i];

  if ($pid <= 0 || $qty <= 0 || $prc < 0) {
    pg_query($conn, "ROLLBACK");
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"Línea inválida (producto/cantidad/precio)"]);
    exit;
  }

  // Buscar id de la línea del pedido (enlaza presupuesto_detalle con detalle_pedido_interno)
  $findDet = pg_query_params($conn, "
    SELECT id FROM public.detalle_pedido_interno
    WHERE numero_pedido = $1 AND id_producto = $2
    LIMIT 1
  ", [$numero_pedido, $pid]);

  $id_detalle_pedido = null;
  if ($findDet && pg_num_rows($findDet) > 0) {
    $id_detalle_pedido = (int)pg_fetch_result($findDet, 0, 0);
  }

  $resDet = pg_query_params($conn, $insDet, [$id_presupuesto, $pid, $qty, $prc, $id_detalle_pedido]);
  if (!$resDet) {
    pg_query($conn, "ROLLBACK");
    http_response_code(500);
    echo json_encode(["ok"=>false,"error"=>"No se pudo insertar detalle"]);
    exit;
  }
}

pg_query($conn, "COMMIT");
echo json_encode(["ok"=>true, "id_presupuesto"=>$id_presupuesto], JSON_UNESCAPED_UNICODE);
