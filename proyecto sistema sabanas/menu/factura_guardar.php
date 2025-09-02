<?php
// factura_guardar.php
header('Content-Type: application/json; charset=utf-8');

try {
  // Conexión (igual que en factura_ver.php)
  require __DIR__ . '/../conexion/configv2.php'; // Debe definir $conn = pg_connect(...)
  if (!$conn) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "No hay conexión a la BD"]);
    exit;
  }

  /* =======================
     ENTRADA (POST)
     ======================= */
  $id_prov = (int)($_POST['id_proveedor'] ?? 0);
  $fecha   = $_POST['fecha_emision'] ?? date('Y-m-d');
  $nro     = trim((string)($_POST['numero_documento'] ?? ''));
  $obs     = $_POST['observacion'] ?? null;

  // Detalle factura (desde OC)
  $ids   = $_POST['id_oc_det'] ?? [];
  $cants = $_POST['cantidad'] ?? [];
  $precs = $_POST['precio_unitario'] ?? [];
  $ivas  = $_POST['tipo_iva'] ?? []; // opcional por línea

  // Parámetros de condición/cuotas (nuevo)
  $condicion      = strtoupper(trim((string)($_POST['condicion'] ?? 'CREDITO'))); // CONTADO | CREDITO
  $cuotas_n       = max(1, (int)($_POST['cuotas'] ?? 1));
  $dias_plazo     = (int)($_POST['dias_plazo'] ?? 30);   // 1ra cuota: +30 días por defecto
  $intervalo_dias = (int)($_POST['intervalo_dias'] ?? 30); // intervalo entre cuotas
  $fechas_cuota   = $_POST['fechas_cuota'] ?? null; // opcional: array de fechas exactas 'YYYY-MM-DD'

  // Si es CONTADO, por defecto dejamos 1 cuota y vencimiento = fecha (o +dias_plazo si lo enviás)
  if ($condicion === 'CONTADO') {
    $cuotas_n = 1;
    // si querés forzar vencimiento hoy: $dias_plazo = 0;
  }

  // Validaciones básicas
  if ($id_prov <= 0 || $nro === '') {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Parámetros requeridos: proveedor y número de documento"]);
    exit;
  }
  if (!is_array($ids) || !count($ids)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Sin líneas de detalle"]);
    exit;
  }
  if (count($ids) !== count($cants) || count($ids) !== count($precs)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Datos de líneas incompletos"]);
    exit;
  }

  /* =======================
     TRANSACCIÓN
     ======================= */
  pg_query($conn, "BEGIN");

  // Cabecera de factura
  // Cabecera de factura (con metadatos de cuotas)
  $insCab = pg_query_params(
    $conn,
    "INSERT INTO public.factura_compra_cab
     (id_proveedor, fecha_emision, numero_documento, observacion, estado,
      condicion, cuotas, dias_plazo, intervalo_dias)
   VALUES ($1,$2,$3,$4,'Registrada',$5,$6,$7,$8)
   RETURNING id_factura",
    [$id_prov, $fecha, $nro, $obs, $condicion, $cuotas_n, $dias_plazo, $intervalo_dias]
  );

  if (!$insCab) {
    pg_query($conn, "ROLLBACK");
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "No se pudo crear la factura"]);
    exit;
  }
  $id_factura = (int)pg_fetch_result($insCab, 0, 0);

  // Acumuladores (precio_unitario SIN IVA)
  $total_base = 0.0;
  $grav10 = 0.0;
  $iva10 = 0.0;
  $grav5  = 0.0;
  $iva5  = 0.0;
  $exentas = 0.0;

  $oc_tocadas = []; // id_oc => numero_pedido

  /* =======================
     DETALLES
     ======================= */
  for ($i = 0; $i < count($ids); $i++) {
    $id_oc_det = (int)$ids[$i];
    $cant      = (int)$cants[$i];
    $prec      = (float)$precs[$i];
    $tiva_line = is_array($ivas) ? ($ivas[$i] ?? null) : null;

    if ($id_oc_det <= 0 || $cant <= 0 || $prec < 0) {
      pg_query($conn, "ROLLBACK");
      http_response_code(400);
      echo json_encode(["ok" => false, "error" => "Línea inválida"]);
      exit;
    }

    // Traer línea OC + producto (valida proveedor, pendiente y obtiene tipo_iva de producto)
    $q = pg_query_params(
      $conn,
      "SELECT ocd.id_oc_det, ocd.id_oc, ocd.id_producto, ocd.cantidad AS oc_cantidad,
              occ.id_proveedor, occ.numero_pedido,
              pr.tipo_iva AS prod_tipo_iva
         FROM public.orden_compra_det ocd
         JOIN public.orden_compra_cab occ ON occ.id_oc = ocd.id_oc
         JOIN public.producto pr         ON pr.id_producto = ocd.id_producto
        WHERE ocd.id_oc_det=$1
        LIMIT 1",
      [$id_oc_det]
    );
    if (!$q || pg_num_rows($q) === 0) {
      pg_query($conn, "ROLLBACK");
      http_response_code(400);
      echo json_encode(["ok" => false, "error" => "OC det no encontrada"]);
      exit;
    }
    $L = pg_fetch_assoc($q);

    // Validar proveedor
    if ((int)$L['id_proveedor'] !== $id_prov) {
      pg_query($conn, "ROLLBACK");
      http_response_code(400);
      echo json_encode(["ok" => false, "error" => "La línea no corresponde al proveedor indicado"]);
      exit;
    }

    // Pendiente por recibir (excluye anuladas)
    $rpend = pg_query_params(
      $conn,
      "SELECT (ocd.cantidad - COALESCE((
         SELECT SUM(fcd.cantidad)
           FROM public.factura_compra_det fcd
           JOIN public.factura_compra_cab fcc ON fcc.id_factura=fcd.id_factura AND fcc.estado<>'Anulada'
          WHERE fcd.id_oc_det = ocd.id_oc_det
       ),0))::int AS pendiente
         FROM public.orden_compra_det ocd
        WHERE ocd.id_oc_det=$1",
      [$id_oc_det]
    );
    $pend = (int)pg_fetch_result($rpend, 0, 0);
    if ($cant > $pend) {
      pg_query($conn, "ROLLBACK");
      http_response_code(400);
      echo json_encode(["ok" => false, "error" => "Cantidad supera pendiente por recibir (pendiente=$pend)"]);
      exit;
    }

    // Insert detalle factura
    $insDet = pg_query_params(
      $conn,
      "INSERT INTO public.factura_compra_det
         (id_factura, id_oc_det, id_producto, cantidad, precio_unitario, tipo_iva)
       VALUES ($1,$2,$3,$4,$5,$6)",
      [$id_factura, $id_oc_det, (int)$L['id_producto'], $cant, $prec, $tiva_line]
    );
    if (!$insDet) {
      pg_query($conn, "ROLLBACK");
      http_response_code(500);
      echo json_encode(["ok" => false, "error" => "No se pudo insertar detalle"]);
      exit;
    }

    // Movimiento de stock (entrada)
    $mov = pg_query_params(
      $conn,
      "INSERT INTO public.movimiento_stock (id_producto, tipo_movimiento, cantidad)
       VALUES ($1,'entrada',$2)",
      [(int)$L['id_producto'], $cant]
    );
    if (!$mov) {
      pg_query($conn, "ROLLBACK");
      http_response_code(500);
      echo json_encode(["ok" => false, "error" => "No se pudo registrar movimiento de stock"]);
      exit;
    }

    // Base (SIN IVA)
    $baseLinea   = $cant * $prec;
    $total_base += $baseLinea;

    // IVA efectivo: prioriza el de la línea; si no, del producto
    $tiva_eff = $tiva_line ?: $L['prod_tipo_iva'];
    $tiva_eff = strtoupper(trim((string)$tiva_eff));

    if (strpos($tiva_eff, '10') === 0) {
      $grav10 += $baseLinea;
      $iva10  += $baseLinea * 0.10;
    } elseif (strpos($tiva_eff, '5') === 0) {
      $grav5  += $baseLinea;
      $iva5   += $baseLinea * 0.05;
    } else {
      $exentas += $baseLinea;
    }

    // Marcar OC tocada (para actualizar estados luego)
    $oc_tocadas[(int)$L['id_oc']] = (int)$L['numero_pedido'];
  }

  /* =======================
     TOTALES Y CABECERA
     ======================= */
  $total_iva     = $iva10 + $iva5;
  $total_factura = $total_base + $total_iva;

  $uCab = pg_query_params(
    $conn,
    "UPDATE public.factura_compra_cab
        SET total_factura = $2
      WHERE id_factura = $1",
    [$id_factura, $total_factura]
  );
  if (!$uCab) {
    pg_query($conn, "ROLLBACK");
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "No se pudo actualizar total de la factura"]);
    exit;
  }

  /* =======================
     CUENTAS POR PAGAR (CAB)
     ======================= */
  $estadoCxP = ($total_factura > 0) ? 'Pendiente' : 'Cancelada';
  $fecha_venc_ref = (new DateTime($fecha))->modify("+{$dias_plazo} day")->format('Y-m-d'); // solo referencial en cab

  $insCxp = pg_query_params(
    $conn,
    "INSERT INTO public.cuenta_pagar
      (id_factura, id_proveedor, fecha_emision, fecha_venc, moneda, total_cxp, saldo_actual, estado, observacion)
     VALUES
      ($1,$2,$3,$4,'PYG',$5,$5,$6,$7)
     RETURNING id_cxp",
    [$id_factura, $id_prov, $fecha, $fecha_venc_ref, $total_factura, $estadoCxP, $obs]
  );
  if (!$insCxp) {
    pg_query($conn, "ROLLBACK");
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "No se pudo crear la Cuenta por Pagar"]);
    exit;
  }
  $id_cxp = (int)pg_fetch_result($insCxp, 0, 0);

  /* =======================
     CUENTAS POR PAGAR (DET - CUOTAS)
     ======================= */
  // Reparto por N cuotas (ajustando última por redondeo)
  $monto_base_cuota = $cuotas_n > 0 ? round($total_factura / $cuotas_n, 2) : $total_factura;
  $acum = 0.00;
  $fv = null;

  for ($n = 1; $n <= $cuotas_n; $n++) {
    // Monto
    if ($n < $cuotas_n) {
      $monto_cuota = $monto_base_cuota;
      $acum += $monto_cuota;
    } else {
      $monto_cuota = round($total_factura - $acum, 2);
    }

    // Fecha de vencimiento
    if (is_array($fechas_cuota) && isset($fechas_cuota[$n - 1]) && !empty($fechas_cuota[$n - 1])) {
      $fv = (new DateTime($fechas_cuota[$n - 1]))->format('Y-m-d');
    } else {
      if ($n === 1) {
        $fv = (new DateTime($fecha))->modify("+{$dias_plazo} day")->format('Y-m-d');
      } else {
        $fv = (new DateTime($fv))->modify("+{$intervalo_dias} day")->format('Y-m-d');
      }
    }

    $insCuota = pg_query_params(
      $conn,
      "INSERT INTO public.cuenta_det_x_pagar
        (id_cxp, nro_cuota, fecha_venc, monto_cuota, saldo_cuota, estado, observacion)
       VALUES
        ($1,$2,$3,$4,$4,'Pendiente',$5)",
      [$id_cxp, $n, $fv, $monto_cuota, $obs]
    );
    if (!$insCuota) {
      pg_query($conn, "ROLLBACK");
      http_response_code(500);
      echo json_encode(["ok" => false, "error" => "No se pudo crear la(s) cuota(s) de CxP"]);
      exit;
    }
  }

  /* =======================
     LIBRO DE COMPRAS
     ======================= */
  $rProv = pg_query_params($conn, "SELECT ruc FROM public.proveedores WHERE id_proveedor=$1", [$id_prov]);
  $rucProv = ($rProv && pg_num_rows($rProv) > 0) ? pg_fetch_result($rProv, 0, 0) : null;

  $insLibro = pg_query_params(
    $conn,
    "INSERT INTO public.libro_compras
      (id_factura, fecha, id_proveedor, ruc, numero_documento,
       gravada_10, iva_10, gravada_5, iva_5, exentas, total, estado)
     VALUES
      ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,'Vigente')",
    [
      $id_factura,
      $fecha,
      $id_prov,
      $rucProv,
      $nro,
      $grav10,
      $iva10,
      $grav5,
      $iva5,
      $exentas,
      $total_factura
    ]
  );
  if (!$insLibro) {
    pg_query($conn, "ROLLBACK");
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "No se pudo registrar en libro de compras"]);
    exit;
  }

  /* =======================
     ESTADOS OC / PEDIDO
     ======================= */
  foreach ($oc_tocadas as $id_oc => $num_pedido) {
    // Estado OC
    $r = pg_query_params(
      $conn,
      "WITH x AS (
         SELECT ocd.id_oc_det, ocd.cantidad AS oc_qty,
                COALESCE((
                  SELECT SUM(fcd.cantidad)
                    FROM public.factura_compra_det fcd
                    JOIN public.factura_compra_cab fcc
                      ON fcc.id_factura=fcd.id_factura AND fcc.estado<>'Anulada'
                   WHERE fcd.id_oc_det=ocd.id_oc_det
                ),0) AS fact_qty
           FROM public.orden_compra_det ocd
          WHERE ocd.id_oc=$1
       )
       SELECT
         SUM(CASE WHEN fact_qty>=oc_qty THEN 1 ELSE 0 END) AS completas,
         COUNT(*) AS total,
         SUM(CASE WHEN fact_qty>0 AND fact_qty<oc_qty THEN 1 ELSE 0 END) AS parciales
       FROM x",
      [$id_oc]
    );
    list($completas, $totalRows, $parciales) = array_map('intval', pg_fetch_row($r));
    $estadoOC = 'Emitida';
    if ($totalRows > 0 && $completas === $totalRows)      $estadoOC = 'Totalmente Recibida';
    elseif ($parciales > 0 || $completas > 0)             $estadoOC = 'Parcialmente Recibida';

    pg_query_params(
      $conn,
      "UPDATE public.orden_compra_cab
          SET estado=$2
        WHERE id_oc=$1
          AND estado<>'Anulada'",
      [$id_oc, $estadoOC]
    );

    // Estado Pedido Interno
    $r2 = pg_query_params(
      $conn,
      "WITH x AS (
         SELECT d.id_producto,
                d.cantidad AS pedida,
                COALESCE((
                  SELECT SUM(fcd.cantidad)
                    FROM public.orden_compra_det ocd
                    JOIN public.orden_compra_cab occ ON occ.id_oc=ocd.id_oc
                    JOIN public.factura_compra_det fcd ON fcd.id_oc_det=ocd.id_oc_det
                    JOIN public.factura_compra_cab fcc ON fcc.id_factura=fcd.id_factura AND fcc.estado<>'Anulada'
                   WHERE occ.numero_pedido=d.numero_pedido
                     AND ocd.id_producto=d.id_producto
                ),0) AS recibida
           FROM public.detalle_pedido_interno d
          WHERE d.numero_pedido=$1
       )
       SELECT
         SUM(CASE WHEN recibida>=pedida THEN 1 ELSE 0 END) AS completas,
         COUNT(*) AS total,
         SUM(CASE WHEN recibida>0 AND recibida<pedida THEN 1 ELSE 0 END) AS parciales
       FROM x",
      [$num_pedido]
    );
    list($c2, $t2, $p2) = array_map('intval', pg_fetch_row($r2));
    $estadoPed = 'Abierto';
    if ($t2 > 0 && $c2 === $t2)      $estadoPed = 'Totalmente Entregado';
    elseif ($p2 > 0 || $c2 > 0)        $estadoPed = 'Parcialmente Entregado';

    pg_query_params(
      $conn,
      "UPDATE public.cabecera_pedido_interno
          SET estado = CASE WHEN estado='Anulado' THEN estado ELSE $2 END
        WHERE numero_pedido=$1",
      [$num_pedido, $estadoPed]
    );
  }

  /* =======================
     COMMIT Y RESPUESTA
     ======================= */
  pg_query($conn, "COMMIT");

  echo json_encode([
    "ok"             => true,
    "id_factura"     => $id_factura,
    "id_cxp"         => $id_cxp,
    "condicion"      => $condicion,
    "cuotas"         => $cuotas_n,
    "total_base"     => round($total_base, 2),
    "iva10"          => round($iva10, 2),
    "iva5"           => round($iva5, 2),
    "exentas"        => round($exentas, 2),
    "total_factura"  => round($total_factura, 2)
  ], JSON_UNESCAPED_UNICODE);
  exit;
} catch (Throwable $e) {
  if (isset($conn)) {
    @pg_query($conn, "ROLLBACK");
  }
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}
