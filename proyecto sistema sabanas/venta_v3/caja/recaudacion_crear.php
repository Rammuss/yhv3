<?php
// /caja/recaudacion_crear.php
session_start();
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

function out($ok, $data=[], $code=200){
  http_response_code($code);
  echo json_encode(['ok'=>$ok] + $data);
  exit;
}

if (empty($_SESSION['id_usuario'])) {
  out(false, ['error'=>'Sesión expirada'], 401);
}

$in = json_decode(file_get_contents('php://input'), true);
$sesiones = $in['sesiones'] ?? null;
$observacion = trim($in['observacion'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

// Validación básica
if (!is_array($sesiones) || count($sesiones) === 0) {
  out(false, ['error'=>'Lista de sesiones vacía'], 400);
}

// Normalizar ids
$sesiones = array_values(array_unique(array_map('intval', $sesiones)));

pg_query($conn, 'BEGIN');
try {
  // 1) Validar que todas existan, estén CERRADAS y no estén ya recaudadas
  //    (asumimos que existe recaudacion_detalle(id_caja_sesion UNIQUE) o lo controlamos acá)
  $qSes = "
    SELECT cs.id_caja_sesion, cs.estado,
           c.id_sucursal, c.nombre AS caja_nombre
      FROM public.caja_sesion cs
      JOIN public.caja c ON c.id_caja = cs.id_caja
     WHERE cs.id_caja_sesion = ANY($1::int[])
     FOR UPDATE
  ";
  $rs = pg_query_params($conn, $qSes, [ '{'.implode(',',$sesiones).'}' ]);
  if (!$rs) throw new Exception(pg_last_error($conn));

  $found = [];
  $id_sucursal = null; // si querés forzar una sola sucursal por recaudación, usá la 1a
  while($row = pg_fetch_assoc($rs)){
    $found[(int)$row['id_caja_sesion']] = $row;
    if ($id_sucursal === null) $id_sucursal = $row['id_sucursal'] !== null ? (int)$row['id_sucursal'] : null;
  }

  // faltantes
  $faltan = array_diff($sesiones, array_keys($found));
  if (!empty($faltan)) {
    throw new Exception('Sesiones inexistentes: '.implode(', ', $faltan));
  }

  // estados
  foreach($found as $id => $row){
    if (strcasecmp($row['estado'], 'Cerrada') !== 0) {
      throw new Exception("La sesión $id no está Cerrada");
    }
  }

  // ya recaudadas?
  $qDup = "
    SELECT id_caja_sesion
      FROM public.recaudacion_detalle
     WHERE id_caja_sesion = ANY($1::int[])
     LIMIT 1
  ";
  $rd = pg_query_params($conn, $qDup, [ '{'.implode(',',$sesiones).'}' ]);
  if ($rd && pg_num_rows($rd) > 0) {
    $dup = (int)pg_fetch_result($rd, 0, 0);
    throw new Exception("La sesión $dup ya fue incluida en una recaudación");
  }

  // 2) Calcular totales por medio desde movimiento_caja (Ingreso - Egreso)
  //    Para todas las sesiones seleccionadas, de una vez:
  $qTot = "
    SELECT
      m.id_caja_sesion,
      COALESCE(SUM(CASE WHEN m.medio='Efectivo'      AND m.tipo='Ingreso' THEN m.monto
                        WHEN m.medio='Efectivo'      AND m.tipo='Egreso'  THEN -m.monto END),0)::numeric(14,2) AS efectivo,
      COALESCE(SUM(CASE WHEN m.medio='Tarjeta'       AND m.tipo='Ingreso' THEN m.monto
                        WHEN m.medio='Tarjeta'       AND m.tipo='Egreso'  THEN -m.monto END),0)::numeric(14,2) AS tarjeta,
      COALESCE(SUM(CASE WHEN m.medio='Transferencia' AND m.tipo='Ingreso' THEN m.monto
                        WHEN m.medio='Transferencia' AND m.tipo='Egreso'  THEN -m.monto END),0)::numeric(14,2) AS transferencia,
      COALESCE(SUM(CASE WHEN m.medio NOT IN ('Efectivo','Tarjeta','Transferencia') AND m.tipo='Ingreso' THEN m.monto
                        WHEN m.medio NOT IN ('Efectivo','Tarjeta','Transferencia') AND m.tipo='Egreso'  THEN -m.monto END),0)::numeric(14,2) AS otros
    FROM public.movimiento_caja m
    WHERE m.id_caja_sesion = ANY($1::int[])
    GROUP BY m.id_caja_sesion
  ";
  $rt = pg_query_params($conn, $qTot, [ '{'.implode(',',$sesiones).'}' ]);
  if (!$rt) throw new Exception(pg_last_error($conn));

  $totales = []; // id_sesion => [ef,ta,tr,ot]
  while($t = pg_fetch_assoc($rt)){
    $totales[(int)$t['id_caja_sesion']] = [
      'ef' => (float)$t['efectivo'],
      'ta' => (float)$t['tarjeta'],
      'tr' => (float)$t['transferencia'],
      'ot' => (float)$t['otros'],
    ];
  }
  // Llenar con ceros las sesiones sin movimientos (posible)
  foreach($sesiones as $sid){
    if (!isset($totales[$sid])) $totales[$sid] = ['ef'=>0,'ta'=>0,'tr'=>0,'ot'=>0];
  }

  // 3) Insertar cabecera de recaudación
  //    Asumimos schema: recaudacion_deposito(id_recaudacion serial PK, id_sucursal int null,
  //    observacion text, estado text default 'Pendiente', monto_total numeric(14,2) default 0,
  //    creado_en timestamptz default now(), actualizado_en timestamptz default now(), id_usuario int)
  $qInsCab = "
    INSERT INTO public.recaudacion_deposito
      (id_sucursal, observacion, estado, monto_total, id_usuario)
    VALUES ($1, $2, 'Pendiente', 0, $3)
    RETURNING id_recaudacion
  ";
  $rc = pg_query_params($conn, $qInsCab, [$id_sucursal, $observacion === '' ? null : $observacion, $id_usuario]);
  if (!$rc) throw new Exception(pg_last_error($conn));
  $idRec = (int)pg_fetch_result($rc, 0, 0);

  // 4) Insertar detalle por sesión (y acumular total)
  $qInsDet = "
    INSERT INTO public.recaudacion_detalle
      (id_recaudacion, id_caja_sesion, monto_efectivo, monto_tarjeta, monto_transferencia, monto_otros)
    VALUES ($1,$2,$3,$4,$5,$6)
  ";
  $monto_total = 0.0;

  foreach($sesiones as $sid){
    $ef = $totales[$sid]['ef'];
    $ta = $totales[$sid]['ta'];
    $tr = $totales[$sid]['tr'];
    $ot = $totales[$sid]['ot'];
    $sum = $ef + $ta + $tr + $ot;
    $monto_total += $sum;

    $rd = pg_query_params($conn, $qInsDet, [$idRec, $sid, $ef, $ta, $tr, $ot]);
    if (!$rd) throw new Exception(pg_last_error($conn));
  }

  // 5) Actualizar total de cabecera
  $qUp = "UPDATE public.recaudacion_deposito SET monto_total=$1, actualizado_en=now() WHERE id_recaudacion=$2";
  $ru = pg_query_params($conn, $qUp, [$monto_total, $idRec]);
  if (!$ru) throw new Exception(pg_last_error($conn));

  pg_query($conn, 'COMMIT');
  out(true, ['id_recaudacion'=>$idRec, 'monto_total'=>$monto_total], 201);

} catch(Exception $e){
  pg_query($conn, 'ROLLBACK');
  out(false, ['error'=>$e->getMessage()], 400);
}
