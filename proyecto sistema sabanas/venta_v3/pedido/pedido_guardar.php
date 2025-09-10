<?php
// === pedido_guardar.php (Alta de Pedido + Reserva + fecha_pedido) ===

session_start();
require_once __DIR__ . '/../../conexion/configv2.php'; // $conn (pg_connect)

header('Content-Type: application/json; charset=utf-8');

// Validar sesión
if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['success'=>false,'error'=>'No autorizado']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit;
}

// ---------- Helpers ----------
function parse_num($v) {
  if ($v === null || $v === '') return 0.0;
  $v = str_replace([' ', "\xc2\xa0"], '', $v);
  if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
    $v = str_replace('.', '', $v); $v = str_replace(',', '.', $v);
  } else { if (strpos($v, ',') !== false) $v = str_replace(',', '.', $v); }
  return (float)$v;
}
function iva_rate($tipo) { return $tipo==='10%'?0.10:($tipo==='5%'?0.05:0.0); }

/** Calcula stock disponible = físico (entradas - salidas) - reservas activas. */
function stock_disponible_php($conn, $id_producto) {
  $fis = pg_query_params($conn, "
    SELECT COALESCE(SUM(CASE LOWER(tipo_movimiento)
             WHEN 'entrada' THEN cantidad::numeric
             WHEN 'salida'  THEN -cantidad::numeric
             ELSE 0::numeric
           END),0)
    FROM public.movimiento_stock
    WHERE id_producto=$1
  ", [$id_producto]);
  $stock_fisico = $fis ? (float)pg_fetch_result($fis, 0, 0) : 0.0;

  $res = pg_query_params($conn, "
    SELECT COALESCE(SUM(cantidad)::numeric,0)
    FROM public.reserva_stock
    WHERE id_producto=$1 AND LOWER(estado)='activa'
  ", [$id_producto]);
  $reservado = $res ? (float)pg_fetch_result($res, 0, 0) : 0.0;

  return $stock_fisico - $reservado;
}

// ---------- Input ----------
$id_cliente    = (int)($_POST['id_cliente'] ?? 0);
$observacion   = trim($_POST['observacion'] ?? '');
$fecha_pedido  = trim($_POST['fecha_pedido'] ?? ''); // YYYY-MM-DD (UI)

$rawItems = $_POST['items'] ?? [];
$items = array_values(array_filter($rawItems, function($it) {
  return !empty($it['id_producto']) && parse_num($it['cantidad'] ?? 0) > 0;
}));

if ($id_cliente <= 0 || empty($items)) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Faltan datos: cliente o ítems.']); exit;
}

// Validar fecha (opcional; si es inválida, se ignora y se usa CURRENT_DATE)
$fecha_valida = null;
if ($fecha_pedido !== '') {
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_pedido)) {
    [$yy,$mm,$dd] = explode('-', $fecha_pedido);
    if (checkdate((int)$mm,(int)$dd,(int)$yy)) {
      $fecha_valida = $fecha_pedido; // OK
    }
  }
}

// Cliente existe
$cli = pg_query_params($conn, "SELECT 1 FROM public.clientes WHERE id_cliente=$1", [$id_cliente]);
if (!$cli || pg_num_rows($cli) === 0) {
  http_response_code(400); echo json_encode(['success'=>false,'error'=>'Cliente no existe.']); exit;
}

// ---------- Calcular líneas/totales ----------
$detalles  = [];
$tot_bruto = 0.0; $tot_desc = 0.0; $tot_iva = 0.0; $tot_neto = 0.0;

foreach ($items as $it) {
  $id_prod  = (int)$it['id_producto'];
  $cant     = parse_num($it['cantidad'] ?? 0);
  $precioIn = isset($it['precio_unitario']) ? parse_num($it['precio_unitario']) : null;
  $desc     = parse_num($it['descuento'] ?? 0);

  $pr = pg_query_params($conn, "
    SELECT id_producto, nombre, precio_unitario, estado, tipo_iva
    FROM public.producto WHERE id_producto=$1 LIMIT 1
  ", [$id_prod]);
  $row = $pr ? pg_fetch_assoc($pr) : null;
  if (!$row) { http_response_code(400); echo json_encode(['success'=>false,'error'=>"Producto $id_prod no existe."]); exit; }

  if (strtolower($row['estado'] ?? '') !== 'activo') {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>"Producto $id_prod no está activo."]); exit;
  }

  $tipo_iva = $row['tipo_iva'] ?: 'Exento';
  $precio   = ($precioIn !== null && $precioIn >= 0) ? $precioIn : (float)$row['precio_unitario'];
  if ($cant <= 0 || $precio < 0 || $desc < 0) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>"Cant/Precio/Desc inválidos ($id_prod)."]); exit;
  }

  $subtotal_bruto = round($cant * $precio, 2);
  $base           = max(0, $subtotal_bruto - $desc);
  $iva_monto      = round($base * iva_rate($tipo_iva), 2);
  $subtotal_neto  = round($base + $iva_monto, 2);

  $detalles[] = [
    'id_producto'     => $id_prod,
    'cantidad'        => $cant,
    'precio_unitario' => $precio,
    'descuento'       => $desc,
    'tipo_iva'        => $tipo_iva,
    'iva_monto'       => $iva_monto,
    'subtotal_bruto'  => $subtotal_bruto,
    'subtotal_neto'   => $subtotal_neto
  ];

  $tot_bruto += $subtotal_bruto;
  $tot_desc  += $desc;
  $tot_iva   += $iva_monto;
  $tot_neto  += $subtotal_neto;
}
$tot_bruto = round($tot_bruto, 2);
$tot_desc  = round($tot_desc, 2);
$tot_iva   = round($tot_iva, 2);
$tot_neto  = round($tot_neto, 2);

// ---------- Transacción ----------
pg_query($conn, "BEGIN");

try {
  $creado_por = $_SESSION['nombre_usuario'] ?? 'web';

  // Cabecera (incluye fecha_pedido)
  $cab = pg_query_params($conn, "
    INSERT INTO public.pedido_cab
      (id_cliente, fecha_pedido, estado, observacion, total_bruto, total_descuento, total_iva, total_neto, creado_por)
    VALUES ($1, COALESCE($2::date, CURRENT_DATE), 'pendiente', $3, $4, $5, $6, $7, $8)
    RETURNING id_pedido
  ", [
      $id_cliente,
      $fecha_valida,       // si es null, usa CURRENT_DATE
      $observacion,
      $tot_bruto, $tot_desc, $tot_iva, $tot_neto,
      $creado_por
  ]);

  if (!$cab) throw new Exception('No se pudo crear la cabecera del pedido.');
  $id_pedido = (int)pg_fetch_result($cab, 0, 0);

  // Detalle
  $stmtDet = "
    INSERT INTO public.pedido_det
      (id_pedido, id_producto, cantidad, precio_unitario, descuento, tipo_iva, iva_monto, subtotal_bruto, subtotal_neto)
    VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9)
  ";
  foreach ($detalles as $d) {
    $ok = pg_query_params($conn, $stmtDet, [
      $id_pedido, $d['id_producto'], $d['cantidad'], $d['precio_unitario'],
      $d['descuento'], $d['tipo_iva'], $d['iva_monto'], $d['subtotal_bruto'], $d['subtotal_neto']
    ]);
    if (!$ok) throw new Exception('No se pudo insertar un renglón del detalle.');
  }

  // Reservas
  foreach ($detalles as $d) {
    $id_prod = (int)$d['id_producto'];
    $qty     = (float)$d['cantidad'];

    // Bloqueo del producto (evita carreras)
    $lock = pg_query_params($conn, "SELECT 1 FROM public.producto WHERE id_producto=$1 FOR UPDATE", [$id_prod]);
    if (!$lock) throw new Exception("No se pudo bloquear producto $id_prod");

    // Disponible actual
    $disp = stock_disponible_php($conn, $id_prod);
    if ($qty > $disp) {
      throw new Exception("Sin stock para prod $id_prod. Disponible: $disp, solicitado: $qty");
    }

    // Upsert reserva (estado 'activa')
    $upd = pg_query_params($conn, "
      UPDATE public.reserva_stock
         SET cantidad = cantidad + $1, actualizado_en = now()
       WHERE id_pedido = $2 AND id_producto = $3 AND LOWER(estado)='activa'
       RETURNING id_reserva
    ", [$qty, $id_pedido, $id_prod]);

    if (!$upd || pg_affected_rows($upd) === 0) {
      $ins = pg_query_params($conn, "
        INSERT INTO public.reserva_stock (id_pedido, id_producto, cantidad, estado)
        VALUES ($1,$2,$3,'activa')
      ", [$id_pedido, $id_prod, $qty]);
      if (!$ins) throw new Exception("No se pudo reservar prod $id_prod");
    }
  }

  pg_query($conn, "COMMIT");
  echo json_encode([
    'success'   => true,
    'id_pedido' => $id_pedido,
    'message'   => 'Pedido guardado y reservado correctamente.'
  ]);
  exit;

} catch (Throwable $e) {
  pg_query($conn, "ROLLBACK");
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
  exit;
}
