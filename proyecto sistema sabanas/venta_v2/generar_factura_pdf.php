<?php
// Archivo: generar_factura.php

include '../conexion/configv2.php'; // Asegúrate de que este archivo tiene la configuración correcta de conexión

$numero_factura = $_GET['numero_factura'];

// Datos de la empresa ficticia
$empresa = [
    'nombre' => 'Empresa Ficticia S.A.',
    'direccion' => 'Av. Principal 123, Ciudad, País',
    'telefono' => '+595 1234 5678',
    'email' => 'contacto@empresaficticia.com'
];

// Consulta para obtener la cabecera de la factura
$query = "SELECT 
    v.numero_factura, 
    v.fecha, 
    v.forma_pago, 
    v.estado, 
    v.metodo_pago,
    v.timbrado, 
    c.nombre AS nombre_cliente, 
    c.apellido AS apellido_cliente, 
    c.ruc_ci AS cedula_cliente,
    v.nota_credito_id,
    v.monto_nc_aplicado
FROM ventas v
LEFT JOIN clientes c ON v.cliente_id = c.id_cliente
WHERE v.numero_factura = '$numero_factura'";
$result = pg_query($conn, $query);
$cabecera = pg_fetch_assoc($result);

// Consulta para obtener los detalles de la factura
$query_detalle = "SELECT 
    d.cantidad, 
    p.nombre AS producto, 
    d.precio_unitario
FROM detalle_venta d
LEFT JOIN producto p ON d.producto_id = p.id_producto
WHERE d.venta_id = (SELECT id FROM ventas WHERE numero_factura = '$numero_factura')";
$result_detalle = pg_query($conn, $query_detalle);

$detalles = [];
$total_factura = 0;
while ($row = pg_fetch_assoc($result_detalle)) {
    $row['subtotal'] = $row['cantidad'] * $row['precio_unitario'];
    $total_factura += $row['subtotal'];
    $detalles[] = $row;
}

// Calcular el total con la nota de crédito aplicada
$monto_nc_aplicado = $cabecera['monto_nc_aplicado'];
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
    <title>Factura</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css">
</head>

<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Factura</h1>
            <div class="box">
                <div id="factura">
                    <div class="columns">
                        <div class="column is-half">
                            <div class="field">
                                <label class="label">Número de Factura</label>
                                <div class="control">
                                    <span><?php echo $cabecera['numero_factura']; ?></span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Fecha</label>
                                <div class="control">
                                    <span><?php echo $cabecera['fecha']; ?></span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Cliente</label>
                                <div class="control">
                                    <span><?php echo $cabecera['nombre_cliente'] . ' ' . $cabecera['apellido_cliente']; ?></span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">RUC/CI</label>
                                <div class="control">
                                    <span><?php echo $cabecera['cedula_cliente']; ?></span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Forma de Pago</label>
                                <div class="control">
                                    <span><?php echo $cabecera['forma_pago']; ?></span>
                                </div>
                            </div>

                            <div class="field">
                                <label class="label">Método de Pago</label>
                                <div class="control">
                                    <span><?php echo $cabecera['metodo_pago']; ?></span>
                                </div>
                            </div>

                            <div class="field">
                                <label class="label">Estado</label>
                                <div class="control">
                                    <span><?php echo $cabecera['estado']; ?></span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Timbrado</label>
                                <div class="control">
                                    <span><?php echo $cabecera['timbrado']; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="column is-half">
                            <div class="field">
                                <label class="label">Empresa</label>
                                <div class="control">
                                    <span><?php echo $empresa['nombre']; ?></span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Dirección</label>
                                <div class="control">
                                    <span><?php echo $empresa['direccion']; ?></span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Teléfono</label>
                                <div class="control">
                                    <span><?php echo $empresa['telefono']; ?></span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Email</label>
                                <div class="control">
                                    <span><?php echo $empresa['email']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <table class="table is-fullwidth is-striped">
                        <thead>
                            <tr>
                                <th>Cantidad</th>
                                <th>Producto</th>
                                <th>Precio Unitario</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalles as $detalle) { ?>
                                <tr>
                                    <td><?php echo $detalle['cantidad']; ?></td>
                                    <td><?php echo $detalle['producto']; ?></td>
                                    <td><?php echo $detalle['precio_unitario']; ?></td>
                                    <td><?php echo $detalle['subtotal']; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3">Total</th>
                                <th><?php echo $total_factura; ?></th>
                            </tr>
                        </tfoot>
                    </table>

                    <?php if ($cabecera['nota_credito_id']) { ?>
                        <div class="field">
                            <label class="label">Número de Nota de Crédito</label>
                            <div class="control">
                                <span><?php echo $cabecera['nota_credito_id']; ?></span>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">Monto de Nota de Crédito</label>
                            <div class="control">
                                <span><?php echo $monto_nc_aplicado; ?></span>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">Total con Nota de Crédito</label>
                            <div class="control">
                                <span><?php echo $total_con_nc; ?></span>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="buttons">
                    <button class="button is-link" onclick="window.history.back()">Volver Atrás</button>
                    <button class="button is-primary" onclick="window.print()">Imprimir</button>
                </div>
            </div>
        </div>
    </section>
</body>

</html>