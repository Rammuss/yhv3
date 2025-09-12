<?php
// buscar_facturas.php
session_start();
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try{
  $num = trim($_GET['num'] ?? '');
  $cli = trim($_GET['cli'] ?? '');

  $where = "WHERE f.condicion_venta = 'Contado'"; // ✅ Solo contado
  $params=[]; $i=1;

  if($num!==''){ 
    $where .= " AND f.numero_documento ILIKE $".$i; 
    $params[]='%'.$num.'%'; 
    $i++; 
  }
  if($cli!==''){
    $where .= " AND (c.ruc_ci ILIKE $".$i." OR (c.nombre||' '||c.apellido) ILIKE $".($i+1).")";
    $params[]='%'.$cli.'%'; 
    $params[]='%'.$cli.'%'; 
    $i+=2;
  }

  $sql = "
    WITH aplic AS (
      SELECT id_factura, SUM(monto_aplicado)::numeric(14,2) AS aplicado
      FROM public.recibo_cobranza_det_aplic
      GROUP BY id_factura
    )
    SELECT
      f.id_factura,
      f.numero_documento,
      (c.nombre||' '||c.apellido) AS cliente,
      f.total_neto::numeric(14,2) AS total,
      (f.total_neto - COALESCE(ap.aplicado,0))::numeric(14,2) AS pendiente
    FROM public.factura_venta_cab f
    JOIN public.clientes c ON c.id_cliente = f.id_cliente
    LEFT JOIN aplic ap ON ap.id_factura = f.id_factura
    $where
      AND (f.total_neto - COALESCE(ap.aplicado,0)) > 0 -- ✅ Solo con saldo pendiente
    ORDER BY f.fecha_emision DESC, f.id_factura DESC
    LIMIT 50
  ";

  $res = $params ? pg_query_params($conn,$sql,$params) : pg_query($conn,$sql);
  if(!$res) throw new Exception('Error consultando facturas');

  $arr=[];
  while($r=pg_fetch_assoc($res)){
    $arr[]=[
      'id_factura'=>(int)$r['id_factura'],
      'numero_documento'=>$r['numero_documento'],
      'cliente'=>$r['cliente'],
      'total'=>(float)$r['total'],
      'pendiente'=>max(0,(float)$r['pendiente']),
    ];
  }
  echo json_encode(['success'=>true,'facturas'=>$arr]);
}catch(Throwable $e){
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
