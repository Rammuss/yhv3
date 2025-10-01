<?php
// api_ot.php — API (POST) para Orden de Trabajo

session_start();
require_once __DIR__ . '/../../conexion/configv2.php'; // <-- AJUSTAR
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function json_error($msg,$code=400){
  http_response_code($code);
  echo json_encode(['success'=>false,'error'=>$msg]);
  exit;
}
function json_ok($data=[]){
  echo json_encode(['success'=>true]+$data);
  exit;
}
function s($x){ return is_string($x)?trim($x):null; }
function arr($x){ return is_array($x)?$x:[]; }

function normalizar_estado($estado){
  $estado = ucfirst(strtolower($estado));
  $validos = ['Programada','En ejecución','Completada','Cancelada'];
  return in_array($estado,$validos,true) ? $estado : 'Programada';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_error('Método no permitido',405);
}

$in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$op = strtolower(s($in['op'] ?? ''));
if ($op==='') json_error('Parámetro op requerido');

try {
  switch ($op) {

    case 'list_profesionales': {
      $r = pg_query($conn, "SELECT id_profesional, nombre FROM public.profesional WHERE estado='Activo' ORDER BY nombre");
      if(!$r) json_error('No se pudo listar profesionales');
      $rows=[]; while($x=pg_fetch_assoc($r)) $rows[] = $x;
      json_ok(['rows'=>$rows]);
    }

    case 'list_ot': {
      $estado = s($in['estado'] ?? '');
      $fecha  = s($in['fecha'] ?? '');
      $where  = [];
      $params = [];
      if($estado!=='' && $estado!=='Todos'){
        $estado = normalizar_estado($estado);
        $where[] = 'ot.estado = $'.(count($params)+1);
        $params[] = $estado;
      }
      if($fecha!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
        $where[] = 'ot.fecha_programada = $'.(count($params)+1);
        $params[] = $fecha;
      }
      $sql = "SELECT ot.id_ot,
                     ot.fecha_programada,
                     ot.hora_programada,
                     ot.estado,
                     c.nombre||' '||COALESCE(c.apellido,'') AS cliente,
                     p.nombre AS profesional,
                     ot.id_reserva
              FROM public.ot_cab ot
              JOIN public.clientes c ON c.id_cliente = ot.id_cliente
              LEFT JOIN public.profesional p ON p.id_profesional = ot.id_profesional";
      if($where){
        $sql .= " WHERE ".implode(' AND ',$where);
      }
      $sql .= " ORDER BY ot.fecha_programada DESC, ot.hora_programada DESC, ot.id_ot DESC LIMIT 200";
      $r = $params ? pg_query_params($conn,$sql,$params) : pg_query($conn,$sql);
      if(!$r) json_error('No se pudieron listar las OT');
      $rows=[];
      while($x=pg_fetch_assoc($r)){
        $x['id_ot'] = (int)$x['id_ot'];
        $rows[] = $x;
      }
      json_ok(['rows'=>$rows]);
    }

    case 'search_reservas': {
      $q      = s($in['q'] ?? '');
      $fecha  = s($in['fecha'] ?? '');
      $estado = s($in['estado'] ?? 'Confirmada');
      $limit  = max(1, min(100, (int)($in['limit'] ?? 30)));

      $params = [];
      $where  = [];

      if($estado!=='' && $estado!=='Todos'){
        $where[] = 'rc.estado = $'.(count($params)+1);
        $params[] = ucfirst(strtolower($estado));
      }else{
        $where[] = "rc.estado IN ('Confirmada','Pendiente')";
      }

      if($q!==''){
        $where[] = "(c.nombre ILIKE $".(count($params)+1)." OR c.apellido ILIKE $".(count($params)+1)." OR CAST(rc.id_reserva AS TEXT) ILIKE $".(count($params)+1).")";
        $params[] = '%'.$q.'%';
      }

      if($fecha!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)){
        $where[] = "(rc.inicio_ts AT TIME ZONE 'America/Asuncion')::date = $".(count($params)+1)."::date";
        $params[] = $fecha;
      }

      $sql = "SELECT rc.id_reserva,
                     rc.inicio_ts,
                     rc.fin_ts,
                     rc.estado,
                     c.nombre||' '||COALESCE(c.apellido,'') AS cliente,
                     p.nombre AS profesional
              FROM public.reserva_cab rc
              JOIN public.clientes c ON c.id_cliente = rc.id_cliente
              LEFT JOIN public.profesional p ON p.id_profesional = rc.id_profesional";
      if($where){
        $sql .= ' WHERE '.implode(' AND ',$where);
      }
      $sql .= ' ORDER BY rc.inicio_ts DESC LIMIT '.$limit;

      $r = $params ? pg_query_params($conn,$sql,$params) : pg_query($conn,$sql);
      if(!$r) json_error('No se pudieron listar reservas');

      $rows=[]; while($x=pg_fetch_assoc($r)){
        $x['id_reserva'] = (int)$x['id_reserva'];
        $rows[] = $x;
      }
      json_ok(['rows'=>$rows]);
    }
    case 'get_ot': {
      $id_ot = (int)($in['id_ot'] ?? 0);
      if($id_ot<=0) json_error('id_ot requerido');
      $sql = "SELECT ot.*, 
                     c.nombre||' '||COALESCE(c.apellido,'') AS cliente_nombre,
                     p.nombre AS profesional_nombre,
                     r.inicio_ts AS reserva_inicio,
                     r.fin_ts    AS reserva_fin
              FROM public.ot_cab ot
              JOIN public.clientes c ON c.id_cliente = ot.id_cliente
              LEFT JOIN public.profesional p ON p.id_profesional = ot.id_profesional
              LEFT JOIN public.reserva_cab r ON r.id_reserva = ot.id_reserva
              WHERE ot.id_ot = $1";
      $r = pg_query_params($conn,$sql,[$id_ot]);
      if(!$r || pg_num_rows($r)===0) json_error('OT no encontrada',404);
      $cab = pg_fetch_assoc($r);

      $sqlDet = "SELECT item_nro, id_producto, descripcion, tipo_item, cantidad,
                        precio_unitario, tipo_iva, duracion_min, observaciones
               FROM public.ot_det
               WHERE id_ot=$1
               ORDER BY item_nro";
      $det=[];
      $rDet = pg_query_params($conn,$sqlDet,[$id_ot]);
      while($row=pg_fetch_assoc($rDet)){
        $row['item_nro'] = (int)$row['item_nro'];
        $row['id_producto'] = $row['id_producto']!==null ? (int)$row['id_producto'] : null;
        $row['cantidad'] = (float)$row['cantidad'];
        $row['precio_unitario'] = (float)$row['precio_unitario'];
        $row['duracion_min'] = (int)$row['duracion_min'];
        $det[]=$row;
      }

      $sqlIns = "SELECT item_nro, id_producto, cantidad, deposito, lote, comentario
                 FROM public.ot_insumo
                 WHERE id_ot=$1
                 ORDER BY item_nro";
      $ins=[];
      $rIns = pg_query_params($conn,$sqlIns,[$id_ot]);
      while($row=pg_fetch_assoc($rIns)){
        $row['item_nro'] = (int)$row['item_nro'];
        $row['id_producto'] = (int)$row['id_producto'];
        $row['cantidad'] = (float)$row['cantidad'];
        $ins[]=$row;
      }

      json_ok(['cab'=>$cab,'det'=>$det,'insumos'=>$ins]);
    }

    case 'list_catalogos': {
      $sqlServ = "SELECT id_producto, nombre, tipo_item, COALESCE(duracion_min,30) AS duracion_min,
                         precio_unitario, COALESCE(tipo_iva,'EXE') AS tipo_iva
                  FROM public.producto
                  WHERE estado='Activo' AND tipo_item IN ('S','D')
                  ORDER BY CASE WHEN tipo_item='S' THEN 0 ELSE 1 END, nombre";
      $sqlIns = "WITH stock AS (
                   SELECT id_producto,
                          COALESCE(SUM(CASE WHEN tipo_movimiento='entrada' THEN cantidad
                                            WHEN tipo_movimiento='salida' THEN -cantidad
                                            ELSE 0 END),0) AS stock_actual
                     FROM public.movimiento_stock
                    GROUP BY id_producto
                 )
                 SELECT p.id_producto,
                        p.nombre,
                        p.precio_unitario,
                        COALESCE(s.stock_actual,0) AS stock_actual
                   FROM public.producto p
                   LEFT JOIN stock s ON s.id_producto = p.id_producto
                  WHERE p.estado='Activo' AND p.tipo_item='P'
                  ORDER BY p.nombre";
      $serv = pg_query($conn,$sqlServ);
      $ins  = pg_query($conn,$sqlIns);
      if(!$serv || !$ins) json_error('No se pudieron cargar catálogos');

      $servRows=[]; while($row=pg_fetch_assoc($serv)){
        $row['id_producto'] = (int)$row['id_producto'];
        $row['duracion_min']= (int)$row['duracion_min'];
        $row['precio_unitario'] = (float)$row['precio_unitario'];
        $row['tipo_iva'] = strtoupper(substr($row['tipo_iva'],0,3));
        $servRows[] = $row;
      }

      $insRows=[]; while($row=pg_fetch_assoc($ins)){
        $row['id_producto'] = (int)$row['id_producto'];
        $row['precio_unitario'] = (float)$row['precio_unitario'];
        $row['stock_actual'] = (int)$row['stock_actual'];
        $insRows[] = $row;
      }

      json_ok(['servicios'=>$servRows,'insumos'=>$insRows]);
    }

    case 'create_from_reserva': {
      $id_reserva = (int)($in['id_reserva'] ?? 0);
      if($id_reserva<=0) json_error('id_reserva requerido');

      $sql = "SELECT rc.*, p.id_profesional
              FROM public.reserva_cab rc
              LEFT JOIN public.profesional p ON p.id_profesional = rc.id_profesional
              WHERE rc.id_reserva = $1 LIMIT 1";
      $r = pg_query_params($conn,$sql,[$id_reserva]);
      if(!$r || pg_num_rows($r)===0) json_error('Reserva no encontrada',404);

      $row = pg_fetch_assoc($r);
      $id_cliente = (int)$row['id_cliente'];
      $id_prof_original = $row['id_profesional']!==null ? (int)$row['id_profesional'] : null;
      $fecha_prog = $row['fecha_reserva'];
      $hora_prog  = substr($row['inicio_ts'],11,5);

      $ya = pg_query_params($conn,"SELECT id_ot FROM public.ot_cab WHERE id_reserva=$1 LIMIT 1",[$id_reserva]);
      if($ya && pg_num_rows($ya)>0){
        $existing = (int)pg_fetch_result($ya,0,0);
        json_ok(['id_ot'=>$existing,'mensaje'=>'Ya existía una OT para la reserva']);
      }

      pg_query($conn,'BEGIN');

      $sqlCab = "INSERT INTO public.ot_cab
                   (id_reserva,id_cliente,id_profesional,fecha_programada,hora_programada,
                    estado,notas,creado_el)
                 VALUES ($1,$2,$3,$4,$5,'Programada',$6,now())
                 RETURNING id_ot";
      $rCab = pg_query_params($conn,$sqlCab,[
        $id_reserva,
        $id_cliente,
        $id_prof_original,
        $fecha_prog,
        $hora_prog,
        $row['notas']
      ]);
      if(!$rCab){ pg_query($conn,'ROLLBACK'); json_error('No se pudo crear OT'); }
      $id_ot = (int)pg_fetch_result($rCab,0,0);

      $sqlDet = "SELECT rd.id_producto,
                        rd.descripcion,
                        rd.precio_unitario,
                        rd.tipo_iva,
                        rd.duracion_min,
                        rd.cantidad,
                        pr.tipo_item
                 FROM public.reserva_det rd
                 LEFT JOIN public.producto pr ON pr.id_producto = rd.id_producto
                 WHERE rd.id_reserva=$1
                 ORDER BY rd.descripcion";
      $rDet = pg_query_params($conn,$sqlDet,[$id_reserva]);
      while($det=pg_fetch_assoc($rDet)){
        $tipo_item = $det['tipo_item'] ?? 'S';
        if(!in_array($tipo_item,['S','D'],true)) $tipo_item = 'S';
        $tipo_iva = $det['tipo_iva'] ?? 'EXE';
        $tipo_iva = strtoupper(substr($tipo_iva,0,3));

        $sqlInsDet = "INSERT INTO public.ot_det
                       (id_ot, id_producto, descripcion, tipo_item, cantidad,
                        precio_unitario, tipo_iva, duracion_min)
                      VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
        $ok = pg_query_params($conn,$sqlInsDet,[
          $id_ot,
          $det['id_producto']!==null?(int)$det['id_producto']:null,
          $det['descripcion'],
          $tipo_item,
          (float)$det['cantidad'],
          (float)$det['precio_unitario'],
          $tipo_iva,
          (int)$det['duracion_min']
        ]);
        if(!$ok){ pg_query($conn,'ROLLBACK'); json_error('Error copiando detalle de reserva'); }
      }

      pg_query($conn,'COMMIT');
      json_ok(['id_ot'=>$id_ot]);
    }
    case 'save_ot_item': {
      $id_ot    = (int)($in['id_ot'] ?? 0);
      $item_nro = (int)($in['item_nro'] ?? 0);
      $id_prod  = $in['id_producto'] !== null ? (int)$in['id_producto'] : null;
      $desc     = s($in['descripcion'] ?? '');
      $tipo     = strtoupper(s($in['tipo_item'] ?? 'S'));
      $cant     = (float)($in['cantidad'] ?? 1);
      $precio   = (float)($in['precio_unitario'] ?? 0);
      $tipo_iva = strtoupper(substr(s($in['tipo_iva'] ?? 'EXE'),0,3));
      $dur      = (int)($in['duracion_min'] ?? 0);
      $obs      = s($in['observaciones'] ?? null);

      if($id_ot<=0) json_error('id_ot requerido');
      if($desc==='') json_error('Descripción requerida');
      if(!in_array($tipo,['S','D'],true)) json_error('tipo_item inválido');
      if($cant<=0) json_error('Cantidad inválida');
      if($tipo_iva==='') $tipo_iva = 'EXE';

      if($item_nro<=0){
        $sql = "INSERT INTO public.ot_det
                  (id_ot, id_producto, descripcion, tipo_item, cantidad,
                   precio_unitario, tipo_iva, duracion_min, observaciones)
                VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9)
                RETURNING item_nro";
        $res = pg_query_params($conn,$sql,[
          $id_ot,$id_prod,$desc,$tipo,$cant,$precio,$tipo_iva,$dur,$obs
        ]);
        if(!$res) json_error('No se pudo agregar el ítem');
        $item_nro = (int)pg_fetch_result($res,0,0);
      }else{
        $sql = "UPDATE public.ot_det
                   SET id_producto=$3,
                       descripcion=$4,
                       tipo_item=$5,
                       cantidad=$6,
                       precio_unitario=$7,
                       tipo_iva=$8,
                       duracion_min=$9,
                       observaciones=$10
                 WHERE id_ot=$1 AND item_nro=$2";
        $res = pg_query_params($conn,$sql,[
          $id_ot,$item_nro,$id_prod,$desc,$tipo,$cant,$precio,$tipo_iva,$dur,$obs
        ]);
        if(!$res) json_error('No se pudo actualizar el ítem');
      }
      json_ok(['item_nro'=>$item_nro]);
    }

    case 'delete_ot_item': {
      $id_ot = (int)($in['id_ot'] ?? 0);
      $item  = (int)($in['item_nro'] ?? 0);
      if($id_ot<=0 || $item<=0) json_error('id_ot y item_nro requeridos');
      $sql = "DELETE FROM public.ot_det WHERE id_ot=$1 AND item_nro=$2";
      $res = pg_query_params($conn,$sql,[$id_ot,$item]);
      if(!$res) json_error('No se pudo eliminar el ítem');
      json_ok();
    }

    case 'save_ot_insumo': {
      $id_ot    = (int)($in['id_ot'] ?? 0);
      $item_nro = (int)($in['item_nro'] ?? 0);
      $id_prod  = (int)($in['id_producto'] ?? 0);
      $cant     = (float)($in['cantidad'] ?? 0);
      $dep      = s($in['deposito'] ?? null);
      $lote     = s($in['lote'] ?? null);
      $coment   = s($in['comentario'] ?? null);

      if($id_ot<=0) json_error('id_ot requerido');
      if($id_prod<=0) json_error('id_producto requerido');
      if($cant<=0) json_error('Cantidad inválida');

      if($item_nro<=0){
        $sql = "INSERT INTO public.ot_insumo
                  (id_ot, id_producto, cantidad, deposito, lote, comentario)
                VALUES ($1,$2,$3,$4,$5,$6)
                RETURNING item_nro";
        $res = pg_query_params($conn,$sql,[
          $id_ot,$id_prod,$cant,$dep,$lote,$coment
        ]);
        if(!$res) json_error('No se pudo agregar el insumo');
        $item_nro = (int)pg_fetch_result($res,0,0);
      }else{
        $sql = "UPDATE public.ot_insumo
                   SET id_producto=$3,
                       cantidad=$4,
                       deposito=$5,
                       lote=$6,
                       comentario=$7
                 WHERE id_ot=$1 AND item_nro=$2";
        $res = pg_query_params($conn,$sql,[
          $id_ot,$item_nro,$id_prod,$cant,$dep,$lote,$coment
        ]);
        if(!$res) json_error('No se pudo actualizar el insumo');
      }
      json_ok(['item_nro'=>$item_nro]);
    }

    case 'delete_ot_insumo': {
      $id_ot = (int)($in['id_ot'] ?? 0);
      $item  = (int)($in['item_nro'] ?? 0);
      if($id_ot<=0 || $item<=0) json_error('id_ot y item_nro requeridos');
      $sql = "DELETE FROM public.ot_insumo WHERE id_ot=$1 AND item_nro=$2";
      $res = pg_query_params($conn,$sql,[$id_ot,$item]);
      if(!$res) json_error('No se pudo eliminar el insumo');
      json_ok();
    }

    case 'update_ot_state': {
      $id_ot  = (int)($in['id_ot'] ?? 0);
      $estado = normalizar_estado($in['estado'] ?? '');
      if($id_ot<=0) json_error('id_ot requerido');
      $fields = ['estado'=>$estado,'actualizado_el'=>date('c')];
      if($estado==='En ejecución'){
        $fields['inicio_real'] = $in['inicio_real'] ?? date('Y-m-d H:i:s');
      }elseif($estado==='Completada'){
        if(!empty($in['finalizar_con_fecha'])){
          $fields['fin_real'] = date('Y-m-d H:i:s');
        }else{
          $fields['fin_real'] = $in['fin_real'] ?? date('Y-m-d H:i:s');
        }
        if(empty($fields['inicio_real'])){
          $fields['inicio_real'] = $in['inicio_real'] ?? date('Y-m-d H:i:s');
        }
      }
      $set = [];
      $params=[];
      foreach($fields as $k=>$v){
        $set[] = "$k=$".(count($params)+1);
        $params[]=$v;
      }
      $params[]=$id_ot;
      $sql = "UPDATE public.ot_cab SET ".implode(',',$set)." WHERE id_ot=$".count($params);
      $res = pg_query_params($conn,$sql,$params);
      if(!$res) json_error('No se pudo actualizar el estado');
      json_ok();
    }

    case 'update_ot_notas': {
      $id_ot = (int)($in['id_ot'] ?? 0);
      $notas = s($in['notas'] ?? null);
      if($id_ot<=0) json_error('id_ot requerido');
      $sql = "UPDATE public.ot_cab SET notas=$2, actualizado_el=now() WHERE id_ot=$1";
      $res = pg_query_params($conn,$sql,[$id_ot,$notas]);
      if(!$res) json_error('No se pudo actualizar notas');
      json_ok();
    }

    case 'update_ot_prof': {
      $id_ot = (int)($in['id_ot'] ?? 0);
      $id_prof= $in['id_profesional']!==null ? (int)$in['id_profesional'] : null;
      if($id_ot<=0) json_error('id_ot requerido');
      $sql = "UPDATE public.ot_cab SET id_profesional=$2, actualizado_el=now() WHERE id_ot=$1";
      $res = pg_query_params($conn,$sql,[$id_ot,$id_prof]);
      if(!$res) json_error('No se pudo actualizar profesional');
      json_ok();
    }
    default: json_error('op no reconocido');
  }
} catch(Throwable $e){
  $msg = $e->getMessage();
  if (stripos($msg,'constraint')!==false) {
    $msg = 'Violación de constraint: revisá los datos.';
  }
  json_error($msg);
}
?>