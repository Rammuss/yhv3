<?php
// ../timbrado/get_timbrado_info.php  — Preview por caja (NO reserva número)
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['nombre_usuario'])) {
  http_response_code(401);
  echo json_encode(['success'=>false,'error'=>'No autorizado']); exit;
}

$idCaja = isset($_SESSION['id_caja']) ? (int)$_SESSION['id_caja'] : (int)($_GET['id_caja'] ?? 0);
if ($idCaja <= 0) {
  echo json_encode(['success'=>false,'error'=>'id_caja requerido']); exit;
}

try {
  // 1) Timbrado vigente (Factura)
  $sqlTim = "
    SELECT id_timbrado, numero_timbrado, tipo_comprobante, tipo_documento,
           establecimiento, punto_expedicion, nro_desde, nro_hasta,
           fecha_inicio, fecha_fin, estado
    FROM public.timbrado
    WHERE tipo_comprobante = 'Factura'
      AND estado = 'Vigente'
      AND CURRENT_DATE BETWEEN fecha_inicio AND fecha_fin
    ORDER BY id_timbrado DESC
    LIMIT 1
  ";
  $rt = pg_query($conn, $sqlTim);
  if (!$rt || pg_num_rows($rt) === 0) {
    echo json_encode(['success'=>false,'error'=>'No hay timbrado vigente para Factura']); exit;
  }
  $t = pg_fetch_assoc($rt);
  $id_timbrado = (int)$t['id_timbrado'];

  // 2) Bloque VIGENTE de ESTA CAJA (el más reciente)
  $ra = pg_query_params($conn, "
    SELECT id_asignacion, desde_numero, hasta_numero, siguiente_numero, estado
    FROM public.timbrado_asignacion
    WHERE id_timbrado = $1
      AND id_caja     = $2
      AND estado      = 'Vigente'
      AND siguiente_numero <= hasta_numero
    ORDER BY id_asignacion DESC
    LIMIT 1
  ", [$id_timbrado, $idCaja]);

  $id_asignacion = null;
  $next_preview = null;
  $disponibles_caja = 0;

  if ($ra && pg_num_rows($ra) > 0) {
    $a = pg_fetch_assoc($ra);
    $id_asignacion   = (int)$a['id_asignacion'];
    $desde           = (int)$a['desde_numero'];
    $hasta           = (int)$a['hasta_numero'];
    $siguiente       = (int)$a['siguiente_numero'];

    if ($siguiente <= $hasta) {
      $next_preview = $siguiente;
      $disponibles_caja = max(0, $hasta - $siguiente + 1);
    }
  }

  // 3) Formato amigable
  $fmt7 = function($n){ return str_pad((string)$n, 7, '0', STR_PAD_LEFT); };
  $next_preview_fmt = null;
  if ($next_preview !== null) {
    $next_preview_fmt = $t['establecimiento'].'-'.$t['punto_expedicion'].'-'.$fmt7($next_preview);
  }

  // 4) Respuesta — OJO: campos de preview en el NIVEL RAÍZ
  echo json_encode([
    'success' => true,
    'timbrado'=> [
      'id_timbrado'      => (int)$t['id_timbrado'],
      'numero_timbrado'  => $t['numero_timbrado'],
      'tipo_comprobante' => $t['tipo_comprobante'],
      'tipo_documento'   => $t['tipo_documento'],
      'establecimiento'  => $t['establecimiento'],
      'punto_expedicion' => $t['punto_expedicion'],
      'nro_desde'        => (int)$t['nro_desde'],
      'nro_hasta'        => (int)$t['nro_hasta'],
      'fecha_inicio'     => $t['fecha_inicio'],
      'fecha_fin'        => $t['fecha_fin'],
      'estado'           => $t['estado']
    ],
    'id_asignacion'    => $id_asignacion,       // ← raíz
    'next_preview'     => $next_preview,        // ← raíz
    'next_preview_fmt' => $next_preview_fmt,    // ← raíz
    'disponibles_caja' => $disponibles_caja,    // ← raíz
    'nota'             => 'El número definitivo se asigna al confirmar (reservar_numero).'
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
