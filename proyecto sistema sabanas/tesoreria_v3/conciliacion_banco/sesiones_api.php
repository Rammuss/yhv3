<?php
require_once __DIR__ . '/conciliacion_common.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id > 0) {
    $conc = require_conciliacion($conn, $id, false);
    ok(['conciliacion' => $conc]);
  }

  $params = [];
  $where = [];
  $i = 1;
  if (!empty($_GET['id_cuenta_bancaria'])) {
    $where[] = "c.id_cuenta_bancaria = $" . $i;
    $params[] = (int)$_GET['id_cuenta_bancaria'];
    $i++;
  }
  if (!empty($_GET['estado'])) {
    $where[] = "c.estado = $" . $i;
    $params[] = $_GET['estado'];
    $i++;
  }
  if (!empty($_GET['desde'])) {
    $where[] = "c.fecha_desde >= $" . $i;
    $params[] = $_GET['desde'];
    $i++;
  }
  if (!empty($_GET['hasta'])) {
    $where[] = "c.fecha_hasta <= $" . $i;
    $params[] = $_GET['hasta'];
    $i++;
  }
  $sql = "
    SELECT c.*, cb.banco, cb.numero_cuenta, cb.moneda
      FROM public.conciliacion_bancaria c
      JOIN public.cuenta_bancaria cb ON cb.id_cuenta_bancaria = c.id_cuenta_bancaria
     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY c.created_at DESC
     LIMIT 200
  ";
  $st = pg_query_params($conn, $sql, $params);
  if (!$st) bad('Error al listar conciliaciones', 500);
  $rows = [];
  while ($r = pg_fetch_assoc($st)) {
    $rows[] = $r;
  }
  ok(['data' => $rows]);
}

if ($method === 'POST') {
  $in = read_json();
  $idCuenta = require_int($in['id_cuenta_bancaria'] ?? null, 'Cuenta bancaria');
  $fechaDesde = $in['fecha_desde'] ?? null;
  $fechaHasta = $in['fecha_hasta'] ?? null;
  $saldoLibroIni = (float)($in['saldo_libro_inicial'] ?? 0);
  $saldoLibroFin = (float)($in['saldo_libro_final'] ?? 0);
  $saldoBancoIni = (float)($in['saldo_banco_inicial'] ?? 0);
  $saldoBancoFin = (float)($in['saldo_banco_final'] ?? 0);
  $obs = trim($in['observacion'] ?? '');

  if (!$fechaDesde || !$fechaHasta) bad('Fechas requeridas');
  if ($fechaDesde > $fechaHasta) bad('Rango de fechas inválido');

  $chkCuenta = pg_query_params(
    $conn,
    "SELECT 1 FROM public.cuenta_bancaria WHERE id_cuenta_bancaria = $1",
    [$idCuenta]
  );
  if (!$chkCuenta || !pg_num_rows($chkCuenta)) bad('Cuenta bancaria inexistente', 404);

  $st = pg_query_params(
    $conn,
    "INSERT INTO public.conciliacion_bancaria
       (id_cuenta_bancaria, fecha_desde, fecha_hasta,
        saldo_libro_inicial, saldo_libro_final,
        saldo_banco_inicial, saldo_banco_final,
        observacion, created_by)
     VALUES ($1, $2::date, $3::date, $4, $5, $6, $7, $8, $9)
     RETURNING id_conciliacion",
    [
      $idCuenta, $fechaDesde, $fechaHasta,
      $saldoLibroIni, $saldoLibroFin,
      $saldoBancoIni, $saldoBancoFin,
      $obs !== '' ? $obs : null,
      $_SESSION['nombre_usuario']
    ]
  );
  if (!$st) bad('No se pudo crear la conciliación', 500);
  $id = (int)pg_fetch_result($st, 0, 0);
  ok(['id_conciliacion' => $id]);
}

if ($method === 'PATCH') {
  $in = read_json();
  $id = require_int($in['id_conciliacion'] ?? null, 'Id conciliación');
  $accion = $in['accion'] ?? '';
  if ($accion === '') bad('Acción requerida');

  pg_query($conn, 'BEGIN');
  $conc = require_conciliacion($conn, $id, true);
  $estado = $conc['estado'];

  if ($accion === 'cerrar') {
    if ($estado !== 'Abierta') { pg_query($conn,'ROLLBACK'); bad('Solo se puede cerrar una conciliación abierta', 409); }
    $diferencia = (float)$conc['diferencia_final'];
    if (abs($diferencia) > 0.009) { pg_query($conn,'ROLLBACK'); bad('No se puede cerrar con diferencia distinta de cero', 409); }
    $ok = pg_query_params(
      $conn,
      "UPDATE public.conciliacion_bancaria
          SET estado='Cerrada',
              closed_at = now(),
              closed_by = $1
        WHERE id_conciliacion=$2",
      [$_SESSION['nombre_usuario'], $id]
    );
    if (!$ok) { pg_query($conn,'ROLLBACK'); bad('No se pudo cerrar la conciliación', 500); }
    pg_query($conn,'COMMIT');
    ok(['id_conciliacion'=>$id,'estado'=>'Cerrada']);
  }

  if ($accion === 'reabrir') {
    if ($estado !== 'Cerrada') { pg_query($conn,'ROLLBACK'); bad('Solo se puede reabrir una conciliación cerrada', 409); }
    $ok = pg_query_params(
      $conn,
      "UPDATE public.conciliacion_bancaria
          SET estado='Abierta',
              closed_at = NULL,
              closed_by = NULL
        WHERE id_conciliacion=$1",
      [$id]
    );
    if (!$ok) { pg_query($conn,'ROLLBACK'); bad('No se pudo reabrir', 500); }
    pg_query($conn,'COMMIT');
    ok(['id_conciliacion'=>$id,'estado'=>'Abierta']);
  }

  if ($accion === 'anular') {
    if ($estado === 'Anulada') { pg_query($conn,'ROLLBACK'); bad('La conciliación ya está anulada', 409); }
    $ok = pg_query_params(
      $conn,
      "UPDATE public.conciliacion_bancaria
          SET estado='Anulada'
        WHERE id_conciliacion=$1",
      [$id]
    );
    if (!$ok) { pg_query($conn,'ROLLBACK'); bad('No se pudo anular', 500); }
    pg_query($conn,'COMMIT');
    ok(['id_conciliacion'=>$id,'estado'=>'Anulada']);
  }

  pg_query($conn,'ROLLBACK');
  bad('Acción no soportada', 405);
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Método no permitido']);
