<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Cuotas Pendientes</title>

    <link rel="stylesheet" href="styles_venta.css"> <!-- Enlace a tu archivo CSS -->
    <script src="navbar.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        table,
        th,
        td {
            border: 1px solid #ddd;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f4f4f4;
        }

        #formulario-pagar {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff;
            padding: 20px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #ddd;
            z-index: 1000;
        }

        #formulario-pagar label {
            display: block;
            margin: 10px 0 5px;
        }

        #formulario-pagar input,
        #formulario-pagar select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
        }

        #formulario-pagar button {
            margin-right: 10px;
        }
    </style>

</head>

<body>
<div id="navbar-container"></div>

    <h1>Consultar Cuotas Pendientes</h1>

    <!-- Formulario de búsqueda -->
    <form action="" method="GET">
        <label for="ruc_ci">RUC/CI del Cliente:</label>
        <input type="text" name="ruc_ci" id="ruc_ci" required>
        <input type="submit" value="Consultar Cuotas">
    </form>

    <!-- Formulario de Pago (Oculto por defecto) -->
    <div id="formulario-pagar">
        <form id="form-pago">
            <input type="hidden" name="cuota_id" id="cuota_id">
            <input type="hidden" name="ruc_ci_hidden" id="ruc_ci_hidden">

            <label for="monto">Monto a Pagar:</label>
            <input type="number" name="monto" id="monto" step="0.01" required>

            <label for="forma_pago">Forma de Pago:</label>
            <select name="forma_pago" id="forma_pago" required>
                <option value="Efectivo">Efectivo</option>
                <option value="Transferencia">Transferencia</option>
                <option value="Tarjeta">Tarjeta</option>
            </select>

            <button type="button" onclick="cerrarFormulario()">Cancelar</button>
            <button type="submit">Confirmar Pago</button>
        </form>
    </div>

    <script>
        // Muestra el formulario de pago con valores
        function mostrarFormularioPagar(cuota_id, monto, ruc_ci) {
            document.getElementById('cuota_id').value = cuota_id;
            document.getElementById('monto').value = monto;
            document.getElementById('ruc_ci_hidden').value = ruc_ci;

            document.getElementById('formulario-pagar').style.display = 'block';
        }

        // Oculta el formulario de pago
        function cerrarFormulario() {
            document.getElementById('formulario-pagar').style.display = 'none';
        }

        // Procesa el pago mediante AJAX
        document.getElementById("form-pago").addEventListener("submit", function(event) {
    event.preventDefault(); // Evita que el formulario se envíe de forma tradicional

    // Crear un FormData con los datos del formulario
    const formData = new FormData(this);

    // Usar fetch() para enviar los datos al servidor
    fetch("procesar_pago.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json()) // Esperar respuesta JSON
        .then(data => {
            if (data.success) {
                alert(data.message); // Mostrar mensaje de éxito

                // Redirigir a la página de comprobante
                if (data.redirect) {
                    window.location.href = data.redirect;
                }
            } else {
                alert("Hubo un problema con el pago: " + data.error); // Mostrar mensaje de error
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Hubo un error al procesar el pago.");
        });
});

    </script>

    <?php
    if (isset($_GET['ruc_ci'])) {
        include '../conexion/configv2.php';
        $ruc_ci = $_GET['ruc_ci'];

        if (!ctype_digit($ruc_ci)) {
            echo "<p>Por favor, ingrese un RUC/CI válido (solo números).</p>";
        } else {
            obtenerCuotasPendientesPorRucCi($conn, $ruc_ci);
        }

        pg_close($conn);
    }

    function obtenerCuotasPendientesPorRucCi($conn, $ruc_ci)
    {
        $query_cliente = "
            SELECT v.id, v.fecha, c.nombre
            FROM ventas v
            JOIN clientes c ON v.cliente_id = c.id_cliente
            WHERE c.ruc_ci = $1;
        ";
        $result_cliente = pg_query_params($conn, $query_cliente, array($ruc_ci));

        if (!$result_cliente || pg_num_rows($result_cliente) == 0) {
            echo "<p>No se encontraron ventas para el cliente con RUC/CI: $ruc_ci.</p>";
            return;
        }

        while ($row = pg_fetch_assoc($result_cliente)) {
            echo "<h3>Venta ID: " . $row['id'] . " - Cliente: " . $row['nombre'] . "</h3>";
            echo "<p>Fecha de Venta: " . $row['fecha'] . "</p>";
            obtenerCuotasPendientesDeVenta($conn, $row['id'], $ruc_ci);
        }
    }

    function obtenerCuotasPendientesDeVenta($conn, $venta_id, $ruc_ci)
    {
        $query_cuotas = "
        SELECT 
            c.id AS cuota_id,
            c.numero_cuota,
            c.monto AS monto_total,
            COALESCE(SUM(p.monto_pago), 0) AS total_pagado,
            (c.monto - COALESCE(SUM(p.monto_pago), 0)) AS monto_restante,
            c.fecha_vencimiento
        FROM 
            cuentas_por_cobrar c
        LEFT JOIN 
            pagos p ON c.id = p.cuenta_id
        WHERE 
            c.venta_id = $1 AND c.estado = 'pendiente'
        GROUP BY 
            c.id, c.numero_cuota, c.monto, c.fecha_vencimiento;
        ";
        $result_cuotas = pg_query_params($conn, $query_cuotas, array($venta_id));

        if (!$result_cuotas || pg_num_rows($result_cuotas) == 0) {
            echo "<p>No hay cuotas pendientes para la venta ID: $venta_id.</p>";
            return;
        }

        echo "<table>
            <thead>
                <tr>
                    <th>Cuota</th>
                    <th>Monto Total</th>
                    <th>Total Pagado</th>
                    <th>Monto Restante</th>
                    <th>Fecha de Vencimiento</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>";

        while ($row = pg_fetch_assoc($result_cuotas)) {
            echo "<tr>
                <td>{$row['numero_cuota']}</td>
                <td>" . number_format($row['monto_total'], 2) . "</td>
                <td>" . number_format($row['total_pagado'], 2) . "</td>
                <td>" . number_format($row['monto_restante'], 2) . "</td>
                <td>{$row['fecha_vencimiento']}</td>
                <td>
                    <button onclick=\"mostrarFormularioPagar({$row['cuota_id']}, {$row['monto_restante']}, '{$ruc_ci}')\">Pagar</button>
                </td>
            </tr>";
        }

        echo "</tbody></table>";
    }
    ?>
</body>

</html>
