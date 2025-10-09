<?php
/**
 * Fondo Fijo – Reposiciones (tolerante a JSON / form / query)
 */

session_start();
require_once __DIR__ . '../../../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'No autorizado']); exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* -------- Helpers -------- */
function bad(string $m, int $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]); exit; }
function ok(array $p=[]){ echo json_encode(['ok'=>true]+$p); exit; }
function ffloat($v){ return $v===null?0.0:(float)$v; }
function fint($v){ return $v===null?0:(int)$v; }

/** Lee input de cualquier forma: JSON, form-data, urlencoded, y mergea con query */
function read_any(): array {
  $ct = $_SERVER['CONTENT_TYPE'] ?? '';
  $raw = file_get_contents('php://input');
  $data = [];

  // 1) JSON
  if (stripos($ct,'application/json')!==false && $raw!=='') {
    $j = json_decode($raw,true);
    if (is_array($j)) $data = $j;
  }

  // 2) Si no era JSON, probar form urlencoded manual (por si fetch mandó sin FormData)
  if (!$data && stripos($ct,'application/x-www-form-urlencoded')!==false && $raw!=='') {
    parse_str($raw, $tmp);
    if (is_array($tmp)) $data = $tmp;
  }

  // 3) $_POST (soporta multipart/form-data)
  if (!$data && !empty($_POST)) $data = $_POST;

  // 4) Merge con query (no pisa claves ya presentes)
  foreach ($_GET as $k=>$v) if (!isset($data[$k])) $data[$k]=$v;

  return $data;
}

/** Ya existe movimiento FFREP para esta rendición? */
function rendicion_ya_reposicionada($conn, int $id_rendicion): bool {
  $st = pg_query_params($conn,
    "SELECT 1 FROM public.cuenta_pagar_mov WHERE ref_tipo='FFREP' AND ref_id=$1 LIMIT 1",
    [$id_rendicion]
  );
  return ($st && pg_num_rows($st)>0);
}

/** Total aprobado (sólo ítems Aprobado) */
function total_aprobado_rendicion($conn, int $id_rendicion): float {
  $st = pg_query_params($conn,
    "SELECT COALESCE(SUM(total),0) AS t FROM public.ff_rendiciones_items
      WHERE id_rendicion=$1 AND estado_item='Aprobado'",
    [$id_rendicion]
  );
  if (!$st) return 0.0;
  $r = pg_fetch_assoc($st);
  return $r? (float)$r['t'] : 0.0;
}

/** Info rendición + FF */
function get_rendicion_info($conn, int $id_rendicion): ?array {
  $st = pg_query_params($conn,
    "SELECT r.id_rendicion, r.id_ff, r.estado, r.observacion,
            ff.id_proveedor, ff.moneda, ff.nombre_caja
       FROM public.ff_rendiciones r
       JOIN public.fondo_fijo ff ON ff.id_ff=r.id_ff
      WHERE r.id_rendicion=$1",
    [$id_rendicion]
  );
  if (!$st || !pg_num_rows($st)) return null;
  return pg_fetch_assoc($st);
}

/* -------- GET -------- */
if ($method==='GET'){
  if (!empty($_GET['id_rendicion'])){
    $id=(int)$_GET['id_rendicion'];
    $info=get_rendicion_info($conn,$id);
    if(!$info) bad('Rendición no encontrada',404);
    ok([
      'rendicion'=>[
        'id_rendicion'=>(int)$info['id_rendicion'],
        'id_ff'=>(int)$info['id_ff'],
        'nombre_ff'=>$info['nombre_caja'],
        'estado'=>$info['estado'],
        'observacion'=>$info['observacion'],
        'id_proveedor'=>(int)$info['id_proveedor'],
        'moneda'=>$info['moneda']
      ],
      'total_aprobado'=> total_aprobado_rendicion($conn,$id),
      'ya_reposicionada'=> rendicion_ya_reposicionada($conn,$id)
    ]);
  }

  // listado elegibles
  $params=[]; $filters=[]; $ix=1;
  if (!empty($_GET['estado'])){ $filters[]="r.estado=$".$ix; $params[]=$_GET['estado']; $ix++; }
  else { $filters[]="r.estado IN ('Aprobada','Parcial')"; }
  if (!empty($_GET['id_ff'])){ $filters[]="r.id_ff=$".$ix; $params[]=(int)$_GET['id_ff']; $ix++; }
  if (!empty($_GET['q'])){ $filters[]="(ff.nombre_caja ILIKE $".$ix." OR r.observacion ILIKE $".$ix.")"; $params[]='%'.trim($_GET['q']).'%'; $ix++; }
  $where = $filters? "WHERE ".implode(' AND ',$filters) : "";

  $sql="
    SELECT r.id_rendicion, r.id_ff, ff.nombre_caja, ff.id_proveedor, ff.moneda,
           r.estado, r.observacion,
           COALESCE(t.total_aprob,0) AS total_aprobado,
           CASE WHEN mv.id_rendicion IS NULL THEN false ELSE true END AS ya_reposicionada
      FROM public.ff_rendiciones r
      JOIN public.fondo_fijo ff ON ff.id_ff=r.id_ff
 LEFT JOIN (SELECT id_rendicion, SUM(total) AS total_aprob
              FROM public.ff_rendiciones_items
             WHERE estado_item='Aprobado'
             GROUP BY id_rendicion) t ON t.id_rendicion=r.id_rendicion
 LEFT JOIN (SELECT ref_id AS id_rendicion
              FROM public.cuenta_pagar_mov
             WHERE ref_tipo='FFREP'
             GROUP BY ref_id) mv ON mv.id_rendicion=r.id_rendicion
     $where
  ORDER BY r.id_rendicion DESC
     LIMIT 500";
  $st=pg_query_params($conn,$sql,$params);
  if(!$st) bad('Error al listar rendiciones elegibles',500);

  $rows=[];
  while($r=pg_fetch_assoc($st)){
    $rows[]=[
      'id_rendicion'=>(int)$r['id_rendicion'],
      'id_ff'=>(int)$r['id_ff'],
      'nombre_ff'=>$r['nombre_caja'],
      'id_proveedor'=>(int)$r['id_proveedor'],
      'moneda'=>$r['moneda'],
      'estado'=>$r['estado'],
      'observacion'=>$r['observacion'],
      'total_aprobado'=>(float)$r['total_aprobado'],
      'ya_reposicionada'=> ($r['ya_reposicionada']==='t'||$r['ya_reposicionada']===true)
    ];
  }
  ok(['data'=>$rows]);
}

/* -------- POST (crear CxP) -------- */
if ($method==='POST'){
  $in = read_any();
  $accion = $in['accion'] ?? '';
  if ($accion!=='crear_cxp'){ bad('Acción no soportada',405); }

  $id_rendicion  = fint($in['id_rendicion'] ?? 0);
  $fecha_emision = trim($in['fecha_emision'] ?? '');
  $fecha_venc    = trim($in['fecha_venc'] ?? '');

  if ($id_rendicion<=0) bad('Rendición inválida');
  if ($fecha_emision==='') bad('Fecha de emisión requerida');
  if ($fecha_venc==='') $fecha_venc=$fecha_emision;

  $info = get_rendicion_info($conn,$id_rendicion);
  if(!$info) bad('Rendición no encontrada',404);
  if (!in_array($info['estado'],['Aprobada','Parcial'],true)) bad('La rendición debe estar Aprobada o Parcial');
  if (rendicion_ya_reposicionada($conn,$id_rendicion)) bad('Ya tiene reposición (FFREP)',409);

  $total = total_aprobado_rendicion($conn,$id_rendicion);
  if ($total<=0) bad('La rendición no tiene ítems aprobados > 0',409);

  $id_proveedor=(int)$info['id_proveedor'];
  $moneda=$info['moneda'];

  pg_query($conn,'BEGIN');

  // 1) CxP interna
  $obsCxp = 'Reposición FF Rendición #'.$id_rendicion;
  $insCxp=pg_query_params($conn,
    "INSERT INTO public.cuenta_pagar
       (id_factura, id_proveedor, fecha_emision, fecha_venc, moneda, total_cxp, saldo_actual, estado, observacion, created_at)
     VALUES
       (NULL, $1, $2::date, $3::date, $4, $5, $5, 'Pendiente', $6, now())
     RETURNING id_cxp",
    [$id_proveedor,$fecha_emision,$fecha_venc,$moneda,$total,$obsCxp]
  );
  if(!$insCxp){ pg_query($conn,'ROLLBACK'); bad('No se pudo crear la CxP',500); }
  $id_cxp=(int)pg_fetch_result($insCxp,0,'id_cxp');

  // 2) 1 cuota
  $obsCuota = 'Reposición FF Rendición #'.$id_rendicion;
  $insDet=pg_query_params($conn,
    "INSERT INTO public.cuenta_det_x_pagar
       (id_cxp, nro_cuota, fecha_venc, monto_cuota, saldo_cuota, estado, observacion)
     VALUES
       ($1, 1, $2::date, $3, $3, 'Pendiente', $4)",
    [$id_cxp,$fecha_venc,$total,$obsCuota]
  );
  if(!$insDet){ pg_query($conn,'ROLLBACK'); bad('No se pudo crear la cuota',500); }

  // 3) Movimiento FFREP (signo=+1)
  $concepto = 'Reposición FF Rendición #'.$id_rendicion;
  $insMov=pg_query_params($conn,
    "INSERT INTO public.cuenta_pagar_mov
       (id_proveedor, fecha, ref_tipo, ref_id, id_cxp, concepto, signo, monto, moneda, created_at)
     VALUES
       ($1, $2::date, 'FFREP', $3, $4, $5, 1, $6, $7, now())",
    [$id_proveedor,$fecha_emision,$id_rendicion,$id_cxp,$concepto,$total,$moneda]
  );
  if(!$insMov){ pg_query($conn,'ROLLBACK'); bad('No se pudo registrar el movimiento FFREP',500); }

  // 4) Anota en rendición (no cambia estado para no chocar con CHECK)
  $updR=pg_query_params($conn,
    "UPDATE public.ff_rendiciones
        SET observacion = CONCAT(COALESCE(observacion,''), CASE WHEN COALESCE(observacion,'')='' THEN '' ELSE ' | ' END,
                                  'Reposición generada CxP #', $1::text),
            updated_at = now()
      WHERE id_rendicion=$2",
    [$id_cxp,$id_rendicion]
  );
  if(!$updR){ pg_query($conn,'ROLLBACK'); bad('No se pudo actualizar la rendición',500); }

  pg_query($conn,'COMMIT');
  ok(['id_rendicion'=>$id_rendicion,'id_cxp'=>$id_cxp,'total'=>$total,'moneda'=>$moneda,'mensaje'=>'CxP de reposición creada']);
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Método no permitido']);
