<?php
// nota_remision/buscar_facturas_para_remision.php
session_start();
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

function like_or_null($s){ return $s!=='' ? "%$s%" : null; }
function is_iso_date($s){ return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$s); }

try{
  $num       = isset($_GET['num'])       ? trim($_GET['num'])       : '';
  $cli       = isset($_GET['cli'])       ? trim($_GET['cli'])       : '';
  $desde     = isset($_GET['desde'])     ? trim($_GET['desde'])     : '';
  $hasta     = isset($_GET['hasta'])     ? trim($_GET['hasta'])     : '';
  $pendiente = isset($_GET['pendiente']) ? (int)$_GET['pendiente']  : 0;

  $page      = max(1, (int)($_GET['page'] ?? 1));
  $page_size = min(100, max(1, (int)($_GET['page_size'] ?? 20)));
  $off = ($page-1)*$page_size;

  // Filtros base (sobre factura_venta_cab + clientes)
  $w = [];
  $p = [];

  // Evitar facturas anuladas
  $w[] = "COALESCE(LOWER(f.estado),'') <> 'anulada'";

  if ($num !== '') {
    $w[] = "f.numero_documento ILIKE $".(count($p)+1);
    $p[] = like_or_null($num);
  }
  if ($cli !== '') {
    $w[] = "(c.ruc_ci ILIKE $".(count($p)+1)." OR c.nombre ILIKE $".(count($p)+1)." OR c.apellido ILIKE $".(count($p)+1).")";
    $p[] = like_or_null($cli);
  }
  if ($desde !== '' && is_iso_date($desde)) {
    $w[] = "f.fecha_emision >= $".(count($p)+1);
    $p[] = $desde;
  }
  if ($hasta !== '' && is_iso_date($hasta)) {
    $w[] = "f.fecha_emision <= $".(count($p)+1);
    $p[] = $hasta;
  }

  $where = $w ? ('WHERE '.implode(' AND ',$w)) : '';

  // Usamos CTE para calcular facturada, remitida y pendiente, y luego filtramos si se pidió "solo pendientes"
  $sql = "
    WITH t AS (
      SELECT
        f.id_factura,
        f.numero_documento,
        f.fecha_emision,
        (COALESCE(f.total_neto,0))::numeric(14,2) AS total,
        (c.nombre||' '||COALESCE(c.apellido,'')) AS cliente,
        c.ruc_ci,

        /* Cantidad facturada (solo productos) */
        (
          SELECT COALESCE(SUM(d.cantidad),0)::numeric(14,3)
          FROM public.factura_venta_det d
          JOIN public.producto p ON p.id_producto = d.id_producto
          WHERE d.id_factura = f.id_factura
            AND COALESCE(p.tipo_item,'P') = 'P'
        ) AS cant_facturada,

        /* Cantidad remitida acumulada (solo productos, remisiones no anuladas) */
        (
          SELECT COALESCE(SUM(rd.cantidad),0)::numeric(14,3)
          FROM public.remision_venta_det rd
          JOIN public.remision_venta_cab rc
               ON rc.id_remision_venta = rd.id_remision_venta
          WHERE rd.id_factura = f.id_factura
            AND COALESCE(LOWER(rc.estado),'') <> 'anulada'
            AND COALESCE(rd.tipo_item,'P') = 'P'
        ) AS cant_remitida
      FROM public.factura_venta_cab f
      JOIN public.clientes c ON c.id_cliente = f.id_cliente
      $where
      ORDER BY f.fecha_emision DESC, f.id_factura DESC
    )
    SELECT
      id_factura,
      numero_documento,
      fecha_emision,
      total,
      cliente,
      ruc_ci,
      cant_facturada,
      cant_remitida,
      GREATEST(cant_facturada - COALESCE(cant_remitida,0), 0)::numeric(14,3) AS cant_pendiente
    FROM t
    ".($pendiente ? "WHERE GREATEST(cant_facturada - COALESCE(cant_remitida,0), 0) > 0" : "")."
    ORDER BY fecha_emision DESC, id_factura DESC
    LIMIT $page_size OFFSET $off
  ";

  $r = pg_query_params($conn, $sql, $p);
  if (!$r) throw new Exception('Error de búsqueda');

  $out = [];
  while($row = pg_fetch_assoc($r)){
    $fact = (float)$row['cant_facturada'];
    $rem  = (float)$row['cant_remitida'];
    $pend = (float)$row['cant_pendiente'];
    $out[] = [
      'id_factura'       => (int)$row['id_factura'],
      'numero_documento' => $row['numero_documento'],
      'fecha_emision'    => $row['fecha_emision'],
      'cliente'          => $row['cliente'],
      'ruc_ci'           => $row['ruc_ci'],
      'total'            => (float)$row['total'],
      'cant_facturada'   => $fact,
      'cant_remitida'    => $rem,
      'cant_pendiente'   => $pend
    ];
  }

  echo json_encode(['success'=>true,'data'=>$out]);

}catch(Throwable $e){
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
