<?php
/**
 * Helpers compartidos para las APIs de conciliación bancaria.
 * Incluye control de sesión, conexión a la BD y utilitarios para respuestas JSON.
 */

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'No autorizado']);
  exit;
}

function bad(string $msg, int $code = 400): void {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg]);
  exit;
}

function ok(array $payload = []): void {
  echo json_encode(['ok' => true] + $payload);
  exit;
}

function read_json(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || $raw === '') return [];
  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : [];
}

function require_int($value, string $label = 'Parámetro'): int {
  if ($value === null || $value === '') bad("$label requerido.");
  if (!is_numeric($value)) bad("$label inválido.");
  return (int)$value;
}

function require_conciliacion($conn, int $id, bool $forUpdate = false): array {
  $lock = $forUpdate ? 'FOR UPDATE' : '';
  $st = pg_query_params(
    $conn,
    "SELECT *
       FROM public.conciliacion_bancaria
      WHERE id_conciliacion = $1
      $lock",
    [$id]
  );
  if (!$st || !pg_num_rows($st)) bad('Conciliación no encontrada', 404);
  return pg_fetch_assoc($st);
}
