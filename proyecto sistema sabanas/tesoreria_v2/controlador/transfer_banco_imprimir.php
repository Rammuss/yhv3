<?php
include "../../conexion/configv2.php"; // Asegúrate de que la ruta sea correcta

// Verificar si se recibió un ID de pago válido
if (!isset($_GET['id_pago']) || empty($_GET['id_pago'])) {
    die("<script>alert('ID de pago no proporcionado'); window.history.back();</script>");
}

$id_pago = intval($_GET['id_pago']);

// Consulta para obtener los detalles del pago, la cuenta bancaria y el nombre del proveedor
$query = "
    SELECT 
        pe.id_orden_pago, 
        pe.monto, 
        pe.fecha_ejecucion, 
        pe.referencia_bancaria, 
        cb.nombre_banco, 
        pe.estado_conciliacion,
        pr.nombre AS nombre_proveedor
    FROM pagos_ejecutados pe
    LEFT JOIN cuentas_bancarias cb ON pe.id_cuenta_bancaria = cb.id_cuenta_bancaria
    LEFT JOIN ordenes_pago op ON pe.id_orden_pago = op.id_orden_pago
    LEFT JOIN proveedores pr ON op.id_proveedor = pr.id_proveedor
    WHERE pe.id_pago = $id_pago
    LIMIT 1
";
$result = pg_query($conn, $query);

if (!$result || pg_num_rows($result) == 0) {
    die("<script>alert('Pago no encontrado'); window.history.back();</script>");
}

$row = pg_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comprobante de Pago</title>
  <!-- Incluir Bulma CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
  <style>
    .container { margin-top: 20px; }
    .box p { font-size: 18px; }
  </style>
</head>
<body>
  <section class="section">
    <div class="container">
      <h1 class="title has-text-centered">Comprobante de Pago</h1>
      <div class="columns">
        <!-- Columna izquierda: Detalles del pago -->
        <div class="column is-half">
          <div class="box">
            <p><strong>Orden de Pago:</strong> <?php echo htmlspecialchars($row['id_orden_pago']); ?></p>
            <?php if(!empty($row['nombre_proveedor'])) { ?>
              <p><strong>Proveedor:</strong> <?php echo htmlspecialchars($row['nombre_proveedor']); ?></p>
            <?php } ?>
            <p><strong>Monto:</strong> <?php echo number_format($row['monto'], 2); ?> Gs</p>
            <p><strong>Fecha de Ejecución:</strong> <?php echo htmlspecialchars($row['fecha_ejecucion']); ?></p>
            <p><strong>Referencia Bancaria:</strong> <?php echo htmlspecialchars($row['referencia_bancaria']); ?></p>
            <?php if(!empty($row['nombre_banco'])) { ?>
              <p><strong>Banco:</strong> <?php echo htmlspecialchars($row['nombre_banco']); ?></p>
            <?php } ?>
          </div>
        </div>
        <!-- Columna derecha: Datos de la empresa -->
        <div class="column is-half">
          <div class="box">
            <p class="has-text-right"><strong>Empresa SA</strong></p>
            <p class="has-text-right">RUC: 1234567890</p>
            <p class="has-text-right">Teléfono: (123) 456-7890</p>
            <p class="has-text-right">Dirección: Av. Siempre Viva 123, Ciudad</p>
          </div>
        </div>
      </div>
      <div class="buttons is-centered">
        <button class="button is-success" onclick="window.print()">Imprimir</button>
        <button class="button is-danger" onclick="window.history.back()">Volver</button>
      </div>
    </div>
  </section>
</body>
</html>
