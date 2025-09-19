<?php
// cobrar_factura_multi.php — Cobros parciales o totales de una factura ya emitida
session_start();
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try{
  if($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception('Método no permitido');
  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;

  $id_factura = (int)($in['id_factura'] ?? 0);
  $fecha      = trim($in['fecha'] ?? date('Y-m-d'));
  $pagos      = is_array($in['pagos'] ?? null) ? $in['pagos'] : [];

  if ($id_factura <= 0) throw new Exception('id_factura inválido');
  if (!$pagos) throw new Exception('Sin pagos');

  // === Sesión/Usuario/Caja (misma lógica que el endpoint de contado) ===
  $id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
  if ($id_usuario <= 0) throw new Exception('Sesión de usuario no válida. Volvé a iniciar sesión.');

  $id_caja_sesion = (int)($_SESSION['id_caja_sesion'] ?? 0);
  if ($id_caja_sesion <= 0) {
    $rq = pg_query_params(
      $conn,
      "SELECT id_caja_sesion
         FROM public.caja_sesion
        WHERE id_usuario = $1 AND estado = 'Abierta'
        ORDER BY fecha_apertura DESC, id_caja_sesion DESC
        LIMIT 1",
      [$id_usuario]
    );
    if ($rq && pg_num_rows($rq)>0) {
      $id_caja_sesion = (int)pg_fetch_result($rq, 0, 0);
      $_SESSION['id_caja_sesion'] = $id_caja_sesion;
    }
  }
  if ($id_caja_sesion <= 0) throw new Exception('No hay sesión de caja abierta. Abrí tu caja para registrar cobros.');
  $chkSes = pg_query_params($conn, "SELECT 1 FROM public.caja_sesion WHERE id_caja_sesion=$1 AND estado='Abierta' LIMIT 1", [$id_caja_sesion]);
  if (!$chkSes || pg_num_rows($chkSes) === 0) throw new Exception('La sesión de caja no está abierta.');

  // Sumar importes válidos (> 0)
  $importe_total = 0.0;
  foreach ($pagos as $p) {
    $imp = (float)($p['importe'] ?? 0);
    if ($imp > 0) $importe_total += $imp;
  }
  if ($importe_total <= 0) throw new Exception('Importe de pagos inválido');

  pg_query($conn, 'BEGIN');

  // 1) Bloquear FACTURA
  $rf = pg_query_params(
    $conn,
    "SELECT id_cliente,
            total_neto::numeric(14,2) AS total,
            COALESCE(numero_documento, '#'||id_factura::text) AS numero_documento
     FROM public.factura_venta_cab
     WHERE id_factura = $1
     FOR UPDATE",
    [$id_factura]
  );
  if (!$rf || pg_num_rows($rf) === 0) throw new Exception('Factura no encontrada');

  $fRow        = pg_fetch_assoc($rf);
  $id_cliente  = (int)$fRow['id_cliente'];
  $total       = (float)$fRow['total'];
  $numero_doc  = trim($fRow['numero_documento']);

  // 2) Pendiente actual
  $ra = pg_query_params(
    $conn,
    "SELECT COALESCE(SUM(monto_aplicado),0)::numeric(14,2) AS cobrados
     FROM public.recibo_cobranza_det_aplic
     WHERE id_factura = $1",
    [$id_factura]
  );
  if (!$ra) throw new Exception('No se pudo leer aplicaciones previas');
  $cobrados  = (float)pg_fetch_result($ra, 0, 0);
  $pendiente = max(0, $total - $cobrados);

  if ($importe_total - $pendiente > 0.01) {
    throw new Exception('La suma de pagos supera el pendiente');
  }

  // 3) Recibo (cabecera)
  $rRec = pg_query_params(
    $conn,
    "INSERT INTO public.recibo_cobranza_cab(fecha, id_cliente, total_recibo, estado, observacion)
     VALUES ($1::date, $2, $3, 'Registrado', $4)
     RETURNING id_recibo",
    [$fecha, $id_cliente, $importe_total, 'Cobro Fact. '.$numero_doc]
  );
  if (!$rRec) throw new Exception('No se pudo crear el recibo');
  $id_recibo = (int)pg_fetch_result($rRec, 0, 0);

  // 4) Aplicar recibo a la factura (todo lo cobrado)
  $okAplic = pg_query_params(
    $conn,
    "INSERT INTO public.recibo_cobranza_det_aplic(id_recibo, id_factura, monto_aplicado)
     VALUES ($1, $2, $3)",
    [$id_recibo, $id_factura, $importe_total]
  );
  if (!$okAplic) throw new Exception('No se pudo aplicar el recibo a la factura');

  // 5) Medios de pago + movimientos (CAJA y BANCO)
  foreach ($pagos as $p) {
    $medio = trim($p['medio'] ?? '');
    $imp   = (float)($p['importe'] ?? 0);
    $ref   = trim($p['referencia'] ?? '') ?: null;
    $id_cta= isset($p['id_cuenta_bancaria']) ? (int)$p['id_cuenta_bancaria'] : null;

    if ($imp <= 0 || $medio==='') continue;

    // detalle del recibo
    $okPago = pg_query_params(
      $conn,
      "INSERT INTO public.recibo_cobranza_det_pago
        (id_recibo, medio_pago, referencia, importe_bruto, comision, fecha_acredit, id_cuenta_bancaria)
       VALUES ($1, $2, $3, $4, 0, $5::date, $6)",
      [$id_recibo, $medio, $ref, $imp, $fecha, $id_cta]
    );
    if (!$okPago) throw new Exception('No se pudo registrar el medio de pago');

    // === CAJA (para todos los medios) ===
    $qMov = "
      INSERT INTO public.movimiento_caja
        (id_caja_sesion, id_usuario, fecha, tipo, origen, medio, monto, descripcion,
         ref_tipo, ref_id, ref_detalle)
      VALUES
        ($1, $2, $3::timestamp, 'Ingreso', 'Venta', $4, $5, $6,
         'Factura', $7, $8)
      RETURNING id_movimiento
    ";
    $desc = 'Factura '.$numero_doc.' · Recibo #'.$id_recibo;
    $rm = pg_query_params($conn, $qMov, [
      $id_caja_sesion,             // $1
      $id_usuario,                 // $2
      $fecha.' 00:00:00',          // $3
      $medio,                      // $4
      $imp,                        // $5
      $desc,                       // $6
      $id_factura,                 // $7
      $medio                       // $8
    ]);
    if (!$rm) {
      $err = pg_last_error($conn);
      throw new Exception('No se pudo registrar movimiento de caja: '.$err);
    }

    // === BANCO solo para no-efectivo ===
    if (in_array($medio, ['Tarjeta','Transferencia','Cheque'], true)) {
      // Si tenés validación de cuenta, podés verificar aquí que exista/esté activa
      $okBanco = pg_query_params(
        $conn,
        "INSERT INTO public.movimiento_banco(
           fecha, tipo, id_cuenta_bancaria, referencia, importe, id_recibo, observacion
         ) VALUES ($1::timestamp, 'Ingreso', $2, $3, $4, $5, $6)",
        [
          $fecha.' 00:00:00',
          ($id_cta ?: null),
          ($ref ?: ('Cobro '.$medio.' '.$numero_doc)),
          $imp,
          $id_recibo,
          'Cobro Fact. '.$numero_doc
        ]
      );
      if (!$okBanco) throw new Exception('No se pudo registrar movimiento bancario');
    }
  }

  // 6) Marcar factura cancelada si quedó en 0
  $pendiente_nuevo = max(0, $pendiente - $importe_total);
  if ($pendiente_nuevo <= 0.01) {
    pg_query_params(
      $conn,
      "UPDATE public.factura_venta_cab SET estado = 'Cancelada' WHERE id_factura = $1",
      [$id_factura]
    );
  }

  pg_query($conn, 'COMMIT');

  echo json_encode([
    'success'         => true,
    'id_recibo'       => $id_recibo,
    'numero_documento'=> $numero_doc,
    'monto'           => round($importe_total,2),
    'pendiente_nuevo' => round($pendiente_nuevo, 2)
  ]);

} catch (Throwable $e) {
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
