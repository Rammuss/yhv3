<?php
// proveedor_guardar.php
header('Content-Type: application/json; charset=utf-8');

// Limpia buffers para que no se mezclen ecos/avisos con el JSON
while (ob_get_level()) { ob_end_clean(); }

// (Opcional) mostrar errores en desarrollo, pero atraparlos en JSON
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

$response = ['ok' => false];

try {
  require __DIR__ . '../../../../conexion/configv2.php'; // <-- AJUSTÁ ESTA RUTA
  if (!$conn) { throw new Exception('Sin conexión a la base de datos'); }

  // Tomar datos
  $id_proveedor = isset($_POST['id_proveedor']) && $_POST['id_proveedor'] !== '' ? (int)$_POST['id_proveedor'] : null;
  $nombre       = trim($_POST['nombre'] ?? '');
  $ruc          = trim($_POST['ruc'] ?? '');
  $telefono     = trim($_POST['telefono'] ?? '');
  $email        = trim($_POST['email'] ?? '');
  $direccion    = trim($_POST['direccion'] ?? '');
  $id_pais      = isset($_POST['id_pais']) ? (int)$_POST['id_pais'] : null;
  $id_ciudad    = isset($_POST['id_ciudad']) ? (int)$_POST['id_ciudad'] : null;
  $tipo         = trim($_POST['tipo'] ?? 'PROVEEDOR');

  // Validaciones mínimas
  if ($nombre === '' || $ruc === '' || $telefono === '' || $email === '' || $direccion === '' || !$id_pais || !$id_ciudad) {
    throw new Exception('Campos obligatorios incompletos');
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Email inválido');
  }

  // Normalizar tipo
  $tipo = strtoupper($tipo);
  $tipos_ok = ['PROVEEDOR','FONDO_FIJO','SERVICIO','TRANSPORTISTA','OTRO'];
  if (!in_array($tipo, $tipos_ok, true)) {
    throw new Exception('Tipo inválido');
  }

  if ($id_proveedor) {
    // UPDATE
    $sql = "
      UPDATE public.proveedores
         SET nombre=$1,
             direccion=$2,
             telefono=$3,
             email=$4,
             ruc=$5,
             id_pais=$6,
             id_ciudad=$7,
             tipo=$8
       WHERE id_proveedor=$9
       RETURNING id_proveedor
    ";
    $params = [$nombre,$direccion,$telefono,$email,$ruc,$id_pais,$id_ciudad,$tipo,$id_proveedor];
    $res = pg_query_params($conn, $sql, $params);
    if(!$res){ throw new Exception(pg_last_error($conn) ?: 'Error actualizando proveedor'); }
    if(pg_num_rows($res)===0){ throw new Exception('Proveedor no encontrado'); }

    $response['ok']  = true;
    $response['msg'] = 'Proveedor actualizado';
    $response['id']  = (int)pg_fetch_result($res, 0, 0);
  } else {
    // INSERT
    $sql = "
      INSERT INTO public.proveedores
        (nombre,direccion,telefono,email,ruc,id_pais,id_ciudad,tipo,estado,deleted_at)
      VALUES ($1,$2,$3,$4,$5,$6,$7,$8,'Activo',NULL)
      RETURNING id_proveedor
    ";
    $params = [$nombre,$direccion,$telefono,$email,$ruc,$id_pais,$id_ciudad,$tipo];
    $res = pg_query_params($conn, $sql, $params);
    if(!$res){
      // Si es por índice único (ruc) activo, devolvés mensaje claro
      $err = pg_last_error($conn);
      if (strpos($err,'uniq_proveedores_ruc_active') !== false) {
        throw new Exception('Ya existe un proveedor activo con ese RUC');
      }
      throw new Exception($err ?: 'Error insertando proveedor');
    }

    $response['ok']  = true;
    $response['msg'] = 'Proveedor creado';
    $response['id']  = (int)pg_fetch_result($res, 0, 0);
  }

  echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  // Devolver SIEMPRE JSON en errores
  $response['ok'] = false;
  $response['error'] = $e->getMessage();
  echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
