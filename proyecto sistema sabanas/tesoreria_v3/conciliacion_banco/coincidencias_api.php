<?php
require_once __DIR__ . '/conciliacion_common.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'POST' && $method !== 'PATCH') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Metodo no permitido']);
  exit;
}

$in = read_json();
$accion = $in['accion'] ?? '';
if ($accion === '') bad('Accion requerida');

if ($accion === 'auto_match') {
  $idConc = require_int($in['id_conciliacion'] ?? null, 'Conciliacion');
  $conc = require_conciliacion($conn, $idConc, true);

  $tolerancia = isset($in['tolerancia']) ? (float)$in['tolerancia'] : 0.0;

  pg_query($conn,'BEGIN');
  $sqlMov = "
    SELECT id_mov, fecha, monto, signo
      FROM public.cuenta_bancaria_mov
     WHERE id_cuenta_bancaria = $1
       AND fecha BETWEEN $2::date AND $3::date
       AND tipo NOT IN ('RESERVA','LIBERACION')
       AND (conciliacion_id IS NULL OR conciliacion_id <> $4)
  ";
  $stMov = pg_query_params($conn, $sqlMov, [
    (int)$conc['id_cuenta_bancaria'], $conc['fecha_desde'], $conc['fecha_hasta'], $idConc
  ]);
  $movs = [];
  while ($m = pg_fetch_assoc($stMov)) {
    $key = $m['signo'].'|'.number_format((float)$m['monto'], 2, '.', '');
    $movs[$key][] = $m;
  }

  $sqlExt = "
    SELECT id_extracto, fecha, monto, signo
      FROM public.conciliacion_bancaria_extracto
     WHERE id_conciliacion = $1
       AND estado = 'Pendiente'
  ";
  $stExt = pg_query_params($conn, $sqlExt, [$idConc]);

  $matches = [];
  while ($ex = pg_fetch_assoc($stExt)) {
    $key = $ex['signo'].'|'.number_format((float)$ex['monto'], 2, '.', '');
    $matched = false;
    if (isset($movs[$key]) && count($movs[$key])) {
      $mov = array_shift($movs[$key]);
      $matches[] = [
        'id_mov' => (int)$mov['id_mov'],
        'id_extracto' => (int)$ex['id_extracto']
      ];
    } elseif ($tolerancia > 0) {
      foreach ($movs as $k => &$lista) {
        if (!$lista) continue;
        if ($lista[0]['signo'] != $ex['signo']) continue;
        $dif = abs((float)$lista[0]['monto'] - (float)$ex['monto']);
        if ($dif <= $tolerancia) {
          $mov = array_shift($lista);
          $matches[] = [
            'id_mov' => (int)$mov['id_mov'],
            'id_extracto' => (int)$ex['id_extracto']
          ];
          $matched = true;
          break;
        }
      }
    }
  }

  foreach ($matches as $match) {
    $idMov = $match['id_mov'];
    $idExt = $match['id_extracto'];

    $okMov = pg_query_params(
      $conn,
      "UPDATE public.cuenta_bancaria_mov
          SET conciliacion_id = $1,
              conciliado_estado = 'Conciliado',
              conciliado_at = now(),
              conciliado_por = $2
        WHERE id_mov = $3",
      [$idConc, $_SESSION['nombre_usuario'], $idMov]
    );
    if (!$okMov){ pg_query($conn,'ROLLBACK'); bad('No se pudo marcar movimiento', 500); }

    $okExt = pg_query_params(
      $conn,
      "UPDATE public.conciliacion_bancaria_extracto
          SET estado='Conciliado',
              match_source='Auto',
              id_mov_conciliado=$2
        WHERE id_extracto=$1",
      [$idExt, $idMov]
    );
    if (!$okExt){ pg_query($conn,'ROLLBACK'); bad('No se pudo marcar extracto', 500); }

    $okDet = pg_query_params(
      $conn,
      "INSERT INTO public.conciliacion_bancaria_det
        (id_conciliacion, id_movimiento, id_extracto, tipo, signo, monto, descripcion, origen, created_by)
       SELECT $1::int, $2::int, $3::bigint, 'MATCH', m.signo, m.monto,
              'Match auto extracto #' || $3::text,
              'Auto', $4
         FROM public.cuenta_bancaria_mov m
        WHERE m.id_mov = $2",
      [$idConc, $idMov, $idExt, $_SESSION['nombre_usuario']]
    );
    if (!$okDet){ pg_query($conn,'ROLLBACK'); bad('No se pudo registrar match', 500); }
  }

  pg_query($conn,'COMMIT');
  ok(['auto_matches' => $matches]);
}

if ($accion === 'match_manual') {
  $idConc = require_int($in['id_conciliacion'] ?? null, 'Conciliacion');
  $conc = require_conciliacion($conn, $idConc, true);
  $idMov = require_int($in['id_movimiento'] ?? null, 'Movimiento');
  $idExt = require_int($in['id_extracto'] ?? null, 'Extracto');

  pg_query($conn,'BEGIN');

  $mov = pg_query_params($conn,
    "SELECT signo, monto FROM public.cuenta_bancaria_mov WHERE id_mov=$1 FOR UPDATE",
    [$idMov]
  );
  if (!$mov || !pg_num_rows($mov)){ pg_query($conn,'ROLLBACK'); bad('Movimiento no encontrado',404); }
  $movRow = pg_fetch_assoc($mov);

  $ext = pg_query_params($conn,
    "SELECT signo, monto FROM public.conciliacion_bancaria_extracto WHERE id_extracto=$1 FOR UPDATE",
    [$idExt]
  );
  if (!$ext || !pg_num_rows($ext)){ pg_query($conn,'ROLLBACK'); bad('Extracto no encontrado',404); }
  $extRow = pg_fetch_assoc($ext);

  if ((int)$movRow['signo'] !== (int)$extRow['signo']) {
    pg_query($conn,'ROLLBACK'); bad('Los signos deben coincidir', 422);
  }

  $okMov = pg_query_params(
    $conn,
    "UPDATE public.cuenta_bancaria_mov
        SET conciliacion_id = $1,
            conciliado_estado='Conciliado',
            conciliado_at = now(),
            conciliado_por = $2
      WHERE id_mov=$3",
    [$idConc, $_SESSION['nombre_usuario'], $idMov]
  );
  if (!$okMov){ pg_query($conn,'ROLLBACK'); bad('No se pudo marcar movimiento', 500); }

  $okExt = pg_query_params(
    $conn,
    "UPDATE public.conciliacion_bancaria_extracto
        SET estado='Conciliado',
            match_source='Manual',
            id_mov_conciliado = $2
      WHERE id_extracto=$1",
    [$idExt, $idMov]
  );
  if (!$okExt){ pg_query($conn,'ROLLBACK'); bad('No se pudo marcar extracto', 500); }

  $okDet = pg_query_params(
    $conn,
    "INSERT INTO public.conciliacion_bancaria_det
        (id_conciliacion, id_movimiento, id_extracto, tipo, signo, monto, descripcion, origen, created_by)
       VALUES ($1::int, $2::int, $3::bigint, 'MATCH', $4, $5, $6, 'Manual', $7)",
    [$idConc, $idMov, $idExt, (int)$movRow['signo'], (float)$movRow['monto'],
     'Match manual', $_SESSION['nombre_usuario']]
  );
  if (!$okDet){ pg_query($conn,'ROLLBACK'); bad('No se pudo registrar detalle', 500); }

  pg_query($conn,'COMMIT');
  ok(['match' => ['id_movimiento'=>$idMov,'id_extracto'=>$idExt]]);
}

if ($accion === 'ajuste_libro' || $accion === 'ajuste_banco') {
  $idConc = require_int($in['id_conciliacion'] ?? null, 'Conciliacion');
  $conc = require_conciliacion($conn, $idConc, true);
  $monto = (float)($in['monto'] ?? 0);
  if ($monto <= 0) bad('Monto inv¡lido');
  $descripcion = trim($in['descripcion'] ?? '');
  $signo = (int)($in['signo'] ?? 1);
  if (!in_array($signo, [1,-1], true)) bad('Signo inv¡lido');

  pg_query($conn,'BEGIN');
  $movId = null;
  $extId = null;

  if ($accion === 'ajuste_libro') {
    $sqlMov = "
      INSERT INTO public.cuenta_bancaria_mov
        (id_cuenta_bancaria, fecha, tipo, signo, monto, descripcion, ref_tabla, ref_id, created_at)
      VALUES ($1, current_date, 'AJUSTE_CONC', $2, $3, $4, 'conciliacion', $5, now())
      RETURNING id_mov
    ";
    $st = pg_query_params($conn, $sqlMov, [
      (int)$conc['id_cuenta_bancaria'], $signo, $monto,
      ($descripcion !== '' ? $descripcion : 'Ajuste conciliacion'),
      $idConc
    ]);
    if (!$st){ pg_query($conn,'ROLLBACK'); bad('No se pudo crear ajuste en libro', 500); }
    $movId = (int)pg_fetch_result($st, 0, 0);

    $ok = pg_query_params(
      $conn,
      "UPDATE public.cuenta_bancaria_mov
          SET conciliacion_id = $1,
              conciliado_estado='Conciliado',
              conciliado_at = now(),
              conciliado_por = $2
        WHERE id_mov=$3",
      [$idConc, $_SESSION['nombre_usuario'], $movId]
    );
    if (!$ok){ pg_query($conn,'ROLLBACK'); bad('No se pudo marcar ajuste', 500); }
  } else {
    $sqlExt = "
      INSERT INTO public.conciliacion_bancaria_extracto
        (id_conciliacion, fecha, descripcion, signo, monto, estado, match_source, created_at)
      VALUES ($1, current_date, $2, $3, $4, 'Conciliado', 'Ajuste', now())
      RETURNING id_extracto
    ";
    $st = pg_query_params($conn, $sqlExt, [
      $idConc, ($descripcion !== '' ? $descripcion : 'Ajuste conciliacion'), $signo, $monto
    ]);
    if (!$st){ pg_query($conn,'ROLLBACK'); bad('No se pudo crear ajuste en banco', 500); }
    $extId = (int)pg_fetch_result($st, 0, 0);
  }

  $okDet = pg_query_params(
    $conn,
    "INSERT INTO public.conciliacion_bancaria_det
        (id_conciliacion, id_movimiento, id_extracto, tipo, signo, monto, descripcion, origen, created_by, ref_mov_generado)
       VALUES ($1, $2, $3, $4, $5, $6, $7, 'Manual', $8, $9)",
    [
      $idConc,
      $movId,
      $extId,
      $accion === 'ajuste_libro' ? 'AJUSTE_LIBRO' : 'AJUSTE_BANCO',
      $signo,
      $monto,
      $descripcion !== '' ? $descripcion : 'Ajuste conciliacion',
      $_SESSION['nombre_usuario'],
      $movId ?? $extId
    ]
  );
  if (!$okDet){ pg_query($conn,'ROLLBACK'); bad('No se pudo registrar detalle de ajuste', 500); }

  pg_query($conn,'COMMIT');
  ok([
    'ajuste' => [
      'tipo' => $accion === 'ajuste_libro' ? 'libro' : 'banco',
      'id_movimiento' => $movId,
      'id_extracto' => $extId
    ]
  ]);
}

if ($accion === 'revertir_match') {
  $idDet = require_int($in['id_detalle'] ?? null, 'Detalle');

  pg_query($conn,'BEGIN');
  $detSt = pg_query_params(
    $conn,
    "SELECT id_conciliacion, id_movimiento, id_extracto, tipo
       FROM public.conciliacion_bancaria_det
      WHERE id_detalle = $1
      FOR UPDATE",
    [$idDet]
  );
  if (!$detSt || !pg_num_rows($detSt)){ pg_query($conn,'ROLLBACK'); bad('Detalle no encontrado', 404); }
  $det = pg_fetch_assoc($detSt);
  if ($det['tipo'] !== 'MATCH') { pg_query($conn,'ROLLBACK'); bad('Solo se pueden revertir matches', 409); }

  if ($det['id_movimiento']) {
    $ok = pg_query_params(
      $conn,
      "UPDATE public.cuenta_bancaria_mov
          SET conciliacion_id = NULL,
              conciliado_estado = NULL,
              conciliado_at = NULL,
              conciliado_por = NULL
        WHERE id_mov = $1",
      [(int)$det['id_movimiento']]
    );
    if (!$ok){ pg_query($conn,'ROLLBACK'); bad('No se pudo revertir movimiento', 500); }
  }

  if ($det['id_extracto']) {
    $ok = pg_query_params(
      $conn,
      "UPDATE public.conciliacion_bancaria_extracto
          SET estado='Pendiente',
              match_source=NULL,
              id_mov_conciliado=NULL
        WHERE id_extracto = $1",
      [(int)$det['id_extracto']]
    );
    if (!$ok){ pg_query($conn,'ROLLBACK'); bad('No se pudo revertir extracto', 500); }
  }

  $ok = pg_query_params(
    $conn,
    "DELETE FROM public.conciliacion_bancaria_det WHERE id_detalle=$1",
    [$idDet]
  );
  if (!$ok){ pg_query($conn,'ROLLBACK'); bad('No se pudo eliminar detalle', 500); }

  pg_query($conn,'COMMIT');
  ok(['revertido' => true]);
}

bad('Accion no soportada', 405);















