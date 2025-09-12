<?php
// clientes_buscar.php (versión corregida)
// Busca clientes por CI/RUC o por nombre+apellido (parcial), con paginado.
// Respuesta JSON: { ok, total, page, page_size, data: [{id_cliente, nombre_completo, ruc_ci, telefono, direccion}] }

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

// (Opcional) proteger por sesión
if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'No autorizado']); exit;
}

$q   = trim($_GET['q'] ?? '');
$pg  = max(1, (int)($_GET['page'] ?? 1));
$ps  = max(1, min(50, (int)($_GET['page_size'] ?? 10)));
$off = ($pg - 1) * $ps;

$qLower   = mb_strtolower($q);
$likeAny  = '%'.$qLower.'%';
$likePref = $qLower.'%';

/**
 * Estrategia:
 * - WHERE permite q vacío: si $1 = '' entonces no filtra; si no, filtra por ruc_ci o nombre completo.
 * - ORDER BY prioriza:
 *     1) ruc exacto (lower(ruc_ci) = $4)
 *     2) nombre+apellido por prefijo (LIKE $5)
 *     3) alfabético
 */

// COUNT
$sqlCount   = "
  SELECT COUNT(*)
  FROM public.clientes
  WHERE ($1 = '' OR lower(ruc_ci) LIKE $2 OR lower(nombre||' '||apellido) LIKE $3)
";
$paramsCnt  = [$qLower, $likeAny, $likeAny];

$stCount = pg_query_params($conn, $sqlCount, $paramsCnt);
if (!$stCount) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Error count']); exit; }
$total = (int)pg_fetch_result($stCount, 0, 0);

// LIST
$sqlList = "
  SELECT
    id_cliente,
    nombre,
    apellido,
    ruc_ci,
    telefono,
    direccion
  FROM public.clientes
  WHERE ($1 = '' OR lower(ruc_ci) LIKE $2 OR lower(nombre||' '||apellido) LIKE $3)
  ORDER BY
    CASE WHEN lower(ruc_ci) = $4 THEN 1 ELSE 0 END DESC,
    CASE WHEN lower(nombre||' '||apellido) LIKE $5 THEN 1 ELSE 0 END DESC,
    nombre ASC, apellido ASC
  LIMIT $6 OFFSET $7
";
$paramsList = [$qLower, $likeAny, $likeAny, $qLower, $likePref, $ps, $off];

$stList = pg_query_params($conn, $sqlList, $paramsList);
if (!$stList) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Error list']); exit; }

$data = [];
while ($r = pg_fetch_assoc($stList)) {
  $data[] = [
    'id_cliente'      => (int)$r['id_cliente'],
    'nombre_completo' => trim(($r['nombre'] ?? '').' '.($r['apellido'] ?? '')),
    'ruc_ci'          => $r['ruc_ci'],
    'telefono'        => $r['telefono'],
    'direccion'       => $r['direccion'],
  ];
}

echo json_encode([
  'ok'        => true,
  'total'     => $total,
  'page'      => $pg,
  'page_size' => $ps,
  'data'      => $data
]);
