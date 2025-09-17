<?php
// nota_remision/nota_remision_print.php — Vista A4 lista para imprimir
session_start();
if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
require_once __DIR__.'/../../conexion/configv2.php';

header('Content-Type: text/html; charset=utf-8');

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function n($x,$d=0){ return number_format((float)$x,$d,',','.'); }
function dt($ts){
  if (!$ts) return '';
  // Acepta 'YYYY-MM-DD HH:MM:SS' o DateTime
  $t = is_string($ts) ? strtotime($ts) : $ts;
  if (!$t) return '';
  return date('Y-m-d H:i', $t);
}
function has_col($conn,$schema,$table,$col){
  $r = pg_query_params($conn,
    "SELECT 1 FROM information_schema.columns WHERE table_schema=$1 AND table_name=$2 AND column_name=$3 LIMIT 1",
    [$schema,$table,$col]
  );
  return $r && pg_num_rows($r) > 0;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0){ http_response_code(400); die('ID de remisión inválido'); }

// CABECERA
$sqlCab = "
  SELECT
    r.id_remision_venta, r.numero_documento, r.fecha,
    r.id_factura, r.id_cliente,
    r.origen, r.destino, r.ciudad_origen, r.ciudad_destino,
    r.fecha_salida, r.fecha_llegada,
    r.chofer_nombre, r.chofer_doc,
    r.vehiculo_marca, r.vehiculo_chapa,
    r.transportista, r.estado,
    COALESCE(r.observacion,'') AS observacion,
    c.nombre, c.apellido, c.ruc_ci, COALESCE(c.direccion,'') AS direccion,
    f.numero_documento AS factura_numero, f.fecha_emision AS factura_fecha
  FROM public.remision_venta_cab r
  JOIN public.clientes c ON c.id_cliente = r.id_cliente
  LEFT JOIN public.factura_venta_cab f ON f.id_factura = r.id_factura
  WHERE r.id_remision_venta = $1
  LIMIT 1
";
$rc = pg_query_params($conn, $sqlCab, [$id]);
if (!$rc || pg_num_rows($rc)===0){ http_response_code(404); die('Remisión no encontrada'); }
$R = pg_fetch_assoc($rc);
$cliente = trim(($R['nombre'] ?? '').' '.($R['apellido'] ?? ''));
$estado  = strtolower($R['estado'] ?? '');
$esAnulada = ($estado === 'anulada');

// DETALLE (soporta ausencia de columna 'unidad')
$schema='public'; $tDet='remision_venta_det';
$hasUnidad = has_col($conn,$schema,$tDet,'unidad');

if ($hasUnidad){
  $sqlDet = "
    SELECT
      COALESCE(descripcion,'') AS descripcion,
      COALESCE(unidad,'UNI')   AS unidad,
      COALESCE(cantidad,0)::numeric(14,3) AS cantidad
    FROM public.remision_venta_det
    WHERE id_remision_venta = $1
    ORDER BY descripcion
  ";
} else {
  $sqlDet = "
    SELECT
      COALESCE(descripcion,'') AS descripcion,
      'UNI' AS unidad,
      COALESCE(cantidad,0)::numeric(14,3) AS cantidad
    FROM public.remision_venta_det
    WHERE id_remision_venta = $1
    ORDER BY descripcion
  ";
}
$rd = pg_query_params($conn, $sqlDet, [$id]);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Nota de Remisión <?= e($R['numero_documento'] ?: ('ID '.$R['id_remision_venta'])) ?></title>
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
  .docbox .num{ font-weight:700; color:var(--em); }
  .grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px; }
  .box{ border:1px solid #e5e7eb; border-radius:8px; padding:10px; }
  .box h3{ margin:0 0 8px; font-size:14px; }
  table{ width:100%; border-collapse:collapse; margin-top:12px; }
  th,td{ border-bottom:1px solid #eee; padding:8px; text-align:left; }
  th{ background:#f8fafc; font-weight:600; }
  .right{ text-align:right; }
  .muted{ color:var(--muted); }
  .badge{ display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; border:1px solid #e5e7eb; }
  .badge.anulada{ color:#b91c1c; border-color:#fecaca; background:#fef2f2; }
  .actions{ position:sticky; top:0; background:#fff; border-bottom:1px solid #eee; padding:8px 14px; display:flex; gap:8px; }
  .btn{ display:inline-block; padding:8px 12px; border-radius:8px; border:1px solid #e5e7eb; text-decoration:none; color:#111; }
  .btn.primary{ background:#2563eb; color:#fff; border-color:#2563eb; }
  .no-print{ display:block; }

  /* Marca de agua ANULADA */
  .watermark{ position:absolute; inset:0; pointer-events:none; display:<?= $esAnulada ? 'block':'none' ?>; }
  .watermark::after{
    content:'ANULADA';
    position:absolute; top:40%; left:50%; transform:translate(-50%,-50%) rotate(-20deg);
    font-size:120px; color:rgba(185,28,28,.12); font-weight:800; letter-spacing:6px;
  }

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
  <a href="javascript:window.close()" class="btn">Cerrar</a>
  <?php if ($esAnulada): ?>
    <span class="badge anulada">Estado: ANULADA</span>
  <?php else: ?>
    <span class="badge">Estado: <?= e($R['estado'] ?: 'Emitida') ?></span>
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
      <h1>NOTA DE REMISIÓN</h1>
      <div>N° <span class="num"><?= e($R['numero_documento'] ?: ('ID '.$R['id_remision_venta'])) ?></span></div>
      <div>Fecha doc.: <strong><?= e($R['fecha']) ?></strong></div>
      <?php if(!empty($R['fecha_salida'])): ?>
        <div>Salida: <strong><?= e(dt($R['fecha_salida'])) ?></strong></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid">
    <div class="box">
      <h3>Cliente</h3>
      <div><strong><?= e($cliente) ?></strong></div>
      <div>RUC/CI: <?= e($R['ruc_ci'] ?: '') ?></div>
      <?php if (!empty($R['direccion'])): ?>
        <div class="muted">Dirección: <?= e($R['direccion']) ?></div>
      <?php endif; ?>
    </div>
    <div class="box">
      <h3>Documento de Venta</h3>
      <div>Factura: <strong><?= e($R['factura_numero'] ?: '—') ?></strong></div>
      <?php if (!empty($R['factura_fecha'])): ?>
        <div>Fecha: <?= e($R['factura_fecha']) ?></div>
      <?php endif; ?>
      <?php if (!empty($R['observacion'])): ?>
        <div class="muted" style="margin-top:6px"><em><?= nl2br(e($R['observacion'])) ?></em></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid">
    <div class="box">
      <h3>Origen / Destino</h3>
      <div><strong>Origen:</strong> <?= e($R['origen'] ?: '—') ?></div>
      <?php if(!empty($R['ciudad_origen'])): ?>
        <div class="muted mini">Ciudad origen: <?= e($R['ciudad_origen']) ?></div>
      <?php endif; ?>
      <div style="margin-top:6px"><strong>Destino:</strong> <?= e($R['destino'] ?: '—') ?></div>
      <?php if(!empty($R['ciudad_destino'])): ?>
        <div class="muted mini">Ciudad destino: <?= e($R['ciudad_destino']) ?></div>
      <?php endif; ?>
    </div>
    <div class="box">
      <h3>Transporte</h3>
      <div><strong>Transportista:</strong> <?= e($R['transportista'] ?: '—') ?></div>
      <div><strong>Chofer:</strong> <?= e($R['chofer_nombre'] ?: '—') ?> — CI: <?= e($R['chofer_doc'] ?: '—') ?></div>
      <div><strong>Vehículo:</strong> <?= e($R['vehiculo_marca'] ?: '—') ?> · Chapa: <?= e($R['vehiculo_chapa'] ?: '—') ?></div>
      <?php if(!empty($R['fecha_llegada'])): ?>
        <div>Hora llegada: <?= e(dt($R['fecha_llegada'])) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Ítems -->
  <h3 style="margin-top:14px;margin-bottom:6px">Detalle de ítems</h3>
  <table>
    <thead>
      <tr>
        <th>Descripción</th>
        <th>Unidad</th>
        <th class="right">Cantidad</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $sumCant = 0.0;
      if ($rd && pg_num_rows($rd)>0):
        while($d = pg_fetch_assoc($rd)):
          $sumCant += (float)$d['cantidad'];
      ?>
        <tr>
          <td><?= e($d['descripcion']) ?></td>
          <td><?= e($d['unidad']) ?></td>
          <td class="right"><?= n($d['cantidad'],3) ?></td>
        </tr>
      <?php endwhile; else: ?>
        <tr><td colspan="3" class="muted">Sin ítems cargados.</td></tr>
      <?php endif; ?>
      <tr>
        <td class="right" colspan="2"><strong>Total de unidades</strong></td>
        <td class="right"><strong><?= n($sumCant,3) ?></strong></td>
      </tr>
    </tbody>
  </table>

  <p class="muted" style="margin-top:8px">
    *Generado por sistema — Usuario: <?= e($_SESSION['nombre_usuario'] ?? '') ?>.
    <?php if ($esAnulada): ?> <strong style="color:var(--danger)">Remisión ANULADA</strong> <?php endif; ?>
  </p>
</div>

<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js" class="no-print"></script>
<?php if (!empty($_GET['auto'])): ?>
<script> window.addEventListener('load', ()=> setTimeout(()=>window.print(), 150)); </script>
<?php endif; ?>
</body>
</html>
