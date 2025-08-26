<?php
// stock.php
header('Content-Type: application/json; charset=utf-8');

include "../conexion/configv2.php"; // Usa tu conexión actual

function respond($arr, $code = 200) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

// Helpers
function stockById($conn, $id) {
  $sql = "
    SELECT COALESCE(SUM(
      CASE WHEN tipo_movimiento='entrada' THEN cantidad ELSE -cantidad END
    ),0)::int AS stock_actual
    FROM public.movimiento_stock
    WHERE id_producto = $1
  ";
  $result = pg_query_params($conn, $sql, array($id));
  if (!$result) return 0;
  $row = pg_fetch_assoc($result);
  return (int)$row['stock_actual'];
}

function listProductsWithStock($conn, $search = '', $limit = 50, $offset = 0, $onlyIds = []) {
  $sql = "
    SELECT
      p.id_producto,
      p.nombre,
      p.precio_unitario,
      p.precio_compra,
      COALESCE(ms.stock,0)::int AS stock_actual
    FROM public.producto p
    LEFT JOIN (
      SELECT id_producto,
             SUM(CASE WHEN tipo_movimiento='entrada' THEN cantidad ELSE -cantidad END) AS stock
      FROM public.movimiento_stock
      GROUP BY id_producto
    ) ms ON ms.id_producto = p.id_producto
    WHERE 1=1
  ";

  $params = [];
  $paramIndex = 1;

  if ($search !== '') {
    $sql .= " AND (p.nombre ILIKE $" . ($paramIndex + 1) . " OR CAST(p.id_producto AS TEXT) ILIKE $" . ($paramIndex + 1) . ") ";
    $params[] = "%$search%";
    $paramIndex++;
  }

  if (!empty($onlyIds)) {
    $placeholders = [];
    foreach ($onlyIds as $i => $v) {
      $placeholders[] = '$' . ($paramIndex + 1);
      $params[] = (int)$v;
      $paramIndex++;
    }
    $sql .= " AND p.id_producto IN (" . implode(',', $placeholders) . ") ";
  }

  $sql .= " ORDER BY p.nombre ASC LIMIT $" . ($paramIndex + 1) . " OFFSET $" . ($paramIndex + 2);
  $params[] = (int)$limit;
  $params[] = (int)$offset;

  $result = pg_query_params($conn, $sql, $params);
  $rows = [];
  while ($row = pg_fetch_assoc($result)) {
    $rows[] = $row;
  }
  return $rows;
}

// Router simple
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$idsParam  = isset($_GET['ids']) ? trim($_GET['ids']) : '';
$all       = isset($_GET['all']) ? (int)$_GET['all'] : 0;
$search    = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit     = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset    = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

try {
  // 1) stock por product_id
  if ($productId > 0) {
    $stock = stockById($conn, $productId);
    respond(['ok'=>true, 'data'=>['id_producto'=>$productId, 'stock_actual'=>$stock]]);
  }

  // 2) stock batch por ids=10,11,12
  if ($idsParam !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $idsParam)), function($n){ return $n>0; }));
    if (empty($ids)) respond(['ok'=>false, 'error'=>'ids inválidos'], 400);

    $rows = listProductsWithStock($conn, '', 10000, 0, $ids);
    $byId = [];
    foreach ($rows as $r) $byId[(int)$r['id_producto']] = $r;

    $result = [];
    foreach ($ids as $id) {
      if (isset($byId[$id])) $result[] = $byId[$id];
      else $result[] = ['id_producto'=>$id, 'nombre'=>null, 'precio_unitario'=>null, 'precio_compra'=>null, 'stock_actual'=>0];
    }
    respond(['ok'=>true, 'data'=>$result]);
  }

  // 3) all=1 → lista paginada para modal (con search opcional)
  if ($all === 1) {
    $rows = listProductsWithStock($conn, $search, $limit, $offset);
    respond(['ok'=>true, 'data'=>$rows, 'paging'=>['limit'=>$limit, 'offset'=>$offset]]);
  }

  // Default: mala solicitud
  respond(['ok'=>false, 'error'=>'Parámetros no válidos. Usa product_id, ids o all=1'], 400);

} catch (Throwable $e) {
  respond(['ok'=>false, 'error'=>'Error interno'], 500);
}
