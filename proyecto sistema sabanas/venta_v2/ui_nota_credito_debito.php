<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Notas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma/css/bulma.min.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .tab-content {
            display: none;
        }

        .tab-content.is-active {
            display: block;
        }
    </style>
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Gestión de Notas de Crédito y Débito</h1>

            <!-- Tabs -->
            <div class="tabs is-boxed">
                <ul>
                    <li id="tabGenerar" class="is-active" onclick="mostrarSeccion('generar')">
                        <a>Generar Nota</a>
                    </li>
                    <li id="tabConsultar" onclick="mostrarSeccion('consultar')">
                        <a>Consultar Notas</a>
                    </li>
                </ul>
            </div>

            <!-- Contenido de las secciones -->

            <!-- Sección: Generar Nota -->
            <div id="generar" class="tab-content is-active">
                <div class="box">
                    <h2 class="subtitle">Buscar Factura y Generar Nota</h2>

                    <!-- Filtros de búsqueda -->
                    <div class="columns">
                        <div class="column">
                            <div class="field">
                                <label class="label">Número de Factura</label>
                                <div class="control">
                                    <input class="input" type="text" id="numero-factura" placeholder="Ingrese número de factura">
                                </div>
                            </div>
                        </div>
                        <div class="column">
                            <div class="field">
                                <label class="label">Fecha</label>
                                <div class="control">
                                    <input class="input" type="date" id="fecha-factura">
                                </div>
                            </div>
                        </div>
                        <div class="column">
                            <div class="field">
                                <label class="label">Estado</label>
                                <div class="control">
                                    <div class="select">
                                        <select id="estado-factura">
                                            <option value="">Todos</option>
                                            <option value="Emitida">Emitida</option>
                                            <option value="Anulada">Anulada</option>
                                            <option value="Ajustada">Ajustada</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botón de búsqueda -->
                    <div class="field is-grouped">
                        <div class="control">
                            <button class="button is-info" onclick="buscarFacturas()">Buscar Facturas</button>
                        </div>
                    </div>

                    <!-- Tabla de Facturas -->
                    <table class="table is-fullwidth is-hoverable">
                        <thead>
                            <tr>
                                <th>ID Factura</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Saldo</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="facturas-table-body">
                            <!-- Datos cargados dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sección: Consultar Notas -->
            <div id="consultar" class="tab-content">
                <div class="box">
                    <h2 class="subtitle">Consultar Notas</h2>

                    <!-- Filtros de búsqueda -->
                    <div class="columns">
                        <div class="column">
                            <div class="field">
                                <label class="label">Número de Nota</label>
                                <div class="control">
                                    <input class="input" type="text" id="numero-nota" placeholder="Ingrese número de nota">
                                </div>
                            </div>
                        </div>
                        <div class="column">
                            <div class="field">
                                <label class="label">Número de Factura</label>
                                <div class="control">
                                    <input class="input" type="text" id="numero-factura-nota" placeholder="Ingrese número de factura">
                                </div>
                            </div>
                        </div>
                        <div class="column">
                            <div class="field">
                                <label class="label">Fecha</label>
                                <div class="control">
                                    <input class="input" type="date" id="fecha-nota">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botón de búsqueda -->
                    <div class="field is-grouped">
                        <div class="control">
                            <button class="button is-info" onclick="buscarNotas()">Buscar Notas</button>
                        </div>
                    </div>

                    <!-- Tabla de Notas -->
                    <table class="table is-fullwidth is-hoverable">
                        <thead>
                            <tr>
                                <th>ID Nota</th>
                                <th>ID Factura</th>
                                <th>Cliente</th>
                                <th>Monto</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="notas-table-body">
                            <!-- Datos cargados dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <script>
        function mostrarSeccion(seccion) {
            // Ocultar todas las secciones
            const contenidos = document.querySelectorAll('.tab-content');
            contenidos.forEach(contenido => contenido.classList.remove('is-active'));

            // Mostrar la sección seleccionada
            document.getElementById(seccion).classList.add('is-active');

            // Actualizar la pestaña activa
            const tabs = document.querySelectorAll('.tabs ul li');
            tabs.forEach(tab => tab.classList.remove('is-active'));

            // Marcar la pestaña seleccionada como activa
            document.getElementById('tab' + seccion.charAt(0).toUpperCase() + seccion.slice(1)).classList.add('is-active');
        }

        function buscarFacturas() {
            // Lógica para buscar facturas usando los filtros
            const numeroFactura = document.getElementById('numero-factura').value;
            const fechaFactura = document.getElementById('fecha-factura').value;
            const estadoFactura = document.getElementById('estado-factura').value;

            console.log(`Buscando facturas con: Número ${numeroFactura}, Fecha ${fechaFactura}, Estado ${estadoFactura}`);

            // Aquí deberías realizar una llamada al servidor para obtener los datos.
        }

        function buscarNotas() {
            // Lógica para buscar notas usando los filtros
            const numeroNota = document.getElementById('numero-nota').value;
            const numeroFacturaNota = document.getElementById('numero-factura-nota').value;
            const fechaNota = document.getElementById('fecha-nota').value;

            console.log(`Buscando notas con: Número ${numeroNota}, Factura ${numeroFacturaNota}, Fecha ${fechaNota}`);

            // Aquí deberías realizar una llamada al servidor para obtener los datos.
        }
    </script>
</body>
</html>
