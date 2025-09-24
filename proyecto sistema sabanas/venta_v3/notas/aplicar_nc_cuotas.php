<?php
// aplicar_nc_cuotas.php — Aplica una NC a la factura (crédito) reduciendo cuotas abiertas, y si sobra crea crédito a favor (CxC negativa). No toca caja.

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit;
  }
  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;

  $id_nc     = isset($in['id_nc']) ? (int)$in['id_nc'] : 0;
  $id_fact   = isset($in['id_factura']) ? (int)$in['id_factura'] : 0;
  $monto_req = isset($in['monto']) ? (float)$in['monto'] : null; // opcional: aplicar parcialmente

  if ($id_nc<=0 || $id_fact<=0) throw new Exception('Parámetros inválidos (id_nc / id_factura)');

  // ===== Cargar NC (lock) =====
  $rNC = pg_query_params($conn,"
    SELECT n.id_nc, n.id_cliente, n.numero_documento, n.total_neto::numeric(14,2) AS total_neto, n.estado
    FROM public.nc_venta_cab n
    WHERE n.id_nc=$1
    FOR UPDATE
  ",[$id_nc]);
  if(!$rNC || pg_num_rows($rNC)===0) throw new Exception('NC no encontrada');
  $NC = pg_fetch_assoc($rNC);
  if ($NC['estado']!=='Emitida') throw new Exception('La NC no está disponible (estado: '.$NC['estado'].')');

  // ===== Cargar Factura (lock) =====
  $rF = pg_query_params($conn,"
    SELECT f.id_factura, f.id_cliente, f.condicion_venta, f.numero_documento
    FROM public.factura_venta_cab f
    WHERE f.id_factura=$1
    FOR UPDATE
  ",[$id_fact]);
  if(!$rF || pg_num_rows($rF)===0) throw new Exception('Factura no encontrada');
  $F = pg_fetch_assoc($rF);

  if ((int)$F['id_cliente'] !== (int)$NC['id_cliente']) {
    throw new Exception('NC y Factura pertenecen a clientes distintos');
  }
  if ($F['condicion_venta']!=='Credito') {
    throw new Exception('La factura no es de tipo Crédito');
  }

  // ===== Pendiente (cuotas abiertas) =====
  $rPend = pg_query_params($conn,"
    SELECT COALESCE(SUM(saldo_actual),0)::numeric(14,2)
    FROM public.cuenta_cobrar
    WHERE id_factura=$1 AND estado='Abierta'
  ",[$id_fact]);
  $pendiente = (float)pg_fetch_result($rPend,0,0);

  // Monto a aplicar (puede ser parcial)
  $montoNC_total = (float)$NC['total_neto'];
  $montoObjetivo = ($monto_req !== null && $monto_req > 0) ? min($monto_req, $montoNC_total) : $montoNC_total;

  pg_query($conn,'BEGIN');

  // ===== Aplicar a cuotas abiertas (FIFO por vencimiento) =====
  $rs = pg_query_params($conn,"
    SELECT id_cxc, saldo_actual::numeric(14,2) AS saldo_actual
    FROM public.cuenta_cobrar
    WHERE id_factura=$1 AND estado='Abierta'
    ORDER BY fecha_vencimiento NULLS FIRST, id_cxc
    FOR UPDATE
  ",[$id_fact]);

  $aplicado_cuotas = 0.0;
  $restante = $montoObjetivo;

  if ($rs) {
    while($restante > 0.009 && ($cc = pg_fetch_assoc($rs))){
      $id_cxc = (int)$cc['id_cxc'];
      $saldo  = (float)$cc['saldo_actual'];
      if ($saldo <= 0.009) continue;

      $aplicar = min($restante, $saldo);

      // movimiento_cxc (nota_credito)
      $okMv = pg_query_params($conn,"
        INSERT INTO public.movimiento_cxc (id_cxc, fecha, tipo, monto, referencia, observacion)
        VALUES ($1, CURRENT_DATE, 'nota_credito', $2::numeric(14,2), $3, $4)
      ",[$id_cxc, $aplicar, $NC['numero_documento'], 'NC aplicada a factura']);
      if(!$okMv) throw new Exception('No se pudo registrar movimiento CxC');

      // actualizar saldo/estado
      $okUp = pg_query_params($conn,"
        UPDATE public.cuenta_cobrar
           SET saldo_actual = (saldo_actual - $1::numeric(14,2)),
               estado = CASE WHEN (saldo_actual - $1::numeric(14,2)) <= 0.009 THEN 'Cerrada' ELSE estado END,
               actualizado_en = NOW()
         WHERE id_cxc=$2
      ",[$aplicar,$id_cxc]);
      if(!$okUp) throw new Exception('No se pudo actualizar cuota');

      $restante       -= $aplicar;
      $aplicado_cuotas+= $aplicar;
    }
  }

  // ===== Si sobra crédito o no había cuotas abiertas: crear CxC negativa (saldo a favor) =====
  // Nota: esto NO falla; deja crédito a favor para usar en futuras cobranzas o para egreso de caja.
  $id_cxc_credito = null;
  if ($restante > 0.009) {
    // CxC negativa (sin ligar a factura; si preferís, podés guardar id_factura=$id_fact para trazabilidad)
    $rCred = pg_query_params($conn,"
      INSERT INTO public.cuenta_cobrar
        (id_cliente, id_factura, fecha_origen, monto_origen, saldo_actual, estado)
      VALUES ($1, NULL, CURRENT_DATE, ($2 * -1)::numeric(14,2), ($2 * -1)::numeric(14,2), 'Abierta')
      RETURNING id_cxc
    ", [(int)$F['id_cliente'], $restante]);
    if(!$rCred) throw new Exception('No se pudo crear CxC negativa para crédito a favor');
    $id_cxc_credito = (int)pg_fetch_result($rCred, 0, 0);

    // Movimiento que explica el origen del crédito
    $okMvCred = pg_query_params($conn,"
      INSERT INTO public.movimiento_cxc (id_cxc, fecha, tipo, monto, referencia, observacion)
      VALUES ($1, CURRENT_DATE, 'nota_credito', $2::numeric(14,2), $3, $4)
    ",[$id_cxc_credito, $restante, $NC['numero_documento'], 'NC saldo a favor']);
    if(!$okMvCred) throw new Exception('No se pudo registrar movimiento del crédito a favor');
  }

  // ===== Estado de la NC =====
  // Si el usuario aplicó TODO el total_neto original (aunque una parte vaya a crédito a favor) => Aplicada
  // Si aplicó menos a propósito (monto_req < total_neto) => dejamos Emitida (o cambiar a 'Parcial' en tu modelo)
  $consumido_total = $montoObjetivo; // lo que decidimos usar hoy (aplicado a cuotas + crédito)
  if (abs($consumido_total - $montoNC_total) <= 0.01) {
    $okNC = pg_query_params($conn,"UPDATE public.nc_venta_cab SET estado='Aplicada' WHERE id_nc=$1",[$id_nc]);
    if(!$okNC) throw new Exception('No se pudo actualizar estado de la NC');
  } else {
    // queda 'Emitida' para usar el saldo restante de la NC en otra operación
    // si querés manejar 'Parcial', podés hacerlo aquí
  }

  pg_query($conn,'COMMIT');

  echo json_encode([
    'success'=>true,
    'id_nc'=>$id_nc,
    'id_factura'=>$id_fact,
    'aplicado_cuotas'=>$aplicado_cuotas,
    'creado_credito_a_favor'=> $restante > 0.009,
    'id_cxc_credito'=> $id_cxc_credito,
    'remanente_credito'=> $restante,
  ]);

}catch(Throwable $e){
  pg_query($conn,'ROLLBACK');
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
