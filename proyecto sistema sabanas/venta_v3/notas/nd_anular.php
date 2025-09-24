<?php
/**
 * nd_anular.php
 * FASE 4 — Anular Nota de Débito (segura).
 * Requisitos:
 *  - ND en estado 'Emitida'
 *  - Sin movimiento_caja origen 'ND'
 *  - Sin movimiento_cxc 'pago' referenciando a esta ND
 * Acciones:
 *  - Eliminar CxC asociada si solo tiene 'recargo' de esta ND
 *  - Eliminar registro de libro_ventas de la ND
 *  - Marcar ND como 'Anulada'
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
  $id_nd = isset($in['id_nd']) ? (int)$in['id_nd'] : 0;
  if ($id_nd <= 0) { throw new Exception('id_nd inválido'); }

  $id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
  if ($id_usuario <= 0) { throw new Exception('Sesión de usuario no válida.'); }

  pg_query($conn, 'BEGIN');

  // 1) ND (LOCK)
  $rNd = pg_query_params($conn, "
    SELECT id_nd, id_cliente, numero_documento, estado
    FROM public.nd_venta_cab
    WHERE id_nd = $1
    FOR UPDATE
  ", [$id_nd]);
  if (!$rNd || pg_num_rows($rNd)===0) { throw new Exception('ND no encontrada'); }
  $nd = pg_fetch_assoc($rNd);

  if ($nd['estado'] !== 'Emitida') {
    throw new Exception("No se puede anular; estado actual: {$nd['estado']}. Si ya cobraste/parcial, revertí primero.");
  }
  $num_nd = $nd['numero_documento'];
  $id_cli = (int)$nd['id_cliente'];

  // 2) Validaciones: caja y cxc pagos
  $rCaja = pg_query_params($conn, "
    SELECT 1 FROM public.movimiento_caja WHERE ref_tipo='ND' AND ref_id=$1 LIMIT 1
  ", [$id_nd]);
  if ($rCaja && pg_num_rows($rCaja)>0) {
    throw new Exception('La ND ya tiene ingresos en caja. Revertí esos cobros antes de anular.');
  }

  $rPagos = pg_query_params($conn, "
    SELECT 1
    FROM public.movimiento_cxc
    WHERE tipo='pago' AND referencia = $1
    LIMIT 1
  ", [$num_nd]);
  if ($rPagos && pg_num_rows($rPagos)>0) {
    throw new Exception('La ND ya tiene pagos aplicados en CxC. Revertí esos pagos antes de anular.');
  }

  // 3) Eliminar CxC asociada a esta ND si solo tiene 'recargo' (referencia = num_nd)
  $rCxc = pg_query_params($conn, "
    SELECT DISTINCT c.id_cxc
    FROM public.cuenta_cobrar c
    JOIN public.movimiento_cxc m ON m.id_cxc = c.id_cxc
    WHERE c.id_cliente=$1 AND m.tipo='recargo' AND m.referencia=$2
  ", [$id_cli, $num_nd]);

  while ($rCxc && ($row = pg_fetch_assoc($rCxc))) {
    $id_cxc = (int)$row['id_cxc'];

    // Solo si no hay otros movimientos
    $rChk = pg_query_params($conn, "
      SELECT
        SUM(CASE WHEN m.tipo='recargo' AND m.referencia=$2 THEN 1 ELSE 0 END) AS qty_recargo_ref,
        SUM(CASE WHEN m.tipo<>'recargo' THEN 1 ELSE 0 END) AS qty_otros
      FROM public.movimiento_cxc m
      WHERE m.id_cxc = $1
    ", [$id_cxc, $num_nd]);
    $qty_otros = 0;
    if ($rChk && pg_num_rows($rChk)>0) {
      $qty_otros = (int)pg_fetch_result($rChk, 0, 'qty_otros');
    }
    if ($qty_otros > 0) {
      throw new Exception('La CxC de la ND tiene otros movimientos. Revertí primero esos movimientos.');
    }

    pg_query_params($conn, "DELETE FROM public.movimiento_cxc WHERE id_cxc=$1", [$id_cxc]);
    pg_query_params($conn, "DELETE FROM public.cuenta_cobrar WHERE id_cxc=$1", [$id_cxc]);
  }

  // 4) Borrar asiento libro_ventas de la ND
  pg_query_params($conn, "
    DELETE FROM public.libro_ventas
    WHERE estado_doc='ND' AND numero_documento=$1
  ", [$num_nd]);

  // 5) Marcar ND como Anulada
  $okNd = pg_query_params($conn, "
    UPDATE public.nd_venta_cab
       SET estado='Anulada', motivo_texto = COALESCE(motivo_texto,'')||' [Anulada el '||TO_CHAR(now(),'YYYY-MM-DD HH24:MI')||']'
     WHERE id_nd=$1
  ", [$id_nd]);
  if (!$okNd) { throw new Exception('No se pudo marcar la ND como Anulada'); }

  pg_query($conn, 'COMMIT');
  echo json_encode(['success'=>true,'id_nd'=>$id_nd,'numero_nd'=>$num_nd,'estado'=>'Anulada']);

} catch (Throwable $e) {
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
