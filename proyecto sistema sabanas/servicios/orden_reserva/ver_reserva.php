<?php
// reservas/ver_reserva.php
session_start();
if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}

require_once __DIR__ . '../../../conexion/configv2.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo 'ID inválido';
  exit;
}

$sqlCab = "
  SELECT r.id_reserva,
         r.fecha_reserva,
         r.inicio_ts,
         r.fin_ts,
         r.estado,
         r.notas,
         r.id_cliente,
         c.nombre AS cliente_nombre,
         c.apellido AS cliente_apellido,
         c.ruc_ci,
         c.telefono,
         c.direccion,
         r.id_profesional,
         pr.nombre AS profesional_nombre
    FROM public.reserva_cab r
    JOIN public.clientes c ON c.id_cliente = r.id_cliente
    LEFT JOIN public.profesional pr ON pr.id_profesional = r.id_profesional
   WHERE r.id_reserva = $1
   LIMIT 1
";
$cab = pg_query_params($conn, $sqlCab, [$id]);
if (!$cab || pg_num_rows($cab) === 0) {
  http_response_code(404);
  echo 'Reserva no encontrada';
  exit;
}
$reserva = pg_fetch_assoc($cab);

$sqlDet = "
  SELECT item_nro,
         id_producto,
         descripcion,
         cantidad,
         precio_unitario,
         COALESCE(tipo_iva,'EXE') AS tipo_iva,
         duracion_min
    FROM public.reserva_det
   WHERE id_reserva = $1
   ORDER BY item_nro
";
$det = pg_query_params($conn, $sqlDet, [$id]);
$items = $det ? pg_fetch_all($det) : [];

function fmt($n) {
  return number_format((float)$n, 0, ',', '.');
}
function fmtDate($ts) {
  return $ts ? date('d/m/Y H:i', strtotime($ts)) : '-';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reserva #<?= htmlspecialchars($reserva['id_reserva']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{margin:0;padding:24px;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto;background:#f1f5f9;color:#0f172a;}
  .wrap{max-width:900px;margin:auto;background:#fff;border-radius:16px;box-shadow:0 16px 40px rgba(15,23,42,.12);padding:24px;}
  h1{margin:0 0 16px;}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:20px;}
  .box{background:#f8fafc;border:1px solid #dbeafe;border-radius:12px;padding:14px;}
  .box h3{margin:0 0 8px;font-size:14px;text-transform:uppercase;color:#475569;}
  table{width:100%;border-collapse:collapse;margin-top:12px;}
  th,td{padding:10px;border-bottom:1px solid #e2e8f0;text-align:left;font-size:13px;}
  th{background:#eff6ff;text-transform:uppercase;font-size:12px;color:#1d4ed8;}
  .actions{display:flex;gap:12px;margin-top:20px;}
  button{padding:10px 14px;border:none;border-radius:10px;background:#2563eb;color:#fff;font-weight:600;cursor:pointer;}
  button:hover{background:#1d4ed8;}
  .muted{color:#64748b;font-size:13px;}
</style>
</head>
<body>
<div class="wrap">
  <h1>Reserva #<?= htmlspecialchars($reserva['id_reserva']) ?></h1>

  <div class="grid">
    <div class="box">
      <h3>Datos del cliente</h3>
      <div><strong><?= htmlspecialchars($reserva['cliente_nombre'].' '.$reserva['cliente_apellido']) ?></strong></div>
      <div class="muted">CI/RUC: <?= htmlspecialchars($reserva['ruc_ci'] ?? '-') ?></div>
      <div class="muted">Teléfono: <?= htmlspecialchars($reserva['telefono'] ?? '-') ?></div>
      <div class="muted">Dirección: <?= htmlspecialchars($reserva['direccion'] ?? '-') ?></div>
    </div>
    <div class="box">
      <h3>Profesional</h3>
      <div><?= $reserva['profesional_nombre'] ? htmlspecialchars($reserva['profesional_nombre']) : 'Sin asignar' ?></div>
    </div>
    <div class="box">
      <h3>Agenda</h3>
      <div>Fecha: <?= date('d/m/Y', strtotime($reserva['fecha_reserva'])) ?></div>
      <div>Inicio: <?= substr($reserva['inicio_ts'], 11, 5) ?></div>
      <div>Fin: <?= substr($reserva['fin_ts'], 11, 5) ?></div>
      <div class="muted">Estado: <?= htmlspecialchars($reserva['estado']) ?></div>
    </div>
    <?php if (!empty($reserva['notas'])): ?>
    <div class="box">
      <h3>Notas</h3>
      <div><?= nl2br(htmlspecialchars($reserva['notas'])) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <h2 style="margin-top:12px;">Servicios reservados</h2>
  <?php if (empty($items)): ?>
    <div class="muted">Esta reserva no tiene servicios asociados.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Producto</th>
          <th>Cantidad</th>
          <th>Duración (min)</th>
          <th>Precio</th>
          <th>IVA</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= (int)$it['item_nro'] ?></td>
            <td><?= htmlspecialchars($it['descripcion']) ?></td>
            <td><?= htmlspecialchars($it['cantidad']) ?></td>
            <td><?= (int)$it['duracion_min'] ?></td>
            <td><?= fmt($it['precio_unitario']) ?></td>
            <td><?= htmlspecialchars($it['tipo_iva'] ?? 'EXE') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="actions">
    <button type="button"
        onclick="window.location.href = '/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/orden_reserva/ui_reserva.php'">
  Volver
</button>

  </div>
</div>
</body>
</html>
