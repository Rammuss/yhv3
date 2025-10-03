<?php
// facturar_y_cobrar_contado.php — Emite factura (Contado) + cobra y mueve caja/banco (con timbrado por sub-rangos)
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

function lpad7($n){ return str_pad((string)$n, 7, '0', STR_PAD_LEFT); }
function is_iso_date($s){ return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s); }

// Detecta la columna de tipo en movimiento_stock
function stock_tipo_col($conn){
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

  $id_pedido     = isset($in['id_pedido']) ? (int)$in['id_pedido'] : 0;
  $obs           = isset($in['observacion']) ? trim($in['observacion']) : null;
  $fecha_emision = (isset($in['fecha_emision']) && is_iso_date($in['fecha_emision']))
                    ? $in['fecha_emision'] : date('Y-m-d');

  // pagos: [{medio, importe, referencia?, id_cuenta_bancaria?}]
  $pagos = is_array($in['pagos'] ?? null) ? $in['pagos'] : [];

  if ($id_pedido <= 0) { throw new Exception('id_pedido inválido'); }
  if (!$pagos || !is_array($pagos)) { throw new Exception('Debe enviar al menos un medio de pago'); }

  // === CAJA/USUARIO: asegurar sesión válida y caja abierta ===
  $id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
  if ($id_usuario <= 0) { throw new Exception('Sesión de usuario no válida (id_usuario vacío). Volvé a iniciar sesión.'); }

  $id_caja_sesion = (int)($_SESSION['id_caja_sesion'] ?? 0);
  $id_caja        = (int)($_SESSION['id_caja'] ?? 0);
  if ($id_caja_sesion <= 0 || $id_caja <= 0) { throw new Exception('No hay caja abierta en esta sesión. Abrí tu caja antes de facturar contado.'); }

  // (Defensivo) validar en BD que la caja_sesion esté Abierta
  $rCaja = pg_query_params($conn,
    "SELECT 1 FROM public.caja_sesion WHERE id_caja_sesion=$1 AND id_caja=$2 AND estado='Abierta' LIMIT 1",
    [$id_caja_sesion, $id_caja]
  );
  if (!$rCaja || pg_num_rows($rCaja) === 0) {
    throw new Exception('La caja de la sesión no está Abierta.');
  }

  // Sumar pagos
  $sumPag = 0.0;
  foreach ($pagos as $p) {
    // Aceptar tanto "1000" como 1000
    $impStr = (string)($p['importe'] ?? '0');
    $impStr = str_replace(',', '.', $impStr);
    $imp = (float)$impStr;
    if ($imp > 0) $sumPag += $imp;
  }
  // Redondeo a 2 decimales para comparar apples-to-apples
  $sumPag = round($sumPag, 2);

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
  if (!$rPed || pg_num_rows($rPed) === 0) { throw new Exception('Pedido no encontrado'); }
  $ped = pg_fetch_assoc($rPed);
  if (in_array($ped['estado'], ['Anulado', 'Facturado'], true)) {
    throw new Exception('El pedido no está disponible para facturar (Anulado o ya Facturado)');
  }
  $id_cliente = (int)$ped['id_cliente'];

  // 2) Totales desde pedido_det (NORMALIZADOS para no duplicar IVA)
    // 2) Totales tomando el precio con IVA del pedido
  $sqlTot = "
    WITH lineas AS (
      SELECT
        CASE
          WHEN UPPER(d.tipo_iva) IN ('IVA10','10%','10','IVA 10','10.0') THEN '10'
          WHEN UPPER(d.tipo_iva) IN ('IVA5','5%','5','IVA 5','5.0')     THEN '5'
          ELSE 'EX'
        END AS tipo_iva_norm,
        (d.cantidad * d.precio_unitario - COALESCE(d.descuento,0))::numeric(14,2) AS imp_total
      FROM public.pedido_det d
      WHERE d.id_pedido = $1
    ),
    descomp AS (
      SELECT
        tipo_iva_norm,
        CASE WHEN tipo_iva_norm='10' THEN round(imp_total / 1.10, 2)
             WHEN tipo_iva_norm='5'  THEN round(imp_total / 1.05, 2)
             ELSE imp_total
        END AS base,
        CASE WHEN tipo_iva_norm='10' THEN round(imp_total - imp_total / 1.10, 2)
             WHEN tipo_iva_norm='5'  THEN round(imp_total - imp_total / 1.05, 2)
             ELSE 0
        END AS iva,
        imp_total
      FROM lineas
    )
    SELECT
      COALESCE(SUM(CASE WHEN tipo_iva_norm='10' THEN base ELSE 0 END),0)::numeric(14,2) AS total_grav10,
      COALESCE(SUM(CASE WHEN tipo_iva_norm='10' THEN iva  ELSE 0 END),0)::numeric(14,2) AS total_iva10,
      COALESCE(SUM(CASE WHEN tipo_iva_norm='5'  THEN base ELSE 0 END),0)::numeric(14,2) AS total_grav5,
      COALESCE(SUM(CASE WHEN tipo_iva_norm='5'  THEN iva  ELSE 0 END),0)::numeric(14,2) AS total_iva5,
      COALESCE(SUM(CASE WHEN tipo_iva_norm='EX' THEN imp_total ELSE 0 END),0)::numeric(14,2) AS total_exentas,
      COALESCE(SUM(imp_total),0)::numeric(14,2) AS total_factura
    FROM descomp
  ";

  $rTot = pg_query_params($conn, $sqlTot, [$id_pedido]);
  if (!$rTot) { throw new Exception('No se pudo calcular totales'); }
  $tot = pg_fetch_assoc($rTot);
  $grav10  = (float)$tot['total_grav10'];
  $iva10   = (float)$tot['total_iva10'];
  $grav5   = (float)$tot['total_grav5'];
  $iva5    = (float)$tot['total_iva5'];
  $exentas = (float)$tot['total_exentas'];
  $total   = round((float)$tot['total_factura'], 2);
  if ($total <= 0) { throw new Exception('Total de factura inválido (ver detalle del pedido)'); }

  // Contado: validar suma pagos == total (con pequeña tolerancia por redondeo)
  $tol = 0.05; // ajustá si usás enteros en Gs
  if (abs($sumPag - $total) > $tol) {
    throw new Exception('El total de pagos debe ser igual al total de la factura (Contado)');
  }

  $total_bruto      = round($grav10 + $grav5 + $exentas, 2);
  $total_descuento  = 0.0;
  $total_neto       = $total;

  // 3) RESERVA DE NÚMERO (JIT por caja)
  $tamBloque = 500; // ajustable
  $rRes = pg_query_params($conn,
    "SELECT * FROM public.reservar_numero($1,$2,$3)",
    ['Factura', $id_caja, $tamBloque]
  );
  if (!$rRes || pg_num_rows($rRes) === 0) {
    throw new Exception('No se pudo reservar numeración (timbrado vencido o pool agotado)');
  }
  $rowNum = pg_fetch_assoc($rRes);

  // Devueltos por reservar_numero(): id_timbrado, id_asignacion, nro_corr, numero_formateado
  $id_timbrado   = (int)$rowNum['id_timbrado'];
  $id_asignacion = (int)$rowNum['id_asignacion']; // compat
  $nro_corr      = (int)$rowNum['nro_corr'];
  $numero_doc    = $rowNum['numero_formateado']; // EEE-PPP-NNNNNNN

  // Obtener número de timbrado para libro_ventas
  $rNumTim = pg_query_params($conn, "SELECT numero_timbrado FROM public.timbrado WHERE id_timbrado=$1 LIMIT 1", [$id_timbrado]);
  if (!$rNumTim || pg_num_rows($rNumTim) === 0) { throw new Exception('No se pudo obtener número de timbrado'); }
  $num_tim = pg_fetch_result($rNumTim, 0, 0);

  // 4) Validar reservas activas suficientes
  // 4) Validar reservas activas suficientes
$rCheck = pg_query_params($conn, "
  WITH req AS (
    SELECT d.id_producto,
           SUM(d.cantidad)::numeric(14,2) AS qty_requerida
      FROM public.pedido_det d
      JOIN public.producto p ON p.id_producto = d.id_producto
     WHERE d.id_pedido = $1
       AND COALESCE(p.tipo_item,'P') NOT IN ('S','D')  -- excluir servicios y descuentos
     GROUP BY d.id_producto
  ),
  res AS (
    SELECT r.id_producto,
           COALESCE(SUM(r.cantidad),0)::numeric(14,2) AS qty_reservada
      FROM public.reserva_stock r
      JOIN public.producto p ON p.id_producto = r.id_producto
     WHERE r.id_pedido = $1
       AND TRIM(LOWER(r.estado)) = 'activa'
       AND COALESCE(p.tipo_item,'P') NOT IN ('S','D')  -- misma condición
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

if ($rCheck === false) { throw new Exception('No se pudo validar reservas'); }
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
      estado, id_pedido, observacion, id_timbrado,
      id_caja_sesion, id_caja, nro_corr, id_asignacion
    ) VALUES (
      $1, $2, 'Contado',
      $3, $4,
      $5, $6, $7, $8, $9,
      $10, $11, $12,
      'Emitida', $13, $14, $15,
      $16, $17, $18, $19
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
    $id_timbrado,
    $id_caja_sesion,
    $id_caja,
    $nro_corr,
    $id_asignacion
  ]);
  if (!$rCab) { throw new Exception('No se pudo crear la factura (cabecera)'); }
  $id_factura = (int)pg_fetch_result($rCab, 0, 0);

  // 6) Detalle
    // 6) Detalle — recalcula base e IVA a partir del precio con IVA
  $okDet = pg_query_params($conn, "
    INSERT INTO public.factura_venta_det(
      id_factura, id_producto, tipo_item, descripcion, unidad,
      cantidad, precio_unitario, tipo_iva, iva_monto, subtotal_neto
    )
    SELECT
      $1,
      d.id_producto,
      COALESCE(p.tipo_item,'P'),
      p.nombre,
      'UNI',
      d.cantidad::numeric(14,2),
      d.precio_unitario,
      CASE
        WHEN UPPER(d.tipo_iva) IN ('IVA10','10%','10','IVA 10','10.0') THEN 'IVA10'
        WHEN UPPER(d.tipo_iva) IN ('IVA5','5%','5','IVA 5','5.0')     THEN 'IVA5'
        ELSE 'EXE'
      END AS tipo_iva_norm,
      CASE
        WHEN UPPER(d.tipo_iva) IN ('IVA10','10%','10','IVA 10','10.0')
          THEN round((d.cantidad*d.precio_unitario - COALESCE(d.descuento,0)) * 0.10 / 1.10, 2)
        WHEN UPPER(d.tipo_iva) IN ('IVA5','5%','5','IVA 5','5.0')
          THEN round((d.cantidad*d.precio_unitario - COALESCE(d.descuento,0)) * 0.05 / 1.05, 2)
        ELSE 0
      END AS iva_monto_calc,
      round(d.cantidad*d.precio_unitario - COALESCE(d.descuento,0), 2) AS subtotal_con_iva
    FROM public.pedido_det d
    JOIN public.producto p ON p.id_producto = d.id_producto
    WHERE d.id_pedido = $2
  ", [$id_factura, $id_pedido]);

  if (!$okDet) { throw new Exception('No se pudo insertar el detalle de la factura'); }

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
  if ($okStock === false) { throw new Exception('No se pudo registrar movimiento de stock'); }

  // 8) Consumir reservas
  $okCons = pg_query_params($conn, "
    UPDATE public.reserva_stock
       SET estado = 'consumida', actualizado_en = NOW()
     WHERE id_pedido = $1 AND TRIM(LOWER(estado)) = 'activa'
  ", [$id_pedido]);
  if ($okCons === false) { throw new Exception('No se pudo consumir las reservas del pedido'); }

  // 9) Libro ventas (NUEVA TABLA) — Cancelada porque cobramos ahora
  $okLibro = pg_query_params($conn, "
    INSERT INTO public.libro_ventas_new(
      fecha_emision, doc_tipo, id_doc, numero_documento, timbrado_numero,
      id_cliente, condicion_venta,
      grav10, iva10, grav5, iva5, exentas, total,
      estado_doc, id_timbrado, id_caja
    ) VALUES (
      $1::date, 'FACT', $2, $3, $4,
      $5, 'Contado',
      $6, $7, $8, $9, $10, $11,
      'Cancelada', $12, $13
    )
  ", [
    $fecha_emision,
    $id_factura,
    $numero_doc,
    $num_tim,
    $id_cliente,
    $grav10, $iva10, $grav5, $iva5, $exentas, $total,
    $id_timbrado,
    $id_caja
  ]);
  if (!$okLibro) { throw new Exception('No se pudo registrar en Libro Ventas'); }

  // 10) Marcar pedido facturado
  $okPed = pg_query_params(
    $conn,
    "UPDATE public.pedido_cab SET estado='Facturado', actualizado_en=NOW() WHERE id_pedido=$1",
    [$id_pedido]
  );
  if (!$okPed) { throw new Exception('No se pudo actualizar estado del pedido'); }

  // ======= COBRO + CAJA/BANCO + APLICACIÓN =======
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
    $impStr = (string)($p['importe'] ?? '0'); $impStr = str_replace(',', '.', $impStr);
    $imp    = (float)$impStr;
    $ref    = trim($p['referencia'] ?? '') ?: null;
    $id_cta = isset($p['id_cuenta_bancaria']) ? (int)$p['id_cuenta_bancaria'] : null;
    if ($imp <= 0 || $medio === '') continue;

    // detalle del recibo (auditoría)
    $okPago = pg_query_params($conn, "
      INSERT INTO public.recibo_cobranza_det_pago(id_recibo, medio_pago, referencia, importe_bruto, comision, fecha_acredit, id_cuenta_bancaria)
      VALUES ($1, $2, $3, $4, 0, $5::date, $6)
    ", [$id_recibo, $medio, $ref, round($imp,2), $fecha_emision, $id_cta]);
    if (!$okPago) throw new Exception('No se pudo registrar el medio de pago');

    // CAJA (Ingreso/origen Venta)
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
      $id_caja_sesion,
      $id_usuario,
      $fecha_emision . ' 00:00:00',
      $medio,
      round($imp,2),
      $desc,
      $id_factura,
      $medio
    ]);
    if (!$rm) {
      $err = pg_last_error($conn);
      if (strpos($err, 'ux_mov_ref') === false) {
        throw new Exception('No se pudo registrar movimiento de caja: ' . $err);
      }
    }

    // BANCO (solo medios bancarios)
    if (in_array($medio, ['Tarjeta', 'Transferencia', 'Cheque'], true)) {
      $okBanco = pg_query_params($conn, "
        INSERT INTO public.movimiento_banco(
          fecha, tipo, id_cuenta_bancaria, referencia, importe, id_recibo, observacion
        ) VALUES ($1::timestamp, 'Ingreso', $2, $3, $4, $5, $6)
      ", [
        $fecha_emision . ' 00:00:00',
        ($id_cta ?: null),
        ($ref ?: ('Cobro ' . $medio . ' fact. ' . $numero_doc)),
        round($imp,2),
        $id_recibo,
        'Cobro ' . $medio . ' fact. ' . $numero_doc
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
    'success'          => true,
    'id_factura'       => $id_factura,
    'numero_documento' => $numero_doc,
    'id_recibo'        => $id_recibo,
    'total'            => (float)$total
  ]);
} catch (Throwable $e) {
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
