<?php
// Configuración de la conexión a la base de datos
require "../../conexion/config_pdo.php";

// Verificar que se haya enviado el parámetro id_factura vía GET
if (!isset($_GET['id_factura'])) {
    die("No se ha proporcionado un ID de factura.");
}

$id_factura = intval($_GET['id_factura']);

// Consulta para obtener la cabecera de la factura y el nombre del proveedor
$stmt = $pdo->prepare("
    SELECT fc.*, p.nombre
    FROM facturas_cabecera_t fc
    INNER JOIN proveedores p ON fc.id_proveedor = p.id_proveedor
    WHERE fc.id_factura = ?
");
$stmt->execute([$id_factura]);
$cabecera = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cabecera) {
    die("Factura no encontrada.");
}

// Consulta para obtener el detalle de la factura
$stmt_detalle = $pdo->prepare("SELECT * FROM facturas_detalle_t WHERE id_factura = ?");
$stmt_detalle->execute([$id_factura]);
$detalles = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura <?php echo htmlspecialchars($cabecera['numero_factura']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { margin-bottom: 20px; text-align: center; }
        table { border-collapse: collapse; width: 80%; margin: auto; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .botones { text-align: center; margin-top: 20px; }
        .botones button { padding: 10px 20px; margin: 0 10px; font-size: 16px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Factura: <?php echo htmlspecialchars($cabecera['numero_factura']); ?></h1>
        <p><strong>Fecha de Emisión:</strong> <?php echo htmlspecialchars($cabecera['fecha_emision']); ?></p>
        <p><strong>Proveedor:</strong> <?php echo htmlspecialchars($cabecera['nombre']); ?></p>
        <p><strong>Total:</strong> <?php echo htmlspecialchars($cabecera['total']); ?></p>
        <p><strong>Estado de Pago:</strong> <?php echo htmlspecialchars($cabecera['estado_pago']); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Descripción</th>
                <th>Cantidad</th>
                <th>Precio Unitario</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($detalles) > 0): ?>
                <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($detalle['descripcion']); ?></td>
                        <td><?php echo htmlspecialchars($detalle['cantidad']); ?></td>
                        <td><?php echo htmlspecialchars($detalle['precio_unitario']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">No hay detalles para esta factura.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="botones">
        <button onclick="window.history.back()">Atrás</button>
        <button onclick="window.print()">Imprimir</button>
    </div>
</body>
</html>
