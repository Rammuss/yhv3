<?php
// get_timbrado_para_caja.php
// Devuelve el timbrado vigente asignado a la caja, y el próximo número sugerido.

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try {
  if (empty($_SESSION['nombre_usuario'])) { 
    throw new Exception('No autenticado');
  }

  $id_caja = (int)($_SESSION['id_caja'] ?? 0);
  if ($id_caja <= 0) throw new Exception('Caja no asignada en sesión');

  // Aceptar tipo por GET ?tipo=Factura|NC|ND, o por ?clase=NC|ND (desde la pantalla de notas)
  $tipo = '';
  if (isset($_GET['tipo']) && trim($_GET['tipo']) !== '') {
    $tipo = trim($_GET['tipo']);
  } elseif (isset($_GET['clase']) && trim($_GET['clase']) !== '') {
    // Mapear 'clase' a tipo_comprobante
    $clase = strtoupper(trim($_GET['clase']));
    if ($clase === 'NC') $tipo = 'NC';
    elseif ($clase === 'ND') $tipo = 'ND';
  }

  // Fallback (si no vino nada). OJO: mejor siempre pasar el tipo correcto desde la UI.
  if ($tipo === '') {
    // Puedes elegir forzar a 'Factura' como último recurso.
    $tipo = 'Factura';
    // Si preferís, en vez de forzar, lanza error:
    // throw new Exception('Tipo de comprobante requerido');
  }

  $sql = "
    SELECT t.id_timbrado, t.numero_timbrado, t.tipo_comprobante,
           t.establecimiento, t.punto_expedicion,
           t.nro_desde, t.nro_hasta, t.nro_actual,
           t.fecha_inicio, t.fecha_fin, t.estado,
           a.id_asignacion
    FROM public.timbrado t
    JOIN public.timbrado_asignacion a
      ON a.id_timbrado = t.id_timbrado
     AND lower(a.estado) = 'vigente'
    WHERE a.id_caja = $1
      AND t.tipo_comprobante = $2
      AND t.estado = 'Vigente'
      AND CURRENT_DATE BETWEEN t.fecha_inicio AND t.fecha_fin
    ORDER BY t.fecha_fin ASC, t.id_timbrado ASC
    LIMIT 1
  ";
  $res = pg_query_params($conn, $sql, [$id_caja, $tipo]);
  if (!$res || pg_num_rows($res) === 0) {
    echo json_encode(['success'=>false, 'error'=>'No hay timbrado vigente asignado a esta caja para el tipo '.$tipo]);
    exit;
  }

  $row = pg_fetch_assoc($res);
  $siguiente = max((int)$row['nro_desde'], (int)$row['nro_actual'] + 1);
  $agotado   = $siguiente > (int)$row['nro_hasta'];

  echo json_encode([
    'success' => true,
    'timbrado'=> [
      'id_timbrado'       => (int)$row['id_timbrado'],
      'numero_timbrado'   => $row['numero_timbrado'],
      'tipo_comprobante'  => $row['tipo_comprobante'],
      'establecimiento'   => $row['establecimiento'],
      'punto_expedicion'  => $row['punto_expedicion'],
      'nro_desde'         => (int)$row['nro_desde'],
      'nro_hasta'         => (int)$row['nro_hasta'],
      'nro_actual'        => (int)$row['nro_actual'],
      'fecha_inicio'      => $row['fecha_inicio'],
      'fecha_fin'         => $row['fecha_fin'],
      'id_asignacion'     => (int)$row['id_asignacion'],
      'proximo_numero'    => $agotado ? null : $siguiente,
      'proximo_formateado'=> $agotado ? null : ($row['establecimiento'].'-'.$row['punto_expedicion'].'-'.str_pad($siguiente,7,'0',STR_PAD_LEFT)),
      'agotado'           => $agotado
    ]
  ]);

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
