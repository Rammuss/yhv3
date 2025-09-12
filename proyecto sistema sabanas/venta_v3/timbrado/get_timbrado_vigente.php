<?php
// get_timbrado_vigente.php
// Devuelve timbrado vigente para FACTURA

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['success'=>false,'error'=>'No autorizado']); exit;
}

try {
  $sql = "
    SELECT id_timbrado,
           numero_timbrado,
           tipo_comprobante,
           tipo_documento,
           establecimiento,
           punto_expedicion,
           nro_actual,
           nro_hasta,
           fecha_inicio,
           fecha_fin,
           estado
    FROM public.timbrado
    WHERE tipo_comprobante = 'Factura'
      AND estado = 'Vigente'
      AND CURRENT_DATE BETWEEN fecha_inicio AND fecha_fin
    ORDER BY id_timbrado DESC
    LIMIT 1
  ";
  $r = pg_query($conn, $sql);
  if (!$r || pg_num_rows($r) === 0) {
    echo json_encode(['success'=>false,'error'=>'No hay timbrado vigente para Factura']); exit;
  }

  $t = pg_fetch_assoc($r);

  $proxNro = (int)$t['nro_actual']; // Este es el que se usará al facturar
  $numFormateado = $t['establecimiento'] . '-' . $t['punto_expedicion'] . '-' . str_pad($proxNro, 7, '0', STR_PAD_LEFT);

  echo json_encode([
    'success'=>true,
    'timbrado'=>[
      'id_timbrado'      => (int)$t['id_timbrado'],
      'numero_timbrado'  => $t['numero_timbrado'],
      'tipo_comprobante' => $t['tipo_comprobante'],
      'tipo_documento'   => $t['tipo_documento'],
      'establecimiento'  => $t['establecimiento'],
      'punto_expedicion' => $t['punto_expedicion'],
      'numero_actual'    => (int)$t['nro_actual'],
      'numero_proximo'   => $proxNro,
      'numero_formateado'=> $numFormateado,
      'nro_hasta'        => (int)$t['nro_hasta'],
      'fecha_inicio'     => $t['fecha_inicio'],
      'fecha_fin'        => $t['fecha_fin'],
      'estado'           => $t['estado']
    ]
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
