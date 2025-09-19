<?php
// cobrar_cuotas_multi.php — Cobro de cuotas (CxC) con múltiples medios
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');
  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;

  $id_cliente = (int)($in['id_cliente'] ?? 0);
  $fecha      = trim($in['fecha'] ?? date('Y-m-d'));
  $cuotas     = is_array($in['cuotas'] ?? null) ? $in['cuotas'] : [];
  $pagos      = is_array($in['pagos'] ?? null) ? $in['pagos'] : [];

  if ($id_cliente <= 0) throw new Exception('id_cliente inválido');
  if (!$cuotas) throw new Exception('Sin cuotas seleccionadas');
  if (!$pagos) throw new Exception('Sin pagos');

  // === Usuario y Caja ===
  $id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
  if ($id_usuario <= 0) throw new Exception('Sesión de usuario no válida. Volvé a iniciar sesión.');

  $id_caja_sesion = (int)($_SESSION['id_caja_sesion'] ?? 0);
  if ($id_caja_sesion <= 0) {
    // recuperar última caja abierta del usuario
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
      $_SESSION['id_caja_sesion'] = $id_caja_sesion;
    }
  }
  if ($id_caja_sesion <= 0) throw new Exception('No hay sesión de caja abierta. Abrí tu caja para registrar cobros.');
  $chkSes = pg_query_params($conn, "SELECT 1 FROM public.caja_sesion WHERE id_caja_sesion=$1 AND estado='Abierta' LIMIT 1", [$id_caja_sesion]);
  if (!$chkSes || pg_num_rows($chkSes) === 0) throw new Exception('La sesión de caja no está abierta.');

  // Sumar pagos (>0)
  $sumPag = 0.0;
  foreach ($pagos as $p) {
    $x = (float)($p['importe'] ?? 0);
    if ($x > 0) $sumPag += $x;
  }
  if ($sumPag <= 0) throw new Exception('Suma de pagos inválida');

  pg_query($conn, 'BEGIN');

  // Traer/validar cuotas (cada cuota = fila en cuenta_cobrar)
  $totalAPagar = 0.0;
  $detCuotas   = [];
  foreach ($cuotas as $q) {
    $id_cxc = (int)($q['id_cuota'] ?? 0); // en UI: id_cuota; en BD: id_cxc
    $pagar  = (float)($q['pagar'] ?? 0);
    if ($id_cxc <= 0 || $pagar <= 0) throw new Exception('Cuota/importe inválido');

    $r = pg_query_params($conn, "
      SELECT
        cxc.id_cxc,
        cxc.id_cliente,
        cxc.saldo_actual::numeric(14,2) AS saldo,
        cxc.monto_origen::numeric(14,2) AS total,
        cxc.nro_cuota,
        cxc.id_factura,
        (SELECT COUNT(*) FROM public.cuenta_cobrar z WHERE z.id_factura = cxc.id_factura) AS cantidad_cuotas,
        f.numero_documento
      FROM public.cuenta_cobrar cxc
      JOIN public.factura_venta_cab f ON f.id_factura = cxc.id_factura
      WHERE cxc.id_cxc = $1
      FOR UPDATE
    ", [$id_cxc]);

    if (!$r || pg_num_rows($r) === 0) throw new Exception("Cuota $id_cxc no encontrada");
    $row = pg_fetch_assoc($r);

    if ((int)$row['id_cliente'] !== $id_cliente) {
      throw new Exception("La cuota $id_cxc no pertenece al cliente");
    }

    $saldo = (float)$row['saldo'];
    if ($pagar - $saldo > 0.01) {
      throw new Exception("El pago excede el saldo de la cuota $id_cxc");
    }

    $detCuotas[] = [
      'id_cxc'           => $id_cxc,
      'pagar'            => $pagar,
      'saldo'            => $saldo,
      'nro_cuota'        => (int)$row['nro_cuota'],
      'cant_cuotas'      => (int)$row['cantidad_cuotas'],
      'id_factura'       => (int)$row['id_factura'],
      'numero_documento' => $row['numero_documento']
    ];
    $totalAPagar += $pagar;
  }

  if (abs($sumPag - $totalAPagar) > 0.01) {
    throw new Exception('La suma de pagos no coincide con la suma a pagar en cuotas');
  }

  // Crear recibo
  $rRec = pg_query_params($conn, "
    INSERT INTO public.recibo_cobranza_cab(fecha, id_cliente, total_recibo, estado, observacion)
    VALUES ($1::date, $2, $3, 'Registrado', $4)
    RETURNING id_recibo
  ", [$fecha, $id_cliente, $totalAPagar, 'Cobro cuotas']);
  if (!$rRec) throw new Exception('No se pudo crear el recibo');
  $id_recibo = (int)pg_fetch_result($rRec, 0, 0);

  // Actualizar cada cuota + registrar movimiento_cxc
  foreach ($detCuotas as $c) {
    $okUpd = pg_query_params($conn, "
      UPDATE public.cuenta_cobrar
      SET saldo_actual = ROUND(GREATEST(saldo_actual - $1, 0), 2),
          estado = CASE WHEN ROUND(saldo_actual - $1, 2) <= 0 THEN 'Cancelada' ELSE estado END,
          actualizado_en = NOW()
      WHERE id_cxc = $2
    ", [$c['pagar'], $c['id_cxc']]);
    if (!$okUpd) throw new Exception('No se pudo actualizar cuota ' . $c['id_cxc']);

    $etiquetaCuota = ($c['nro_cuota'] ? ($c['nro_cuota'] . '/' . max(1, $c['cant_cuotas'])) : '1/1');
    $okMov = pg_query_params($conn, "
      INSERT INTO public.movimiento_cxc(id_cxc, fecha, tipo, monto, referencia, observacion)
      VALUES ($1, $2::date, 'Pago', $3, $4, $5)
    ", [
      $c['id_cxc'],
      $fecha,
      $c['pagar'],
      'Recibo #' . $id_recibo,
      'Pago cuota ' . $etiquetaCuota . ' · Fact. ' . $c['numero_documento']
    ]);
    if (!$okMov) throw new Exception('No se pudo registrar movimiento CxC');
  }

  // Medios de pago + CAJA/BANCO
  foreach ($pagos as $p) {
    $medio  = trim($p['medio'] ?? '');
    $imp    = (float)($p['importe'] ?? 0);
    $ref    = trim($p['referencia'] ?? '') ?: null;
    $id_cta = isset($p['id_cuenta_bancaria']) ? (int)$p['id_cuenta_bancaria'] : null;

    if ($imp <= 0 || $medio === '') continue;

    // Detalle del recibo
    $okPago = pg_query_params($conn, "
      INSERT INTO public.recibo_cobranza_det_pago(id_recibo, medio_pago, referencia, importe_bruto, comision, fecha_acredit, id_cuenta_bancaria)
      VALUES ($1, $2, $3, $4, 0, $5::date, $6)
    ", [$id_recibo, $medio, $ref, $imp, $fecha, $id_cta]);
    if (!$okPago) throw new Exception('No se pudo registrar el medio de pago');

    // === CAJA (para todos los medios) ===
    $qMov = "
  INSERT INTO public.movimiento_caja
    (id_caja_sesion, id_usuario, fecha, tipo, origen, medio, monto, descripcion,
     ref_tipo, ref_id, ref_detalle)
  VALUES
    ($1, $2, $3::timestamp, 'Ingreso', 'Venta', $4, $5, $6,
     'Recibo', $7, $8)
  RETURNING id_movimiento
";
    $desc = 'Recibo #' . $id_recibo . ' · CxC cliente ' . $id_cliente;
    $rm = pg_query_params($conn, $qMov, [
      $id_caja_sesion,
      $id_usuario,
      $fecha . ' 00:00:00',
      $medio,
      $imp,
      $desc,
      $id_recibo,
      $medio
    ]);
    if (!$rm) {
      $err = pg_last_error($conn);
      throw new Exception('No se pudo registrar movimiento de caja: ' . $err);
    }


    // === BANCO (solo no efectivo) — compatible con tu schema actual
    if (in_array($medio, ['Tarjeta', 'Transferencia', 'Cheque'], true)) {
      $okBanco = pg_query_params($conn, "
        INSERT INTO public.movimiento_banco(
          fecha, tipo, id_cuenta_bancaria, referencia, importe, id_recibo, observacion
        ) VALUES ($1::timestamp, 'Ingreso', $2, $3, $4, $5, $6)
      ", [
        $fecha . ' 00:00:00',
        ($id_cta ?: null),
        ($ref ?: ('Cobro ' . $medio . ' Recibo #' . $id_recibo)),
        $imp,
        $id_recibo,
        'Cobro cuotas'
      ]);
      if (!$okBanco) throw new Exception('No se pudo registrar movimiento bancario');
    }
  }

  /* ===== Aplicaciones del recibo a FACTURAS (para imprimir referencias y estado) ===== */
  $porFactura = []; // [id_factura] => ['monto'=>x, 'numero'=>'001-...']
  foreach ($detCuotas as $c) {
    $fid = (int)$c['id_factura'];
    if ($fid > 0 && $c['pagar'] > 0) {
      if (!isset($porFactura[$fid])) {
        $porFactura[$fid] = ['monto' => 0.0, 'numero' => $c['numero_documento']];
      }
      $porFactura[$fid]['monto'] += (float)$c['pagar'];
    }
  }
  foreach ($porFactura as $fid => $info) {
    $okApl = pg_query_params($conn, "
      INSERT INTO public.recibo_cobranza_det_aplic(id_recibo, id_factura, monto_aplicado)
      VALUES ($1, $2, $3)
    ", [$id_recibo, $fid, $info['monto']]);
    if (!$okApl) throw new Exception('No se pudo registrar la aplicación del recibo a la factura ' . $info['numero']);
  }
  /* ===== FIN Aplicaciones ===== */

  // Marcar facturas como Cancelada si quedó saldo 0
  $facturasAfectadas = array_keys($porFactura);
  foreach ($facturasAfectadas as $id_factura) {
    $rSaldo = pg_query_params($conn, "
      SELECT COALESCE(SUM(saldo_actual),0)::numeric(14,2) AS saldo
      FROM public.cuenta_cobrar
      WHERE id_factura = $1
        AND COALESCE(estado,'') <> 'Anulada'
    ", [$id_factura]);
    if (!$rSaldo) throw new Exception('No se pudo calcular saldo de la factura ' . $id_factura);

    $saldo = (float)pg_fetch_result($rSaldo, 0, 'saldo');
    if ($saldo <= 0.01) {
      $okFac = pg_query_params($conn, "
        UPDATE public.factura_venta_cab
           SET estado = 'Cancelada'
         WHERE id_factura = $1
           AND estado <> 'Anulada'
      ", [$id_factura]);
      if (!$okFac) throw new Exception('No se pudo marcar factura #' . $id_factura . ' como Cancelada');
    }
  }

  pg_query($conn, 'COMMIT');

  // Resumen para la UI
  $docs_aplicados = [];
  foreach ($porFactura as $fid => $info) {
    $docs_aplicados[] = ['id_factura' => $fid, 'numero_documento' => $info['numero'], 'monto' => round($info['monto'], 2)];
  }

  echo json_encode([
    'success'        => true,
    'id_recibo'      => $id_recibo,
    'monto'          => round($totalAPagar, 2),
    'docs_aplicados' => $docs_aplicados
  ]);
} catch (Throwable $e) {
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
