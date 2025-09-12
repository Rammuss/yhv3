<?php
// facturar_pedido.php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

function lpad7($n){ return str_pad((string)$n, 7, '0', STR_PAD_LEFT); }
function is_iso_date($s){ return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$s); }

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit;
  }

  // .... arriba quedan los requires/session/headers y helpers

$in = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$id_pedido  = isset($in['id_pedido']) ? (int)$in['id_pedido'] : 0;
$condicion  = isset($in['condicion_venta']) ? trim($in['condicion_venta']) : '';
$medioPago  = isset($in['medio_pago']) ? trim($in['medio_pago']) : null;
$refPago    = isset($in['referencia_pago']) ? trim($in['referencia_pago']) : null;
$vto        = isset($in['fecha_vencimiento']) ? trim($in['fecha_vencimiento']) : null;
$obs        = isset($in['observacion']) ? trim($in['observacion']) : null;
$fecha_emision = (isset($in['fecha_emision']) && is_iso_date($in['fecha_emision']))
                 ? $in['fecha_emision'] : date('Y-m-d');

// NUEVO: leer plan de cuotas (si viene desde la UI)
$plan = (isset($in['plan_cuotas']) && is_array($in['plan_cuotas'])) ? $in['plan_cuotas'] : [];

if ($id_pedido <= 0) { throw new Exception('id_pedido inválido'); }
if (!in_array($condicion, ['Contado','Credito'], true)) { throw new Exception('condicion_venta inválida'); }

if ($condicion === 'Contado') {
  if (empty($medioPago)) { throw new Exception('medio_pago es requerido para Contado'); }
} else {
  // CREDITO:
  if (count($plan) > 0) {
    // Validación mínima del plan (opcional pero recomendado)
    foreach ($plan as $i => $c) {
      if (empty($c['vencimiento']) || !is_iso_date($c['vencimiento'])) {
        throw new Exception("Plan de cuotas: vencimiento inválido en cuota ".($i+1));
      }
      if (!isset($c['nro']) || !isset($c['total'])) {
        throw new Exception("Plan de cuotas: datos incompletos (nro/total) en cuota ".($i+1));
      }
      if ((float)$c['total'] <= 0) {
        throw new Exception("Plan de cuotas: total de cuota inválido en cuota ".($i+1));
      }
    }
    // Si hay plan, NO exigimos $vto
    $vto = null;
  } else {
    // Modo simple: exigir fecha_vencimiento única
    if (empty($vto) || !is_iso_date($vto)) {
      throw new Exception('fecha_vencimiento requerida (YYYY-MM-DD) para Crédito');
    }
    if ($vto < $fecha_emision) { throw new Exception('La fecha de vencimiento no puede ser anterior a la emisión'); }
  }
}

// ... y el resto del archivo queda como lo tenías


  // TX
  pg_query($conn, 'BEGIN');

  // 1) Pedido + Cliente (lock)
  $sqlPed = "
    SELECT p.id_pedido, p.estado, p.observacion, p.id_cliente,
           c.nombre, c.apellido, c.ruc_ci
    FROM public.pedido_cab p
    JOIN public.clientes c ON c.id_cliente = p.id_cliente
    WHERE p.id_pedido = $1
    FOR UPDATE
  ";
  $rPed = pg_query_params($conn, $sqlPed, [$id_pedido]);
  if (!$rPed || pg_num_rows($rPed) === 0) { throw new Exception('Pedido no encontrado'); }
  $ped = pg_fetch_assoc($rPed);
  if (in_array($ped['estado'], ['Anulado','Facturado'], true)) {
    throw new Exception('El pedido no está disponible para facturar (Anulado o ya Facturado)');
  }
  $id_cliente = (int)$ped['id_cliente'];

  // 2) Totales desde pedido_det
  $sqlTot = "
    WITH base AS (
      SELECT
        CASE WHEN d.tipo_iva = '10%' THEN d.subtotal_neto ELSE 0 END AS grav10,
        CASE WHEN d.tipo_iva = '10%' THEN d.iva_monto     ELSE 0 END AS iva10,
        CASE WHEN d.tipo_iva = '5%'  THEN d.subtotal_neto ELSE 0 END AS grav5,
        CASE WHEN d.tipo_iva = '5%'  THEN d.iva_monto     ELSE 0 END AS iva5,
        CASE WHEN d.tipo_iva = 'Exento' THEN d.subtotal_neto ELSE 0 END AS exentas
      FROM public.pedido_det d
      WHERE d.id_pedido = $1
    )
    SELECT
      COALESCE(SUM(grav10),0)::numeric(14,2)  AS total_grav10,
      COALESCE(SUM(iva10),0)::numeric(14,2)   AS total_iva10,
      COALESCE(SUM(grav5),0)::numeric(14,2)   AS total_grav5,
      COALESCE(SUM(iva5),0)::numeric(14,2)    AS total_iva5,
      COALESCE(SUM(exentas),0)::numeric(14,2) AS total_exentas,
      (COALESCE(SUM(grav10),0)+COALESCE(SUM(iva10),0)+COALESCE(SUM(grav5),0)+COALESCE(SUM(iva5),0)+COALESCE(SUM(exentas),0))::numeric(14,2) AS total_factura
    FROM base;
  ";
  $rTot = pg_query_params($conn, $sqlTot, [$id_pedido]);
  $tot = pg_fetch_assoc($rTot);
  $grav10  = (float)$tot['total_grav10'];
  $iva10   = (float)$tot['total_iva10'];
  $grav5   = (float)$tot['total_grav5'];
  $iva5    = (float)$tot['total_iva5'];
  $exentas = (float)$tot['total_exentas'];
  $total   = (float)$tot['total_factura'];
  if ($total <= 0) { throw new Exception('Total de factura inválido (ver detalle del pedido)'); }

  // Totales cabecera en PHP
  $total_bruto      = $grav10 + $grav5 + $exentas;
  $total_descuento  = 0.0;
  $total_neto       = $total;

  // 3) Timbrado vigente para la FECHA EMISIÓN
  $sqlTim = "
    SELECT id_timbrado, numero_timbrado, establecimiento, punto_expedicion, nro_actual, nro_hasta,
           fecha_inicio, fecha_fin
    FROM public.timbrado
    WHERE tipo_comprobante='Factura'
      AND estado='Vigente'
      AND $1::date BETWEEN fecha_inicio AND fecha_fin
    ORDER BY id_timbrado
    FOR UPDATE
    LIMIT 1
  ";
  $rTim = pg_query_params($conn, $sqlTim, [$fecha_emision]);
  if (!$rTim || pg_num_rows($rTim) === 0) { throw new Exception('No hay timbrado vigente para la fecha de emisión'); }
  $tim = pg_fetch_assoc($rTim);
  $id_timbrado = (int)$tim['id_timbrado'];
  $nro_actual  = (int)$tim['nro_actual'];
  $nro_hasta   = (int)$tim['nro_hasta'];
  if ($nro_actual > $nro_hasta) { throw new Exception('Rango de timbrado agotado'); }

  $establec = $tim['establecimiento'];
  $punto    = $tim['punto_expedicion'];
  $num_tim  = $tim['numero_timbrado'];
  $numero_doc = $establec . '-' . $punto . '-' . lpad7($nro_actual);

  // 4) Validar reservas activas suficientes (6A)
  $sqlCheckReserva = "
    WITH req AS (
      SELECT d.id_producto, SUM(d.cantidad)::numeric(14,2) AS qty_requerida
      FROM public.pedido_det d
      WHERE d.id_pedido = $1
      GROUP BY d.id_producto
    ),
    res AS (
      SELECT r.id_producto, COALESCE(SUM(r.cantidad),0)::numeric(14,2) AS qty_reservada
      FROM public.reserva_stock r
      WHERE r.id_pedido = $1 AND lower(r.estado) = 'activa'
      GROUP BY r.id_producto
    )
    SELECT p.id_producto, p.nombre,
           req.qty_requerida,
           COALESCE(res.qty_reservada,0) AS qty_reservada
    FROM req
    LEFT JOIN res ON res.id_producto = req.id_producto
    JOIN public.producto p ON p.id_producto = req.id_producto
    WHERE COALESCE(res.qty_reservada,0) < req.qty_requerida
  ";
  $rCheck = pg_query_params($conn, $sqlCheckReserva, [$id_pedido]);
  if ($rCheck === false) { throw new Exception('No se pudo validar reservas'); }
  if (pg_num_rows($rCheck) > 0) {
    $faltantes = [];
    while ($row = pg_fetch_assoc($rCheck)) {
      $faltantes[] = $row['nombre']." (req: ".$row['qty_requerida'].", res: ".$row['qty_reservada'].")";
    }
    throw new Exception('Reserva insuficiente para: '.implode(', ', $faltantes));
  }

  // 5) Cabecera de FACTURA
  $sqlFacCab = "
    INSERT INTO public.factura_venta_cab(
      fecha_emision, id_cliente, condicion_venta,
      numero_documento, timbrado_numero,
      total_grav10, total_iva10, total_grav5, total_iva5, total_exentas,
      total_bruto, total_descuento, total_neto,
      estado, id_pedido, observacion, id_timbrado
    ) VALUES (
      $1, $2, $3,
      $4, $5,
      $6, $7, $8, $9, $10,
      $11, $12, $13,
      'Emitida', $14, $15, $16
    ) RETURNING id_factura
  ";
  $paramsCab = [
    $fecha_emision, $id_cliente, $condicion,
    $numero_doc, $num_tim,
    $grav10, $iva10, $grav5, $iva5, $exentas,
    $total_bruto, $total_descuento, $total_neto,
    $id_pedido, $obs, $id_timbrado
  ];
  $rCab = pg_query_params($conn, $sqlFacCab, $paramsCab);
  if (!$rCab) { throw new Exception('No se pudo crear la factura (cabecera)'); }
  $id_factura = (int)pg_fetch_result($rCab, 0, 0);

  // 6) Detalle
  $sqlDet = "
    INSERT INTO public.factura_venta_det(
      id_factura, id_producto, tipo_item, descripcion, unidad,
      cantidad, precio_unitario, tipo_iva, iva_monto, subtotal_neto
    )
    SELECT $1, d.id_producto, COALESCE(p.tipo_item,'P'), p.nombre, 'UNI',
           d.cantidad, d.precio_unitario, d.tipo_iva, d.iva_monto, d.subtotal_neto
    FROM public.pedido_det d
    JOIN public.producto p ON p.id_producto = d.id_producto
    WHERE d.id_pedido = $2
  ";
  $okDet = pg_query_params($conn, $sqlDet, [$id_factura, $id_pedido]);
  if (!$okDet) { throw new Exception('No se pudo insertar el detalle de la factura'); }

  // 7) Movimiento de stock (solo productos) — 'salida'
  $sqlStock = "
    INSERT INTO public.movimiento_stock(
      fecha, id_producto, tipo_movimiento, cantidad, observacion
    )
    SELECT
      $1::timestamp,
      d.id_producto,
      'salida'::varchar(10),
      GREATEST(1, ROUND(d.cantidad)::int),
      'Fact. '||$2
    FROM public.pedido_det d
    JOIN public.producto p ON p.id_producto = d.id_producto
    WHERE d.id_pedido = $3
      AND COALESCE(p.tipo_item,'P') = 'P'
  ";
  $okStock = pg_query_params($conn, $sqlStock, [$fecha_emision.' 00:00:00', $numero_doc, $id_pedido]);
  if ($okStock === false) { throw new Exception('No se pudo registrar movimiento de stock'); }

  // 8) Consumir reservas del pedido (6B)
  $sqlConsumirReserva = "
    UPDATE public.reserva_stock
    SET estado = 'consumida',
        actualizado_en = NOW()
    WHERE id_pedido = $1
      AND lower(estado) = 'activa'
  ";
  $okCons = pg_query_params($conn, $sqlConsumirReserva, [$id_pedido]);
  if ($okCons === false) { throw new Exception('No se pudo consumir las reservas del pedido'); }

  
  // 9) Cobro contado o CxC (cuotas múltiples si viene plan_cuotas)
if ($condicion === 'Contado') {
  // ======== CONTADO (sin cambios) ========
  $sqlRec = "
    INSERT INTO public.recibo_cobranza_cab(fecha, id_cliente, total_recibo, estado, observacion)
    VALUES ($1::date, $2, $3, 'Registrado', $4)
    RETURNING id_recibo
  ";
  $rRec = pg_query_params($conn, $sqlRec, [$fecha_emision, $id_cliente, $total, 'Cobro contado Fact. '.$numero_doc]);
  if (!$rRec) { throw new Exception('No se pudo crear el recibo'); }
  $id_recibo = (int)pg_fetch_result($rRec, 0, 0);

  $okAplic = pg_query_params(
    $conn,
    "INSERT INTO public.recibo_cobranza_det_aplic(id_recibo, id_factura, monto_aplicado) VALUES ($1,$2,$3)",
    [$id_recibo, $id_factura, $total]
  );
  if (!$okAplic) { throw new Exception('No se pudo aplicar el recibo a la factura'); }

  $okPago = pg_query_params(
    $conn,
    "INSERT INTO public.recibo_cobranza_det_pago(id_recibo, medio_pago, referencia, importe_bruto, comision, fecha_acredit)
     VALUES ($1,$2,$3,$4,0,$5::date)",
    [$id_recibo, $medioPago, $refPago, $total, $fecha_emision]
  );
  if (!$okPago) { throw new Exception('No se pudo registrar el medio de pago'); }

  if (strcasecmp($medioPago, 'Efectivo') === 0) {
    $okCaja = pg_query_params(
      $conn,
      "INSERT INTO public.movimiento_caja(fecha, tipo, concepto, importe, id_recibo, observacion)
       VALUES ($1::date,'Ingreso',$2,$3,$4,$5)",
      [$fecha_emision, 'Cobro contado Fact. '.$numero_doc, $total, $id_recibo, $medioPago]
    );
    if (!$okCaja) { throw new Exception('No se pudo registrar movimiento de caja'); }
  } else {
    $okBanco = pg_query_params(
      $conn,
      "INSERT INTO public.movimiento_banco(fecha, tipo, banco, referencia, importe, id_recibo, observacion)
       VALUES ($1::date,'Ingreso',NULL,$2,$3,$4,$5)",
      [$fecha_emision, $refPago ?: ('Cobro '.$medioPago), $total, $id_recibo, 'Cobro contado Fact. '.$numero_doc]
    );
    if (!$okBanco) { throw new Exception('No se pudo registrar movimiento bancario'); }
  }

} else {
  // ======== CRÉDITO ========
  $plan = $in['plan_cuotas'] ?? null;

  if (is_array($plan) && count($plan) > 0) {
    // Modo cuotas múltiples (recomendado)
    // Validar que el total de cuotas = total factura (tolerancia por redondeo)
    $sumPlan = 0.0;
    foreach ($plan as $c) {
      if (empty($c['vencimiento']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $c['vencimiento'])) {
        throw new Exception('Plan de cuotas: vencimiento inválido');
      }
      if (!isset($c['nro']) || !isset($c['total'])) {
        throw new Exception('Plan de cuotas: datos incompletos (nro/total)');
      }
      $sumPlan += (float)$c['total'];
    }
    if (abs($sumPlan - $total) > 0.05) {
      throw new Exception('El total del plan de cuotas no coincide con el total de la factura');
    }

    // Insertar 1 CxC por cuota
    foreach ($plan as $c) {
      $nro      = (int)$c['nro'];
      $venc     = (string)$c['vencimiento'];
      $cap      = isset($c['capital']) ? (float)$c['capital'] : 0.0;
      $int      = isset($c['interes']) ? (float)$c['interes'] : 0.0;
      $totalCu  = (float)$c['total'];

      $okCxc = pg_query_params(
        $conn,
        "INSERT INTO public.cuenta_cobrar
           (id_cliente, id_factura, fecha_origen,
            monto_origen, saldo_actual, fecha_vencimiento,
            estado, nro_cuota, capital, interes)
         VALUES ($1,$2,$3::date,$4,$4,$5::date,'Abierta',$6,$7,$8)",
        [$id_cliente, $id_factura, $fecha_emision, $totalCu, $venc, $nro, $cap, $int]
      );
      if (!$okCxc) { throw new Exception('No se pudo crear la cuota de CxC'); }
    }

  } else {
    // Modo simple: 1 sola CxC con fecha_vencimiento
    if (empty($vto) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vto)) {
      throw new Exception('fecha_vencimiento requerida (o enviar plan_cuotas) para Crédito');
    }
    $okCxc = pg_query_params(
      $conn,
      "INSERT INTO public.cuenta_cobrar(id_cliente, id_factura, fecha_origen, monto_origen, saldo_actual, fecha_vencimiento, estado)
       VALUES ($1,$2,$3::date,$4,$4,$5::date,'Abierta')",
      [$id_cliente, $id_factura, $fecha_emision, $total, $vto]
    );
    if (!$okCxc) { throw new Exception('No se pudo crear la cuenta por cobrar'); }
  }
}


  // 10) Libro Ventas
  $okLibro = pg_query_params(
    $conn,
    "INSERT INTO public.libro_ventas(fecha_emision, id_factura, numero_documento, timbrado_numero, id_cliente, condicion_venta,
                                     grav10, iva10, grav5, iva5, exentas, total, estado_doc)
     VALUES ($1::date,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,'Emitida')",
    [$fecha_emision, $id_factura, $numero_doc, $num_tim, $id_cliente, $condicion,
     $grav10, $iva10, $grav5, $iva5, $exentas, $total]
  );
  if (!$okLibro) { throw new Exception('No se pudo registrar en Libro Ventas'); }

  // 11) Marcar pedido como facturado
  $okPed = pg_query_params($conn,
    "UPDATE public.pedido_cab SET estado='Facturado', actualizado_en=NOW() WHERE id_pedido=$1",
    [$id_pedido]
  );
  if (!$okPed) { throw new Exception('No se pudo actualizar estado del pedido'); }

  // 12) Avanzar numeración del timbrado
  $okTim = pg_query_params($conn,
    "UPDATE public.timbrado SET nro_actual = nro_actual + 1 WHERE id_timbrado=$1",
    [$id_timbrado]
  );
  if (!$okTim) { throw new Exception('No se pudo avanzar numeración del timbrado'); }

  pg_query($conn, 'COMMIT');

  echo json_encode([
    'success'=> true,
    'id_factura'=> $id_factura,
    'numero_documento'=> $numero_doc,
    'condicion_venta'=> $condicion,
    'fecha_emision'=> $fecha_emision,
    'total'=> $total
  ]);
} catch (Throwable $e) {
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
