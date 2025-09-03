<?php
// proveedor_eliminar.php (soft delete)
header('Content-Type: application/json; charset=utf-8');

try {
  require __DIR__ . '../../../../conexion/configv2.php'; // $conn
  if (!$conn) { echo json_encode(["ok"=>false,"error"=>"Sin conexión"]); exit; }

  $id = isset($_POST['id_proveedor']) && ctype_digit($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : 0;
  if ($id <= 0) { echo json_encode(["ok"=>false,"error"=>"id_proveedor inválido"]); exit; }

  // Si ya está borrado, devolvemos OK idempotente
  $chk = pg_query_params($conn,
    "SELECT deleted_at FROM public.proveedores WHERE id_proveedor=$1",
    [$id]
  );
  if (!$chk || pg_num_rows($chk)===0) { echo json_encode(["ok"=>false,"error"=>"Proveedor no encontrado"]); exit; }
  if (!pg_fetch_result($chk,0,'deleted_at')) {
    // marcar como eliminado
    $q = pg_query_params($conn, "
      UPDATE public.proveedores
         SET estado='Inactivo', deleted_at = NOW()
       WHERE id_proveedor=$1
    ", [$id]);
    if (!$q) { echo json_encode(["ok"=>false,"error"=>pg_last_error($conn)]); exit; }
  }
  echo json_encode(["ok"=>true,"msg"=>"Proveedor eliminado (soft delete)"]);
} catch (Throwable $e) {
  echo json_encode(["ok"=>false,"error"=>$e->getMessage()]);
}
