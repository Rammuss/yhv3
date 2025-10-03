<?php
// pedido_ver.php
session_start();
require_once __DIR__.'/../../conexion/configv2.php';

if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}

$id_pedido = (int)($_GET['id'] ?? 0);
if ($id_pedido <= 0) {
  echo "ID inválido"; exit;
}

function normalizar_tipo_iva($tipo){
  $tipo = strtoupper(trim((string)$tipo));
  if ($tipo === 'IVA10' || $tipo === 'IVA 10' || $tipo === '10%' || $tipo === '10' || strpos($tipo,'10')!==false) return 'IVA10';
  if ($tipo === 'IVA5'  || $tipo === 'IVA 5'  || $tipo === '5%'  || $tipo === '5'  || strpos($tipo,'5')!==false)  return 'IVA5';
  return 'EXE';
}
function etiqueta_iva($tipo){
  $tipo = normalizar_tipo_iva($tipo);
  if ($tipo==='IVA10') return '10%';
  if ($tipo==='IVA5')  return '5%';
  return 'Exento';
}
function tasa_iva($tipo){
  $tipo = normalizar_tipo_iva($tipo);
  if ($tipo==='IVA10') return 0.10;
  if ($tipo==='IVA5')  return 0.05;
  return 0.0;
}

// --- Cabecera del pedido ---
$sqlCab = "
  SELECT pc.*, 
         (c.nombre||' '||c.apellido) AS cliente,
         c.ruc_ci, c.direccion, c.telefono
  FROM public.pedido_cab pc
  JOIN public.clientes c ON c.id_cliente = pc.id_cliente
  WHERE pc.id_pedido = $1
";
$resCab = pg_query_params($conn, $sqlCab, [$id_pedido]);
$cab = $resCab && pg_num_rows($resCab) ? pg_fetch_assoc($resCab) : null;
if (!$cab) {
  echo "Pedido no encontrado"; exit;
}

// --- Detalle del pedido ---
$sqlDet = "
  SELECT d.*, p.nombre AS producto
  FROM public.pedido_det d
  JOIN public.producto p ON p.id_producto = d.id_producto
  WHERE d.id_pedido = $1
  ORDER BY d.id_pedido_det
";
$resDet = pg_query_params($conn, $sqlDet, [$id_pedido]);
$detallesDb = $resDet ? pg_fetch_all($resDet) : [];

$detalles = [];
$sumBase = 0.0;
$sumDesc = 0.0;
$sumIva  = 0.0;
$sumTotal = 0.0;

foreach ($detallesDb as $d) {
  $cantidad = (float)$d['cantidad'];
  $precio   = (float)$d['precio_unitario'];  // precio con IVA
  $descuento= (float)$d['descuento'];
  $tipoIva  = normalizar_tipo_iva($d['tipo_iva']);
  $rate     = tasa_iva($tipoIva);

  $importeSinDesc = $cantidad * $precio;
  $descuento      = min($descuento, $importeSinDesc);
  $importeFinal   = max(0.0, $importeSinDesc - $descuento); // total con IVA

  if ($rate > 0) {
    $base = round($importeFinal / (1 + $rate), 2);
    $ivaMonto = round($importeFinal - $base, 2);
  } else {
    $base = round($importeFinal, 2);
    $ivaMonto = 0.0;
  }

  $detalles[] = [
    'producto'   => $d['producto'],
    'cantidad'   => $cantidad,
    'precio'     => $precio,
    'descuento'  => $descuento,
    'iva_label'  => etiqueta_iva($tipoIva),
    'subtotal'   => $importeFinal,
    'iva_monto'  => $ivaMonto,
  ];

  $sumBase  += $base;
  $sumDesc  += $descuento;
  $sumIva   += $ivaMonto;
  $sumTotal += $importeFinal;
}

$sumBase  = round($sumBase, 2);
$sumDesc  = round($sumDesc, 2);
$sumIva   = round($sumIva, 2);
$sumTotal = round($sumTotal, 2);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Pedido #<?= htmlspecialchars($id_pedido) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:Arial,sans-serif;margin:20px;background:#f9fafb;color:#111}
  .doc{max-width:900px;margin:auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
  h1{margin:0 0 10px}
  .muted{color:#6b7280;font-size:.9rem}
  .row{display:flex;justify-content:space-between;flex-wrap:wrap}
  table{width:100%;border-collapse:collapse;margin-top:16px}
  th,td{border:1px solid #e5e7eb;padding:8px;font-size:.9rem;text-align:right}
  th{background:#f3f4f6}
  td:first-child,th:first-child{text-align:left}
  .totals{margin-top:16px;display:flex;justify-content:flex-end}
  .totals table{width:auto}
  .badge{display:inline-block;padding:3px 8px;border-radius:6px;font-size:.8rem}
  .b-pend{background:#eef2ff;color:#1e40af}
  .b-fact{background:#ecfdf5;color:#065f46}
  .b-anul{background:#fef2f2;color:#991b1b}
  .right{text-align:right}
  .btn{display:inline-block;margin-top:16px;padding:10px 14px;background:#2563eb;color:#fff;border-radius:8px;text-decoration:none}
</style>
</head>
<body>
<div class="doc">
  <h1>Pedido #<?= htmlspecialchars($cab['id_pedido']) ?></h1>
  <div class="row muted">
    <div>
      <div><b>Cliente:</b> <?= htmlspecialchars($cab['cliente']) ?></div>
      <div><b>CI/RUC:</b> <?= htmlspecialchars($cab['ruc_ci']) ?></div>
      <div><b>Dirección:</b> <?= htmlspecialchars($cab['direccion']) ?></div>
      <div><b>Tel:</b> <?= htmlspecialchars($cab['telefono']) ?></div>
    </div>
    <div style="text-align:right">
      <div><b>Fecha:</b> <?= htmlspecialchars(substr($cab['fecha_pedido'],0,10)) ?></div>
      <div><b>Estado:</b>
        <?php
          $st = strtolower($cab['estado']);
          $cls = $st==='facturado'?'b-fact':($st==='anulado'?'b-anul':'b-pend');
          echo "<span class='badge $cls'>".ucfirst($st)."</span>";
        ?>
      </div>
      <div><b>Creado por:</b> <?= htmlspecialchars($cab['creado_por']) ?></div>
    </div>
  </div>

  <?php if (!empty($cab['observacion'])): ?>
    <p class="muted"><b>Observación:</b> <?= htmlspecialchars($cab['observacion']) ?></p>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th>Producto</th>
        <th>Cant.</th>
        <th>Precio (c/IVA)</th>
        <th>Desc.</th>
        <th>IVA %</th>
        <th>Subtotal (c/IVA)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($detalles as $d): ?>
        <tr>
          <td><?= htmlspecialchars($d['producto']) ?></td>
          <td><?= rtrim(rtrim(number_format($d['cantidad'],2,'.',''),'0'),'.') ?></td>
          <td><?= number_format($d['precio'],2,',','.') ?></td>
          <td><?= number_format($d['descuento'],2,',','.') ?></td>
          <td><?= htmlspecialchars($d['iva_label']) ?></td>
          <td><?= number_format($d['subtotal'],2,',','.') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totals">
    <table>
      <tr><th>Total Base (sin IVA)</th><td><?= number_format($sumBase,2,',','.') ?></td></tr>
      <tr><th>Descuentos</th><td><?= number_format($sumDesc,2,',','.') ?></td></tr>
      <tr><th>IVA</th><td><?= number_format($sumIva,2,',','.') ?></td></tr>
      <tr><th>Total Neto</th><td><b><?= number_format($sumTotal,2,',','.') ?></b></td></tr>
    </table>
  </div>

  <div class="right">
    <a class="btn" href="#" onclick="window.print();return false;">Imprimir</a>
  </div>
</div>
</body>
</html>
