<?php
// API CRUD para profesionales

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Método no permitido']);
  exit;
}

function s($v){ return is_string($v) ? trim($v) : null; }
function json_error($msg, $code=400){
  http_response_code($code);
  echo json_encode(['success'=>false,'error'=>$msg]);
  exit;
}
function json_ok($data=[]){
  echo json_encode(['success'=>true]+$data);
  exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$op = strtolower(s($in['op'] ?? ''));
if ($op==='') json_error('Parámetro op requerido');

try {
  switch ($op) {

    case 'list': {
      $estado = s($in['estado'] ?? '');
      $where = '';
      $params = [];
      if ($estado!=='' && $estado!=='Todos') {
        $where = ' WHERE estado = $1';
        $params[] = ucfirst(strtolower($estado));
      }
      $sql = "SELECT id_profesional, nombre, telefono, email, estado
              FROM public.profesional
              $where
              ORDER BY nombre";
      $res = $params ? pg_query_params($conn,$sql,$params) : pg_query($conn,$sql);
      if(!$res) json_error('No se pudieron listar profesionales');
      $rows=[]; while($row=pg_fetch_assoc($res)){
        $row['id_profesional']=(int)$row['id_profesional'];
        $rows[]=$row;
      }
      json_ok(['rows'=>$rows]);
    }

    case 'get': {
      $id = (int)($in['id_profesional'] ?? 0);
      if($id<=0) json_error('id_profesional requerido');
      $res = pg_query_params($conn,"SELECT id_profesional,nombre,telefono,email,estado FROM public.profesional WHERE id_profesional=$1",[$id]);
      if(!$res || pg_num_rows($res)===0) json_error('Profesional no encontrado',404);
      $row = pg_fetch_assoc($res);
      $row['id_profesional']=(int)$row['id_profesional'];
      json_ok(['row'=>$row]);
    }

    case 'create': {
      $nombre = s($in['nombre'] ?? '');
      if($nombre==='') json_error('Nombre requerido');
      $tel = s($in['telefono'] ?? null);
      $email = s($in['email'] ?? null);
      $estado = ucfirst(strtolower(s($in['estado'] ?? 'Activo')));
      if(!in_array($estado,['Activo','Inactivo'],true)) $estado='Activo';

      $res = pg_query_params($conn,
        "INSERT INTO public.profesional(nombre,telefono,email,estado)
         VALUES($1,$2,$3,$4) RETURNING id_profesional",
         [$nombre,$tel,$email,$estado]);
      if(!$res) json_error('No se pudo crear profesional');
      $id = (int)pg_fetch_result($res,0,0);
      json_ok(['id_profesional'=>$id]);
    }

    case 'update': {
      $id = (int)($in['id_profesional'] ?? 0);
      if($id<=0) json_error('id_profesional requerido');
      $nombre = s($in['nombre'] ?? '');
      if($nombre==='') json_error('Nombre requerido');
      $tel = s($in['telefono'] ?? null);
      $email = s($in['email'] ?? null);
      $estado = ucfirst(strtolower(s($in['estado'] ?? 'Activo')));
      if(!in_array($estado,['Activo','Inactivo'],true)) $estado='Activo';

      $res = pg_query_params($conn,
        "UPDATE public.profesional
            SET nombre=$2, telefono=$3, email=$4, estado=$5
          WHERE id_profesional=$1",
        [$id,$nombre,$tel,$email,$estado]);
      if(!$res) json_error('No se pudo actualizar profesional');
      json_ok();
    }

    case 'delete': {
      $id = (int)($in['id_profesional'] ?? 0);
      if($id<=0) json_error('id_profesional requerido');
      $res = pg_query_params($conn,"DELETE FROM public.profesional WHERE id_profesional=$1",[$id]);
      if(!$res) json_error('No se pudo eliminar profesional');
      json_ok();
    }

    case 'toggle': {
      $id = (int)($in['id_profesional'] ?? 0);
      if($id<=0) json_error('id_profesional requerido');
      $res = pg_query_params(
        $conn,
        "UPDATE public.profesional
            SET estado = CASE WHEN estado='Activo' THEN 'Inactivo' ELSE 'Activo' END
          WHERE id_profesional=$1
          RETURNING estado",
        [$id]
      );
      if(!$res || pg_num_rows($res)===0) json_error('No se pudo actualizar estado');
      json_ok(['estado'=>pg_fetch_result($res,0,0)]);
    }

    default: json_error('op no reconocido');
  }
} catch(Throwable $e){
  json_error($e->getMessage());
}
