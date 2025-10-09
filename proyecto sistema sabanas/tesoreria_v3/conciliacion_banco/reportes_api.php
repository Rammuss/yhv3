<?php
require_once __DIR__ . '/conciliacion_common.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'GET') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Método no permitido']);
  exit;
}

$idConc = require_int($_GET['id_conciliacion'] ?? null, 'Conciliación');
$conc = require_conciliacion($conn, $idConc, false);

$csv = isset($_GET['formato']) && strtolower($_GET['formato']) === 'csv';

$sqlMovConc = "
  SELECT m.id_mov, m.fecha, m.descripcion, m.signo, m.monto
    FROM public.cuenta_bancaria_mov m
   WHERE m.conciliacion_id = $1
   ORDER BY m.fecha, m.id_mov
";
$sqlMovPend = "
  SELECT m.id_mov, m.fecha, m.descripcion, m.signo, m.monto
    FROM public.cuenta_bancaria_mov m
   WHERE m.id_cuenta_bancaria = $1
     AND m.fecha BETWEEN $2::date AND $3::date
     AND m.conciliacion_id IS DISTINCT FROM $4
     AND m.tipo NOT IN ('RESERVA','LIBERACION')
   ORDER BY m.fecha, m.id_mov
";
$sqlExtConc = "
  SELECT e.id_extracto, e.fecha, e.descripcion, e.signo, e.monto
    FROM public.conciliacion_bancaria_extracto e
   WHERE e.id_conciliacion = $1
     AND e.estado = 'Conciliado'
   ORDER BY e.fecha, e.id_extracto
";
$sqlExtPend = "
  SELECT e.id_extracto, e.fecha, e.descripcion, e.signo, e.monto
    FROM public.conciliacion_bancaria_extracto e
   WHERE e.id_conciliacion = $1
     AND e.estado <> 'Conciliado'
   ORDER BY e.fecha, e.id_extracto
";

$movConc = pg_query_params($conn, $sqlMovConc, [$idConc]);
$movPend = pg_query_params($conn, $sqlMovPend, [
  (int)$conc['id_cuenta_bancaria'],
  $conc['fecha_desde'],
  $conc['fecha_hasta'],
  $idConc
]);
$extConc = pg_query_params($conn, $sqlExtConc, [$idConc]);
$extPend = pg_query_params($conn, $sqlExtPend, [$idConc]);

if (!$movConc || !$movPend || !$extConc || !$extPend) bad('No se pudo generar el reporte', 500);

$data = [
  'conciliacion' => $conc,
  'movimientos_conciliados' => pg_fetch_all($movConc) ?: [],
  'movimientos_pendientes'  => pg_fetch_all($movPend) ?: [],
  'extracto_conciliado'     => pg_fetch_all($extConc) ?: [],
  'extracto_pendiente'      => pg_fetch_all($extPend) ?: []
];

if ($csv) {
  $filename = sprintf('conciliacion_%d.csv', $idConc);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.$filename.'"');

  $out = fopen('php://output', 'w');
  fputcsv($out, ['Conciliación', $idConc]);
  fputcsv($out, ['Cuenta', $conc['id_cuenta_bancaria']]);
  fputcsv($out, ['Periodo', $conc['fecha_desde'], $conc['fecha_hasta']]);
  fputcsv($out, []);

  fputcsv($out, ['Movimientos conciliados']);
  fputcsv($out, ['ID','Fecha','Descripción','Signo','Monto']);
  foreach ($data['movimientos_conciliados'] as $row) {
    fputcsv($out, [$row['id_mov'], $row['fecha'], $row['descripcion'], $row['signo'], $row['monto']]);
  }
  fputcsv($out, []);

  fputcsv($out, ['Movimientos pendientes']);
  fputcsv($out, ['ID','Fecha','Descripción','Signo','Monto']);
  foreach ($data['movimientos_pendientes'] as $row) {
    fputcsv($out, [$row['id_mov'], $row['fecha'], $row['descripcion'], $row['signo'], $row['monto']]);
  }
  fputcsv($out, []);

  fputcsv($out, ['Extracto conciliado']);
  fputcsv($out, ['ID','Fecha','Descripción','Signo','Monto']);
  foreach ($data['extracto_conciliado'] as $row) {
    fputcsv($out, [$row['id_extracto'], $row['fecha'], $row['descripcion'], $row['signo'], $row['monto']]);
  }
  fputcsv($out, []);

  fputcsv($out, ['Extracto pendiente']);
  fputcsv($out, ['ID','Fecha','Descripción','Signo','Monto']);
  foreach ($data['extracto_pendiente'] as $row) {
    fputcsv($out, [$row['id_extracto'], $row['fecha'], $row['descripcion'], $row['signo'], $row['monto']]);
  }
  fclose($out);
  exit;
}

ok($data);
