<?php
// productos_disponibles.php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';

header('Content-Type: application/json; charset=utf-8');

// (opcional) bloquear si no hay sesión
if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['success'=>false,'error'=>'No autorizado']);
  exit;
}

// detectar nombre de columna para el tipo en movimiento_stock
$col = 'tipo_movimiento';
$chk = pg_query_params($conn, "SELECT 1 FROM information_schema.columns
  WHERE table_schema='public' AND table_name='movimiento_stock' AND column_name='tipo_movimiento' LIMIT 1", []);
if (!$chk || pg_num_rows($chk) === 0) {
  $chk2 = pg_query_params($conn, "SELECT 1 FROM information_schema.columns
    WHERE table_schema='public' AND table_name='movimiento_stock' AND column_name='tipo' LIMIT 1", []);
  if ($chk2 && pg_num_rows($chk2) > 0) {
    $col = 'tipo';
  } else {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>"No se encontró la columna de tipo ('tipo_movimiento' o 'tipo') en movimiento_stock"]);
    exit;
  }
}

$q = trim($_GET['q'] ?? '');
$showAll = isset($_GET['all']) && $_GET['all'] == '1';

try {
  // filtro por nombre (opcional)
  $whereName = $q !== '' ? "AND LOWER(p.nombre) LIKE LOWER($1)" : "";

  // si no es debug, mostramos solo con disponible > 0
  $whereDisponible = $showAll ? '' : "AND (COALESCE(f.stock_fisico,0) - COALESCE(r.reservado_activo,0)) > 0";

  $sql = "
    WITH fisico AS (
      SELECT id_producto,
             SUM(
               CASE TRIM(LOWER($col))
                 WHEN 'entrada' THEN ABS(cantidad)::numeric
                 WHEN 'salida'  THEN -ABS(cantidad)::numeric
                 ELSE 0::numeric
               END
             ) AS stock_fisico
      FROM public.movimiento_stock
      GROUP BY id_producto
    ),
    reservado AS (
      SELECT id_producto,
             COALESCE(SUM(cantidad),0)::numeric AS reservado_activo
      FROM public.reserva_stock
      WHERE TRIM(LOWER(estado)) = 'activa'      -- <<< SOLO reservas activas
      GROUP BY id_producto
    )
    SELECT
      p.id_producto,
      p.nombre,
      p.precio_unitario,
      COALESCE(p.tipo_iva, 'Exento') AS tipo_iva,
      COALESCE(f.stock_fisico,0) AS stock_fisico,
      COALESCE(r.reservado_activo,0) AS reservado_activo,
      GREATEST(COALESCE(f.stock_fisico,0) - COALESCE(r.reservado_activo,0), 0) AS stock_disponible
    FROM public.producto p
    LEFT JOIN fisico   f ON f.id_producto = p.id_producto
    LEFT JOIN reservado r ON r.id_producto = p.id_producto
    WHERE TRIM(LOWER(p.estado)) = 'activo'
      $whereName
      $whereDisponible
    ORDER BY p.nombre ASC
  ";

  if ($q !== '') {
    $res = pg_query_params($conn, $sql, ['%'.$q.'%']);
  } else {
    $res = pg_query($conn, $sql);
  }
  if (!$res) {
    throw new Exception('Error al consultar productos');
  }

  $productos = [];
  while ($row = pg_fetch_assoc($res)) {
    $productos[] = [
      'id_producto'      => (int)$row['id_producto'],
      'nombre'           => $row['nombre'],
      'precio_unitario'  => (float)$row['precio_unitario'],
      'tipo_iva'         => $row['tipo_iva'],
      'stock_fisico'     => (float)$row['stock_fisico'],
      'reservado_activo' => (float)$row['reservado_activo'],
      'stock_disponible' => (float)$row['stock_disponible'],
    ];
  }

  echo json_encode(['success'=>true,'productos'=>$productos]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
