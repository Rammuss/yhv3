<?php
// anular_factura.php — Anula una factura Emitida y revierte efectos (stock, CxC, libro_ventas).
// No borra registros: deja trazabilidad con estados "Anulada".

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

function is_positive_int($v){ return is_numeric($v) && (int)$v > 0; }

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit;
  }

  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;

  $id_factura  = isset($in['id_factura']) ? (int)$in['id_factura'] : 0;
  $motivo      = isset($in['motivo']) ? trim($in['motivo']) : null;
  $anulado_por = isset($in['anulado_por']) ? trim($in['anulado_por']) : ($_SESSION['nombre_usuario'] ?? null);

  if (!is_positive_int($id_factura)) { throw new Exception('id_factura inválido'); }
  if (empty($motivo)) { throw new Exception('motivo es requerido'); }

  pg_query($conn, 'BEGIN');

  // 1) Traer factura + lock
  $sqlFac = "
    SELECT f.id_factura, f.estado, f.id_cliente, f.id_pedido, f.condicion_venta, f.total_neto,
           f.numero_documento, f.fecha_emision
    FROM public.factura_venta_cab f
    WHERE f.id_factura = $1
    FOR UPDATE
  ";
  $rFac = pg_query_params($conn, $sqlFac, [$id_factura]);
  if (!$rFac || pg_num_rows($rFac) === 0) { throw new Exception('Factura no encontrada'); }
  $fac = pg_fetch_assoc($rFac);

  if ($fac['estado'] === 'Anulada') { throw new Exception('La factura ya está Anulada'); }
  if ($fac['estado'] !== 'Emitida') { throw new Exception('Solo se pueden anular facturas en estado Emitida'); }

  $id_cliente      = (int)$fac['id_cliente'];
  $id_pedido       = $fac['id_pedido'] !== null ? (int)$fac['id_pedido'] : null;
  $condicion_venta = $fac['condicion_venta'];
  $numero_doc      = $fac['numero_documento'];

  // 2) Validar que no existan pagos aplicados (si hay CxC con saldo_actual < monto_origen, hubo pagos)
  $sqlCxCCheck = "
    SELECT 1
    FROM public.cuenta_cobrar c
    WHERE c.id_factura = $1
      AND COALESCE(c.saldo_actual,0) < COALESCE(c.monto_origen,0)
    LIMIT 1
  ";
  $rCxCCheck = pg_query_params($conn, $sqlCxCCheck, [$id_factura]);
  if ($rCxCCheck && pg_num_rows($rCxCCheck) > 0) {
    throw new Exception('No se puede anular: existen pagos aplicados a la CxC asociada');
  }

  // 3) Revertir STOCK con entrada por cada ítem de producto
  $sqlStockEntrada = "
    INSERT INTO public.movimiento_stock(fecha, id_producto, tipo_movimiento, cantidad, observacion)
    SELECT
      NOW(),
      d.id_producto,
      'entrada'::varchar(10),
      GREATEST(1, ROUND(d.cantidad)::int),
      'Anulación Fact. '||$1
    FROM public.factura_venta_det d
    JOIN public.producto p ON p.id_producto = d.id_producto
    WHERE d.id_factura = $2
      AND COALESCE(p.tipo_item,'P') = 'P'
  ";
  $okStock = pg_query_params($conn, $sqlStockEntrada, [$numero_doc, $id_factura]);
  if ($okStock === false) { throw new Exception('No se pudo revertir el movimiento de stock'); }

  // 4) CxC: marcar como Anulada (si existe)
  //    (No borramos; saldo a 0 para evitar cobranzas posteriores)
  $sqlCxCUpdate = "
    UPDATE public.cuenta_cobrar
       SET estado='Anulada', saldo_actual=0
     WHERE id_factura = $1
  ";
  $okCxC = pg_query_params($conn, $sqlCxCUpdate, [$id_factura]);
  if ($okCxC === false) { throw new Exception('No se pudo actualizar CxC a estado Anulada'); }

  // 5) Libro Ventas: marcar estado_doc='Anulada'
  $sqlLibro = "
    UPDATE public.libro_ventas
       SET estado_doc='Anulada'
     WHERE id_factura = $1
  ";
  $okLib = pg_query_params($conn, $sqlLibro, [$id_factura]);
  if ($okLib === false) { throw new Exception('No se pudo actualizar Libro de Ventas'); }

  // 6) Factura → estado Anulada + auditoría de anulación
  $sqlFacUpd = "
    UPDATE public.factura_venta_cab
       SET estado='Anulada',
           motivo_anulacion=$2,
           anulado_por=$3,
           anulado_en=NOW()
     WHERE id_factura=$1
  ";
  $okFacUpd = pg_query_params($conn, $sqlFacUpd, [$id_factura, $motivo, $anulado_por]);
  if ($okFacUpd === false) { throw new Exception('No se pudo marcar la factura como Anulada'); }

  // 7) Pedido: devolver a Pendiente (opcional)
  if (!empty($id_pedido)) {
    $sqlPed = "
      UPDATE public.pedido_cab
         SET estado='Pendiente', actualizado_en=NOW()
       WHERE id_pedido=$1
    ";
    $okPed = pg_query_params($conn, $sqlPed, [$id_pedido]);
    if ($okPed === false) { throw new Exception('No se pudo devolver el pedido a Pendiente'); }
  }

  // NOTA: No se toca timbrado (numeración no se retrocede).

  pg_query($conn, 'COMMIT');

  echo json_encode([
    'success'   => true,
    'id_factura'=> $id_factura,
    'numero_documento'=> $numero_doc,
    'estado'    => 'Anulada'
  ]);

} catch (Throwable $e) {
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
