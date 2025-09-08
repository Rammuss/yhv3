<?php
// factura_items_para_nota.php
header('Content-Type: application/json; charset=utf-8');

try {
  require __DIR__ . '/../conexion/configv2.php'; // $conn = pg_connect(...)
  if (!$conn) {
    http_response_code(500);
    echo json_encode(["ok"=>false,"error"=>"Sin conexión a la BD"]); exit;
  }

  if (!isset($_GET['id_factura']) || !ctype_digit($_GET['id_factura'])) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"Parámetro id_factura inválido"]); exit;
  }
  $id_factura = (int)$_GET['id_factura'];

  // Verificar factura
  $qf = pg_query_params($conn,
    "SELECT f.id_factura, f.estado, f.id_proveedor
       FROM public.factura_compra_cab f
      WHERE f.id_factura = $1
      LIMIT 1",
    [$id_factura]
  );
  if (!$qf || pg_num_rows($qf) === 0) {
    http_response_code(404);
    echo json_encode(["ok"=>false,"error"=>"Factura no encontrada"]); exit;
  }
  $F = pg_fetch_assoc($qf);
  if (strcasecmp($F['estado'],'Anulada') === 0) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"La factura está Anulada"]); exit;
  }

  $sqlItemsFactura = "
    WITH fac AS (
      SELECT
        d.id_producto,
        pr.nombre AS producto,
        SUM(d.cantidad)::numeric AS cant_fact,
        CASE
          WHEN SUM(d.cantidad) > 0
          THEN ROUND( SUM(d.cantidad * d.precio_unitario) / SUM(d.cantidad), 2 )
          ELSE 0
        END AS pu_base,
        -- usar agregación también para el campo del producto
        COALESCE(
          NULLIF(MAX(NULLIF(d.tipo_iva,'')), ''),
          MAX(pr.tipo_iva)
        ) AS tipo_iva
      FROM public.factura_compra_det d
      JOIN public.producto pr ON pr.id_producto = d.id_producto
      WHERE d.id_factura = $1
      GROUP BY d.id_producto, pr.nombre
    ),
    devs AS (
      SELECT
        ncd.id_producto,
        SUM(ncd.cantidad)::numeric AS cant_devuelta
      FROM public.notas_compra_cab nc
      JOIN public.notas_compra_det ncd ON ncd.id_nota = nc.id_nota
      WHERE nc.id_factura_ref = $1
        AND nc.tipo = 'NC'
        AND nc.estado <> 'Anulada'
        AND ncd.id_producto IS NOT NULL
      GROUP BY ncd.id_producto
    )
    SELECT
      fac.id_producto,
      fac.producto,
      fac.cant_fact::numeric(14,2)                         AS cantidad_facturada,
      COALESCE(devs.cant_devuelta, 0)::numeric(14,2)       AS cantidad_devuelta_previa,
      GREATEST(fac.cant_fact - COALESCE(devs.cant_devuelta,0), 0)::numeric(14,2) AS disponible,
      fac.pu_base::numeric(14,2)                           AS precio_unitario_base,
      CASE
        WHEN UPPER(TRIM(fac.tipo_iva)) LIKE '10%%' THEN '10%'
        WHEN UPPER(TRIM(fac.tipo_iva)) LIKE '5%%'  THEN '5%'
        ELSE 'Exento'
      END AS tipo_iva
    FROM fac
    LEFT JOIN devs ON devs.id_producto = fac.id_producto
    ORDER BY fac.producto ASC;
  ";

  $res = pg_query_params($conn, $sqlItemsFactura, [$id_factura]);
  if (!$res) {
    http_response_code(500);
    echo json_encode(["ok"=>false,"error"=>"Error consultando ítems de factura: ".pg_last_error($conn)]); exit;
  }

  $out = [];
  while ($row = pg_fetch_assoc($res)) {
    $out[] = [
      "id_producto"                => (int)$row["id_producto"],
      "producto"                   => $row["producto"],
      "cantidad_facturada"         => (float)$row["cantidad_facturada"],
      "cantidad_devuelta_previa"   => (float)$row["cantidad_devuelta_previa"],
      "disponible"                 => (float)$row["disponible"],
      "precio_unitario_base"       => (float)$row["precio_unitario_base"],
      "tipo_iva"                   => $row["tipo_iva"]
    ];
  }

  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>$e->getMessage()]);
}
