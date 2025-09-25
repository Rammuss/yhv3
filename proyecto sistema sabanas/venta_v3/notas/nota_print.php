<?php
// nota_print.php — Imprime NC / ND en A4, lista para imprimir
session_start();
if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
require_once __DIR__.'/../../conexion/configv2.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function n($x, $d=0){ return number_format((float)$x, $d, ',', '.'); }

$clase = isset($_GET['clase']) ? strtoupper(trim($_GET['clase'])) : '';
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!in_array($clase, ['NC','ND'], true) || $id<=0){
  http_response_code(400);
  die('Parámetros inválidos. Esperado: ?clase=NC|ND&id=###');
}

// === Cargar CABECERA ===
if ($clase === 'NC') {
  $sqlCab = "
    SELECT
      n.*,
      c.nombre, c.apellido, c.ruc_ci,
      COALESCE(c.direccion,'') AS direccion,
      t.establecimiento, t.punto_expedicion, t.numero_timbrado, t.fecha_inicio, t.fecha_fin
    FROM public.nc_venta_cab n
    JOIN public.clientes c ON c.id_cliente = n.id_cliente
    LEFT JOIN public.timbrado t ON t.id_timbrado = n.id_timbrado
    WHERE n.id_nc = $1
    LIMIT 1
  ";
  $rCab = pg_query_params($conn, $sqlCab, [$id]);
  if (!$rCab || pg_num_rows($rCab)===0){ http_response_code(404); die('NC no encontrada'); }
  $Cab = pg_fetch_assoc($rCab);

  $sqlDet = "
    SELECT
      descripcion,               -- texto
      COALESCE(cantidad,0)  AS cantidad,
      COALESCE(precio_unitario,0) AS precio_unitario,
      COALESCE(descuento,0) AS descuento,
      COALESCE(tipo_iva,'EX') AS tipo_iva,
      COALESCE(iva_monto,0)  AS iva_monto,
      COALESCE(subtotal_bruto,0) AS subtotal_bruto,
      COALESCE(subtotal_neto,0)  AS subtotal_neto,
      id_producto
    FROM public.nc_venta_det
    WHERE id_nc = $1
    ORDER BY descripcion
  ";
  $rDet = pg_query_params($conn, $sqlDet, [$id]);

  $numero_documento = $Cab['numero_documento'];
  $estadoDoc        = $Cab['estado'];
  $fechaEmision     = $Cab['fecha_emision'];
  $idFactura        = $Cab['id_factura'];
  $afectaStock      = ($Cab['afecta_stock'] === 't' || $Cab['afecta_stock'] === true || $Cab['afecta_stock']==='1');

  $total_bruto = (float)$Cab['total_bruto'];
  $total_desc  = (float)$Cab['total_descuento'];
  $total_iva   = (float)$Cab['total_iva'];
  $total_neto  = (float)$Cab['total_neto'];

} else { // ND
  $sqlCab = "
    SELECT
      d.*,
      c.nombre, c.apellido, c.ruc_ci,
      COALESCE(c.direccion,'') AS direccion,
      t.establecimiento, t.punto_expedicion, t.numero_timbrado, t.fecha_inicio, t.fecha_fin
    FROM public.nd_venta_cab d
    JOIN public.clientes c ON c.id_cliente = d.id_cliente
    LEFT JOIN public.timbrado t ON t.id_timbrado = d.id_timbrado
    WHERE d.id_nd = $1
    LIMIT 1
  ";
  $rCab = pg_query_params($conn, $sqlCab, [$id]);
  if (!$rCab || pg_num_rows($rCab)===0){ http_response_code(404); die('ND no encontrada'); }
  $Cab = pg_fetch_assoc($rCab);

  $sqlDet = "
    SELECT
      descripcion,
      COALESCE(cantidad,0)  AS cantidad,
      COALESCE(precio_unitario,0) AS precio_unitario,
      COALESCE(descuento,0) AS descuento,
      COALESCE(tipo_iva,'EX') AS tipo_iva,
      COALESCE(iva_monto,0)  AS iva_monto,
      COALESCE(subtotal_bruto,0) AS subtotal_bruto,
      COALESCE(subtotal_neto,0)  AS subtotal_neto,
      id_producto
    FROM public.nd_venta_det
    WHERE id_nd = $1
    ORDER BY descripcion
  ";
  $rDet = pg_query_params($conn, $sqlDet, [$id]);

  $numero_documento = $Cab['numero_documento'];
  $estadoDoc        = $Cab['estado'];
  $fechaEmision     = $Cab['fecha_emision'];
  $idFactura        = $Cab['id_factura'];
  $afectaStock      = false;

  $total_bruto = (float)$Cab['total_bruto'];
  $total_desc  = (float)$Cab['total_descuento'];
  $total_iva   = (float)$Cab['total_iva'];
  $total_neto  = (float)$Cab['total_neto'];
}

// cliente
$cliente = trim(($Cab['nombre'] ?? '').' '.($Cab['apellido'] ?? ''));

// === Totales IVA por detalle (por si en cabecera no están desglosados) ===
$sum = ['grav10'=>0,'iva10'=>0,'grav5'=>0,'iva5'=>0,'exentas'=>0];
if ($rDet && pg_num_rows($rDet)>0) {
  pg_result_seek($rDet, 0);
  while($d = pg_fetch_assoc($rDet)){
    $tipo = strtoupper(trim($d['tipo_iva'] ?? 'EX'));
    $subn = (float)$d['subtotal_neto'];
    $iva  = (float)$d['iva_monto'];
    if ($tipo==='10' || $tipo==='10%'){
      $sum['grav10'] += $subn;
      $sum['iva10']  += $iva;
    } elseif ($tipo==='5' || $tipo==='5%'){
      $sum['grav5'] += $subn;
      $sum['iva5']  += $iva;
    } else {
      $sum['exentas'] += $subn;
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= ($clase==='NC'?'Nota de Crédito':'Nota de Débito') ?> <?= e($numero_documento) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  :root{ --text:#111827; --muted:#6b7280; --em:#2563eb; --danger:#b91c1c; --ok:#166534; --warn:#9a6700; --vio:#7c3aed; }
  body{ margin:0; color:var(--text); font: 14px/1.45 system-ui,-apple-system,Segoe UI,Roboto; background:#fff; }
  .sheet{ width:210mm; min-height:297mm; margin:0 auto; padding:16mm 14mm; box-sizing:border-box; position:relative; }
  .head{ display:flex; justify-content:space-between; align-items:flex-start; gap:16px; border-bottom:2px solid #eee; padding-bottom:10px; }
  .brand h2{ margin:0 0 6px; font-size:20px; }
  .brand small{ color:var(--muted); }
  .docbox{ text-align:right; }
  .docbox h1{ margin:0; font-size:22px; letter-spacing:.5px; }
  .docbox .num{ font-weight:800; color:var(--em); }
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
  .badge.ok{ color:#166534; border-color:#bbf7d0; background:#f0fdf4; }
  .badge.warn{ color:#9a6700; border-color:#fde68a; background:#fef9c3; }
  .badge.emph{ color:#7c3aed; border-color:#ddd6fe; background:#f5f3ff; }
  .badge.danger{ color:#b91c1c; border-color:#fecaca; background:#fef2f2; }

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
  <a href="javascript:history.back()" class="btn">Volver</a>
  <span class="badge emph"><?= ($clase==='NC'?'NC':'ND') ?> · <?= e($numero_documento) ?></span>
  <span class="badge">Estado: <?= e($estadoDoc) ?></span>
  <?php if ($afectaStock): ?><span class="badge warn">NC con devolución a stock</span><?php endif; ?>
  <?php if (!empty($idFactura)): ?><span class="badge ok">Asociada a Factura #<?= (int)$idFactura ?></span><?php endif; ?>
</div>

<div class="sheet">
  <div class="head">
    <div class="brand">
      <h2>Tu Empresa</h2>
      <small class="muted">
        RUC: 80000000-1 · Tel: (021) 000-000 · Asunción, PY<br>
        Email: ventas@tuempresa.com
      </small>
    </div>
    <div class="docbox">
      <h1><?= ($clase==='NC'?'NOTA DE CRÉDITO':'NOTA DE DÉBITO') ?></h1>
      <div>N° <span class="num"><?= e($numero_documento) ?></span></div>
      <?php if (!empty($Cab['numero_timbrado'])): ?>
        <div>Timbrado: <strong><?= e($Cab['numero_timbrado']) ?></strong>
          <?php if (!empty($Cab['fecha_fin'])): ?>
            <span class="muted"> (Vence: <?= e($Cab['fecha_fin']) ?>)</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($Cab['establecimiento']) || !empty($Cab['punto_expedicion'])): ?>
        <div class="muted">Est.: <?= e($Cab['establecimiento']) ?> · Pto.: <?= e($Cab['punto_expedicion']) ?></div>
      <?php endif; ?>
      <div>Emisión: <strong><?= e($fechaEmision) ?></strong></div>
    </div>
  </div>

  <div class="grid">
    <div class="box">
      <h3>Cliente</h3>
      <div><strong><?= e($cliente) ?></strong></div>
      <div>RUC/CI: <?= e($Cab['ruc_ci']) ?></div>
      <?php if (!empty($Cab['direccion'])): ?>
        <div class="muted">Dirección: <?= e($Cab['direccion']) ?></div>
      <?php endif; ?>
    </div>
    <div class="box">
      <h3>Referencia</h3>
      <?php if (!empty($idFactura)): ?>
        <div>Factura asociada: #<?= (int)$idFactura ?></div>
      <?php else: ?>
        <div>Factura asociada: -</div>
      <?php endif; ?>
      <?php if (!empty($Cab['id_motivo'])): ?>
        <div>Motivo: #<?= (int)$Cab['id_motivo'] ?> <?= !empty($Cab['motivo_texto']) ? '· '.e($Cab['motivo_texto']) : '' ?></div>
      <?php elseif (!empty($Cab['motivo_texto'])): ?>
        <div>Motivo: <?= e($Cab['motivo_texto']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:8%">Cant.</th>
        <th>Descripción</th>
        <th style="width:10%">IVA</th>
        <th class="right" style="width:16%">Precio</th>
        <th class="right" style="width:16%">Desc.</th>
        <th class="right" style="width:16%">Subtotal</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($rDet && pg_num_rows($rDet)>0): ?>
      <?php pg_result_seek($rDet, 0); while($d=pg_fetch_assoc($rDet)): ?>
        <tr>
          <td><?= n($d['cantidad'], 0) ?></td>
          <td><?= e($d['descripcion']) ?></td>
          <td><?= e($d['tipo_iva']) ?></td>
          <td class="right"><?= n($d['precio_unitario'], 0) ?></td>
          <td class="right"><?= n($d['descuento'], 0) ?></td>
          <td class="right"><?= n($d['subtotal_neto'], 0) ?></td>
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
        <td class="right"><?= n($sum['grav10'], 0) ?></td>
      </tr>
      <tr>
        <td colspan="4"></td>
        <td class="right">IVA 10%:</td>
        <td class="right"><?= n($sum['iva10'], 0) ?></td>
      </tr>
      <tr>
        <td colspan="4"></td>
        <td class="right">Grav. 5%:</td>
        <td class="right"><?= n($sum['grav5'], 0) ?></td>
      </tr>
      <tr>
        <td colspan="4"></td>
        <td class="right">IVA 5%:</td>
        <td class="right"><?= n($sum['iva5'], 0) ?></td>
      </tr>
      <tr>
        <td colspan="4"></td>
        <td class="right">Exentas:</td>
        <td class="right"><?= n($sum['exentas'], 0) ?></td>
      </tr>
      <tr>
        <td colspan="4"></td>
        <td class="right"><strong>Total Neto:</strong></td>
        <td class="right"><strong><?= n($total_neto, 0) ?></strong></td>
      </tr>
    </tfoot>
  </table>

  <p class="muted" style="margin-top:8px">
    *Documento generado por sistema — Usuario: <?= e($_SESSION['nombre_usuario'] ?? '') ?>.
  </p>
</div>

<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js" class="no-print"></script>
<script>
// Auto-print si viene ?auto=1
(function(){
  try{
    const p = new URLSearchParams(location.search);
    if (p.get('auto') === '1') {
      window.addEventListener('load', ()=> setTimeout(()=> window.print(), 300));
    }
  }catch(e){}
})();
</script>
</body>
</html>
