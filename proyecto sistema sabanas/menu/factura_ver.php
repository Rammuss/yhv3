<?php
// factura_ver.php  (versión que DESGLOSA cuando el precio ya incluye IVA)
header('Content-Type: application/json; charset=utf-8');
$response = ['ok' => false];

try {
  require __DIR__ . '/../conexion/configv2.php';

  if (!isset($_GET['id_factura']) || !ctype_digit($_GET['id_factura'])) {
    throw new Exception('Parámetro id_factura inválido');
  }
  $id = (int) $_GET['id_factura'];

  // --- CONFIG ---
  // true  => los precios unitarios y subtotales de detalle YA incluyen IVA (compras usual en PY)
  // false => los precios unitarios están sin IVA y aquí se suma el impuesto
  $preciosIncluyenIVA = true;

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
           f.timbrado_numero,
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

  // DETALLES (en tu BD: d.subtotal = cantidad * precio_unitario)
  $sqlDet = "
    SELECT d.id_factura_det,
           d.id_producto,
           pr.nombre AS producto,
           d.cantidad,
           d.precio_unitario,
           d.subtotal AS total_linea_raw,  -- cantidad * precio_unitario (según lo guardado)
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
  $baseTotal = 0.0;       // suma de bases imponibles + exentas
  $iva10 = 0.0; 
  $iva5  = 0.0; 
  $exentaBase = 0.0;
  $granTotal = 0.0;

  while ($row = pg_fetch_assoc($rdet)) {
    $ivaR   = (float) $row['iva'];               // 10 / 5 / 0
    $cant   = (float) $row['cantidad'];
    $pu     = (float) $row['precio_unitario'];

    // total_linea según lo guardado
    $totalLineaGuardado = (float) $row['total_linea_raw'];

    // Normalizamos por si hubiera pequeñas discrepancias: cantidad * precio_unitario
    $totalCalculado = $cant * $pu;

    // Preferimos el total calculado (evita arrastres).
    $totalLinea = $totalCalculado;

    $baseLinea = 0.0;
    $ivaLinea  = 0.0;

    if ($preciosIncluyenIVA) {
      // DESGLOSE: el total de la línea ya incluye IVA
      if ($ivaR === 10.0) {
        $ivaLinea  = $totalLinea / 11;          // 10% incluido
        $baseLinea = $totalLinea - $ivaLinea;   // = total * 10/11
        $iva10    += $ivaLinea;
      } elseif ($ivaR === 5.0) {
        $ivaLinea  = $totalLinea / 21;          // 5% incluido
        $baseLinea = $totalLinea - $ivaLinea;   // = total * 20/21
        $iva5     += $ivaLinea;
      } else {
        $baseLinea = $totalLinea;               // exentas
        $exentaBase += $baseLinea;
      }
      $granTotal += $totalLinea;                // ya viene con IVA (o exenta)
      $baseTotal += $baseLinea;                 // suma de bases (incluye exentas)
    } else {
      // SUMA: el precio unitario está sin IVA y aquí se agrega
      if ($ivaR === 10.0) {
        $ivaLinea  = $totalLinea * 0.10;
        $baseLinea = $totalLinea;
        $iva10    += $ivaLinea;
        $granTotal += ($baseLinea + $ivaLinea);
      } elseif ($ivaR === 5.0) {
        $ivaLinea  = $totalLinea * 0.05;
        $baseLinea = $totalLinea;
        $iva5     += $ivaLinea;
        $granTotal += ($baseLinea + $ivaLinea);
      } else {
        $baseLinea = $totalLinea; // exenta
        $exentaBase += $baseLinea;
        $granTotal += $baseLinea;
      }
      $baseTotal += $baseLinea;
    }

    $detalles[] = [
      'id_factura_det'      => (int) $row['id_factura_det'],
      'id_producto'         => (int) $row['id_producto'],
      'producto'            => $row['producto'],
      'cantidad'            => $cant,
      'precio_unitario'     => $pu,              // tal como fue guardado
      'iva'                 => $ivaR,            // 10 / 5 / 0
      'base_linea'          => round($baseLinea, 2),
      'iva_linea'           => round($ivaLinea, 2),
      'total_linea'         => round($preciosIncluyenIVA ? $totalLinea : ($baseLinea + $ivaLinea), 2),
    ];
  }

  // Totales
  $response['cabecera'] = [
    'id_factura'       => (int)$cab['id_factura'],
    'id_proveedor'     => (int)$cab['id_proveedor'],
    'proveedor'        => $cab['proveedor'],
    'ruc'              => $cab['ruc'],
    'telefono'         => $cab['telefono'],
    'fecha_emision'    => $cab['fecha_emision'],
    'numero_documento' => $cab['numero_documento'],
    'timbrado_numero'  => $cab['timbrado_numero'],
    'estado'           => $cab['estado'],
    'observacion'      => $cab['observacion'],
    'total_factura'    => isset($cab['total_factura']) ? (float)$cab['total_factura'] : null,
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
    // Base imponible total (incluye exentas)
    'subtotal'   => round($baseTotal, 2),
    'iva10'      => round($iva10, 2),
    'iva5'       => round($iva5, 2),
    'iva_exenta' => round($exentaBase, 2),  // base exenta
    'total'      => round($granTotal, 2),
    'modo_calculo' => $preciosIncluyenIVA ? 'desglose_desde_precio_con_iva' : 'suma_iva_a_precio_neto'
  ];

  $response['ok'] = true;
  echo json_encode($response);
  exit;

} catch (Exception $e) {
  $response['error'] = $e->getMessage();
  echo json_encode($response);
}
