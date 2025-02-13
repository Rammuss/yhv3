<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provisiones de Cuentas a Pagar</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css">
    <link rel="stylesheet" href="../css/styles_T.css">

</head>
<body>
<div id="navbar-container"></div>

    <section class="section">
        <div class="container">
            <h1 class="title">Provisiones de Cuentas a Pagar</h1>
            
            <!-- Formulario de búsqueda -->
            <div class="box">
                <div class="field is-grouped">
                    <div class="control">
                        <input class="input" type="date" id="fecha" placeholder="Buscar por fecha">
                    </div>
                    <div class="control">
                        <input class="input" type="text" id="ruc" placeholder="Buscar por RUC de Proveedor">
                    </div>
                    <div class="control">
                        <div class="select">
                            <select id="estado">
                                <option value="">Todos los estados</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="pagado">Pagado</option>
                                <option value="anulado">Anulado</option>
                            </select>
                        </div>
                    </div>
                    <div class="control">
                        <button class="button is-primary" id="buscar">Buscar</button>
                    </div>
                </div>
            </div>

            <!-- Tabla de resultados -->
            <table class="table is-fullwidth is-striped">
                <thead>
                    <tr>
                        <th>ID Provisión</th>
                        <th>ID Factura</th>
                        <th>ID Proveedor</th>
                        <th>Monto Provisionado</th>
                        <th>Tipo_provision</th>
                        <th>Estado</th>
                        <th>Fecha Creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="resultados">
                    <!-- Los resultados se cargarán aquí -->
                </tbody>
            </table>
        </div>
    </section>

    <script src="../js/provisiones.js"></script>
    <script src="../js/navbarT.js"></script>

</body>
</html>