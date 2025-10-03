<?php
// servicios/reclamo/api_reclamo.php
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
function arr($v){ return is_array($v) ? $v : []; }
function normalize_estado($estado){
  $estado = ucfirst(strtolower((string)$estado));
  $validos = ['Abierto','En gestión','Resuelto','Cerrado'];
  return in_array($estado,$validos,true) ? $estado : 'Abierto';
}
function normalize_prioridad($prioridad){
  $prioridad = ucfirst(strtolower((string)$prioridad));
  $validos = ['Baja','Media','Alta'];
  return in_array($prioridad,$validos,true) ? $prioridad : 'Media';
}
function json_error($msg,$code=400){
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
      $estado   = s($in['estado'] ?? '');
      $idCliente= (int)($in['id_cliente'] ?? 0);
      $idProfesional = (int)($in['id_profesional'] ?? 0);
      $desde    = s($in['fecha_desde'] ?? '');
      $hasta    = s($in['fecha_hasta'] ?? '');
      $where=[]; $params=[];

      if($estado!=='' && $estado!=='Todos'){
        $where[] = 'r.estado = $'.(count($params)+1);
        $params[] = ucfirst(strtolower($estado));
      }
      if($idCliente>0){
        $where[] = 'r.id_cliente = $'.(count($params)+1);
        $params[] = $idCliente;
      }
      if($idProfesional>0){
        $where[] = '(ot.id_profesional = $'.(count($params)+1).')';
        $params[] = $idProfesional;
      }
      if($desde!=='' && $hasta!==''){
        $where[] = 'r.fecha_reclamo BETWEEN $'.(count($params)+1).' AND $'.(count($params)+2);
        $params[]=$desde; $params[]=$hasta;
      } elseif($desde!==''){
        $where[] = 'r.fecha_reclamo >= $'.(count($params)+1);
        $params[]=$desde;
      } elseif($hasta!==''){
        $where[] = 'r.fecha_reclamo <= $'.(count($params)+1);
        $params[]=$hasta;
      }

      $sql = "
        SELECT r.id_reclamo,
               r.fecha_reclamo,
               r.estado,
               r.tipo,
               r.canal,
               r.prioridad,
               r.responsable,
               r.id_cliente,
               c.nombre AS cliente_nombre,
               c.apellido AS cliente_apellido,
               r.id_ot,
               ot.id_profesional,
               pr.nombre AS profesional_nombre,
               r.id_reserva
          FROM public.serv_reclamo r
          JOIN public.clientes c ON c.id_cliente = r.id_cliente
          LEFT JOIN public.ot_cab ot ON ot.id_ot = r.id_ot
          LEFT JOIN public.profesional pr ON pr.id_profesional = ot.id_profesional
      ";
      if($where){
        $sql .= ' WHERE '.implode(' AND ',$where);
      }
      $sql .= ' ORDER BY r.fecha_reclamo DESC, r.id_reclamo DESC LIMIT 200';

      $res = $params ? pg_query_params($conn,$sql,$params) : pg_query($conn,$sql);
      if(!$res) json_error('No se pudieron listar reclamos');
      $rows=[];
      while($row = pg_fetch_assoc($res)){
        $row['id_reclamo'] = (int)$row['id_reclamo'];
        $row['id_cliente'] = (int)$row['id_cliente'];
        $row['id_ot'] = $row['id_ot']!==null ? (int)$row['id_ot'] : null;
        $row['id_reserva'] = $row['id_reserva']!==null ? (int)$row['id_reserva'] : null;
        $row['id_profesional'] = $row['id_profesional']!==null ? (int)$row['id_profesional'] : null;
        $rows[]=$row;
      }
      json_ok(['rows'=>$rows]);
    }

    case 'get': {
      $id = (int)($in['id_reclamo'] ?? 0);
      if($id<=0) json_error('id_reclamo requerido');

      $cab = pg_query_params($conn,"
        SELECT r.*,
               c.nombre, c.apellido, c.ruc_ci, c.telefono,
               ot.id_profesional,
               pr.nombre AS profesional_nombre
          FROM public.serv_reclamo r
          JOIN public.clientes c ON c.id_cliente = r.id_cliente
          LEFT JOIN public.ot_cab ot ON ot.id_ot = r.id_ot
          LEFT JOIN public.profesional pr ON pr.id_profesional = ot.id_profesional
         WHERE r.id_reclamo = $1
         LIMIT 1
      ",[$id]);
      if(!$cab || pg_num_rows($cab)===0) json_error('Reclamo no encontrado',404);
      $row = pg_fetch_assoc($cab);
      $row['id_reclamo'] = (int)$row['id_reclamo'];
      $row['id_cliente'] = (int)$row['id_cliente'];
      $row['id_ot'] = $row['id_ot']!==null ? (int)$row['id_ot'] : null;
      $row['id_reserva'] = $row['id_reserva']!==null ? (int)$row['id_reserva'] : null;
      $row['id_profesional'] = $row['id_profesional']!==null ? (int)$row['id_profesional'] : null;

      $hist = pg_query_params($conn,"
        SELECT id_historial, evento, detalle, registrado_por, registrado_en
          FROM public.serv_reclamo_historial
         WHERE id_reclamo = $1
         ORDER BY registrado_en ASC
      ",[$id]);
      $historial=[];
      if($hist){
        while($h = pg_fetch_assoc($hist)){
          $h['id_historial'] = (int)$h['id_historial'];
          $historial[] = $h;
        }
      }
      json_ok(['reclamo'=>$row,'historial'=>$historial]);
    }

    case 'create': {
      $id_cliente = (int)($in['id_cliente'] ?? 0);
      if($id_cliente<=0) json_error('id_cliente requerido');
      $descripcion = s($in['descripcion'] ?? '');
      if($descripcion==='') json_error('Descripción requerida');

      $id_ot = isset($in['id_ot']) && $in['id_ot']!=='' ? (int)$in['id_ot'] : null;
      $id_reserva = isset($in['id_reserva']) && $in['id_reserva']!=='' ? (int)$in['id_reserva'] : null;
      $canal = s($in['canal'] ?? 'Otro') ?: 'Otro';
      $tipo  = s($in['tipo'] ?? 'General') ?: 'General';
      $prioridad = normalize_prioridad($in['prioridad'] ?? 'Media');
      $estado = normalize_estado($in['estado'] ?? 'Abierto');
      $responsable = s($in['responsable'] ?? null);
      $creado_por = $_SESSION['nombre_usuario'] ?? null;

      $sql = "
        INSERT INTO public.serv_reclamo
          (id_ot,id_reserva,id_cliente,fecha_reclamo,canal,tipo,descripcion,estado,prioridad,responsable,creado_por)
        VALUES ($1,$2,$3,now(),$4,$5,$6,$7,$8,$9,$10)
        RETURNING id_reclamo
      ";
      $res = pg_query_params($conn,$sql,[
        $id_ot,
        $id_reserva,
        $id_cliente,
        $canal,
        $tipo,
        $descripcion,
        $estado,
        $prioridad,
        $responsable,
        $creado_por
      ]);
      if(!$res) json_error('No se pudo crear el reclamo');
      $id_reclamo = (int)pg_fetch_result($res,0,0);
      json_ok(['id_reclamo'=>$id_reclamo]);
    }

    case 'update': {
      $id_reclamo = (int)($in['id_reclamo'] ?? 0);
      if($id_reclamo<=0) json_error('id_reclamo requerido');

      $campos = [];
      $params = [];
      if(isset($in['canal'])){
        $campos[] = 'canal = $'.(count($params)+1);
        $params[] = s($in['canal']) ?: 'Otro';
      }
      if(isset($in['tipo'])){
        $campos[] = 'tipo = $'.(count($params)+1);
        $params[] = s($in['tipo']) ?: 'General';
      }
      if(isset($in['descripcion'])){
        $desc = s($in['descripcion']);
        if($desc==='') json_error('Descripción no puede quedar vacía');
        $campos[] = 'descripcion = $'.(count($params)+1);
        $params[] = $desc;
      }
      if(isset($in['prioridad'])){
        $campos[] = 'prioridad = $'.(count($params)+1);
        $params[] = normalize_prioridad($in['prioridad']);
      }
      if(isset($in['responsable'])){
        $campos[] = 'responsable = $'.(count($params)+1);
        $params[] = s($in['responsable']) ?: null;
      }
      if(isset($in['resolucion'])){
        $campos[] = 'resolucion = $'.(count($params)+1);
        $params[] = s($in['resolucion']) ?: null;
      }
      if(isset($in['id_ot'])){
        $campos[] = 'id_ot = $'.(count($params)+1);
        $params[] = ($in['id_ot']!=='' ? (int)$in['id_ot'] : null);
      }
      if(isset($in['id_reserva'])){
        $campos[] = 'id_reserva = $'.(count($params)+1);
        $params[] = ($in['id_reserva']!=='' ? (int)$in['id_reserva'] : null);
      }

      if(empty($campos)) json_error('No hay campos para actualizar');
      $campos[] = 'actualizado_en = now()';
      $params[] = $id_reclamo;

      $sql = "UPDATE public.serv_reclamo SET ".implode(',',$campos)." WHERE id_reclamo = $".count($params);
      $res = pg_query_params($conn,$sql,$params);
      if(!$res) json_error('No se pudo actualizar el reclamo');
      json_ok();
    }

    case 'change_state': {
      $id_reclamo = (int)($in['id_reclamo'] ?? 0);
      if($id_reclamo<=0) json_error('id_reclamo requerido');
      $estado = normalize_estado($in['estado'] ?? 'Abierto');

      $params = [$estado, $id_reclamo];
      $set = "estado = $1, actualizado_en=now()";
      if($estado==='Resuelto' || $estado==='Cerrado'){
        $set .= ", fecha_cierre = now()";
      } else {
        $set .= ", fecha_cierre = NULL";
      }

      $res = pg_query_params($conn,"
        UPDATE public.serv_reclamo
           SET $set
         WHERE id_reclamo = $2
         RETURNING estado
      ", $params);
      if(!$res || pg_num_rows($res)===0) json_error('No se pudo cambiar el estado');
      json_ok(['estado'=>pg_fetch_result($res,0,0)]);
    }

    case 'add_historial': {
      $id_reclamo = (int)($in['id_reclamo'] ?? 0);
      if($id_reclamo<=0) json_error('id_reclamo requerido');
      $evento = s($in['evento'] ?? '');
      $detalle = s($in['detalle'] ?? '');
      if($evento==='') json_error('Evento requerido');
      if($detalle==='') json_error('Detalle requerido');
      $registrado_por = $_SESSION['nombre_usuario'] ?? null;

      $sql = "
        INSERT INTO public.serv_reclamo_historial
          (id_reclamo, evento, detalle, registrado_por)
        VALUES ($1,$2,$3,$4)
      ";
      $res = pg_query_params($conn,$sql,[$id_reclamo,$evento,$detalle,$registrado_por]);
      if(!$res) json_error('No se pudo registrar en historial');
      json_ok();
    }

    case 'delete': {
      $id_reclamo = (int)($in['id_reclamo'] ?? 0);
      if($id_reclamo<=0) json_error('id_reclamo requerido');
      $estado = pg_query_params($conn,"SELECT estado FROM public.serv_reclamo WHERE id_reclamo=$1",[$id_reclamo]);
      if(!$estado || pg_num_rows($estado)===0) json_error('Reclamo no encontrado',404);
      $estadoActual = pg_fetch_result($estado,0,0);
      if($estadoActual!=='Abierto') json_error('Sólo se pueden eliminar reclamos en estado Abierto');
      $res = pg_query_params($conn,"DELETE FROM public.serv_reclamo WHERE id_reclamo=$1",[$id_reclamo]);
      if(!$res) json_error('No se pudo eliminar el reclamo');
      json_ok();
    }

    default:
      json_error('op no reconocido');
  }
} catch(Throwable $e){
  $msg = $e->getMessage();
  if (stripos($msg,'constraint')!==false) {
    $msg = 'Error de integridad de datos.';
  }
  json_error($msg);
}
