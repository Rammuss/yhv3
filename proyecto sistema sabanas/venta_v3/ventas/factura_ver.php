<?php
// factura_ver.php — Normaliza y redirige a la vista imprimible
session_start();
if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
require_once __DIR__.'/../../conexion/configv2.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$numero = trim($_GET['numero'] ?? '');

try {
  if ($id <= 0 && $numero === '') {
    throw new Exception('Parámetros inválidos: falta id o número de documento');
  }

  if ($id <= 0 && $numero !== '') {
    $sql = "SELECT id_factura FROM public.factura_venta_cab WHERE LOWER(numero_documento)=LOWER($1) LIMIT 1";
    $res = pg_query_params($conn, $sql, [$numero]);
    if (!$res || pg_num_rows($res)===0) { throw new Exception('Factura no encontrada por número'); }
    $id = (int)pg_fetch_result($res, 0, 0);
  } else {
    // valida existencia rápida
    $sql = "SELECT 1 FROM public.factura_venta_cab WHERE id_factura=$1";
    $res = pg_query_params($conn, $sql, [$id]);
    if (!$res || pg_num_rows($res)===0) { throw new Exception('Factura no encontrada'); }
  }

  header('Location: factura_print.php?id='.$id);
  exit;

} catch (Throwable $e) {
  http_response_code(404);
  echo "<!doctype html><meta charset='utf-8'><body style='font-family:system-ui'>
        <h3>Error</h3><p>".e($e->getMessage())."</p></body>";
}
