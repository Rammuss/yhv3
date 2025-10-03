<?php
// get_pedido_para_facturar.php
// Trae cabecera + detalle del pedido para la UI de facturación.

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

function normalizar_tipo_iva_codigo_local($tipo){
  $tipo = strtoupper(trim((string)$tipo));
  if ($tipo === 'IVA10' || $tipo === 'IVA 10' || $tipo === '10%' || $tipo === '10' || strpos($tipo,'10')!==false) return 'IVA10';
  if ($tipo === 'IVA5'  || $tipo === 'IVA 5'  || $tipo === '5%'  || $tipo === '5'  || strpos($tipo,'5') !==false) return 'IVA5';
  return 'EXE';
}
function tasa_iva_local($tipo){
  $tipo = normalizar_tipo_iva_codigo_local($tipo);
  if ($tipo==='IVA10') return 0.10;
  if ($tipo==='IVA5')  return 0.05;
  return 0.0;
}

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

  $estado = strtolower(trim($cab['estado'] ?? ''));
  if (in_array($estado, ['anulado','facturado'], true)) {
    http_response_code(409);
    echo json_encode(['success'=>false,'error'=>'El pedido no está disponible para facturar (Anulado o ya Facturado).']); exit;
  }

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

  if ($hasTipoItem) {
    $sqlDet = "
      SELECT d.id_pedido_det, d.id_producto,
             pr.nombre AS descripcion,
             COALESCE(pr.tipo_item,'P') AS tipo_item,
             'UNI'::varchar(10) AS unidad,
             d.cantidad::numeric(14,3)        AS cantidad,
             d.precio_unitario::numeric(14,2) AS precio_unitario,
             d.tipo_iva,
             d.descuento::numeric(14,2)       AS descuento
      FROM public.pedido_det d
      JOIN public.producto pr ON pr.id_producto = d.id_producto
      WHERE d.id_pedido = $1
      ORDER BY d.id_pedido_det
    ";
  } else {
    $sqlDet = "
      SELECT d.id_pedido_det, d.id_producto,
             pr.nombre AS descripcion,
             'P' AS tipo_item,
             'UNI'::varchar(10) AS unidad,
             d.cantidad::numeric(14,3)        AS cantidad,
             d.precio_unitario::numeric(14,2) AS precio_unitario,
             d.tipo_iva,
             d.descuento::numeric(14,2)       AS descuento
      FROM public.pedido_det d
      JOIN public.producto pr ON pr.id_producto = d.id_producto
      WHERE d.id_pedido = $1
      ORDER BY d.id_pedido_det
    ";
  }

  $stDet = pg_query_params($conn, $sqlDet, [$id_pedido]);
  if (!$stDet) { throw new Exception('No se pudo obtener el detalle del pedido'); }

  $items = [];
  $g10_base = 0.0;
  $i10      = 0.0;
  $g5_base  = 0.0;
  $i5       = 0.0;
  $ex_base  = 0.0;
  $total_visible = 0.0;

 while ($r = pg_fetch_assoc($stDet)) {
  $tipoIva = normalizar_tipo_iva_codigo_local($r['tipo_iva'] ?? '');
  $rate    = tasa_iva_local($tipoIva);

  $cantidad    = (float)$r['cantidad'];
  $precio      = (float)$r['precio_unitario']; // con IVA
  $descRaw     = (float)$r['descuento'];

  $importeBruto = $cantidad * $precio;         // con IVA
  $descuento    = ($descRaw > 0 && $importeBruto > 0)
                    ? min($descRaw, $importeBruto)
                    : 0.0;
  $importeFinal = $importeBruto - $descuento;  // permite negativos

  if ($rate > 0) {
    $base = round($importeFinal / (1 + $rate), 2);
    $iva  = round($importeFinal - $base, 2);
  } else {
    $base = round($importeFinal, 2);
    $iva  = 0.0;
  }

  $items[] = [
    'id_pedido_det'   => (int)$r['id_pedido_det'],
    'id_producto'     => (int)$r['id_producto'],
    'descripcion'     => $r['descripcion'],
    'tipo_item'       => $r['tipo_item'],
    'unidad'          => $r['unidad'],
    'cantidad'        => $cantidad,
    'precio_unitario' => $precio,
    'tipo_iva'        => $tipoIva,
    'descuento'       => $descuento,
    'iva_monto'       => $iva,
    'subtotal_neto'   => round($importeFinal, 2)
  ];

  if ($tipoIva === 'IVA10') { $g10_base += $base; $i10 += $iva; }
  elseif ($tipoIva === 'IVA5') { $g5_base += $base; $i5 += $iva; }
  else { $ex_base += $base; }

  $total_visible += $importeFinal;
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
