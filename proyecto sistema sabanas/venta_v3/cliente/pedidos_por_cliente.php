<?php
// pedidos_por_cliente.php (ajustado para estado en minúsculas)
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'No autorizado']); exit;
}

$id_cliente = (int)($_GET['id_cliente'] ?? 0);
$estado     = strtolower(trim($_GET['estado'] ?? 'pendiente')); // por defecto "pendiente"
$pg  = max(1, (int)($_GET['page'] ?? 1));
$ps  = max(1, min(100, (int)($_GET['page_size'] ?? 50)));
$off = ($pg - 1) * $ps;

if ($id_cliente <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'id_cliente inválido']); exit;
}

$params = [$id_cliente];
$where  = "WHERE p.id_cliente = $1";

if ($estado !== '') {
  // Normaliza estado a lower() en la consulta
  $where .= " AND lower(trim(p.estado)) = $2";
  $params[] = $estado;
}

$sqlCount = "SELECT COUNT(*) FROM public.pedido_cab p $where";
$stCount  = pg_query_params($conn, $sqlCount, $params);
if (!$stCount) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Error count']); exit; }
$total = (int)pg_fetch_result($stCount, 0, 0);

// LISTA
$sql = "
  SELECT
    p.id_pedido,
    p.fecha_pedido,
    p.estado,
    p.total_bruto,
    p.total_descuento,
    p.total_iva,
    p.total_neto
  FROM public.pedido_cab p
  $where
  ORDER BY p.fecha_pedido DESC, p.id_pedido DESC
  LIMIT $".(count($params)+1)." OFFSET $".(count($params)+2)."
";
$params[] = $ps;
$params[] = $off;

$st = pg_query_params($conn, $sql, $params);
if (!$st) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Error list']); exit; }

$data = [];
while ($r = pg_fetch_assoc($st)) {
  $fecha = $r['fecha_pedido'];
  $data[] = [
    'id_pedido'    => (int)$r['id_pedido'],
    'fecha_pedido' => $fecha,
    'estado'       => $r['estado'],
    'total_neto'   => (float)$r['total_neto'],
    // concatenación con "." para que no falle
    'resumen'      => '#' . $r['id_pedido'] . ' | ' .
                      date('d/m/Y', strtotime($fecha)) . ' | Gs. ' .
                      number_format($r['total_neto'], 0, ',', '.')
  ];
}

echo json_encode([
  'ok'        => true,
  'total'     => $total,
  'page'      => $pg,
  'page_size' => $ps,
  'data'      => $data
]);
