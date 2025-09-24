<?php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try {
  if (empty($_SESSION['nombre_usuario'])) { throw new Exception('No autenticado'); }

  // ===== Params =====
  $q         = isset($_GET['q']) ? trim($_GET['q']) : '';
  $id_cli    = isset($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0;

  // Estados: estado=Emitida|Cancelada|Anulada|*  o  estados[]=Emitida&estados[]=Anulada
  $estado    = isset($_GET['estado']) ? trim($_GET['estado']) : '*';
  $estados   = isset($_GET['estados']) && is_array($_GET['estados']) ? array_filter($_GET['estados']) : [];

  // Fechas
  $desde     = isset($_GET['desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde']) ? $_GET['desde'] : null;
  $hasta     = isset($_GET['hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta']) ? $_GET['hasta'] : null;

  // Chips rápidas: hoy | ult7 | este_mes | mes_pasado
  $preset    = isset($_GET['preset']) ? trim($_GET['preset']) : '';

  // Condición de venta opcional
  $cond      = isset($_GET['cond']) ? trim($_GET['cond']) : '';

  // Paginación
  $limit     = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
  $offset    = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

  // ===== Resolver preset → fechas =====
  if ($preset !== '') {
    $hoy = new DateTime('today', new DateTimeZone('America/Asuncion'));
    if ($preset === 'hoy') {
      $desde = $hoy->format('Y-m-d');
      $hasta = $hoy->format('Y-m-d');
    } elseif ($preset === 'ult7') {
      $d1 = (clone $hoy)->modify('-6 days');
      $desde = $d1->format('Y-m-d');
      $hasta = $hoy->format('Y-m-d');
    } elseif ($preset === 'este_mes') {
      $d1 = new DateTime($hoy->format('Y-m-01'), $hoy->getTimezone());
      $d2 = (clone $d1)->modify('last day of this month');
      $desde = $d1->format('Y-m-d');
      $hasta = $d2->format('Y-m-d');
    } elseif ($preset === 'mes_pasado') {
      $d1 = (clone $hoy)->modify('first day of last month');
      $d2 = (clone $hoy)->modify('last day of last month');
      $desde = $d1->format('Y-m-d');
      $hasta = $d2->format('Y-m-d');
    }
  }

  // ===== Where dinámico =====
  $where  = [];
  $params = [];
  $pi = 1;

  // Estado(s)
  if (!empty($estados)) {
    // normalizar y validar
    $valid = ['Emitida','Cancelada','Anulada'];
    $vals = array_values(array_intersect($estados, $valid));
    if (!empty($vals)) {
      $in = [];
      foreach ($vals as $v) { $in[] = '$'.$pi; $params[] = $v; $pi++; }
      $where[] = "f.estado IN (".implode(',', $in).")";
    }
  } elseif ($estado !== '*' && in_array($estado, ['Emitida','Cancelada','Anulada'], true)) {
    $where[] = "f.estado = $".$pi; $params[] = $estado; $pi++;
  }

  // Condición de venta
  if ($cond === 'Contado' || $cond === 'Credito') {
    $where[] = "f.condicion_venta = $".$pi; $params[] = $cond; $pi++;
  }

  // Cliente (id)
  if ($id_cli > 0) {
    $where[] = "f.id_cliente = $".$pi; $params[] = $id_cli; $pi++;
  }

  // Fechas
  if ($desde && $hasta) {
    $where[] = "f.fecha_emision BETWEEN $".$pi." AND $".($pi+1);
    $params[] = $desde; $params[] = $hasta; $pi+=2;
  } elseif ($desde) {
    $where[] = "f.fecha_emision >= $".$pi; $params[] = $desde; $pi++;
  } elseif ($hasta) {
    $where[] = "f.fecha_emision <= $".$pi; $params[] = $hasta; $pi++;
  }

  // Texto libre / nro doc / cliente / RUC
  if ($q !== '') {
    $q_nodash = str_replace('-', '', $q);
    $where[] = "("
      ." c.ruc_ci ILIKE $".$pi
      ." OR (c.nombre||' '||c.apellido) ILIKE $".($pi+1)
      ." OR f.numero_documento ILIKE $".($pi+2)
      ." OR translate(f.numero_documento,'-','') ILIKE translate($".($pi+3).",' -','')"
    .")";
    $params[] = '%'.$q.'%';
    $params[] = '%'.$q.'%';
    $params[] = '%'.$q.'%';
    $params[] = '%'.$q_nodash.'%';
    $pi += 4;
  }

  if (empty($where)) { $where[] = '1=1'; }

  // ===== Query con total_count para paginar =====
  $sql = "
    SELECT
      f.id_factura,
      f.numero_documento,
      f.fecha_emision AS fecha,
      (c.nombre||' '||c.apellido) AS cliente,
      c.ruc_ci AS ruc,
      f.total_factura AS total,
      f.condicion_venta,
      f.estado,
      f.id_timbrado,
      (t.establecimiento||'-'||t.punto_expedicion) AS ppp,
      COUNT(*) OVER() AS total_count
    FROM public.factura_venta_cab f
    JOIN public.clientes c ON c.id_cliente = f.id_cliente
    LEFT JOIN public.timbrado t ON t.id_timbrado = f.id_timbrado
    WHERE ".implode(' AND ', $where)."
    ORDER BY f.fecha_emision DESC, f.id_factura DESC
    LIMIT $limit OFFSET $offset
  ";

  $res = pg_query_params($conn, $sql, $params);
  if ($res === false) { throw new Exception('No se pudo buscar'); }

  $rows = [];
  $total_count = 0;
  while ($row = pg_fetch_assoc($res)) {
    $total_count = (int)$row['total_count'];
    $rows[] = [
      'id_factura'       => (int)$row['id_factura'],
      'numero_documento' => $row['numero_documento'],
      'fecha'            => $row['fecha'],
      'cliente'          => $row['cliente'],
      'ruc'              => $row['ruc'],
      'total'            => (float)$row['total'],
      'condicion_venta'  => $row['condicion_venta'],
      'estado'           => $row['estado'],
      'id_timbrado'      => $row['id_timbrado'] ? (int)$row['id_timbrado'] : null,
      'ppp'              => $row['ppp'] ?: null
    ];
  }

  echo json_encode([
    'success'     => true,
    'data'        => $rows,
    'count'       => count($rows),
    'total_count' => $total_count,
    'has_more'    => ($offset + count($rows)) < $total_count
  ]);

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
