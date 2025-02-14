<?php
include "../../conexion/configv2.php"; // Asegúrate de que la ruta es correcta

$query = "
    SELECT 
        p.id_pago, 
        p.id_orden_pago, 
        o.metodo_pago,  
        c.nombre_banco AS cuenta_bancaria, 
        p.monto, 
        p.fecha_ejecucion, 
        p.referencia_bancaria, 
        p.estado_conciliacion, 
        u.nombre_usuario AS usuario
    FROM pagos_ejecutados p
    LEFT JOIN ordenes_pago o ON p.id_orden_pago = o.id_orden_pago
    LEFT JOIN cuentas_bancarias c ON p.id_cuenta_bancaria = c.id_cuenta_bancaria
    LEFT JOIN usuarios u ON p.id_usuario = u.id
    ORDER BY p.fecha_ejecucion DESC";

$result = pg_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="es">

<head>
<link rel="stylesheet" href="../css/styles_T.css">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Pagos Ejecutados</title>
    <!-- Incluir Bulma CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css">
    <!-- Archivo de estilos personalizado -->
    <link rel="stylesheet" href="../../tesoreria_v2/css/styles_T.css">
   
</head>

<body>
<div id="navbar-container"></div>

    <section class="section">
        <div class="container">
            <h2 class="title has-text-centered">Lista de Pagos Ejecutados</h2>
            <div class="table-container">
                <table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
                    <thead>
                        <tr>
                            <th>ID Pago</th>
                            <th>Orden de Pago</th>
                            <th>Método de Pago</th>
                            <th>Cuenta Bancaria</th>
                            <th>Monto</th>
                            <th>Fecha de Ejecución</th>
                            <th>Referencia Bancaria</th>
                            <th>Estado Conciliación</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = pg_fetch_assoc($result)) {
                            // Asignar clase según el método de pago
                            $rowClass = "";
                            if ($row['metodo_pago'] == "Cheque") {
                                $rowClass = "cheque";
                            } elseif ($row['metodo_pago'] == "Transferencia") {
                                $rowClass = "transferencia";
                            } elseif ($row['metodo_pago'] == "Efectivo") {
                                $rowClass = "efectivo";
                            }
                        ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td><?php echo htmlspecialchars($row['id_pago']); ?></td>
                                <td><?php echo htmlspecialchars($row['id_orden_pago']); ?></td>
                                <td><?php echo htmlspecialchars($row['metodo_pago']); ?></td>
                                <td><?php echo htmlspecialchars($row['cuenta_bancaria']); ?></td>
                                <td><?php echo number_format($row['monto'], 2); ?> Gs</td>
                                <td><?php echo htmlspecialchars($row['fecha_ejecucion']); ?></td>
                                <td><?php echo htmlspecialchars($row['referencia_bancaria']); ?></td>
                                <td><?php echo htmlspecialchars($row['estado_conciliacion']); ?></td>
                                <td><?php echo htmlspecialchars($row['usuario']); ?></td>
                                <td>
                                    <?php
                                    if ($row['metodo_pago'] == "Cheque") {
                                        echo '<a href="../controlador/cheque_imprimir_ver.php?id_pago=' . $row['id_pago'] . '" class="button is-small is-info action-button">Ver Cheque</a>';
                                    } elseif ($row['metodo_pago'] == "Transferencia") {
                                        echo '<a href="../controlador/transfer_banco_imprimir.php?id_pago=' . $row['id_pago'] . '" class="button is-small is-info action-button">Ver Transferencia</a>';
                                    } elseif ($row['metodo_pago'] == "Efectivo") {
                                        echo '<a href="../controlador/efectivo_comprobante.php?id_pago=' . $row['id_pago'] . '" class="button is-small is-info action-button">Ver Efectivo</a>';
                                    } else {
                                        echo 'No disponible';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</body>
<script src="../js/navbarT.js"></script>


</html>