<?php
// cobrar_factura_multi.php
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

  // Sumar importes válidos (> 0)
  $importe_total = 0.0;
  foreach ($pagos as $p) {
    $imp = (float)($p['importe'] ?? 0);
    if ($imp > 0) $importe_total += $imp;
  }
  if ($importe_total <= 0) throw new Exception('Importe de pagos inválido');

  pg_query($conn, 'BEGIN');

  // 1) Bloquear la FACTURA y leer numero_documento
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
  $numero_doc  = trim($fRow['numero_documento']); // ej: 001-001-0000123

  // 2) Total ya cobrado
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

  // 4) Aplicar recibo a la factura (aplicamos todo lo cobrado a esa factura)
  $okAplic = pg_query_params(
    $conn,
    "INSERT INTO public.recibo_cobranza_det_aplic(id_recibo, id_factura, monto_aplicado)
     VALUES ($1, $2, $3)",
    [$id_recibo, $id_factura, $importe_total]
  );
  if (!$okAplic) throw new Exception('No se pudo aplicar el recibo a la factura');

  // ¿Existe columna id_cuenta_bancaria en movimiento_banco?
  $hasMBacc = false;
  $chkMBacc = pg_query_params(
    $conn,
    "SELECT 1 FROM information_schema.columns
     WHERE table_schema='public' AND table_name='movimiento_banco' AND column_name='id_cuenta_bancaria' LIMIT 1", []
  );
  if ($chkMBacc && pg_num_rows($chkMBacc)>0) $hasMBacc = true;

  // ¿Existe columna estado en cuentas_bancarias?
  $hasEstadoCuenta = false;
  $chkEstado = pg_query_params(
    $conn,
    "SELECT 1 FROM information_schema.columns
     WHERE table_schema='public' AND table_name='cuentas_bancarias' AND column_name='estado' LIMIT 1", []
  );
  if ($chkEstado && pg_num_rows($chkEstado)>0) $hasEstadoCuenta = true;

  // 5) Medios de pago + movimientos
  foreach ($pagos as $p) {
    $medio = trim($p['medio'] ?? '');
    $imp   = (float)($p['importe'] ?? 0);
    $ref   = trim($p['referencia'] ?? '');
    $id_cuenta_bancaria = isset($p['id_cuenta_bancaria']) ? (int)$p['id_cuenta_bancaria'] : null;

    if ($imp <= 0) continue;

    // Detalle de pago
    $okPago = pg_query_params(
      $conn,
      "INSERT INTO public.recibo_cobranza_det_pago(id_recibo, medio_pago, referencia, importe_bruto, comision, fecha_acredit)
       VALUES ($1, $2, $3, $4, 0, $5::date)",
      [$id_recibo, $medio, ($ref?:null), $imp, $fecha]
    );
    if (!$okPago) throw new Exception('No se pudo registrar el medio de pago');

    if (strcasecmp($medio, 'Efectivo') === 0) {
      // Movimiento de CAJA
      $okCaja = pg_query_params(
        $conn,
        "INSERT INTO public.movimiento_caja(fecha, tipo, concepto, importe, id_recibo, observacion)
         VALUES ($1::date, 'Ingreso', $2, $3, $4, $5)",
        [$fecha, 'Cobro Fact. '.$numero_doc, $imp, $id_recibo, 'Efectivo']
      );
      if (!$okCaja) throw new Exception('No se pudo registrar movimiento de caja');

    } else {
      // Transferencia (y otros medios bancarios) → exigir y validar cuenta
      if (!$id_cuenta_bancaria) {
        throw new Exception('Falta seleccionar la cuenta bancaria para el medio: '.$medio);
      }

      // Validar existencia (y estado activo si la columna existe)
      $sqlCuenta = "SELECT id_cuenta_bancaria FROM public.cuentas_bancarias WHERE id_cuenta_bancaria=$1";
      if ($hasEstadoCuenta) $sqlCuenta .= " AND LOWER(estado) IN ('activa','activo')";
      $sqlCuenta .= " LIMIT 1";

      $rc = pg_query_params($conn, $sqlCuenta, [$id_cuenta_bancaria]);
      if(!$rc || pg_num_rows($rc)===0){
        throw new Exception('Cuenta bancaria inválida/inactiva');
      }

      // Movimiento de BANCO (clave: grabar id_cuenta_bancaria)
      if ($hasMBacc) {
        $okBanco = pg_query_params(
          $conn,
          "INSERT INTO public.movimiento_banco(
             fecha, tipo, referencia, importe, id_recibo, observacion, id_cuenta_bancaria
           )
           VALUES ($1::date, 'Ingreso', $2, $3, $4, $5, $6)",
          [
            $fecha,
            ($ref ?: ('Cobro '.$medio.' '.$numero_doc)),
            $imp,
            $id_recibo,
            'Cobro Fact. '.$numero_doc,
            $id_cuenta_bancaria
          ]
        );
      } else {
        // Si aún no migraste, guardamos sin el id (pero ya no usamos 'banco' texto)
        $okBanco = pg_query_params(
          $conn,
          "INSERT INTO public.movimiento_banco(
             fecha, tipo, referencia, importe, id_recibo, observacion
           )
           VALUES ($1::date, 'Ingreso', $2, $3, $4, $5)",
          [
            $fecha,
            ($ref ?: ('Cobro '.$medio.' '.$numero_doc)),
            $imp,
            $id_recibo,
            'Cobro Fact. '.$numero_doc
          ]
        );
      }
      if (!$okBanco) throw new Exception('No se pudo registrar movimiento bancario');
    }
  }

  // 6) (Opcional) marcar factura cancelada si quedó en 0
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
    'monto'           => $importe_total,
    'pendiente_nuevo' => round($pendiente_nuevo, 2)
  ]);

} catch (Throwable $e) {
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
