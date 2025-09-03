<?php
// guardar_ajuste_inventario.php
session_start();
header('Content-Type: application/json');
include("../../conexion/configv2.php");

$response = ['success' => false, 'mensaje' => ''];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Método no permitido.');
  }

  $id_producto     = intval($_POST['id_producto'] ?? 0);
  $tipo_movimiento = trim($_POST['tipo_movimiento'] ?? '');
  $cantidad        = intval($_POST['cantidad'] ?? 0);
  $observacion     = trim($_POST['observacion'] ?? '');

  if ($id_producto <= 0 || $cantidad <= 0) {
    throw new Exception('Producto y cantidad son obligatorios y deben ser válidos.');
  }

  // Validar tipo (dejar sólo ajustes para este endpoint)
  $validos = ['AJUSTE_POS', 'AJUSTE_NEG'];
  if (!in_array($tipo_movimiento, $validos, true)) {
    throw new Exception('Tipo de movimiento inválido.');
  }

  // Verificar existencia de producto
  $qProd = "SELECT id_producto FROM public.producto WHERE id_producto = $1 LIMIT 1";
  $rProd = pg_query_params($conn, $qProd, [$id_producto]);
  if (!$rProd || pg_num_rows($rProd) === 0) {
    throw new Exception('El producto no existe.');
  }

  // Transacción (por si luego querés extender lógica)
  pg_query($conn, "BEGIN");

  // Insertar movimiento (cantidad siempre positiva, la semántica la da el tipo)
  $qIns = "
    INSERT INTO public.movimiento_stock (id_producto, tipo_movimiento, cantidad, fecha)
    VALUES ($1, $2, $3, NOW())
    RETURNING id
  ";
  $rIns = pg_query_params($conn, $qIns, [$id_producto, $tipo_movimiento, $cantidad]);
  if (!$rIns) {
    throw new Exception('No se pudo registrar el movimiento.');
  }
  $row = pg_fetch_assoc($rIns);
  $mov_id = $row['id'] ?? null;

  // (Opcional) Registrar observación en una tabla aparte si la tenés.
  // Si querés guardar la observación en esta misma tabla, agregá una columna "observacion text"
  // y actualizá el insert para incluirla.

  pg_query($conn, "COMMIT");

  $response['success'] = true;
  $response['mensaje'] = "Ajuste registrado (#$mov_id).";
  echo json_encode($response);
  exit;

} catch (Exception $e) {
  pg_query($conn, "ROLLBACK");
  $response['mensaje'] = $e->getMessage();
  echo json_encode($response);
  exit;
}
