<?php
// producto_restaurar.php (activa: estado = 'Activo')
header('Content-Type: application/json; charset=utf-8');

try {
  require __DIR__ . '../../../../conexion/configv2.php'; // Debe definir $conn
  if (!$conn) { throw new Exception('Sin conexión a la base de datos'); }

  $id = isset($_POST['id_producto']) ? (int)$_POST['id_producto'] : 0;
  if ($id <= 0) { throw new Exception('id_producto inválido'); }

  $r = pg_query_params($conn,
    "UPDATE public.producto SET estado='Activo' WHERE id_producto=$1 RETURNING id_producto",
    [$id]
  );
  if (!$r) { throw new Exception(pg_last_error($conn) ?: 'No se pudo activar'); }
  if (pg_num_rows($r) === 0) { throw new Exception('Producto no encontrado'); }

  echo json_encode(["ok"=>true, "msg"=>"Producto activado correctamente"]);
} catch (Throwable $e) {
  http_response_code(200);
  echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
}
