<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Facturas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="../css/styles_T.css">
    

</head>
<body>

<div id="navbar-container"></div>

    <section class="section">
        <div class="container">
            <h1 class="title">Lista de Facturas</h1>
            <h2 class="subtitle">Busca y gestiona las facturas cargadas en el sistema.</h2>

            <!-- Formulario de Búsqueda -->
            <form id="formulario-busqueda">
                <div class="columns">
                    <!-- Buscar por Fecha -->
                    <div class="column">
                        <div class="field">
                            <label class="label">Fecha</label>
                            <div class="control">
                                <input class="input" type="date" id="fecha-busqueda">
                            </div>
                        </div>
                    </div>

                    <!-- Buscar por Estado -->
                    <div class="column">
                        <div class="field">
                            <label class="label">Estado</label>
                            <div class="control">
                                <div class="select">
                                    <select id="estado-busqueda">
                                        <option value="">Todos</option>
                                        <option value="Pendiente">Pendiente</option>
                                        <option value="Pagado">Pagada</option>
                                        <option value="anulada">Anulada</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Buscar por Número de Factura -->
                    <div class="column">
                        <div class="field">
                            <label class="label">Número de Factura</label>
                            <div class="control">
                                <input class="input" type="text" id="numero-factura-busqueda" placeholder="Número de factura">
                            </div>
                        </div>
                    </div>

                    <!-- Buscar por Proveedor -->
                    <div class="column">
                        <div class="field">
                            <label class="label">Proveedor</label>
                            <div class="control">
                                <input class="input" type="text" id="proveedor-busqueda" placeholder="Nombre del proveedor">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botón de Búsqueda -->
                <div class="field">
                    <div class="control">
                        <button type="button" class="button is-primary" onclick="buscarFacturas()">Buscar</button>
                    </div>
                </div>
            </form>

            <!-- Tabla de Facturas -->
            <table class="table is-fullwidth">
                <thead>
                    <tr>
                        <th>Número de Factura</th>
                        <th>Proveedor</th>
                        <th>Fecha</th>
                        <th>Monto Total</th>
                        <th>Estado</th>
                        <th>Provisionada</th>
                        <th>Iva generado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-facturas">
                    <!-- Las facturas se cargarán dinámicamente desde JavaScript -->
                </tbody>
            </table>
        </div>
    </section>

    <script src="../js/listaFacturas.js"></script>
    <script src="../js/navbarT.js"></script>

</body>
</html>