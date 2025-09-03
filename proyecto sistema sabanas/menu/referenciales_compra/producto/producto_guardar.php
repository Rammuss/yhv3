<?php
header('Content-Type: application/json; charset=utf-8');

try {
  require __DIR__ . '../../../../conexion/configv2.php'; // $conn
  if (!$conn) { throw new Exception('Sin conexión'); }

  $id_producto    = isset($_POST['id_producto']) && $_POST['id_producto'] !== '' ? (int)$_POST['id_producto'] : null;
  $nombre         = trim($_POST['nombre'] ?? '');
  $precio_unit    = $_POST['precio_unitario'] ?? null;
  $precio_compra  = $_POST['precio_compra'] ?? null;
  $estado         = trim($_POST['estado'] ?? 'Activo');
  $tipo_iva       = trim($_POST['tipo_iva'] ?? '10%');
  $categoria      = trim($_POST['categoria'] ?? '');

  if ($nombre==='') { throw new Exception('Nombre requerido'); }
  if (!is_numeric($precio_unit) || !is_numeric($precio_compra)) { throw new Exception('Precios inválidos'); }
  if ($precio_unit < 0 || $precio_compra < 0) { throw new Exception('Precios no pueden ser negativos'); }

  $tipo_iva = in_array($tipo_iva, ['10%','5%','Exento'], true) ? $tipo_iva : '10%';
  $estado   = in_array($estado, ['Activo','Inactivo'], true) ? $estado : 'Activo';

  if ($id_producto) {
    $sql = "
      UPDATE public.producto
         SET nombre=$1, precio_unitario=$2, precio_compra=$3, estado=$4,
             tipo_iva=$5, categoria=$6
       WHERE id_producto=$7
       RETURNING id_producto
    ";
    $params = [$nombre,$precio_unit,$precio_compra,$estado,$tipo_iva,$categoria,$id_producto];
    $r = pg_query_params($conn,$sql,$params);
    if(!$r){ throw new Exception(pg_last_error($conn) ?: 'No se pudo actualizar'); }
    echo json_encode(["ok"=>true,"msg"=>"Producto actualizado","id"=>(int)pg_fetch_result($r,0,0)], JSON_UNESCAPED_UNICODE);
  } else {
    $sql = "
      INSERT INTO public.producto
        (nombre,precio_unitario,precio_compra,estado,tipo_iva,categoria)
      VALUES ($1,$2,$3,$4,$5,$6)
      RETURNING id_producto
    ";
    $params = [$nombre,$precio_unit,$precio_compra,$estado,$tipo_iva,$categoria];
    $r = pg_query_params($conn,$sql,$params);
    if(!$r){ throw new Exception(pg_last_error($conn) ?: 'No se pudo insertar'); }
    echo json_encode(["ok"=>true,"msg"=>"Producto creado","id"=>(int)pg_fetch_result($r,0,0)], JSON_UNESCAPED_UNICODE);
  }
} catch(Throwable $e){
  echo json_encode(["ok"=>false,"error"=>$e->getMessage()]);
}
