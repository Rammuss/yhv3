<?php
// facturar_y_cobrar_contado.php — Emite factura (Contado) + cobra y mueve caja/banco
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

function lpad7($n)
{
  return str_pad((string)$n, 7, '0', STR_PAD_LEFT);
}
function is_iso_date($s)
{
  return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}

// Detecta la columna de tipo en movimiento_stock
function stock_tipo_col($conn)
{
  $col = null;
  $q1 = pg_query_params($conn, "SELECT 1 FROM information_schema.columns
           WHERE table_schema='public' AND table_name='movimiento_stock' AND column_name='tipo_movimiento' LIMIT 1", []);
  if ($q1 && pg_num_rows($q1) > 0) $col = 'tipo_movimiento';
  if (!$col) {
    $q2 = pg_query_params($conn, "SELECT 1 FROM information_schema.columns
             WHERE table_schema='public' AND table_name='movimiento_stock' AND column_name='tipo' LIMIT 1", []);
    if ($q2 && pg_num_rows($q2) > 0) $col = 'tipo';
  }
  if (!$col) throw new Exception("No se encontró la columna de tipo ('tipo_movimiento' o 'tipo') en movimiento_stock");
  return $col;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
  }

  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;

  $id_pedido  = isset($in['id_pedido']) ? (int)$in['id_pedido'] : 0;
  $obs        = isset($in['observacion']) ? trim($in['observacion']) : null;
  $fecha_emision = (isset($in['fecha_emision']) && is_iso_date($in['fecha_emision']))
    ? $in['fecha_emision'] : date('Y-m-d');

  // pagos: [{medio, importe, referencia?, id_cuenta_bancaria?}]
  $pagos = is_array($in['pagos'] ?? null) ? $in['pagos'] : [];

  if ($id_pedido <= 0) {
    throw new Exception('id_pedido inválido');
  }
  if (!$pagos || !is_array($pagos)) {
    throw new Exception('Debe enviar al menos un medio de pago');
  }

  // === CAJA/USUARIO: asegurar sesión válida y caja abierta (con parche) ===
  $id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
  if ($id_usuario <= 0) {
    throw new Exception('Sesión de usuario no válida (id_usuario vacío). Volvé a iniciar sesión.');
  }

  $id_caja_sesion = (int)($_SESSION['id_caja_sesion'] ?? 0);
  if ($id_caja_sesion <= 0) {
    // Recuperar de BD la última sesión Abierta del usuario
    $rq = pg_query_params(
      $conn,
      "SELECT id_caja_sesion
         FROM public.caja_sesion
        WHERE id_usuario = $1 AND estado = 'Abierta'
        ORDER BY fecha_apertura DESC, id_caja_sesion DESC
        LIMIT 1",
      [$id_usuario]
    );
    if ($rq && pg_num_rows($rq) > 0) {
      $id_caja_sesion = (int)pg_fetch_result($rq, 0, 0);
      $_SESSION['id_caja_sesion'] = $id_caja_sesion; // cachear para siguientes requests
    }
  }
  if ($id_caja_sesion <= 0) throw new Exception('No hay sesión de caja abierta. Abrí tu caja antes de facturar contado.');
  $chkSes = pg_query_params($conn, "SELECT 1 FROM public.caja_sesion WHERE id_caja_sesion=$1 AND estado='Abierta' LIMIT 1", [$id_caja_sesion]);
  if (!$chkSes || pg_num_rows($chkSes) === 0) throw new Exception('La sesión de caja no está abierta.');

  // sumar pagos
  $sumPag = 0.0;
  foreach ($pagos as $p) {
    $imp = (float)($p['importe'] ?? 0);
    if ($imp > 0) $sumPag += $imp;
  }
  if ($sumPag <= 0) {
    throw new Exception('Importe total de pagos inválido');
  }

  // ===== TX =====
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
  if (!$rPed || pg_num_rows($rPed) === 0) {
    throw new Exception('Pedido no encontrado');
  }
  $ped = pg_fetch_assoc($rPed);
  if (in_array($ped['estado'], ['Anulado', 'Facturado'], true)) {
    throw new Exception('El pedido no está disponible para facturar (Anulado o ya Facturado)');
  }
  $id_cliente = (int)$ped['id_cliente'];

  // 2) Totales desde pedido_det
  $rTot = pg_query_params($conn, "
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
  ", [$id_pedido]);
  if (!$rTot) {
    throw new Exception('No se pudieron obtener los totales del pedido');
  }
  $tot = pg_fetch_assoc($rTot);
  $grav10  = (float)$tot['total_grav10'];
  $iva10   = (float)$tot['total_iva10'];
  $grav5   = (float)$tot['total_grav5'];
  $iva5    = (float)$tot['total_iva5'];
  $exentas = (float)$tot['total_exentas'];
  $total   = (float)$tot['total_factura'];
  if ($total <= 0) {
    throw new Exception('Total de factura inválido (ver detalle del pedido)');
  }

  // Contado: validar suma pagos == total
  if (abs($sumPag - $total) > 0.01) {
    throw new Exception('El total de pagos debe ser igual al total de la factura (Contado)');
  }

  // Totales cabecera en PHP
  $total_bruto      = $grav10 + $grav5 + $exentas;
  $total_descuento  = 0.0;
  $total_neto       = $total;

  // 3) Timbrado y correlativo -> $numero_doc
  $rTim = pg_query_params($conn, "
    SELECT id_timbrado, numero_timbrado, establecimiento, punto_expedicion, nro_actual, nro_hasta,
           fecha_inicio, fecha_fin
    FROM public.timbrado
    WHERE tipo_comprobante='Factura'
      AND estado='Vigente'
      AND $1::date BETWEEN fecha_inicio AND fecha_fin
    ORDER BY id_timbrado
    FOR UPDATE
    LIMIT 1
  ", [$fecha_emision]);
  if (!$rTim || pg_num_rows($rTim) === 0) {
    throw new Exception('No hay timbrado vigente para la fecha de emisión');
  }
  $tim = pg_fetch_assoc($rTim);
  $id_timbrado = (int)$tim['id_timbrado'];
  $nro_actual  = (int)$tim['nro_actual'];
  $nro_hasta   = (int)$tim['nro_hasta'];
  if ($nro_actual > $nro_hasta) {
    throw new Exception('Rango de timbrado agotado');
  }
  $establec = $tim['establecimiento'];
  $punto    = $tim['punto_expedicion'];
  $num_tim  = $tim['numero_timbrado'];
  $numero_doc = $establec . '-' . $punto . '-' . lpad7($nro_actual);

  // 4) Validar reservas activas suficientes
  $rCheck = pg_query_params($conn, "
    WITH req AS (
      SELECT d.id_producto, SUM(d.cantidad)::numeric(14,2) AS qty_requerida
      FROM public.pedido_det d
      WHERE d.id_pedido = $1
      GROUP BY d.id_producto
    ),
    res AS (
      SELECT r.id_producto, COALESCE(SUM(r.cantidad),0)::numeric(14,2) AS qty_reservada
      FROM public.reserva_stock r
      WHERE r.id_pedido = $1 AND TRIM(LOWER(r.estado)) = 'activa'
      GROUP BY r.id_producto
    )
    SELECT p.id_producto, p.nombre,
           req.qty_requerida,
           COALESCE(res.qty_reservada,0) AS qty_reservada
    FROM req
    LEFT JOIN res ON res.id_producto = req.id_producto
    JOIN public.producto p ON p.id_producto = req.id_producto
    WHERE COALESCE(res.qty_reservada,0) < req.qty_requerida
  ", [$id_pedido]);
  if ($rCheck === false) {
    throw new Exception('No se pudo validar reservas');
  }
  if (pg_num_rows($rCheck) > 0) {
    $faltantes = [];
    while ($row = pg_fetch_assoc($rCheck)) {
      $faltantes[] = $row['nombre'] . " (req: " . $row['qty_requerida'] . ", res: " . $row['qty_reservada'] . ")";
    }
    throw new Exception('Reserva insuficiente para: ' . implode(', ', $faltantes));
  }

  // 5) Cabecera de FACTURA
  $rCab = pg_query_params($conn, "
    INSERT INTO public.factura_venta_cab(
      fecha_emision, id_cliente, condicion_venta,
      numero_documento, timbrado_numero,
      total_grav10, total_iva10, total_grav5, total_iva5, total_exentas,
      total_bruto, total_descuento, total_neto,
      estado, id_pedido, observacion, id_timbrado
    ) VALUES (
      $1, $2, 'Contado',
      $3, $4,
      $5, $6, $7, $8, $9,
      $10, $11, $12,
      'Emitida', $13, $14, $15
    ) RETURNING id_factura
  ", [
    $fecha_emision,
    $id_cliente,
    $numero_doc,
    $num_tim,
    $grav10,
    $iva10,
    $grav5,
    $iva5,
    $exentas,
    $total_bruto,
    $total_descuento,
    $total_neto,
    $id_pedido,
    $obs,
    $id_timbrado
  ]);
  if (!$rCab) {
    throw new Exception('No se pudo crear la factura (cabecera)');
  }
  $id_factura = (int)pg_fetch_result($rCab, 0, 0);

  // 6) Detalle
  $okDet = pg_query_params($conn, "
    INSERT INTO public.factura_venta_det(
      id_factura, id_producto, tipo_item, descripcion, unidad,
      cantidad, precio_unitario, tipo_iva, iva_monto, subtotal_neto
    )
    SELECT $1, d.id_producto, COALESCE(p.tipo_item,'P'), p.nombre, 'UNI',
           d.cantidad::numeric(14,2), d.precio_unitario, d.tipo_iva, d.iva_monto, d.subtotal_neto
    FROM public.pedido_det d
    JOIN public.producto p ON p.id_producto = d.id_producto
    WHERE d.id_pedido = $2
  ", [$id_factura, $id_pedido]);
  if (!$okDet) {
    throw new Exception('No se pudo insertar el detalle de la factura');
  }

  // 7) Movimiento de stock
  $colTipo = stock_tipo_col($conn);
  $okStock = pg_query_params($conn, "
    INSERT INTO public.movimiento_stock(fecha, id_producto, {$colTipo}, cantidad, observacion)
    SELECT $1::timestamp, d.id_producto, 'salida'::varchar(10),
           d.cantidad::numeric(14,2), 'Fact. '||$2
    FROM public.pedido_det d
    JOIN public.producto p ON p.id_producto = d.id_producto
    WHERE d.id_pedido = $3 AND COALESCE(p.tipo_item,'P') = 'P'
  ", [$fecha_emision . ' 00:00:00', $numero_doc, $id_pedido]);
  if ($okStock === false) {
    throw new Exception('No se pudo registrar movimiento de stock');
  }

  // 8) Consumir reservas
  $okCons = pg_query_params($conn, "
    UPDATE public.reserva_stock
       SET estado = 'consumida', actualizado_en = NOW()
     WHERE id_pedido = $1 AND TRIM(LOWER(estado)) = 'activa'
  ", [$id_pedido]);
  if ($okCons === false) {
    throw new Exception('No se pudo consumir las reservas del pedido');
  }

  // 9) Libro ventas (Cancelada porque cobramos ahora)
  $okLibro = pg_query_params($conn, "
    INSERT INTO public.libro_ventas(
      fecha_emision, id_factura, numero_documento, timbrado_numero, id_cliente, condicion_venta,
      grav10, iva10, grav5, iva5, exentas, total, estado_doc
    ) VALUES ($1::date,$2,$3,$4,$5,'Contado',$6,$7,$8,$9,$10,$11,'Cancelada')
  ", [
    $fecha_emision,
    $id_factura,
    $numero_doc,
    $num_tim,
    $id_cliente,
    $grav10,
    $iva10,
    $grav5,
    $iva5,
    $exentas,
    $total
  ]);
  if (!$okLibro) {
    throw new Exception('No se pudo registrar en Libro Ventas');
  }

  // 10) Marcar pedido facturado
  $okPed = pg_query_params(
    $conn,
    "UPDATE public.pedido_cab SET estado='Facturado', actualizado_en=NOW() WHERE id_pedido=$1",
    [$id_pedido]
  );
  if (!$okPed) {
    throw new Exception('No se pudo actualizar estado del pedido');
  }

  // 11) Avanzar numeración timbrado
  $okTim = pg_query_params(
    $conn,
    "UPDATE public.timbrado SET nro_actual = nro_actual + 1 WHERE id_timbrado=$1",
    [$id_timbrado]
  );
  if (!$okTim) {
    throw new Exception('No se pudo avanzar numeración del timbrado');
  }

  // ======= AHORA SÍ: COBRO + CAJA/BANCO + APLICACIÓN =======
  $obsRec = trim(($obs ? ($obs . ' · ') : '') . ('Cobro contado fact. ' . $numero_doc));

  // Recibo
  $rRec = pg_query_params($conn, "
    INSERT INTO public.recibo_cobranza_cab(fecha, id_cliente, total_recibo, estado, observacion)
    VALUES ($1::date, $2, $3, 'Registrado', $4)
    RETURNING id_recibo
  ", [$fecha_emision, $id_cliente, $sumPag, $obsRec]);
  if (!$rRec) throw new Exception('No se pudo crear el recibo');
  $id_recibo = (int)pg_fetch_result($rRec, 0, 0);

  // Detalle de pagos + Caja + Banco
  foreach ($pagos as $p) {
    $medio  = trim($p['medio'] ?? '');
    $imp    = (float)($p['importe'] ?? 0);
    $ref    = trim($p['referencia'] ?? '') ?: null;
    $id_cta = isset($p['id_cuenta_bancaria']) ? (int)$p['id_cuenta_bancaria'] : null;
    if ($imp <= 0 || $medio === '') continue;

    // detalle del recibo (auditoría)
    $okPago = pg_query_params($conn, "
      INSERT INTO public.recibo_cobranza_det_pago(id_recibo, medio_pago, referencia, importe_bruto, comision, fecha_acredit, id_cuenta_bancaria)
      VALUES ($1, $2, $3, $4, 0, $5::date, $6)
    ", [$id_recibo, $medio, $ref, $imp, $fecha_emision, $id_cta]);
    if (!$okPago) throw new Exception('No se pudo registrar el medio de pago');

    // CAJA (Ingreso/origen Venta) — incluye id_usuario
    $qMov = "
  INSERT INTO public.movimiento_caja
    (id_caja_sesion, id_usuario, fecha, tipo, origen, medio, monto, descripcion,
     ref_tipo, ref_id, ref_detalle)
  VALUES
    ($1, $2, $3::timestamp, 'Ingreso', 'Venta', $4, $5, $6,
     'Factura', $7, $8)
  RETURNING id_movimiento
";
    $desc = 'Factura ' . $numero_doc . ' · Recibo #' . $id_recibo;
    $rm = pg_query_params($conn, $qMov, [
      $id_caja_sesion,                 // $1
      $id_usuario,                     // $2  <-- NUEVO
      $fecha_emision . ' 00:00:00',      // $3
      $medio,                          // $4
      $imp,                            // $5
      $desc,                           // $6
      $id_factura,                     // $7
      $medio                           // $8
    ]);
    if (!$rm) {
      $err = pg_last_error($conn);
      if (strpos($err, 'ux_mov_ref') === false) {
        throw new Exception('No se pudo registrar movimiento de caja: ' . $err);
      }
    }


    // BANCO (solo para Tarjeta/Transferencia/Cheque) — COMPATIBLE con tu schema actual
    if (in_array($medio, ['Tarjeta', 'Transferencia', 'Cheque'], true)) {
      $okBanco = pg_query_params($conn, "
    INSERT INTO public.movimiento_banco(
      fecha, tipo, id_cuenta_bancaria, referencia, importe, id_recibo, observacion
    ) VALUES ($1::timestamp, 'Ingreso', $2, $3, $4, $5, $6)
  ", [
        $fecha_emision . ' 00:00:00',                    // fecha
        ($id_cta ?: null),                             // id_cuenta_bancaria (puede ser NULL)
        ($ref ?: ('Cobro ' . $medio . ' fact. ' . $numero_doc)), // referencia
        $imp,                                          // importe
        $id_recibo,                                    // id_recibo (lo acabamos de crear)
        'Cobro ' . $medio . ' fact. ' . $numero_doc          // observacion
      ]);
      if (!$okBanco) throw new Exception('No se pudo registrar movimiento bancario');
    }
  }

  // Aplicar recibo a la factura
  $okAplic = pg_query_params($conn, "
    INSERT INTO public.recibo_cobranza_det_aplic(id_recibo, id_factura, monto_aplicado)
    VALUES ($1, $2, $3)
  ", [$id_recibo, $id_factura, $sumPag]);
  if (!$okAplic) throw new Exception('No se pudo aplicar el recibo a la factura');

  // Marcar factura como Cancelada
  $okFac = pg_query_params($conn, "
    UPDATE public.factura_venta_cab
       SET estado = 'Cancelada'
     WHERE id_factura = $1
       AND estado <> 'Anulada'
  ", [$id_factura]);
  if (!$okFac) throw new Exception('No se pudo actualizar el estado de la factura');

  pg_query($conn, 'COMMIT');

  echo json_encode([
    'success' => true,
    'id_factura' => $id_factura,
    'numero_documento' => $numero_doc,
    'id_recibo' => $id_recibo,
    'total' => (float)$total
  ]);
} catch (Throwable $e) {
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
