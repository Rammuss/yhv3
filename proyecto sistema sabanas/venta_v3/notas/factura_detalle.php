<?php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try {
  if (empty($_SESSION['nombre_usuario'])) { throw new Exception('No autenticado'); }
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id<=0) throw new Exception('id inválido');

  // Cabecera + cliente + timbrado
  $sqlCab = "
    SELECT f.id_factura, f.numero_documento, f.fecha_emision, f.condicion_venta, f.estado,
           f.total_grav10, f.total_iva10, f.total_grav5, f.total_iva5, f.total_exentas,
           f.total_factura, f.total_bruto, f.total_descuento, f.total_iva, f.total_neto,
           f.id_timbrado, (t.establecimiento||'-'||t.punto_expedicion) AS ppp, t.numero_timbrado,
           c.id_cliente, (c.nombre||' '||c.apellido) AS cliente, c.ruc_ci
    FROM public.factura_venta_cab f
    JOIN public.clientes c ON c.id_cliente = f.id_cliente
    LEFT JOIN public.timbrado t ON t.id_timbrado = f.id_timbrado
    WHERE f.id_factura = $1
    LIMIT 1
  ";
  $rCab = pg_query_params($conn, $sqlCab, [$id]);
  if (!$rCab || pg_num_rows($rCab)===0) throw new Exception('Factura no encontrada');
  $cab = pg_fetch_assoc($rCab);

  // Detalle
  $sqlDet = "
    SELECT d.id_factura_det, d.id_producto, d.descripcion, d.unidad,
           d.cantidad, d.precio_unitario, d.tipo_iva, d.iva_monto, d.subtotal_neto
    FROM public.factura_venta_det d
    WHERE d.id_factura = $1
    ORDER BY d.id_factura_det
  ";
  $rDet = pg_query_params($conn, $sqlDet, [$id]);
  $detalle = [];
  while ($rDet && ($row = pg_fetch_assoc($rDet))) {
    $detalle[] = [
      'id_factura_det' => (int)$row['id_factura_det'],
      'id_producto'    => $row['id_producto'] ? (int)$row['id_producto'] : null,
      'descripcion'    => $row['descripcion'],
      'unidad'         => $row['unidad'],
      'cantidad'       => (float)$row['cantidad'],
      'precio_unitario'=> (float)$row['precio_unitario'],
      'tipo_iva'       => $row['tipo_iva'],
      'iva_monto'      => (float)$row['iva_monto'],
      'subtotal_neto'  => (float)$row['subtotal_neto']
    ];
  }

  // Historial de notas asociadas a la factura
  $sqlNotas = "
    SELECT 'NC' AS clase, n.id_nc AS id, n.numero_documento, n.fecha_emision, n.estado, n.total_neto
    FROM public.nc_venta_cab n
    WHERE n.id_factura = $1
    UNION ALL
    SELECT 'ND' AS clase, d.id_nd AS id, d.numero_documento, d.fecha_emision, d.estado, d.total_neto
    FROM public.nd_venta_cab d
    WHERE d.id_factura = $1
    ORDER BY fecha_emision DESC, clase
  ";
  $rNotas = pg_query_params($conn, $sqlNotas, [$id]);
  $notas = [];
  while ($rNotas && ($n = pg_fetch_assoc($rNotas))) {
    $notas[] = [
      'clase'            => $n['clase'],
      'id'               => (int)$n['id'],
      'numero_documento' => $n['numero_documento'],
      'fecha_emision'    => $n['fecha_emision'],
      'estado'           => $n['estado'],
      'total_neto'       => (float)$n['total_neto']
    ];
  }

  echo json_encode([
    'success'=>true,
    'data'=>[
      'id_factura'       => (int)$cab['id_factura'],
      'numero_documento' => $cab['numero_documento'],
      'fecha_emision'    => $cab['fecha_emision'],
      'condicion_venta'  => $cab['condicion_venta'],
      'estado'           => $cab['estado'],
      'total_grav10'     => (float)$cab['total_grav10'],
      'total_iva10'      => (float)$cab['total_iva10'],
      'total_grav5'      => (float)$cab['total_grav5'],
      'total_iva5'       => (float)$cab['total_iva5'],
      'total_exentas'    => (float)$cab['total_exentas'],
      'total_factura'    => (float)$cab['total_factura'],
      'total_bruto'      => (float)$cab['total_bruto'],
      'total_descuento'  => (float)$cab['total_descuento'],
      'total_iva'        => (float)$cab['total_iva'],
      'total_neto'       => (float)$cab['total_neto'],
      'id_timbrado'      => $cab['id_timbrado'] ? (int)$cab['id_timbrado'] : null,
      'ppp'              => $cab['ppp'] ?: null,
      'timbrado_numero'  => $cab['numero_timbrado'] ?: null,
      'id_cliente'       => (int)$cab['id_cliente'],
      'cliente'          => $cab['cliente'],
      'ruc_ci'           => $cab['ruc_ci'],
      'detalle'          => $detalle,
      'notas'            => $notas
    ]
  ]);

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
