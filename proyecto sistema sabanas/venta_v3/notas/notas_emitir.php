<?php
/**
 * notas_emitir.php
 * Emite Nota de Crédito (NC) o Nota de Débito (ND) con numeración por CAJA usando reservar_numero().
 * Cabecera + Detalle + CxC + (Stock en NC si devolución) + Libro Ventas (libro_ventas_new).
 */

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

/* ---------------- Helpers ---------------- */
function is_iso_date($s){ return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$s); }
function stock_tipo_col($conn){
  // Detecta si la columna de tipo en movimiento_stock es 'tipo_movimiento' o 'tipo'
  $q1 = pg_query_params($conn,"SELECT 1 FROM information_schema.columns
    WHERE table_schema='public' AND table_name='movimiento_stock' AND column_name='tipo_movimiento' LIMIT 1",[]);
  if ($q1 && pg_num_rows($q1)>0) return 'tipo_movimiento';
  $q2 = pg_query_params($conn,"SELECT 1 FROM information_schema.columns
    WHERE table_schema='public' AND table_name='movimiento_stock' AND column_name='tipo' LIMIT 1",[]);
  if ($q2 && pg_num_rows($q2)>0) return 'tipo';
  throw new Exception("No se encontró la columna de tipo ('tipo_movimiento' o 'tipo') en movimiento_stock");
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit;
  }

  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;

  $clase   = strtoupper(trim($in['clase'] ?? ''));         // 'NC' | 'ND'
  $fecha   = (isset($in['fecha_emision']) && is_iso_date($in['fecha_emision'])) ? $in['fecha_emision'] : date('Y-m-d');
  $id_cli  = isset($in['id_cliente']) ? (int)$in['id_cliente'] : 0;
  $id_fact = isset($in['id_factura']) && $in['id_factura']!=='' ? (int)$in['id_factura'] : null; // opcional
  $mot_id  = isset($in['id_motivo']) ? (int)$in['id_motivo'] : null;
  $mot_txt = isset($in['motivo_texto']) ? trim($in['motivo_texto']) : null;
  $detalle = (isset($in['detalle']) && is_array($in['detalle'])) ? $in['detalle'] : [];
  // Solo NC puede afectar stock (devolución)
  $afecta_stock = ($clase === 'NC') ? (bool)($in['afecta_stock'] ?? false) : false;

  // Totales (si no vienen, los calculamos)
  $tot_bruto = (float)($in['total_bruto'] ?? 0);
  $tot_desc  = (float)($in['total_descuento'] ?? 0);
  $tot_iva   = (float)($in['total_iva'] ?? 0);
  $tot_neto  = (float)($in['total_neto'] ?? 0);

  if (!in_array($clase, ['NC','ND'], true)) { throw new Exception('clase inválida (NC|ND)'); }
  if ($id_cli <= 0) { throw new Exception('id_cliente requerido'); }
  if (count($detalle) === 0) { throw new Exception('detalle vacío'); }

  // Sesión / Caja
  $id_usuario     = (int)($_SESSION['id_usuario'] ?? 0);
  $id_caja_sesion = (int)($_SESSION['id_caja_sesion'] ?? 0);
  $id_caja        = (int)($_SESSION['id_caja'] ?? 0);
  if ($id_usuario <= 0) { throw new Exception('Sesión de usuario no válida.'); }
  if ($id_caja_sesion <= 0 || $id_caja <= 0) { throw new Exception('No hay caja abierta en esta sesión.'); }

  // Caja abierta
  $rCaja = pg_query_params($conn,
    "SELECT 1 FROM public.caja_sesion WHERE id_caja_sesion=$1 AND id_caja=$2 AND estado='Abierta' LIMIT 1",
    [$id_caja_sesion, $id_caja]
  );
  if (!$rCaja || pg_num_rows($rCaja)===0) { throw new Exception('La caja de la sesión no está Abierta.'); }

  // Si no vinieron totales, calcular
  if ($tot_neto <= 0) {
    foreach ($detalle as $it) {
      $cant = (float)($it['cantidad'] ?? 0);
      $pu   = (float)($it['precio_unitario'] ?? 0);
      $desc = (float)($it['descuento'] ?? 0);
      $sub  = (float)($it['subtotal_neto'] ?? ($cant * $pu - $desc));
      if ($sub < 0) $sub = 0;

      $tot_neto  += $sub;
      $tot_bruto += (float)($it['subtotal_bruto'] ?? max(0, $cant * $pu));
      $tot_desc  += $desc;
      $tot_iva   += (float)($it['iva_monto'] ?? 0);
    }
    $tot_bruto = round($tot_bruto, 2);
    $tot_desc  = round($tot_desc, 2);
    $tot_iva   = round($tot_iva, 2);
    $tot_neto  = round($tot_neto, 2);
  }
  if ($tot_neto <= 0) { throw new Exception('total_neto inválido'); }

  // Si está vinculada a una factura, traigo condicion_venta para el libro (si no, default Contado)
  $cond_venta = 'Contado';
  if ($id_fact) {
    $rCV = pg_query_params($conn, "SELECT condicion_venta FROM public.factura_venta_cab WHERE id_factura=$1", [$id_fact]);
    if ($rCV && pg_num_rows($rCV)>0) {
      $cond_venta = pg_fetch_result($rCV, 0, 0) ?: 'Contado';
    }
  }

  // ---------------- TX ----------------
  pg_query($conn, 'BEGIN');

  // A) Reservar número (usa SP simple por PPP y caja)
  $tamBloque = isset($in['tamano_bloque']) ? (int)$in['tamano_bloque'] : 1; // ignorado por el SP simple; ok enviar 1
  $rRes = pg_query_params($conn, "SELECT * FROM public.reservar_numero($1,$2,$3)", [$clase, $id_caja, $tamBloque]);
  if (!$rRes || pg_num_rows($rRes)===0) { throw new Exception('No se pudo reservar numeración'); }
  $res = pg_fetch_assoc($rRes);
  $id_timbrado   = (int)$res['id_timbrado'];
  $id_asignacion = isset($res['id_asignacion']) ? (int)$res['id_asignacion'] : null; // compat
  $nro_corr      = (int)$res['nro_corr'];
  $numero_doc    = $res['numero_formateado']; // EEE-PPP-0000001

  // PPP
  $rTim = pg_query_params($conn, "SELECT establecimiento, punto_expedicion FROM public.timbrado WHERE id_timbrado=$1", [$id_timbrado]);
  if (!$rTim || pg_num_rows($rTim)===0) { throw new Exception('Timbrado no encontrado'); }
  $tim = pg_fetch_assoc($rTim);
  $ppp = $tim['establecimiento'].'-'.$tim['punto_expedicion'];

  // Auditoría
  $tz = new DateTimeZone('America/Asuncion');
  $ahora_ts = (new DateTime('now', $tz))->format('Y-m-d H:i:sP');
  $creado_por = $_SESSION['usuario'] ?? 'system';

  if ($clase === 'NC') {
    /* ---- NC: CAB ---- */
    $rCab = pg_query_params($conn,"
      INSERT INTO public.nc_venta_cab(
        id_factura, id_cliente, id_timbrado, id_asignacion, nro_corr,
        ppp, numero_documento, fecha_emision,
        id_motivo, motivo_texto,
        total_bruto, total_descuento, total_iva, total_neto,
        afecta_stock, estado, creado_por, creado_en, id_caja, id_caja_sesion
      ) VALUES (
        $1,$2,$3,$4,$5,
        $6,$7,$8,
        $9,$10,
        $11,$12,$13,$14,
        $15,'Emitida',$16,$17,$18,$19
      ) RETURNING id_nc
    ", [
      $id_fact, $id_cli, $id_timbrado, $id_asignacion, $nro_corr,
      $ppp, $numero_doc, $fecha,
      $mot_id, $mot_txt,
      $tot_bruto, $tot_desc, $tot_iva, $tot_neto,
      $afecta_stock ? 't' : 'f', $creado_por, $ahora_ts, $id_caja, $id_caja_sesion
    ]);
    if (!$rCab) throw new Exception('No se pudo crear NC (cabecera)');
    $id_nota = (int)pg_fetch_result($rCab, 0, 0);

    /* ---- NC: DET ---- */
    $sqlDet = "
      INSERT INTO public.nc_venta_det(
        id_nc, id_producto, descripcion, cantidad, precio_unitario,
        descuento, tipo_iva, iva_monto, subtotal_bruto, subtotal_neto
      ) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)
    ";
    foreach($detalle as $i=>$it){
      $ok = pg_query_params($conn,$sqlDet,[
        $id_nota,
        isset($it['id_producto']) ? (int)$it['id_producto'] : null,
        $it['descripcion'] ?? null,
        (float)($it['cantidad'] ?? 0),
        (float)($it['precio_unitario'] ?? 0),
        (float)($it['descuento'] ?? 0),
        $it['tipo_iva'] ?? '10',      // '10'|'5'|'EX'
        (float)($it['iva_monto'] ?? 0),
        (float)($it['subtotal_bruto'] ?? 0),
        (float)($it['subtotal_neto'] ?? 0),
      ]);
      if (!$ok) throw new Exception('No se pudo insertar detalle NC en fila '.($i+1));
    }

    /* ---- NC: CxC (aplica crédito) ---- */
    $credito = $tot_neto;
    if ($credito > 0.009) {
      if ($id_fact){
        $rs = pg_query_params($conn, "
          SELECT id_cxc, saldo_actual
          FROM public.cuenta_cobrar
          WHERE id_cliente=$1 AND id_factura=$2 AND estado='Abierta'
          ORDER BY fecha_vencimiento NULLS FIRST, id_cxc
        ", [$id_cli, $id_fact]);

        while ($credito > 0.009 && $rs && ($cc = pg_fetch_assoc($rs))){
          $id_cxc = (int)$cc['id_cxc']; $saldo = (float)$cc['saldo_actual'];
          if ($saldo <= 0) { continue; }
          $aplicar = min($credito, $saldo);

          pg_query_params($conn,"
            INSERT INTO public.movimiento_cxc (id_cxc, fecha, tipo, monto, referencia, observacion)
            VALUES ($1,$2::date,'nota_credito',$3,$4,'NC aplicada a factura')
          ", [$id_cxc, $fecha, $aplicar, $numero_doc]);

          pg_query_params($conn,"
            UPDATE public.cuenta_cobrar
               SET saldo_actual = saldo_actual - $1,
                   estado = CASE WHEN saldo_actual - $1 <= 0.009 THEN 'Cerrada' ELSE estado END,
                   actualizado_en = NOW()
             WHERE id_cxc = $2
          ", [$aplicar, $id_cxc]);

          $credito -= $aplicar;
        }
      }
      if ($credito > 0.009) {
        // crédito a favor (CxC negativa)
        $r = pg_query_params($conn,"
          INSERT INTO public.cuenta_cobrar
            (id_cliente, id_factura, fecha_origen, monto_origen, saldo_actual, estado)
          VALUES ($1,NULL,$2::date,$3 * -1, $3 * -1,'Abierta')
          RETURNING id_cxc
        ", [$id_cli, $fecha, $credito]);
        $id_cxc_cred = (int)pg_fetch_result($r, 0, 0);

        pg_query_params($conn,"
          INSERT INTO public.movimiento_cxc (id_cxc, fecha, tipo, monto, referencia, observacion)
          VALUES ($1,$2::date,'nota_credito',$3,$4,'NC saldo a favor')
        ", [$id_cxc_cred, $fecha, $credito, $numero_doc]);
      }
    }

    /* ---- NC: Stock devolución (entrada) ---- */
    if ($afecta_stock){
      $tipoCol = stock_tipo_col($conn);
      $okStock = pg_query_params($conn,"
        INSERT INTO public.movimiento_stock (fecha, id_producto, {$tipoCol}, cantidad, observacion)
        SELECT $1::timestamp, d.id_producto, 'entrada',
               d.cantidad::numeric(14,3), 'NC '||$2
        FROM public.nc_venta_det d
        JOIN public.producto p ON p.id_producto = d.id_producto
        WHERE d.id_nc = $3 AND d.id_producto IS NOT NULL
      ", [$fecha.' 00:00:00', $numero_doc, $id_nota]);
      if ($okStock === false) throw new Exception('No se pudo registrar movimiento de stock (NC)');
    }

    /* ---- NC: Libro Ventas (libro_ventas_new) → negativos ---- */
    $rSum = pg_query_params($conn, "
      WITH base AS (
        SELECT
          CASE WHEN d.tipo_iva IN ('10','10%') THEN d.subtotal_neto ELSE 0 END AS g10,
          CASE WHEN d.tipo_iva IN ('10','10%') THEN d.iva_monto     ELSE 0 END AS i10,
          CASE WHEN d.tipo_iva IN ('5','5%')   THEN d.subtotal_neto ELSE 0 END AS g5,
          CASE WHEN d.tipo_iva IN ('5','5%')   THEN d.iva_monto     ELSE 0 END AS i5,
          CASE WHEN d.tipo_iva ILIKE 'EX%'     THEN d.subtotal_neto ELSE 0 END AS ex
        FROM public.nc_venta_det d
        WHERE d.id_nc = $1
      )
      SELECT
        COALESCE(SUM(g10),0)::numeric(14,2),
        COALESCE(SUM(i10),0)::numeric(14,2),
        COALESCE(SUM(g5),0)::numeric(14,2),
        COALESCE(SUM(i5),0)::numeric(14,2),
        COALESCE(SUM(ex),0)::numeric(14,2)
      FROM base
    ", [$id_nota]);
    if (!$rSum) throw new Exception('No se pudo calcular totales de libro para NC');
    list($lv_g10,$lv_i10,$lv_g5,$lv_i5,$lv_ex) = array_map('floatval', pg_fetch_row($rSum));

    $rNumTim = pg_query_params($conn,"SELECT numero_timbrado FROM public.timbrado WHERE id_timbrado=$1",[$id_timbrado]);
    if (!$rNumTim || pg_num_rows($rNumTim)==0) throw new Exception('No se pudo obtener número de timbrado (NC)');
    $num_tim = pg_fetch_result($rNumTim,0,0);

    $okLibro = pg_query_params($conn,"
      INSERT INTO public.libro_ventas_new(
        fecha_emision, doc_tipo, id_doc, numero_documento, timbrado_numero,
        id_cliente, condicion_venta,
        grav10, iva10, grav5, iva5, exentas, total,
        estado_doc, id_timbrado, id_caja
      ) VALUES (
        $1::date,'NC',$2,$3,$4,
        $5,$6,
        -($7::numeric),-($8::numeric),-($9::numeric),-($10::numeric),-($11::numeric),-($12::numeric),
        'Emitida',$13,$14
      )
    ",[
      $fecha, $id_nota, $numero_doc, $num_tim,
      $id_cli, $cond_venta,
      $lv_g10,$lv_i10,$lv_g5,$lv_i5,$lv_ex,$tot_neto,
      $id_timbrado, $id_caja
    ]);
    if (!$okLibro) throw new Exception('No se pudo registrar NC en libro_ventas_new');

  } else {
    /* ---- ND: CAB ---- */
    $rCab = pg_query_params($conn,"
      INSERT INTO public.nd_venta_cab(
        id_factura, id_cliente, id_timbrado, id_asignacion, nro_corr,
        ppp, numero_documento, fecha_emision,
        id_motivo, motivo_texto,
        total_bruto, total_descuento, total_iva, total_neto,
        estado, creado_por, creado_en, id_caja, id_caja_sesion
      ) VALUES (
        $1,$2,$3,$4,$5,
        $6,$7,$8,
        $9,$10,
        $11,$12,$13,$14,
        'Emitida',$15,$16,$17,$18
      ) RETURNING id_nd
    ",[
      $id_fact, $id_cli, $id_timbrado, $id_asignacion, $nro_corr,
      $ppp, $numero_doc, $fecha,
      $mot_id, $mot_txt,
      $tot_bruto, $tot_desc, $tot_iva, $tot_neto,
      $creado_por, $ahora_ts, $id_caja, $id_caja_sesion
    ]);
    if (!$rCab) throw new Exception('No se pudo crear ND (cabecera)');
    $id_nota = (int)pg_fetch_result($rCab,0,0);

    /* ---- ND: DET ---- */
    $sqlDet = "
      INSERT INTO public.nd_venta_det(
        id_nd, id_producto, descripcion, cantidad, precio_unitario,
        descuento, tipo_iva, iva_monto, subtotal_bruto, subtotal_neto
      ) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)
    ";
    foreach($detalle as $i=>$it){
      $ok = pg_query_params($conn,$sqlDet,[
        $id_nota,
        isset($it['id_producto']) ? (int)$it['id_producto'] : null,
        $it['descripcion'] ?? null,
        (float)($it['cantidad'] ?? 0),
        (float)($it['precio_unitario'] ?? 0),
        (float)($it['descuento'] ?? 0),
        $it['tipo_iva'] ?? '10',
        (float)($it['iva_monto'] ?? 0),
        (float)($it['subtotal_bruto'] ?? 0),
        (float)($it['subtotal_neto'] ?? 0),
      ]);
      if (!$ok) throw new Exception('No se pudo insertar detalle ND en fila '.($i+1));
    }

    /* ---- ND: CxC (crea deuda/recargo) ---- */
    $vto_nd = $fecha; // si querés, sumar días
    $r = pg_query_params($conn,"
      INSERT INTO public.cuenta_cobrar
        (id_cliente, id_factura, fecha_origen, monto_origen, saldo_actual, fecha_vencimiento, estado)
      VALUES ($1,$2,$3::date,$4,$4,$5::date,'Abierta')
      RETURNING id_cxc
    ",[$id_cli, $id_fact, $fecha, $tot_neto, $vto_nd]);
    $id_cxc_nd = (int)pg_fetch_result($r,0,0);

    pg_query_params($conn,"
      INSERT INTO public.movimiento_cxc (id_cxc, fecha, tipo, monto, referencia, observacion)
      VALUES ($1,$2::date,'recargo',$3,$4,'ND recargo/ajuste')
    ",[$id_cxc_nd, $fecha, $tot_neto, $numero_doc]);

    /* ---- ND: Libro Ventas (libro_ventas_new) → positivos ---- */
    $rSum = pg_query_params($conn, "
      WITH base AS (
        SELECT
          CASE WHEN d.tipo_iva IN ('10','10%') THEN d.subtotal_neto ELSE 0 END AS g10,
          CASE WHEN d.tipo_iva IN ('10','10%') THEN d.iva_monto     ELSE 0 END AS i10,
          CASE WHEN d.tipo_iva IN ('5','5%')   THEN d.subtotal_neto ELSE 0 END AS g5,
          CASE WHEN d.tipo_iva IN ('5','5%')   THEN d.iva_monto     ELSE 0 END AS i5,
          CASE WHEN d.tipo_iva ILIKE 'EX%'     THEN d.subtotal_neto ELSE 0 END AS ex
        FROM public.nd_venta_det d
        WHERE d.id_nd = $1
      )
      SELECT
        COALESCE(SUM(g10),0)::numeric(14,2),
        COALESCE(SUM(i10),0)::numeric(14,2),
        COALESCE(SUM(g5),0)::numeric(14,2),
        COALESCE(SUM(i5),0)::numeric(14,2),
        COALESCE(SUM(ex),0)::numeric(14,2)
      FROM base
    ", [$id_nota]);
    if (!$rSum) throw new Exception('No se pudo calcular totales de libro para ND');
    list($lv_g10,$lv_i10,$lv_g5,$lv_i5,$lv_ex) = array_map('floatval', pg_fetch_row($rSum));

    $rNumTim = pg_query_params($conn,"SELECT numero_timbrado FROM public.timbrado WHERE id_timbrado=$1",[$id_timbrado]);
    if (!$rNumTim || pg_num_rows($rNumTim)==0) throw new Exception('No se pudo obtener número de timbrado (ND)');
    $num_tim = pg_fetch_result($rNumTim,0,0);

    $okLibro = pg_query_params($conn,"
      INSERT INTO public.libro_ventas_new(
        fecha_emision, doc_tipo, id_doc, numero_documento, timbrado_numero,
        id_cliente, condicion_venta,
        grav10, iva10, grav5, iva5, exentas, total,
        estado_doc, id_timbrado, id_caja
      ) VALUES (
        $1::date,'ND',$2,$3,$4,
        $5,$6,
        ($7::numeric),($8::numeric),($9::numeric),($10::numeric),($11::numeric),($12::numeric),
        'Emitida',$13,$14
      )
    ",[
      $fecha, $id_nota, $numero_doc, $num_tim,
      $id_cli, $cond_venta,
      $lv_g10,$lv_i10,$lv_g5,$lv_i5,$lv_ex,$tot_neto,
      $id_timbrado, $id_caja
    ]);
    if (!$okLibro) throw new Exception('No se pudo registrar ND en libro_ventas_new');
  }

  pg_query($conn,'COMMIT');

  echo json_encode([
    'success'=>true,
    'clase'=>$clase,
    'numero_documento'=>$numero_doc,
    'ppp'=>$ppp,
    'id_timbrado'=>$id_timbrado,
    'id_asignacion'=>$id_asignacion,
    'nro_corr'=>$nro_corr,
    'id_nota'=>$id_nota,
    'fecha_emision'=>$fecha,
    'totales'=>[
      'bruto'=>$tot_bruto,
      'descuento'=>$tot_desc,
      'iva'=>$tot_iva,
      'neto'=>$tot_neto
    ]
  ]);

} catch (Throwable $e) {
  pg_query($conn,'ROLLBACK');
  $msg = $e->getMessage();
  if (
    str_contains($msg,'ux_nc_num_doc') || str_contains($msg,'ux_nd_num_doc') ||
    str_contains($msg,'ux_nc_num_corr') || str_contains($msg,'ux_nd_num_corr') ||
    str_contains($msg,'nc_venta_cab_unq') || str_contains($msg,'nd_venta_cab_unq')
  ){
    $msg = 'El número del comprobante ya fue utilizado. Reintente.';
  }
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$msg]);
}
