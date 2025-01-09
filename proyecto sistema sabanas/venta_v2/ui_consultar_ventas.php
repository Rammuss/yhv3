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
                                    <option value="Pagado">Pagado</option>
                                    <option value="Pendiente">Pendiente</option>
                                    <option value="Cancelado">Cancelado</option>
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
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${item.cedula_cliente}</td>
                                <td>${item.numero_factura}</td>
                                <td>${item.fecha}</td>
                                <td>${item.estado}</td>
                                <td>
                                    <button class="button is-link" onclick="redirigir('${item.numero_factura}')">Enviar</button>
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

        function redirigir(numero_factura) {
            // Redirigir a una nueva URL con el número de factura como parámetro
            window.location.href = `../venta_v2/generar_factura_pdf.php?numero_factura=${numero_factura}`;
        }
    </script>
</body>
</html>
