<?php
// factura_ver.php
header('Content-Type: application/json; charset=utf-8');
$response = ['ok' => false];

try {
  require __DIR__ . '/../conexion/configv2.php';

  if (!isset($_GET['id_factura']) || !ctype_digit($_GET['id_factura'])) {
    throw new Exception('Parámetro id_factura inválido');
  }
  $id = (int) $_GET['id_factura'];

  // CABECERA (agregado: timbrado_numero)
  $sqlCab = "
    SELECT f.id_factura,
           f.id_proveedor,
           to_char(f.fecha_emision, 'YYYY-MM-DD') AS fecha_emision,
           f.numero_documento,
           f.estado,
           f.total_factura,
           f.observacion,
           f.condicion,
           f.cuotas,
           f.dias_plazo,
           f.intervalo_dias,
           f.moneda,
           f.id_sucursal,
           f.timbrado_numero,              -- << NUEVO
           s.nombre AS sucursal_nombre,
           p.nombre   AS proveedor,
           p.ruc,
           p.telefono
      FROM public.factura_compra_cab f
      JOIN public.proveedores p ON p.id_proveedor = f.id_proveedor
 LEFT JOIN public.sucursales  s ON s.id_sucursal  = f.id_sucursal
     WHERE f.id_factura = $1
     LIMIT 1;
  ";

  $rcab = pg_query_params($conn, $sqlCab, [$id]);
  if (!$rcab)  { throw new Exception('Error consultando cabecera: ' . pg_last_error($conn)); }
  if (pg_num_rows($rcab) === 0) { throw new Exception('No existe la factura'); }
  $cab = pg_fetch_assoc($rcab);

  // DETALLES (precio_unitario SIN IVA; d.subtotal = cant * pu)
  $sqlDet = "
    SELECT d.id_factura_det,
           d.id_producto,
           pr.nombre AS producto,
           d.cantidad,
           d.precio_unitario,
           d.subtotal AS total_linea,
           CASE
             WHEN upper(trim(COALESCE(NULLIF(d.tipo_iva,''), pr.tipo_iva))) LIKE '10%' THEN 10
             WHEN upper(trim(COALESCE(NULLIF(d.tipo_iva,''), pr.tipo_iva))) LIKE '5%'  THEN 5
             ELSE 0
           END AS iva
      FROM public.factura_compra_det d
      JOIN public.producto pr ON pr.id_producto = d.id_producto
     WHERE d.id_factura = $1
     ORDER BY d.id_factura_det;
  ";
  $rdet = pg_query_params($conn, $sqlDet, [$id]);
  if (!$rdet) { throw new Exception('Error consultando detalles: ' . pg_last_error($conn)); }

  $detalles = [];
  $subtotal = 0.0;
  $iva10 = 0.0; $iva5 = 0.0; $exenta = 0.0;

  while ($row = pg_fetch_assoc($rdet)) {
    $ivaR       = (float) $row['iva'];          // 10 / 5 / 0
    $baseLinea  = (float) $row['total_linea'];  // SIN IVA (d.subtotal = cant * pu)
    $impuesto   = 0.0;
    $totalBruto = $baseLinea;

    if ($ivaR === 10.0) {
      $impuesto   = $baseLinea * 0.10;
      $iva10     += $impuesto;
      $subtotal  += $baseLinea;
      $totalBruto = $baseLinea + $impuesto;
    } elseif ($ivaR === 5.0) {
      $impuesto   = $baseLinea * 0.05;
      $iva5      += $impuesto;
      $subtotal  += $baseLinea;
      $totalBruto = $baseLinea + $impuesto;
    } else {
      $exenta    += $baseLinea;
      $subtotal  += $baseLinea;
    }

    $detalles[] = [
      'id_factura_det'  => (int) $row['id_factura_det'],
      'id_producto'     => (int) $row['id_producto'],
      'producto'        => $row['producto'],
      'cantidad'        => (float) $row['cantidad'],
      'precio_unitario' => (float) $row['precio_unitario'],
      'iva'             => $ivaR,
      'base_linea'      => $baseLinea,   // base sin IVA
      'total_linea'     => $totalBruto   // total con IVA
    ];
  }

  $total = $subtotal + $iva10 + $iva5; // exentas ya están en subtotal

  // Respuesta
  $response['cabecera'] = [
    'id_factura'       => (int)$cab['id_factura'],
    'id_proveedor'     => (int)$cab['id_proveedor'],
    'proveedor'        => $cab['proveedor'],
    'ruc'              => $cab['ruc'],
    'telefono'         => $cab['telefono'],
    'fecha_emision'    => $cab['fecha_emision'],
    'numero_documento' => $cab['numero_documento'],
    'timbrado_numero'  => $cab['timbrado_numero'],           // << NUEVO en la salida
    'estado'           => $cab['estado'],
    'observacion'      => $cab['observacion'],
    'total_factura'    => isset($cab['total_factura']) ? (float)$cab['total_factura'] : null,
    // nuevos:
    'condicion'        => $cab['condicion'],
    'cuotas'           => isset($cab['cuotas']) ? (int)$cab['cuotas'] : null,
    'dias_plazo'       => isset($cab['dias_plazo']) ? (int)$cab['dias_plazo'] : null,
    'intervalo_dias'   => isset($cab['intervalo_dias']) ? (int)$cab['intervalo_dias'] : null,
    'moneda'           => $cab['moneda'],
    'id_sucursal'      => isset($cab['id_sucursal']) ? (int)$cab['id_sucursal'] : null,
    'sucursal_nombre'  => $cab['sucursal_nombre']
  ];

  $response['detalles'] = $detalles;
  $response['totales']  = [
    'subtotal'   => round($subtotal, 2),
    'iva10'      => round($iva10, 2),
    'iva5'       => round($iva5, 2),
    'iva_exenta' => round($exenta, 2),
    'total'      => round($total, 2),
  ];
  $response['ok'] = true;

  echo json_encode($response);
  exit;

} catch (Exception $e) {
  $response['error'] = $e->getMessage();
  echo json_encode($response);
}
