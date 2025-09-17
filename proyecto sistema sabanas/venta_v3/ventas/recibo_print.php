<?php
// recibo_print.php — Vista A4 lista para imprimir
session_start();
if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
require_once __DIR__.'/../../conexion/configv2.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function n($x,$d=0){ return number_format((float)$x,$d,',','.'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0){ http_response_code(400); die('ID de recibo inválido'); }

// CABECERA
$sqlCab = "
  SELECT r.id_recibo, r.fecha, r.total_recibo, r.estado, COALESCE(r.observacion,'') AS observacion,
         r.id_cliente,
         c.nombre, c.apellido, c.ruc_ci, COALESCE(c.direccion,'') AS direccion
  FROM public.recibo_cobranza_cab r
  JOIN public.clientes c ON c.id_cliente = r.id_cliente
  WHERE r.id_recibo = $1
  LIMIT 1
";
$rc = pg_query_params($conn, $sqlCab, [$id]);
if (!$rc || pg_num_rows($rc)===0){ http_response_code(404); die('Recibo no encontrado'); }
$R = pg_fetch_assoc($rc);
$cliente = trim(($R['nombre'] ?? '').' '.($R['apellido'] ?? ''));
$estado  = strtolower($R['estado'] ?? '');
$esAnulado = ($estado === 'anulado');

// MEDIOS DE PAGO (usa cuenta_bancaria)
$sqlPag = "
  SELECT
    p.medio_pago,
    COALESCE(p.referencia,'') AS referencia,
    COALESCE(p.importe_bruto,0)::numeric(14,2) AS importe,
    COALESCE(p.comision,0)::numeric(14,2)      AS comision,
    COALESCE(p.fecha_acredit, r.fecha)         AS fecha_acredit,
    CASE
      WHEN p.id_cuenta_bancaria IS NOT NULL THEN
        COALESCE(b.banco,'Banco')||' · '||COALESCE(b.numero_cuenta,'s/n')||
        ' ('||COALESCE(b.moneda,'')||' '||COALESCE(b.tipo,'')||')'
      WHEN LOWER(p.medio_pago)='efectivo' THEN 'Caja'
      ELSE '—'
    END AS cuenta_label
  FROM public.recibo_cobranza_det_pago p
  JOIN public.recibo_cobranza_cab r ON r.id_recibo = p.id_recibo
  LEFT JOIN public.cuenta_bancaria b ON b.id_cuenta_bancaria = p.id_cuenta_bancaria
  WHERE p.id_recibo = $1
  ORDER BY p.id_recibo, p.medio_pago
";
$rp = pg_query_params($conn, $sqlPag, [$id]);

// APLICACIONES A FACTURAS (resumen por documento)
$sqlApl = "
  SELECT a.id_factura,
         a.monto_aplicado::numeric(14,2) AS monto,
         COALESCE(f.numero_documento,'(s/n)') AS numero_documento
  FROM public.recibo_cobranza_det_aplic a
  LEFT JOIN public.factura_venta_cab f ON f.id_factura = a.id_factura
  WHERE a.id_recibo = $1
  ORDER BY a.id_factura
";
$ra = pg_query_params($conn, $sqlApl, [$id]);

// DETALLE DE CUOTAS COBRADAS (usa movimiento_cxc + cuenta_cobrar)
$sqlCuotas = "
  SELECT
    f.numero_documento,
    cxc.nro_cuota,
    COALESCE(cxc.cant_cuotas,1) AS cant_cuotas,
    cxc.fecha_vencimiento AS vencimiento,
    mc.monto::numeric(14,2) AS pagado
  FROM public.movimiento_cxc mc
  JOIN public.cuenta_cobrar cxc ON cxc.id_cxc = mc.id_cxc
  JOIN public.factura_venta_cab f ON f.id_factura = cxc.id_factura
  WHERE mc.tipo='Pago' AND mc.referencia = $1
  ORDER BY f.numero_documento, cxc.nro_cuota
";
$refRec = 'Recibo #'.$id;
$rcu = pg_query_params($conn, $sqlCuotas, [$refRec]);

// Fallback: si no hay aplicaciones, resumimos por doc a partir de cuotas
$aplFallback = [];
if ($ra && pg_num_rows($ra)===0 && $rcu && pg_num_rows($rcu)>0){
  while($row = pg_fetch_assoc($rcu)){
    $aplFallback[$row['numero_documento']] = ($aplFallback[$row['numero_documento']] ?? 0) + (float)$row['pagado'];
  }
  pg_free_result($rcu);
  $rcu = pg_query_params($conn, $sqlCuotas, [$refRec]);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recibo #<?= e($R['id_recibo']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  :root{ --text:#111827; --muted:#6b7280; --em:#2563eb; --danger:#b91c1c; }
  body{ margin:0; color:var(--text); font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto; background:#fff; }
  .sheet{ width:210mm; min-height:297mm; margin:0 auto; padding:16mm 14mm; box-sizing:border-box; position:relative; }
  .head{ display:flex; justify-content:space-between; align-items:flex-start; gap:16px; border-bottom:2px solid #eee; padding-bottom:10px; }
  .brand h2{ margin:0 0 6px; font-size:20px; }
  .brand small{ color:var(--muted); }
  .docbox{ text-align:right; }
  .docbox h1{ margin:0; font-size:22px; letter-spacing:.5px; }
  .docbox .num{ font-weight:600; color:var(--em); }
  .grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px; }
  .box{ border:1px solid #e5e7eb; border-radius:8px; padding:10px; }
  .box h3{ margin:0 0 8px; font-size:14px; }
  table{ width:100%; border-collapse:collapse; margin-top:12px; }
  th,td{ border-bottom:1px solid #eee; padding:8px; text-align:left; }
  th{ background:#f8fafc; font-weight:600; }
  .right{ text-align:right; }
  .muted{ color:var(--muted); }
  .badge{ display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; border:1px solid #e5e7eb; }
  .badge.anulado{ color:#b91c1c; border-color:#fecaca; background:#fef2f2; }
  .actions{ position:sticky; top:0; background:#fff; border-bottom:1px solid #eee; padding:8px 14px; display:flex; gap:8px; }
  .btn{ display:inline-block; padding:8px 12px; border-radius:8px; border:1px solid #e5e7eb; text-decoration:none; color:#111; }
  .btn.primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
  .no-print{ display:block; }
  .watermark{ position:absolute; inset:0; pointer-events:none; display:<?= $esAnulado ? 'block':'none' ?>; }
  .watermark::after{
    content:'ANULADO';
    position:absolute; top:40%; left:50%; transform:translate(-50%,-50%) rotate(-20deg);
    font-size:120px; color:rgba(185,28,28,.12); font-weight:800; letter-spacing:6px;
  }
  @media print{ .no-print{ display:none !important; } .sheet{ box-shadow:none; margin:0; padding:12mm; } @page{ size:A4; margin:10mm; } }
</style>
</head>
<body>
<div id="navbar-container" class="no-print"></div>

<div class="actions no-print">
  <a href="javascript:window.print()" class="btn primary">Imprimir</a>
  <a href="javascript:window.close()" class="btn">Cerrar</a>
  <?php if ($esAnulado): ?>
    <span class="badge anulado">Estado: ANULADO</span>
  <?php else: ?>
    <span class="badge">Estado: <?= e($R['estado']) ?></span>
  <?php endif; ?>
</div>

<div class="sheet">
  <div class="watermark"></div>

  <div class="head">
    <div class="brand">
      <h2>Tu Empresa</h2>
      <small class="muted">
        RUC: 80000000-1 · Tel: (021) 000-000 · Asunción, PY<br>
        Email: ventas@tuempresa.com
      </small>
    </div>
    <div class="docbox">
      <h1>RECIBO</h1>
      <div>N° <span class="num"><?= e($R['id_recibo']) ?></span></div>
      <div>Fecha: <strong><?= e($R['fecha']) ?></strong></div>
      <div>Total recibido: <strong><?= n($R['total_recibo'], 0) ?></strong></div>
    </div>
  </div>

  <div class="grid">
    <div class="box">
      <h3>Cliente</h3>
      <div><strong><?= e($cliente) ?></strong></div>
      <div>RUC/CI: <?= e($R['ruc_ci']) ?></div>
      <?php if (!empty($R['direccion'])): ?>
        <div class="muted">Dirección: <?= e($R['direccion']) ?></div>
      <?php endif; ?>
    </div>
    <div class="box">
      <h3>Observación</h3>
      <div class="muted"><?= nl2br(e($R['observacion'])) ?></div>
    </div>
  </div>

  <!-- Medios de pago -->
  <h3 style="margin-top:14px;margin-bottom:6px">Medios de pago</h3>
  <table>
    <thead>
      <tr>
        <th>Medio</th>
        <th>Referencia</th>
        <th>Cuenta</th>
        <th>Fecha acredit.</th>
        <th class="right">Importe</th>
        <th class="right">Comisión</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $sumPagos = 0; $sumCom = 0;
      if ($rp && pg_num_rows($rp)>0):
        while($p = pg_fetch_assoc($rp)):
          $sumPagos += (float)$p['importe'];
          $sumCom   += (float)$p['comision'];
      ?>
        <tr>
          <td><?= e($p['medio_pago']) ?></td>
          <td><?= e($p['referencia']) ?></td>
          <td><?= e($p['cuenta_label']) ?></td>
          <td><?= e($p['fecha_acredit']) ?></td>
          <td class="right"><?= n($p['importe'],0) ?></td>
          <td class="right"><?= n($p['comision'],0) ?></td>
        </tr>
      <?php endwhile; else: ?>
        <tr><td colspan="6" class="muted">Sin medios de pago cargados.</td></tr>
      <?php endif; ?>
      <tr>
        <td colspan="4" class="right"><strong>Totales</strong></td>
        <td class="right"><strong><?= n($sumPagos,0) ?></strong></td>
        <td class="right"><strong><?= n($sumCom,0) ?></strong></td>
      </tr>
    </tbody>
  </table>

  <!-- Resumen aplicado -->
  <h3 style="margin-top:14px;margin-bottom:6px">Aplicado a documentos</h3>
  <table>
    <thead>
      <tr>
        <th>Documento</th>
        <th class="right">Monto aplicado</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $sumApl = 0;
      if ($ra && pg_num_rows($ra)>0):
        while($a = pg_fetch_assoc($ra)):
          $sumApl += (float)$a['monto'];
      ?>
        <tr>
          <td>Factura <?= e($a['numero_documento']) ?> (ID <?= (int)$a['id_factura'] ?>)</td>
          <td class="right"><?= n($a['monto'],0) ?></td>
        </tr>
      <?php endwhile;
        elseif (!empty($aplFallback)):
          foreach($aplFallback as $doc=>$monto):
            $sumApl += (float)$monto; ?>
            <tr>
              <td>Factura <?= e($doc) ?></td>
              <td class="right"><?= n($monto,0) ?></td>
            </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="2" class="muted">Este recibo aún no tiene aplicaciones registradas.</td></tr>
      <?php endif; ?>
      <tr>
        <td class="right"><strong>Total aplicado</strong></td>
        <td class="right"><strong><?= n($sumApl,0) ?></strong></td>
      </tr>
    </tbody>
  </table>

  <!-- Detalle de cuotas cobradas -->
  <h3 style="margin-top:14px;margin-bottom:6px">Detalle de cuotas cobradas</h3>
  <table>
    <thead>
      <tr>
        <th>Factura</th>
        <th>Cuota</th>
        <th>Vencimiento</th>
        <th class="right">Pagado</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $sumCuotas = 0;
      if ($rcu && pg_num_rows($rcu)>0):
        while($c = pg_fetch_assoc($rcu)):
          $sumCuotas += (float)$c['pagado']; ?>
          <tr>
            <td><?= e($c['numero_documento']) ?></td>
            <td><?= (int)$c['nro_cuota'] ?>/<?= (int)$c['cant_cuotas'] ?></td>
            <td><?= $c['vencimiento'] ? e($c['vencimiento']) : '—' ?></td>
            <td class="right"><?= n($c['pagado'],0) ?></td>
          </tr>
      <?php endwhile; else: ?>
        <tr><td colspan="4" class="muted">Sin cuotas asociadas a este recibo.</td></tr>
      <?php endif; ?>
      <tr>
        <td colspan="3" class="right"><strong>Total cuotas</strong></td>
        <td class="right"><strong><?= n($sumCuotas,0) ?></strong></td>
      </tr>
    </tbody>
  </table>

  <p class="muted" style="margin-top:8px">
    *Generado por sistema — Usuario: <?= e($_SESSION['nombre_usuario'] ?? '') ?>.
    <?php if ($esAnulado): ?> <strong style="color:var(--danger)">Recibo ANULADO</strong> <?php endif; ?>
  </p>
</div>

<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js" class="no-print"></script>
<?php if (!empty($_GET['auto'])): ?>
<script> window.addEventListener('load', ()=> setTimeout(()=>window.print(), 150)); </script>
<?php endif; ?>
</body>
</html>
