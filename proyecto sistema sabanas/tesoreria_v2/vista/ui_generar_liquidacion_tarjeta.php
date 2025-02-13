<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Liquidación</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css">
    <link rel="stylesheet" href="../css/styles_T.css">

</head>
<body>
<div id="navbar-container"></div>

    <section class="section">
        <div class="container">
            <h1 class="title has-text-centered">Generar Liquidación de Tarjetas</h1>

            <div class="columns is-centered">
                <div class="column is-half">
                    <div class="box">
                        <form id="liquidacionForm">
                            <div class="field">
                                <label class="label">Fecha Desde</label>
                                <div class="control">
                                    <input class="input" type="date" id="fechaDesde" name="fecha_desde" required>
                                </div>
                            </div>

                            <div class="field">
                                <label class="label">Fecha Hasta</label>
                                <div class="control">
                                    <input class="input" type="date" id="fechaHasta" name="fecha_hasta" required>
                                </div>
                            </div>

                            <div class="field">
                                <label class="label">Procesadora</label>
                                <div class="control">
                                    <div class="select is-fullwidth">
                                        <select id="procesadoraSelect" name="id_procesadora" required>
                                            <option value="">Seleccione una procesadora</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="field">
                                <div class="control">
                                    <button class="button is-primary is-fullwidth mt-4" type="submit">Generar Liquidación</button>
                                </div>
                            </div>
                        </form>
                        <div id="mensaje" class="mt-3"></div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <script src="../js/liquidacion_reporte.js"></script>
    <script src="../js/navbarT.js"></script>

</body>
</html>
