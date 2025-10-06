<?php
// facturar_pedido.php — Emite la factura SIN cobro (Contado/Crédito), con numeración por sub-rangos de CAJA
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

function is_iso_date($s)
{
  return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
  }

  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;

  $id_pedido  = isset($in['id_pedido']) ? (int)$in['id_pedido'] : 0;
  $condicion  = isset($in['condicion_venta']) ? trim($in['condicion_venta']) : '';
  $vto        = isset($in['fecha_vencimiento']) ? trim($in['fecha_vencimiento']) : null;
  $obs        = isset($in['observacion']) ? trim($in['observacion']) : null;
  $fecha_emision = (isset($in['fecha_emision']) && is_iso_date($in['fecha_emision']))
    ? $in['fecha_emision'] : date('Y-m-d');

  // Plan de cuotas (opcional en Crédito)

  $plan = (isset($in['plan_cuotas']) && is_array($in['plan_cuotas'])) ? $in['plan_cuotas'] : [];

  $ignorarReservaInput = !empty($in['ignorar_reserva']);   // <-- agregá esto


  // Contado diferido (opcional)
  $contado_diferido = !empty($in['contado_diferido']) ? (bool)$in['contado_diferido'] : false;
  $monto_cobrado = isset($in['monto_cobrado']) ? (float)$in['monto_cobrado'] : 0.0;

  if ($id_pedido <= 0) {
    throw new Exception('id_pedido inválido');
  }
  if (!in_array($condicion, ['Contado', 'Credito'], true)) {
    throw new Exception('condicion_venta inválida');
  }

  if ($condicion === 'Credito') {
    if (count($plan) > 0) {
      foreach ($plan as $i => $c) {
        if (empty($c['vencimiento']) || !is_iso_date($c['vencimiento'])) {
          throw new Exception("Plan de cuotas: vencimiento inválido en cuota " . ($i + 1));
        }
        if (!isset($c['nro']) || !isset($c['total'])) {
          throw new Exception("Plan de cuotas: datos incompletos (nro/total) en cuota " . ($i + 1));
        }
        if ((float)$c['total'] <= 0) {
          throw new Exception("Plan de cuotas: total de cuota inválido en cuota " . ($i + 1));
        }
      }
      $vto = null;
    } else {
      if (empty($vto) || !is_iso_date($vto)) {
        throw new Exception('fecha_vencimiento requerida (YYYY-MM-DD) para Crédito');
      }
      if ($vto < $fecha_emision) {
        throw new Exception('La fecha de vencimiento no puede ser anterior a la emisión');
      }
    }
  }

  // === Sesión caja / usuario ===
  $id_usuario     = (int)($_SESSION['id_usuario'] ?? 0);
  $id_caja_sesion = (int)($_SESSION['id_caja_sesion'] ?? 0);
  $id_caja        = (int)($_SESSION['id_caja'] ?? 0);
  if ($id_usuario <= 0) {
    throw new Exception('Sesión de usuario no válida.');
  }
  if ($id_caja_sesion <= 0 || $id_caja <= 0) {
    throw new Exception('No hay caja abierta en esta sesión.');
  }

  $rCaja = pg_query_params(
    $conn,
    "SELECT 1 FROM public.caja_sesion WHERE id_caja_sesion=$1 AND id_caja=$2 AND estado='Abierta' LIMIT 1",
    [$id_caja_sesion, $id_caja]
  );
  if (!$rCaja || pg_num_rows($rCaja) === 0) {
    throw new Exception('La caja de la sesión no está Abierta.');
  }

  // ===== TX =====
  pg_query($conn, 'BEGIN');

  // 1) Pedido + Cliente (lock)
  $sqlPed = "
    SELECT p.id_pedido, p.estado, p.observacion, p.id_cliente,
           c.nombre, c.apellido, c.ruc_ci
    FROM public.pedido_cab p
    JOIN public.clientes c ON c.id_cliente = p.id_cliente
    WHERE p.id_pedido = $1
    FOR UPDATE
  ";
  $rPed = pg_query_params($conn, $sqlPed, [$id_pedido]);
  if (!$rPed || pg_num_rows($rPed) === 0) {
    throw new Exception('Pedido no encontrado');
  }
    $ped = pg_fetch_assoc($rPed);
  if (in_array($ped['estado'], ['Anulado', 'Facturado'], true)) {
    throw new Exception('El pedido no está disponible para facturar (Anulado o ya Facturado)');
  }
  $id_cliente = (int)$ped['id_cliente'];

  $obsPedido = $ped['observacion'] ?? '';
  if (!$obs && $obsPedido) {            // si no vino obs nueva, reutilizá la del pedido
    $obs = $obsPedido;
  }

  $ignorarReserva = $ignorarReservaInput;
  if (!$ignorarReserva && stripos($obsPedido, 'generado desde ot') !== false) {
    $ignorarReserva = true;             // pedidos “Generado desde OT #...”
  }


  // 2) Totales desde pedido_det (NORMALIZADOS: base e IVA correctos SIN duplicar)
  $sqlTot = "
WITH lineas AS (
  SELECT
    CASE
      WHEN UPPER(d.tipo_iva) IN ('IVA10','10%','10','IVA 10','10.0') THEN '10'
      WHEN UPPER(d.tipo_iva) IN ('IVA5','5%','5','IVA 5','5.0')     THEN '5'
      ELSE 'EX'
    END AS tipo_iva_norm,
    (d.cantidad * d.precio_unitario - COALESCE(d.descuento,0))::numeric(14,2) AS imp_total
  FROM public.pedido_det d
  WHERE d.id_pedido = $1
),
descomp AS (
  SELECT
    tipo_iva_norm,
    CASE
      WHEN tipo_iva_norm = '10' THEN round(imp_total / 1.10, 2)
      WHEN tipo_iva_norm = '5'  THEN round(imp_total / 1.05, 2)
      ELSE imp_total
    END AS base,
    CASE
      WHEN tipo_iva_norm = '10' THEN round(imp_total - imp_total / 1.10, 2)
      WHEN tipo_iva_norm = '5'  THEN round(imp_total - imp_total / 1.05, 2)
      ELSE 0
    END AS iva,
    imp_total
  FROM lineas
)
SELECT
  COALESCE(SUM(CASE WHEN tipo_iva_norm='10' THEN base ELSE 0 END),0)::numeric(14,2) AS total_grav10,
  COALESCE(SUM(CASE WHEN tipo_iva_norm='10' THEN iva  ELSE 0 END),0)::numeric(14,2) AS total_iva10,
  COALESCE(SUM(CASE WHEN tipo_iva_norm='5'  THEN base ELSE 0 END),0)::numeric(14,2) AS total_grav5,
  COALESCE(SUM(CASE WHEN tipo_iva_norm='5'  THEN iva  ELSE 0 END),0)::numeric(14,2) AS total_iva5,
  COALESCE(SUM(CASE WHEN tipo_iva_norm='EX' THEN imp_total ELSE 0 END),0)::numeric(14,2) AS total_exentas,
  COALESCE(SUM(imp_total),0)::numeric(14,2) AS total_factura
FROM descomp
";


  $rTot = pg_query_params($conn, $sqlTot, [$id_pedido]);
  if (!$rTot) {
    throw new Exception('No se pudo calcular totales');
  }
  $tot = pg_fetch_assoc($rTot);

  $grav10  = (float)$tot['total_grav10'];
  $iva10   = (float)$tot['total_iva10'];
  $grav5   = (float)$tot['total_grav5'];
  $iva5    = (float)$tot['total_iva5'];
  $exentas = (float)$tot['total_exentas'];
  $total   = (float)$tot['total_factura'];

  if ($total <= 0) {
    throw new Exception('Total de factura inválido (ver detalle del pedido)');
  }

  $total_bruto      = round($grav10 + $grav5 + $exentas, 2); // bases SIN IVA
  $total_iva        = round($iva10 + $iva5, 2);
  $total_descuento  = 0.0;                                   // si algún día tenés descuentos por línea, sumalos aquí
  $total_neto       = round($total, 2);                       // total visible (bases + IVA)

  // 3) RESERVA DE NÚMERO (JIT por CAJA)
  $tamBloque = 500; // configurable
  $rRes = pg_query_params(
    $conn,
    "SELECT * FROM public.reservar_numero($1,$2,$3)", // ('Factura', id_caja, tam_bloque)
    ['Factura', $id_caja, $tamBloque]
  );
  if (!$rRes || pg_num_rows($rRes) === 0) {
    throw new Exception('No se pudo reservar numeración (timbrado vencido o pool agotado)');
  }
  $rowNum = pg_fetch_assoc($rRes);
  $id_timbrado   = (int)$rowNum['id_timbrado'];
  $id_asignacion = (int)$rowNum['id_asignacion']; // compat
  $nro_corr      = (int)$rowNum['nro_corr'];
  $numero_doc    = $rowNum['numero_formateado'];  // EEE-PPP-NNNNNNN

  // número de timbrado para Libro Ventas
  $rNumTim = pg_query_params($conn, "SELECT numero_timbrado FROM public.timbrado WHERE id_timbrado=$1 LIMIT 1", [$id_timbrado]);
  if (!$rNumTim || pg_num_rows($rNumTim) === 0) {
    throw new Exception('No se pudo obtener número de timbrado');
  }
  $num_tim = pg_fetch_result($rNumTim, 0, 0);

  // 4) Validar reservas activas suficientes
  $sqlCheckReserva = "
  WITH req AS (
    SELECT d.id_producto,
           SUM(d.cantidad)::numeric(14,2) AS qty_requerida
      FROM public.pedido_det d
      JOIN public.producto p ON p.id_producto = d.id_producto
     WHERE d.id_pedido = $1
       AND COALESCE(p.tipo_item,'P') NOT IN ('S','D')   -- excluir servicios y descuentos
     GROUP BY d.id_producto
  ),
  res AS (
    SELECT r.id_producto,
           COALESCE(SUM(r.cantidad),0)::numeric(14,2) AS qty_reservada
      FROM public.reserva_stock r
      JOIN public.producto p ON p.id_producto = r.id_producto
     WHERE r.id_pedido = $1
       AND lower(r.estado) = 'activa'
       AND COALESCE(p.tipo_item,'P') NOT IN ('S','D')   -- misma condición
     GROUP BY r.id_producto
  )
  SELECT p.id_producto, p.nombre,
         req.qty_requerida,
         COALESCE(res.qty_reservada,0) AS qty_reservada
    FROM req
    LEFT JOIN res ON res.id_producto = req.id_producto
    JOIN public.producto p ON p.id_producto = req.id_producto
   WHERE COALESCE(res.qty_reservada,0) < req.qty_requerida
";

    if (!$ignorarReserva) {
    $rCheck = pg_query_params($conn, $sqlCheckReserva, [$id_pedido]);
    if ($rCheck === false) {
      throw new Exception('No se pudo validar reservas');
    }
    if (pg_num_rows($rCheck) > 0) {
      $faltantes = [];
      while ($row = pg_fetch_assoc($rCheck)) {
        $faltantes[] = $row['nombre'] .
                       " (req: " . $row['qty_requerida'] .
                       ", res: " . $row['qty_reservada'] . ")";
      }
      throw new Exception('Reserva insuficiente para: ' . implode(', ', $faltantes));
    }
  }


  // 5) Cabecera de FACTURA — AHORA incluyendo total_iva y total_factura
  $sqlFacCab = "
    INSERT INTO public.factura_venta_cab(
      fecha_emision, id_cliente, condicion_venta,
      numero_documento, timbrado_numero,
      total_grav10, total_iva10, total_grav5, total_iva5, total_exentas,
      total_bruto, total_descuento, total_iva, total_factura, total_neto,
      estado, id_pedido, observacion, id_timbrado,
      id_caja_sesion, id_caja, nro_corr, id_asignacion
    ) VALUES (
      $1, $2, $3,
      $4, $5,
      $6, $7, $8, $9, $10,
      $11, $12, $13, $14, $15,
      'Emitida', $16, $17, $18,
      $19, $20, $21, $22
    ) RETURNING id_factura
  ";
  $paramsCab = [
    $fecha_emision,
    $id_cliente,
    $condicion,
    $numero_doc,
    $num_tim,
    $grav10,
    $iva10,
    $grav5,
    $iva5,
    $exentas,
    $total_bruto,
    $total_descuento,
    $total_iva,
    $total,
    $total_neto,
    $id_pedido,
    $obs,
    $id_timbrado,
    $id_caja_sesion,
    $id_caja,
    $nro_corr,
    $id_asignacion
  ];
  $rCab = pg_query_params($conn, $sqlFacCab, $paramsCab);
  if (!$rCab) {
    throw new Exception('No se pudo crear la factura (cabecera)');
  }
  $id_factura = (int)pg_fetch_result($rCab, 0, 0);

  // 6) Detalle
  $sqlDet = "
  INSERT INTO public.factura_venta_det(
    id_factura, id_producto, tipo_item, descripcion, unidad,
    cantidad, precio_unitario, tipo_iva, iva_monto, subtotal_neto
  )
  SELECT
    $1,
    d.id_producto,
    COALESCE(p.tipo_item,'P'),
    p.nombre,
    'UNI',
    d.cantidad,
    d.precio_unitario,
    CASE
      WHEN UPPER(d.tipo_iva) IN ('IVA10','10%','10','IVA 10','10.0') THEN 'IVA10'
      WHEN UPPER(d.tipo_iva) IN ('IVA5','5%','5','IVA 5','5.0')     THEN 'IVA5'
      ELSE 'EXE'
    END AS tipo_iva_norm,
    CASE
      WHEN UPPER(d.tipo_iva) IN ('IVA10','10%','10','IVA 10','10.0')
        THEN round((d.cantidad*d.precio_unitario - COALESCE(d.descuento,0)) * 0.10 / 1.10, 2)
      WHEN UPPER(d.tipo_iva) IN ('IVA5','5%','5','IVA 5','5.0')
        THEN round((d.cantidad*d.precio_unitario - COALESCE(d.descuento,0)) * 0.05 / 1.05, 2)
      ELSE 0
    END AS iva_monto_calc,
    round(d.cantidad*d.precio_unitario - COALESCE(d.descuento,0), 2) AS subtotal_con_iva
  FROM public.pedido_det d
  JOIN public.producto p ON p.id_producto = d.id_producto
  WHERE d.id_pedido = $2
";

  $okDet = pg_query_params($conn, $sqlDet, [$id_factura, $id_pedido]);
  if (!$okDet) {
    throw new Exception('No se pudo insertar el detalle de la factura');
  }

  // 7) Movimiento de stock (solo productos) — 'salida'
  $sqlStock = "
    INSERT INTO public.movimiento_stock(fecha, id_producto, tipo_movimiento, cantidad, observacion)
    SELECT $1::timestamp, d.id_producto, 'salida'::varchar(10),
           d.cantidad, 'Fact. '||$2
    FROM public.pedido_det d
    JOIN public.producto p ON p.id_producto = d.id_producto
    WHERE d.id_pedido = $3 AND COALESCE(p.tipo_item,'P') = 'P'
  ";
  $okStock = pg_query_params($conn, $sqlStock, [$fecha_emision . ' 00:00:00', $numero_doc, $id_pedido]);
  if ($okStock === false) {
    throw new Exception('No se pudo registrar movimiento de stock');
  }

  // 8) Consumir reservas del pedido
  $sqlConsumirReserva = "
    UPDATE public.reserva_stock
    SET estado = 'consumida', actualizado_en = NOW()
    WHERE id_pedido = $1 AND lower(estado) = 'activa'
  ";
  $okCons = pg_query_params($conn, $sqlConsumirReserva, [$id_pedido]);
  if ($okCons === false) {
    throw new Exception('No se pudo consumir las reservas del pedido');
  }

  // 9) Libro Ventas (estado 'Emitida')
  $okLibro = pg_query_params(
    $conn,
    "INSERT INTO public.libro_ventas_new(
       fecha_emision, doc_tipo, id_doc, numero_documento, timbrado_numero,
       id_cliente, condicion_venta,
       grav10, iva10, grav5, iva5, exentas, total,
       estado_doc, id_timbrado, id_caja
     ) VALUES (
       $1::date, 'FACT', $2, $3, $4,
       $5, $6,
       $7, $8, $9, $10, $11, $12,
       'Emitida', $13, $14
     )",
    [
      $fecha_emision,          // $1
      $id_factura,             // $2
      $numero_doc,             // $3
      $num_tim,                // $4
      $id_cliente,             // $5
      $condicion,              // $6
      $grav10,
      $iva10,
      $grav5,
      $iva5,
      $exentas,
      $total,  // $7..$12 (total visible)
      $id_timbrado,            // $13
      $id_caja                 // $14
    ]
  );
  if (!$okLibro) {
    throw new Exception('No se pudo registrar en Libro Ventas');
  }

  // 10) Marcar pedido como facturado
  $okPed = pg_query_params(
    $conn,
    "UPDATE public.pedido_cab SET estado='Facturado', actualizado_en=NOW() WHERE id_pedido=$1",
    [$id_pedido]
  );
  if (!$okPed) {
    throw new Exception('No se pudo actualizar estado del pedido');
  }

  // 11) Crédito: generar CxC (simple o por cuotas)
  if ($condicion === 'Credito') {
    if (count($plan) > 0) {
      $sumPlan = 0.0;
      foreach ($plan as $c) {
        $sumPlan += (float)$c['total'];
      }
      if (abs($sumPlan - $total) > 0.05) {
        throw new Exception('El total del plan de cuotas no coincide con el total de la factura');
      }
      foreach ($plan as $c) {
        $nro   = (int)$c['nro'];
        $venc  = (string)$c['vencimiento'];
        $cap   = isset($c['capital']) ? (float)$c['capital'] : 0.0;
        $int   = isset($c['interes']) ? (float)$c['interes'] : 0.0;
        $totCu = (float)$c['total'];

        $okCxc = pg_query_params(
          $conn,
          "INSERT INTO public.cuenta_cobrar
             (id_cliente, id_factura, fecha_origen,
              monto_origen, saldo_actual, fecha_vencimiento,
              estado, nro_cuota, capital, interes, cant_cuotas)
           VALUES ($1,$2,$3::date,$4,$4,$5::date,'Abierta',$6,$7,$8,$9)",
          [$id_cliente, $id_factura, $fecha_emision, $totCu, $venc, $nro, $cap, $int, count($plan)]
        );
        if (!$okCxc) {
          throw new Exception('No se pudo crear la cuota de CxC');
        }
      }
    } else {
      $okCxc = pg_query_params(
        $conn,
        "INSERT INTO public.cuenta_cobrar
           (id_cliente, id_factura, fecha_origen, monto_origen, saldo_actual, fecha_vencimiento, estado, nro_cuota, cant_cuotas)
         VALUES ($1,$2,$3::date,$4,$4,$5::date,'Abierta',1,1)",
        [$id_cliente, $id_factura, $fecha_emision, $total, $vto]
      );
      if (!$okCxc) {
        throw new Exception('No se pudo crear la cuenta por cobrar');
      }
    }
  }

  // 11-bis) Contado sin cobro: CxC simple si no se cobró aún
  if ($condicion === 'Contado') {
    if ($monto_cobrado < 0) {
      $monto_cobrado = 0.0;
    }
    if ($monto_cobrado > $total) {
      $monto_cobrado = $total;
    }

    $vto_contado = (isset($in['fecha_vencimiento']) && is_iso_date($in['fecha_vencimiento']))
      ? $in['fecha_vencimiento']
      : $fecha_emision;

    if ($contado_diferido || ($monto_cobrado < $total)) {
      $rExiste = pg_query_params(
        $conn,
        "SELECT 1
           FROM public.cuenta_cobrar
          WHERE id_factura = $1
            AND COALESCE(nro_cuota,0)=0
            AND COALESCE(cant_cuotas,0)=0
          LIMIT 1",
        [$id_factura]
      );

      if ($rExiste && pg_num_rows($rExiste) === 0) {
        $saldo_inicial = round($total - $monto_cobrado, 2);

        $okCxcContado = pg_query_params(
          $conn,
          "INSERT INTO public.cuenta_cobrar
             (id_cliente, id_factura, fecha_origen,
              monto_origen, saldo_actual, fecha_vencimiento,
              estado, nro_cuota, capital, interes, cant_cuotas)
           VALUES ($1,$2,$3::date,$4,$5,$6::date,'Abierta',0,$7,$8,0)",
          [
            $id_cliente,
            $id_factura,
            $fecha_emision,
            $total,                 // monto_origen = total de la factura
            $saldo_inicial,         // saldo_actual = total - cobrado
            $vto_contado,
            0.0,                    // capital
            0.0                     // interes
          ]
        );

        if (!$okCxcContado) {
          throw new Exception('No se pudo crear la CxC de contado diferido');
        }
      }
    }
  }

  pg_query($conn, 'COMMIT');

  echo json_encode([
    'success' => true,
    'id_factura' => $id_factura,
    'numero_documento' => $numero_doc,
    'condicion_venta' => $condicion,
    'fecha_emision' => $fecha_emision,
    'total' => $total
  ]);
} catch (Throwable $e) {
  pg_query($conn, 'ROLLBACK');
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
