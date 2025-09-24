<?php
/**
 * nc_anular.php
 * FASE 4 — Anular Nota de Crédito (segura).
 * Requisitos para anular:
 *  - NC en estado 'Emitida' (aún no aplicada ni devuelta por caja)
 *  - Sin movimiento_caja origen 'NC' de esta nota
 *  - Sin movimiento_cxc 'pago' asociados a esta NC
 * Acciones:
 *  - Revertir CxC negativa creada por la NC (si corresponde)
 *  - Revertir stock (si afecta_stock=true): salida
 *  - Eliminar registro en libro_ventas de esta NC
 *  - Marcar NC como 'Anulada'
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
  $id_nc = isset($in['id_nc']) ? (int)$in['id_nc'] : 0;
  if ($id_nc <= 0) { throw new Exception('id_nc inválido'); }

  // Sesión (solo para auditoría/permiso; no movemos caja)
  $id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
  if ($id_usuario <= 0) { throw new Exception('Sesión de usuario no válida.'); }

  pg_query($conn, 'BEGIN');

  // 1) NC (LOCK)
  $rNc = pg_query_params($conn, "
    SELECT id_nc, id_cliente, numero_documento, estado, afecta_stock
    FROM public.nc_venta_cab
    WHERE id_nc = $1
    FOR UPDATE
  ", [$id_nc]);
  if (!$rNc || pg_num_rows($rNc)===0) { throw new Exception('NC no encontrada'); }
  $nc = pg_fetch_assoc($rNc);

  if ($nc['estado'] !== 'Emitida') {
    throw new Exception("No se puede anular; estado actual: {$nc['estado']}. Si ya está 'Parcial/Aplicada', primero revertí caja/CxC.");
  }

  $num_nc    = $nc['numero_documento'];
  $id_cli    = (int)$nc['id_cliente'];
  $af_stock  = ($nc['afecta_stock'] === 't');

  // 2) Validaciones de uso posterior
  // 2.1 Caja: no debe haber egresos por esta NC
  $rCaja = pg_query_params($conn, "
    SELECT 1
    FROM public.movimiento_caja
    WHERE ref_tipo='NC' AND ref_id=$1
    LIMIT 1
  ", [$id_nc]);
  if ($rCaja && pg_num_rows($rCaja)>0) {
    throw new Exception('La NC ya tiene egresos en caja. Revertí esos movimientos antes de anular.');
  }

  // 2.2 CxC: no debe haberse aplicado (pago) el crédito de esta NC
  $rPagos = pg_query_params($conn, "
    SELECT 1
    FROM public.movimiento_cxc
    WHERE tipo='pago' AND referencia = $1
    LIMIT 1
  ", [$num_nc]);
  if ($rPagos && pg_num_rows($rPagos)>0) {
    throw new Exception('La NC ya fue aplicada a CxC (pago). Revertí esas aplicaciones antes de anular.');
  }

  // 3) Revertir CxC negativa creada por esta NC (si existe)
  //   Buscamos CxC con mov 'nota_credito' referencia = nro NC.
  $rCred = pg_query_params($conn, "
    SELECT DISTINCT c.id_cxc
    FROM public.cuenta_cobrar c
    JOIN public.movimiento_cxc m ON m.id_cxc = c.id_cxc
    WHERE c.id_cliente = $1
      AND m.tipo = 'nota_credito'
      AND m.referencia = $2
  ", [$id_cli, $num_nc]);
  while ($rCred && ($row = pg_fetch_assoc($rCred))) {
    $id_cxc = (int)$row['id_cxc'];

    // 3.1 Asegurar que esa CxC solo tenga movimientos provenientes de esta NC (nota_credito) y sin pagos.
    $rChk = pg_query_params($conn, "
      SELECT
        SUM(CASE WHEN m.tipo='nota_credito' AND m.referencia=$2 THEN 1 ELSE 0 END) AS qty_nc_ref,
        SUM(CASE WHEN m.tipo<>'nota_credito' THEN 1 ELSE 0 END) AS qty_otros
      FROM public.movimiento_cxc m
      WHERE m.id_cxc = $1
    ", [$id_cxc, $num_nc]);
    $qty_nc_ref = 0; $qty_otros = 0;
    if ($rChk && pg_num_rows($rChk)>0) {
      $q = pg_fetch_assoc($rChk);
      $qty_nc_ref = (int)$q['qty_nc_ref'];
      $qty_otros  = (int)$q['qty_otros'];
    }
    if ($qty_otros > 0) {
      throw new Exception('La CxC del crédito tiene otros movimientos. Revertí primero esos movimientos.');
    }

    // 3.2 Borrar movimientos de esa CxC y la CxC (deja saldo en 0 implícitamente)
    pg_query_params($conn, "DELETE FROM public.movimiento_cxc WHERE id_cxc=$1", [$id_cxc]);
    pg_query_params($conn, "DELETE FROM public.cuenta_cobrar WHERE id_cxc=$1", [$id_cxc]);
  }

  // 4) Revertir STOCK si corresponde: salida de lo que entró por la NC
  if ($af_stock) {
    $sqlStockOut = "
      INSERT INTO public.movimiento_stock(id_producto, tipo_movimiento, cantidad, fecha, observacion)
      SELECT
        d.id_producto,
        'salida'::varchar(10),
        GREATEST(1, ROUND(d.cantidad))::int,  -- ⚠️ si usás fracciones, cambiá tu DDL a numeric(14,3)
        now(),
        'ANULACIÓN NC '||$1
      FROM public.nc_venta_det d
      WHERE d.id_nc = $2 AND d.id_producto IS NOT NULL
    ";
    $okSt = pg_query_params($conn, $sqlStockOut, [$num_nc, $id_nc]);
    if ($okSt === false) { throw new Exception('No se pudo revertir stock de la NC'); }
  }

  // 5) Borrar asiento de libro_ventas correspondiente a esta NC (usamos número_documento)
  pg_query_params($conn, "
    DELETE FROM public.libro_ventas
    WHERE estado_doc='NC' AND numero_documento=$1
  ", [$num_nc]);

  // 6) Marcar NC como Anulada
  $okNc = pg_query_params($conn, "
    UPDATE public.nc_venta_cab
       SET estado='Anulada', motivo_texto = COALESCE(motivo_texto,'')||' [Anulada el '||TO_CHAR(now(),'YYYY-MM-DD HH24:MI')||']'
     WHERE id_nc=$1
  ", [$id_nc]);
  if (!$okNc) { throw new Exception('No se pudo marcar la NC como Anulada'); }

  pg_query($conn, 'COMMIT');
  echo json_encode(['success'=>true,'id_nc'=>$id_nc,'numero_nc'=>$num_nc,'estado'=>'Anulada']);

} catch (Throwable $e) {
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
