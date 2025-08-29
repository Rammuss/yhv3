<?php
// oc_print.php — versión formal con datos de proveedor
require_once("../conexion/config.php");
$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$c) { die("DB error"); }

$id_oc = isset($_GET['id_oc']) ? (int)$_GET['id_oc'] : 0;
if ($id_oc<=0) { die("id_oc requerido"); }

/* === Configuración encabezado de tu empresa (editar a gusto) === */
$empresa_nombre   = "Mi Empresa S.R.L.";
$empresa_ruc      = "80000000-1";
$empresa_dir      = "Av. Siempre Viva 123, Asunción";
$empresa_tel      = "+595 21 000 000";
$empresa_email    = "compras@miempresa.com";
$empresa_logo_url = ""; // si tenés un logo accesible por URL, ponelo aquí

/* === CABECERA OC + PROVEEDOR + CIUDAD/PAÍS === */
$sqlCab = "
  SELECT
    oc.id_oc, oc.numero_pedido, oc.id_proveedor, oc.fecha_emision, oc.estado, oc.observacion,
    prov.nombre AS proveedor,
    prov.ruc    AS proveedor_ruc,
    prov.direccion AS proveedor_direccion,
    prov.telefono  AS proveedor_telefono,
    prov.email     AS proveedor_email,
    pa.nombre      AS proveedor_pais,
    ci.nombre      AS proveedor_ciudad
  FROM public.orden_compra_cab oc
  LEFT JOIN public.proveedores prov ON prov.id_proveedor = oc.id_proveedor
  LEFT JOIN public.paises pa       ON pa.id_pais = prov.id_pais
  LEFT JOIN public.ciudades ci     ON ci.id_ciudad = prov.id_ciudad
  WHERE oc.id_oc = $1
  LIMIT 1
";
$rc = pg_query_params($c, $sqlCab, [$id_oc]);
if (!$rc || pg_num_rows($rc)==0) { die("OC no encontrada"); }
$cab = pg_fetch_assoc($rc);

/* === DETALLE === */
$sqlDet = "
  SELECT d.id_oc_det, d.id_producto, p.nombre AS producto,
         d.cantidad, d.precio_unit
  FROM public.orden_compra_det d
  JOIN public.producto p ON p.id_producto = d.id_producto
  WHERE d.id_oc = $1
  ORDER BY d.id_oc_det
";
$rd = pg_query_params($c,$sqlDet,[$id_oc]);
$det = []; $total = 0.0;
if ($rd) {
  while ($d = pg_fetch_assoc($rd)) {
    $det[] = $d;
    $total += (float)$d['cantidad'] * (float)$d['precio_unit'];
  }
}

/* === Helpers === */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function n2($x){ return number_format((float)$x, 2, ',', '.'); }
function n0($x){ return number_format((float)$x, 0, ',', '.'); }

// Armado proveedor (evita " - , ," si falta algo)
$proveedor_linea1 = trim(($cab['proveedor'] ?: $cab['id_proveedor']));
$proveedor_linea2 = trim(($cab['proveedor_direccion'] ?? ''));
$proveedor_linea3 = trim(implode(' - ', array_filter([
  $cab['proveedor_ciudad'] ?? '',
  $cab['proveedor_pais'] ?? ''
])));
$proveedor_linea4 = trim(implode(' • ', array_filter([
  $cab['proveedor_telefono'] ? ('Tel: '.$cab['proveedor_telefono']) : '',
  $cab['proveedor_email'] ? ('Email: '.$cab['proveedor_email']) : ''
])));
$proveedor_ruc = $cab['proveedor_ruc'] ?? '';

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>OC #<?php echo h($cab['id_oc']); ?></title>
<style>
  :root{
    --border:#dcdcdc;
    --muted:#666;
    --em:#3149c2;
  }
  body{font-family:Arial, sans-serif; margin:24px; color:#222}
  .header{display:flex; align-items:center; gap:16px; border-bottom:2px solid var(--border); padding-bottom:10px; margin-bottom:12px}
  .logo{width:90px; height:90px; object-fit:contain; border:1px solid var(--border); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:12px; color:var(--muted)}
  .empresa h2{margin:0; font-size:20px}
  .empresa .muted{color:var(--muted); font-size:12px}
  .title{display:flex; justify-content:space-between; align-items:flex-end; margin:8px 0 10px}
  .title h1{margin:0; font-size:22px}
  .badge{border:1px solid var(--border); padding:6px 10px; border-radius:8px; font-size:13px}
  .grid{display:grid; grid-template-columns: 1fr 1fr; gap:12px}
  .box{border:1px solid var(--border); border-radius:8px; padding:10px}
  .box h3{margin:0 0 6px; font-size:14px; color:#111}
  .muted{color:var(--muted)}
  table{width:100%; border-collapse:collapse; margin-top:10px}
  th,td{border:1px solid var(--border); padding:8px; text-align:left}
  th{background:#f7f7f7}
  .right{text-align:right}
  .totals{margin-top:10px; display:flex; justify-content:flex-end}
  .totals .sum{min-width:280px; border:1px solid var(--border); border-radius:8px; padding:8px 10px}
  .sum .row{display:flex; justify-content:space-between; margin:4px 0}
  .sum .row.total{font-weight:bold}
  .footer{margin-top:16px; display:grid; grid-template-columns: 1fr 1fr; gap:12px}
  .sign{border:1px dashed var(--border); border-radius:8px; padding:10px; min-height:120px}
  .sign .line{margin-top:60px; border-top:1px solid var(--border); padding-top:6px; text-align:center; color:var(--muted); font-size:12px}
  .print{margin-top:12px}
  @media print {.print{display:none}}
</style>
</head>
<body>

  <!-- Encabezado -->
  <div class="header">
    <div class="logo">
      <?php if ($empresa_logo_url): ?>
        <img src="<?php echo h($empresa_logo_url); ?>" alt="Logo" style="max-width:100%; max-height:100%">
      <?php else: ?>
        LOGO
      <?php endif; ?>
    </div>
    <div class="empresa">
      <h2><?php echo h($empresa_nombre); ?></h2>
      <div class="muted">RUC: <?php echo h($empresa_ruc); ?></div>
      <div class="muted"><?php echo h($empresa_dir); ?></div>
      <div class="muted">Tel: <?php echo h($empresa_tel); ?> • <?php echo h($empresa_email); ?></div>
    </div>
  </div>

  <div class="title">
    <h1>Orden de Compra #<?php echo (int)$cab['id_oc']; ?></h1>
    <div class="badge">
      Fecha emisión: <b><?php echo h($cab['fecha_emision']); ?></b> &nbsp; | &nbsp;
      Estado: <b><?php echo h($cab['estado']); ?></b>
    </div>
  </div>

  <div class="grid">
    <div class="box">
      <h3>Proveedor</h3>
      <div><b><?php echo h($proveedor_linea1); ?></b></div>
      <?php if ($proveedor_ruc): ?><div>RUC: <?php echo h($proveedor_ruc); ?></div><?php endif; ?>
      <?php if ($proveedor_linea2): ?><div><?php echo h($proveedor_linea2); ?></div><?php endif; ?>
      <?php if ($proveedor_linea3): ?><div><?php echo h($proveedor_linea3); ?></div><?php endif; ?>
      <?php if ($proveedor_linea4): ?><div class="muted"><?php echo h($proveedor_linea4); ?></div><?php endif; ?>
    </div>
    <div class="box">
      <h3>Pedido relacionado</h3>
      <div>Pedido #: <b><?php echo (int)$cab['numero_pedido']; ?></b></div>
      <?php if (!empty($cab['observacion'])): ?>
        <div style="margin-top:6px"><span class="muted">Obs:</span> <?php echo nl2br(h($cab['observacion'])); ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Detalle -->
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>ID Producto</th>
        <th>Descripción</th>
        <th class="right">Cantidad</th>
        <th class="right">Precio Unit.</th>
        <th class="right">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($det as $i=>$d):
        $lt = (float)$d['cantidad'] * (float)$d['precio_unit']; ?>
        <tr>
          <td><?php echo $i+1; ?></td>
          <td><?php echo (int)$d['id_producto']; ?></td>
          <td><?php echo h($d['producto']); ?></td>
          <td class="right"><?php echo n0($d['cantidad']); ?></td>
          <td class="right"><?php echo n2($d['precio_unit']); ?></td>
          <td class="right"><?php echo n2($lt); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totales (si luego agregás IVA, acá es buen lugar) -->
  <div class="totals">
    <div class="sum">
      <div class="row"><span>Subtotal</span><span><?php echo n2($total); ?></span></div>
      <!-- <div class="row"><span>IVA (10%)</span><span><?php // echo n2($total*0.10); ?></span></div> -->
      <div class="row total"><span>Total</span><span><?php echo n2($total); ?></span></div>
    </div>
  </div>

  <!-- Firmas y condiciones -->
  <div class="footer">
    <div class="sign">
      <div><b>Condiciones de compra</b></div>
      <div class="muted" style="font-size:12px; margin-top:6px">
        Plazo de entrega, garantía, y condiciones de pago según acuerdo. En caso de discrepancias,
        prevalece esta OC. Indicar número de OC en la factura y remisión.
      </div>
      <div class="line">Firma y sello del proveedor</div>
    </div>
    <div class="sign">
      <div><b>Autorización</b></div>
      <div class="muted" style="font-size:12px; margin-top:6px">
        Aprobado por: ___________________________<br>
        Fecha: ________________________
      </div>
      <div class="line">Firma autorizado por Mi Empresa</div>
    </div>
  </div>

  <div class="print">
    <button onclick="window.print()">Imprimir / Guardar PDF</button>
  </div>

</body>
</html>
