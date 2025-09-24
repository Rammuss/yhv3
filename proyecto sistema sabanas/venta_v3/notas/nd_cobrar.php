<?php
/**
 * nd_cobrar.php
 * FASE 3 — Cobro de una Nota de Débito (ND) desde la CAJA.
 * - Ingreso en movimiento_caja
 * - Pago en CxC (sobre CxC generada por la ND)
 * - Actualiza ND a 'Cobrada' o 'Parcial'
 */

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit;
  }

  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;

  // --------- Entrada ---------
  $id_nd = isset($in['id_nd']) ? (int)$in['id_nd'] : 0;
  $monto = isset($in['monto']) ? (float)$in['monto'] : 0.0;
  $medio = trim($in['medio'] ?? 'Efectivo');
  $obs   = trim($in['observacion'] ?? '');

  if ($id_nd <= 0) { throw new Exception('id_nd inválido'); }
  if ($monto <= 0) { throw new Exception('Monto a cobrar inválido'); }

  // --------- Sesión de Caja ---------
  $id_usuario     = (int)($_SESSION['id_usuario'] ?? 0);
  $id_caja_sesion = (int)($_SESSION['id_caja_sesion'] ?? 0);
  $id_caja        = (int)($_SESSION['id_caja'] ?? 0);
  if ($id_usuario <= 0) { throw new Exception('Sesión de usuario no válida.'); }
  if ($id_caja_sesion <= 0 || $id_caja <= 0) { throw new Exception('No hay caja abierta en esta sesión.'); }

  $rCaja = pg_query_params($conn,
    "SELECT 1 FROM public.caja_sesion WHERE id_caja_sesion=$1 AND id_caja=$2 AND estado='Abierta' LIMIT 1",
    [$id_caja_sesion, $id_caja]
  );
  if (!$rCaja || pg_num_rows($rCaja)===0) { throw new Exception('La caja de la sesión no está Abierta.'); }

  // --------- TX ---------
  pg_query($conn, 'BEGIN');

  // A) ND (LOCK)
  $rNd = pg_query_params($conn, "
    SELECT id_nd, id_cliente, numero_documento, total_neto, estado
    FROM public.nd_venta_cab
    WHERE id_nd = $1
    FOR UPDATE
  ", [$id_nd]);
  if (!$rNd || pg_num_rows($rNd)===0) { throw new Exception('ND no encontrada'); }
  $nd = pg_fetch_assoc($rNd);

  if (!in_array($nd['estado'], ['Emitida','Parcial'], true)) {
    throw new Exception('La ND no está disponible para cobro (estado actual: '.$nd['estado'].')');
  }

  $id_cliente = (int)$nd['id_cliente'];
  $num_doc_nd = $nd['numero_documento'];

  // B) CxC pendientes que provienen de esta ND (mov 'recargo' con referencia = nro ND)
  $rCxC = pg_query_params($conn, "
    SELECT DISTINCT c.id_cxc, c.saldo_actual
    FROM public.cuenta_cobrar c
    JOIN public.movimiento_cxc m ON m.id_cxc = c.id_cxc
    WHERE c.id_cliente = $1
      AND c.estado = 'Abierta'
      AND c.saldo_actual > 0
      AND m.tipo = 'recargo'
      AND m.referencia = $2
    ORDER BY c.id_cxc
  ", [$id_cliente, $num_doc_nd]);

  $pend = 0.0; $cxc_list = [];
  while ($rCxC && ($row = pg_fetch_assoc($rCxC))) {
    $cxc_list[] = ['id_cxc'=>(int)$row['id_cxc'], 'saldo'=>(float)$row['saldo_actual']];
    $pend += (float)$row['saldo_actual'];
  }
  if ($pend <= 0.009) { throw new Exception('Esta ND no registra saldo pendiente a cobrar.'); }

  $monto_cobrar = min($monto, $pend);

  // C) Movimiento de CAJA — INGRESO
  $descCaja = $obs !== '' ? $obs : ('Cobro ND '.$num_doc_nd);
  $rMovCaja = pg_query_params($conn, "
    INSERT INTO public.movimiento_caja
      (id_caja_sesion, tipo, origen, id_referencia, medio, monto, descripcion, fecha, id_usuario, ref_tipo, ref_id, ref_detalle)
    VALUES
      ($1, 'Ingreso', 'ND', $2, $3, $4, $5, now(), $6, 'ND', $2, $7)
    RETURNING id_movimiento
  ", [$id_caja_sesion, $id_nd, $medio, $monto_cobrar, $descCaja, $id_usuario, $num_doc_nd]);
  if (!$rMovCaja) { throw new Exception('No se pudo registrar el ingreso en caja'); }
  $id_mov_caja = (int)pg_fetch_result($rMovCaja, 0, 0);

  // D) Aplicar pago en CxC
  $restante = $monto_cobrar;
  foreach ($cxc_list as $c) {
    if ($restante <= 0.009) break;
    $id_cxc = $c['id_cxc']; $saldo = (float)$c['saldo'];
    if ($saldo <= 0) continue;

    $aplicar = min($restante, $saldo);

    // pago
    pg_query_params($conn, "
      INSERT INTO public.movimiento_cxc
        (id_cxc, fecha, tipo, monto, referencia, observacion)
      VALUES ($1, $2::date, 'pago', $3, $4, 'Cobro ND')
    ", [$id_cxc, date('Y-m-d'), $aplicar, $num_doc_nd]);

    // saldo baja hacia 0
    pg_query_params($conn, "
      UPDATE public.cuenta_cobrar
         SET saldo_actual = saldo_actual - $1,
             estado = CASE WHEN saldo_actual - $1 <= 0.009 THEN 'Cerrada' ELSE estado END,
             actualizado_en = NOW()
       WHERE id_cxc = $2
    ", [$aplicar, $id_cxc]);

    $restante -= $aplicar;
  }

  // E) Estado de la ND
  $rCheck = pg_query_params($conn, "
    SELECT COALESCE(SUM(c.saldo_actual),0) AS saldo_restante
    FROM public.cuenta_cobrar c
    JOIN public.movimiento_cxc m ON m.id_cxc = c.id_cxc
    WHERE c.id_cliente = $1
      AND c.estado = 'Abierta'
      AND c.saldo_actual > 0
      AND m.tipo = 'recargo'
      AND m.referencia = $2
  ", [$id_cliente, $num_doc_nd]);
  $saldo_restante = ($rCheck && pg_num_rows($rCheck)>0) ? (float)pg_fetch_result($rCheck, 0, 0) : 0.0;

  $nuevo_estado = ($saldo_restante <= 0.009) ? 'Cobrada' : 'Parcial';
  pg_query_params($conn, "UPDATE public.nd_venta_cab SET estado=$1 WHERE id_nd=$2", [$nuevo_estado, $id_nd]);

  // OK
  pg_query($conn, 'COMMIT');
  echo json_encode([
    'success'=>true,
    'id_nd'=>$id_nd,
    'numero_nd'=>$num_doc_nd,
    'monto_cobrado'=>$monto_cobrar,
    'medio'=>$medio,
    'id_mov_caja'=>$id_mov_caja,
    'estado_nd'=>$nuevo_estado
  ]);

} catch (Throwable $e) {
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
