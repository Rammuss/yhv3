
<?php
// producto_eliminar.php (soft delete: estado = 'Inactivo')
header('Content-Type: application/json; charset=utf-8');

try {
  require __DIR__ . '../../../../conexion/configv2.php'; // Debe definir $conn
  if (!$conn) { throw new Exception('Sin conexión a la base de datos'); }

  $id = isset($_POST['id_producto']) ? (int)$_POST['id_producto'] : 0;
  if ($id <= 0) { throw new Exception('id_producto inválido'); }

  // (Opcional) Verificar si el producto está involucrado en documentos abiertos.
  // Descomenta si querés bloquear la inactivación cuando tenga pendientes en OCs no anuladas, etc.
  /*
  $q = pg_query_params($conn, "
    SELECT 1
    FROM orden_compra_det d
    JOIN orden_compra_cab c ON c.id_oc = d.id_oc
    WHERE d.id_producto = $1 AND COALESCE(c.estado,'') NOT IN ('Anulada','Totalmente Recibida')
    LIMIT 1
  ", [$id]);
  if ($q && pg_num_rows($q) > 0) {
    throw new Exception('No se puede inactivar: el producto tiene OCs abiertas o pendientes');
  }
  */

  $r = pg_query_params($conn,
    "UPDATE public.producto SET estado='Inactivo' WHERE id_producto=$1 RETURNING id_producto",
    [$id]
  );
  if (!$r) { throw new Exception(pg_last_error($conn) ?: 'No se pudo inactivar'); }
  if (pg_num_rows($r) === 0) { throw new Exception('Producto no encontrado'); }

  echo json_encode(["ok"=>true, "msg"=>"Producto inactivado correctamente"]);
} catch (Throwable $e) {
  http_response_code(200);
  echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
}
