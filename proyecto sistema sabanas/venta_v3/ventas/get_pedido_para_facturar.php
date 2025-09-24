<?php
// get_pedido_para_facturar.php
// Trae cabecera + detalle del pedido para poblar la UI de facturación.
// GET: id_pedido
// Respuesta: { success, pedido:{...}, items:[...], totales:{...} }

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

// (Opcional) proteger por sesión
if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['success'=>false, 'error'=>'No autorizado']); exit;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit;
  }

  $id_pedido = isset($_GET['id_pedido']) ? (int)$_GET['id_pedido'] : 0;
  if ($id_pedido <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'id_pedido inválido']); exit;
  }

  // --- 1) Cabecera del pedido + cliente ---
  $sqlCab = "
    SELECT p.id_pedido, p.fecha_pedido, p.estado, p.observacion,
           p.total_bruto, p.total_descuento, p.total_iva, p.total_neto,
           c.id_cliente, c.nombre, c.apellido, c.ruc_ci, c.telefono, c.direccion
    FROM public.pedido_cab p
    JOIN public.clientes c ON c.id_cliente = p.id_cliente
    WHERE p.id_pedido = $1
  ";
  $stCab = pg_query_params($conn, $sqlCab, [$id_pedido]);
  if (!$stCab || pg_num_rows($stCab) === 0) {
    http_response_code(404);
    echo json_encode(['success'=>false,'error'=>'Pedido no encontrado']); exit;
  }
  $cab = pg_fetch_assoc($stCab);

  // Validar estado (no facturado / no anulado)
  $estado = strtolower(trim($cab['estado'] ?? ''));
  if (in_array($estado, ['anulado','facturado'], true)) {
    http_response_code(409);
    echo json_encode(['success'=>false,'error'=>'El pedido no está disponible para facturar (Anulado o ya Facturado).']); exit;
  }

  // --- 2) Detectar si existe la columna producto.tipo_item ---
  $hasTipoItem = false;
  $chk = pg_query_params(
    $conn,
    "SELECT 1
     FROM information_schema.columns
     WHERE table_schema='public'
       AND table_name='producto'
       AND column_name='tipo_item'
     LIMIT 1", []
  );
  if ($chk && pg_num_rows($chk) > 0) $hasTipoItem = true;

  // --- 3) Detalle del pedido (con tipo_item robusto) ---
  if ($hasTipoItem) {
    $sqlDet = "
      SELECT d.id_pedido_det, d.id_producto,
             pr.nombre AS descripcion,
             COALESCE(pr.tipo_item,'P') AS tipo_item,     -- 'P' o 'S'
             'UNI'::varchar(10) AS unidad,
             d.cantidad::numeric(14,3)        AS cantidad,
             d.precio_unitario::numeric(14,2) AS precio_unitario,
             d.tipo_iva,
             d.descuento::numeric(14,2)       AS descuento,
             d.iva_monto::numeric(14,2)       AS iva_monto,
             d.subtotal_neto::numeric(14,2)   AS subtotal_neto
      FROM public.pedido_det d
      JOIN public.producto pr ON pr.id_producto = d.id_producto
      WHERE d.id_pedido = $1
      ORDER BY d.id_pedido_det
    ";
  } else {
    // Sin columna tipo_item: devolvemos siempre 'P'
    $sqlDet = "
      SELECT d.id_pedido_det, d.id_producto,
             pr.nombre AS descripcion,
             'P' AS tipo_item,                              -- fallback
             'UNI'::varchar(10) AS unidad,
             d.cantidad::numeric(14,3)        AS cantidad,
             d.precio_unitario::numeric(14,2) AS precio_unitario,
             d.tipo_iva,
             d.descuento::numeric(14,2)       AS descuento,
             d.iva_monto::numeric(14,2)       AS iva_monto,
             d.subtotal_neto::numeric(14,2)   AS subtotal_neto
      FROM public.pedido_det d
      JOIN public.producto pr ON pr.id_producto = d.id_producto
      WHERE d.id_pedido = $1
      ORDER BY d.id_pedido_det
    ";
  }

  $stDet = pg_query_params($conn, $sqlDet, [$id_pedido]);
  if (!$stDet) { throw new Exception('No se pudo obtener el detalle del pedido'); }

  // --- 4) Construir items y totales NORMALIZADOS ---
  $items = [];
  $g10_base = 0.0;  // base 10% sin IVA
  $i10      = 0.0;  // IVA 10%
  $g5_base  = 0.0;  // base 5% sin IVA
  $i5       = 0.0;  // IVA 5%
  $ex_base  = 0.0;  // exentas
  $total_visible = 0.0; // total con IVA (lo que ve el cliente)

  while ($r = pg_fetch_assoc($stDet)) {
    // Item para la UI (tal cual DB)
    $itm = [
      'id_pedido_det'   => (int)$r['id_pedido_det'],
      'id_producto'     => (int)$r['id_producto'],
      'descripcion'     => $r['descripcion'],
      'tipo_item'       => $r['tipo_item'],               // 'P' o 'S'
      'unidad'          => $r['unidad'],
      'cantidad'        => (float)$r['cantidad'],
      'precio_unitario' => (float)$r['precio_unitario'],
      'tipo_iva'        => $r['tipo_iva'],                // '10%' | '5%' | 'Exento'
      'descuento'       => (float)$r['descuento'],
      'iva_monto'       => (float)$r['iva_monto'],
      'subtotal_neto'   => (float)$r['subtotal_neto'],
    ];
    $items[] = $itm;

    // --- Normalización por línea para totales ---
    $tiva = strtoupper(trim($itm['tipo_iva'])); // '10%' | '5%' | 'EXENTO'
    // Importe visible (si no llega, lo calculo como cant*pu - desc)
    $importe = $itm['subtotal_neto'] > 0
             ? $itm['subtotal_neto']
             : max(0.0, $itm['cantidad'] * $itm['precio_unitario'] - $itm['descuento']);

    // IVA de la línea: uso el enviado si es >0, si no descompongo por /11 o /21
    if ($tiva === '10%' || $tiva === '10') {
      $iva_calc = ($itm['iva_monto'] > 0) ? round($itm['iva_monto'], 2)
                                          : round($importe / 11.0, 2);
      $base = $importe - $iva_calc;
      $g10_base += $base;
      $i10      += $iva_calc;
    } elseif ($tiva === '5%' || $tiva === '5') {
      $iva_calc = ($itm['iva_monto'] > 0) ? round($itm['iva_monto'], 2)
                                          : round($importe / 21.0, 2);
      $base = $importe - $iva_calc;
      $g5_base += $base;
      $i5      += $iva_calc;
    } else {
      // Exento
      $base = $importe;
      $ex_base += $base;
    }

    // El total visible YA incluye el IVA -> no volver a sumarlo
    $total_visible += $importe;
  }

  $totales = [
    'total_grav10'  => round($g10_base, 2),
    'total_iva10'   => round($i10, 2),
    'total_grav5'   => round($g5_base, 2),
    'total_iva5'    => round($i5, 2),
    'total_exentas' => round($ex_base, 2),
    'total_factura' => round($total_visible, 2)
  ];

  echo json_encode([
    'success' => true,
    'pedido'  => [
      'id_pedido'     => (int)$cab['id_pedido'],
      'fecha_pedido'  => $cab['fecha_pedido'],
      'estado'        => $cab['estado'],
      'observacion'   => $cab['observacion'],
      'totales_pedido'=> [
        'total_bruto'      => (float)$cab['total_bruto'],
        'total_descuento'  => (float)$cab['total_descuento'],
        'total_iva'        => (float)$cab['total_iva'],
        'total_neto'       => (float)$cab['total_neto'],
      ],
      'cliente'       => [
        'id_cliente'      => (int)$cab['id_cliente'],
        'nombre'          => $cab['nombre'],
        'apellido'        => $cab['apellido'],
        'ruc_ci'          => $cab['ruc_ci'],
        'telefono'        => $cab['telefono'],
        'direccion'       => $cab['direccion']
      ]
    ],
    'items'   => $items,
    'totales' => $totales
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
