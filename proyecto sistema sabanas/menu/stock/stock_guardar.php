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
  $tipo_movimiento = trim($_POST['tipo_movimiento'] ?? ''); // esperado: AJUSTE_POS / AJUSTE_NEG
  $cantidad        = intval($_POST['cantidad'] ?? 0);
  $observacion     = trim($_POST['observacion'] ?? '');

  if ($id_producto <= 0 || $cantidad <= 0) {
    throw new Exception('Producto y cantidad son obligatorios y deben ser válidos.');
  }

  // Validar que venga como ajuste
  $validos = ['AJUSTE_POS', 'AJUSTE_NEG'];
  if (!in_array($tipo_movimiento, $validos, true)) {
    throw new Exception('Tipo de ajuste inválido.');
  }

  // Mapear ajuste -> tipo real (ENTRADA / SALIDA)
  $tipoReal = ($tipo_movimiento === 'AJUSTE_POS') ? 'entrada' : 'salida';

  // Verificar existencia de producto
  $qProd = "SELECT id_producto FROM public.producto WHERE id_producto = $1 LIMIT 1";
  $rProd = pg_query_params($conn, $qProd, [$id_producto]);
  if (!$rProd || pg_num_rows($rProd) === 0) {
    throw new Exception('El producto no existe.');
  }

  pg_query($conn, "BEGIN");

  // Insertar movimiento: cantidad positiva, tipo real
  $qIns = "
    INSERT INTO public.movimiento_stock (id_producto, tipo_movimiento, cantidad, fecha, observacion)
    VALUES ($1, $2, $3, NOW(), $4)
    RETURNING id
  ";
  $rIns = pg_query_params($conn, $qIns, [
    $id_producto,
    $tipoReal,            // guarda ENTRADA o SALIDA
    $cantidad,
    $observacion ?: 'Ajuste manual'
  ]);

  if (!$rIns) {
    throw new Exception('No se pudo registrar el ajuste.');
  }
  $row = pg_fetch_assoc($rIns);
  $mov_id = $row['id'] ?? null;

  pg_query($conn, "COMMIT");

  $response['success'] = true;
  $response['mensaje'] = "Ajuste registrado como $tipoReal (#$mov_id).";
  echo json_encode($response);
  exit;

} catch (Exception $e) {
  pg_query($conn, "ROLLBACK");
  $response['mensaje'] = $e->getMessage();
  echo json_encode($response);
  exit;
}
