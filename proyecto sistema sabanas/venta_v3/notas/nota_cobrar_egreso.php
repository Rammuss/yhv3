<?php
// /venta_v3/notas/nota_cobrar_egreso.php
// Genera un egreso (devolución) a partir de una Nota de Crédito y aplica contra CxC negativas.
// - Caja: Egreso (origen Devolucion)
// - Banco: opcional si el medio es bancario (Tarjeta/Transferencia/Cheque) y se pasa id_cuenta_bancaria
// - CxC: registra movimiento_cxc tipo='ajuste' (válido para tu CHECK y varchar(12)) y actualiza saldo/estado
// - NC: marca 'Aplicada' o 'Aplicada Parcial'

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

function is_iso_date($s){ return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$s); }
function medio_es_bancario($m){ return in_array($m, ['Tarjeta','Transferencia','Cheque'], true); }

try {
  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;

  $id_nc   = isset($in['id_nc']) ? (int)$in['id_nc'] : 0;
  $medio   = isset($in['medio']) ? trim($in['medio']) : 'Efectivo';
  $importe = isset($in['importe']) ? (float)$in['importe'] : 0.0;
  $refExt  = isset($in['referencia']) ? trim($in['referencia']) : null;
  $id_cta  = isset($in['id_cuenta_bancaria']) ? (int)$in['id_cuenta_bancaria'] : null;
  $fecha   = (isset($in['fecha']) && is_iso_date($in['fecha'])) ? $in['fecha'] : date('Y-m-d');

  $id_usuario     = (int)($_SESSION['id_usuario'] ?? 0);
  $id_caja_sesion = (int)($_SESSION['id_caja_sesion'] ?? 0);
  $id_caja        = (int)($_SESSION['id_caja'] ?? 0);

  if ($id_usuario <= 0 || $id_caja_sesion <= 0) {
    throw new Exception("Sesión de usuario/caja inválida.");
  }
  if ($id_nc <= 0) { throw new Exception("id_nc inválido."); }
  if ($medio === '') { throw new Exception("Medio de pago inválido."); }
  if (medio_es_bancario($medio) && !$id_cta) {
    throw new Exception("Debe indicar id_cuenta_bancaria para medios bancarios ($medio).");
  }

  // Validar caja abierta
  $rCaja = pg_query_params($conn,
    "SELECT 1 FROM public.caja_sesion WHERE id_caja_sesion=$1 AND id_caja=$2 AND estado='Abierta' LIMIT 1",
    [$id_caja_sesion, $id_caja]
  );
  if (!$rCaja || pg_num_rows($rCaja)===0) { throw new Exception('La caja de la sesión no está Abierta.'); }

  pg_query($conn, 'BEGIN');

  // 1) Traer NC y validar estado. Traemos también id_cliente para aplicar CxC.
  $rNc = pg_query_params($conn, "
    SELECT n.id_nc, n.id_cliente, n.numero_documento, n.total_neto::numeric(14,2) AS total_neto,
           n.estado, n.fecha_emision::date AS fecha_emision
    FROM public.nc_venta_cab n
    WHERE n.id_nc=$1
    FOR UPDATE
  ", [$id_nc]);
  if (!$rNc || pg_num_rows($rNc)===0) throw new Exception("NC no encontrada.");
  $nc = pg_fetch_assoc($rNc);

  if ($nc['estado'] !== 'Emitida' && $nc['estado'] !== 'Aplicada Parcial') {
    throw new Exception("NC no disponible (estado: {$nc['estado']}).");
  }

  $id_cliente   = (int)$nc['id_cliente'];
  $numero_nc    = $nc['numero_documento'];
  $total_nc     = (float)$nc['total_neto'];

  // Si no se pasó importe, usamos el total de la NC
  if ($importe <= 0.009) $importe = $total_nc;

  // 2) Registrar movimiento de caja (Egreso)
  $sqlCaja = "
    INSERT INTO public.movimiento_caja(
      id_caja_sesion, id_usuario, fecha, tipo, origen, medio, monto, descripcion,
      ref_tipo, ref_id, ref_detalle
    ) VALUES (
      $1, $2, $3::timestamp, 'Egreso', 'Devolucion', $4, $5, $6,
      'NC', $7, $8
    ) RETURNING id_movimiento
  ";
  $descCaja = 'Devolución por NC '.$numero_nc . ($refExt ? (' · Ref: '.$refExt) : '');
  $rMovCaja = pg_query_params($conn, $sqlCaja, [
    $id_caja_sesion,
    $id_usuario,
    $fecha.' 00:00:00',
    $medio,
    $importe,
    $descCaja,
    $id_nc,
    $medio
  ]);
  if (!$rMovCaja) throw new Exception("No se pudo registrar el movimiento de caja.");
  $id_mov_caja = (int)pg_fetch_result($rMovCaja, 0, 0);

  // 3) Movimiento bancario (si corresponde)
  $id_mov_banco = null;
  if (medio_es_bancario($medio)) {
    $okBanco = pg_query_params($conn, "
      INSERT INTO public.movimiento_banco(
        fecha, tipo, id_cuenta_bancaria, referencia, importe, observacion
      ) VALUES ($1::timestamp, 'Egreso', $2, $3, $4, $5)
      RETURNING id_mov_banco
    ", [
      $fecha.' 00:00:00',
      $id_cta,
      ($refExt ?: 'Devolución NC '.$numero_nc),
      $importe,
      'Devolución al cliente por NC '.$numero_nc
    ]);
    if (!$okBanco) throw new Exception('No se pudo registrar movimiento bancario.');
    $id_mov_banco = (int)pg_fetch_result($okBanco, 0, 0);
  }

  // 4) Aplicar contra CxC negativas (saldo a favor del cliente)
  //    Buscamos CxC del cliente con saldo_actual < 0 (abiertas), y aplicamos el importe hasta agotar.
  $resCxc = pg_query_params($conn, "
    SELECT id_cxc, saldo_actual::numeric(14,2) AS saldo
    FROM public.cuenta_cobrar
    WHERE id_cliente = $1
      AND estado = 'Abierta'
      AND saldo_actual < 0
    ORDER BY fecha_origen, id_cxc
    FOR UPDATE
  ", [$id_cliente]);

  $restante = $importe;     // lo que aún falta aplicar en CxC
  $aplic_total = 0.0;       // total aplicado a CxC negativas

  while ($restante > 0.009 && $resCxc && ($cx = pg_fetch_assoc($resCxc))) {
    $id_cxc    = (int)$cx['id_cxc'];
    $saldo_neg = (float)$cx['saldo']; // negativo

    if ($saldo_neg >= -0.009) continue;

    $aplicar = min($restante, abs($saldo_neg)); // monto positivo a aplicar

    // movimiento_cxc: usar tipo 'ajuste' (válido con tu CHECK y varchar(12))
    $okMv = pg_query_params($conn, "
      INSERT INTO public.movimiento_cxc (id_cxc, fecha, tipo, monto, referencia, observacion)
      VALUES ($1, $2::date, 'ajuste', $3, $4, $5)
    ", [
      $id_cxc,
      $fecha,
      $aplicar,
      $numero_nc,
      'Devolución por NC (egreso de caja/banco)'
    ]);
    if (!$okMv) throw new Exception('No se pudo registrar movimiento CxC.');

    // actualizar saldo (sube hacia 0) y estado
    $okUp = pg_query_params($conn, "
      UPDATE public.cuenta_cobrar
         SET saldo_actual = saldo_actual + $1,
             estado = CASE WHEN saldo_actual + $1 > -0.009 THEN 'Cerrada' ELSE estado END,
             actualizado_en = NOW()
       WHERE id_cxc = $2
    ", [$aplicar, $id_cxc]);
    if (!$okUp) throw new Exception('No se pudo actualizar saldo de CxC.');

    $restante   -= $aplicar;
    $aplic_total+= $aplicar;
  }

  // 5) Marcar la NC según lo aplicado
  //    Si lo aplicado (aplic_total) es ~igual al importe devuelto, queda Aplicada.
  //    Si devolviste menos que el total de la NC, podés dejar 'Aplicada Parcial'.
  $nuevo_estado = (abs($importe - $aplic_total) <= 0.01) ? 'Aplicada' : 'Aplicada Parcial';

  $rUpNc = pg_query_params($conn, "
    UPDATE public.nc_venta_cab
       SET estado=$1
     WHERE id_nc=$2
  ", [$nuevo_estado, $id_nc]);
  if (!$rUpNc) throw new Exception("No se pudo actualizar estado de la NC.");

  pg_query($conn, 'COMMIT');

  echo json_encode([
    'ok'            => true,
    'msg'           => 'Egreso registrado y aplicado correctamente.',
    'id_nc'         => $id_nc,
    'numero_nc'     => $numero_nc,
    'importe'       => round($importe,2),
    'aplicado_cxc'  => round($aplic_total,2),
    'caja'          => ['id_movimiento'=>$id_mov_caja, 'medio'=>$medio],
    'banco'         => medio_es_bancario($medio) ? ['id_mov_banco'=>$id_mov_banco, 'id_cuenta_bancaria'=>$id_cta] : null,
    'estado_nc'     => $nuevo_estado
  ]);

} catch (Throwable $e) {
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
