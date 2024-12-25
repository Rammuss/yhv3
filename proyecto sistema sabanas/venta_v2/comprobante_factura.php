<?php
// Conexión a la base de datos
include '../conexion/configv2.php';


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
    v.estado AS estado,
    v.cuotas AS cuotas,
    v.timbrado AS timbrado,
    dv.producto_id AS producto_id,
    p.nombre AS producto,
    dv.cantidad AS cantidad,
    dv.precio_unitario AS precio_unitario,
    (dv.cantidad * dv.precio_unitario) AS total_producto
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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Comprobante de Factura</title>
    <script type="text/javascript">
        function imprimir() {
            window.print();
        }

        function volver() {
            window.location.href = '../venta_v2/ui_facturacion.php';        }
    </script>
</head>
<body>
    <h1>Comprobante de Factura</h1>
    <?php if (!empty($comprobante)): ?>
        <?php $factura = $comprobante[0]; ?>
        <p><strong>Número de Factura:</strong> <?= htmlspecialchars($factura['numero_factura']) ?></p>
        <p><strong>Fecha:</strong> <?= htmlspecialchars($factura['fecha']) ?></p>
        <p><strong>Cliente:</strong> <?= htmlspecialchars($factura['cliente']) ?></p>
        <p><strong>Dirección:</strong> <?= htmlspecialchars($factura['direccion']) ?></p>
        <p><strong>Teléfono:</strong> <?= htmlspecialchars($factura['telefono']) ?></p>
        <p><strong>RUC/CI:</strong> <?= htmlspecialchars($factura['ruc_ci']) ?></p>
        <p><strong>Forma de Pago:</strong> <?= htmlspecialchars($factura['forma_pago']) ?></p>
        <p><strong>Estado:</strong> <?= htmlspecialchars($factura['estado']) ?></p>
        <p><strong>Cuotas:</strong> <?= htmlspecialchars($factura['cuotas']) ?></p>
        <p><strong>Timbrado:</strong> <?= htmlspecialchars($factura['timbrado']) ?></p>

        <h2>Detalles de la Venta</h2>
        <table border="1">
            <tr>
                <th>ID Producto</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio Unitario</th>
                <th>Total Producto</th>
            </tr>
            <?php foreach ($comprobante as $detalle): ?>
                <tr>
                    <td><?= htmlspecialchars($detalle['producto_id']) ?></td>
                    <td><?= htmlspecialchars($detalle['producto']) ?></td>
                    <td><?= htmlspecialchars($detalle['cantidad']) ?></td>
                    <td><?= htmlspecialchars($detalle['precio_unitario']) ?></td>
                    <td><?= htmlspecialchars($detalle['total_producto']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <!-- Botones para Imprimir y Volver -->
        <button onclick="imprimir()">Imprimir</button>
        <button onclick="volver()">Volver</button>
        
    <?php else: ?>
        <p>No se encontraron datos para la venta ID <?= htmlspecialchars($venta_id) ?></p>
    <?php endif; ?>

    <?php pg_close($conn); ?>
</body>
</html>
