<?php
// listar_facturas.php — Lista facturas con filtros, búsqueda por cliente (nombre/apellido/CI) y paginación.

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

function is_iso_date($s){ return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$s); }
function get_input(){
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = json_decode(file_get_contents('php://input'), true);
    if (is_array($raw)) return $raw;
    return $_POST;
  }
  return $_GET;
}

try {
  if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','POST'], true)) {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit;
  }

  $in = get_input();

  $q           = isset($in['q']) ? trim((string)$in['q']) : null;
  $estado      = isset($in['estado']) ? trim((string)$in['estado']) : null;
  $condicion   = isset($in['condicion']) ? trim((string)$in['condicion']) : null;
  $fdesde      = isset($in['fecha_desde']) && is_iso_date($in['fecha_desde']) ? $in['fecha_desde'] : null;
  $fhasta      = isset($in['fecha_hasta']) && is_iso_date($in['fecha_hasta']) ? $in['fecha_hasta'] : null;

  $page        = isset($in['page']) ? max(1, (int)$in['page']) : 1;
  $per_page    = isset($in['per_page']) ? min(100, max(1, (int)$in['per_page'])) : 20;

  // Orden seguro (whitelist)
  $sort_by_in  = isset($in['sort_by']) ? strtolower(trim($in['sort_by'])) : 'fecha_emision';
  $sort_dir_in = isset($in['sort_dir']) ? strtolower(trim($in['sort_dir'])) : 'desc';
  $sort_dir    = ($sort_dir_in === 'asc') ? 'ASC' : 'DESC';

  $sort_map = [
    'fecha_emision'   => 'f.fecha_emision',
    'numero_documento'=> 'f.numero_documento',
    'total_neto'      => 'f.total_neto',
    'cliente'         => "unaccent(lower(coalesce(c.apellido,'')||' '||coalesce(c.nombre,'')))"
  ];
  $sort_col = isset($sort_map[$sort_by_in]) ? $sort_map[$sort_by_in] : $sort_map['fecha_emision'];

  // WHERE dinámico
  $where = [];
  $params = [];
  $i = 1;

  if (!empty($q)) {
    // Busca en cliente nombre, apellido, ruc_ci y número de documento
    $where[] = "(unaccent(lower(c.nombre)) LIKE unaccent(lower($".$i.")) 
                 OR unaccent(lower(c.apellido)) LIKE unaccent(lower($".$i."))
                 OR lower(c.ruc_ci) LIKE lower($".$i.")
                 OR lower(f.numero_documento) LIKE lower($".$i."))";
    $params[] = '%'.$q.'%';
    $i++;
  }
  if (!empty($estado)) {
    $where[] = "f.estado = $".$i;
    $params[] = $estado;
    $i++;
  }
  if (!empty($condicion)) {
    $where[] = "f.condicion_venta = $".$i;
    $params[] = $condicion;
    $i++;
  }
  if (!empty($fdesde)) {
    $where[] = "f.fecha_emision >= $".$i."::date";
    $params[] = $fdesde;
    $i++;
  }
  if (!empty($fhasta)) {
    $where[] = "f.fecha_emision <= $".$i."::date";
    $params[] = $fhasta;
    $i++;
  }

  $where_sql = count($where) ? ('WHERE '.implode(' AND ', $where)) : '';

  // COUNT total para paginación
  $sqlCount = "
    SELECT COUNT(*) AS total
    FROM public.factura_venta_cab f
    JOIN public.clientes c ON c.id_cliente = f.id_cliente
    $where_sql
  ";
  $rCount = pg_query_params($conn, $sqlCount, $params);
  if (!$rCount) { throw new Exception('No se pudo contar resultados'); }
  $total_rows = (int)pg_fetch_result($rCount, 0, 'total');

  // Datos paginados
  $offset = ($page - 1) * $per_page;

  // NOTA: para ordenar por 'cliente' usamos la expresión aliased en $sort_col
  $sqlData = "
    SELECT
      f.id_factura,
      f.numero_documento,
      f.fecha_emision,
      f.estado,
      f.condicion_venta,
      f.total_bruto,
      f.total_descuento,
      f.total_neto,
      f.total_iva10,
      f.total_iva5,
      f.total_grav10,
      f.total_grav5,
      f.total_exentas,
      f.id_pedido,
      c.id_cliente,
      c.nombre,
      c.apellido,
      c.ruc_ci
    FROM public.factura_venta_cab f
    JOIN public.clientes c ON c.id_cliente = f.id_cliente
    $where_sql
    ORDER BY $sort_col $sort_dir, f.id_factura DESC
    LIMIT $per_page OFFSET $offset
  ";

  // pg_query_params no permite parametrizar ORDER/LIMIT/OFFSET, pero son whitelisteados/integers
  $rData = pg_query_params($conn, $sqlData, $params);
  if (!$rData) { throw new Exception('No se pudo obtener los datos'); }

  $items = [];
  while ($row = pg_fetch_assoc($rData)) {
    $items[] = [
      'id_factura'        => (int)$row['id_factura'],
      'numero_documento'  => $row['numero_documento'],
      'fecha_emision'     => $row['fecha_emision'],
      'estado'            => $row['estado'],
      'condicion_venta'   => $row['condicion_venta'],
      'totales' => [
        'total_bruto'     => (float)$row['total_bruto'],
        'total_descuento' => (float)$row['total_descuento'],
        'total_iva10'     => (float)$row['total_iva10'],
        'total_iva5'      => (float)$row['total_iva5'],
        'total_grav10'    => (float)$row['total_grav10'],
        'total_grav5'     => (float)$row['total_grav5'],
        'total_exentas'   => (float)$row['total_exentas'],
        'total_neto'      => (float)$row['total_neto']
      ],
      'cliente' => [
        'id_cliente'      => (int)$row['id_cliente'],
        'nombre'          => $row['nombre'],
        'apellido'        => $row['apellido'],
        'ruc_ci'          => $row['ruc_ci'],
        'nombre_completo' => trim(($row['nombre'] ?? '').' '.($row['apellido'] ?? ''))
      ],
      'id_pedido'         => $row['id_pedido'] !== null ? (int)$row['id_pedido'] : null
    ];
  }

  echo json_encode([
    'success' => true,
    'items' => $items,
    'meta' => [
      'page'      => $page,
      'per_page'  => $per_page,
      'total'     => $total_rows,
      'pages'     => (int)ceil($total_rows / $per_page),
      'sort_by'   => array_search($sort_col, $sort_map, true) ?: $sort_by_in,
      'sort_dir'  => strtolower($sort_dir)
    ]
  ]);

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
