<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Ventas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css">
    <script src="../venta_v2/navbar.js"></script>
    <link rel="stylesheet" href="../venta_v2/styles_venta.css">
</head>

<body>
    <div id="navbar-container"></div>
    <section class="section">
        <div class="container">
            <h1 class="title">Buscar Ventas</h1>
            <div class="box">
                <form id="searchForm">
                    <div class="field">
                        <label class="label">RUC</label>
                        <div class="control">
                            <input class="input" type="text" name="ruc_ci" placeholder="Ingrese el RUC">
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Número de Factura</label>
                        <div class="control">
                            <input class="input" type="text" name="numero_factura" placeholder="Ingrese el número de factura">
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Fecha</label>
                        <div class="control">
                            <input class="input" type="date" name="fecha">
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Estado</label>
                        <div class="control">
                            <div class="select">
                                <select name="estado">
                                    <option value="">Seleccionar estado</option>
                                    <option value="pagado">Pagado</option>
                                    <option value="pendiente">Pendiente</option>
                                    <option value="anulado">Cancelado</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <div class="control">
                            <button class="button is-primary" type="submit">Buscar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <h2 class="title">Resultados de la Búsqueda</h2>
            <div class="box">
                <table class="table is-fullwidth is-striped">
                    <thead>
                        <tr>
                            <th>RUC</th>
                            <th>Número de Factura</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="resultBody">
                        <!-- Aquí se insertarán las filas de los resultados -->
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('searchForm').addEventListener('submit', function(event) {
                event.preventDefault(); // Evita que el formulario se envíe de manera tradicional
                const formData = new FormData(this);
                const jsonData = {};
                formData.forEach((value, key) => {
                    jsonData[key] = value;
                });

                fetch('buscar_facturas.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(jsonData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Datos recibidos:', data);
                        if (data.success) {
                            // Limpiar el tbody antes de agregar nuevas filas
                            const tbody = document.getElementById('resultBody');
                            tbody.innerHTML = '';
                            data.data.forEach(item => {
                                // Asignar clases de Bulma dependiendo del estado
                                let estadoClass = '';
                                switch (item.estado) {
                                    case 'pendiente':
                                        estadoClass = 'has-text-warning-black has-background-warning-light is-inline';
                                        break;
                                    case 'pagado':
                                        estadoClass = 'has-text-success-black has-background-success-light is-inline';
                                        break;
                                    case 'anulado':
                                        estadoClass = 'has-text-danger-black has-background-danger-light is-inline';
                                        break;
                                    default:
                                        estadoClass = 'has-text-grey has-background-light';
                                        break;
                                }

                                const row = document.createElement('tr');
                                row.innerHTML = `
                        <td>${item.cedula_cliente}</td>
                        <td>${item.numero_factura}</td>
                        <td>${item.fecha}</td>
                        <td class="${estadoClass}">${item.estado}</td>
                        <td>
                            <button class="button is-link" onclick="redirigir('${item.numero_factura}')">Ver</button>
                            <button class="button is-danger" onclick="anularFactura('${item.numero_factura}')">Anular</button>
                        </td>
                    `;
                                tbody.appendChild(row);
                            });
                        } else {
                            console.error('Error en la respuesta del servidor:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error al enviar los datos:', error);
                    });
            });
        });


        // Función para redirigir a la factura PDF
        function redirigir(numero_factura) {
            window.location.href = `../venta_v2/generar_factura_pdf.php?numero_factura=${numero_factura}`;
        }

        // Función para anular la factura
        function anularFactura(numero_factura) {
            if (confirm(`¿Estás seguro de que deseas anular la factura ${numero_factura}?`)) {
                // Realizamos la solicitud para actualizar el estado de la factura
                fetch('../venta_v2/anular_factura.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            numero_factura: numero_factura,
                            estado: 'anulado'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Factura anulada con éxito');
                            // Recargar las facturas después de la actualización
                            document.getElementById('searchForm').submit(); // Enviar de nuevo el formulario para obtener las facturas actualizadas
                        } else {
                            console.error('Error al anular la factura:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error al realizar la solicitud de anulación:', error);
                    });
            }
        }
    </script>
</body>

</html>