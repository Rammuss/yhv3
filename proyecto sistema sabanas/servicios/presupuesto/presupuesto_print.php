<?php
// servicios/presupuesto/presupuesto_print.php
session_start();
if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}

require_once __DIR__ . '/../../conexion/configv2.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo 'ID inválido';
  exit;
}

$sqlCab = "
  SELECT p.*,
         c.nombre, c.apellido, c.ruc_ci, c.telefono, c.direccion
    FROM public.serv_presupuesto_cab p
    JOIN public.clientes c ON c.id_cliente = p.id_cliente
   WHERE p.id_presupuesto = $1
   LIMIT 1
";
$cab = pg_query_params($conn, $sqlCab, [$id]);
if (!$cab || pg_num_rows($cab) === 0) {
  http_response_code(404);
  echo 'Presupuesto no encontrado';
  exit;
}
$pres = pg_fetch_assoc($cab);

$sqlDet = "
  SELECT descripcion,
         tipo_item,
         cantidad,
         precio_unitario,
         descuento,
         tipo_iva,
         iva_monto,
         subtotal_neto
    FROM public.serv_presupuesto_det
   WHERE id_presupuesto = $1
   ORDER BY id_presupuesto_det
";
$det = pg_query_params($conn, $sqlDet, [$id]);
$items = $det ? pg_fetch_all($det) : [];

function fmt($n){
  return number_format((float)$n, 0, ',', '.');
}
function fmtDate($d){
  return $d ? date('d/m/Y', strtotime($d)) : '-';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Presupuesto #<?= htmlspecialchars($pres['id_presupuesto']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    --text:#111827; --muted:#64748b; --accent:#1d4ed8;
  }
  body{margin:0;font:15px/1.45 "Source Sans Pro",system-ui,-apple-system,Segoe UI,Roboto;color:var(--text);background:#fff;}
  .sheet{max-width:960px;margin:0 auto;padding:32px;}
  h1{margin:0 0 20px;font-size:26px;}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-bottom:18px;}
  .box{border:1px solid #e2e8f0;border-radius:12px;padding:14px;background:#f8fafc;}
  .box h3{margin:0 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
  table{width:100%;border-collapse:collapse;margin-top:14px;}
  th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:13px;}
  th{background:#eff6ff;text-transform:uppercase;font-size:12px;color:var(--accent);}
  .totals{display:flex;gap:24px;justify-content:flex-end;margin-top:14px;font-size:14px;}
  .totals div{min-width:150px;}
  .muted{color:var(--muted);font-size:13px;}
  .badge{display:inline-block;padding:4px 10px;border-radius:999px;font-weight:600;font-size:12px;margin-left:8px;}
  .Borrador{background:#fef9c3;color:#854d0e;}
  .Enviado{background:#dbeafe;color:#1d4ed8;}
  .Aprobado{background:#dcfce7;color:#166534;}
  .Rechazado{background:#fee2e2;color:#b91c1c;}
  .Vencido{background:#e2e8f0;color:#475569;}
  @media print{
    body{background:#fff;}
    .sheet{box-shadow:none;margin:0;padding:24px;}
    .no-print{display:none!important;}
  }
</style>
</head>
<body>
<div class="sheet">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <div>
      <h1>Presupuesto #<?= htmlspecialchars($pres['id_presupuesto']) ?></h1>
      <div class="muted">Generado el <?= fmtDate($pres['fecha_presupuesto']) ?> por <?= htmlspecialchars($_SESSION['nombre_usuario'] ?? '') ?></div>
    </div>
    <div>
      <strong>Estado:</strong>
      <span class="badge <?= htmlspecialchars($pres['estado']) ?>"><?= htmlspecialchars($pres['estado']) ?></span>
    </div>
  </div>

  <div class="grid">
    <div class="box">
      <h3>Cliente</h3>
      <div><strong><?= htmlspecialchars(($pres['nombre'] ?? '').' '.($pres['apellido'] ?? '')) ?></strong></div>
      <div class="muted">CI/RUC: <?= htmlspecialchars($pres['ruc_ci'] ?? '-') ?></div>
      <div class="muted">Teléfono: <?= htmlspecialchars($pres['telefono'] ?? '-') ?></div>
      <div class="muted">Dirección: <?= htmlspecialchars($pres['direccion'] ?? '-') ?></div>
    </div>
    <div class="box">
      <h3>Fechas</h3>
      <div>Fecha del presupuesto: <?= fmtDate($pres['fecha_presupuesto']) ?></div>
      <div>Validez hasta: <?= fmtDate($pres['validez_hasta']) ?></div>
      <?php if (!empty($pres['id_reserva'])): ?>
        <div>Reserva origen: #<?= (int)$pres['id_reserva'] ?></div>
      <?php endif; ?>
    </div>
    <?php if (!empty($pres['notas'])): ?>
    <div class="box">
      <h3>Notas</h3>
      <div><?= nl2br(htmlspecialchars($pres['notas'])) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <h2 style="margin:16px 0 8px 0;font-size:18px;">Detalle</h2>
  <?php if (empty($items)): ?>
    <p class="muted">Este presupuesto no tiene ítems cargados.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Descripción</th>
          <th>Tipo</th>
          <th>Cant.</th>
          <th>Precio c/IVA</th>
          <th>Descuento</th>
          <th>IVA</th>
          <th>Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $idx => $it): ?>
          <tr>
            <td><?= $idx + 1 ?></td>
            <td><?= htmlspecialchars($it['descripcion']) ?></td>
            <td><?= htmlspecialchars($it['tipo_item']) ?></td>
            <td><?= (float)$it['cantidad'] ?></td>
            <td><?= fmt($it['precio_unitario']) ?></td>
            <td><?= fmt($it['descuento']) ?></td>
            <td><?= htmlspecialchars($it['tipo_iva']) ?></td>
            <td><?= fmt($it['subtotal_neto']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="totals">
    <div>
      <div><strong>Gravadas 10%:</strong> Gs <?= fmt($pres['total_bruto'] - $pres['total_iva'] - $pres['total_descuento']) ?></div>
      <div><strong>IVA 10%:</strong> Gs <?= fmt($pres['total_iva']) ?></div>
    </div>
    <div>
      <div><strong>Descuentos:</strong> Gs <?= fmt($pres['total_descuento']) ?></div>
      <div><strong>Total neto:</strong> Gs <?= fmt($pres['total_neto']) ?></div>
    </div>
  </div>

  <p class="muted" style="margin-top:20px;">
    Documento generado automáticamente por el sistema. Usuario: <?= htmlspecialchars($_SESSION['nombre_usuario'] ?? '') ?>.
  </p>

  <button class="no-print" style="padding:10px 16px;border:none;border-radius:10px;background:#2563eb;color:#fff;font-weight:600;cursor:pointer;margin-top:12px;" onclick="window.print()">Imprimir</button>

</div>
</body>
</html>
