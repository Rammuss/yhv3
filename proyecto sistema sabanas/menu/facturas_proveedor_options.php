<?php
// facturas_proveedor_options.php
header('Content-Type: application/json; charset=utf-8');

try {
  require __DIR__ . '/../conexion/configv2.php'; // Debe definir $conn = pg_connect(...)
  if (!$conn) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Sin conexión a la BD']); exit;
  }

  /* ============
     ENTRADA
     ============ */
  // id_proveedor es requerido
  if (!isset($_GET['id_proveedor']) || !ctype_digit($_GET['id_proveedor'])) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'id_proveedor inválido']); exit;
  }
  $id_proveedor = (int)$_GET['id_proveedor'];

  // Filtros opcionales
  $estado       = isset($_GET['estado']) ? trim($_GET['estado']) : '';     // p.ej. 'Registrada' | 'Anulada' | ''(todas)
  $solo_saldo   = isset($_GET['solo_con_saldo']) && $_GET['solo_con_saldo']=='1'; // solo facturas con saldo > 0
  $desde        = isset($_GET['desde']) ? trim($_GET['desde']) : '';       // 'YYYY-MM-DD'
  $hasta        = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';       // 'YYYY-MM-DD'
  $q            = isset($_GET['q']) ? trim($_GET['q']) : '';               // busca en numero_documento
  $limit        = isset($_GET['limit']) && ctype_digit($_GET['limit']) ? max(1, min(1000, (int)$_GET['limit'])) : 200;

  /* ============
     QUERY
     ============ */
  $where = [];
  $params = [];
  $i = 1;

  // Proveedor (obligatorio)
  $where[] = "f.id_proveedor = $" . ($i++); $params[] = $id_proveedor;

  // Estado
  if ($estado !== '') {
    $where[] = "f.estado = $" . ($i++); $params[] = $estado;
  } else {
    // por defecto, no traer anuladas (ajustá si querés todas)
    $where[] = "f.estado <> 'Anulada'";
  }

  // Rango fechas
  if ($desde !== '') { $where[] = "f.fecha_emision >= $" . ($i++); $params[] = $desde; }
  if ($hasta !== '') { $where[] = "f.fecha_emision <= $" . ($i++); $params[] = $hasta; }

  // Búsqueda por nro doc
  if ($q !== '') {
    $where[] = "f.numero_documento ILIKE $" . ($i++); $params[] = '%'.$q.'%';
  }

  // Sólo con saldo > 0
  $saldoClause = $solo_saldo ? " AND COALESCE(cxp.saldo_actual, 0) > 0 " : "";

  $sql = "
    SELECT
      f.id_factura,
      f.numero_documento,
      to_char(f.fecha_emision, 'YYYY-MM-DD') AS fecha_emision,
      f.estado,
      f.total_factura,
      f.moneda,
      cxp.id_cxp,
      COALESCE(cxp.total_cxp, f.total_factura) AS total_cxp,
      COALESCE(cxp.saldo_actual, 0)            AS saldo
    FROM public.factura_compra_cab f
    LEFT JOIN public.cuenta_pagar cxp ON cxp.id_factura = f.id_factura
    " . (count($where) ? "WHERE ".implode(' AND ',$where) : "") . "
      $saldoClause
    ORDER BY f.fecha_emision DESC, f.id_factura DESC
    LIMIT $limit
  ";

  $res = pg_query_params($conn, $sql, $params);
  if (!$res) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Error consultando: '.pg_last_error($conn)]); exit;
  }

  $data = [];
  while ($row = pg_fetch_assoc($res)) {
    $data[] = [
      'id_factura'       => (int)$row['id_factura'],
      'numero_documento' => $row['numero_documento'],
      'fecha_emision'    => $row['fecha_emision'],
      'estado'           => $row['estado'],
      'moneda'           => $row['moneda'],
      'total'            => isset($row['total_cxp']) ? (float)$row['total_cxp'] : (float)$row['total_factura'],
      'saldo'            => (float)$row['saldo'],
      'id_cxp'           => isset($row['id_cxp']) ? (int)$row['id_cxp'] : null
    ];
  }

  // Para tu select, no hace falta el wrapper {ok:true}; el front lo espera como array
  echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
