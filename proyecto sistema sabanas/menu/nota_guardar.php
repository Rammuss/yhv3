<?php
// nota_guardar.php
header('Content-Type: application/json; charset=utf-8');

try {
  require __DIR__ . '/../conexion/configv2.php'; // $conn = pg_connect(...)
  if (!$conn) {
    http_response_code(500);
    echo json_encode(["ok"=>false,"error"=>"Sin conexión a la BD"]); exit;
  }

  // ===== ENTRADA =====
  $tipo           = strtoupper(trim((string)($_POST['tipo'] ?? '')));
  $id_prov        = (int)($_POST['id_proveedor'] ?? 0);
  $id_factura_ref = (int)($_POST['id_factura_ref'] ?? 0);
  $fecha          = $_POST['fecha_emision'] ?? date('Y-m-d');
  $nro            = trim((string)($_POST['numero_documento'] ?? ''));
  $timbr          = isset($_POST['timbrado_numero']) ? trim((string)$_POST['timbrado_numero']) : null;
  if ($timbr === '') $timbr = null;
  $moneda         = $_POST['moneda'] ?? 'PYG';
  $obs            = $_POST['observacion'] ?? null;
  $id_sucursal    = isset($_POST['id_sucursal']) && $_POST['id_sucursal']!=='' ? (int)$_POST['id_sucursal'] : null;

  $ids_prod = $_POST['id_producto'] ?? [];
  $descs    = $_POST['descripcion'] ?? []; // puede venir vacío (no obligatorio en BD)
  $cants    = $_POST['cantidad'] ?? [];
  $precs    = $_POST['precio_unitario'] ?? [];
  $ivas     = $_POST['tipo_iva'] ?? [];

  // Val. básicas
  if (!in_array($tipo, ['NC','ND'], true)) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"Tipo inválido (NC o ND)"]); exit;
  }
  if ($id_prov<=0 || $id_factura_ref<=0 || $nro==='') {
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"Parámetros requeridos: proveedor, factura de referencia y número de documento"]); exit;
  }
  if (!is_array($ids_prod) || !count($ids_prod) || count($ids_prod)!==count($cants) || count($ids_prod)!==count($precs)) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"Detalle incompleto"]); exit;
  }
  if ($timbr!==null && !preg_match('/^\d+$/',$timbr)) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"El timbrado debe contener solo números"]); exit;
  }

  // Factura ref válida
  $qF = pg_query_params($conn,
    "SELECT id_factura, id_proveedor, estado
       FROM public.factura_compra_cab
      WHERE id_factura=$1
      LIMIT 1", [$id_factura_ref]);
  if (!$qF || pg_num_rows($qF)===0) {
    http_response_code(404);
    echo json_encode(["ok"=>false,"error"=>"Factura de referencia no encontrada"]); exit;
  }
  $F = pg_fetch_assoc($qF);
  if ((int)$F['id_proveedor'] !== $id_prov) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"La factura referenciada no corresponde al proveedor indicado"]); exit;
  }
  if (strcasecmp($F['estado'],'Anulada')===0) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"La factura referenciada está Anulada"]); exit;
  }

  // Sucursal activa si viene
  if ($id_sucursal!==null) {
    $qSuc = pg_query_params($conn,
      "SELECT 1 FROM public.sucursales WHERE id_sucursal=$1 AND estado='ACTIVO' LIMIT 1",[$id_sucursal]);
    if (!$qSuc || pg_num_rows($qSuc)===0) {
      http_response_code(400);
      echo json_encode(["ok"=>false,"error"=>"Sucursal inexistente o INACTIVA"]); exit;
    }
  }

  // ===== PRE-CÁLCULOS: facturado y devuelto previo (por producto) =====
  $qFac = pg_query_params($conn,
    "SELECT d.id_producto, SUM(d.cantidad)::numeric AS cant
       FROM public.factura_compra_det d
      WHERE d.id_factura=$1
      GROUP BY d.id_producto", [$id_factura_ref]);
  $facturado = [];
  if ($qFac) while($r=pg_fetch_assoc($qFac)) { $facturado[(int)$r['id_producto']] = (float)$r['cant']; }

  $qDev = pg_query_params($conn,
    "SELECT ncd.id_producto, SUM(ncd.cantidad)::numeric AS cant
       FROM public.notas_compra_cab nc
       JOIN public.notas_compra_det ncd ON ncd.id_nota = nc.id_nota
      WHERE nc.id_factura_ref=$1
        AND nc.tipo='NC' AND nc.estado<>'Anulada'
        AND ncd.id_producto IS NOT NULL
      GROUP BY ncd.id_producto", [$id_factura_ref]);
  $devPrev = [];
  if ($qDev) while($r=pg_fetch_assoc($qDev)) { $devPrev[(int)$r['id_producto']] = (float)$r['cant']; }

  // ===== TRANSACCIÓN =====
  pg_query($conn, "BEGIN");

  // Cabecera
  $insCab = pg_query_params($conn,
    "INSERT INTO public.notas_compra_cab
       (tipo, id_proveedor, fecha_emision, numero_documento, timbrado_numero,
        moneda, observacion, estado, id_factura_ref, id_sucursal, total_nota, created_at)
     VALUES ($1,$2,$3,$4,$5,$6,$7,'Registrada',$8,$9,0, now())
     RETURNING id_nota",
    [$tipo, $id_prov, $fecha, $nro, $timbr, $moneda, $obs, $id_factura_ref, $id_sucursal]
  );
  if (!$insCab) {
    pg_query($conn,"ROLLBACK");
    http_response_code(500);
    echo json_encode(["ok"=>false,"error"=>"No se pudo crear la nota"]); exit;
  }
  $id_nota = (int)pg_fetch_result($insCab,0,0);

  // Detalle + totales
  $grav10=0.0; $iva10=0.0; $grav5=0.0; $iva5=0.0; $exentas=0.0; $total_nota=0.0;
  $hay_linea_con_producto = false;

  for($i=0; $i<count($ids_prod); $i++){
    $idp_raw = $ids_prod[$i];
    $idp     = ($idp_raw==='' || $idp_raw===null) ? null : (int)$idp_raw;
    $cant    = isset($cants[$i]) ? (float)$cants[$i] : null;
    $prec    = isset($precs[$i]) ? (float)$precs[$i] : null;
    $tiva_in = is_array($ivas) ? ($ivas[$i] ?? null) : null;
    $tiva_in = $tiva_in ? strtoupper(trim((string)$tiva_in)) : null;

    if ($idp!==null) {
      $hay_linea_con_producto = true;

      if ($cant===null || $cant<=0 || $prec===null || $prec<0) {
        pg_query($conn,"ROLLBACK");
        http_response_code(400);
        echo json_encode(["ok"=>false,"error"=>"Cantidad/Precio inválidos en una línea con producto"]); exit;
      }

      $qP = pg_query_params($conn,
        "SELECT id_producto, COALESCE(NULLIF(tipo_iva,''),'Exento') AS tipo_iva
           FROM public.producto
          WHERE id_producto=$1
          LIMIT 1", [$idp]);
      if (!$qP || pg_num_rows($qP)===0) {
        pg_query($conn,"ROLLBACK");
        http_response_code(400);
        echo json_encode(["ok"=>false,"error"=>"Producto no encontrado (id=$idp)"]); exit;
      }
      $P = pg_fetch_assoc($qP);
      if (!$tiva_in) $tiva_in = $P['tipo_iva'];

      if ($tipo==='NC') {
        $fact = (float)($facturado[$idp] ?? 0);
        $dev  = (float)($devPrev[$idp] ?? 0);
        $disp = max(0, $fact - $dev);
        if ($cant > $disp) {
          pg_query($conn,"ROLLBACK");
          http_response_code(400);
          echo json_encode(["ok"=>false,"error"=>"Cantidad a devolver del producto $idp excede lo disponible ($disp)"]); exit;
        }
        $devPrev[$idp] = $dev + $cant;
      }

      $insDet = pg_query_params($conn,
        "INSERT INTO public.notas_compra_det
           (id_nota, id_producto, cantidad, precio_unitario, tipo_iva)
         VALUES ($1,$2,$3,$4,$5)",
        [$id_nota, $idp, $cant, $prec, $tiva_in]
      );
      if (!$insDet) {
        pg_query($conn,"ROLLBACK");
        http_response_code(500);
        echo json_encode(["ok"=>false,"error"=>"No se pudo insertar el detalle"]); exit;
      }

      $base = $cant * $prec;
    } else {
      if ($cant===null || $cant<=0 || $prec===null || $prec<0) {
        pg_query($conn,"ROLLBACK");
        http_response_code(400);
        echo json_encode(["ok"=>false,"error"=>"Cantidad/Precio inválidos en una línea de ajuste"]); exit;
      }
      if (!$tiva_in) $tiva_in = 'Exento';

      $insDet = pg_query_params($conn,
        "INSERT INTO public.notas_compra_det
           (id_nota, id_producto, cantidad, precio_unitario, tipo_iva)
         VALUES ($1,NULL,$2,$3,$4)",
        [$id_nota, $cant, $prec, $tiva_in]
      );
      if (!$insDet) {
        pg_query($conn,"ROLLBACK");
        http_response_code(500);
        echo json_encode(["ok"=>false,"error"=>"No se pudo insertar el detalle de ajuste"]); exit;
      }

      $base = $cant * $prec;
    }

    if (strpos($tiva_in,'10')===0) {
      $grav10 += $base;  $iva10 += $base * 0.10;
    } elseif (strpos($tiva_in,'5')===0) {
      $grav5  += $base;  $iva5  += $base * 0.05;
    } else {
      $exentas += $base;
    }
    $total_nota += $base;
  }

  $total_con_iva = $total_nota + $iva10 + $iva5;

  $uCab = pg_query_params($conn,
    "UPDATE public.notas_compra_cab
        SET total_nota=$2
      WHERE id_nota=$1",
    [$id_nota, $total_con_iva]
  );
  if (!$uCab) {
    pg_query($conn,"ROLLBACK");
    http_response_code(500);
    echo json_encode(["ok"=>false,"error"=>"No se pudo actualizar el total de la nota"]); exit;
  }

  // ===== STOCK (solo NC con producto) =====
  $stock_salidas_cant = 0;
  if ($tipo==='NC' && $hay_linea_con_producto) {
    $qL = pg_query_params($conn,
      "SELECT id_producto, cantidad
         FROM public.notas_compra_det
        WHERE id_nota=$1 AND id_producto IS NOT NULL",
      [$id_nota]
    );
    while($r=pg_fetch_assoc($qL)) {
      $insMov = pg_query_params($conn,
        "INSERT INTO public.movimiento_stock (id_producto, tipo_movimiento, cantidad)
         VALUES ($1,'salida',$2)",
        [(int)$r['id_producto'], (int)$r['cantidad']]
      );
      if (!$insMov) {
        pg_query($conn,"ROLLBACK");
        http_response_code(500);
        echo json_encode(["ok"=>false,"error"=>"No se pudo registrar movimiento de stock"]); exit;
      }
      $stock_salidas_cant += (int)$r['cantidad'];
    }
  }

  // ===== CxP =====
  $signo = ($tipo==='NC') ? -1 : 1;

  $qCxp = pg_query_params($conn,
    "SELECT id_cxp, saldo_actual, estado
       FROM public.cuenta_pagar
      WHERE id_factura=$1
      LIMIT 1",
    [$id_factura_ref]
  );
  if (!$qCxp || pg_num_rows($qCxp)===0) {
    pg_query($conn,"ROLLBACK");
    http_response_code(400);
    echo json_encode(["ok"=>false,"error"=>"No existe CxP para la factura referenciada"]); exit;
  }
  $CXP = pg_fetch_assoc($qCxp);
  $id_cxp = (int)$CXP['id_cxp'];
  $saldo_antes = (float)$CXP['saldo_actual'];

  $nuevo_saldo = $saldo_antes + ($signo * $total_con_iva);
  if ($nuevo_saldo < 0) $nuevo_saldo = 0.00;

  $nuevo_estado = 'Pendiente';
  if ($nuevo_saldo == 0.0)              $nuevo_estado = 'Cancelada';
  elseif ($nuevo_saldo < $saldo_antes)  $nuevo_estado = 'Parcial';
  else                                  $nuevo_estado = 'Pendiente';

  $uCxp = pg_query_params($conn,
    "UPDATE public.cuenta_pagar
        SET saldo_actual=$2, estado=$3
      WHERE id_cxp=$1",
    [$id_cxp, $nuevo_saldo, $nuevo_estado]
  );
  if (!$uCxp) {
    pg_query($conn,"ROLLBACK");
    http_response_code(500);
    echo json_encode(["ok"=>false,"error"=>"No se pudo actualizar la Cuenta por Pagar"]); exit;
  }

  $concepto = ($tipo === 'NC' ? 'Nota de Crédito ' : 'Nota de Débito ') . $nro;
  $insMov = pg_query_params(
    $conn,
    "INSERT INTO public.cuenta_pagar_mov
       (id_proveedor, fecha, ref_tipo, ref_id, id_cxp, concepto, signo, monto, moneda, id_nota)
     VALUES
       ($1,           $2,    $3,       $4,     $5,     $6,       $7,    $8,    $9,      $10)",
    [$id_prov, $fecha, $tipo, $id_nota, $id_cxp, $concepto, $signo, $total_con_iva, $moneda, $id_nota]
  );
  if (!$insMov) {
    pg_query($conn,"ROLLBACK");
    http_response_code(500);
    echo json_encode(["ok"=>false,"error"=>"No se pudo registrar el movimiento de CxP"]); exit;
  }

  // ===== Libro de Compras (mov por nota) =====
  $insLCM = pg_query_params($conn,
    "INSERT INTO public.libro_compras_mov
       (id_factura, id_nota, fecha,
        gravada_10, iva_10, gravada_5, iva_5, exentas, total,
        signo, estado, timbrado_numero)
     VALUES
       ($1, $2, $3,
        $4, $5, $6, $7, $8, $9,
        $10, 'Vigente', $11)",
    [
      $id_factura_ref, $id_nota, $fecha,
      $signo*$grav10, $signo*$iva10, $signo*$grav5, $signo*$iva5, $signo*$exentas,
      $signo*$total_con_iva,
      $signo, $timbr
    ]
  );
  if (!$insLCM) {
    pg_query($conn,"ROLLBACK");
    http_response_code(500);
    echo json_encode(["ok"=>false,"error"=>"No se pudo registrar el movimiento en libro de compras"]); exit;
  }

  // ===== COMMIT + RESPUESTA (resumen corto) =====
  pg_query($conn, "COMMIT");

  echo json_encode([
    "ok"             => true,
    "id_nota"        => $id_nota,
    "tipo"           => $tipo,
    "id_factura_ref" => $id_factura_ref,
    "total_nota"     => round($total_con_iva,2),
    "grav10"         => round($grav10,2),
    "iva10"          => round($iva10,2),
    "grav5"          => round($grav5,2),
    "iva5"           => round($iva5,2),
    "exentas"        => round($exentas,2),
    // --> RESUMEN PARA TOAST (corto y directo)
    "resumen" => [
      "stock" => [
        "movido"        => ($tipo==='NC' && $hay_linea_con_producto),
        "salidas_cant"  => ($tipo==='NC' && $hay_linea_con_producto) ? $stock_salidas_cant : 0
      ],
      "cxp" => [
        "id_cxp"        => $id_cxp,
        "saldo_antes"   => round($saldo_antes,2),
        "saldo_despues" => round($nuevo_saldo,2),
        "estado"        => $nuevo_estado
      ],
      "libro" => [
        "registrado"    => true,
        "signo"         => $signo, // -1 NC, +1 ND
        "total_mov"     => round($signo*$total_con_iva,2),
        "timbrado"      => $timbr
      ]
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if (isset($conn)) { @pg_query($conn, "ROLLBACK"); }
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>$e->getMessage()]);
}
