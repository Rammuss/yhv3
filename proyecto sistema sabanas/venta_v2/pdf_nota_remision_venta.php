<?php
// Configuración de cabeceras
header('Content-Type: text/html; charset=utf-8');

// Incluir configuración de conexión
include '../conexion/configv2.php';

// Obtener el ID de la nota de remisión desde el parámetro de la URL
if (isset($_GET['idNota'])) {
    $idNota = $_GET['idNota'];
} else {
    echo "Error: Falta el ID de la nota de remisión.";
    exit;
}

// Consultar cabecera de la nota (nota_remision_venta_cabecera)
$sqlCabecera = "SELECT nrvc.id_remision, nrvc.numero_factura, nrvc.fecha, nrvc.estado, c.nombre
                FROM public.nota_remision_venta_cabecera nrvc
                JOIN public.clientes c ON nrvc.cliente_id = c.id_cliente
                WHERE nrvc.id_remision = $1";
$resultCabecera = pg_query_params($conn, $sqlCabecera, [$idNota]);

if (!$resultCabecera || pg_num_rows($resultCabecera) === 0) {
    echo "Error: No se encontró la nota de remisión.";
    exit;
}

$cabecera = pg_fetch_assoc($resultCabecera);

// Consultar detalles de la nota (nota_remision_venta_detalle)
$sqlDetalles = "SELECT nrvd.id, nrvd.producto_id, nrvd.cantidad, nrvd.precio_unitario, p.nombre AS producto_nombre
                FROM public.nota_remision_venta_detalle nrvd
                JOIN public.producto p ON nrvd.producto_id = p.id_producto
                WHERE nrvd.remision_id = $1";
$resultDetalles = pg_query_params($conn, $sqlDetalles, [$idNota]);

if (!$resultDetalles) {
    echo "Error: No se pudieron obtener los detalles de la nota.";
    exit;
}

$detalles = [];
while ($row = pg_fetch_assoc($resultDetalles)) {
    $detalles[] = $row;
}

// Generar HTML
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota de Remisión</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma/css/bulma.min.css">
    <style>
        .container { margin: 20px; }
        .table { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title">Nota de Remisión</h1>
        <p><strong>Número de Nota:</strong> <?= htmlspecialchars($cabecera['id_remision']) ?></p>
        <p><strong>Cliente:</strong> <?= htmlspecialchars($cabecera['nombre']) ?></p>
        <p><strong>Fecha:</strong> <?= htmlspecialchars($cabecera['fecha']) ?></p>
        <p><strong>Estado:</strong> <?= htmlspecialchars($cabecera['estado']) ?></p>
        <p><strong>Número de Factura:</strong> <?= htmlspecialchars($cabecera['numero_factura']) ?></p>
        
        <table class="table is-bordered is-striped is-hoverable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio Unitario</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $detalle): ?>
                <tr>
                    <td><?= htmlspecialchars($detalle['id']) ?></td>
                    <td><?= htmlspecialchars($detalle['producto_nombre']) ?></td>
                    <td><?= htmlspecialchars($detalle['cantidad']) ?></td>
                    <td><?= htmlspecialchars($detalle['precio_unitario']) ?></td>
                    <td><?= htmlspecialchars($detalle['cantidad'] * $detalle['precio_unitario']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="buttons">
            <button class="button is-primary" onclick="window.print()">Imprimir</button>
            <button class="button is-danger" onclick="window.history.back()">Volver</button>
        </div>
    </div>
</body>
</html>

<?php
// Cerrar la conexión
pg_close($conn);
?>
