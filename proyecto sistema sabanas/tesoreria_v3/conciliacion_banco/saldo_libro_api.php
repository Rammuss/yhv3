<?php
require_once __DIR__ . '/conciliacion_common.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
  exit;
}

$idCuenta = require_int($_GET['id_cuenta_bancaria'] ?? null, 'Cuenta bancaria');
$fechaDesde = $_GET['fecha_desde'] ?? null;
$fechaHasta = $_GET['fecha_hasta'] ?? null;

if (!$fechaDesde || !$fechaHasta) bad('Fechas requeridas');
if ($fechaDesde > $fechaHasta) bad('Rango de fechas inválido');

$chk = pg_query_params(
  $conn,
  "SELECT banco, numero_cuenta, moneda
     FROM public.cuenta_bancaria
    WHERE id_cuenta_bancaria = $1",
  [$idCuenta]
);
if (!$chk || !pg_num_rows($chk)) bad('Cuenta bancaria no encontrada', 404);
$cuenta = pg_fetch_assoc($chk);

$sql = "
  SELECT
    COALESCE(SUM(CASE WHEN fecha < $2::date THEN signo * monto ELSE 0 END), 0) AS saldo_inicial,
    COALESCE(SUM(CASE WHEN fecha <= $3::date THEN signo * monto ELSE 0 END), 0) AS saldo_final,
    COALESCE(SUM(CASE WHEN fecha BETWEEN $2::date AND $3::date THEN signo * monto ELSE 0 END), 0) AS saldo_periodo,
    COUNT(*) FILTER (WHERE fecha BETWEEN $2::date AND $3::date AND tipo NOT IN ('RESERVA','LIBERACION')) AS cant_periodo
  FROM public.cuenta_bancaria_mov
  WHERE id_cuenta_bancaria = $1
    AND tipo NOT IN ('RESERVA','LIBERACION')
";
$st = pg_query_params($conn, $sql, [$idCuenta, $fechaDesde, $fechaHasta]);
if (!$st) bad('No se pudo calcular saldos', 500);
$row = pg_fetch_assoc($st);

ok([
  'cuenta' => [
    'id_cuenta_bancaria' => $idCuenta,
    'banco' => $cuenta['banco'],
    'numero_cuenta' => $cuenta['numero_cuenta'],
    'moneda' => $cuenta['moneda']
  ],
  'saldo_libro_inicial' => (float)$row['saldo_inicial'],
  'saldo_libro_final'   => (float)$row['saldo_final'],
  'saldo_periodo'       => (float)$row['saldo_periodo'],
  'cant_periodo'        => (int)$row['cant_periodo']
]);
