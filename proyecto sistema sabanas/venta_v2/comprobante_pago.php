<?php
include '../conexion/configv2.php';

// Verificar si el ID del pago está presente en la URL
if (!isset($_GET['pago_id']) || !ctype_digit($_GET['pago_id'])) {
    die("ID de pago inválido o no proporcionado.");
}

$pago_id = $_GET['pago_id'];

// Obtener los detalles del pago desde la base de datos
$query_pago = "
    SELECT 
        p.id AS pago_id, 
        p.cuenta_id, 
        p.monto_pago, 
        p.fecha_pago, 
        p.forma_pago, 
        cl.ruc_ci,  -- Obtener el RUC/CI del cliente
        c.monto AS monto_total, 
        c.estado AS estado_cuota
    FROM pagos p
    JOIN cuentas_por_cobrar c ON p.cuenta_id = c.id
    JOIN ventas v ON c.venta_id = v.id  -- Relacionar con la tabla ventas
    JOIN clientes cl ON v.cliente_id = cl.id_cliente  -- Relacionar con la tabla clientes
    WHERE p.id = $1;
";

$result_pago = pg_query_params($conn, $query_pago, array($pago_id));

if (!$result_pago || pg_num_rows($result_pago) == 0) {
    die("No se encontraron datos para el ID de pago proporcionado.");
}

$detalle_pago = pg_fetch_assoc($result_pago);

// Cerrar conexión
pg_close($conn);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Pago</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 0;
            background-color: #f9f9f9;
        }

        .comprobante-container {
            max-width: 600px;
            margin: auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        }

        .comprobante-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .comprobante-header h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }

        .comprobante-details {
            margin-bottom: 20px;
        }

        .comprobante-details p {
            margin: 5px 0;
            font-size: 16px;
        }

        .comprobante-footer {
            text-align: center;
            margin-top: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            font-size: 14px;
            text-decoration: none;
            color: #fff;
            background-color: #007bff;
            border-radius: 4px;
            margin-top: 10px;
        }

        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <div class="comprobante-container">
        <div class="comprobante-header">
            <h1>Comprobante de Pago</h1>
            <p>Número de Comprobante: <strong>#<?= htmlspecialchars($detalle_pago['pago_id']) ?></strong></p>
        </div>
        <div class="comprobante-details">
            <p><strong>RUC/CI:</strong> <?= htmlspecialchars($detalle_pago['ruc_ci']) ?></p>
            <p><strong>Monto Total:</strong> <?= number_format($detalle_pago['monto_total'], 2) ?> Gs</p>
            <p><strong>Monto Pagado:</strong> <?= number_format($detalle_pago['monto_pago'], 2) ?> Gs</p>
            <p><strong>Saldo Restante:</strong> <?= number_format($detalle_pago['monto_total'] - $detalle_pago['monto_pago'], 2) ?> Gs</p>
            <p><strong>Estado:</strong> <?= htmlspecialchars(ucfirst($detalle_pago['estado_cuota'])) ?></p>
            <p><strong>Forma de Pago:</strong> <?= htmlspecialchars($detalle_pago['forma_pago']) ?></p>
            <p><strong>Fecha de Pago:</strong> <?= htmlspecialchars($detalle_pago['fecha_pago']) ?></p>
        </div>
        <div class="comprobante-footer">
            <p>Gracias por su pago.</p>
            <a href="javascript:window.print()" class="btn">Imprimir Comprobante</a>
            <a href="/TALLER DE ANALISIS Y PROGRAMACIÓN I//proyecto sistema sabanas//venta_v2//ui_consulta_cuotas.php" class="btn">Volver al Inicio</a>
        </div>
    </div>
</body>

</html>
