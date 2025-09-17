<?php
// ventas/remision/anular_remision.php
session_start();
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try{
  if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception('Método no permitido');
  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;

  $id = (int)($in['id_remision'] ?? 0);
  $motivo = trim($in['motivo'] ?? '');

  if ($id<=0) throw new Exception('id_remision inválido');

  pg_query($conn, 'BEGIN');

  // Lock y validación
  $r = pg_query_params($conn, "
    SELECT id_remision_venta, estado, observacion
    FROM public.remision_venta_cab
    WHERE id_remision_venta = $1
    FOR UPDATE
  ", [$id]);
  if(!$r || pg_num_rows($r)===0) throw new Exception('Remisión no encontrada');

  $row = pg_fetch_assoc($r);
  $estado = strtolower($row['estado'] ?? '');
  if ($estado === 'anulada') throw new Exception('La remisión ya está anulada');

  // Armar observación con marca de anulación
  $usr = $_SESSION['nombre_usuario'] ?? 'sistema';
  $marca = 'Anulada el '.date('Y-m-d H:i').' por '.$usr.($motivo ? (' · Motivo: '.$motivo) : '');
  $obsNueva = trim(($row['observacion'] ?? '').($row['observacion'] ? " | " : "").$marca);

  // Actualizar estado
  $ok = pg_query_params($conn, "
    UPDATE public.remision_venta_cab
       SET estado='Anulada', observacion=$2, actualizado_en=NOW()
     WHERE id_remision_venta=$1
  ", [$id, $obsNueva]);
  if (!$ok) throw new Exception('No se pudo anular');

  /* 
   * Si tu emisión de remisión genera movimiento de stock,
   * acá podrías revertirlo (insertar entradas inversas, etc.).
   * Dejamos el hook comentado para no romper nada:
   *
   * TODO: revertir stock si aplica
   */

  pg_query($conn, 'COMMIT');
  echo json_encode(['success'=>true]);

}catch(Throwable $e){
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
