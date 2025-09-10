<?php
// pedido_anular.php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';

header('Content-Type: application/json; charset=utf-8');

// Autenticación básica
if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['success'=>false,'error'=>'No autorizado']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit;
}

$id_pedido = (int)($_POST['id_pedido'] ?? 0);
$motivo    = trim($_POST['motivo'] ?? '');

if ($id_pedido <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Falta id_pedido']); exit;
}

// Traer pedido
$ped = pg_query_params($conn, "
  SELECT id_pedido, estado
  FROM public.pedido_cab
  WHERE id_pedido = $1
  LIMIT 1
", [$id_pedido]);

if (!$ped || pg_num_rows($ped) === 0) {
  http_response_code(404);
  echo json_encode(['success'=>false,'error'=>'Pedido no encontrado']); exit;
}
$row = pg_fetch_assoc($ped);

// Reglas de negocio
$estado = strtolower($row['estado'] ?? '');
if ($estado === 'anulado') {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'El pedido ya está anulado']); exit;
}
if ($estado === 'facturado' || $estado === 'cerrado') {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'No puede anular un pedido facturado/cerrado']); exit;
}

/* Si tenés facturas vinculadas, descomentá para bloquear:
$fac = pg_query_params($conn, "SELECT 1 FROM public.factura_cab WHERE id_pedido=$1 LIMIT 1", [$id_pedido]);
if ($fac && pg_num_rows($fac) > 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'El pedido tiene factura vinculada']); exit;
}
*/

pg_query($conn, "BEGIN");

try {
  $usuario = $_SESSION['nombre_usuario'] ?? 'web';

  // 1) Cambiar estado del pedido a anulado + auditoría
  $up = pg_query_params($conn, "
    UPDATE public.pedido_cab
       SET estado = 'anulado',
           motivo_anulacion = $2,
           anulado_por = $3,
           anulado_en  = NOW()
     WHERE id_pedido = $1
  ", [$id_pedido, $motivo !== '' ? $motivo : null, $usuario]);
  if (!$up || pg_affected_rows($up) === 0) {
    throw new Exception('No se pudo actualizar el pedido.');
  }

  // 2) Liberar reservas (cambiar estado para que no descuenten disponible)
  $res = pg_query_params($conn, "
    UPDATE public.reserva_stock
       SET estado = 'cancelada',
           actualizado_en = NOW()
     WHERE id_pedido = $1
       AND LOWER(estado) = 'activa'
  ", [$id_pedido]);
  if ($res === false) {
    throw new Exception('No se pudieron liberar las reservas.');
  }

  // (Opcional) Registrar un log en una tabla historica si la tenés
  // INSERT INTO public.pedido_evento (id_pedido, tipo, detalle, creado_por) VALUES (...);

  pg_query($conn, "COMMIT");
  echo json_encode(['success'=>true,'message'=>'Pedido anulado y reservas liberadas.','id_pedido'=>$id_pedido]); exit;

} catch (Throwable $e) {
  pg_query($conn, "ROLLBACK");
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]); exit;
}
