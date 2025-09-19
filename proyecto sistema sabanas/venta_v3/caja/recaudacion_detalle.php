<?php
// /caja/recaudacion_detalle.php — Vista de recaudación (sin depósito)
session_start();
if (empty($_SESSION['id_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
require_once __DIR__.'/../../conexion/configv2.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function n($x,$d=0){ return number_format((float)$x,$d,',','.'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0){ http_response_code(400); die('ID inválido'); }

// Cabecera
$rc = pg_query_params($conn, "
  SELECT r.id_recaudacion, r.fecha, r.id_sucursal, r.estado, r.monto_total,
         r.observacion, r.creado_en, r.actualizado_en, r.id_usuario,
         u.nombre_usuario AS creado_por
    FROM public.recaudacion_deposito r
    LEFT JOIN public.usuarios u ON u.id = r.id_usuario
   WHERE r.id_recaudacion = $1
   LIMIT 1
", [$id]);
if (!$rc || pg_num_rows($rc)===0){ http_response_code(404); die('Recaudación no encontrada'); }
$R = pg_fetch_assoc($rc);

// Detalle: sesiones incluidas
$rd = pg_query_params($conn, "
  SELECT rd.id_caja_sesion,
         cs.fecha_apertura, cs.fecha_cierre,
         c.nombre AS caja_nombre,
         u.nombre_usuario AS cajero,
         rd.monto_efectivo, rd.monto_tarjeta, rd.monto_transferencia, rd.monto_otros
    FROM public.recaudacion_detalle rd
    JOIN public.caja_sesion cs ON cs.id_caja_sesion = rd.id_caja_sesion
    JOIN public.caja c ON c.id_caja = cs.id_caja
    JOIN public.usuarios u ON u.id = cs.id_usuario
   WHERE rd.id_recaudacion = $1
   ORDER BY cs.fecha_cierre DESC NULLS LAST, rd.id_caja_sesion DESC
", [$id]);

$rows = [];
$sum = ['ef'=>0,'ta'=>0,'tr'=>0,'ot'=>0];
if ($rd) {
  while($x = pg_fetch_assoc($rd)){
    $rows[] = $x;
    $sum['ef'] += (float)$x['monto_efectivo'];
    $sum['ta'] += (float)$x['monto_tarjeta'];
    $sum['tr'] += (float)$x['monto_transferencia'];
    $sum['ot'] += (float)$x['monto_otros'];
  }
}
$totalCalc = $sum['ef'] + $sum['ta'] + $sum['tr'] + $sum['ot'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recaudación #<?= (int)$R['id_recaudacion'] ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  :root{ --text:#111827; --muted:#6b7280; --em:#2563eb; --danger:#b91c1c; --ok:#166534; --warn:#9a6700; }
  body{ margin:0; color:var(--text); font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto; background:#fff; }
  .container{ max-width:1100px; margin:24px auto; padding:0 14px; }
  .head{ display:flex; justify-content:space-between; align-items:center; margin:10px 0 14px; }
  .head h1{ margin:0; font-size:22px; }
  .muted{ color:var(--muted); }
  .card{ border:1px solid #e5e7eb; border-radius:12px; padding:14px; margin-bottom:12px; }
  table{ width:100%; border-collapse:collapse; }
  th,td{ padding:10px; border-bottom:1px solid #f1f5f9; text-align:left; }
  th{ background:#f8fafc; }
  .right{ text-align:right; }
  .grid{ display:grid; gap:12px; }
  @media(min-width:900px){ .grid.cols-4{ grid-template-columns: repeat(4,1fr); } }
  .btn{ display:inline-block; padding:10px 14px; border-radius:10px; border:1px solid #e5e7eb; text-decoration:none; color:#111; cursor:pointer; }
  .btn.primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
  .btn.ghost{ background:#fff; }
  @media print{
    .no-print{ display:none !important; }
    .container{ margin:0; }
  }
</style>
</head>
<body>
<div id="navbar-container" class="no-print"></div>

<div class="container">
  <div class="head">
    <div>
      <h1>Recaudación #<?= (int)$R['id_recaudacion'] ?> — <?= e($R['estado']) ?></h1>
      <div class="muted">
        Generada: <?= e($R['creado_en'] ?? $R['fecha']) ?>
        · Por: <strong><?= e($R['creado_por'] ?? ('#'.($R['id_usuario'] ?? ''))) ?></strong>
      </div>
    </div>
    <div class="no-print">
      <a href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/caja/ui_recaudacion_historial.php" class="btn">Historial</a>
      <a href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/caja/ui_recaudaciones.php" class="btn">Consolidar otra</a>
      <a href="javascript:window.print()" class="btn primary">Imprimir</a>
    </div>
  </div>

  <!-- Totales -->
  <div class="card">
    <div class="grid cols-4">
      <div><strong>Efectivo:</strong> Gs <?= n($sum['ef'],0) ?></div>
      <div><strong>Tarjeta:</strong> Gs <?= n($sum['ta'],0) ?></div>
      <div><strong>Transferencia:</strong> Gs <?= n($sum['tr'],0) ?></div>
      <div><strong>Otros:</strong> Gs <?= n($sum['ot'],0) ?></div>
    </div>
    <div style="margin-top:8px;">
      <strong>Total:</strong> Gs <?= n($totalCalc,0) ?>
      <span class="muted"> (cabecera: Gs <?= n($R['monto_total'] ?? 0,0) ?>)</span>
    </div>
    <?php if(!empty($R['observacion'])): ?>
      <div class="muted" style="margin-top:6px;">Obs.: <?= e($R['observacion']) ?></div>
    <?php endif; ?>
  </div>

  <!-- Sesiones incluidas -->
  <div class="card">
    <table>
      <thead>
        <tr>
          <th>Sesión</th>
          <th>Caja</th>
          <th>Cajero</th>
          <th style="width:160px">Apertura</th>
          <th style="width:160px">Cierre</th>
          <th class="right" style="width:120px">Efectivo</th>
          <th class="right" style="width:120px">Tarjeta</th>
          <th class="right" style="width:120px">Transf.</th>
          <th class="right" style="width:120px">Otros</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if(empty($rows)):
          echo '<tr><td colspan="9" class="muted">Sin sesiones.</td></tr>';
        else:
          foreach($rows as $x): ?>
          <tr>
            <td>#<?= (int)$x['id_caja_sesion'] ?></td>
            <td><?= e($x['caja_nombre']) ?></td>
            <td><?= e($x['cajero']) ?></td>
            <td><?= e($x['fecha_apertura']) ?></td>
            <td><?= e($x['fecha_cierre']) ?></td>
            <td class="right"><?= n($x['monto_efectivo'],0) ?></td>
            <td class="right"><?= n($x['monto_tarjeta'],0) ?></td>
            <td class="right"><?= n($x['monto_transferencia'],0) ?></td>
            <td class="right"><?= n($x['monto_otros'],0) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="5" class="right"><strong>Totales</strong></td>
          <td class="right"><strong><?= n($sum['ef'],0) ?></strong></td>
          <td class="right"><strong><?= n($sum['ta'],0) ?></strong></td>
          <td class="right"><strong><?= n($sum['tr'],0) ?></strong></td>
          <td class="right"><strong><?= n($sum['ot'],0) ?></strong></td>
        </tr>
      </tfoot>
    </table>
    <p class="muted" style="margin-top:8px;">*Documento generado por el sistema — Usuario: <?= e($_SESSION['nombre_usuario'] ?? '') ?>.</p>
  </div>
</div>

<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js" class="no-print"></script>
</body>
</html>
