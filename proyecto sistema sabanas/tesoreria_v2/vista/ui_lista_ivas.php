<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de IVAs Generados</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css">
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">IVAs Generados</h1>

            <!-- Formulario de búsqueda -->
            <div class="box">
                <div class="field is-grouped">
                    <div class="control">
                        <input class="input" type="text" id="numero_factura" placeholder="Buscar por Número de Factura">
                    </div>
                    <div class="control">
                        <input class="input" type="date" id="fecha" placeholder="Buscar por Fecha">
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
                        <th>ID IVA</th>
                        <th>Número Factura</th>
                        <th>IVA 5%</th>
                        <th>IVA 10%</th>
                        <th>Fecha Generación</th>
                    </tr>
                </thead>
                <tbody id="resultados">
                    <!-- Los resultados se cargarán dinámicamente aquí -->
                </tbody>
            </table>
        </div>
    </section>
    <script defer src="../js/ivas.js"></script>

</body>
</html>
