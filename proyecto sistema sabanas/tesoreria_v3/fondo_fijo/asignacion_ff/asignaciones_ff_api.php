<?php
/**
 * Fondo Fijo – Cajas (Asignaciones)
 * GET    ?           -> listado con filtros
 * GET    ?id_ff=...  -> detalle + movimientos
 * POST   crear_caja  -> crea una caja (saldo_actual=0, estado=Activo)
 * POST   asignar     -> agrega mov. ASIGNACION (+) y actualiza saldo_actual
 * PATCH  cerrar/reabrir
 */
session_start();
require_once __DIR__ . '../../../../conexion/configv2.php';
pg_query($conn, "SET search_path TO public");
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'No autorizado']);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function bad($m,$c=400){ http_response_code($c); echo json_encode(['ok'=>false,'error'=>$m]); exit; }
function ok($p=[]){ echo json_encode(['ok'=>true]+$p); exit; }
function read_json(){ $r=file_get_contents('php://input'); $j=json_decode($r,true); return is_array($j)?$j:[]; }
function pgf($v){ return $v===null?0.0:(float)$v; }
function pgi($v){ return $v===null?0:(int)$v; }
function ff_saldo($conn,$id){
  $st=pg_query_params($conn,"SELECT COALESCE(SUM(signo*monto),0) s FROM public.fondo_fijo_mov WHERE id_ff=$1",[$id]);
  if(!$st) return 0.0; $r=pg_fetch_assoc($st); return pgf($r['s']??0);
}

/* ---------- GET ---------- */
if ($method==='GET'){
  if (!empty($_GET['id_ff'])){
    $id=(int)$_GET['id_ff'];
    $st=pg_query_params($conn,"
      SELECT ff.id_ff, ff.id_proveedor, p.nombre AS responsable, ff.nombre_caja, ff.moneda,
             ff.monto_asignado, ff.saldo_actual, ff.estado, ff.observacion, ff.created_at, ff.created_by, ff.updated_at
      FROM public.fondo_fijo ff
      LEFT JOIN public.proveedores p ON p.id_proveedor=ff.id_proveedor
      WHERE ff.id_ff=$1
    ",[$id]);
    if(!$st||!pg_num_rows($st)) bad('Fondo fijo no encontrado',404);
    $ff=pg_fetch_assoc($st);

    $limit= isset($_GET['limit'])? max(1,min(500,(int)$_GET['limit'])):100;
    $mov=pg_query_params($conn,"
      SELECT id_mov, fecha, tipo, signo, monto, ref_tabla, ref_id, descripcion, created_at, created_by
      FROM public.fondo_fijo_mov
      WHERE id_ff=$1
      ORDER BY fecha DESC, id_mov DESC
      LIMIT $2
    ",[$id,$limit]);
    if(!$mov) bad('Error al listar movimientos',500);
    $rows=[];
    while($m=pg_fetch_assoc($mov)){
      $rows[]=[
        'id_mov'=>pgi($m['id_mov']),
        'fecha'=>$m['fecha'],'tipo'=>$m['tipo'],'signo'=>(int)$m['signo'],
        'monto'=>pgf($m['monto']),'ref_tabla'=>$m['ref_tabla'],
        'ref_id'=>$m['ref_id']!==null?(int)$m['ref_id']:null,
        'descripcion'=>$m['descripcion'],'created_at'=>$m['created_at'],'created_by'=>$m['created_by']
      ];
    }
    ok([
      'ff'=>[
        'id_ff'=>pgi($ff['id_ff']),
        'id_proveedor'=>pgi($ff['id_proveedor']),
        'responsable'=>$ff['responsable'],
        'nombre_caja'=>$ff['nombre_caja'],
        'moneda'=>$ff['moneda'],
        'monto_asignado'=>pgf($ff['monto_asignado']), // tope
        'saldo_cache'=>pgf($ff['saldo_actual']),
        'saldo'=>ff_saldo($conn,$id),
        'estado'=>$ff['estado'],
        'observacion'=>$ff['observacion'],
        'created_at'=>$ff['created_at'],'created_by'=>$ff['created_by'],'updated_at'=>$ff['updated_at']
      ],
      'movimientos'=>$rows
    ]);
  }

  // filtros: estado, id_proveedor, moneda, q (nombre_caja)
  $params=[]; $f=[]; $i=1;
  if(!empty($_GET['estado'])){ $f[]="ff.estado=$".$i; $params[]=$_GET['estado']; $i++; }
  if(!empty($_GET['id_proveedor'])){ $f[]="ff.id_proveedor=$".$i; $params[]=(int)$_GET['id_proveedor']; $i++; }
  if(!empty($_GET['moneda'])){ $f[]="ff.moneda=$".$i; $params[]=$_GET['moneda']; $i++; }
  if(!empty($_GET['q'])){ $f[]="ff.nombre_caja ILIKE $".$i; $params[]='%'.trim($_GET['q']).'%'; $i++; }
  $where = $f? "WHERE ".implode(' AND ',$f):'';

  $st=pg_query_params($conn,"
    SELECT ff.id_ff, ff.nombre_caja, ff.id_proveedor, p.nombre AS responsable,
           ff.moneda, ff.monto_asignado, ff.saldo_actual, ff.estado, ff.created_at,
           COALESCE(m.saldo,0) AS saldo_mov
    FROM public.fondo_fijo ff
    LEFT JOIN public.proveedores p ON p.id_proveedor=ff.id_proveedor
    LEFT JOIN (SELECT id_ff, SUM(signo*monto) saldo FROM public.fondo_fijo_mov GROUP BY id_ff) m ON m.id_ff=ff.id_ff
    $where
    ORDER BY ff.created_at DESC, ff.id_ff DESC
    LIMIT 500
  ",$params);
  if(!$st) bad('Error al listar fondos fijos',500);

  $data=[];
  while($r=pg_fetch_assoc($st)){
    $data[]=[
      'id_ff'=>pgi($r['id_ff']),
      'nombre'=>$r['nombre_caja'],
      'id_proveedor'=>pgi($r['id_proveedor']),
      'responsable_nombre'=>$r['responsable'],
      'moneda'=>$r['moneda'],
      'monto_asignado'=>pgf($r['monto_asignado']),
      'saldo_actual'=>pgf($r['saldo_actual']),
      'saldo'=>pgf($r['saldo_mov']),
      'estado'=>$r['estado'],
      'created_at'=>$r['created_at']
    ];
  }
  ok(['data'=>$data]);
}

/* ---------- POST ---------- */
if ($method==='POST'){
  $in = read_json();
  $accion = $in['accion'] ?? '';

  /* crear_caja */
  if ($accion==='crear_caja'){
    $id_prov = (int)($in['id_proveedor'] ?? 0);
    $nombre  = trim($in['nombre_caja'] ?? '');
    $moneda  = ($in['moneda'] ?? 'PYG');
    $tope    = (float)($in['tope'] ?? 0);
    $saldoInicial = array_key_exists('saldo_inicial', $in)
      ? max(0.0, (float)$in['saldo_inicial'])
      : max(0.0, $tope);
    $obs     = trim($in['observacion'] ?? '');
    $user    = $_SESSION['nombre_usuario'];

    if($id_prov<=0) bad('Proveedor inválido');
    if($nombre==='') bad('Nombre de caja requerido');
    if($tope<0) bad('El tope debe ser >= 0');
    if($saldoInicial < 0) bad('Saldo inicial inválido');
    if($tope>0 && $saldoInicial > $tope) bad('El saldo inicial no puede superar el tope asignado');

    // validar proveedor existe y es FONDO_FIJO (opcional pero recomendado)
    $v=pg_query_params($conn,"SELECT 1 FROM public.proveedores WHERE id_proveedor=$1 AND estado='Activo' AND tipo='FONDO_FIJO'",[$id_prov]);
    if(!$v || !pg_num_rows($v)) bad('El proveedor no es válido o no es de tipo FONDO_FIJO',422);

    pg_query($conn,'BEGIN');
    $st=pg_query_params($conn,"
      INSERT INTO public.fondo_fijo
        (id_proveedor, nombre_caja, moneda, monto_asignado, saldo_actual, estado, observacion, created_at, created_by)
      VALUES ($1,$2,$3,$4,0,'Activo',$5, now(), $6)
      RETURNING id_ff
    ",[$id_prov,$nombre,$moneda,$tope, $obs!==''?$obs:null, $user]);
    if(!$st){ pg_query($conn,'ROLLBACK'); bad('No se pudo crear la caja',500); }
    $idFf = (int)pg_fetch_result($st,0,0);

    if ($saldoInicial > 0) {
      $okMov = pg_query_params(
        $conn,
        "INSERT INTO public.fondo_fijo_mov
           (id_ff, fecha, tipo, signo, monto, descripcion, ref_tabla, ref_id, created_at, created_by)
         VALUES ($1, current_date, 'ASIGNACION', 1, $2, 'Saldo inicial', 'bootstrap', NULL, now(), $3)",
        [$idFf, $saldoInicial, $user]
      );
      if(!$okMov){ pg_query($conn,'ROLLBACK'); bad('No se pudo registrar el saldo inicial',500); }

      $okUpd = pg_query_params(
        $conn,
        "UPDATE public.fondo_fijo
            SET saldo_actual = COALESCE(saldo_actual,0) + $1,
                updated_at = now()
          WHERE id_ff = $2",
        [$saldoInicial, $idFf]
      );
      if(!$okUpd){ pg_query($conn,'ROLLBACK'); bad('No se pudo actualizar el saldo inicial',500); }
    }

    pg_query($conn,'COMMIT');
    ok(['id_ff'=>$idFf,'saldo_inicial'=>$saldoInicial,'mensaje'=>'Caja creada']);
  }

  /* asignar (movimiento de caja, opcional) */
  if ($accion==='asignar'){
    $id_ff=(int)($in['id_ff']??0);
    $monto=(float)($in['monto']??0);
    $fecha=($in['fecha']??date('Y-m-d'));
    $desc =trim($in['descripcion']??'');
    $user =$_SESSION['nombre_usuario'];

    if($id_ff<=0) bad('Caja inválida');
    if($monto<=0) bad('Monto inválido');

    $st=pg_query_params($conn,"SELECT id_ff, moneda, monto_asignado, estado FROM public.fondo_fijo WHERE id_ff=$1 FOR UPDATE",[$id_ff]);
    if(!$st||!pg_num_rows($st)) bad('Caja no encontrada',404);
    $ff=pg_fetch_assoc($st);
    if($ff['estado']!=='Activo') bad('La caja no está activa');

    $saldo_mov = ff_saldo($conn,$id_ff);
    $tope = $ff['monto_asignado']!==null?(float)$ff['monto_asignado']:null;
    if($tope!==null && ($saldo_mov+$monto)>$tope) bad('La asignación supera el tope de la caja');

    pg_query($conn,'BEGIN');
    $ok1=pg_query_params($conn,"
      INSERT INTO public.fondo_fijo_mov
        (id_ff, fecha, tipo, signo, monto, descripcion, ref_tabla, ref_id, created_at, created_by)
      VALUES ($1,$2::date,'ASIGNACION',1,$3,$4,NULL,NULL,now(),$5)
    ",[$id_ff,$fecha,$monto,$desc!==''?$desc:null,$user]);
    if(!$ok1){ pg_query($conn,'ROLLBACK'); bad('No se pudo registrar la asignación',500); }

    $ok2=pg_query_params($conn,"
      UPDATE public.fondo_fijo
         SET saldo_actual = COALESCE(saldo_actual,0) + $1,
             updated_at = now()
       WHERE id_ff=$2
    ",[$monto,$id_ff]);
    if(!$ok2){ pg_query($conn,'ROLLBACK'); bad('No se pudo actualizar saldo',500); }

    pg_query($conn,'COMMIT');
    ok(['id_ff'=>$id_ff,'saldo'=>$saldo_mov+$monto,'moneda'=>$ff['moneda'],'mensaje'=>'Asignación registrada']);
  }

  bad('Acción no soportada',405);
}

/* ---------- PATCH ---------- */
if ($method==='PATCH'){
  $in=read_json();
  $accion=$in['accion']??'';
  $id=(int)($in['id_ff']??0);
  if($id<=0) bad('Caja inválida');

  $st=pg_query_params($conn,"SELECT id_ff, estado FROM public.fondo_fijo WHERE id_ff=$1 FOR UPDATE",[$id]);
  if(!$st||!pg_num_rows($st)) bad('Caja no encontrada',404);

  if($accion==='cerrar'){
    $s=ff_saldo($conn,$id);
    if(abs($s)>0.00001) bad('No se puede cerrar: el saldo debe ser cero');
    $up=pg_query_params($conn,"UPDATE public.fondo_fijo SET estado='Cerrado', updated_at=now() WHERE id_ff=$1",[$id]);
    if(!$up) bad('No se pudo cerrar la caja',500);
    ok(['id_ff'=>$id,'estado'=>'Cerrado']);
  }elseif($accion==='reabrir'){
    $up=pg_query_params($conn,"UPDATE public.fondo_fijo SET estado='Activo', updated_at=now() WHERE id_ff=$1",[$id]);
    if(!$up) bad('No se pudo reabrir la caja',500);
    ok(['id_ff'=>$id,'estado'=>'Activo']);
  }else{
    bad('Acción no soportada',405);
  }
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Método no permitido']);
