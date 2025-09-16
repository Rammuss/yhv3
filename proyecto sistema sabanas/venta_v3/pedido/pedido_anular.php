<?php
// === pedido_anular.php ===
session_start();
require_once __DIR__ . '/../../conexion/configv2.php'; // $conn (pg_connect)

header('Content-Type: application/json; charset=utf-8');

// --- Auth & método ---
if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['success'=>false,'msg'=>'No autorizado']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false,'msg'=>'Método no permitido']); exit;
}

// --- Input ---
$id_pedido = (int)($_POST['id_pedido'] ?? 0);
$motivo    = trim($_POST['motivo'] ?? '');
if ($id_pedido <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'msg'=>'Falta id_pedido']); exit;
}

if (!pg_query($conn, "BEGIN")) {
  http_response_code(500);
  echo json_encode(['success'=>false,'msg'=>'No se pudo iniciar transacción']); exit;
}

try {
  // Bloquear cabecera y leer estado
  $qCab = pg_query_params(
    $conn,
    "SELECT estado FROM public.pedido_cab WHERE id_pedido=$1 FOR UPDATE",
    [$id_pedido]
  );
  if (!$qCab || pg_num_rows($qCab)===0) throw new Exception('Pedido no existe.');

  $estado_actual = strtolower(pg_fetch_result($qCab,0,0));
  if ($estado_actual === 'anulado') throw new Exception('El pedido ya está anulado.');
  if ($estado_actual !== 'pendiente') throw new Exception('Sólo se pueden anular pedidos en estado PENDIENTE.');

  $usuario = $_SESSION['nombre_usuario'] ?? 'web';

  // Actualizar cabecera usando columnas existentes en tu tabla
  $updCab = pg_query_params(
    $conn,
    "UPDATE public.pedido_cab
        SET estado = 'anulado',
            motivo_anulacion = CASE WHEN $2 <> '' THEN $2 ELSE motivo_anulacion END,
            anulado_por = $3,
            anulado_en = NOW(),
            actualizado_en = NOW()
      WHERE id_pedido = $1",
    [$id_pedido, $motivo, $usuario]
  );
  if (!$updCab || pg_affected_rows($updCab)===0) throw new Exception('No se pudo anular el pedido.');

  // Pasar reservas activas a ANULADA (libera stock disponible en tu lógica)
  $updRes = pg_query_params(
    $conn,
    "UPDATE public.reserva_stock
        SET estado = 'cancelada',
            actualizado_en = NOW()
      WHERE id_pedido = $1
        AND LOWER(estado)='activa'",
    [$id_pedido]
  );
  if ($updRes === false) throw new Exception('No se pudieron anular las reservas.');

  if (!pg_query($conn, "COMMIT")) throw new Exception('No se pudo confirmar la transacción.');

  echo json_encode([
    'success'=>true,
    'id_pedido'=>$id_pedido,
    'msg'=>'Pedido y reservas anulados correctamente.'
  ]);
  exit;

} catch (Throwable $e) {
  pg_query($conn, "ROLLBACK");
  http_response_code(400);
  echo json_encode(['success'=>false,'msg'=>$e->getMessage()]);
  exit;
}
