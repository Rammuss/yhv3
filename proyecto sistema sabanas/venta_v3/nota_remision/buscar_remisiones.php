<?php
// ventas/remision/buscar_remisiones.php
session_start();
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

function like_or_null($s){ return $s!=='' ? "%$s%" : null; }
function is_iso_date($s){ return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$s); }

try{
  $num   = isset($_GET['num'])   ? trim($_GET['num'])   : '';
  $cli   = isset($_GET['cli'])   ? trim($_GET['cli'])   : '';
  $est   = isset($_GET['est'])   ? trim($_GET['est'])   : '';
  $desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
  $hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

  $page      = max(1, (int)($_GET['page'] ?? 1));
  $page_size = min(100, max(1, (int)($_GET['page_size'] ?? 50)));
  $off = ($page-1)*$page_size;

  $w=[]; $p=[];
  // Evitar datos corruptos
  $w[] = "1=1";

  if ($num !== '') {
    $w[] = "r.numero_documento ILIKE $".(count($p)+1);
    $p[] = like_or_null($num);
  }
  if ($cli !== '') {
    $w[] = "(c.ruc_ci ILIKE $".(count($p)+1)." OR c.nombre ILIKE $".(count($p)+1)." OR c.apellido ILIKE $".(count($p)+1).")";
    $p[] = like_or_null($cli);
  }
  if ($est !== '') {
    $w[] = "COALESCE(r.estado,'') = $".(count($p)+1);
    $p[] = $est;
  }
  if ($desde !== '' && is_iso_date($desde)) {
    $w[] = "r.fecha >= $".(count($p)+1);
    $p[] = $desde;
  }
  if ($hasta !== '' && is_iso_date($hasta)) {
    $w[] = "r.fecha <= $".(count($p)+1);
    $p[] = $hasta;
  }

  $where = $w ? 'WHERE '.implode(' AND ',$w) : '';

  $sql = "
    SELECT
      r.id_remision_venta,
      r.numero_documento,
      r.fecha,
      COALESCE(r.estado,'Emitida') AS estado,
      r.id_factura,
      f.numero_documento AS factura_numero,
      c.id_cliente,
      (c.nombre||' '||COALESCE(c.apellido,'')) AS cliente,
      c.ruc_ci,
      -- Totales desde el detalle
      (SELECT COUNT(*) FROM public.remision_venta_det d WHERE d.id_remision_venta = r.id_remision_venta) AS cant_items,
      (SELECT COALESCE(SUM(d.cantidad),0)::numeric(14,3) FROM public.remision_venta_det d WHERE d.id_remision_venta = r.id_remision_venta) AS cant_total
    FROM public.remision_venta_cab r
    JOIN public.clientes c ON c.id_cliente = r.id_cliente
    LEFT JOIN public.factura_venta_cab f ON f.id_factura = r.id_factura
    $where
    ORDER BY r.fecha DESC, r.id_remision_venta DESC
    LIMIT $page_size OFFSET $off
  ";

  $r = pg_query_params($conn, $sql, $p);
  if (!$r) throw new Exception('Error al buscar');

  $out=[];
  while($row=pg_fetch_assoc($r)){
    $out[] = [
      'id_remision'     => (int)$row['id_remision_venta'],
      'numero_documento'=> $row['numero_documento'],
      'fecha'           => $row['fecha'],
      'estado'          => $row['estado'],
      'id_factura'      => (int)$row['id_factura'],
      'factura_numero'  => $row['factura_numero'],
      'cliente'         => $row['cliente'],
      'ruc_ci'          => $row['ruc_ci'],
      'cant_items'      => (int)$row['cant_items'],
      'cant_total'      => (float)$row['cant_total'],
    ];
  }

  echo json_encode(['success'=>true,'remisiones'=>$out]);

}catch(Throwable $e){
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
