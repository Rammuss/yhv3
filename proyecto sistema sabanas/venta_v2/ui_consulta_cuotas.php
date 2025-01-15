<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Cuotas Pendientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css" rel="stylesheet">
    <script src="navbar.js"></script>
    <link rel="stylesheet" href="../venta_v2/styles_venta.css">
</head>

<body>
<div id="navbar-container"></div>

    <div class="container">
        <h1 class="title is-3">Consultar Cuotas Pendientes</h1>

        <!-- Formulario de búsqueda -->
        <form action="" method="GET" class="box">
            <div class="field">
                <label class="label" for="ruc_ci">RUC/CI del Cliente:</label>
                <div class="control">
                    <input class="input" type="text" name="ruc_ci" id="ruc_ci" required>
                </div>
            </div>
            <div class="control">
                <button class="button is-link" type="submit">Consultar Cuotas</button>
            </div>
        </form>

        <!-- Resultados de la consulta -->
        <?php
        if (isset($_GET['ruc_ci'])) {
            include '../conexion/configv2.php';
            $ruc_ci = $_GET['ruc_ci'];

            if (!ctype_digit($ruc_ci)) {
                echo "<p class='has-text-danger'>Por favor, ingrese un RUC/CI válido (solo números).</p>";
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
                echo "<p class='has-text-warning'>No se encontraron ventas para el cliente con RUC/CI: $ruc_ci.</p>";
                return;
            }

            while ($row = pg_fetch_assoc($result_cliente)) {
                echo "<h3 class='subtitle is-4'>Venta ID: " . $row['id'] . " - Cliente: " . $row['nombre'] . "</h3>";
                echo "<p><strong>Fecha de Venta:</strong> " . $row['fecha'] . "</p>";
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
                echo "<p class='has-text-warning-black'>No hay cuotas pendientes para la venta ID: $venta_id.</p>";
                return;
            }

            echo "<table class='table is-striped is-bordered is-fullwidth'>
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
                        <button class='button is-info' onclick=\"mostrarFormularioPagar({$row['cuota_id']}, {$row['monto_restante']}, '{$ruc_ci}')\">Pagar</button>
                    </td>
                </tr>";
            }

            echo "</tbody></table>";
        }
        ?>

        <!-- Formulario de Pago (Oculto por defecto) -->
        <div id="formulario-pagar" class="modal">
            <div class="modal-background" onclick="cerrarFormulario()"></div>
            <div class="modal-content">
                <div class="box">
                    <form id="form-pago">
                        <input type="hidden" name="cuota_id" id="cuota_id">
                        <input type="hidden" name="ruc_ci_hidden" id="ruc_ci_hidden">

                        <div class="field">
                            <label class="label" for="monto">Monto a Pagar:</label>
                            <div class="control">
                                <input class="input" type="number" name="monto" id="monto" step="0.01" required>
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="forma_pago">Forma de Pago:</label>
                            <div class="control">
                                <div class="select">
                                    <select name="forma_pago" id="forma_pago" required>
                                        <option value="Efectivo">Efectivo</option>
                                        <option value="Transferencia">Transferencia</option>
                                        <option value="Tarjeta">Tarjeta</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="buttons">
                            <button class="button is-danger" type="button" onclick="cerrarFormulario()">Cancelar</button>
                            <button class="button is-success" type="submit">Confirmar Pago</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Muestra el formulario de pago con valores
        function mostrarFormularioPagar(cuota_id, monto, ruc_ci) {
            document.getElementById('cuota_id').value = cuota_id;
            document.getElementById('monto').value = monto;
            document.getElementById('ruc_ci_hidden').value = ruc_ci;
            document.getElementById('formulario-pagar').classList.add('is-active');
        }

        // Oculta el formulario de pago
        function cerrarFormulario() {
            document.getElementById('formulario-pagar').classList.remove('is-active');
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
</body>

</html>
