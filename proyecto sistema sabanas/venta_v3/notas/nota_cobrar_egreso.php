<?php
// /venta_v3/notas/nota_cobrar_egreso.php
// Genera un egreso de caja a partir de una Nota de Crédito y marca la NC como aplicada.

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
  $id_nc   = isset($in['id_nc']) ? (int)$in['id_nc'] : 0;
  $medio   = isset($in['medio']) ? trim($in['medio']) : 'Efectivo';
  $importe = isset($in['importe']) ? (float)$in['importe'] : 0;

  $id_usuario     = (int)($_SESSION['id_usuario'] ?? 0);
  $id_caja_sesion = (int)($_SESSION['id_caja_sesion'] ?? 0);

  if ($id_usuario <= 0 || $id_caja_sesion <= 0) {
    throw new Exception("Sesión de usuario/caja inválida.");
  }
  if ($id_nc <= 0 || $importe <= 0) {
    throw new Exception("Datos inválidos (id_nc / importe).");
  }

  pg_query($conn, 'BEGIN');

  // 1) Traer NC y validar estado
  $rNc = pg_query_params($conn,
    "SELECT numero_documento, total_neto, estado 
     FROM public.nc_venta_cab 
     WHERE id_nc=$1 
     FOR UPDATE",
    [$id_nc]
  );
  if (!$rNc || pg_num_rows($rNc) === 0) {
    throw new Exception("NC no encontrada.");
  }
  $nc = pg_fetch_assoc($rNc);
  if ($nc['estado'] !== 'Emitida') {
    throw new Exception("NC no está disponible (estado: {$nc['estado']}).");
  }

  // Si no se pasó importe, usar el total de la NC
  if ($importe <= 0.009) $importe = (float)$nc['total_neto'];

  // 2) Registrar movimiento de caja (Egreso)
  $sqlMov = "
    INSERT INTO public.movimiento_caja(
      id_caja_sesion, tipo, fecha, origen, medio, monto, descripcion, id_usuario
    ) VALUES (
      $1, 'Egreso', NOW(), 'Devolucion', $2, $3, $4, $5
    ) RETURNING id_movimiento
  ";
  $rMov = pg_query_params($conn, $sqlMov, [
    $id_caja_sesion,
    $medio,
    $importe,
    'Egreso por NC '.$nc['numero_documento'],
    $id_usuario
  ]);
  if (!$rMov) {
    throw new Exception("No se pudo registrar el movimiento de caja.");
  }
  $id_mov = (int)pg_fetch_result($rMov, 0, 0);

  // 3) Marcar la NC como aplicada
  $rUp = pg_query_params($conn,
  "UPDATE public.nc_venta_cab
     SET estado='Aplicada'
   WHERE id_nc=$1",
  [$id_nc]
);
  if (!$rUp) {
    throw new Exception("No se pudo actualizar estado de la NC.");
  }

  pg_query($conn, 'COMMIT');

  echo json_encode([
    'ok'    => true,
    'msg'   => 'Egreso registrado correctamente.',
    'id_nc' => $id_nc,
    'id_mov'=> $id_mov,
    'importe' => $importe
  ]);

} catch (Throwable $e) {
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
