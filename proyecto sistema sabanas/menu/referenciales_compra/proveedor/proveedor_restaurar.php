<?php
// proveedor_restaurar.php
header('Content-Type: application/json; charset=utf-8');
try{
  require __DIR__ . '../../../../conexion/configv2.php'; // $conn
  if (!$conn) { echo json_encode(["ok"=>false,"error"=>"Sin conexión"]); exit; }

  $id = isset($_POST['id_proveedor']) && ctype_digit($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : 0;
  if ($id<=0) { echo json_encode(["ok"=>false,"error"=>"id_proveedor inválido"]); exit; }

  // Verificar que no exista otro activo con mismo RUC
  $res = pg_query_params($conn, "
    SELECT ruc FROM public.proveedores WHERE id_proveedor=$1
  ", [$id]);
  if(!$res || pg_num_rows($res)===0){ echo json_encode(["ok"=>false,"error"=>"Proveedor no encontrado"]); exit; }
  $ruc = pg_fetch_result($res,0,0);

  $dup = pg_query_params($conn, "
    SELECT 1 FROM public.proveedores
     WHERE ruc=$1 AND deleted_at IS NULL AND id_proveedor<>$2
     LIMIT 1
  ", [$ruc, $id]);
  if ($dup && pg_num_rows($dup)>0){
    echo json_encode(["ok"=>false,"error"=>"No se puede restaurar: ya existe un proveedor activo con ese RUC"]); exit;
  }

  $q = pg_query_params($conn, "
    UPDATE public.proveedores
       SET estado='Activo', deleted_at=NULL
     WHERE id_proveedor=$1
  ", [$id]);
  if(!$q){ echo json_encode(["ok"=>false,"error"=>pg_last_error($conn)]); exit; }

  echo json_encode(["ok"=>true,"msg"=>"Proveedor restaurado"]);
}catch(Throwable $e){
  echo json_encode(["ok"=>false,"error"=>$e->getMessage()]);
}
