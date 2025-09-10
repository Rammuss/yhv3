<?php
// Busca clientes por CI/RUC o por nombre+apellido (parcial), con paginado.
// Respuesta JSON: { ok, total, page, page_size, data: [{id_cliente, nombre_completo, ruc_ci, telefono, direccion}] }

session_start();
require_once __DIR__ . '/../../conexion/configv2.php'; // $conn = pg_connect(...)

header('Content-Type: application/json; charset=utf-8');

// (Opcional) proteger por sesión
if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'No autorizado']); exit;
}

$q   = trim($_GET['q'] ?? '');     // término de búsqueda (nombre, apellido o ruc_ci)
$pg  = max(1, (int)($_GET['page'] ?? 1));
$ps  = max(1, min(50, (int)($_GET['page_size'] ?? 10)));
$off = ($pg - 1) * $ps;

// Normalizaciones para ordenar mejor
$qLower  = mb_strtolower($q);
$like    = '%'.$qLower.'%';
$prefix  = $qLower.'%';

// armamos filtros: si viene vacío, listamos todos (o podés forzar error si querés)
$where   = 'WHERE 1=1';
$params  = [];
$pi      = 1; // param index

if ($q !== '') {
  // Buscamos por ruc_ci o por nombre+apellido (concatenado)
  $where .= " AND (lower(ruc_ci) LIKE $".$pi." OR lower(nombre||' '||apellido) LIKE $".($pi+1).")";
  $params[] = $like;
  $params[] = $like;
  $pi += 2;
}

// total
$sqlCount = "SELECT COUNT(*) FROM public.clientes $where";
$stCount  = pg_query_params($conn, $sqlCount, $params);
if (!$stCount) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Error count']); exit; }
$total = (int)pg_fetch_result($stCount, 0, 0);

// lista
// Orden: match exacto en ruc_ci primero, luego prefijo en nombre, luego alfabético
$sqlList = "
  SELECT
    id_cliente,
    nombre,
    apellido,
    ruc_ci,
    telefono,
    direccion,
    lower(ruc_ci) = $".$pi."                    AS exact_ruc,
    lower(nombre||' '||apellido) LIKE $".($pi+1)." AS prefix_name
  FROM public.clientes
  $where
  ORDER BY exact_ruc DESC, prefix_name DESC, nombre ASC, apellido ASC
  LIMIT $".($pi+2)." OFFSET $".($pi+3)."
";

$params[] = $qLower;     // exact_ruc
$params[] = $prefix;     // prefix_name
$params[] = $ps;         // limit
$params[] = $off;        // offset

$stList = pg_query_params($conn, $sqlList, $params);
if (!$stList) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Error list']); exit; }

$data = [];
while ($r = pg_fetch_assoc($stList)) {
  $data[] = [
    'id_cliente'      => (int)$r['id_cliente'],
    'nombre_completo' => trim($r['nombre'].' '.$r['apellido']),
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
