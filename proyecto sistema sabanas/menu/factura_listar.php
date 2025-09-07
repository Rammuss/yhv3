<?php
// factura_listar.php
header('Content-Type: application/json; charset=utf-8');

try {
  require __DIR__ . '/../conexion/configv2.php'; // $conn

  if (!$conn) {
    echo json_encode(['ok'=>false,'error'=>'Sin conexión']); exit;
  }

  // Filtros
  $id_proveedor = isset($_GET['id_proveedor']) && ctype_digit($_GET['id_proveedor']) ? (int)$_GET['id_proveedor'] : null;
  $estado       = isset($_GET['estado']) ? trim($_GET['estado']) : '';
  $desde        = isset($_GET['desde']) ? trim($_GET['desde']) : '';
  $hasta        = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

  $where  = [];
  $params = [];
  $i = 1;

  if ($id_proveedor) { $where[] = "f.id_proveedor = $" . ($i++); $params[] = $id_proveedor; }
  if ($estado !== ''){ $where[] = "f.estado = $" . ($i++);       $params[] = $estado; }
  if ($desde !== '') { $where[] = "f.fecha_emision >= $" . ($i++);$params[] = $desde; }
  if ($hasta !== '') { $where[] = "f.fecha_emision <= $" . ($i++);$params[] = $hasta; }

  $sql = "
    SELECT
      f.id_factura,
      p.nombre AS proveedor,
      to_char(f.fecha_emision,'YYYY-MM-DD') AS fecha_emision,
      f.numero_documento,
      f.timbrado_numero,                 -- << NUEVO: timbrado en el listado
      f.estado,
      f.total_factura,
      COALESCE(NULLIF(f.condicion,''),'CONTADO') AS condicion,
      f.cuotas
    FROM public.factura_compra_cab f
    JOIN public.proveedores p ON p.id_proveedor = f.id_proveedor
    " . (count($where) ? "WHERE ".implode(' AND ',$where) : "") . "
    ORDER BY f.fecha_emision DESC, f.id_factura DESC
    LIMIT 500
  ";

  $res = pg_query_params($conn, $sql, $params);
  if (!$res) {
    echo json_encode(['ok'=>false,'error'=>'Error consultando: '.pg_last_error($conn)]); exit;
  }

  $data = [];
  while ($row = pg_fetch_assoc($res)) {
    $data[] = [
      'id_factura'       => (int)$row['id_factura'],
      'proveedor'        => $row['proveedor'],
      'fecha_emision'    => $row['fecha_emision'],
      'numero_documento' => $row['numero_documento'],
      'timbrado_numero'  => $row['timbrado_numero'], // << NUEVO en la respuesta
      'estado'           => $row['estado'],
      'total_factura'    => (float)$row['total_factura'],
      // claves que usa tu front:
      'condicion'        => strtoupper(trim($row['condicion'] ?? 'CONTADO')), // CREDITO/CONTADO
      'cuotas'           => isset($row['cuotas']) ? (int)$row['cuotas'] : null,
    ];
  }

  echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
