<?php
// Conexión a la base de datos
include('../conexion/configv2.php');

// Verificar si se recibió el id de la nota
if (!isset($_GET['nota_id'])) {
    die("ID de nota no especificado.");
}

$nota_id = intval($_GET['nota_id']);

// Consultar datos de la nota
$query = "
    SELECT 
        n.id, 
        n.cliente_id, 
        n.tipo, 
        n.fecha, 
        n.estado, 
        n.monto, 
        n.motivo, 
        n.venta_id, 
        c.nombre AS cliente_nombre, 
        v.fecha AS fecha_venta,
        v.numero_factura  -- Agregamos el campo numero_factura
    FROM 
        notas_credito_debito n
    LEFT JOIN 
        clientes c ON n.cliente_id = c.id_cliente
    LEFT JOIN 
        ventas v ON n.venta_id = v.id
    WHERE 
        n.id = $1
";
$result = pg_query_params($conn, $query, [$nota_id]);

if (!$result || pg_num_rows($result) === 0) {
    die("No se encontraron datos para la nota especificada.");
}

$nota = pg_fetch_assoc($result);

// Consultar detalles de la nota solo si es de tipo crédito
$detalles = [];
if ($nota['tipo'] == 'credito') {
    $queryDetalles = "
        SELECT 
            d.id, 
            d.producto_id, 
            p.nombre AS producto_nombre, 
            d.cantidad, 
            d.precio_unitario
        FROM 
            detalle_notas_credito_debito d
        LEFT JOIN 
            producto p ON d.producto_id = p.id_producto
        WHERE 
            d.nota_id = $1
    ";
    $resultDetalles = pg_query_params($conn, $queryDetalles, [$nota_id]);

    if ($resultDetalles && pg_num_rows($resultDetalles) > 0) {
        while ($row = pg_fetch_assoc($resultDetalles)) {
            // Calcular el monto
            $row['monto'] = $row['cantidad'] * $row['precio_unitario'];
            $detalles[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota #<?php echo $nota_id; ?></title>
    <!-- Agregar el enlace a Bulma -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.9.3/css/bulma.min.css">
</head>

<body>
    <div class="container">
        <section class="section">
            <h1 class="title is-2">Nota de Crédito/Débito #<?php echo $nota_id; ?></h1>

            <!-- Cabecera de la empresa -->
            <div class="columns">
                <div class="column is-half">
                    <div class="box">
                        <p><strong>Empresa XYZ</strong></p>
                        <p>Calle Ficticia 123, Ciudad</p>
                        <p>Tel: (123) 456-7890</p>
                        <p>RUC: 12345678901</p>
                    </div>
                </div>
                <div class="column is-half">
                    <div class="box">
                        <p><strong>Cliente:</strong> <?php echo htmlspecialchars($nota['cliente_nombre']); ?></p>
                        <p><strong>Monto Total:</strong> <?php echo number_format($nota['monto'], 2); ?></p>
                        <p><strong>Motivo:</strong> <?php echo htmlspecialchars($nota['motivo']); ?></p>
                        <p><strong>Fecha de la Nota:</strong> <?php echo date('d/m/Y H:i:s', strtotime($nota['fecha'])); ?></p>
                        <p><strong>Estado:</strong> <?php echo htmlspecialchars($nota['estado']); ?></p>
                        <p><strong>Tipo:</strong> <?php echo htmlspecialchars($nota['tipo']); ?></p>
                        <p><strong>Fecha de la Venta:</strong> <?php echo date('d/m/Y', strtotime($nota['fecha_venta'])); ?></p>
                        <p><strong>Número de Factura:</strong> <?php echo htmlspecialchars($nota['numero_factura']); ?></p> <!-- Mostrar el numero_factura -->
                    </div>
                </div>
            </div>

            <h2 class="title is-4">Detalles de la Nota</h2>
            <div class="table-container">
                <table class="table is-striped is-fullwidth">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($nota['tipo'] == 'debito') : ?>
                            <tr>
                                <td colspan="3"><?php echo htmlspecialchars($nota['motivo']); ?></td>
                                <td><?php echo number_format($nota['monto'], 2); ?></td>
                            </tr>
                        <?php elseif (!empty($detalles)) : ?>
                            <?php foreach ($detalles as $detalle) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($detalle['producto_nombre']); ?></td>
                                    <td><?php echo number_format($detalle['cantidad'], 2); ?></td>
                                    <td><?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                                    <td><?php echo number_format($detalle['monto'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="4" class="has-text-centered">No hay detalles para esta nota de crédito.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="buttons">
                <button class="button is-success" onclick="window.print()">Imprimir</button>
                <button class="button is-link" onclick="window.history.back()">Volver</button>
            </div>
        </section>
    </div>
</body>

</html>
