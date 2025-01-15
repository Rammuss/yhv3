<?php
// Conexión a la base de datos
include '../conexion/configv2.php';

// Datos de la empresa ficticia
$empresa = [
    'nombre' => 'Empresa Ficticia S.A.',
    'direccion' => 'Av. Principal 123, Ciudad, País',
    'telefono' => '+595 1234 5678',
    'email' => 'contacto@empresaficticia.com'
];

// Obtener el venta_id desde la URL
$venta_id = $_GET['venta_id'];

// Consulta SQL para obtener los datos del comprobante
$sql = "
SELECT
    v.numero_factura AS numero_factura,
    v.fecha AS fecha,
    c.nombre || ' ' || c.apellido AS cliente,
    c.direccion AS direccion,
    c.telefono AS telefono,
    c.ruc_ci AS ruc_ci,
    v.forma_pago AS forma_pago,
    v.metodo_pago AS metodo_pago, -- Método de pago agregado aquí
    v.estado AS estado,
    v.cuotas AS cuotas,
    v.timbrado AS timbrado,
    dv.producto_id AS producto_id,
    p.nombre AS producto,
    dv.cantidad AS cantidad,
    dv.precio_unitario AS precio_unitario,
    (dv.cantidad * dv.precio_unitario) AS total_producto,
    v.nota_credito_id AS nota_credito_id,
    v.monto_nc_aplicado AS monto_nc_aplicado
FROM
    ventas v
JOIN
    clientes c ON v.cliente_id = c.id_cliente
JOIN
    detalle_venta dv ON v.id = dv.venta_id
JOIN
    producto p ON dv.producto_id = p.id_producto
WHERE
    v.id = $1";

$result = pg_query_params($conn, $sql, array($venta_id));

if (!$result) {
    echo 'Error en la consulta';
    exit;
}

$comprobante = pg_fetch_all($result);

$total_factura = 0;
foreach ($comprobante as $detalle) {
    $total_factura += $detalle['total_producto'];
}

// Calcular el total con la nota de crédito aplicada
$monto_nc_aplicado = $comprobante[0]['monto_nc_aplicado'];
if ($monto_nc_aplicado > $total_factura) {
    $monto_nc_aplicado = $total_factura;
}
$total_con_nc = $total_factura - $monto_nc_aplicado;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Factura</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css">
    <style>
        body {
            padding: 20px;
        }
        .factura {
            margin: 20px 0;
        }
    </style>
    <script type="text/javascript">
        function imprimir() {
            window.print();
        }

        function volver() {
            window.location.href = '../venta_v2/ui_facturacion.php';
        }
    </script>
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Factura</h1>
            <?php if (!empty($comprobante)): ?>
                <?php $factura = $comprobante[0]; ?>
                <div class="columns">
                    <div class="column is-half">
                        <div class="box">
                            <p><strong>Número de Factura:</strong> <?= htmlspecialchars($factura['numero_factura']) ?></p>
                            <p><strong>Fecha:</strong> <?= htmlspecialchars($factura['fecha']) ?></p>
                            <p><strong>Cliente:</strong> <?= htmlspecialchars($factura['cliente']) ?></p>
                            <p><strong>Dirección:</strong> <?= htmlspecialchars($factura['direccion']) ?></p>
                            <p><strong>Teléfono:</strong> <?= htmlspecialchars($factura['telefono']) ?></p>
                            <p><strong>RUC/CI:</strong> <?= htmlspecialchars($factura['ruc_ci']) ?></p>
                            <p><strong>Forma de Pago:</strong> <?= htmlspecialchars($factura['forma_pago']) ?></p>
                            <p><strong>Método de Pago:</strong> <?= htmlspecialchars($factura['metodo_pago']) ?></p> <!-- Nuevo campo -->
                            <p><strong>Estado:</strong> <?= htmlspecialchars($factura['estado']) ?></p>
                            <p><strong>Cuotas:</strong> <?= htmlspecialchars($factura['cuotas']) ?></p>
                            <p><strong>Timbrado:</strong> <?= htmlspecialchars($factura['timbrado']) ?></p>
                        </div>
                    </div>
                    <div class="column is-half">
                        <div class="box">
                            <p><strong>Empresa:</strong> <?= htmlspecialchars($empresa['nombre']) ?></p>
                            <p><strong>Dirección:</strong> <?= htmlspecialchars($empresa['direccion']) ?></p>
                            <p><strong>Teléfono:</strong> <?= htmlspecialchars($empresa['telefono']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($empresa['email']) ?></p>
                        </div>
                    </div>
                </div>
                
                <h2 class="subtitle">Detalles de la Venta</h2>
                <table class="table is-fullwidth is-striped">
                    <thead>
                        <tr>
                            <th>ID Producto</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Total Producto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comprobante as $detalle): ?>
                            <tr>
                                <td><?= htmlspecialchars($detalle['producto_id']) ?></td>
                                <td><?= htmlspecialchars($detalle['producto']) ?></td>
                                <td><?= htmlspecialchars($detalle['cantidad']) ?></td>
                                <td><?= htmlspecialchars($detalle['precio_unitario']) ?></td>
                                <td><?= htmlspecialchars($detalle['total_producto']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4">Total</th>
                            <th><?= htmlspecialchars($total_factura) ?></th>
                        </tr>
                    </tfoot>
                </table>
                
                <?php if ($factura['nota_credito_id']) { ?>
                    <div class="box factura">
                        <p><strong>Número de Nota de Crédito:</strong> <?= htmlspecialchars($factura['nota_credito_id']) ?></p>
                        <p><strong>Monto de Nota de Crédito:</strong> <?= htmlspecialchars($monto_nc_aplicado) ?></p>
                        <p><strong>Total con Nota de Crédito:</strong> <?= htmlspecialchars($total_con_nc) ?></p>
                    </div>
                <?php } ?>
                
                <!-- Botones para Imprimir y Volver -->
                <div class="buttons">
                    <button class="button is-link" onclick="imprimir()">Imprimir</button>
                    <button class="button is-primary" onclick="volver()">Volver</button>
                </div>
                
            <?php else: ?>
                <p>No se encontraron datos para la venta ID <?= htmlspecialchars($venta_id) ?></p>
            <?php endif; ?>
        </div>
    </section>
    <?php pg_close($conn); ?>
</body>
</html>
