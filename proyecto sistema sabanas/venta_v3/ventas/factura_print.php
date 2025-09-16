<?php
// factura_print.php — Vista A4, lista para imprimir
session_start();
if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
require_once __DIR__.'/../../conexion/configv2.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function n($x, $d=0){ return number_format((float)$x, $d, ',', '.'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id<=0){ http_response_code(400); die('ID inválido'); }

// Cabecera + cliente + timbrado
$sql = "
  SELECT
    f.*,
    c.nombre, c.apellido, c.ruc_ci,
    COALESCE(c.direccion,'') AS direccion,
    t.establecimiento, t.punto_expedicion, t.numero_timbrado, t.fecha_inicio, t.fecha_fin
  FROM public.factura_venta_cab f
  JOIN public.clientes c ON c.id_cliente = f.id_cliente
  LEFT JOIN public.timbrado t ON t.id_timbrado = f.id_timbrado
  WHERE f.id_factura = $1
  LIMIT 1
";
$r = pg_query_params($conn, $sql, [$id]);
if (!$r || pg_num_rows($r)===0){ http_response_code(404); die('Factura no encontrada'); }
$F = pg_fetch_assoc($r);

// Detalle
$sqlD = "
  SELECT descripcion, unidad, cantidad, precio_unitario, tipo_iva, iva_monto, subtotal_neto
  FROM public.factura_venta_det
  WHERE id_factura = $1
  ORDER BY descripcion
";
$rd = pg_query_params($conn, $sqlD, [$id]);

$estado = strtolower($F['estado'] ?? '');
$esAnulada = ($estado === 'anulada');
$cliente = trim(($F['nombre'] ?? '').' '.($F['apellido'] ?? ''));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Factura <?= e($F['numero_documento']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  :root{ --text:#111827; --muted:#6b7280; --em:#2563eb; --danger:#b91c1c; }
  body{ margin:0; color:var(--text); font: 14px/1.45 system-ui,-apple-system,Segoe UI,Roboto; background:#fff; }
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
  tfoot td{ border:none; }
  .right{ text-align:right; }
  .muted{ color:var(--muted); }
  .badge{ display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; border:1px solid #e5e7eb; }
  .badge.anulada{ color:#b91c1c; border-color:#fecaca; background:#fef2f2; }

  /* Marca de agua ANULADA */
  .watermark{
    position:absolute; inset:0; pointer-events:none; display:<?= $esAnulada ? 'block':'none' ?>;
  }
  .watermark::after{
    content:'ANULADA';
    position:absolute; top:40%; left:50%; transform:translate(-50%,-50%) rotate(-20deg);
    font-size:120px; color:rgba(185,28,28,.12); font-weight:800; letter-spacing:6px;
  }

  .actions{ position:sticky; top:0; background:#fff; border-bottom:1px solid #eee; padding:8px 14px; display:flex; gap:8px; }
  .btn{ display:inline-block; padding:8px 12px; border-radius:8px; border:1px solid #e5e7eb; text-decoration:none; color:#111; }
  .btn.primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
  .no-print{ display:block; }
  @media print{
    .no-print{ display:none !important; }
    .sheet{ box-shadow:none; margin:0; padding:12mm; }
    @page{ size:A4; margin:10mm; }
  }
</style>
</head>
<body>
<div id="navbar-container" class="no-print"></div>

<div class="actions no-print">
  <a href="javascript:window.print()" class="btn primary">Imprimir</a>
  <a href="ui_facturas.php" class="btn">Volver</a>
  <?php if ($esAnulada): ?>
    <span class="badge anulada">Estado: ANULADA</span>
  <?php else: ?>
    <span class="badge">Estado: <?= e($F['estado']) ?></span>
  <?php endif; ?>
</div>

<div class="sheet">
  <div class="watermark"></div>

  <div class="head">
    <div class="brand">
      <h2>Tu Empresa</h2>
      <small class="muted">
        RUC: 80000000-1 · Tel: (021) 000-000 · Asunción, PY<br>
        <!-- Podés reemplazar/traer estos datos desde tu tabla de empresa -->
        Email: ventas@tuempresa.com
      </small>
    </div>
    <div class="docbox">
      <h1>FACTURA</h1>
      <div>N° <span class="num"><?= e($F['numero_documento']) ?></span></div>
      <?php if (!empty($F['numero_timbrado'])): ?>
        <div>Timbrado: <strong><?= e($F['numero_timbrado']) ?></strong>
          <?php if (!empty($F['fecha_fin'])): ?>
            <span class="muted"> (Vence: <?= e($F['fecha_fin']) ?>)</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($F['establecimiento']) || !empty($F['punto_expedicion'])): ?>
        <div class="muted">Est.: <?= e($F['establecimiento']) ?> · Pto.: <?= e($F['punto_expedicion']) ?></div>
      <?php endif; ?>
      <div>Emisión: <strong><?= e($F['fecha_emision']) ?></strong></div>
      <div>Condición: <strong><?= e($F['condicion_venta']) ?></strong></div>
    </div>
  </div>

  <div class="grid">
    <div class="box">
      <h3>Cliente</h3>
      <div><strong><?= e($cliente) ?></strong></div>
      <div>RUC/CI: <?= e($F['ruc_ci']) ?></div>
      <?php if (!empty($F['direccion'])): ?>
        <div class="muted">Dirección: <?= e($F['direccion']) ?></div>
      <?php endif; ?>
    </div>
    <div class="box">
      <h3>Referencia</h3>
      <div>Pedido: <?= $F['id_pedido'] ? '#'.e($F['id_pedido']) : '-' ?></div>
      <?php if (!empty($F['observacion'])): ?>
        <div class="muted">Obs.: <?= e($F['observacion']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:8%">Cant.</th>
        <th>Descripción</th>
        <th style="width:10%">Uni.</th>
        <th class="right" style="width:16%">Precio</th>
        <th class="right" style="width:10%">IVA</th>
        <th class="right" style="width:16%">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($rd && pg_num_rows($rd)>0): ?>
        <?php while($d=pg_fetch_assoc($rd)): ?>
          <tr>
            <td><?= e(n($d['cantidad'], 0)) ?></td>
            <td><?= e($d['descripcion']) ?></td>
            <td><?= e($d['unidad']) ?></td>
            <td class="right"><?= e(n($d['precio_unitario'], 0)) ?></td>
            <td class="right"><?= e($d['tipo_iva']) ?></td>
            <td class="right"><?= e(n($d['subtotal_neto'], 0)) ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="6" class="muted">Sin ítems</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="6">&nbsp;</td></tr>
      <tr>
        <td colspan="4"></td>
        <td class="right">Grav. 10%:</td>
        <td class="right"><?= e(n($F['total_grav10'] ?? 0, 0)) ?></td>
      </tr>
      <tr>
        <td colspan="4"></td>
        <td class="right">IVA 10%:</td>
        <td class="right"><?= e(n($F['total_iva10'] ?? 0, 0)) ?></td>
      </tr>
      <tr>
        <td colspan="4"></td>
        <td class="right">Grav. 5%:</td>
        <td class="right"><?= e(n($F['total_grav5'] ?? 0, 0)) ?></td>
      </tr>
      <tr>
        <td colspan="4"></td>
        <td class="right">IVA 5%:</td>
        <td class="right"><?= e(n($F['total_iva5'] ?? 0, 0)) ?></td>
      </tr>
      <tr>
        <td colspan="4"></td>
        <td class="right">Exentas:</td>
        <td class="right"><?= e(n($F['total_exentas'] ?? 0, 0)) ?></td>
      </tr>
      <tr>
        <td colspan="4"></td>
        <td class="right"><strong>Total Neto:</strong></td>
        <td class="right"><strong><?= e(n($F['total_neto'] ?? 0, 0)) ?></strong></td>
      </tr>
    </tfoot>
  </table>

  <p class="muted" style="margin-top:8px">
    *Documento generado por sistema — Usuario: <?= e($_SESSION['nombre_usuario'] ?? '') ?>.
    <?php if ($esAnulada): ?> <strong style="color:var(--danger)">Factura ANULADA</strong> <?php endif; ?>
  </p>
</div>

<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js" class="no-print"></script>
</body>
</html>
