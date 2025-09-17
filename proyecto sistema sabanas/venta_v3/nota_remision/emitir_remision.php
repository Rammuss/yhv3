<?php
// nota_remision/emitir_remision.php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

function is_iso_date($s){ return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$s); }
function is_hhmm($s){ return is_string($s) && preg_match('/^\d{2}:\d{2}$/',$s); }
function lpad7($n){ return str_pad((string)$n, 7, '0', STR_PAD_LEFT); }

// Para insertar detalle de forma compatible si faltan columnas opcionales
function has_col($conn, $schema, $table, $col){
  $r = pg_query_params($conn,
    "SELECT 1 FROM information_schema.columns WHERE table_schema=$1 AND table_name=$2 AND column_name=$3 LIMIT 1",
    [$schema,$table,$col]
  );
  return $r && pg_num_rows($r) > 0;
}

try{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit;
  }

  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;

  // ===== Payload =====
  $id_factura  = (int)($in['id_factura'] ?? 0);
  $id_cliente  = (int)($in['id_cliente'] ?? 0);
  $fecha       = trim($in['fecha_salida'] ?? date('Y-m-d')); // UI: campo "fecha"
  $hora        = trim($in['hora_salida']  ?? '');            // UI: campo "hora" (opcional)
  $origen      = trim($in['origen_dir']   ?? ($in['origen']  ?? ''));
  $destino     = trim($in['destino_dir']  ?? ($in['destino'] ?? ''));
  $ciudad_origen  = trim($in['ciudad_origen']  ?? '');
  $ciudad_destino = trim($in['ciudad_destino'] ?? '');
  $transportista  = trim($in['transportista']  ?? '');
  $chofer_nombre  = trim($in['chofer_nombre']  ?? '');
  $chofer_doc     = trim($in['chofer_ci']      ?? ''); // en tabla se llama chofer_doc
  $vehiculo_marca = trim($in['vehiculo_marca'] ?? '');
  $vehiculo_chapa = trim($in['vehiculo_chapa'] ?? '');
  $observacion    = trim($in['observacion']    ?? '');
  $motivo         = trim($in['motivo']         ?? ''); // tu tabla no tiene "motivo"; lo anexamos a la obs
  $items          = is_array($in['items'] ?? null) ? $in['items'] : [];

  if ($id_factura <= 0) throw new Exception('id_factura requerido');
  if ($id_cliente <= 0) throw new Exception('id_cliente requerido');
  if (!is_iso_date($fecha)) throw new Exception('fecha_salida inválida (YYYY-MM-DD)');
  if ($hora !== '' && !is_hhmm($hora)) throw new Exception('hora_salida inválida (HH:MM)');
  if (!$items) throw new Exception('Debe enviar al menos un ítem');

  // Componer timestamp de salida
  $fecha_salida_ts = $fecha . ' ' . ($hora !== '' ? $hora.':00' : '00:00:00');

  // ===== Validar pendientes por producto =====
  $rFact = pg_query_params($conn, "
    SELECT d.id_producto, SUM(d.cantidad)::numeric(14,3) AS facturado
    FROM public.factura_venta_det d
    JOIN public.producto p ON p.id_producto = d.id_producto
    WHERE d.id_factura = $1
      AND COALESCE(p.tipo_item,'P') = 'P'
    GROUP BY d.id_producto
  ", [$id_factura]);
  if (!$rFact) throw new Exception('No se pudo leer el detalle de la factura');

  $facturados = [];
  while($row = pg_fetch_assoc($rFact)){
    $facturados[(int)$row['id_producto']] = (float)$row['facturado'];
  }
  if (!$facturados) throw new Exception('La factura no posee ítems de producto');

  $rRem = pg_query_params($conn, "
    SELECT rd.id_producto, COALESCE(SUM(rd.cantidad),0)::numeric(14,3) AS remitido
    FROM public.remision_venta_det rd
    JOIN public.remision_venta_cab rc ON rc.id_remision_venta = rd.id_remision_venta
    WHERE rd.id_factura = $1
      AND COALESCE(LOWER(rc.estado),'') <> 'anulada'
    GROUP BY rd.id_producto
  ", [$id_factura]);
  if (!$rRem) throw new Exception('No se pudo leer remisiones previas');

  $remitidos = [];
  while($row = pg_fetch_assoc($rRem)){
    $remitidos[(int)$row['id_producto']] = (float)$row['remitido'];
  }

  $detOk = [];
  foreach ($items as $it){
    $id_prod = (int)($it['id_producto'] ?? 0);
    $cant    = (float)($it['cantidad'] ?? 0);
    $desc    = trim($it['descripcion'] ?? '');
    $uni     = trim($it['unidad'] ?? 'UNI');
    if ($id_prod <= 0 || $cant <= 0) continue;
    if (!isset($facturados[$id_prod])) throw new Exception("El producto $id_prod no existe en la factura");
    $fact = (float)$facturados[$id_prod];
    $rem  = (float)($remitidos[$id_prod] ?? 0);
    $pend = max(0.0, $fact - $rem);
    if ($cant - $pend > 0.0001) throw new Exception("Cantidad a remitir ($cant) supera el pendiente ($pend) para producto $id_prod");
    $detOk[] = [
      'id_producto'=>$id_prod,
      'descripcion'=>$desc,
      'unidad'=>$uni,
      'cantidad'=>round($cant,3)
    ];
  }
  if (!$detOk) throw new Exception('No hay cantidades > 0 para remitir');

  // Agregar motivo a la observación si vino
  if ($motivo !== '') {
    $observacion = ($observacion ? $observacion."\n" : '') . "Motivo: ".$motivo;
  }

  pg_query($conn, 'BEGIN');

  // ===== Cabecera =====
  $sqlCab = "
    INSERT INTO public.remision_venta_cab(
      fecha, id_factura, id_cliente,
      origen, destino, ciudad_origen, ciudad_destino,
      fecha_salida, -- timestamp
      chofer_nombre, chofer_doc,
      vehiculo_marca, vehiculo_chapa,
      transportista,
      estado, observacion
    ) VALUES (
      CURRENT_DATE, $1, $2,
      $3, $4, $5, $6,
      $7::timestamp,
      $8, $9,
      $10, $11,
      $12,
      'Emitida', $13
    )
    RETURNING id_remision_venta
  ";
  $rCab = pg_query_params($conn, $sqlCab, [
    $id_factura, $id_cliente,
    $origen, $destino, ($ciudad_origen ?: null), ($ciudad_destino ?: null),
    $fecha_salida_ts,
    ($chofer_nombre ?: null), ($chofer_doc ?: null),
    ($vehiculo_marca ?: null), ($vehiculo_chapa ?: null),
    ($transportista ?: null),
    ($observacion ?: null)
  ]);
  if (!$rCab) throw new Exception('No se pudo crear la cabecera de remisión');

  $id_remision = (int)pg_fetch_result($rCab, 0, 0);

  // Generar número de documento simple (ajustá a tu lógica de timbrado si corresponde)
  $numero_doc = 'NR-'.lpad7($id_remision);
  $okNum = pg_query_params($conn,
    "UPDATE public.remision_venta_cab SET numero_documento=$1 WHERE id_remision_venta=$2",
    [$numero_doc, $id_remision]
  );
  if (!$okNum) throw new Exception('No se pudo asignar el número de remisión');

  // ===== Detalle =====
  $schema='public'; $tDet='remision_venta_det';
  $has_tipo   = has_col($conn,$schema,$tDet,'tipo_item');
  $has_unidad = has_col($conn,$schema,$tDet,'unidad');

  $cols = "id_remision_venta, id_factura, id_producto, descripcion, cantidad";
  $vals = "$1, $2, $3, $4, $5";
  $args = function($d) use ($id_remision,$id_factura){ return [$id_remision, $id_factura, $d['id_producto'], $d['descripcion'], $d['cantidad']]; };

  if ($has_unidad) {
    $cols = "id_remision_venta, id_factura, id_producto, descripcion, unidad, cantidad";
    $vals = "$1, $2, $3, $4, $5, $6";
    $args = function($d) use ($id_remision,$id_factura){ return [$id_remision, $id_factura, $d['id_producto'], $d['descripcion'], $d['unidad'], $d['cantidad']]; };
  }
  if ($has_tipo && $has_unidad) {
    $cols = "id_remision_venta, id_factura, id_producto, tipo_item, descripcion, unidad, cantidad";
    $vals = "$1, $2, $3, 'P', $4, $5, $6";
    $args = function($d) use ($id_remision,$id_factura){ return [$id_remision, $id_factura, $d['id_producto'], $d['descripcion'], $d['unidad'], $d['cantidad']]; };
  } elseif ($has_tipo && !$has_unidad) {
    $cols = "id_remision_venta, id_factura, id_producto, tipo_item, descripcion, cantidad";
    $vals = "$1, $2, $3, 'P', $4, $5";
    $args = function($d) use ($id_remision,$id_factura){ return [$id_remision, $id_factura, $d['id_producto'], $d['descripcion'], $d['cantidad']]; };
  }

  $sqlDet = "INSERT INTO public.$tDet($cols) VALUES ($vals)";
  $stmt = pg_prepare($conn, "ins_det_rem", $sqlDet);
  if (!$stmt) throw new Exception('No se pudo preparar inserción de detalle');

  foreach ($detOk as $d){
    $ok = pg_execute($conn, "ins_det_rem", $args($d));
    if (!$ok) throw new Exception('No se pudo insertar un ítem de la remisión');
  }

  pg_query($conn, 'COMMIT');

  echo json_encode([
    'success'=>true,
    'id_remision'=>$id_remision,
    'numero_documento'=>$numero_doc
  ]);

}catch(Throwable $e){
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
