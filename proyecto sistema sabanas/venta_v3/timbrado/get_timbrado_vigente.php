<?php
// get_timbrado_info.php  — Preview por caja (NO reserva número)
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['success'=>false,'error'=>'No autorizado']); exit;
}

$idCaja = isset($_SESSION['id_caja']) ? (int)$_SESSION['id_caja'] : (int)($_GET['id_caja'] ?? 0);
if ($idCaja <= 0) { echo json_encode(['success'=>false,'error'=>'id_caja requerido']); exit; }

try {
  $sql = "
    WITH tim AS (
      SELECT id_timbrado, numero_timbrado, tipo_comprobante, tipo_documento,
             establecimiento, punto_expedicion, nro_desde, nro_hasta,
             fecha_inicio, fecha_fin, estado
      FROM public.timbrado
      WHERE tipo_comprobante = 'Factura'
        AND estado = 'Vigente'
        AND CURRENT_DATE BETWEEN fecha_inicio AND fecha_fin
      ORDER BY id_timbrado DESC
      LIMIT 1
    )
    SELECT t.*,
           a.id_asignacion,
           a.siguiente_numero AS next_preview,
           GREATEST(a.hasta_numero - a.siguiente_numero + 1, 0) AS disponibles_caja
    FROM tim t
    LEFT JOIN public.timbrado_asignacion a
      ON a.id_timbrado = t.id_timbrado
     AND a.id_caja     = $1
     AND a.estado      = 'Vigente'
     AND a.siguiente_numero <= a.hasta_numero
    ORDER BY a.id_asignacion
    LIMIT 1
  ";

  $r = pg_query_params($conn, $sql, [$idCaja]);
  if (!$r || pg_num_rows($r) === 0) {
    echo json_encode(['success'=>false,'error'=>'No hay timbrado vigente para Factura']); exit;
  }

  $t = pg_fetch_assoc($r);

  $preview = isset($t['next_preview']) ? (int)$t['next_preview'] : null;
  $previewFmt = $preview
    ? ($t['establecimiento'].'-'.$t['punto_expedicion'].'-'.str_pad($preview, 7, '0', STR_PAD_LEFT))
    : null;

  echo json_encode([
    'success'=> true,
    'timbrado'=>[
      'id_timbrado'       => (int)$t['id_timbrado'],
      'numero_timbrado'   => $t['numero_timbrado'],
      'tipo_comprobante'  => $t['tipo_comprobante'],
      'tipo_documento'    => $t['tipo_documento'],
      'establecimiento'   => $t['establecimiento'],
      'punto_expedicion'  => $t['punto_expedicion'],
      'nro_desde'         => (int)$t['nro_desde'],
      'nro_hasta'         => (int)$t['nro_hasta'],
      'fecha_inicio'      => $t['fecha_inicio'],
      'fecha_fin'         => $t['fecha_fin'],
      'estado'            => $t['estado'],
      'id_asignacion'     => isset($t['id_asignacion']) ? (int)$t['id_asignacion'] : null,
      'disponibles_caja'  => isset($t['disponibles_caja']) ? (int)$t['disponibles_caja'] : 0,
      'next_preview'      => $preview,      // informativo
      'next_preview_fmt'  => $previewFmt,   // informativo
      'nota'              => 'El número definitivo se asigna al confirmar (reservar_numero).'
    ]
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
