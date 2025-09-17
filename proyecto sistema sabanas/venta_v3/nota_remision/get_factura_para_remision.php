<?php
// nota_remision/get_factura_para_remision.php
session_start();
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try{
  $id = (int)($_GET['id_factura'] ?? 0);
  if ($id <= 0) throw new Exception('id_factura inválido');

  // Cabecera
  $sqlC = "
    SELECT f.id_factura, f.numero_documento, f.fecha_emision,
           f.id_cliente, (c.nombre||' '||COALESCE(c.apellido,'')) AS cliente,
           COALESCE(f.total_neto,0)::numeric(14,2) AS total
    FROM public.factura_venta_cab f
    JOIN public.clientes c ON c.id_cliente = f.id_cliente
    WHERE f.id_factura = $1
      AND COALESCE(LOWER(f.estado),'') <> 'anulada'
    LIMIT 1
  ";
  $rc = pg_query_params($conn, $sqlC, [$id]);
  if (!$rc || pg_num_rows($rc)===0) throw new Exception('Factura no encontrada');
  $F = pg_fetch_assoc($rc);

  // Detalle (solo productos) + remitido por producto
  $sqlD = "
    SELECT
      d.id_producto,
      COALESCE(p.tipo_item,'P') AS tipo_item,
      COALESCE(p.nombre, d.descripcion) AS descripcion,
      COALESCE(d.unidad,'UNI') AS unidad,
      d.cantidad::numeric(14,3) AS cant_facturada,
      COALESCE((
        SELECT SUM(rd.cantidad)::numeric(14,3)
        FROM public.remision_venta_det rd
        JOIN public.remision_venta_cab rc
             ON rc.id_remision_venta = rd.id_remision_venta
        WHERE rd.id_factura = d.id_factura
          AND COALESCE(LOWER(rc.estado),'') <> 'anulada'
          AND rd.id_producto = d.id_producto
      ),0)::numeric(14,3) AS cant_remitida
    FROM public.factura_venta_det d
    JOIN public.producto p ON p.id_producto = d.id_producto
    WHERE d.id_factura = $1
      AND COALESCE(p.tipo_item,'P') = 'P'
    ORDER BY descripcion
  ";
  $rd = pg_query_params($conn, $sqlD, [$id]);
  if ($rd === false) throw new Exception('No se pudo leer el detalle');

  $items = [];
  while($r = pg_fetch_assoc($rd)){
    $fact = (float)$r['cant_facturada'];
    $rem  = (float)$r['cant_remitida'];
    $pend = max(0.0, $fact - $rem);
    $items[] = [
      'id_producto'     => isset($r['id_producto']) ? (int)$r['id_producto'] : null,
      'tipo_item'       => $r['tipo_item'],
      'descripcion'     => $r['descripcion'],
      'unidad'          => $r['unidad'],
      'cant_facturada'  => $fact,
      'cant_remitida'   => $rem,
      'cant_pendiente'  => $pend
    ];
  }

  echo json_encode(['success'=>true,'factura'=>$F,'items'=>$items]);

}catch(Throwable $e){
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
